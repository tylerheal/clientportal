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
        stripe_customer_id TEXT,
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
        fulfilment_status TEXT NOT NULL DEFAULT "open",
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
        stripe_customer TEXT,
        stripe_payment_method TEXT,
        stripe_subscription_id TEXT,
        paypal_subscription_id TEXT,
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
    ensure_order_fulfilment_status($pdo);
    ensure_invoice_sequence_column($pdo);
    ensure_user_payment_columns($pdo);
    ensure_subscription_payment_columns($pdo);
    ensure_service_payment_metadata($pdo);

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
    ensure_invoice_template_format($pdo);
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

function ensure_order_fulfilment_status(PDO $pdo): void
{
    $stmt = $pdo->query('PRAGMA table_info(orders)');
    $columns = $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];
    $names = array_map(static fn(array $column): string => (string) ($column['name'] ?? ''), $columns);

    if (in_array('fulfilment_status', $names, true)) {
        return;
    }

    $pdo->exec('ALTER TABLE orders ADD COLUMN fulfilment_status TEXT NOT NULL DEFAULT "open"');
    $pdo->exec('UPDATE orders SET fulfilment_status = "open" WHERE fulfilment_status IS NULL OR fulfilment_status = ""');
}

function ensure_user_payment_columns(PDO $pdo): void
{
    $stmt = $pdo->query('PRAGMA table_info(users)');
    $columns = $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];
    $names = array_map(static fn(array $column): string => (string) ($column['name'] ?? ''), $columns);

    if (!in_array('stripe_customer_id', $names, true)) {
        $pdo->exec('ALTER TABLE users ADD COLUMN stripe_customer_id TEXT');
    }
}

function ensure_subscription_payment_columns(PDO $pdo): void
{
    $stmt = $pdo->query('PRAGMA table_info(subscriptions)');
    $columns = $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];
    $names = array_map(static fn(array $column): string => (string) ($column['name'] ?? ''), $columns);

    if (!in_array('stripe_customer', $names, true)) {
        $pdo->exec('ALTER TABLE subscriptions ADD COLUMN stripe_customer TEXT');
    }

    if (!in_array('stripe_payment_method', $names, true)) {
        $pdo->exec('ALTER TABLE subscriptions ADD COLUMN stripe_payment_method TEXT');
    }

    if (!in_array('stripe_subscription_id', $names, true)) {
        $pdo->exec('ALTER TABLE subscriptions ADD COLUMN stripe_subscription_id TEXT');
    }

    if (!in_array('paypal_subscription_id', $names, true)) {
        $pdo->exec('ALTER TABLE subscriptions ADD COLUMN paypal_subscription_id TEXT');
    }
}

function ensure_service_payment_metadata(PDO $pdo): void
{
    $stmt = $pdo->query('PRAGMA table_info(services)');
    $columns = $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];
    $names = array_map(static fn(array $column): string => (string) ($column['name'] ?? ''), $columns);

    if (!in_array('payment_metadata', $names, true)) {
        $pdo->exec('ALTER TABLE services ADD COLUMN payment_metadata TEXT');
    }

    $pdo->exec('UPDATE services SET payment_metadata = "{}" WHERE payment_metadata IS NULL');
}

function seed_default_admin(PDO $pdo): void
{
    $config = require __DIR__ . '/config.php';
    $default = $config['default_admin'];

    $totalAdmins = (int) $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'admin'")->fetchColumn();
    if ($totalAdmins > 0) {
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
        'brand_surface_color' => '#f4f6fb',
        'brand_card_color' => '#ffffff',
        'brand_control_color' => '#eef2ff',
        'brand_border_color' => '#dce1eb',
        'brand_text_color' => '#111827',
        'brand_muted_color' => '#6b7280',
        'mail_from_name' => 'Service Portal',
        'mail_from_address' => 'no-reply@example.com',
        'mail_transport' => 'mail',
        'smtp_host' => '',
        'smtp_port' => '587',
        'smtp_username' => '',
        'smtp_encryption' => 'tls',
        'smtp_password' => '',
        'email_template_order' => "Hi {{name}},\n\nThanks for your order of {{service}}. We'll let you know once it's underway.\n\nRegards,\n{{company}}",
        'email_template_ticket_reply' => "Hi {{name}},\n\nWe've responded to your support ticket '{{subject}}'.\n\n{{message}}\n\nRegards,\n{{company}}",
        'stripe_publishable_key' => '',
        'stripe_secret_key' => '',
        'paypal_client_id' => '',
        'paypal_client_secret' => '',
        'paypal_mode' => 'sandbox',
        'payments_enable_paypal' => '0',
        'payments_enable_stripe' => '0',
        'payments_enable_google_pay' => '0',
        'currency_code' => 'GBP',
        'support_email' => 'support@example.com',
        'brand_url' => '',
        'brand_address' => '',
        'brand_support_url' => ''
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
    $orderConfirmationBody = <<<'HTML'
<!doctype html>
<html lang="en" xmlns="http://www.w3.org/1999/xhtml" xmlns:v="urn:schemas-microsoft-com:vml" xmlns:o="urn:schemas-microsoft-com:office:office">
  <head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta http-equiv="x-ua-compatible" content="ie=edge">
    <title>Order received – {{order.number}}</title>
    <style>
body{margin:0!important;padding:0!important;background:#f6f7fb;color:#1b1f23;}
img{border:0;outline:none;text-decoration:none;display:block;max-width:100%;height:auto;}
table{border-collapse:collapse!important;}
a{color:#0b5fff;text-decoration:none;}
.container{width:100%;background:#f6f7fb;padding:24px;}
.card{max-width:640px;margin:0 auto;background:#ffffff;border:1px solid #eef0f4;border-radius:12px;overflow:hidden;}
.px{padding-left:24px;padding-right:24px;}
.py{padding-top:24px;padding-bottom:24px;}
.muted{color:#6a7380;}
.btn{display:inline-block;padding:12px 18px;border-radius:8px;background:#0b5fff;color:#ffffff;font-weight:600;}
.badge{display:inline-block;padding:4px 10px;border-radius:999px;background:#242a36;color:#e7eaf0;font-size:12px;}
.hr{height:1px;background:#eef0f4;border:none;margin:24px 0;}
.text-right{text-align:right;}
.small{font-size:12px;}
.h1{font-size:20px;line-height:1.4;margin:0 0 8px 0;font-weight:700;color:#101318;}
.h2{font-size:16px;line-height:1.5;margin:0 0 8px 0;font-weight:700;color:#101318;}
@media (max-width:480px){
  .px{padding-left:16px!important;padding-right:16px!important;}
  .py{padding-top:16px!important;padding-bottom:16px!important;}
  .h1{font-size:18px!important;}
}
    </style>
    <!--[if mso]>
      <style>.btn{padding:0!important;}</style>
    <![endif]-->
  </head>
  <body>
    <div style="display:none;overflow:hidden;line-height:1px;opacity:0;max-height:0;max-width:0;">Thanks for your order. We’ll email updates as things progress.&#8203;</div>
    <center class="container">
      <table role="presentation" class="card" width="100%" cellpadding="0" cellspacing="0">
        <tr>
          <td class="px py" style="background:#131620;">
            <table role="presentation" width="100%">
              <tr>
                <td style="vertical-align:middle;">
                  <img src="{{brand.logo_url}}" alt="{{brand.name}}" width="128">
                </td>
                <td class="text-right" style="vertical-align:middle;">
                  <span class="badge">Order {{order.number}}</span>
                </td>
              </tr>
            </table>
          </td>
        </tr>
        <tr>
          <td class="px py">
            <p class="h1">Thanks for your order, {{client.first_name}}</p>
            <p>We’ve received your order <strong>{{order.number}}</strong> placed on {{order.date}}.</p>
            <table role="presentation" width="100%" style="width:100%;border:1px solid #eef0f4;border-radius:8px;">
              <tr><td class="px py">
                <p class="h2">Items</p>
                {{order.items_html}}
                <hr class="hr"/>
                <table role="presentation" width="100%">
                  <tr><td class="muted">Subtotal</td><td class="text-right">{{order.subtotal}}</td></tr>
                  <tr><td class="muted">VAT</td><td class="text-right">{{order.vat}}</td></tr>
                  <tr><td><strong>Total</strong></td><td class="text-right"><strong>{{order.total}}</strong></td></tr>
                </table>
              </td></tr>
            </table>
            <p style="margin:24px 0;"><a class="btn" href="{{order.url}}">View Order</a></p>
            <p class="small muted">Payment method: {{order.payment_method}}</p>
          </td>
        </tr>
        <tr>
          <td class="px py" style="background:#ffffff;">
            <hr class="hr"/>
            <p class="small muted" style="margin:0 0 6px 0;">
              {{brand.name}} • <a href="{{brand.url}}">{{brand.url}}</a> • <a href="mailto:{{brand.email}}">{{brand.email}}</a>
            </p>
            <p class="small muted" style="margin:0 0 6px 0;">{{brand.address}}</p>
            <p class="small muted" style="margin:0;">You’re receiving this because you have an account with {{brand.name}}. <a href="{{brand.support_url}}">Get help</a>.</p>
          </td>
        </tr>
      </table>
    </center>
  </body>
</html>
HTML;

    $ticketReplyBody = <<<'HTML'
<!doctype html>
<html lang="en" xmlns="http://www.w3.org/1999/xhtml" xmlns:v="urn:schemas-microsoft-com:vml" xmlns:o="urn:schemas-microsoft-com:office:office">
  <head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta http-equiv="x-ua-compatible" content="ie=edge">
    <title>Update on ticket #{{ticket.id}}</title>
    <style>
body{margin:0!important;padding:0!important;background:#f6f7fb;color:#1b1f23;}
img{border:0;outline:none;text-decoration:none;display:block;max-width:100%;height:auto;}
table{border-collapse:collapse!important;}
a{color:#0b5fff;text-decoration:none;}
.container{width:100%;background:#f6f7fb;padding:24px;}
.card{max-width:640px;margin:0 auto;background:#ffffff;border:1px solid #eef0f4;border-radius:12px;overflow:hidden;}
.px{padding-left:24px;padding-right:24px;}
.py{padding-top:24px;padding-bottom:24px;}
.muted{color:#6a7380;}
.btn{display:inline-block;padding:12px 18px;border-radius:8px;background:#0b5fff;color:#ffffff;font-weight:600;}
.badge{display:inline-block;padding:4px 10px;border-radius:999px;background:#242a36;color:#e7eaf0;font-size:12px;}
.hr{height:1px;background:#eef0f4;border:none;margin:24px 0;}
.text-right{text-align:right;}
.small{font-size:12px;}
.h1{font-size:20px;line-height:1.4;margin:0 0 8px 0;font-weight:700;color:#101318;}
.h2{font-size:16px;line-height:1.5;margin:0 0 8px 0;font-weight:700;color:#101318;}
@media (max-width:480px){
  .px{padding-left:16px!important;padding-right:16px!important;}
  .py{padding-top:16px!important;padding-bottom:16px!important;}
  .h1{font-size:18px!important;}
}
    </style>
    <!--[if mso]>
      <style>.btn{padding:0!important;}</style>
    <![endif]-->
  </head>
  <body>
    <div style="display:none;overflow:hidden;line-height:1px;opacity:0;max-height:0;max-width:0;">Our team replied: “{{message.snippet}}”&#8203;</div>
    <center class="container">
      <table role="presentation" class="card" width="100%" cellpadding="0" cellspacing="0">
        <tr>
          <td class="px py" style="background:#131620;">
            <table role="presentation" width="100%">
              <tr>
                <td style="vertical-align:middle;">
                  <img src="{{brand.logo_url}}" alt="{{brand.name}}" width="128">
                </td>
                <td class="text-right" style="vertical-align:middle;">
                  <span class="badge">Ticket #{{ticket.id}}</span>
                </td>
              </tr>
            </table>
          </td>
        </tr>
        <tr>
          <td class="px py">
            <p class="h1">There’s an update on your ticket, {{client.first_name}}</p>
            <p>We’ve replied to <strong>#{{ticket.id}}</strong>. Here’s a preview:</p>
            <table role="presentation" width="100%" style="background:#ffffff;border:1px solid #eef0f4;border-radius:8px;">
              <tr><td class="px py">
                <p style="margin:0;font-style:italic;color:#3a4556;">“{{message.snippet}}”</p>
              </td></tr>
            </table>
            <p style="margin:24px 0;"><a class="btn" href="{{ticket.url}}">View &amp; Reply</a></p>
          </td>
        </tr>
        <tr>
          <td class="px py" style="background:#ffffff;">
            <hr class="hr"/>
            <p class="small muted" style="margin:0 0 6px 0;">
              {{brand.name}} • <a href="{{brand.url}}">{{brand.url}}</a> • <a href="mailto:{{brand.email}}">{{brand.email}}</a>
            </p>
            <p class="small muted" style="margin:0 0 6px 0;">{{brand.address}}</p>
            <p class="small muted" style="margin:0;">You’re receiving this because you have an account with {{brand.name}}. <a href="{{brand.support_url}}">Get help</a>.</p>
          </td>
        </tr>
      </table>
    </center>
  </body>
</html>
HTML;

    $ticketOpenedBody = <<<'HTML'
<!doctype html>
<html lang="en" xmlns="http://www.w3.org/1999/xhtml" xmlns:v="urn:schemas-microsoft-com:vml" xmlns:o="urn:schemas-microsoft-com:office:office">
  <head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta http-equiv="x-ua-compatible" content="ie=edge">
    <title>We’ve opened your ticket #{{ticket.id}} – {{ticket.subject}}</title>
    <style>
body{margin:0!important;padding:0!important;background:#f6f7fb;color:#1b1f23;}
img{border:0;outline:none;text-decoration:none;display:block;max-width:100%;height:auto;}
table{border-collapse:collapse!important;}
a{color:#0b5fff;text-decoration:none;}
.container{width:100%;background:#f6f7fb;padding:24px;}
.card{max-width:640px;margin:0 auto;background:#ffffff;border:1px solid #eef0f4;border-radius:12px;overflow:hidden;}
.px{padding-left:24px;padding-right:24px;}
.py{padding-top:24px;padding-bottom:24px;}
.muted{color:#6a7380;}
.btn{display:inline-block;padding:12px 18px;border-radius:8px;background:#0b5fff;color:#ffffff;font-weight:600;}
.badge{display:inline-block;padding:4px 10px;border-radius:999px;background:#242a36;color:#e7eaf0;font-size:12px;}
.hr{height:1px;background:#eef0f4;border:none;margin:24px 0;}
.text-right{text-align:right;}
.small{font-size:12px;}
.h1{font-size:20px;line-height:1.4;margin:0 0 8px 0;font-weight:700;color:#101318;}
.h2{font-size:16px;line-height:1.5;margin:0 0 8px 0;font-weight:700;color:#101318;}
@media (max-width:480px){
  .px{padding-left:16px!important;padding-right:16px!important;}
  .py{padding-top:16px!important;padding-bottom:16px!important;}
  .h1{font-size:18px!important;}
}
    </style>
    <!--[if mso]>
      <style>.btn{padding:0!important;}</style>
    <![endif]-->
  </head>
  <body>
    <div style="display:none;overflow:hidden;line-height:1px;opacity:0;max-height:0;max-width:0;">Thanks for getting in touch. We’ll reply shortly.&#8203;</div>
    <center class="container">
      <table role="presentation" class="card" width="100%" cellpadding="0" cellspacing="0">
        <tr>
          <td class="px py" style="background:#131620;">
            <table role="presentation" width="100%">
              <tr>
                <td style="vertical-align:middle;">
                  <img src="{{brand.logo_url}}" alt="{{brand.name}}" width="128">
                </td>
                <td class="text-right" style="vertical-align:middle;">
                  <span class="badge">Ticket #{{ticket.id}}</span>
                </td>
              </tr>
            </table>
          </td>
        </tr>
        <tr>
          <td class="px py">
            <p class="h1">Thanks {{client.first_name}}, we’ve opened your ticket</p>
            <p>Ticket <strong>#{{ticket.id}}</strong> is now <strong>{{ticket.status}}</strong> with priority <strong>{{ticket.priority}}</strong>.</p>
            <p>We’ll take a look and get back to you. You can follow progress or reply at any time.</p>
            <p style="margin:24px 0;"><a class="btn" href="{{ticket.url}}">View Ticket</a></p>
            <table role="presentation" width="100%" style="background:#fafbff;border:1px solid #eef0f4;border-radius:8px;">
              <tr><td class="px py">
                <p class="h2" style="margin-top:0;">Summary</p>
                <p><strong>Subject:</strong> {{ticket.subject}}<br/>
                   <strong>Ticket ID:</strong> {{ticket.id}}<br/>
                   <strong>Status:</strong> {{ticket.status}}<br/>
                   <strong>Priority:</strong> {{ticket.priority}}</p>
              </td></tr>
            </table>
          </td>
        </tr>
        <tr>
          <td class="px py" style="background:#ffffff;">
            <hr class="hr"/>
            <p class="small muted" style="margin:0 0 6px 0;">
              {{brand.name}} • <a href="{{brand.url}}">{{brand.url}}</a> • <a href="mailto:{{brand.email}}">{{brand.email}}</a>
            </p>
            <p class="small muted" style="margin:0 0 6px 0;">{{brand.address}}</p>
            <p class="small muted" style="margin:0;">You’re receiving this because you have an account with {{brand.name}}. <a href="{{brand.support_url}}">Get help</a>.</p>
          </td>
        </tr>
      </table>
    </center>
  </body>
</html>
HTML;

    $invoiceCreatedBody = <<<'HTML'
<!doctype html>
<html lang="en" xmlns="http://www.w3.org/1999/xhtml" xmlns:v="urn:schemas-microsoft-com:vml" xmlns:o="urn:schemas-microsoft-com:office:office">
  <head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta http-equiv="x-ua-compatible" content="ie=edge">
    <title>Invoice {{invoice.number}} for {{service}}</title>
    <style>
body{margin:0!important;padding:0!important;background:#f6f7fb;color:#1b1f23;}
img{border:0;outline:none;text-decoration:none;display:block;max-width:100%;height:auto;}
table{border-collapse:collapse!important;}
a{color:#0b5fff;text-decoration:none;}
.container{width:100%;background:#f6f7fb;padding:24px;}
.card{max-width:640px;margin:0 auto;background:#ffffff;border:1px solid #eef0f4;border-radius:12px;overflow:hidden;}
.px{padding-left:24px;padding-right:24px;}
.py{padding-top:24px;padding-bottom:24px;}
.muted{color:#6a7380;}
.btn{display:inline-block;padding:12px 18px;border-radius:8px;background:#0b5fff;color:#ffffff;font-weight:600;}
.badge{display:inline-block;padding:4px 10px;border-radius:999px;background:#242a36;color:#e7eaf0;font-size:12px;}
.hr{height:1px;background:#eef0f4;border:none;margin:24px 0;}
.text-right{text-align:right;}
.small{font-size:12px;}
.h1{font-size:20px;line-height:1.4;margin:0 0 8px 0;font-weight:700;color:#101318;}
.h2{font-size:16px;line-height:1.5;margin:0 0 8px 0;font-weight:700;color:#101318;}
@media (max-width:480px){
  .px{padding-left:16px!important;padding-right:16px!important;}
  .py{padding-top:16px!important;padding-bottom:16px!important;}
  .h1{font-size:18px!important;}
}
    </style>
  </head>
  <body>
    <div style="display:none;overflow:hidden;line-height:1px;opacity:0;max-height:0;max-width:0;">Invoice {{invoice.number}} for {{service}} is now available.&#8203;</div>
    <center class="container">
      <table role="presentation" class="card" width="100%" cellpadding="0" cellspacing="0">
        <tr>
          <td class="px py" style="background:#131620;">
            <table role="presentation" width="100%">
              <tr>
                <td style="vertical-align:middle;">
                  <img src="{{brand.logo_url}}" alt="{{brand.name}}" width="128">
                </td>
                <td class="text-right" style="vertical-align:middle;">
                  <span class="badge">Invoice {{invoice.number}}</span>
                </td>
              </tr>
            </table>
          </td>
        </tr>
        <tr>
          <td class="px py">
            <p class="h1">New invoice issued, {{client.first_name}}</p>
            <p>We’ve raised invoice <strong>{{invoice.number}}</strong> for {{service}}. The total due is {{invoice.total}} and it is payable by {{invoice.due_date}}.</p>
            <table role="presentation" width="100%" style="background:#fafbff;border:1px solid #eef0f4;border-radius:8px;">
              <tr><td class="px py">
                <p class="h2">Invoice summary</p>
                <p><strong>Issued:</strong> {{invoice.date}}<br/>
                   <strong>Due:</strong> {{invoice.due_date}}<br/>
                   <strong>Status:</strong> {{invoice.status}}</p>
                <hr class="hr"/>
                <table role="presentation" width="100%">
                  {{order.items_html}}
                  <tr><td class="muted">Total</td><td class="text-right"><strong>{{invoice.total}}</strong></td></tr>
                </table>
              </td></tr>
            </table>
            <p style="margin:24px 0;"><a class="btn" href="{{invoice.url}}">Review Invoice</a></p>
          </td>
        </tr>
        <tr>
          <td class="px py" style="background:#ffffff;">
            <hr class="hr"/>
            <p class="small muted" style="margin:0 0 6px 0;">
              {{brand.name}} • <a href="{{brand.url}}">{{brand.url}}</a> • <a href="mailto:{{brand.email}}">{{brand.email}}</a>
            </p>
            <p class="small muted" style="margin:0 0 6px 0;">{{brand.address}}</p>
            <p class="small muted" style="margin:0;">You’re receiving this because you have an account with {{brand.name}}. <a href="{{brand.support_url}}">Get help</a>.</p>
          </td>
        </tr>
      </table>
    </center>
  </body>
</html>
HTML;

    $invoicePaymentBody = <<<'HTML'
<!doctype html>
<html lang="en" xmlns="http://www.w3.org/1999/xhtml" xmlns:v="urn:schemas-microsoft-com:vml" xmlns:o="urn:schemas-microsoft-com:office:office">
  <head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta http-equiv="x-ua-compatible" content="ie=edge">
    <title>Payment received – #{{invoice.number}} ({{invoice.total}})</title>
    <style>
body{margin:0!important;padding:0!important;background:#f6f7fb;color:#1b1f23;}
img{border:0;outline:none;text-decoration:none;display:block;max-width:100%;height:auto;}
table{border-collapse:collapse!important;}
a{color:#0b5fff;text-decoration:none;}
.container{width:100%;background:#f6f7fb;padding:24px;}
.card{max-width:640px;margin:0 auto;background:#ffffff;border:1px solid #eef0f4;border-radius:12px;overflow:hidden;}
.px{padding-left:24px;padding-right:24px;}
.py{padding-top:24px;padding-bottom:24px;}
.muted{color:#6a7380;}
.btn{display:inline-block;padding:12px 18px;border-radius:8px;background:#0b5fff;color:#ffffff;font-weight:600;}
.badge{display:inline-block;padding:4px 10px;border-radius:999px;background:#242a36;color:#e7eaf0;font-size:12px;}
.hr{height:1px;background:#eef0f4;border:none;margin:24px 0;}
.text-right{text-align:right;}
.small{font-size:12px;}
.h1{font-size:20px;line-height:1.4;margin:0 0 8px 0;font-weight:700;color:#101318;}
.h2{font-size:16px;line-height:1.5;margin:0 0 8px 0;font-weight:700;color:#101318;}
@media (max-width:480px){
  .px{padding-left:16px!important;padding-right:16px!important;}
  .py{padding-top:16px!important;padding-bottom:16px!important;}
  .h1{font-size:18px!important;}
}
    </style>
  </head>
  <body>
    <div style="display:none;overflow:hidden;line-height:1px;opacity:0;max-height:0;max-width:0;">Thanks! We’ve applied your payment.&#8203;</div>
    <center class="container">
      <table role="presentation" class="card" width="100%" cellpadding="0" cellspacing="0">
        <tr>
          <td class="px py" style="background:#131620;">
            <table role="presentation" width="100%">
              <tr>
                <td style="vertical-align:middle;">
                  <img src="{{brand.logo_url}}" alt="{{brand.name}}" width="128">
                </td>
                <td class="text-right" style="vertical-align:middle;">
                  <span class="badge">Paid</span>
                </td>
              </tr>
            </table>
          </td>
        </tr>
        <tr>
          <td class="px py">
            <p class="h1">Payment received</p>
            <p>Thanks! We’ve received your payment for invoice <strong>{{invoice.number}}</strong>.</p>
            <p><strong>Amount:</strong> {{invoice.total}}</p>
            <p style="margin:24px 0;"><a class="btn" href="{{invoice.url}}">Download Invoice</a></p>
            <p class="small muted">If you didn’t make this payment, contact us immediately at <a href="mailto:{{brand.email}}">{{brand.email}}</a>.</p>
          </td>
        </tr>
        <tr>
          <td class="px py" style="background:#ffffff;">
            <hr class="hr"/>
            <p class="small muted" style="margin:0 0 6px 0;">
              {{brand.name}} • <a href="{{brand.url}}">{{brand.url}}</a> • <a href="mailto:{{brand.email}}">{{brand.email}}</a>
            </p>
            <p class="small muted" style="margin:0 0 6px 0;">{{brand.address}}</p>
            <p class="small muted" style="margin:0;">You’re receiving this because you have an account with {{brand.name}}. <a href="{{brand.support_url}}">Get help</a>.</p>
          </td>
        </tr>
      </table>
    </center>
  </body>
</html>
HTML;

    $invoiceOverdueBody = <<<'HTML'
<!doctype html>
<html lang="en" xmlns="http://www.w3.org/1999/xhtml" xmlns:v="urn:schemas-microsoft-com:vml" xmlns:o="urn:schemas-microsoft-com:office:office">
  <head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta http-equiv="x-ua-compatible" content="ie=edge">
    <title>Payment overdue – Invoice {{invoice.number}}</title>
    <style>
body{margin:0!important;padding:0!important;background:#f6f7fb;color:#1b1f23;}
img{border:0;outline:none;text-decoration:none;display:block;max-width:100%;height:auto;}
table{border-collapse:collapse!important;}
a{color:#0b5fff;text-decoration:none;}
.container{width:100%;background:#f6f7fb;padding:24px;}
.card{max-width:640px;margin:0 auto;background:#ffffff;border:1px solid #eef0f4;border-radius:12px;overflow:hidden;}
.px{padding-left:24px;padding-right:24px;}
.py{padding-top:24px;padding-bottom:24px;}
.muted{color:#6a7380;}
.btn{display:inline-block;padding:12px 18px;border-radius:8px;background:#0b5fff;color:#ffffff;font-weight:600;}
.badge{display:inline-block;padding:4px 10px;border-radius:999px;background:#b91c1c;color:#ffffff;font-size:12px;}
.hr{height:1px;background:#eef0f4;border:none;margin:24px 0;}
.text-right{text-align:right;}
.small{font-size:12px;}
.h1{font-size:20px;line-height:1.4;margin:0 0 8px 0;font-weight:700;color:#101318;}
.h2{font-size:16px;line-height:1.5;margin:0 0 8px 0;font-weight:700;color:#101318;}
@media (max-width:480px){
  .px{padding-left:16px!important;padding-right:16px!important;}
  .py{padding-top:16px!important;padding-bottom:16px!important;}
  .h1{font-size:18px!important;}
}
    </style>
  </head>
  <body>
    <div style="display:none;overflow:hidden;line-height:1px;opacity:0;max-height:0;max-width:0;">Invoice {{invoice.number}} for {{service}} is overdue.&#8203;</div>
    <center class="container">
      <table role="presentation" class="card" width="100%" cellpadding="0" cellspacing="0">
        <tr>
          <td class="px py" style="background:#131620;">
            <table role="presentation" width="100%">
              <tr>
                <td style="vertical-align:middle;">
                  <img src="{{brand.logo_url}}" alt="{{brand.name}}" width="128">
                </td>
                <td class="text-right" style="vertical-align:middle;">
                  <span class="badge">Overdue</span>
                </td>
              </tr>
            </table>
          </td>
        </tr>
        <tr>
          <td class="px py">
            <p class="h1">Invoice overdue – {{invoice.number}}</p>
            <p>Invoice <strong>{{invoice.number}}</strong> for {{service}} is now overdue. Please arrange payment at your earliest convenience.</p>
            <table role="presentation" width="100%" style="background:#fafbff;border:1px solid #eef0f4;border-radius:8px;">
              <tr><td class="px py">
                <p class="h2">Invoice details</p>
                <p><strong>Issued:</strong> {{invoice.date}}<br/>
                   <strong>Due:</strong> {{invoice.due_date}}<br/>
                   <strong>Status:</strong> {{invoice.status}}</p>
                <hr class="hr"/>
                <table role="presentation" width="100%">
                  {{order.items_html}}
                  <tr><td class="muted">Balance due</td><td class="text-right"><strong>{{invoice.total}}</strong></td></tr>
                </table>
              </td></tr>
            </table>
            <p style="margin:24px 0;"><a class="btn" href="{{invoice.url}}">Pay Invoice</a></p>
          </td>
        </tr>
        <tr>
          <td class="px py" style="background:#ffffff;">
            <hr class="hr"/>
            <p class="small muted" style="margin:0 0 6px 0;">
              {{brand.name}} • <a href="{{brand.url}}">{{brand.url}}</a> • <a href="mailto:{{brand.email}}">{{brand.email}}</a>
            </p>
            <p class="small muted" style="margin:0 0 6px 0;">{{brand.address}}</p>
            <p class="small muted" style="margin:0;">You’re receiving this because you have an account with {{brand.name}}. <a href="{{brand.support_url}}">Get help</a>.</p>
          </td>
        </tr>
      </table>
    </center>
  </body>
</html>
HTML;

    $adminNewOrderBody = <<<'HTML'
<!doctype html>
<html lang="en" xmlns="http://www.w3.org/1999/xhtml" xmlns:v="urn:schemas-microsoft-com:vml" xmlns:o="urn:schemas-microsoft-com:office:office">
  <head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta http-equiv="x-ua-compatible" content="ie=edge">
    <title>New order {{order.number}} – {{client.full_name}} ({{order.total}})</title>
    <style>
body{margin:0!important;padding:0!important;background:#f6f7fb;color:#1b1f23;}
img{border:0;outline:none;text-decoration:none;display:block;max-width:100%;height:auto;}
table{border-collapse:collapse!important;}
a{color:#0b5fff;text-decoration:none;}
.container{width:100%;background:#f6f7fb;padding:24px;}
.card{max-width:640px;margin:0 auto;background:#ffffff;border:1px solid #eef0f4;border-radius:12px;overflow:hidden;}
.px{padding-left:24px;padding-right:24px;}
.py{padding-top:24px;padding-bottom:24px;}
.muted{color:#6a7380;}
.btn{display:inline-block;padding:12px 18px;border-radius:8px;background:#0b5fff;color:#ffffff;font-weight:600;}
.badge{display:inline-block;padding:4px 10px;border-radius:999px;background:#242a36;color:#e7eaf0;font-size:12px;}
.hr{height:1px;background:#eef0f4;border:none;margin:24px 0;}
.text-right{text-align:right;}
.small{font-size:12px;}
.h1{font-size:20px;line-height:1.4;margin:0 0 8px 0;font-weight:700;color:#101318;}
.h2{font-size:16px;line-height:1.5;margin:0 0 8px 0;font-weight:700;color:#101318;}
@media (max-width:480px){
  .px{padding-left:16px!important;padding-right:16px!important;}
  .py{padding-top:16px!important;padding-bottom:16px!important;}
  .h1{font-size:18px!important;}
}
    </style>
  </head>
  <body>
    <center class="container">
      <table role="presentation" class="card" width="100%" cellpadding="0" cellspacing="0">
        <tr>
          <td class="px py" style="background:#131620;">
            <table role="presentation" width="100%">
              <tr>
                <td style="vertical-align:middle;">
                  <img src="{{brand.logo_url}}" alt="{{brand.name}}" width="128">
                </td>
                <td class="text-right" style="vertical-align:middle;">
                  <span class="badge">Order {{order.number}}</span>
                </td>
              </tr>
            </table>
          </td>
        </tr>
        <tr>
          <td class="px py">
            <p class="h1">New order received</p>
            <p><strong>Order:</strong> {{order.number}} on {{order.date}}</p>
            <p><strong>Client:</strong> {{client.full_name}} ({{client.email}})</p>
            <table role="presentation" width="100%" style="border:1px solid #eef0f4;border-radius:8px;">
              <tr><td class="px py">
                <p class="h2">Items</p>
                {{order.items_html}}
                <hr class="hr"/>
                <table role="presentation" width="100%">
                  <tr><td class="muted">Total</td><td class="text-right"><strong>{{order.total}}</strong></td></tr>
                  <tr><td class="muted">Payment</td><td class="text-right">{{order.payment_method}}</td></tr>
                </table>
              </td></tr>
            </table>
            <p style="margin:24px 0;"><a class="btn" href="{{order.url}}">Open in Portal</a></p>
          </td>
        </tr>
      </table>
    </center>
  </body>
</html>
HTML;

    $adminNewTicketBody = <<<'HTML'
<!doctype html>
<html lang="en" xmlns="http://www.w3.org/1999/xhtml" xmlns:v="urn:schemas-microsoft-com:vml" xmlns:o="urn:schemas-microsoft-com:office:office">
  <head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta http-equiv="x-ua-compatible" content="ie=edge">
    <title>New ticket #{{ticket.id}} – {{ticket.subject}}</title>
    <style>
body{margin:0!important;padding:0!important;background:#f6f7fb;color:#1b1f23;}
img{border:0;outline:none;text-decoration:none;display:block;max-width:100%;height:auto;}
table{border-collapse:collapse!important;}
a{color:#0b5fff;text-decoration:none;}
.container{width:100%;background:#f6f7fb;padding:24px;}
.card{max-width:640px;margin:0 auto;background:#ffffff;border:1px solid #eef0f4;border-radius:12px;overflow:hidden;}
.px{padding-left:24px;padding-right:24px;}
.py{padding-top:24px;padding-bottom:24px;}
.muted{color:#6a7380;}
.btn{display:inline-block;padding:12px 18px;border-radius:8px;background:#0b5fff;color:#ffffff;font-weight:600;}
.badge{display:inline-block;padding:4px 10px;border-radius:999px;background:#242a36;color:#e7eaf0;font-size:12px;}
.hr{height:1px;background:#eef0f4;border:none;margin:24px 0;}
.h1{font-size:20px;line-height:1.4;margin:0 0 8px 0;font-weight:700;color:#101318;}
@media (max-width:480px){
  .px{padding-left:16px!important;padding-right:16px!important;}
  .py{padding-top:16px!important;padding-bottom:16px!important;}
  .h1{font-size:18px!important;}
}
    </style>
  </head>
  <body>
    <center class="container">
      <table role="presentation" class="card" width="100%" cellpadding="0" cellspacing="0">
        <tr>
          <td class="px py" style="background:#131620;">
            <table role="presentation" width="100%">
              <tr>
                <td style="vertical-align:middle;">
                  <img src="{{brand.logo_url}}" alt="{{brand.name}}" width="128">
                </td>
                <td class="text-right" style="vertical-align:middle;">
                  <span class="badge">New Ticket</span>
                </td>
              </tr>
            </table>
          </td>
        </tr>
        <tr>
          <td class="px py">
            <p class="h1">New support ticket</p>
            <p><strong>#{{ticket.id}}</strong> from {{client.full_name}} ({{client.email}})</p>
            <p><strong>Subject:</strong> {{ticket.subject}} • <strong>Priority:</strong> {{ticket.priority}}</p>
            <p style="margin:24px 0;"><a class="btn" href="{{ticket.url}}">Open Ticket</a></p>
          </td>
        </tr>
      </table>
    </center>
  </body>
</html>
HTML;

    $adminTicketReplyBody = <<<'HTML'
<!doctype html>
<html lang="en" xmlns="http://www.w3.org/1999/xhtml" xmlns:v="urn:schemas-microsoft-com:vml" xmlns:o="urn:schemas-microsoft-com:office:office">
  <head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta http-equiv="x-ua-compatible" content="ie=edge">
    <title>Client replied on #{{ticket.id}}</title>
    <style>
body{margin:0!important;padding:0!important;background:#f6f7fb;color:#1b1f23;}
img{border:0;outline:none;text-decoration:none;display:block;max-width:100%;height:auto;}
table{border-collapse:collapse!important;}
a{color:#0b5fff;text-decoration:none;}
.container{width:100%;background:#f6f7fb;padding:24px;}
.card{max-width:640px;margin:0 auto;background:#ffffff;border:1px solid #eef0f4;border-radius:12px;overflow:hidden;}
.px{padding-left:24px;padding-right:24px;}
.py{padding-top:24px;padding-bottom:24px;}
.muted{color:#6a7380;}
.btn{display:inline-block;padding:12px 18px;border-radius:8px;background:#0b5fff;color:#ffffff;font-weight:600;}
.badge{display:inline-block;padding:4px 10px;border-radius:999px;background:#242a36;color:#e7eaf0;font-size:12px;}
.hr{height:1px;background:#eef0f4;border:none;margin:24px 0;}
.h1{font-size:20px;line-height:1.4;margin:0 0 8px 0;font-weight:700;color:#101318;}
@media (max-width:480px){
  .px{padding-left:16px!important;padding-right:16px!important;}
  .py{padding-top:16px!important;padding-bottom:16px!important;}
  .h1{font-size:18px!important;}
}
    </style>
  </head>
  <body>
    <center class="container">
      <table role="presentation" class="card" width="100%" cellpadding="0" cellspacing="0">
        <tr>
          <td class="px py" style="background:#131620;">
            <table role="presentation" width="100%">
              <tr>
                <td style="vertical-align:middle;">
                  <img src="{{brand.logo_url}}" alt="{{brand.name}}" width="128">
                </td>
                <td class="text-right" style="vertical-align:middle;">
                  <span class="badge">Ticket #{{ticket.id}}</span>
                </td>
              </tr>
            </table>
          </td>
        </tr>
        <tr>
          <td class="px py">
            <p class="h1">Client replied</p>
            <p>Ticket <strong>#{{ticket.id}}</strong> has a new reply from {{client.full_name}}.</p>
            <table role="presentation" width="100%" style="background:#ffffff;border:1px solid #eef0f4;border-radius:8px;">
              <tr><td class="px py">
                <p style="margin:0;color:#3a4556;">“{{message.snippet}}”</p>
              </td></tr>
            </table>
            <p style="margin:24px 0;"><a class="btn" href="{{ticket.url}}">Reply in Portal</a></p>
          </td>
        </tr>
      </table>
    </center>
  </body>
</html>
HTML;

    $adminPaymentSuccessBody = <<<'HTML'
<!doctype html>
<html lang="en" xmlns="http://www.w3.org/1999/xhtml" xmlns:v="urn:schemas-microsoft-com:vml" xmlns:o="urn:schemas-microsoft-com:office:office">
  <head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta http-equiv="x-ua-compatible" content="ie=edge">
    <title>Payment success – {{invoice.number}} ({{invoice.total}})</title>
    <style>
body{margin:0!important;padding:0!important;background:#f6f7fb;color:#1b1f23;}
img{border:0;outline:none;text-decoration:none;display:block;max-width:100%;height:auto;}
table{border-collapse:collapse!important;}
a{color:#0b5fff;text-decoration:none;}
.container{width:100%;background:#f6f7fb;padding:24px;}
.card{max-width:640px;margin:0 auto;background:#ffffff;border:1px solid #eef0f4;border-radius:12px;overflow:hidden;}
.px{padding-left:24px;padding-right:24px;}
.py{padding-top:24px;padding-bottom:24px;}
.muted{color:#6a7380;}
.btn{display:inline-block;padding:12px 18px;border-radius:8px;background:#0b5fff;color:#ffffff;font-weight:600;}
.badge{display:inline-block;padding:4px 10px;border-radius:999px;background:#242a36;color:#e7eaf0;font-size:12px;}
.hr{height:1px;background:#eef0f4;border:none;margin:24px 0;}
.h1{font-size:20px;line-height:1.4;margin:0 0 8px 0;font-weight:700;color:#101318;}
@media (max-width:480px){
  .px{padding-left:16px!important;padding-right:16px!important;}
  .py{padding-top:16px!important;padding-bottom:16px!important;}
  .h1{font-size:18px!important;}
}
    </style>
  </head>
  <body>
    <center class="container">
      <table role="presentation" class="card" width="100%" cellpadding="0" cellspacing="0">
        <tr>
          <td class="px py" style="background:#131620;">
            <table role="presentation" width="100%">
              <tr>
                <td style="vertical-align:middle;">
                  <img src="{{brand.logo_url}}" alt="{{brand.name}}" width="128">
                </td>
                <td class="text-right" style="vertical-align:middle;">
                  <span class="badge">Paid</span>
                </td>
              </tr>
            </table>
          </td>
        </tr>
        <tr>
          <td class="px py">
            <p class="h1">Payment received</p>
            <p>Order <strong>{{order.number}}</strong> has been paid by {{client.full_name}}.</p>
            <p><strong>Amount:</strong> {{invoice.total}}</p>
            <p style="margin:24px 0;"><a class="btn" href="{{order.url}}">Open Order</a></p>
          </td>
        </tr>
      </table>
    </center>
  </body>
</html>
HTML;

    $defaults = [
        [
            'slug' => 'order_confirmation',
            'name' => 'Order confirmation',
            'subject' => 'Order received – {{order.number}}',
            'body' => $orderConfirmationBody,
        ],
        [
            'slug' => 'ticket_reply',
            'name' => 'Ticket reply',
            'subject' => 'Ticket update: {{ticket.subject}}',
            'body' => $ticketReplyBody,
        ],
        [
            'slug' => 'client_ticket_opened',
            'name' => 'Ticket opened (client)',
            'subject' => 'We’ve opened your ticket #{{ticket.id}}',
            'body' => $ticketOpenedBody,
        ],
        [
            'slug' => 'invoice_created',
            'name' => 'Invoice created',
            'subject' => 'Invoice {{invoice.number}} for {{service}}',
            'body' => $invoiceCreatedBody,
        ],
        [
            'slug' => 'invoice_payment_success',
            'name' => 'Invoice payment success',
            'subject' => 'Payment received – Invoice {{invoice.number}}',
            'body' => $invoicePaymentBody,
        ],
        [
            'slug' => 'invoice_overdue',
            'name' => 'Invoice overdue',
            'subject' => 'Payment overdue – Invoice {{invoice.number}}',
            'body' => $invoiceOverdueBody,
        ],
        [
            'slug' => 'admin_new_order',
            'name' => 'Admin new order',
            'subject' => 'New order {{order.number}} – {{client.full_name}}',
            'body' => $adminNewOrderBody,
        ],
        [
            'slug' => 'admin_new_ticket',
            'name' => 'Admin new ticket',
            'subject' => 'New ticket #{{ticket.id}} – {{ticket.subject}}',
            'body' => $adminNewTicketBody,
        ],
        [
            'slug' => 'admin_ticket_reply',
            'name' => 'Admin ticket reply',
            'subject' => 'Client replied on #{{ticket.id}}',
            'body' => $adminTicketReplyBody,
        ],
        [
            'slug' => 'admin_payment_success',
            'name' => 'Admin payment success',
            'subject' => 'Payment received – {{invoice.number}} ({{invoice.total}})',
            'body' => $adminPaymentSuccessBody,
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

function ensure_invoice_sequence_column(PDO $pdo): void
{
    $columns = $pdo->query('PRAGMA table_info(invoices)');
    $schema = $columns ? $columns->fetchAll(PDO::FETCH_ASSOC) : [];
    $hasSequence = false;
    foreach ($schema as $column) {
        if (($column['name'] ?? '') === 'sequence') {
            $hasSequence = true;
            break;
        }
    }

    if (!$hasSequence) {
        $pdo->exec('ALTER TABLE invoices ADD COLUMN sequence INTEGER');
    }

    $needsBackfillStmt = $pdo->query('SELECT COUNT(*) FROM invoices WHERE sequence IS NULL OR sequence <= 0');
    $needsBackfill = (int) ($needsBackfillStmt ? $needsBackfillStmt->fetchColumn() : 0);
    if ($needsBackfill === 0) {
        return;
    }

    $pdo->beginTransaction();
    try {
        $rows = $pdo->query('SELECT id, user_id, created_at FROM invoices ORDER BY user_id, created_at, id');
        $records = $rows ? $rows->fetchAll(PDO::FETCH_ASSOC) : [];
        $update = $pdo->prepare('UPDATE invoices SET sequence = :sequence WHERE id = :id');
        $currentUser = null;
        $currentSequence = 0;
        foreach ($records as $row) {
            $userId = (int) ($row['user_id'] ?? 0);
            if ($currentUser === null || $userId !== $currentUser) {
                $currentUser = $userId;
                $currentSequence = 0;
            }
            $currentSequence++;
            $update->execute([
                'sequence' => $currentSequence,
                'id' => (int) $row['id'],
            ]);
        }
        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }
}

function ensure_invoice_template_format(PDO $pdo): void
{
    $templates = [
        'invoice_payment_success' => [
            'subject' => ['Payment received – Invoice #{{invoice}}', 'Payment received – Invoice {{invoice}}'],
            'body' => [
                "Hi {{name}},\n\nWe've received your payment for invoice #{{invoice}} covering {{service}}. Thank you!\n\nRegards,\n{{company}}",
                "Hi {{name}},\n\nWe've received your payment for invoice {{invoice}} covering {{service}}. Thank you!\n\nRegards,\n{{company}}",
            ],
        ],
        'invoice_created' => [
            'subject' => ['Invoice #{{invoice}} for {{service}}', 'Invoice {{invoice}} for {{service}}'],
            'body' => [
                "Hi {{name}},\n\nWe've raised invoice #{{invoice}} for {{service}}. The total due is {{amount}} and it is payable by {{due_date}}.\n\nRegards,\n{{company}}",
                "Hi {{name}},\n\nWe've raised invoice {{invoice}} for {{service}}. The total due is {{amount}} and it is payable by {{due_date}}.\n\nRegards,\n{{company}}",
            ],
        ],
        'invoice_overdue' => [
            'subject' => ['Payment overdue – Invoice #{{invoice}}', 'Payment overdue – Invoice {{invoice}}'],
            'body' => [
                "Hi {{name}},\n\nInvoice #{{invoice}} for {{service}} is now overdue. Please complete payment as soon as possible.\n\nRegards,\n{{company}}",
                "Hi {{name}},\n\nInvoice {{invoice}} for {{service}} is now overdue. Please complete payment as soon as possible.\n\nRegards,\n{{company}}",
            ],
        ],
    ];

    $now = (new \DateTimeImmutable())->format(\DateTimeInterface::ATOM);
    foreach ($templates as $slug => $template) {
        $stmt = $pdo->prepare('SELECT subject, body FROM email_templates WHERE slug = :slug LIMIT 1');
        $stmt->execute(['slug' => $slug]);
        if ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $updates = [];
            if (($row['subject'] ?? '') === $template['subject'][0]) {
                $updates['subject'] = $template['subject'][1];
            }
            if (($row['body'] ?? '') === $template['body'][0]) {
                $updates['body'] = $template['body'][1];
            }
            if ($updates) {
                $updates['updated_at'] = $now;
                $updates['slug'] = $slug;
                $sets = [];
                foreach (array_keys($updates) as $key) {
                    if ($key === 'slug') {
                        continue;
                    }
                    $sets[] = $key . ' = :' . $key;
                }
                if ($sets) {
                    $updatesSql = 'UPDATE email_templates SET ' . implode(', ', $sets) . ' WHERE slug = :slug';
                    $updateStmt = $pdo->prepare($updatesSql);
                    $updateStmt->execute($updates);
                }
            }
        }
    }
}
