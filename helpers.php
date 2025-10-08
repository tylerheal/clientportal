<?php
declare(strict_types=1);

if (!function_exists('str_starts_with')) {
    function str_starts_with(string $haystack, string $needle): bool
    {
        return $needle === '' || strncmp($haystack, $needle, strlen($needle)) === 0;
    }
}

function base_path(): string
{
    $scriptName = $_SERVER['SCRIPT_NAME'] ?? '';
    $dir = rtrim(str_replace('\\', '/', dirname($scriptName)), '/');
    return $dir === '' ? '' : $dir;
}

function request_path(): string
{
    $uri = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?? '/';
    $uri = '/' . ltrim($uri, '/');
    $base = base_path();

    if ($base !== '') {
        if ($uri === $base) {
            $uri = '/';
        } elseif (str_starts_with($uri, $base . '/')) {
            $uri = substr($uri, strlen($base));
        }
    }

    return $uri === '' ? '/' : $uri;
}

function url_for(string $path = ''): string
{
    $prefix = base_path();
    $path = '/' . ltrim($path, '/');
    return $prefix . ($path === '/' ? '' : $path);
}

function asset_url(string $path): string
{
    if ($path === '') {
        return '';
    }

    if (preg_match('#^(?:https?:)?//#i', $path)) {
        return $path;
    }

    return url_for(ltrim($path, '/'));
}

function local_asset_path(string $path): ?string
{
    if ($path === '' || preg_match('#^(?:https?:)?//#i', $path)) {
        return null;
    }

    $clean = ltrim($path, '/');
    return __DIR__ . '/' . $clean;
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

function is_admin(?array $user = null): bool
{
    $user = $user ?? current_user();
    return ($user['role'] ?? null) === 'admin';
}

function read_user_settings(array $user): array
{
    if (empty($user['settings'])) {
        return [];
    }

    try {
        $decoded = json_decode((string) $user['settings'], true, 512, JSON_THROW_ON_ERROR);
    } catch (Throwable $e) {
        return [];
    }

    return is_array($decoded) ? $decoded : [];
}

function persist_user_settings(PDO $pdo, int $userId, array $settings): string
{
    $payload = json_encode($settings, JSON_THROW_ON_ERROR);
    $stmt = $pdo->prepare('UPDATE users SET settings = :settings, updated_at = :updated WHERE id = :id');
    $stmt->execute([
        'settings' => $payload,
        'updated' => (new \DateTimeImmutable())->format(\DateTimeInterface::ATOM),
        'id' => $userId,
    ]);
    return $payload;
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

function currency_code(): string
{
    $raw = strtoupper(preg_replace('/[^A-Z]/', '', (string) get_setting('currency_code', 'GBP')));
    return strlen($raw) === 3 ? $raw : 'GBP';
}

function format_currency(float $amount): string
{
    $code = currency_code();
    if (class_exists('NumberFormatter')) {
        $locale = class_exists('Locale') ? Locale::getDefault() : 'en_GB';
        static $formatters = [];
        $key = $locale . '|' . $code;
        if (!isset($formatters[$key])) {
            $formatter = new NumberFormatter($locale, NumberFormatter::CURRENCY);
            $formatters[$key] = $formatter;
        }
        $formatter = $formatters[$key] ?? null;
        if ($formatter instanceof NumberFormatter) {
            $formatted = $formatter->formatCurrency($amount, $code);
            if ($formatted !== false) {
                return $formatted;
            }
        }
    }

    $symbolMap = [
        'GBP' => '£',
        'USD' => '$',
        'EUR' => '€',
        'AUD' => 'A$',
        'CAD' => 'C$',
        'NZD' => 'NZ$',
    ];
    $symbol = $symbolMap[$code] ?? '';
    return $symbol . number_format($amount, 2);
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

function paypal_mode(): string
{
    $mode = strtolower(get_setting('paypal_mode', 'sandbox'));
    return $mode === 'live' ? 'live' : 'sandbox';
}

function paypal_api_base(): string
{
    return paypal_mode() === 'live'
        ? 'https://api-m.paypal.com'
        : 'https://api-m.sandbox.paypal.com';
}

function paypal_credentials(): array
{
    $clientId = trim(get_setting('paypal_client_id', ''));
    $secret = trim(get_setting('paypal_client_secret', ''));
    if ($clientId === '' || $secret === '') {
        throw new RuntimeException('PayPal credentials are not configured.');
    }
    return [$clientId, $secret];
}

function paypal_access_token(bool $forceRefresh = false): string
{
    static $cache = ['token' => null, 'expires' => 0];
    if (!$forceRefresh && $cache['token'] && $cache['expires'] > time()) {
        return $cache['token'];
    }

    if (!function_exists('curl_init')) {
        throw new RuntimeException('The cURL extension is required for PayPal integration.');
    }

    [$clientId, $secret] = paypal_credentials();
    $endpoint = paypal_api_base() . '/v1/oauth2/token';
    $ch = curl_init($endpoint);
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_USERPWD => $clientId . ':' . $secret,
        CURLOPT_POSTFIELDS => 'grant_type=client_credentials',
        CURLOPT_HTTPHEADER => ['Accept: application/json', 'Accept-Language: en_US'],
    ]);
    $response = curl_exec($ch);
    if ($response === false) {
        $error = curl_error($ch);
        curl_close($ch);
        throw new RuntimeException('PayPal authentication failed: ' . $error);
    }
    $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    $data = json_decode($response, true);
    if (!is_array($data)) {
        throw new RuntimeException('Unexpected response from PayPal authentication.');
    }
    if ($status >= 400) {
        $message = $data['error_description'] ?? $data['error'] ?? 'PayPal authentication failed.';
        throw new RuntimeException($message);
    }
    $token = $data['access_token'] ?? null;
    if (!$token) {
        throw new RuntimeException('PayPal did not return an access token.');
    }
    $expires = (int) ($data['expires_in'] ?? 0);
    $cache = [
        'token' => $token,
        'expires' => time() + max($expires - 60, 60),
    ];
    return $token;
}

function paypal_api_request(string $method, string $path, ?array $payload = null): array
{
    $attempts = 0;
    do {
        if (!function_exists('curl_init')) {
            throw new RuntimeException('The cURL extension is required for PayPal integration.');
        }
        $token = paypal_access_token($attempts > 0);
        $url = str_starts_with($path, 'http') ? $path : paypal_api_base() . '/' . ltrim($path, '/');
        $headers = [
            'Authorization: Bearer ' . $token,
            'Content-Type: application/json',
            'Accept: application/json',
        ];
        $options = [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST => strtoupper($method),
            CURLOPT_HTTPHEADER => $headers,
        ];
        if ($payload !== null) {
            $json = json_encode($payload, JSON_THROW_ON_ERROR);
            $options[CURLOPT_POSTFIELDS] = $json;
        }
        $ch = curl_init($url);
        curl_setopt_array($ch, $options);
        $response = curl_exec($ch);
        if ($response === false) {
            $error = curl_error($ch);
            curl_close($ch);
            throw new RuntimeException('PayPal request failed: ' . $error);
        }
        $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        $data = json_decode($response, true);
        if ($status === 401 && $attempts === 0) {
            $attempts++;
            continue;
        }
        if ($status >= 400) {
            $message = $data['message'] ?? $data['details'][0]['description'] ?? $data['error_description'] ?? 'PayPal request failed.';
            throw new RuntimeException($message);
        }
        return is_array($data) ? $data : [];
    } while ($attempts < 2);

    throw new RuntimeException('PayPal request failed with repeated authentication errors.');
}

function theme_styles(): string
{
    $primary = get_setting('brand_primary_color', '#3b82f6');
    $font = get_setting('brand_font_family', 'Inter, sans-serif');
    $surface = get_setting('brand_surface_color', '#f4f6fb');
    $card = get_setting('brand_card_color', '#ffffff');
    $control = get_setting('brand_control_color', '#eef2ff');
    $border = get_setting('brand_border_color', '#dce1eb');
    $text = get_setting('brand_text_color', '#111827');
    $muted = get_setting('brand_muted_color', '#6b7280');
    $tableHover = 'rgba(59, 130, 246, 0.08)';

    $cardSoft = sprintf('color-mix(in srgb, %s 88%%, #f8fafc)', $card);

    $styles = <<<CSS
:root { --brand-primary: {$primary}; --brand-primary-dark: {$primary}; --brand-font: {$font}; }
body.dashboard-body {
    --bg: {$surface};
    --panel: {$card};
    --panel-2: {$card};
    --panel-2: {$cardSoft};
    --surface-contrast: {$control};
    --control-bg: {$control};
    --avatar-bg: {$control};
    --metric-bg: {$cardSoft};
    --table-hover: {$tableHover};
    --border: {$border};
    --text: {$text};
    --muted: {$muted};
}
body.auth-body {
    --bg: #0b0c10;
    --panel: #111827;
    --panel-2: #0f1625;
    --surface-contrast: #0c1424;
    --control-bg: #0f1625;
    --avatar-bg: #162036;
    --metric-bg: #10192d;
    --table-hover: #0d1527;
    --border: #1f2937;
    --text: #e5e7eb;
    --muted: #9aa7b8;
}
CSS;

    return $styles;
}

function is_ajax_request(): bool
{
    return isset($_SERVER['HTTP_X_REQUESTED_WITH'])
        && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
}

function brand_initials(string $name, string $fallback = 'SP'): string
{
    $trimmed = trim($name);
    if ($trimmed === '') {
        return $fallback;
    }

    $parts = preg_split('/\s+/', $trimmed) ?: [];
    $letters = [];

    foreach ($parts as $part) {
        if ($part === '') {
            continue;
        }

        if (function_exists('mb_substr')) {
            $char = mb_substr($part, 0, 1, 'UTF-8');
            $char = function_exists('mb_strtoupper') ? mb_strtoupper($char, 'UTF-8') : $char;
        } else {
            $char = strtoupper(substr($part, 0, 1));
        }

        if ($char !== '') {
            $letters[] = $char;
        }

        if (count($letters) === 2) {
            break;
        }
    }

    if (!$letters) {
        return $fallback;
    }

    return implode('', $letters);
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

function format_relative_time(?string $value): string
{
    if (!$value) {
        return '';
    }

    try {
        $target = new \DateTimeImmutable($value);
    } catch (\Exception $e) {
        return $value;
    }

    $now = new \DateTimeImmutable();
    $seconds = $now->getTimestamp() - $target->getTimestamp();
    $future = $seconds < 0;
    $seconds = abs($seconds);

    if ($seconds < 45) {
        return $future ? 'in a few seconds' : 'Just now';
    }
    if ($seconds < 90) {
        return $future ? 'in a minute' : '1 minute ago';
    }

    $minutes = (int) floor($seconds / 60);
    if ($seconds < 3600) {
        return $future ? sprintf('in %d minute%s', $minutes, $minutes === 1 ? '' : 's') : sprintf('%d minute%s ago', $minutes, $minutes === 1 ? '' : 's');
    }

    $hours = (int) floor($seconds / 3600);
    if ($seconds < 86400) {
        return $future ? sprintf('in %d hour%s', $hours, $hours === 1 ? '' : 's') : sprintf('%d hour%s ago', $hours, $hours === 1 ? '' : 's');
    }

    $days = (int) floor($seconds / 86400);
    if ($seconds < 604800) {
        if ($days === 1) {
            return $future ? 'Tomorrow' : 'Yesterday';
        }
        return $future ? sprintf('in %d days', $days) : sprintf('%d days ago', $days);
    }

    $weeks = (int) floor($seconds / 604800);
    if ($seconds < 2629800) { // ~1 month
        return $future ? sprintf('in %d week%s', $weeks, $weeks === 1 ? '' : 's') : sprintf('%d week%s ago', $weeks, $weeks === 1 ? '' : 's');
    }

    $months = (int) floor($seconds / 2629800);
    if ($seconds < 31557600) { // ~1 year
        return $future ? sprintf('in %d month%s', $months, $months === 1 ? '' : 's') : sprintf('%d month%s ago', $months, $months === 1 ? '' : 's');
    }

    $years = (int) floor($seconds / 31557600);
    return $future ? sprintf('in %d year%s', $years, $years === 1 ? '' : 's') : sprintf('%d year%s ago', $years, $years === 1 ? '' : 's');
}
