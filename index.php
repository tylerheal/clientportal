<?php
$path = trim(parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH), '/');
if ($path === '') {
    require __DIR__ . '/login.php';
    return;
}

$routes = [
    'login' => 'login.php',
    'signup' => 'signup.php',
    'dashboard' => 'dashboard.php',
    'logout' => 'logout.php',
    'profile' => 'profile.php',
    'verify-otp' => 'verify-otp.php',
];

if (isset($routes[$path])) {
    require __DIR__ . '/' . $routes[$path];
    return;
}

http_response_code(404);
echo 'Page not found';
