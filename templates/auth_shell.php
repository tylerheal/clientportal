<?php
if (!defined('APP_BOOTSTRAPPED')) {
    require __DIR__ . '/../bootstrap.php';
}
$company = get_setting('company_name', 'Service Portal');
$logoSetting = get_setting('brand_logo_url', '');
$logo = $logoSetting !== '' ? asset_url($logoSetting) : '';
$primary = get_setting('brand_primary_color', '#2a6dff');
$authHasLogo = $logo !== '';
$view = $authView ?? 'login';
$baseAction = [
    'login' => url_for('login'),
    'signup' => url_for('signup'),
    'verify-otp' => url_for('verify-otp'),
][$view] ?? url_for('login');
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($pageTitle ?? $company); ?></title>
    <link rel="stylesheet" href="<?= e(url_for('static/css/app.css')); ?>">
    <style><?= theme_styles(); ?></style>
</head>
<body class="auth-body">
    <div class="auth-wrapper">
        <header class="auth-brand<?= $authHasLogo ? ' auth-brand--has-logo' : ''; ?>">
            <div class="auth-logo" style="--brand-primary: <?= e($primary); ?>;">
                <?php if ($logo !== ''): ?>
                    <img src="<?= e($logo); ?>" alt="<?= e($company); ?> logo">
                <?php else: ?>
                    <span><?= e(brand_initials($company)); ?></span>
                <?php endif; ?>
            </div>
            <h1 class="<?= $authHasLogo ? 'sr-only' : ''; ?>"><?= e($company); ?></h1>
        </header>
        <main class="auth-card">
            <h2 class="auth-title"><?= e($pageTitle ?? ''); ?></h2>
            <?php if ($view === 'login'): ?>
                <p class="auth-subtitle">Welcome back 👋</p>
            <?php elseif ($view === 'signup'): ?>
                <p class="auth-subtitle">Create your account</p>
            <?php elseif ($view === 'verify-otp'): ?>
                <p class="auth-subtitle">Enter your 6-digit code</p>
            <?php endif; ?>
            <?php foreach (['error', 'success'] as $flashType): ?>
                <?php if ($message = flash($flashType)): ?>
                    <div class="auth-alert auth-alert--<?= $flashType; ?>"><?= e($message); ?></div>
                <?php endif; ?>
            <?php endforeach; ?>
            <form action="<?= e($baseAction); ?>" method="post" class="auth-form">
                <?php if ($view === 'login'): ?>
                    <label>Email
                        <input type="email" name="email" value="<?= e($_POST['email'] ?? ''); ?>" required autocomplete="email">
                    </label>
                    <label>Password
                        <input type="password" name="password" required autocomplete="current-password">
                    </label>
                    <button type="submit">Sign in</button>
                    <p class="auth-meta">Don't have an account? <a href="<?= e(url_for('signup')); ?>">Sign up</a></p>
                <?php elseif ($view === 'signup'): ?>
                    <div class="auth-grid">
                        <label>First name
                            <input type="text" name="first_name" value="<?= e($_POST['first_name'] ?? ''); ?>" required autocomplete="given-name">
                        </label>
                        <label>Last name
                            <input type="text" name="last_name" value="<?= e($_POST['last_name'] ?? ''); ?>" required autocomplete="family-name">
                        </label>
                    </div>
                    <label>Email
                        <input type="email" name="email" value="<?= e($_POST['email'] ?? ''); ?>" required autocomplete="email">
                    </label>
                    <div class="auth-grid">
                        <label>Company
                            <input type="text" name="company" value="<?= e($_POST['company'] ?? ''); ?>" autocomplete="organization">
                        </label>
                        <label>Phone
                            <input type="tel" name="phone" value="<?= e($_POST['phone'] ?? ''); ?>" autocomplete="tel">
                        </label>
                    </div>
                    <div class="auth-grid">
                        <label>Password
                            <input type="password" name="password" minlength="8" required autocomplete="new-password">
                        </label>
                        <label>Confirm password
                            <input type="password" name="password_confirm" minlength="8" required autocomplete="new-password">
                        </label>
                    </div>
                    <button type="submit">Sign up</button>
                    <p class="auth-meta">Already have an account? <a href="<?= e(url_for('login')); ?>">Sign in</a></p>
                <?php elseif ($view === 'verify-otp'): ?>
                    <label>Authenticator code
                        <input type="text" name="token" inputmode="numeric" pattern="[0-9]*" minlength="6" maxlength="6" required autocomplete="one-time-code">
                    </label>
                    <label class="auth-recovery">Or recovery code
                        <input type="text" name="recovery" autocomplete="one-time-code">
                    </label>
                    <button type="submit">Verify and continue</button>
                    <p class="auth-meta"><a href="<?= e(url_for('login')); ?>">Back to login</a></p>
                <?php endif; ?>
            </form>
        </main>
        <footer class="auth-footer">
            <p>Powered by <?= e($company); ?></p>
        </footer>
    </div>
</body>
</html>
