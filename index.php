<?php
$path = trim(parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH), '/');
$segments = $path === '' ? [] : explode('/', $path);

if ($path === '') {
    require __DIR__ . '/login.php';
    return;
}

$first = $segments[0] ?? null;

if ($first === 'dashboard') {
    $_GET['view'] = $segments[1] ?? 'overview';
    if (isset($segments[2])) {
        $_GET['resource_id'] = $segments[2];
    }
    require __DIR__ . '/dashboard.php';
    return;
}

if ($first === 'admin') {
    $_GET['admin_view'] = $segments[1] ?? 'overview';
    if (isset($segments[2])) {
        $_GET['resource_id'] = $segments[2];
    }
    require __DIR__ . '/admin/dashboard.php';
    return;
}

$routes = [
    'login' => 'login.php',
    'signup' => 'signup.php',
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
