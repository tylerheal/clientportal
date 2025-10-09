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

function absolute_url(string $path = ''): string
{
    $scheme = (!empty($_SERVER['HTTPS']) && strtolower((string) $_SERVER['HTTPS']) !== 'off') ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    return $scheme . '://' . $host . url_for($path);
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

function get_service_payment_metadata(PDO $pdo, int $serviceId): array
{
    $stmt = $pdo->prepare('SELECT payment_metadata FROM services WHERE id = :id LIMIT 1');
    $stmt->execute(['id' => $serviceId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$row) {
        return [];
    }

    $raw = (string) ($row['payment_metadata'] ?? '');
    if (trim($raw) === '') {
        return [];
    }

    try {
        $decoded = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
    } catch (Throwable $e) {
        return [];
    }

    return is_array($decoded) ? $decoded : [];
}

function save_service_payment_metadata(PDO $pdo, int $serviceId, array $metadata): void
{
    $payload = json_encode($metadata, JSON_THROW_ON_ERROR);
    $stmt = $pdo->prepare('UPDATE services SET payment_metadata = :meta, updated_at = :updated WHERE id = :id');
    $stmt->execute([
        'meta' => $payload,
        'updated' => (new \DateTimeImmutable())->format(\DateTimeInterface::ATOM),
        'id' => $serviceId,
    ]);
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

function is_truthy_setting(string $key, bool $default = false): bool
{
    $value = strtolower((string) get_setting($key, $default ? '1' : '0'));
    return in_array($value, ['1', 'true', 'yes', 'on'], true);
}

function payments_available(): array
{
    static $cache = null;
    if ($cache !== null) {
        return $cache;
    }

    $stripePublishable = trim((string) get_setting('stripe_publishable_key', ''));
    $stripeSecret = trim((string) get_setting('stripe_secret_key', ''));
    $paypalId = trim((string) get_setting('paypal_client_id', ''));
    $paypalSecret = trim((string) get_setting('paypal_client_secret', ''));

    $stripeEnabled = is_truthy_setting('payments_enable_stripe') && $stripePublishable !== '' && $stripeSecret !== '';
    $paypalEnabled = is_truthy_setting('payments_enable_paypal') && $paypalId !== '' && $paypalSecret !== '';
    $googlePayEnabled = $stripeEnabled && is_truthy_setting('payments_enable_google_pay');

    $cache = [
        'stripe' => $stripeEnabled,
        'paypal' => $paypalEnabled,
        'google_pay' => $googlePayEnabled,
        'stripe_publishable' => $stripePublishable,
    ];

    return $cache;
}

function turnstile_enabled(): bool
{
    return is_truthy_setting('turnstile_enabled')
        && trim((string) get_setting('turnstile_site_key', '')) !== ''
        && trim((string) get_setting('turnstile_secret_key', '')) !== '';
}

function turnstile_site_key(): string
{
    return trim((string) get_setting('turnstile_site_key', ''));
}

function verify_turnstile(string $token, ?string $remoteIp = null): bool
{
    if (!turnstile_enabled()) {
        return true;
    }

    $token = trim($token);
    if ($token === '') {
        return false;
    }

    $secret = trim((string) get_setting('turnstile_secret_key', ''));
    if ($secret === '') {
        return false;
    }

    $payload = http_build_query(array_filter([
        'secret' => $secret,
        'response' => $token,
        'remoteip' => $remoteIp,
    ]));

    $context = stream_context_create([
        'http' => [
            'method' => 'POST',
            'header' => "Content-type: application/x-www-form-urlencoded\r\n",
            'content' => $payload,
            'timeout' => 10,
        ],
    ]);

    $result = @file_get_contents('https://challenges.cloudflare.com/turnstile/v0/siteverify', false, $context);
    if ($result === false) {
        return false;
    }

    $decoded = json_decode($result, true);
    if (!is_array($decoded)) {
        return false;
    }

    return (bool) ($decoded['success'] ?? false);
}

function stripe_publishable_key(): string
{
    return trim((string) get_setting('stripe_publishable_key', ''));
}

function stripe_secret_key(): string
{
    return trim((string) get_setting('stripe_secret_key', ''));
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

function stripe_api_request(string $method, string $path, array $params = []): array
{
    if (!function_exists('curl_init')) {
        throw new RuntimeException('The cURL extension is required for Stripe integration.');
    }

    $secret = stripe_secret_key();
    if ($secret === '') {
        throw new RuntimeException('Stripe secret key is not configured.');
    }

    $url = str_starts_with($path, 'http') ? $path : 'https://api.stripe.com/' . ltrim($path, '/');
    $ch = curl_init($url);
    $options = [
        CURLOPT_CUSTOMREQUEST => strtoupper($method),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPAUTH => CURLAUTH_BASIC,
        CURLOPT_USERPWD => $secret . ':',
    ];

    if (!empty($params)) {
        $options[CURLOPT_POSTFIELDS] = http_build_query($params);
    }

    curl_setopt_array($ch, $options);
    $response = curl_exec($ch);
    if ($response === false) {
        $error = curl_error($ch);
        curl_close($ch);
        throw new RuntimeException('Stripe request failed: ' . $error);
    }
    $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    $data = json_decode($response, true);
    if (!is_array($data)) {
        throw new RuntimeException('Unexpected response from Stripe.');
    }
    if ($status >= 400) {
        $message = $data['error']['message'] ?? 'Stripe request failed.';
        throw new RuntimeException($message);
    }

    return $data;
}

function ensure_stripe_customer(PDO $pdo, array $userRow): ?string
{
    $customerId = trim((string) ($userRow['stripe_customer_id'] ?? ''));
    if ($customerId !== '') {
        return $customerId;
    }

    if (empty(payments_available()['stripe'])) {
        return null;
    }

    $email = trim((string) ($userRow['email'] ?? ''));
    $name = trim((string) ($userRow['name'] ?? ''));

    $params = [];
    if ($email !== '') {
        $params['email'] = $email;
    }
    if ($name !== '') {
        $params['name'] = $name;
    }

    try {
        $response = stripe_api_request('POST', 'v1/customers', $params);
    } catch (Throwable $e) {
        return null;
    }

    $createdId = trim((string) ($response['id'] ?? ''));
    if ($createdId === '') {
        return null;
    }

    $stmt = $pdo->prepare('UPDATE users SET stripe_customer_id = :customer, updated_at = :updated WHERE id = :id');
    $stmt->execute([
        'customer' => $createdId,
        'updated' => (new \DateTimeImmutable())->format(\DateTimeInterface::ATOM),
        'id' => (int) ($userRow['id'] ?? 0),
    ]);

    return $createdId;
}

function paypal_api_base(): string
{
    return paypal_mode() === 'live'
        ? 'https://api-m.paypal.com'
        : 'https://api-m.sandbox.paypal.com';
}

function currency_minor_unit(string $currency): int
{
    static $zeroDecimals = [
        'BIF', 'CLP', 'DJF', 'GNF', 'JPY', 'KMF', 'KRW', 'MGA', 'PYG', 'RWF', 'UGX', 'VND', 'VUV', 'XAF', 'XOF', 'XPF',
    ];
    return in_array(strtoupper($currency), $zeroDecimals, true) ? 1 : 100;
}

function to_minor_units(float $amount, string $currency): int
{
    $multiplier = currency_minor_unit($currency);
    return (int) round($amount * $multiplier);
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

class PayPalApiException extends RuntimeException
{
    private int $statusCode;

    public function __construct(int $statusCode, string $message)
    {
        parent::__construct($message, $statusCode);
        $this->statusCode = $statusCode;
    }

    public function getStatusCode(): int
    {
        return $this->statusCode;
    }
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

        $data = $response !== '' ? json_decode($response, true) : [];
        if ($status === 401 && $attempts === 0) {
            $attempts++;
            continue;
        }
        if ($status >= 400) {
            $message = 'PayPal request failed.';
            if (is_array($data)) {
                $message = $data['message'] ?? $data['details'][0]['description'] ?? $data['error_description'] ?? $data['error'] ?? $message;
            }
            throw new PayPalApiException($status, $message);
        }
        if ($data === null) {
            throw new RuntimeException('Unexpected response from PayPal API.');
        }
        return is_array($data) ? $data : [];
    } while ($attempts < 2);

    throw new RuntimeException('PayPal request failed with repeated authentication errors.');
}

function create_paypal_product(string $name, string $description): string
{
    $nameTrimmed = function_exists('mb_substr') ? mb_substr($name, 0, 127) : substr($name, 0, 127);
    $descriptionTrimmed = function_exists('mb_substr') ? mb_substr($description, 0, 256) : substr($description, 0, 256);

    $payload = [
        'name' => $nameTrimmed,
        'description' => $descriptionTrimmed,
        'type' => 'SERVICE',
        'category' => 'SOFTWARE',
    ];

    $product = paypal_api_request('POST', 'v1/catalogs/products', $payload);
    $productId = (string) ($product['id'] ?? '');

    if ($productId === '') {
        throw new RuntimeException('PayPal did not return a product identifier.');
    }

    return $productId;
}

function ensure_paypal_product_for_service(int $serviceId, string $serviceName): string
{
    $pdo = get_db();
    $metadata = get_service_payment_metadata($pdo, $serviceId);
    $productId = (string) ($metadata['paypal_product_id'] ?? '');
    $description = 'Recurring service for ' . $serviceName;

    if ($productId !== '') {
        try {
            paypal_api_request('GET', 'v1/catalogs/products/' . urlencode($productId));
            return $productId;
        } catch (PayPalApiException $exception) {
            if ($exception->getStatusCode() !== 404) {
                throw $exception;
            }
        }
    }

    $productId = create_paypal_product($serviceName, $description);
    $metadata['paypal_product_id'] = $productId;
    save_service_payment_metadata($pdo, $serviceId, $metadata);

    return $productId;
}

function ensure_stripe_price_for_service(PDO $pdo, int $serviceId, string $serviceName, float $amount, string $currency, string $interval): string
{
    if ($amount <= 0) {
        throw new RuntimeException('Service price must be greater than zero for Stripe subscriptions.');
    }

    $metadata = get_service_payment_metadata($pdo, $serviceId);
    $prices = [];
    if (isset($metadata['stripe_prices']) && is_array($metadata['stripe_prices'])) {
        $prices = $metadata['stripe_prices'];
    }

    $intervalKey = strtolower($interval);
    if (!in_array($intervalKey, ['monthly', 'annual'], true)) {
        $intervalKey = 'monthly';
    }

    $currencyCode = strtolower($currency);
    $normalizedAmount = number_format($amount, 2, '.', '');

    if (isset($prices[$intervalKey]) && is_array($prices[$intervalKey])) {
        $record = $prices[$intervalKey];
        $recordCurrency = strtolower((string) ($record['currency'] ?? ''));
        $recordAmount = number_format((float) ($record['amount'] ?? 0), 2, '.', '');
        $priceId = (string) ($record['price_id'] ?? '');
        if ($priceId !== '' && $recordCurrency === $currencyCode && $recordAmount === $normalizedAmount) {
            return $priceId;
        }
    }

    $intervalUnit = $intervalKey === 'annual' ? 'year' : 'month';
    $response = stripe_api_request('POST', 'v1/prices', [
        'currency' => $currencyCode,
        'unit_amount' => to_minor_units($amount, $currency),
        'recurring[interval]' => $intervalUnit,
        'recurring[interval_count]' => 1,
        'product_data[name]' => $serviceName,
        'product_data[metadata][service_id]' => (string) $serviceId,
    ]);

    $priceId = (string) ($response['id'] ?? '');
    if ($priceId === '') {
        throw new RuntimeException('Stripe did not return a price identifier.');
    }

    $prices[$intervalKey] = [
        'price_id' => $priceId,
        'currency' => $currencyCode,
        'amount' => $normalizedAmount,
    ];
    $metadata['stripe_prices'] = $prices;
    save_service_payment_metadata($pdo, $serviceId, $metadata);

    return $priceId;
}

function create_paypal_plan(string $productId, string $name, string $intervalUnit, string $currency, string $amount): string
{
    $currencyCode = strtoupper($currency);
    $normalizedAmount = number_format((float) $amount, 2, '.', '');

    $planPayload = [
        'product_id' => $productId,
        'name' => $name,
        'billing_cycles' => [[
            'frequency' => [
                'interval_unit' => strtoupper($intervalUnit),
                'interval_count' => 1,
            ],
            'tenure_type' => 'REGULAR',
            'sequence' => 1,
            'total_cycles' => 0,
            'pricing_scheme' => [
                'version' => 1,
                'fixed_price' => [
                    'value' => $normalizedAmount,
                    'currency_code' => $currencyCode,
                ],
            ],
        ]],
        'payment_preferences' => [
            'auto_bill_outstanding' => true,
            'setup_fee_failure_action' => 'CONTINUE',
            'payment_failure_threshold' => 3,
        ],
        'taxes' => [
            'percentage' => '0.00',
            'inclusive' => false,
        ],
    ];

    $planPayload['payment_preferences']['setup_fee'] = [
        'value' => '0.00',
        'currency_code' => $currencyCode,
    ];

    $plan = paypal_api_request('POST', 'v1/billing/plans', $planPayload);

    $planId = $plan['id'] ?? '';
    if ($planId === '') {
        throw new RuntimeException('PayPal did not return a plan identifier.');
    }

    $planPath = 'v1/billing/plans/' . urlencode($planId);
    $prefetchedStatus = null;

    try {
        paypal_api_request('POST', $planPath . '/activate', [
            'reason' => 'Initial activation',
        ]);
    } catch (PayPalApiException $exception) {
        if ($exception->getStatusCode() === 422) {
            try {
                $details = paypal_api_request('GET', $planPath);
                $prefetchedStatus = strtoupper((string) ($details['status'] ?? ''));
                if ($prefetchedStatus === 'ACTIVE') {
                    return $planId;
                }
            } catch (\Throwable $inner) {
                // Ignore and fall through to retry logic below.
            }
        }

        if ($prefetchedStatus !== 'ACTIVE') {
            throw $exception;
        }
    }

    // PayPal plan activation can take a moment to propagate; poll until it is active.
    $maxAttempts = 20;
    $delayMicroseconds = 500000; // 0.5 seconds
    for ($attempt = 0; $attempt < $maxAttempts; $attempt++) {
        try {
            if ($prefetchedStatus !== null) {
                $status = $prefetchedStatus;
                $prefetchedStatus = null;
            } else {
                $details = paypal_api_request('GET', $planPath);
                $status = strtoupper((string) ($details['status'] ?? ''));
            }
            if ($status === 'ACTIVE') {
                return $planId;
            }
        } catch (PayPalApiException $exception) {
            // A short propagation delay can surface as a 404. Retry before giving up.
            if ($exception->getStatusCode() >= 500) {
                throw $exception;
            }
        }

        usleep($delayMicroseconds);
    }

    throw new RuntimeException('PayPal plan activation is taking longer than expected. Please retry in a few seconds.');
}

function prepare_paypal_subscription_plan(array $invoice, array $subscription): array
{
    $serviceName = $invoice['service_name'] ?? 'Subscription';
    $subscriptionId = (int) ($subscription['id'] ?? 0);
    $interval = strtolower((string) ($subscription['interval'] ?? 'monthly'));
    $amount = number_format((float) ($invoice['total'] ?? 0), 2, '.', '');
    $currency = currency_code();

    $serviceId = (int) ($invoice['service_id'] ?? 0);
    if ($serviceId <= 0) {
        throw new RuntimeException('Unable to prepare PayPal subscription without a service reference.');
    }

    $productId = ensure_paypal_product_for_service($serviceId, $serviceName);
    $unit = $interval === 'annual' ? 'YEAR' : 'MONTH';
    $planName = $serviceName . ' ' . ($interval === 'annual' ? 'Annual' : 'Monthly');
    $planId = create_paypal_plan($productId, $planName, $unit, $currency, $amount);

    return [
        'plan_id' => $planId,
        'custom_id' => $subscriptionId > 0 ? 'SUB-' . $subscriptionId : null,
    ];
}

/**
 * Generate the next invoice for a subscription and attempt automatic payment if configured.
 *
 * @return array{skipped:bool, reason?:string, invoice_id?:int, charged?:bool, provider?:string|null, reference?:string|null, provider_managed?:bool, amount?:float}
 */
function process_subscription_cycle(PDO $pdo, array $subscription, \DateTimeImmutable $now, bool $force = false): array
{
    $subscriptionId = (int) ($subscription['id'] ?? 0);
    if ($subscriptionId <= 0) {
        throw new RuntimeException('Invalid subscription.');
    }

    $amount = (float) ($subscription['price'] ?? 0);
    if ($amount <= 0) {
        throw new RuntimeException('Subscriptions require a service price before they can be billed.');
    }

    $nextBillingAt = isset($subscription['next_billing_at'])
        ? new \DateTimeImmutable($subscription['next_billing_at'])
        : $now;

    if (!$force && $nextBillingAt > $now) {
        return [
            'skipped' => true,
            'reason' => 'Next renewal is scheduled for ' . $nextBillingAt->format('j M Y H:i'),
        ];
    }

    $interval = strtolower((string) ($subscription['interval'] ?? 'monthly'));
    $intervalSpec = $interval === 'annual' ? '+1 year' : '+1 month';
    $dueAt = $now;

    $pdo->prepare('INSERT INTO invoices (subscription_id, order_id, user_id, service_id, total, status, due_at, created_at, updated_at) VALUES (:subscription_id, :order_id, :user_id, :service_id, :total, :status, :due_at, :created_at, :updated_at)')
        ->execute([
            'subscription_id' => $subscriptionId,
            'order_id' => $subscription['order_id'] ?? null,
            'user_id' => $subscription['user_id'] ?? null,
            'service_id' => $subscription['service_id'] ?? null,
            'total' => $amount,
            'status' => 'pending',
            'due_at' => $dueAt->format(\DateTimeInterface::ATOM),
            'created_at' => $dueAt->format(\DateTimeInterface::ATOM),
            'updated_at' => $dueAt->format(\DateTimeInterface::ATOM),
        ]);

    $invoiceId = (int) $pdo->lastInsertId();

    $nextAnchor = $force ? $now : $nextBillingAt;
    $pdo->prepare('UPDATE subscriptions SET next_billing_at = :next, updated_at = :updated WHERE id = :id')
        ->execute([
            'next' => $nextAnchor->modify($intervalSpec)->format(\DateTimeInterface::ATOM),
            'updated' => $now->format(\DateTimeInterface::ATOM),
            'id' => $subscriptionId,
        ]);

    $invoiceStmt = $pdo->prepare('SELECT i.*, u.email, u.name, s.name AS service_name, sub.stripe_customer, sub.stripe_payment_method, sub.stripe_subscription_id, sub.paypal_subscription_id FROM invoices i JOIN users u ON u.id = i.user_id JOIN services s ON s.id = i.service_id LEFT JOIN subscriptions sub ON sub.id = i.subscription_id WHERE i.id = :id LIMIT 1');
    $invoiceStmt->execute(['id' => $invoiceId]);
    $invoice = $invoiceStmt->fetch();

    if ($invoice) {
        $replacements = [
            '{{name}}' => $invoice['name'],
            '{{service}}' => $invoice['service_name'],
            '{{invoice}}' => (string) $invoiceId,
            '{{amount}}' => format_currency($amount),
            '{{due_date}}' => $dueAt->format('j M Y'),
            '{{company}}' => get_setting('company_name', 'Service Portal'),
        ];
        $body = sprintf(
            "Hi %s,\n\nWe've raised invoice #%s for %s. The total due is %s and it is payable by %s.",
            $invoice['name'],
            $invoiceId,
            $invoice['service_name'],
            format_currency($amount),
            $dueAt->format('j M Y')
        );
        send_templated_email($pdo, 'invoice_created', $replacements, $invoice['email'], 'New invoice issued', $body);
        record_notification($pdo, (int) $subscription['user_id'], 'Invoice #' . $invoiceId . ' generated for ' . $invoice['service_name'], url_for('dashboard#invoices'));
        notify_admins($pdo, 'Invoice #' . $invoiceId . ' generated for ' . $invoice['service_name'], url_for('admin/orders'));
    }

    $result = [
        'skipped' => false,
        'invoice_id' => $invoiceId,
        'charged' => false,
        'provider' => null,
        'reference' => null,
        'provider_managed' => false,
        'amount' => $amount,
    ];

    if (!$invoice) {
        return $result;
    }

    $providerManaged = ($invoice['stripe_subscription_id'] ?? '') !== '' || ($invoice['paypal_subscription_id'] ?? '') !== '';
    $result['provider_managed'] = $providerManaged;

    if ($providerManaged) {
        return $result;
    }

    $stripeCustomer = trim((string) ($invoice['stripe_customer'] ?? ''));
    $stripeMethod = trim((string) ($invoice['stripe_payment_method'] ?? ''));
    if ($stripeCustomer !== '' && $stripeMethod !== '' && !empty(payments_available()['stripe'])) {
        $currency = currency_code();
        try {
            $intent = stripe_api_request('POST', 'v1/payment_intents', [
                'amount' => to_minor_units($amount, $currency),
                'currency' => strtolower($currency),
                'customer' => $stripeCustomer,
                'payment_method' => $stripeMethod,
                'off_session' => 'true',
                'confirm' => 'true',
                'description' => sprintf('Subscription #%d – %s', $subscriptionId, $invoice['service_name']),
                'metadata[invoice_id]' => (string) $invoiceId,
                'metadata[subscription_id]' => (string) $subscriptionId,
            ]);

            $intentId = $intent['id'] ?? null;
            if ($intentId) {
                finalise_invoice_payment($pdo, $invoice, 'stripe', $intentId, $amount, [
                    'stripe_customer' => $stripeCustomer,
                    'stripe_payment_method' => $stripeMethod,
                ]);
                $result['charged'] = true;
                $result['provider'] = 'stripe';
                $result['reference'] = $intentId;
            }
        } catch (Throwable $e) {
            $message = 'Automatic payment failed for invoice #' . $invoiceId . ': ' . $e->getMessage();
            error_log($message);
            record_notification($pdo, (int) $subscription['user_id'], 'Automatic payment failed for invoice #' . $invoiceId . '. Please review the invoice to pay manually.', url_for('dashboard#invoices'));
            notify_admins($pdo, $message, url_for('admin/orders'));
        }
    }

    return $result;
}

/**
 * Ensure a subscription row exists for a recurring invoice/order combination.
 */
function ensure_subscription_record(PDO $pdo, array $invoice): ?int
{
    $subscriptionId = (int) ($invoice['subscription_id'] ?? 0);
    if ($subscriptionId > 0) {
        $existing = $pdo->prepare('SELECT id FROM subscriptions WHERE id = :id LIMIT 1');
        $existing->execute(['id' => $subscriptionId]);
        if ((int) $existing->fetchColumn() > 0) {
            return $subscriptionId;
        }

        $invoiceId = (int) ($invoice['id'] ?? 0);
        if ($invoiceId > 0) {
            $pdo->prepare('UPDATE invoices SET subscription_id = NULL WHERE id = :invoice')
                ->execute(['invoice' => $invoiceId]);
        }
        $invoice['subscription_id'] = null;
        $subscriptionId = 0;
    }

    $orderId = (int) ($invoice['order_id'] ?? 0);
    $serviceId = (int) ($invoice['service_id'] ?? 0);
    $userId = (int) ($invoice['user_id'] ?? 0);
    if ($orderId <= 0 || $serviceId <= 0 || $userId <= 0) {
        return null;
    }

    $existingStmt = $pdo->prepare('SELECT id FROM subscriptions WHERE order_id = :order LIMIT 1');
    $existingStmt->execute(['order' => $orderId]);
    $existingId = (int) $existingStmt->fetchColumn();
    if ($existingId > 0) {
        $pdo->prepare('UPDATE invoices SET subscription_id = :subscription WHERE id = :invoice')
            ->execute([
                'subscription' => $existingId,
                'invoice' => (int) ($invoice['id'] ?? 0),
            ]);

        return $existingId;
    }

    $serviceStmt = $pdo->prepare('SELECT billing_interval, name FROM services WHERE id = :id LIMIT 1');
    $serviceStmt->execute(['id' => $serviceId]);
    $serviceRow = $serviceStmt->fetch(PDO::FETCH_ASSOC) ?: [];
    $billingInterval = strtolower((string) ($serviceRow['billing_interval'] ?? ''));
    $orderIntervalStmt = $pdo->prepare('SELECT billing_interval FROM orders WHERE id = :id LIMIT 1');
    $orderIntervalStmt->execute(['id' => $orderId]);
    $orderIntervalRaw = $orderIntervalStmt->fetchColumn();
    $orderInterval = strtolower((string) $orderIntervalRaw);
    if (!in_array($billingInterval, ['monthly', 'annual'], true) && in_array($orderInterval, ['monthly', 'annual'], true)) {
        $billingInterval = $orderInterval;
    }
    if (!in_array($billingInterval, ['monthly', 'annual'], true) && !empty($serviceRow['name'])) {
        $normalized = strtolower((string) $serviceRow['name']);
        if (strpos($normalized, 'care plan') !== false) {
            $billingInterval = 'monthly';
        }
    }
    if (!in_array($billingInterval, ['monthly', 'annual'], true)) {
        return null;
    }

    if (isset($serviceRow['billing_interval']) && strtolower((string) $serviceRow['billing_interval']) !== $billingInterval) {
        $pdo->prepare('UPDATE services SET billing_interval = :interval, updated_at = :updated WHERE id = :id')
            ->execute([
                'interval' => $billingInterval,
                'updated' => (new \DateTimeImmutable())->format(\DateTimeInterface::ATOM),
                'id' => $serviceId,
            ]);
    }
    if ($orderInterval !== $billingInterval) {
        $pdo->prepare('UPDATE orders SET billing_interval = :interval, updated_at = :updated WHERE id = :id')
            ->execute([
                'interval' => $billingInterval,
                'updated' => (new \DateTimeImmutable())->format(\DateTimeInterface::ATOM),
                'id' => $orderId,
            ]);
    }

    $now = new \DateTimeImmutable();
    $intervalSpec = $billingInterval === 'annual' ? '+1 year' : '+1 month';
    $nextBilling = $now->modify($intervalSpec);

    $pdo->prepare('INSERT INTO subscriptions (order_id, user_id, service_id, interval, next_billing_at, status, created_at, updated_at) VALUES (:order_id, :user_id, :service_id, :interval, :next_billing_at, :status, :created_at, :updated_at)')
        ->execute([
            'order_id' => $orderId,
            'user_id' => $userId,
            'service_id' => $serviceId,
            'interval' => $billingInterval,
            'next_billing_at' => $nextBilling->format(\DateTimeInterface::ATOM),
            'status' => 'active',
            'created_at' => $now->format(\DateTimeInterface::ATOM),
            'updated_at' => $now->format(\DateTimeInterface::ATOM),
        ]);

    $newSubscriptionId = (int) $pdo->lastInsertId();
    if ($newSubscriptionId > 0) {
        $pdo->prepare('UPDATE invoices SET subscription_id = :subscription WHERE id = :invoice')
            ->execute([
                'subscription' => $newSubscriptionId,
                'invoice' => (int) ($invoice['id'] ?? 0),
            ]);
        return $newSubscriptionId;
    }

    return null;
}

function finalize_stripe_checkout_session(PDO $pdo, array $invoice, string $sessionId): void
{
    $sessionId = trim($sessionId);
    $invoiceId = (int) ($invoice['id'] ?? 0);

    if ($sessionId === '' || $invoiceId <= 0) {
        throw new RuntimeException('Stripe checkout session details were incomplete.');
    }

    $session = stripe_api_request('GET', 'v1/checkout/sessions/' . urlencode($sessionId), [
        'expand[]' => 'subscription',
    ]);

    $sessionIdentifier = (string) ($session['id'] ?? '');

    if (strtolower((string) ($session['mode'] ?? '')) !== 'subscription') {
        throw new RuntimeException('Stripe session is not a subscription checkout.');
    }

    $sessionInvoiceId = (int) ($session['metadata']['invoice_id'] ?? 0);
    if ($sessionInvoiceId !== 0 && $sessionInvoiceId !== $invoiceId) {
        throw new RuntimeException('Stripe session does not match the requested invoice.');
    }

    $paymentStatus = strtolower((string) ($session['payment_status'] ?? ''));
    if (!in_array($paymentStatus, ['paid', 'no_payment_required'], true)) {
        throw new RuntimeException('Stripe has not completed the checkout session yet.');
    }

    $subscription = $session['subscription'] ?? null;
    if (is_string($subscription) && $subscription !== '') {
        $subscription = stripe_api_request('GET', 'v1/subscriptions/' . urlencode($subscription), [
            'expand[]' => 'latest_invoice.payment_intent',
        ]);
    }
    if (!is_array($subscription)) {
        $subscription = [];
    }

    $stripeSubscriptionId = (string) ($subscription['id'] ?? '');
    $customerId = (string) ($session['customer'] ?? ($subscription['customer'] ?? ''));

    $latestInvoice = $session['latest_invoice'] ?? ($subscription['latest_invoice'] ?? null);
    if (is_string($latestInvoice) && $latestInvoice !== '') {
        $latestInvoice = stripe_api_request('GET', 'v1/invoices/' . urlencode($latestInvoice), [
            'expand[]' => 'payment_intent',
        ]);
    }
    if (!is_array($latestInvoice)) {
        $latestInvoice = [];
    }

    $latestInvoiceId = (string) ($latestInvoice['id'] ?? '');

    $intent = $latestInvoice['payment_intent'] ?? ($session['payment_intent'] ?? null);
    if (is_string($intent) && $intent !== '') {
        $intent = stripe_api_request('GET', 'v1/payment_intents/' . urlencode($intent));
    }
    if (!is_array($intent)) {
        $intent = [];
    }

    $intentId = (string) ($intent['id'] ?? '');
    if ($intentId === '') {
        if ($latestInvoiceId !== '') {
            $intentId = 'stripe-invoice:' . $latestInvoiceId;
        } elseif ($sessionIdentifier !== '') {
            $intentId = 'stripe-session:' . $sessionIdentifier;
        }
        if ($intentId === '') {
            throw new RuntimeException('Stripe did not return a payment intent for the checkout session.');
        }
    }

    $paymentMethodId = (string) ($intent['payment_method'] ?? ($intent['latest_charge']['payment_method'] ?? ''));
    if ($paymentMethodId === '') {
        $defaultPaymentMethod = $subscription['default_payment_method'] ?? ($latestInvoice['default_payment_method'] ?? null);
        if (is_string($defaultPaymentMethod) && $defaultPaymentMethod !== '') {
            $paymentMethodId = $defaultPaymentMethod;
        } elseif (is_array($defaultPaymentMethod)) {
            $paymentMethodId = (string) ($defaultPaymentMethod['id'] ?? '');
        }
    }

    $metadata = [
        'stripe_customer' => $customerId,
        'stripe_subscription_id' => $stripeSubscriptionId,
    ];
    if ($paymentMethodId !== '') {
        $metadata['stripe_payment_method'] = $paymentMethodId;
    }

    finalise_invoice_payment($pdo, $invoice, 'stripe', $intentId, (float) ($invoice['total'] ?? 0), $metadata);
}

/**
 * Force a subscription renewal cycle immediately.
 */
function trigger_subscription_cycle(PDO $pdo, int $subscriptionId, bool $force = false): array
{
    $stmt = $pdo->prepare('SELECT s.*, sv.price, sv.name AS service_name, u.email, u.name, o.payment_method FROM subscriptions s JOIN services sv ON sv.id = s.service_id JOIN users u ON u.id = s.user_id LEFT JOIN orders o ON o.id = s.order_id WHERE s.id = :id LIMIT 1');
    $stmt->execute(['id' => $subscriptionId]);
    $subscription = $stmt->fetch();
    if (!$subscription) {
        throw new RuntimeException('Subscription not found.');
    }

    $now = new \DateTimeImmutable();
    return process_subscription_cycle($pdo, $subscription, $now, $force);
}

function finalise_invoice_payment(PDO $pdo, array $invoice, string $provider, string $reference, ?float $amount = null, array $metadata = []): void
{
    $now = (new \DateTimeImmutable())->format(\DateTimeInterface::ATOM);
    $invoiceId = (int) ($invoice['id'] ?? 0);
    $orderId = (int) ($invoice['order_id'] ?? 0);
    $userId = (int) ($invoice['user_id'] ?? 0);
    $subscriptionId = (int) ($invoice['subscription_id'] ?? 0);

    if ($subscriptionId <= 0) {
        $ensuredSubscription = ensure_subscription_record($pdo, $invoice);
        if ($ensuredSubscription) {
            $subscriptionId = $ensuredSubscription;
            $invoice['subscription_id'] = $subscriptionId;
        }
    }
    $serviceName = $invoice['service_name'] ?? '';
    $clientName = $invoice['name'] ?? ($invoice['client_name'] ?? '');
    $clientEmail = $invoice['email'] ?? null;
    $total = $amount ?? (float) ($invoice['total'] ?? 0);

    if ($invoiceId <= 0 || $orderId <= 0 || $userId <= 0) {
        throw new RuntimeException('Unable to record payment without complete invoice details.');
    }

    $pdo->beginTransaction();
    try {
        $pdo->prepare('UPDATE orders SET payment_status = "paid", payment_reference = :reference, updated_at = :updated WHERE id = :id')
            ->execute([
                'reference' => $reference,
                'updated' => $now,
                'id' => $orderId,
            ]);

        $pdo->prepare('UPDATE invoices SET status = "paid", paid_at = :paid_at, updated_at = :updated WHERE id = :id')
            ->execute([
                'paid_at' => $now,
                'updated' => $now,
                'id' => $invoiceId,
            ]);

        $update = $pdo->prepare('UPDATE payments SET status = :status, updated_at = :updated WHERE invoice_id = :invoice_id AND provider = :provider AND reference = :reference');
        $update->execute([
            'status' => 'paid',
            'updated' => $now,
            'invoice_id' => $invoiceId,
            'provider' => $provider,
            'reference' => $reference,
        ]);

        if ($update->rowCount() === 0) {
            $pdo->prepare('INSERT INTO payments (invoice_id, provider, reference, amount, status, created_at, updated_at) VALUES (:invoice_id, :provider, :reference, :amount, :status, :created_at, :updated_at)')
                ->execute([
                    'invoice_id' => $invoiceId,
                    'provider' => $provider,
                    'reference' => $reference,
                    'amount' => $total,
                    'status' => 'paid',
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
        }

        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }

    if ($subscriptionId > 0) {
        $pdo->prepare('UPDATE subscriptions SET status = "active", updated_at = :updated WHERE id = :id AND status != "active"')
            ->execute([
                'updated' => $now,
                'id' => $subscriptionId,
            ]);

        $updates = [];
        if ($provider === 'stripe') {
            $stripeCustomer = trim((string) ($metadata['stripe_customer'] ?? ''));
            $stripeMethod = trim((string) ($metadata['stripe_payment_method'] ?? ''));
            if ($stripeCustomer !== '') {
                $updates['stripe_customer'] = $stripeCustomer;
            }
            if ($stripeMethod !== '') {
                $updates['stripe_payment_method'] = $stripeMethod;
            }
            $stripeSubscriptionRef = trim((string) ($metadata['stripe_subscription_id'] ?? ''));
            if ($stripeSubscriptionRef !== '') {
                $updates['stripe_subscription_id'] = $stripeSubscriptionRef;
            }
        } elseif ($provider === 'paypal') {
            $paypalSubscription = trim((string) ($metadata['paypal_subscription_id'] ?? ''));
            if ($paypalSubscription !== '') {
                $updates['paypal_subscription_id'] = $paypalSubscription;
            }
        }

        if (!empty($updates)) {
            $fields = [];
            foreach ($updates as $key => $value) {
                $fields[] = $key . ' = :' . $key;
            }
            $fields[] = 'updated_at = :updated_at';
            $updates['updated_at'] = $now;
            $updates['id'] = $subscriptionId;
            $stmt = $pdo->prepare('UPDATE subscriptions SET ' . implode(', ', $fields) . ' WHERE id = :id');
            $stmt->execute($updates);
        }
    }

    $replacements = [
        '{{name}}' => $clientName !== '' ? $clientName : 'there',
        '{{service}}' => $serviceName,
        '{{invoice}}' => (string) $invoiceId,
        '{{company}}' => get_setting('company_name', 'Service Portal'),
    ];

    $body = sprintf(
        "Hi %s,\n\nWe've recorded your payment for invoice #%s covering %s.",
        $clientName !== '' ? $clientName : 'there',
        $invoiceId,
        $serviceName
    );

    if ($clientEmail) {
        send_templated_email($pdo, 'invoice_payment_success', $replacements, $clientEmail, 'Payment received', $body);
    }

    record_notification($pdo, $userId, 'Invoice #' . $invoiceId . ' paid successfully.', url_for('dashboard/orders'));
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

function send_templated_email(PDO $pdo, string $slug, array $replacements, string $to, string $fallbackSubject, ?string $fallbackBody = null): bool
{
    $template = find_template($pdo, $slug);
    if ($template) {
        $subject = strtr($template['subject'], $replacements);
        $body = strtr($template['body'], $replacements);
    } else {
        $subject = $fallbackSubject;
        $body = $fallbackBody ?? $fallbackSubject;
    }
    return send_notification_email($to, $subject, $body);
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

function admin_user_ids(PDO $pdo): array
{
    $ids = [];
    foreach ($pdo->query("SELECT id FROM users WHERE role = 'admin'") as $row) {
        $ids[] = (int) $row['id'];
    }
    return $ids;
}

function admin_notification_emails(PDO $pdo): array
{
    $emails = [];

    foreach ($pdo->query("SELECT email FROM users WHERE role = 'admin'") as $row) {
        $email = strtolower(trim((string) ($row['email'] ?? '')));
        if ($email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $emails[] = $email;
        }
    }

    $supportEmail = trim((string) get_setting('support_email', ''));
    if ($supportEmail !== '' && filter_var($supportEmail, FILTER_VALIDATE_EMAIL)) {
        $emails[] = strtolower($supportEmail);
    }

    return array_values(array_unique($emails));
}

function notify_admins(PDO $pdo, string $message, ?string $link = null): void
{
    $company = get_setting('company_name', 'Service Portal');
    $subject = sprintf('[%s] %s', $company, $message);
    $body = $message;
    if ($link) {
        $body .= "\n\nOpen: " . $link;
    }

    foreach (admin_user_ids($pdo) as $adminId) {
        record_notification($pdo, $adminId, $message, $link);
    }

    foreach (admin_notification_emails($pdo) as $email) {
        send_notification_email($email, $subject, $body);
    }
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

function send_notification_email(string $to, string $subject, string $body): bool
{
    $config = require __DIR__ . '/config.php';
    $defaults = $config['mail'];
    $fromName = get_setting('mail_from_name', $defaults['from_name']);
    $fromAddress = get_setting('mail_from_address', $defaults['from_address']);
    $transport = strtolower((string) get_setting('mail_transport', 'mail'));

    $appendMailLog = static function (?string $note, ?\Throwable $error = null) use ($to, $subject, $body): void {
        $logDir = __DIR__ . '/data';
        if (!is_dir($logDir)) {
            mkdir($logDir, 0775, true);
        }
        $entry = sprintf(
            "[%s] %s | %s\n%s\n",
            (new \DateTimeImmutable())->format('c'),
            $to,
            $subject,
            $body
        );
        if ($note !== null && $note !== '') {
            $entry .= 'Note: ' . $note . "\n";
        }
        if ($error) {
            $entry .= 'Error: ' . $error->getMessage() . "\n";
        }
        file_put_contents($logDir . '/mail.log', $entry . "\n", FILE_APPEND);
    };

    $logFailure = static function (?\Throwable $error = null, ?string $note = null) use ($appendMailLog): void {
        $appendMailLog($note, $error);
    };

    $logDiagnostic = static function (string $note) use ($appendMailLog): void {
        $appendMailLog($note, null);
    };

    if ($transport === 'sendgrid') {
        $storedApiKey = trim((string) get_setting('sendgrid_api_key', ''));
        $envApiKey = getenv('SENDGRID_API_KEY');
        $envApiKey = is_string($envApiKey) ? trim($envApiKey) : '';
        $usingEnvApiKey = $envApiKey !== '';
        $apiKey = $usingEnvApiKey ? $envApiKey : $storedApiKey;
        $storedRegion = strtolower((string) get_setting('sendgrid_region', 'us'));
        $region = strtolower((string) (getenv('SENDGRID_REGION') ?: $storedRegion));

        if ($apiKey !== '' && class_exists(\SendGrid\Mail\Mail::class)) {
            try {
                $email = new \SendGrid\Mail\Mail();
                $email->setFrom($fromAddress, $fromName);
                $email->setSubject($subject);
                $email->addTo($to);
                $email->addContent('text/plain', $body);

                $normalizeRegion = static function (string $value): string {
                    $value = strtolower(trim($value));
                    return $value === 'eu' ? 'eu' : 'global';
                };

                $resolvedRegion = $normalizeRegion($region);
                $createClient = static function (string $targetRegion) use ($apiKey): \SendGrid {
                    $normalized = strtolower($targetRegion) === 'eu' ? 'eu' : 'global';
                    $options = [];
                    if ($normalized === 'eu') {
                        $options['host'] = 'https://api.eu.sendgrid.com';
                    }
                    $client = new \SendGrid($apiKey, $options);
                    if ($normalized === 'eu' && method_exists($client, 'setDataResidency')) {
                        $client->setDataResidency('eu');
                    }
                    return $client;
                };

                $sendgrid = $createClient($resolvedRegion);
                $response = $sendgrid->send($email);
                $status = $response->statusCode();
                if ($status >= 200 && $status < 300) {
                    return true;
                }

                $errorBody = trim((string) $response->body());
                $isRegionalBlock = $status === 401 && stripos($errorBody, 'regional attribute') !== false;

                if ($isRegionalBlock && $resolvedRegion !== 'eu') {
                    try {
                        $euResponse = $createClient('eu')->send($email);
                    } catch (Throwable $retryError) {
                        $logFailure(
                            $retryError,
                            'SendGrid EU retry failed. Switch to the EU region in Settings → Email delivery or export SENDGRID_REGION=eu, then try again.'
                        );
                        return false;
                    }

                    $euStatus = $euResponse->statusCode();
                    if ($euStatus >= 200 && $euStatus < 300) {
                        $logDiagnostic('Initial SendGrid request was rejected due to a regional mismatch, but retrying via the EU endpoint succeeded. Update Settings → Email delivery or export SENDGRID_REGION=eu to avoid the extra attempt.');
                        return true;
                    }

                    $euBody = trim((string) $euResponse->body());
                    $message = 'SendGrid API responded with HTTP ' . $euStatus;
                    if ($euBody !== '') {
                        $message .= ': ' . $euBody;
                    }

                    $logFailure(
                        new RuntimeException($message),
                        'Initial request returned HTTP 401 with a regional attribute error. Switch to the EU region in Settings → Email delivery or export SENDGRID_REGION=eu.'
                    );
                    return false;
                }

                $message = 'SendGrid API responded with HTTP ' . $status;
                if ($errorBody !== '') {
                    $message .= ': ' . $errorBody;
                }

                $noteParts = [];
                if ($isRegionalBlock) {
                    $noteParts[] = 'Switch to the EU region in Settings → Email delivery or export SENDGRID_REGION=eu.';
                }

                if ($status === 401) {
                    $bodyLower = strtolower($errorBody);
                    $decodedBody = null;
                    if ($errorBody !== '') {
                        $decoded = json_decode($errorBody, true);
                        if (is_array($decoded)) {
                            $decodedBody = $decoded;
                        }
                    }

                    if (
                        strpos($bodyLower, 'authorization grant is invalid') !== false
                        || strpos($bodyLower, 'invalid api key') !== false
                        || strpos($bodyLower, 'unauthorized') !== false
                    ) {
                        $noteParts[] = 'SendGrid rejected the request because the API key is no longer valid. Rotate the key from the SendGrid dashboard and paste the fresh value into Settings → Email delivery.';
                    }

                    if ($decodedBody && isset($decodedBody['errors']) && is_array($decodedBody['errors'])) {
                        foreach ($decodedBody['errors'] as $error) {
                            $detail = strtolower((string) ($error['message'] ?? ''));
                            if (strpos($detail, 'ip') !== false && strpos($detail, 'allowlist') !== false) {
                                $noteParts[] = 'If IP Access Management is enabled on your SendGrid account, allowlist the server IP or disable the restriction for this key before retrying.';
                            }
                            if (strpos($detail, 'permission') !== false) {
                                $noteParts[] = 'Confirm that the API key still has the "Mail Send" permission in the SendGrid dashboard.';
                            }
                        }
                    }

                    if ($usingEnvApiKey) {
                        $noteParts[] = 'A SENDGRID_API_KEY environment variable is set on the server and takes precedence over the saved key in Settings. Make sure that environment variable matches the active key or remove it before saving a new value.';
                    }
                }

                $note = null;
                if (!empty($noteParts)) {
                    $noteParts = array_values(array_unique($noteParts));
                    $note = implode(' ', $noteParts);
                }

                $logFailure(new RuntimeException($message), $note);
                return false;
            } catch (Throwable $sendgridError) {
                $logFailure($sendgridError);
                return false;
            }
        } elseif ($apiKey === '') {
            $logFailure(new RuntimeException('SendGrid API key is not configured.'));
            return false;
        } elseif (!class_exists(\SendGrid\Mail\Mail::class)) {
            $logFailure(new RuntimeException('SendGrid library is not installed.'));
            return false;
        }
        return false;
    }

    if ($transport === 'smtp') {
        $host = trim((string) get_setting('smtp_host', ''));
        $port = (int) get_setting('smtp_port', '587');
        $username = trim((string) get_setting('smtp_username', ''));
        $password = get_setting('smtp_password', '');
        $encryption = strtolower((string) get_setting('smtp_encryption', 'tls'));
        $verifyPeer = get_setting('smtp_verify_peer', '1') !== '0';
        $verifyPeerName = get_setting('smtp_verify_peer_name', '1') !== '0';
        $allowSelfSigned = get_setting('smtp_allow_self_signed', '0') === '1';

        $defaultVerifyPeer = $verifyPeer;
        $defaultVerifyPeerName = $verifyPeerName;
        $defaultAllowSelfSigned = $allowSelfSigned;

        $envHost = trim((string) getenv('SMTP_HOST'));
        if ($envHost !== '') {
            $host = $envHost;
        }

        $envPort = trim((string) getenv('SMTP_PORT'));
        if ($envPort !== '' && is_numeric($envPort)) {
            $port = (int) $envPort;
        }

        $envUsername = trim((string) getenv('SMTP_USERNAME'));
        if ($envUsername !== '') {
            $username = $envUsername;
        }

        $envPassword = getenv('SMTP_PASSWORD');
        if ($envPassword !== false && trim((string) $envPassword) !== '') {
            $password = (string) $envPassword;
        }

        $envEncryption = strtolower(trim((string) getenv('SMTP_ENCRYPTION')));
        if (in_array($envEncryption, ['tls', 'ssl', 'none'], true)) {
            $encryption = $envEncryption;
        }

        $envVerifyPeer = getenv('SMTP_VERIFY_PEER');
        if ($envVerifyPeer !== false && $envVerifyPeer !== '') {
            $verifyPeerValue = filter_var($envVerifyPeer, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
            if ($verifyPeerValue !== null) {
                $verifyPeer = (bool) $verifyPeerValue;
            } else {
                $verifyPeer = $defaultVerifyPeer;
            }
        }

        $envVerifyPeerName = getenv('SMTP_VERIFY_PEER_NAME');
        if ($envVerifyPeerName !== false && $envVerifyPeerName !== '') {
            $verifyPeerNameValue = filter_var($envVerifyPeerName, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
            if ($verifyPeerNameValue !== null) {
                $verifyPeerName = (bool) $verifyPeerNameValue;
            } else {
                $verifyPeerName = $defaultVerifyPeerName;
            }
        }

        $envAllowSelfSigned = getenv('SMTP_ALLOW_SELF_SIGNED');
        if ($envAllowSelfSigned !== false && $envAllowSelfSigned !== '') {
            $allowSelfSignedValue = filter_var($envAllowSelfSigned, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
            if ($allowSelfSignedValue !== null) {
                $allowSelfSigned = (bool) $allowSelfSignedValue;
            } else {
                $allowSelfSigned = $defaultAllowSelfSigned;
            }
        }

        if ($host !== '' && $username !== '' && $password !== '') {
            try {
                if (!class_exists('PHPMailer\\PHPMailer\\PHPMailer')) {
                    require_once __DIR__ . '/lib/PHPMailer/Exception.php';
                    require_once __DIR__ . '/lib/PHPMailer/PHPMailer.php';
                    require_once __DIR__ . '/lib/PHPMailer/SMTP.php';
                }

                $mailer = new PHPMailer\PHPMailer\PHPMailer(true);
                $mailer->CharSet = 'UTF-8';
                $mailer->Encoding = 'base64';
                $mailer->isSMTP();
                $mailer->Host = $host;
                $mailer->Port = $port ?: 587;
                $mailer->SMTPAuth = true;
                $mailer->Username = $username;
                $mailer->Password = $password;
                if ($encryption === 'ssl') {
                    $mailer->SMTPSecure = PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_SMTPS;
                } elseif ($encryption === 'tls') {
                    $mailer->SMTPSecure = PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
                } else {
                    $mailer->SMTPSecure = false;
                }
                $mailer->SMTPOptions = [
                    'ssl' => [
                        'verify_peer' => $verifyPeer,
                        'verify_peer_name' => $verifyPeerName,
                        'allow_self_signed' => $allowSelfSigned,
                    ],
                ];
                $mailer->setFrom($fromAddress, $fromName);
                $mailer->addAddress($to);
                $mailer->Subject = $subject;
                $mailer->Body = $body;
                $mailer->AltBody = $body;
                $mailer->send();
                return true;
            } catch (\Throwable $smtpError) {
                $logFailure($smtpError);
                return false;
            }
        }
        $logFailure(new RuntimeException('SMTP settings are incomplete.'));
        return false;
    }

    $headers = [
        'From: ' . $fromName . ' <' . $fromAddress . '>',
        'Reply-To: ' . $fromAddress,
        'Content-Type: text/plain; charset=UTF-8'
    ];

    $success = @mail($to, $subject, $body, implode("\r\n", $headers));

    if (!$success) {
        $logFailure();
    }
    return $success;
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
