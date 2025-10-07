<?php
require __DIR__ . '/bootstrap.php';

if (current_user()) {
    redirect('dashboard');
}

$pendingId = $_SESSION['pending_2fa_user'] ?? null;
if (!$pendingId) {
    redirect('login');
}

$stmt = $pdo->prepare('SELECT * FROM users WHERE id = :id LIMIT 1');
$stmt->execute(['id' => $pendingId]);
$user = $stmt->fetch();

if (!$user) {
    unset($_SESSION['pending_2fa_user']);
    flash('error', 'That session has expired. Please sign in again.');
    redirect('login');
}

if (is_post()) {
    $token = trim($_POST['token'] ?? '');
    $recovery = strtoupper(trim($_POST['recovery'] ?? ''));

    if ($token === '' && $recovery === '') {
        flash('error', 'Enter either your authenticator code or a recovery code.');
    } else {
        $verified = false;
        if ($token !== '' && $user['totp_secret']) {
            $verified = verify_totp($user['totp_secret'], $token);
        }

        if (!$verified && $recovery !== '' && $user['totp_recovery_codes']) {
            $codes = json_decode($user['totp_recovery_codes'], true) ?: [];
            $index = array_search($recovery, $codes, true);
            if ($index !== false) {
                $verified = true;
                unset($codes[$index]);
                $stmt = $pdo->prepare('UPDATE users SET totp_recovery_codes = :codes, updated_at = :updated WHERE id = :id');
                $stmt->execute([
                    'codes' => json_encode(array_values($codes), JSON_THROW_ON_ERROR),
                    'updated' => (new \DateTimeImmutable())->format(\DateTimeInterface::ATOM),
                    'id' => $user['id'],
                ]);
            }
        }

        if ($verified) {
            unset($_SESSION['pending_2fa_user']);
            login($user);
            flash('success', 'Authentication successful. Welcome back, ' . $user['name'] . '!');
            redirect('dashboard');
        } else {
            flash('error', 'The code provided was not recognised. Try again.');
        }
    }
}

$pageTitle = 'Two-factor authentication';
$authView = 'verify-otp';
include __DIR__ . '/templates/auth_shell.php';
