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
        created_at TEXT NOT NULL,
        updated_at TEXT NOT NULL
    )');

    $pdo->exec('CREATE TABLE IF NOT EXISTS services (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        name TEXT NOT NULL,
        description TEXT,
        price REAL NOT NULL DEFAULT 0,
        form_schema TEXT,
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

    seed_default_admin($pdo);
    seed_default_settings($pdo);
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
