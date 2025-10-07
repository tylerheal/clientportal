<?php
if (!defined('APP_BOOTSTRAPPED')) {
    require __DIR__ . '/../../bootstrap.php';
}
$company = get_setting('company_name', 'Service Portal');
$customLogo = get_setting('brand_logo_url', '');
$logo = $customLogo !== '' ? $customLogo : null;
$primary = get_setting('brand_primary_color', '#2563eb');
$font = get_setting('brand_font_family', 'Inter, sans-serif');
$notifications = $notifications ?? [];
$unreadCount = isset($unreadNotifications) ? count($unreadNotifications) : 0;
$sidebar = $sidebar ?? [];
$pageTitle = $pageTitle ?? $company;
$user = $user ?? current_user();
$searchAction = $searchAction ?? url_for('dashboard');
$bodyClass = $bodyClass ?? '';
$activeKey = $activeKey ?? '';
$brandInitials = brand_initials($company);
$currentPath = trim(request_path(), '/');
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
        <aside class="dashboard-sidebar" style="--brand-primary: <?= e($primary); ?>;">
            <div class="sidebar-brand">
                <div class="sidebar-logo">
                    <?php if ($logo): ?>
                        <img src="<?= e($logo); ?>" alt="<?= e($company); ?> logo">
                    <?php else: ?>
                        <span class="sidebar-logo--placeholder" aria-hidden="true"><?= e($brandInitials); ?></span>
                    <?php endif; ?>
                </div>
                <span><?= e($company); ?></span>
            </div>
            <nav class="sidebar-nav">
                <?php foreach ($sidebar as $item): ?>
                    <?php $isActive = ($activeKey && ($item['key'] ?? '') === $activeKey) || (!empty($item['current'])); ?>
                    <a href="<?= e($item['href']); ?>" class="sidebar-link<?= $isActive ? ' sidebar-link--active' : ''; ?>">
                        <span><?= e($item['label']); ?></span>
                    </a>
                <?php endforeach; ?>
            </nav>
            <div class="sidebar-footer">
                <a class="sidebar-footer-link" href="<?= e(url_for('profile')); ?>">Profile &amp; security</a>
                <a class="sidebar-footer-link" href="<?= e(url_for('logout')); ?>">Sign out</a>
            </div>
        </aside>
        <div class="dashboard-main">
            <header class="dashboard-header">
                <div class="header-title">
                    <h1><?= e($pageTitle); ?></h1>
                </div>
                <div class="header-actions">
                    <form action="<?= e($searchAction); ?>" method="get" class="dashboard-search">
                        <input type="search" name="q" value="<?= e($_GET['q'] ?? ''); ?>" placeholder="Search the portal">
                    </form>
                    <details class="notification-details">
                        <summary class="notification-button<?= $unreadCount ? ' notification-button--active' : ''; ?>" title="Notifications" aria-label="Notifications">
                            <span class="icon icon--bell" aria-hidden="true">
                                <svg viewBox="0 0 448 512" role="presentation" focusable="false">
                                    <path d="M224 512a64 64 0 0 0 64-64H160a64 64 0 0 0 64 64Zm215.4-149.1c-20.9-21.5-55.5-53.6-55.5-154.9 0-77.7-54.5-139.5-127.9-155.2V32a32 32 0 0 0-64 0v20.8C118.6 68.5 64 130.3 64 208c0 101.3-34.6 133.4-55.5 154.9A31.9 31.9 0 0 0 0 384c0 17.7 14.3 32 32 32h384c17.7 0 32-14.3 32-32a31.9 31.9 0 0 0-8.6-21.1Z" />
                                </svg>
                            </span>
                            <?php if ($unreadCount): ?>
                                <span class="notification-count"><?= $unreadCount; ?></span>
                            <?php endif; ?>
                        </summary>
                        <div class="notification-panel">
                            <header>
                                <strong>Notifications</strong>
                                <form action="<?= e(url_for('dashboard')); ?>" method="post">
                                    <input type="hidden" name="action" value="mark_notifications">
                                    <input type="hidden" name="redirect" value="<?= e($currentPath); ?>">
                                    <button type="submit">Mark all read</button>
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
                                    <li class="empty">You’re all caught up.</li>
                                <?php endif; ?>
                            </ul>
                        </div>
                    </details>
                    <a class="profile-chip" href="<?= e(url_for('profile')); ?>" title="Profile &amp; security">
                        <div class="profile-avatar">
                            <?php if (!empty($user['avatar_url'])): ?>
                                <img src="<?= e($user['avatar_url']); ?>" alt="<?= e($user['name'] ?? 'Profile'); ?>" onerror="this.remove();">
                            <?php else: ?>
                                <span class="icon icon--user" aria-hidden="true">
                                    <svg viewBox="0 0 448 512" role="presentation" focusable="false">
                                        <path d="M224 256A128 128 0 1 0 224 0a128 128 0 1 0 0 256zm-45.7 48C79.8 304 0 383.8 0 482.3 0 498.7 13.3 512 29.7 512h388.6c16.4 0 29.7-13.3 29.7-29.7C448 383.8 368.2 304 269.7 304z" />
                                    </svg>
                                </span>
                            <?php endif; ?>
                        </div>
                        <div class="profile-meta">
                            <span class="profile-name"><?= e($user['name'] ?? 'Your profile'); ?></span>
                            <span class="profile-email"><?= e($user['email'] ?? ''); ?></span>
                        </div>
                    </a>
                </div>
            </header>
            <main class="dashboard-content">
                <?= $content ?? ''; ?>
            </main>
        </div>
    </div>
</body>
</html>
