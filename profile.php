<?php
require __DIR__ . '/bootstrap.php';
require_login();

$pdo = get_db();
$current = current_user();
$stmt = $pdo->prepare('SELECT * FROM users WHERE id = :id LIMIT 1');
$stmt->execute(['id' => $current['id']]);
$user = $stmt->fetch();

if (!$user) {
    logout();
    flash('error', 'Your profile could not be found. Please sign in again.');
    redirect('login');
}

if (is_post()) {
    $action = $_POST['action'] ?? '';

    try {
        switch ($action) {
            case 'update_profile':
                $name = trim($_POST['name'] ?? '');
                $company = trim($_POST['company'] ?? '');
                $phone = trim($_POST['phone'] ?? '');
                $avatar = trim($_POST['avatar_url'] ?? '');
                if ($name === '') {
                    throw new RuntimeException('Please provide your full name.');
                }
                $pdo->prepare('UPDATE users SET name = :name, company = :company, phone = :phone, avatar_url = :avatar, updated_at = :updated WHERE id = :id')
                    ->execute([
                        'name' => $name,
                        'company' => $company,
                        'phone' => $phone,
                        'avatar' => $avatar,
                        'updated' => (new \DateTimeImmutable())->format(\DateTimeInterface::ATOM),
                        'id' => $user['id'],
                    ]);
                $_SESSION['user']['name'] = $name;
                flash('success', 'Profile updated.');
                break;
            case 'change_password':
                $currentPassword = $_POST['current_password'] ?? '';
                $newPassword = $_POST['new_password'] ?? '';
                $confirm = $_POST['confirm_password'] ?? '';
                if (!password_verify($currentPassword, $user['password_hash'])) {
                    throw new RuntimeException('Your current password is incorrect.');
                }
                if ($newPassword === '' || strlen($newPassword) < 8) {
                    throw new RuntimeException('New password must be at least 8 characters.');
                }
                if ($newPassword !== $confirm) {
                    throw new RuntimeException('New passwords do not match.');
                }
                $pdo->prepare('UPDATE users SET password_hash = :password, updated_at = :updated WHERE id = :id')
                    ->execute([
                        'password' => password_hash($newPassword, PASSWORD_DEFAULT),
                        'updated' => (new \DateTimeImmutable())->format(\DateTimeInterface::ATOM),
                        'id' => $user['id'],
                    ]);
                flash('success', 'Password updated successfully.');
                break;
            case 'begin_totp':
                $_SESSION['totp_setup'] = [
                    'secret' => random_base32(32),
                ];
                flash('success', 'Scan the secret below with your authenticator app, then enter the 6-digit code.');
                break;
            case 'confirm_totp':
                if (empty($_SESSION['totp_setup']['secret'])) {
                    throw new RuntimeException('Start the setup process first.');
                }
                $token = trim($_POST['token'] ?? '');
                if ($token === '' || !verify_totp($_SESSION['totp_setup']['secret'], $token)) {
                    throw new RuntimeException('That code is not valid. Please try again.');
                }
                $codes = generate_recovery_codes();
                $pdo->prepare('UPDATE users SET totp_secret = :secret, totp_enabled = 1, totp_recovery_codes = :codes, updated_at = :updated WHERE id = :id')
                    ->execute([
                        'secret' => $_SESSION['totp_setup']['secret'],
                        'codes' => json_encode($codes, JSON_THROW_ON_ERROR),
                        'updated' => (new \DateTimeImmutable())->format(\DateTimeInterface::ATOM),
                        'id' => $user['id'],
                    ]);
                unset($_SESSION['totp_setup']);
                $_SESSION['user']['totp_enabled'] = true;
                flash('success', 'Two-factor authentication is now enabled. Store your recovery codes safely.');
                break;
            case 'disable_totp':
                $pdo->prepare('UPDATE users SET totp_secret = NULL, totp_enabled = 0, totp_recovery_codes = NULL, updated_at = :updated WHERE id = :id')
                    ->execute([
                        'updated' => (new \DateTimeImmutable())->format(\DateTimeInterface::ATOM),
                        'id' => $user['id'],
                    ]);
                unset($_SESSION['totp_setup']);
                $_SESSION['user']['totp_enabled'] = false;
                flash('success', 'Two-factor authentication disabled.');
                break;
            case 'regenerate_codes':
                if (empty($user['totp_enabled']) || empty($user['totp_secret'])) {
                    throw new RuntimeException('Enable two-factor authentication first.');
                }
                $codes = generate_recovery_codes();
                $pdo->prepare('UPDATE users SET totp_recovery_codes = :codes, updated_at = :updated WHERE id = :id')
                    ->execute([
                        'codes' => json_encode($codes, JSON_THROW_ON_ERROR),
                        'updated' => (new \DateTimeImmutable())->format(\DateTimeInterface::ATOM),
                        'id' => $user['id'],
                    ]);
                flash('success', 'New recovery codes generated.');
                break;
        }
    } catch (Throwable $e) {
        flash('error', $e->getMessage());
    }

    redirect('profile');
}

$stmt->execute(['id' => $user['id']]);
$user = $stmt->fetch();
$pendingSetup = $_SESSION['totp_setup']['secret'] ?? null;
$totpUri = $pendingSetup ? sprintf('otpauth://totp/%s:%s?secret=%s&issuer=%s', rawurlencode(get_setting('company_name', 'Service Portal')), rawurlencode($user['email']), $pendingSetup, rawurlencode(get_setting('company_name', 'Service Portal'))) : null;
$recoveryCodes = $user['totp_recovery_codes'] ? json_decode($user['totp_recovery_codes'], true) : [];
$company = get_setting('company_name', 'Service Portal');
$logo = get_setting('brand_logo_url', '');

?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Profile & security · <?= e($company); ?></title>
    <link rel="stylesheet" href="<?= e(url_for('static/css/app.css')); ?>">
    <style><?= theme_styles(); ?></style>
</head>
<body class="profile-body">
    <div class="profile-wrapper">
        <aside class="profile-sidebar">
            <div class="profile-brand">
                <?php if ($logo): ?>
                    <img src="<?= e($logo); ?>" alt="<?= e($company); ?>">
                <?php else: ?>
                    <span><?= strtoupper(substr($company, 0, 1)); ?></span>
                <?php endif; ?>
                <strong><?= e($company); ?></strong>
            </div>
            <nav>
                <a href="<?= e(url_for('dashboard')); ?>">← Back to dashboard</a>
            </nav>
        </aside>
        <main class="profile-main">
            <header>
                <h1>Profile & security</h1>
                <p>Manage your personal details and secure your account.</p>
            </header>
            <?php foreach (['error', 'success'] as $type): ?>
                <?php if ($message = flash($type)): ?>
                    <div class="auth-alert auth-alert--<?= $type; ?>"><?= e($message); ?></div>
                <?php endif; ?>
            <?php endforeach; ?>
            <section class="profile-section">
                <h2>Account details</h2>
                <form action="<?= e(url_for('profile')); ?>" method="post" class="profile-form">
                    <input type="hidden" name="action" value="update_profile">
                    <label>Name
                        <input type="text" name="name" value="<?= e($user['name']); ?>" required>
                    </label>
                    <label>Company
                        <input type="text" name="company" value="<?= e($user['company']); ?>">
                    </label>
                    <label>Phone
                        <input type="text" name="phone" value="<?= e($user['phone']); ?>">
                    </label>
                    <label>Avatar URL
                        <input type="url" name="avatar_url" value="<?= e($user['avatar_url']); ?>">
                    </label>
                    <button type="submit">Save profile</button>
                </form>
            </section>
            <section class="profile-section">
                <h2>Password</h2>
                <form action="<?= e(url_for('profile')); ?>" method="post" class="profile-form">
                    <input type="hidden" name="action" value="change_password">
                    <label>Current password
                        <input type="password" name="current_password" required>
                    </label>
                    <label>New password
                        <input type="password" name="new_password" minlength="8" required>
                    </label>
                    <label>Confirm new password
                        <input type="password" name="confirm_password" minlength="8" required>
                    </label>
                    <button type="submit">Update password</button>
                </form>
            </section>
            <section class="profile-section">
                <h2>Two-factor authentication</h2>
                <?php if (!empty($user['totp_enabled'])): ?>
                    <p>Two-factor authentication is <strong>enabled</strong> for this account.</p>
                    <?php if ($recoveryCodes): ?>
                        <div class="recovery-list">
                            <?php foreach ($recoveryCodes as $code): ?>
                                <code><?= e($code); ?></code>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                    <form action="<?= e(url_for('profile')); ?>" method="post" class="inline-form">
                        <input type="hidden" name="action" value="regenerate_codes">
                        <button type="submit">Generate new recovery codes</button>
                    </form>
                    <form action="<?= e(url_for('profile')); ?>" method="post" class="inline-form" onsubmit="return confirm('Disable two-factor authentication?');">
                        <input type="hidden" name="action" value="disable_totp">
                        <button type="submit" class="danger">Disable 2FA</button>
                    </form>
                <?php else: ?>
                    <p>Protect your account with an authenticator app.</p>
                    <?php if ($pendingSetup): ?>
                        <div class="totp-setup">
                            <p class="totp-secret">Secret: <strong><?= e($pendingSetup); ?></strong></p>
                            <?php if ($totpUri): ?>
                                <p><small>Add manually: <?= e($totpUri); ?></small></p>
                            <?php endif; ?>
                            <form action="<?= e(url_for('profile')); ?>" method="post" class="profile-form">
                                <input type="hidden" name="action" value="confirm_totp">
                                <label>Authenticator code
                                    <input type="text" name="token" inputmode="numeric" pattern="[0-9]*" minlength="6" maxlength="6" required>
                                </label>
                                <button type="submit">Confirm & enable</button>
                            </form>
                        </div>
                    <?php else: ?>
                        <form action="<?= e(url_for('profile')); ?>" method="post" class="inline-form">
                            <input type="hidden" name="action" value="begin_totp">
                            <button type="submit">Start setup</button>
                        </form>
                    <?php endif; ?>
                <?php endif; ?>
            </section>
        </main>
    </div>
</body>
</html>
