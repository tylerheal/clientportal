<?php
require __DIR__ . '/bootstrap.php';

if (current_user()) {
    redirect('dashboard');
}

if (is_post()) {
    $first = trim($_POST['first_name'] ?? '');
    $last = trim($_POST['last_name'] ?? '');
    $name = trim($first . ' ' . $last);
    $email = strtolower(trim($_POST['email'] ?? ''));
    $password = $_POST['password'] ?? '';
    $confirm = $_POST['password_confirm'] ?? '';

    if ($first === '' || $last === '' || $email === '' || $password === '') {
        flash('error', 'Please complete all required fields.');
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        flash('error', 'Please use a valid email address.');
    } elseif ($password !== $confirm) {
        flash('error', 'Your passwords do not match.');
    } else {
        $exists = $pdo->prepare('SELECT COUNT(*) FROM users WHERE email = :email');
        $exists->execute(['email' => $email]);
        if ($exists->fetchColumn()) {
            flash('error', 'An account already exists with that email address.');
        } else {
            $now = (new \DateTimeImmutable())->format(\DateTimeInterface::ATOM);
            $stmt = $pdo->prepare('INSERT INTO users (email, password_hash, name, company, phone, role, created_at, updated_at)
                VALUES (:email, :password_hash, :name, :company, :phone, :role, :created_at, :updated_at)');
            $stmt->execute([
                'email' => $email,
                'password_hash' => password_hash($password, PASSWORD_DEFAULT),
                'name' => $name,
                'company' => trim($_POST['company'] ?? ''),
                'phone' => trim($_POST['phone'] ?? ''),
                'role' => 'client',
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            $userId = (int) $pdo->lastInsertId();
            $user = $pdo->query('SELECT * FROM users WHERE id = ' . $userId)->fetch();
            login($user);
            flash('success', 'Welcome aboard! Your client portal is ready.');
            redirect('dashboard');
        }
    }
}

$pageTitle = 'Create your account';
$authView = 'signup';
include __DIR__ . '/templates/auth_shell.php';
