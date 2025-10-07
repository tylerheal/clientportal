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

$settings = read_user_settings($user);
$notificationDefaults = [
    'account' => ['security' => true, 'login' => true],
    'orders' => ['placed' => true, 'paid' => true, 'overdue' => true],
    'tickets' => ['replied' => true, 'assigned' => true, 'closed' => true],
    'forms' => ['assigned' => true, 'reminders' => false],
];
$timezones = [
    'UTC' => 'Coordinated Universal Time (UTC)',
    'Europe/London' => 'London (GMT/BST)',
    'Europe/Paris' => 'Paris (CET)',
    'America/New_York' => 'New York (EST/EDT)',
    'America/Los_Angeles' => 'Los Angeles (PST/PDT)',
    'Asia/Dubai' => 'Dubai (GST)',
    'Australia/Sydney' => 'Sydney (AEST/AEDT)',
];

if (is_post()) {
    $action = $_POST['action'] ?? '';

    try {
        switch ($action) {
            case 'update_profile':
                $firstName = trim($_POST['first_name'] ?? '');
                $lastName = trim($_POST['last_name'] ?? '');
                $company = trim($_POST['company'] ?? '');
                $phone = trim($_POST['phone'] ?? '');
                $avatar = trim($_POST['avatar_url'] ?? '');
                $timezoneChoice = $_POST['timezone'] ?? 'UTC';
                $pushBrowser = isset($_POST['push_browser']);
                if ($firstName === '' && $lastName === '') {
                    throw new RuntimeException('Please provide at least a first or last name.');
                }
                if (!array_key_exists($timezoneChoice, $timezones)) {
                    $timezoneChoice = 'UTC';
                }
                $name = trim($firstName . ' ' . $lastName);
                $pdo->prepare('UPDATE users SET name = :name, company = :company, phone = :phone, avatar_url = :avatar, updated_at = :updated WHERE id = :id')
                    ->execute([
                        'name' => $name,
                        'company' => $company,
                        'phone' => $phone,
                        'avatar' => $avatar,
                        'updated' => (new \DateTimeImmutable())->format(\DateTimeInterface::ATOM),
                        'id' => $user['id'],
                    ]);
                $settings['profile'] = [
                    'first_name' => $firstName,
                    'last_name' => $lastName,
                    'timezone' => $timezoneChoice,
                    'push_browser' => $pushBrowser,
                ];
                $settingsJson = persist_user_settings($pdo, (int) $user['id'], $settings);
                $_SESSION['user']['name'] = $name;
                $_SESSION['user']['company'] = $company;
                $_SESSION['user']['phone'] = $phone;
                $_SESSION['user']['avatar_url'] = $avatar;
                $_SESSION['user']['settings'] = $settingsJson;
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
            case 'update_notifications':
                $submitted = $_POST['notifications'] ?? [];
                $compiled = $notificationDefaults;
                foreach ($notificationDefaults as $group => $options) {
                    foreach ($options as $key => $default) {
                        $compiled[$group][$key] = isset($submitted[$group][$key]);
                    }
                }
                $settings['notifications'] = $compiled;
                $settingsJson = persist_user_settings($pdo, (int) $user['id'], $settings);
                $_SESSION['user']['settings'] = $settingsJson;
                flash('success', 'Notification preferences updated.');
                break;
            case 'begin_totp':
                $_SESSION['totp_setup'] = [
                    'secret' => random_base32(32),
                ];
                flash('info', 'Scan the secret below with your authenticator app, then enter the 6-digit code.');
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

$cancel = $_GET['cancel_totp'] ?? null;
if ($cancel !== null) {
    unset($_SESSION['totp_setup']);
    flash('success', 'Two-factor setup cancelled.');
    redirect('profile');
}

$stmt->execute(['id' => $user['id']]);
$user = $stmt->fetch();
$settings = read_user_settings($user);
$profileSettings = $settings['profile'] ?? [];
$nameParts = explode(' ', trim($user['name']), 2);
$firstName = $profileSettings['first_name'] ?? ($nameParts[0] ?? '');
$lastName = $profileSettings['last_name'] ?? ($nameParts[1] ?? '');
$timezoneChoice = $profileSettings['timezone'] ?? 'UTC';
$pushBrowser = !empty($profileSettings['push_browser']);
$notificationPrefs = array_replace_recursive($notificationDefaults, $settings['notifications'] ?? []);
$pendingSetup = $_SESSION['totp_setup']['secret'] ?? null;
$totpUri = $pendingSetup ? sprintf('otpauth://totp/%s:%s?secret=%s&issuer=%s', rawurlencode(get_setting('company_name', 'Service Portal')), rawurlencode($user['email']), $pendingSetup, rawurlencode(get_setting('company_name', 'Service Portal'))) : null;
$recoveryCodes = $user['totp_recovery_codes'] ? json_decode($user['totp_recovery_codes'], true) : [];
 $role = $user['role'] ?? 'client';

$adminSidebar = [
    ['type' => 'group', 'label' => 'Activity'],
    ['key' => 'overview', 'label' => 'Overview', 'href' => url_for('admin/overview')],
    ['key' => 'orders', 'label' => 'Orders', 'href' => url_for('admin/orders')],
    ['key' => 'tickets', 'label' => 'Tickets', 'href' => url_for('admin/tickets')],
    ['key' => 'clients', 'label' => 'Clients', 'href' => url_for('admin/clients')],
    ['type' => 'group', 'label' => 'Billing'],
    ['key' => 'invoices', 'label' => 'Invoices', 'href' => url_for('admin/invoices')],
    ['key' => 'payments', 'label' => 'Payments', 'href' => url_for('admin/payments')],
    ['type' => 'group', 'label' => 'Marketing'],
    ['key' => 'automations', 'label' => 'Automations', 'href' => url_for('admin/automations')],
    ['type' => 'group', 'label' => 'Setup'],
    ['key' => 'services', 'label' => 'Services', 'href' => url_for('admin/services')],
    ['key' => 'forms', 'label' => 'Forms', 'href' => url_for('admin/forms')],
    ['key' => 'administrators', 'label' => 'Admins', 'href' => url_for('admin/administrators')],
    ['key' => 'settings', 'label' => 'Settings', 'href' => url_for('admin/settings')],
];

$clientSidebar = [
    ['type' => 'group', 'label' => 'Activity'],
    ['key' => 'overview', 'label' => 'Overview', 'href' => url_for('dashboard')],
    ['key' => 'orders', 'label' => 'Orders', 'href' => url_for('dashboard/orders')],
    ['key' => 'tickets', 'label' => 'Support', 'href' => url_for('dashboard/tickets')],
    ['type' => 'group', 'label' => 'Services'],
    ['key' => 'services', 'label' => 'Services', 'href' => url_for('dashboard/services')],
    ['key' => 'forms', 'label' => 'Forms', 'href' => url_for('dashboard/forms')],
    ['type' => 'group', 'label' => 'Billing'],
    ['key' => 'invoices', 'label' => 'Invoices', 'href' => url_for('dashboard/invoices')],
];

$sidebar = $role === 'admin' ? $adminSidebar : $clientSidebar;
$company = get_setting('company_name', 'Service Portal');
$pageTitle = 'Profile & security';
$bodyClass = 'profile-view';
$searchAction = $role === 'admin' ? url_for('admin/overview') : url_for('dashboard');
$backHref = $role === 'admin' ? url_for('admin/overview') : url_for('dashboard');
$activeKey = '';

ob_start();
?>
<section class="page-section profile-page">
    <header class="page-header profile-header">
        <div>
            <h1>Profile &amp; security</h1>
            <p>Update your personal details, security options, and notifications.</p>
        </div>
        <a class="button button--ghost" href="<?= e($backHref); ?>">← Back to dashboard</a>
    </header>
    <?php foreach (['error', 'success', 'info'] as $type): ?>
        <?php if ($message = flash($type)): ?>
            <div class="alert alert--<?= $type; ?>"><?= e($message); ?></div>
        <?php endif; ?>
    <?php endforeach; ?>

    <div class="profile-columns">
        <article class="card profile-card">
            <header class="profile-card__header">
                <div>
                    <h2>Account details</h2>
                    <p>Keep your contact information and preferences current.</p>
                </div>
            </header>
            <form action="<?= e(url_for('profile')); ?>" method="post" class="form-grid form-grid--two">
                <input type="hidden" name="action" value="update_profile">
                <label>First name
                    <input type="text" name="first_name" value="<?= e($firstName); ?>" required>
                </label>
                <label>Last name
                    <input type="text" name="last_name" value="<?= e($lastName); ?>">
                </label>
                <label>Email address
                    <input type="email" value="<?= e($user['email']); ?>" disabled>
                </label>
                <label>Company
                    <input type="text" name="company" value="<?= e($user['company']); ?>">
                </label>
                <label>Phone number
                    <input type="text" name="phone" value="<?= e($user['phone']); ?>">
                </label>
                <label>Timezone
                    <select name="timezone">
                        <?php foreach ($timezones as $zone => $label): ?>
                            <option value="<?= e($zone); ?>" <?= $zone === $timezoneChoice ? 'selected' : ''; ?>><?= e($label); ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <label>Avatar URL
                    <input type="url" name="avatar_url" placeholder="https://" value="<?= e($user['avatar_url']); ?>">
                    <span class="hint">Provide a direct image link for your profile avatar.</span>
                </label>
                <label class="toggle">
                    <input type="checkbox" name="push_browser" <?= $pushBrowser ? 'checked' : ''; ?>>
                    <span>Enable push notifications in this browser</span>
                </label>
                <div class="form-actions">
                    <button type="submit" class="button button--primary">Save changes</button>
                </div>
            </form>
        </article>

        <article class="card profile-card">
            <header class="profile-card__header">
                <div>
                    <h2>Security</h2>
                    <p>Update your password and manage two-factor authentication.</p>
                </div>
            </header>
            <form action="<?= e(url_for('profile')); ?>" method="post" class="form-grid form-grid--two">
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
                <div class="form-actions">
                    <button type="submit" class="button button--ghost">Update password</button>
                </div>
            </form>
            <div class="two-factor-panel">
                <div>
                    <h3>Two-factor authentication</h3>
                    <p>Add an extra layer of protection using an authenticator app.</p>
                </div>
                <div class="two-factor-actions">
                    <?php if (!empty($user['totp_enabled'])): ?>
                        <span class="badge badge--success">Enabled</span>
                        <form action="<?= e(url_for('profile')); ?>" method="post" class="inline-form">
                            <input type="hidden" name="action" value="disable_totp">
                            <button type="submit" class="button button--ghost">Disable 2FA</button>
                        </form>
                        <form action="<?= e(url_for('profile')); ?>" method="post" class="inline-form">
                            <input type="hidden" name="action" value="regenerate_codes">
                            <button type="submit" class="button button--ghost">Regenerate recovery codes</button>
                        </form>
                    <?php else: ?>
                        <span class="badge badge--pending">Disabled</span>
                        <form action="<?= e(url_for('profile')); ?>" method="post" class="inline-form">
                            <input type="hidden" name="action" value="begin_totp">
                            <button type="submit" class="button button--primary">Enable 2FA</button>
                        </form>
                    <?php endif; ?>
                </div>
            </div>
        </article>
    </div>

    <article class="card profile-card profile-card--notifications">
        <header class="profile-card__header">
            <div>
                <h2>Notifications</h2>
                <p>Select which updates arrive in your inbox.</p>
            </div>
        </header>
        <form action="<?= e(url_for('profile')); ?>" method="post" class="notification-groups">
            <input type="hidden" name="action" value="update_notifications">
            <?php
            $notificationLabels = [
                'account' => 'Account notifications',
                'orders' => 'Order notifications',
                'tickets' => 'Ticket notifications',
                'forms' => 'Onboarding forms',
            ];
            $notificationLead = [
                'account' => 'Security alerts and account changes.',
                'orders' => 'Billing updates and payment reminders.',
                'tickets' => 'Support ticket activity and assignments.',
                'forms' => 'Onboarding and intake reminders.',
            ];
            $notificationDescriptions = [
                'account' => ['security' => 'Alerts when account security settings change.', 'login' => 'Email when we detect a new device login.'],
                'orders' => ['placed' => 'Send me a receipt when an order is placed.', 'paid' => 'Notify me when an order is paid.', 'overdue' => 'Send reminders before and after an invoice is due.'],
                'tickets' => ['replied' => 'Let me know when staff reply to a ticket.', 'assigned' => 'Alert me if a ticket is assigned to a teammate.', 'closed' => 'Email when a ticket is closed.'],
                'forms' => ['assigned' => 'Email me when a form is assigned.', 'reminders' => 'Send reminders for incomplete forms.'],
            ];
            ?>
            <div class="notification-columns">
                <?php foreach ($notificationLabels as $group => $title): ?>
                    <section class="notification-group">
                        <header>
                            <h3><?= e($title); ?></h3>
                            <?php if (!empty($notificationLead[$group])): ?>
                                <p><?= e($notificationLead[$group]); ?></p>
                            <?php endif; ?>
                        </header>
                        <ul>
                            <?php foreach ($notificationDescriptions[$group] as $key => $copy): ?>
                                <li class="toggle-item">
                                    <label>
                                        <input type="checkbox" name="notifications[<?= e($group); ?>][<?= e($key); ?>]" <?= !empty($notificationPrefs[$group][$key]) ? 'checked' : ''; ?>>
                                        <span>
                                            <strong><?= e(ucfirst(str_replace('_', ' ', $key))); ?></strong>
                                            <small><?= e($copy); ?></small>
                                        </span>
                                    </label>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    </section>
                <?php endforeach; ?>
            </div>
            <div class="form-actions">
                <button type="submit" class="button button--primary">Save notification settings</button>
            </div>
        </form>
    </article>

    <?php if (!empty($recoveryCodes)): ?>
        <article class="card profile-card">
            <header class="profile-card__header">
                <div>
                    <h2>Recovery codes</h2>
                    <p>Store these one-time codes somewhere secure. Each code is valid once.</p>
                </div>
            </header>
            <div class="profile-recovery">
                <ul>
                    <?php foreach ($recoveryCodes as $code): ?>
                        <li><?= e($code); ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        </article>
    <?php endif; ?>

    <?php if ($pendingSetup): ?>
        <div class="modal-overlay" data-modal="totp" data-qr="<?= e($totpUri); ?>">
            <div class="modal-card">
                <button class="modal-close" data-close-modal>&times;</button>
                <h3>Scan to enable 2FA</h3>
                <p>Scan the code with your authenticator app or enter the secret manually.</p>
                <div id="qr-container" class="qr-box"></div>
                <p class="modal-secret"><strong>Secret:</strong> <?= e($pendingSetup); ?></p>
                <form action="<?= e(url_for('profile')); ?>" method="post" class="form-grid">
                    <input type="hidden" name="action" value="confirm_totp">
                    <label>6-digit code
                        <input type="text" name="token" inputmode="numeric" pattern="[0-9]*" minlength="6" maxlength="6" required>
                    </label>
                    <div class="form-actions">
                        <button type="submit" class="button button--primary">Enable 2FA</button>
                    </div>
                </form>
            </div>
        </div>
    <?php endif; ?>
</section>
<?php
$content = ob_get_clean();

if ($pendingSetup) {
    $pageScripts[] = url_for('static/js/qrcode.js');
    $cancelUrl = json_encode(url_for('profile') . '?cancel_totp=1', JSON_THROW_ON_ERROR);
    $inlineScripts[] = <<<JS
document.addEventListener('DOMContentLoaded', () => {
    const modal = document.querySelector('[data-modal="totp"]');
    if (!modal) {
        return;
    }
    const close = modal.querySelector('[data-close-modal]');
    if (close) {
        close.addEventListener('click', () => {
            window.location = {$cancelUrl};
        });
    }
    const qrContainer = modal.querySelector('#qr-container');
    const qrValue = modal.getAttribute('data-qr');
    if (qrValue && window.SimpleQR && qrContainer) {
        SimpleQR(qrContainer, qrValue);
    }
});
JS;
}

$inlineScripts[] = <<<JS
document.addEventListener('DOMContentLoaded', () => {
    const toggles = document.querySelectorAll('.notification-group input[type="checkbox"]');
    toggles.forEach((toggle) => {
        toggle.addEventListener('focus', () => toggle.closest('label').classList.add('is-focused'));
        toggle.addEventListener('blur', () => toggle.closest('label').classList.remove('is-focused'));
    });
});
JS;

include __DIR__ . '/templates/partials/dashboard_layout.php';
