<?php
if (!defined('APP_BOOTSTRAPPED')) {
    require __DIR__ . '/../../bootstrap.php';
}
$company = get_setting('company_name', 'Service Portal');
$logo = get_setting('brand_logo_url', '');
$primary = get_setting('brand_primary_color', '#2563eb');
$font = get_setting('brand_font_family', 'Inter, sans-serif');
$notifications = $notifications ?? [];
$unreadCount = isset($unreadNotifications) ? count($unreadNotifications) : 0;
$sidebar = $sidebar ?? [];
$pageTitle = $pageTitle ?? $company;
$user = $user ?? current_user();
$searchAction = $searchAction ?? url_for('dashboard');
$bodyClass = $bodyClass ?? '';
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($pageTitle); ?> · <?= e($company); ?></title>
    <link rel="stylesheet" href="<?= e(url_for('static/css/app.css')); ?>">
    <style><?= theme_styles(); ?></style>
</head>
<body class="dashboard-body <?= e($bodyClass); ?>">
    <div class="dashboard-layout">
        <aside class="dashboard-sidebar">
            <div class="sidebar-brand">
                <div class="sidebar-logo" style="--brand-primary: <?= e($primary); ?>;">
                    <?php if ($logo): ?>
                        <img src="<?= e($logo); ?>" alt="<?= e($company); ?> logo">
                    <?php else: ?>
                        <span><?= strtoupper(substr($company, 0, 1)); ?></span>
                    <?php endif; ?>
                </div>
                <span><?= e($company); ?></span>
            </div>
            <nav class="sidebar-nav">
                <?php foreach ($sidebar as $item): ?>
                    <?php $active = ($item['href'] === '#overview' && ($_GET['section'] ?? '#overview') === '#overview') || (!empty($item['current'])); ?>
                    <a href="<?= e($item['href']); ?>" class="sidebar-link<?= $active ? ' sidebar-link--active' : ''; ?>">
                        <span><?= e($item['label']); ?></span>
                    </a>
                <?php endforeach; ?>
            </nav>
            <div class="sidebar-footer">
                <a class="sidebar-footer-link" href="<?= e(url_for('profile')); ?>">Profile & security</a>
                <a class="sidebar-footer-link" href="<?= e(url_for('logout')); ?>">Sign out</a>
            </div>
        </aside>
        <div class="dashboard-main">
            <header class="dashboard-header">
                <form action="<?= e($searchAction); ?>" method="get" class="dashboard-search">
                    <input type="search" name="q" value="<?= e($_GET['q'] ?? ''); ?>" placeholder="Search...">
                </form>
                <div class="dashboard-actions">
                    <details class="notification-details">
                        <summary class="notification-button" title="Notifications">
                            <span class="bell<?= $unreadCount ? ' bell--active' : ''; ?>"></span>
                            <?php if ($unreadCount): ?>
                                <span class="notification-count"><?= $unreadCount; ?></span>
                            <?php endif; ?>
                        </summary>
                        <div class="notification-panel">
                            <header>
                                <strong>Notifications</strong>
                                <form action="<?= e(url_for('dashboard')); ?>" method="post">
                                    <input type="hidden" name="action" value="mark_notifications">
                                    <button type="submit">Clear</button>
                                </form>
                            </header>
                            <ul>
                                <?php foreach ($notifications as $note): ?>
                                    <li class="<?= empty($note['read_at']) ? 'is-unread' : ''; ?>">
                                        <a href="<?= e($note['link'] ?? url_for('dashboard')); ?>">
                                            <span><?= e($note['message']); ?></span>
                                            <time><?= e(format_datetime($note['created_at'])); ?></time>
                                        </a>
                                    </li>
                                <?php endforeach; ?>
                                <?php if (!$notifications): ?>
                                    <li class="empty">No notifications yet.</li>
                                <?php endif; ?>
                            </ul>
                        </div>
                    </details>
                    <div class="dashboard-profile">
                        <img src="<?= e($user['avatar_url'] ?? ''); ?>" alt="Avatar" onerror="this.classList.add('fallback'); this.setAttribute('data-letter', '<?= e(strtoupper(substr($user['name'], 0, 1))); ?>');">
                        <div class="profile-menu">
                            <strong><?= e($user['name']); ?></strong>
                            <span><?= e($user['email']); ?></span>
                        </div>
                    </div>
                </div>
            </header>
            <main class="dashboard-content">
                <?= $content ?? ''; ?>
            </main>
        </div>
    </div>
</body>
</html>
