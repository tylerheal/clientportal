<?php
declare(strict_types=1);

function base_path(): string
{
    $scriptName = $_SERVER['SCRIPT_NAME'] ?? '';
    $dir = rtrim(str_replace('\\', '/', dirname($scriptName)), '/');
    return $dir === '' ? '' : $dir;
}

function url_for(string $path = ''): string
{
    $prefix = base_path();
    $path = '/' . ltrim($path, '/');
    return $prefix . ($path === '/' ? '' : $path);
}

function redirect(string $path): void
{
    header('Location: ' . url_for($path));
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
        redirect('login');
    }

    if ($role !== null && current_user()['role'] !== $role) {
        flash('error', 'You are not authorised to access that area.');
        redirect('dashboard');
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

function find_template(PDO $pdo, string $slug): ?array
{
    static $cache = [];
    if (!array_key_exists($slug, $cache)) {
        $stmt = $pdo->prepare('SELECT * FROM email_templates WHERE slug = :slug LIMIT 1');
        $stmt->execute(['slug' => $slug]);
        $cache[$slug] = $stmt->fetch() ?: null;
    }
    return $cache[$slug];
}

function send_templated_email(PDO $pdo, string $slug, array $replacements, string $to, string $fallbackSubject, ?string $fallbackBody = null): void
{
    $template = find_template($pdo, $slug);
    if ($template) {
        $subject = strtr($template['subject'], $replacements);
        $body = strtr($template['body'], $replacements);
    } else {
        $subject = $fallbackSubject;
        $body = $fallbackBody ?? $fallbackSubject;
    }
    send_notification_email($to, $subject, $body);
}

function random_base32(int $length = 32): string
{
    $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
    $result = '';
    for ($i = 0; $i < $length; $i++) {
        $result .= $alphabet[random_int(0, strlen($alphabet) - 1)];
    }
    return $result;
}

function base32_decode(string $encoded): string
{
    $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
    $encoded = strtoupper($encoded);
    $encoded = preg_replace('/[^A-Z2-7]/', '', $encoded);
    $bits = '';
    foreach (str_split($encoded) as $char) {
        $value = strpos($alphabet, $char);
        if ($value === false) {
            continue;
        }
        $bits .= str_pad(decbin($value), 5, '0', STR_PAD_LEFT);
    }

    $bytes = '';
    foreach (str_split($bits, 8) as $chunk) {
        if (strlen($chunk) === 8) {
            $bytes .= chr(bindec($chunk));
        }
    }
    return $bytes;
}

function totp_now(string $secret, int $window = 0, int $period = 30, int $digits = 6): string
{
    $key = base32_decode($secret);
    $timeSlice = (int) floor(time() / $period) + $window;
    $binaryTime = pack('N*', 0) . pack('N*', $timeSlice);
    $hash = hash_hmac('sha1', $binaryTime, $key, true);
    $offset = ord(substr($hash, -1)) & 0x0F;
    $truncated = (ord($hash[$offset]) & 0x7F) << 24
        | (ord($hash[$offset + 1]) & 0xFF) << 16
        | (ord($hash[$offset + 2]) & 0xFF) << 8
        | (ord($hash[$offset + 3]) & 0xFF);
    $code = $truncated % (10 ** $digits);
    return str_pad((string) $code, $digits, '0', STR_PAD_LEFT);
}

function verify_totp(string $secret, string $code, int $window = 1): bool
{
    $code = preg_replace('/\D/', '', $code);
    if (strlen($code) < 6) {
        return false;
    }
    for ($i = -$window; $i <= $window; $i++) {
        if (hash_equals(totp_now($secret, $i), $code)) {
            return true;
        }
    }
    return false;
}

function generate_recovery_codes(int $count = 8): array
{
    $codes = [];
    for ($i = 0; $i < $count; $i++) {
        $codes[] = strtoupper(bin2hex(random_bytes(4)));
    }
    return $codes;
}

function get_notifications(PDO $pdo, int $userId): array
{
    $stmt = $pdo->prepare('SELECT * FROM notifications WHERE user_id = :user ORDER BY created_at DESC LIMIT 20');
    $stmt->execute(['user' => $userId]);
    return $stmt->fetchAll();
}

function record_notification(PDO $pdo, int $userId, string $message, ?string $link = null): void
{
    $stmt = $pdo->prepare('INSERT INTO notifications (user_id, message, link, created_at) VALUES (:user_id, :message, :link, :created_at)');
    $stmt->execute([
        'user_id' => $userId,
        'message' => $message,
        'link' => $link,
        'created_at' => (new \DateTimeImmutable())->format(\DateTimeInterface::ATOM),
    ]);
}

function mark_notifications_read(PDO $pdo, int $userId): void
{
    $pdo->prepare('UPDATE notifications SET read_at = :read_at WHERE user_id = :user_id AND read_at IS NULL')
        ->execute([
            'read_at' => (new \DateTimeImmutable())->format(\DateTimeInterface::ATOM),
            'user_id' => $userId,
        ]);
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
