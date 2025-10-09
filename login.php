<?php
require __DIR__ . '/bootstrap.php';

if (current_user()) {
    redirect('dashboard');
}

if (is_post()) {
    $email = strtolower(trim($_POST['email'] ?? ''));
    $password = $_POST['password'] ?? '';

    if (!verify_turnstile($_POST['cf-turnstile-response'] ?? '', $_SERVER['REMOTE_ADDR'] ?? null)) {
        flash('error', 'Please confirm you are human before signing in.');
    } elseif ($email === '' || $password === '') {
        flash('error', 'Please enter both your email and password.');
    } else {
        $stmt = $pdo->prepare('SELECT * FROM users WHERE email = :email LIMIT 1');
        $stmt->execute(['email' => $email]);
        $user = $stmt->fetch();

        if (!$user || !password_verify($password, $user['password_hash'])) {
            flash('error', 'Incorrect email or password.');
        } else {
            if (!empty($user['totp_enabled'])) {
                $_SESSION['pending_2fa_user'] = (int) $user['id'];
                flash('success', 'Password accepted. Enter your authentication code to continue.');
                redirect('verify-otp');
            }

            login($user);
            flash('success', 'Welcome back, ' . $user['name'] . '!');
            redirect('dashboard');
        }
    }
}

$pageTitle = 'Login';
$authView = 'login';
include __DIR__ . '/templates/auth_shell.php';
