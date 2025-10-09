<?php
declare(strict_types=1);

if (!defined('APP_BOOTSTRAPPED')) {
    define('APP_BOOTSTRAPPED', true);
}

$autoload = __DIR__ . '/vendor/autoload.php';
if (is_readable($autoload)) {
    require_once $autoload;
}

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start([
        'cookie_httponly' => true,
        'cookie_secure' => isset($_SERVER['HTTPS']),
    ]);
}

require_once __DIR__ . '/database.php';
require_once __DIR__ . '/helpers.php';

$pdo = get_db();

if (!function_exists('login')) {
    function login(array $user): void
    {
        session_regenerate_id(true);
        $_SESSION['user'] = [
            'id' => $user['id'],
            'email' => $user['email'],
            'name' => $user['name'],
            'role' => $user['role'],
            'totp_enabled' => (bool) ($user['totp_enabled'] ?? false),
        ];
    }
}

if (!function_exists('logout')) {
    function logout(): void
    {
        unset($_SESSION['user']);
        session_regenerate_id(true);
    }
}
