<?php
declare(strict_types=1);

if (!defined('APP_BOOTSTRAPPED')) {
    define('APP_BOOTSTRAPPED', true);
}

session_start([
    'cookie_httponly' => true,
    'cookie_secure' => isset($_SERVER['HTTPS']),
]);

require_once __DIR__ . '/database.php';
require_once __DIR__ . '/helpers.php';

$pdo = get_db();

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

function logout(): void
{
    unset($_SESSION['user']);
    session_regenerate_id(true);
}
