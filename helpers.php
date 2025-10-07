<?php
declare(strict_types=1);

function redirect(string $path): void
{
    header('Location: ' . $path);
    exit;
}

function flash(string $key, ?string $message = null): ?string
{
    if (!isset($_SESSION['flash'])) {
        $_SESSION['flash'] = [];
    }

    if ($message === null) {
        $value = $_SESSION['flash'][$key] ?? null;
        unset($_SESSION['flash'][$key]);
        return $value;
    }

    $_SESSION['flash'][$key] = $message;
    return null;
}

function current_user(): ?array
{
    return $_SESSION['user'] ?? null;
}

function require_login(?string $role = null): void
{
    if (!current_user()) {
        flash('error', 'Please log in to continue.');
        redirect('login.php');
    }

    if ($role !== null && current_user()['role'] !== $role) {
        flash('error', 'You are not authorised to access that area.');
        redirect('dashboard.php');
    }
}

function is_post(): bool
{
    return $_SERVER['REQUEST_METHOD'] === 'POST';
}

function form_value(string $key, string $default = ''): string
{
    return htmlspecialchars($_POST[$key] ?? $default, ENT_QUOTES, 'UTF-8');
}

function e(?string $value): string
{
    return htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8');
}

function format_currency(float $amount): string
{
    return '£' . number_format($amount, 2);
}

function get_setting(string $key, ?string $default = null): ?string
{
    if (!isset($GLOBALS['__settings_cache'])) {
        $pdo = get_db();
        $stmt = $pdo->query('SELECT key, value FROM settings');
        $GLOBALS['__settings_cache'] = [];
        foreach ($stmt->fetchAll() as $row) {
            $GLOBALS['__settings_cache'][$row['key']] = $row['value'];
        }
    }

    return $GLOBALS['__settings_cache'][$key] ?? $default;
}

function set_setting(string $key, string $value): void
{
    $pdo = get_db();
    $stmt = $pdo->prepare('REPLACE INTO settings (key, value) VALUES (:key, :value)');
    $stmt->execute(['key' => $key, 'value' => $value]);
    unset($GLOBALS['__settings_cache']);
}

function theme_styles(): string
{
    $primary = get_setting('brand_primary_color', '#3b82f6');
    $font = get_setting('brand_font_family', 'Inter, sans-serif');

    return ":root { --brand-primary: {$primary}; --brand-primary-dark: {$primary}; --brand-font: {$font}; }";
}

function send_notification_email(string $to, string $subject, string $body): void
{
    $config = require __DIR__ . '/config.php';
    $from = $config['mail'];
    $headers = [
        'From: ' . $from['from_name'] . ' <' . $from['from_address'] . '>',
        'Reply-To: ' . ($from['from_address']),
        'Content-Type: text/plain; charset=UTF-8'
    ];

    $success = @mail($to, $subject, $body, implode("\r\n", $headers));

    if (!$success) {
        $logDir = __DIR__ . '/data';
        if (!is_dir($logDir)) {
            mkdir($logDir, 0775, true);
        }
        $entry = sprintf("[%s] %s | %s\n%s\n\n", (new \DateTimeImmutable())->format('c'), $to, $subject, $body);
        file_put_contents($logDir . '/mail.log', $entry, FILE_APPEND);
    }
}

function parse_form_schema(?string $schema): array
{
    if (!$schema) {
        return [];
    }

    $decoded = json_decode($schema, true);
    if (!is_array($decoded)) {
        return [];
    }

    return $decoded;
}

function render_form_fields(array $schema): string
{
    if (!$schema) {
        return '<p class="text-sm text-gray-500">No custom fields configured for this service.</p>';
    }

    $html = '';
    foreach ($schema as $field) {
        $name = e($field['name'] ?? '');
        $label = e($field['label'] ?? $name);
        $type = e($field['type'] ?? 'text');
        $required = !empty($field['required']) ? 'required' : '';

        $html .= "<label class='block text-sm font-medium mb-1'>{$label}";
        $html .= "<input type='{$type}' name='custom[{$name}]' {$required} class='mt-1 w-full rounded border border-gray-300 px-3 py-2 focus:border-brand focus:ring-2 focus:ring-brand'/>";
        $html .= '</label>';
    }

    return $html;
}

function format_datetime(?string $value): string
{
    if (!$value) {
        return '';
    }
    try {
        $dt = new \DateTimeImmutable($value);
        return $dt->format('j M Y H:i');
    } catch (\Exception $e) {
        return $value;
    }
}
