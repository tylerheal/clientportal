<?php
if (!defined('APP_BOOTSTRAPPED')) {
    require __DIR__ . '/../../bootstrap.php';
}

$company = get_setting('company_name', 'Service Portal');
$logoSetting = get_setting('brand_logo_url', '');
$logo = $logoSetting !== '' ? asset_url($logoSetting) : null;
$primary = get_setting('brand_primary_color', '#2563eb');
$font = get_setting('brand_font_family', 'Inter, sans-serif');
$notifications = $notifications ?? [];
$unreadCount = isset($unreadNotifications) ? count($unreadNotifications) : 0;
$sidebar = $sidebar ?? [];
$pageTitle = $pageTitle ?? $company;
$user = $user ?? current_user();
$searchAction = $searchAction ?? url_for('dashboard');
$bodyClass = $bodyClass ?? '';
$bodyClassAttr = trim('dashboard-ready ' . $bodyClass);
$activeKey = $activeKey ?? '';
$brandInitials = brand_initials($company);
$currentPath = trim(request_path(), '/');
$pageScripts = $scripts ?? $pageScripts ?? [];
$inlineScripts = $inlineScripts ?? [];
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
<body class="dashboard-body <?= e($bodyClassAttr); ?>">
    <div class="layout portal-layout dashboard-layout">
        <aside class="sidebar portal-sidebar dashboard-sidebar" data-sidebar>
            <div class="sidebar-inner">
                <div class="brand sidebar-brand">
                    <div class="sidebar-logo">
                        <?php if ($logo): ?>
                            <img src="<?= e($logo); ?>" alt="<?= e($company); ?> logo">
                        <?php else: ?>
                            <span class="sidebar-logo--placeholder" aria-hidden="true"><?= e($brandInitials); ?></span>
                        <?php endif; ?>
                    </div>
                    <span><?= e($company); ?></span>
                </div>
                <nav class="nav sidebar-nav" aria-label="Primary">
                    <?php foreach ($sidebar as $item): ?>
                        <?php if (($item['type'] ?? 'link') === 'group'): ?>
                            <div class="group sidebar-group"><?= e($item['label']); ?></div>
                            <?php continue; ?>
                        <?php endif; ?>
                        <?php $isActive = ($activeKey && ($item['key'] ?? '') === $activeKey) || (!empty($item['current'])); ?>
                        <a href="<?= e($item['href']); ?>" class="nav-link sidebar-link<?= $isActive ? ' active sidebar-link--active' : ''; ?>">
                            <span class="sidebar-icon" aria-hidden="true"></span>
                            <span><?= e($item['label']); ?></span>
                        </a>
                    <?php endforeach; ?>
                </nav>
                <div class="sidebar-footer">
                    <a class="sidebar-footer-link" href="<?= e(url_for('profile')); ?>">Profile &amp; security</a>
                    <a class="sidebar-footer-link" href="<?= e(url_for('logout')); ?>">Sign out</a>
                </div>
            </div>
        </aside>
        <div class="main portal-main dashboard-main">
            <header class="topbar portal-topbar dashboard-header">
                <button type="button" class="iconbtn portal-icon-button sidebar-toggle" id="menuBtn" data-mobile-menu-toggle aria-label="Toggle navigation">☰</button>
                <form action="<?= e($searchAction); ?>" method="get" class="search portal-search" role="search">
                    <input type="search" name="q" value="<?= e($_GET['q'] ?? ''); ?>" placeholder="Search…" aria-label="Search the portal">
                </form>
                <button type="button" class="iconbtn portal-icon-button" aria-label="Help">?</button>
                <details class="notification-details" data-notifications>
                    <summary class="iconbtn portal-icon-button notification-button<?= $unreadCount ? ' notification-button--active' : ''; ?>" aria-label="Notifications">
                        <span aria-hidden="true">🔔</span>
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
                <a class="profile-button avatar" href="<?= e(url_for('profile')); ?>" aria-label="Profile &amp; security">
                    <span class="profile-avatar">
                        <?php if (!empty($user['avatar_url'])): ?>
                            <img src="<?= e($user['avatar_url']); ?>" alt="<?= e($user['name'] ?? 'Profile'); ?>" onerror="this.remove();">
                        <?php elseif (!empty($user['name'])): ?>
                            <span aria-hidden="true"><?= e(function_exists('mb_substr') ? mb_substr($user['name'], 0, 1) : substr($user['name'], 0, 1)); ?></span>
                        <?php else: ?>
                            <span aria-hidden="true"><?= e($brandInitials); ?></span>
                        <?php endif; ?>
                    </span>
                </a>
            </header>
            <main class="main-content portal-content dashboard-content">
                <?= $content ?? ''; ?>
            </main>
        </div>
    </div>
    <div class="mobile-menu portal-mobile-menu" id="mobileMenu" data-mobile-menu>
        <div class="mobile-sidebar portal-mobile-sidebar">
            <div class="brand sidebar-brand">
                <div class="sidebar-logo">
                    <?php if ($logo): ?>
                        <img src="<?= e($logo); ?>" alt="<?= e($company); ?> logo">
                    <?php else: ?>
                        <span class="sidebar-logo--placeholder" aria-hidden="true"><?= e($brandInitials); ?></span>
                    <?php endif; ?>
                </div>
                <span><?= e($company); ?></span>
            </div>
            <nav class="mobile-nav" aria-label="Mobile">
                <?php foreach ($sidebar as $item): ?>
                    <?php if (($item['type'] ?? 'link') === 'group') { continue; } ?>
                    <?php $isActive = ($activeKey && ($item['key'] ?? '') === $activeKey) || (!empty($item['current'])); ?>
                    <a href="<?= e($item['href']); ?>" class="<?= $isActive ? 'active' : ''; ?>"><?= e($item['label']); ?></a>
                <?php endforeach; ?>
            </nav>
            <nav class="mobile-nav">
                <a href="<?= e(url_for('profile')); ?>">Profile &amp; security</a>
                <a href="<?= e(url_for('logout')); ?>">Sign out</a>
            </nav>
        </div>
    </div>
    <?php foreach ($pageScripts as $script): ?>
        <?php if (is_array($script)): ?>
            <script<?php if (!empty($script['type'])): ?> type="<?= e($script['type']); ?>"<?php endif; ?><?php if (!empty($script['defer'])): ?> defer<?php endif; ?> src="<?= e($script['src']); ?>"></script>
        <?php else: ?>
            <script src="<?= e($script); ?>"></script>
        <?php endif; ?>
    <?php endforeach; ?>
    <?php foreach ($inlineScripts as $inline): ?>
        <script><?= $inline; ?></script>
    <?php endforeach; ?>
    <script defer src="<?= e(url_for('static/js/app.js')); ?>"></script>
</body>
</html>
