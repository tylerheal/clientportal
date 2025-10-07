<?php
require __DIR__ . '/bootstrap.php';

$path = trim(request_path(), '/');
$segments = $path === '' ? [] : explode('/', $path);

if ($path === '') {
    require __DIR__ . '/login.php';
    return;
}

$first = $segments[0] ?? null;

if ($first === 'dashboard') {
    array_shift($segments);
    $_GET['view'] = $segments[0] ?? 'overview';
    if (isset($segments[1])) {
        $_GET['resource_id'] = $segments[1];
    }
    require __DIR__ . '/dashboard.php';
    return;
}

if ($first === 'admin') {
    array_shift($segments);
    $_GET['admin_view'] = $segments[0] ?? 'overview';
    if (isset($segments[1])) {
        $_GET['resource_id'] = $segments[1];
    }
    require __DIR__ . '/dashboard.php';
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
