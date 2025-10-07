<?php
declare(strict_types=1);

function get_db(): PDO
{
    static $pdo = null;
    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $dataDir = __DIR__ . '/data';
    if (!is_dir($dataDir)) {
        mkdir($dataDir, 0775, true);
    }

    $dsn = 'sqlite:' . $dataDir . '/portal.sqlite3';
    $pdo = new PDO($dsn);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    $pdo->exec('PRAGMA foreign_keys = ON');

    initialise_schema($pdo);

    return $pdo;
}

function initialise_schema(PDO $pdo): void
{
    $pdo->exec('CREATE TABLE IF NOT EXISTS users (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        email TEXT NOT NULL UNIQUE,
        password_hash TEXT NOT NULL,
        name TEXT NOT NULL,
        company TEXT,
        phone TEXT,
        role TEXT NOT NULL DEFAULT "client",
        settings JSON,
        totp_secret TEXT,
        totp_enabled INTEGER NOT NULL DEFAULT 0,
        totp_recovery_codes TEXT,
        avatar_url TEXT,
        created_at TEXT NOT NULL,
        updated_at TEXT NOT NULL
    )');

    $pdo->exec('CREATE TABLE IF NOT EXISTS services (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        name TEXT NOT NULL,
        description TEXT,
        price REAL NOT NULL DEFAULT 0,
        form_schema TEXT,
        billing_interval TEXT NOT NULL DEFAULT "one_time",
        active INTEGER NOT NULL DEFAULT 1,
        created_by INTEGER,
        created_at TEXT NOT NULL,
        updated_at TEXT NOT NULL,
        FOREIGN KEY(created_by) REFERENCES users(id)
    )');

    $pdo->exec('CREATE TABLE IF NOT EXISTS orders (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        user_id INTEGER NOT NULL,
        service_id INTEGER NOT NULL,
        payment_method TEXT NOT NULL,
        payment_status TEXT NOT NULL DEFAULT "pending",
        total_amount REAL NOT NULL,
        form_data TEXT,
        payment_reference TEXT,
        billing_interval TEXT NOT NULL DEFAULT "one_time",
        created_at TEXT NOT NULL,
        updated_at TEXT NOT NULL,
        FOREIGN KEY(user_id) REFERENCES users(id),
        FOREIGN KEY(service_id) REFERENCES services(id)
    )');

    $pdo->exec('CREATE TABLE IF NOT EXISTS tickets (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        user_id INTEGER NOT NULL,
        subject TEXT NOT NULL,
        status TEXT NOT NULL DEFAULT "open",
        created_at TEXT NOT NULL,
        updated_at TEXT NOT NULL,
        FOREIGN KEY(user_id) REFERENCES users(id)
    )');

    $pdo->exec('CREATE TABLE IF NOT EXISTS ticket_messages (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        ticket_id INTEGER NOT NULL,
        user_id INTEGER NOT NULL,
        message TEXT NOT NULL,
        created_at TEXT NOT NULL,
        FOREIGN KEY(ticket_id) REFERENCES tickets(id),
        FOREIGN KEY(user_id) REFERENCES users(id)
    )');

    $pdo->exec('CREATE TABLE IF NOT EXISTS settings (
        key TEXT PRIMARY KEY,
        value TEXT
    )');

    $pdo->exec('CREATE TABLE IF NOT EXISTS email_templates (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        slug TEXT NOT NULL UNIQUE,
        name TEXT NOT NULL,
        subject TEXT NOT NULL,
        body TEXT NOT NULL,
        created_at TEXT NOT NULL,
        updated_at TEXT NOT NULL
    )');

    $pdo->exec('CREATE TABLE IF NOT EXISTS notifications (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        user_id INTEGER NOT NULL,
        message TEXT NOT NULL,
        link TEXT,
        created_at TEXT NOT NULL,
        read_at TEXT,
        FOREIGN KEY(user_id) REFERENCES users(id)
    )');

    $pdo->exec('CREATE TABLE IF NOT EXISTS subscriptions (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        order_id INTEGER NOT NULL,
        user_id INTEGER NOT NULL,
        service_id INTEGER NOT NULL,
        interval TEXT NOT NULL,
        next_billing_at TEXT NOT NULL,
        status TEXT NOT NULL DEFAULT "active",
        created_at TEXT NOT NULL,
        updated_at TEXT NOT NULL,
        FOREIGN KEY(order_id) REFERENCES orders(id),
        FOREIGN KEY(user_id) REFERENCES users(id),
        FOREIGN KEY(service_id) REFERENCES services(id)
    )');

    $pdo->exec('CREATE TABLE IF NOT EXISTS invoices (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        subscription_id INTEGER,
        order_id INTEGER,
        user_id INTEGER NOT NULL,
        service_id INTEGER NOT NULL,
        total REAL NOT NULL,
        status TEXT NOT NULL DEFAULT "pending",
        due_at TEXT NOT NULL,
        paid_at TEXT,
        created_at TEXT NOT NULL,
        updated_at TEXT NOT NULL,
        FOREIGN KEY(subscription_id) REFERENCES subscriptions(id),
        FOREIGN KEY(order_id) REFERENCES orders(id),
        FOREIGN KEY(user_id) REFERENCES users(id),
        FOREIGN KEY(service_id) REFERENCES services(id)
    )');

    ensure_nullable_invoice_subscription($pdo);

    $pdo->exec('CREATE TABLE IF NOT EXISTS payments (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        invoice_id INTEGER NOT NULL,
        provider TEXT NOT NULL,
        reference TEXT,
        amount REAL NOT NULL,
        status TEXT NOT NULL DEFAULT "initiated",
        created_at TEXT NOT NULL,
        updated_at TEXT NOT NULL,
        FOREIGN KEY(invoice_id) REFERENCES invoices(id)
    )');

    seed_default_admin($pdo);
    seed_default_settings($pdo);
    seed_default_templates($pdo);
}

function ensure_nullable_invoice_subscription(PDO $pdo): void
{
    $stmt = $pdo->query('PRAGMA table_info(invoices)');
    $columns = $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];
    $needsMigration = false;

    foreach ($columns as $column) {
        if (($column['name'] ?? '') === 'subscription_id') {
            if ((int) ($column['notnull'] ?? 0) === 1) {
                $needsMigration = true;
            }
            break;
        }
    }

    if (!$needsMigration) {
        return;
    }

    $pdo->exec('PRAGMA foreign_keys = OFF');

    try {
        $pdo->beginTransaction();
        $pdo->exec('DROP TABLE IF EXISTS invoices_tmp');
        $pdo->exec('CREATE TABLE invoices_tmp (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            subscription_id INTEGER,
            order_id INTEGER,
            user_id INTEGER NOT NULL,
            service_id INTEGER NOT NULL,
            total REAL NOT NULL,
            status TEXT NOT NULL DEFAULT "pending",
            due_at TEXT NOT NULL,
            paid_at TEXT,
            created_at TEXT NOT NULL,
            updated_at TEXT NOT NULL,
            FOREIGN KEY(subscription_id) REFERENCES subscriptions(id),
            FOREIGN KEY(order_id) REFERENCES orders(id),
            FOREIGN KEY(user_id) REFERENCES users(id),
            FOREIGN KEY(service_id) REFERENCES services(id)
        )');

        $pdo->exec('INSERT INTO invoices_tmp (id, subscription_id, order_id, user_id, service_id, total, status, due_at, paid_at, created_at, updated_at)
            SELECT id, subscription_id, order_id, user_id, service_id, total, status, due_at, paid_at, created_at, updated_at FROM invoices');

        $pdo->exec('DROP TABLE invoices');
        $pdo->exec('ALTER TABLE invoices_tmp RENAME TO invoices');
        $pdo->commit();
    } catch (\Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $pdo->exec('DROP TABLE IF EXISTS invoices_tmp');
        throw $e;
    } finally {
        $pdo->exec('PRAGMA foreign_keys = ON');
    }
}

function seed_default_admin(PDO $pdo): void
{
    $config = require __DIR__ . '/config.php';
    $default = $config['default_admin'];

    $stmt = $pdo->prepare('SELECT id FROM users WHERE email = :email LIMIT 1');
    $stmt->execute(['email' => $default['email']]);
    if ($stmt->fetchColumn()) {
        return;
    }

    $now = (new \DateTimeImmutable())->format(\DateTimeInterface::ATOM);
    $passwordHash = password_hash($default['password'], PASSWORD_DEFAULT);

    $insert = $pdo->prepare('INSERT INTO users (email, password_hash, name, role, created_at, updated_at)
        VALUES (:email, :password_hash, :name, :role, :created_at, :updated_at)');
    $insert->execute([
        'email' => $default['email'],
        'password_hash' => $passwordHash,
        'name' => $default['name'],
        'role' => 'admin',
        'created_at' => $now,
        'updated_at' => $now,
    ]);
}

function seed_default_settings(PDO $pdo): void
{
    $defaults = [
        'company_name' => 'Service Portal',
        'brand_logo_url' => '',
        'brand_primary_color' => '#3b82f6',
        'brand_font_family' => 'Inter, sans-serif',
        'email_template_order' => "Hi {{name}},\n\nThanks for your order of {{service}}. We'll let you know once it's underway.\n\nRegards,\n{{company}}",
        'email_template_ticket_reply' => "Hi {{name}},\n\nWe've responded to your support ticket '{{subject}}'.\n\n{{message}}\n\nRegards,\n{{company}}",
        'stripe_publishable_key' => '',
        'stripe_secret_key' => '',
        'paypal_client_id' => '',
        'paypal_client_secret' => '',
        'paypal_mode' => 'sandbox',
        'currency_code' => 'GBP',
        'support_email' => 'support@example.com'
    ];

    $select = $pdo->prepare('SELECT key FROM settings WHERE key = :key');
    $insert = $pdo->prepare('INSERT INTO settings (key, value) VALUES (:key, :value)');

    foreach ($defaults as $key => $value) {
        $select->execute(['key' => $key]);
        if (!$select->fetchColumn()) {
            $insert->execute(['key' => $key, 'value' => $value]);
        }
    }
}

function seed_default_templates(PDO $pdo): void
{
    $now = (new \DateTimeImmutable())->format(\DateTimeInterface::ATOM);
    $defaults = [
        [
            'slug' => 'order_confirmation',
            'name' => 'Order confirmation',
            'subject' => 'Order received – {{service}}',
            'body' => "Hi {{name}},\n\nThanks for your order of {{service}}. We'll let you know once it's underway.\n\nRegards,\n{{company}}",
        ],
        [
            'slug' => 'ticket_reply',
            'name' => 'Ticket reply',
            'subject' => 'Ticket update: {{subject}}',
            'body' => "Hi {{name}},\n\nWe've responded to your support ticket '{{subject}}'.\n\n{{message}}\n\nRegards,\n{{company}}",
        ],
        [
            'slug' => 'invoice_payment_success',
            'name' => 'Invoice payment success',
            'subject' => 'Payment received – Invoice #{{invoice}}',
            'body' => "Hi {{name}},\n\nWe've received your payment for invoice #{{invoice}} covering {{service}}. Thank you!\n\nRegards,\n{{company}}",
        ],
        [
            'slug' => 'invoice_overdue',
            'name' => 'Invoice overdue',
            'subject' => 'Payment overdue – Invoice #{{invoice}}',
            'body' => "Hi {{name}},\n\nInvoice #{{invoice}} for {{service}} is now overdue. Please complete payment as soon as possible.\n\nRegards,\n{{company}}",
        ],
    ];

    $insert = $pdo->prepare('INSERT OR IGNORE INTO email_templates (slug, name, subject, body, created_at, updated_at) VALUES (:slug, :name, :subject, :body, :created_at, :updated_at)');
    foreach ($defaults as $template) {
        $insert->execute([
            'slug' => $template['slug'],
            'name' => $template['name'],
            'subject' => $template['subject'],
            'body' => $template['body'],
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }
}
