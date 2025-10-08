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
$searchScopes = $searchScopes ?? [
    ['label' => 'Everything', 'value' => ''],
    ['label' => 'Services', 'value' => 'services'],
    ['label' => 'Orders', 'value' => 'orders'],
    ['label' => 'Tickets', 'value' => 'tickets'],
    ['label' => 'Messages', 'value' => 'messages'],
    ['label' => 'Settings', 'value' => 'settings'],
];
$activeScope = $_GET['scope'] ?? '';
$bodyClass = $bodyClass ?? '';
$bodyClassAttr = trim('dashboard-ready ' . $bodyClass);
$activeKey = $activeKey ?? '';
$brandInitials = brand_initials($company);
$currentPath = trim(request_path(), '/');
$pageScripts = $scripts ?? $pageScripts ?? [];
$inlineScripts = $inlineScripts ?? [];
$notificationPreview = array_slice($notifications, 0, 3);
$notificationsLink = is_admin($user ?? null)
    ? url_for('admin/notifications')
    : url_for('dashboard/notifications');
$iconMap = [
    'overview' => '🏠',
    'orders' => '📦',
    'tickets' => '🎫',
    'clients' => '🧑‍🤝‍🧑',
    'services' => '🛠️',
    'forms' => '📝',
    'invoices' => '🧾',
    'payments' => '💳',
    'automations' => '🤖',
    'administrators' => '🛡️',
    'settings' => '⚙️',
    'dashboard' => '📊',
];
$brandHasLogo = (bool) $logo;
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
    <div class="layout" data-layout>
        <aside class="sidebar" data-sidebar>
            <div class="brand<?= $brandHasLogo ? ' brand--has-logo' : ''; ?>">
                <div class="sidebar-logo">
                    <?php if ($logo): ?>
                        <img src="<?= e($logo); ?>" alt="<?= e($company); ?> logo">
                    <?php else: ?>
                        <span class="sidebar-logo--placeholder" aria-hidden="true"><?= e($brandInitials); ?></span>
                    <?php endif; ?>
                </div>
                <?php if ($brandHasLogo): ?>
                    <span class="sr-only"><?= e($company); ?></span>
                <?php else: ?>
                    <span class="brand-name"><?= e($company); ?></span>
                <?php endif; ?>
            </div>
            <nav class="nav" aria-label="Primary navigation">
                <?php foreach ($sidebar as $item): ?>
                    <?php if (($item['type'] ?? 'link') === 'group'): ?>
                        <div class="group"><?= e($item['label']); ?></div>
                        <?php continue; ?>
                    <?php endif; ?>
                    <?php $isActive = ($activeKey && ($item['key'] ?? '') === $activeKey) || (!empty($item['current'])); ?>
                    <?php $icon = $item['icon'] ?? ($iconMap[$item['key'] ?? ''] ?? '⬤'); ?>
                    <a href="<?= e($item['href']); ?>" class="<?= $isActive ? 'active' : ''; ?>">
                        <span class="sidebar-icon" aria-hidden="true"><?= e($icon); ?></span>
                        <span><?= e($item['label']); ?></span>
                    </a>
                <?php endforeach; ?>
            </nav>
            <div class="sidebar-footer">
                <a href="<?= e(url_for('profile')); ?>">Profile &amp; security</a>
                <a href="<?= e(url_for('logout')); ?>">Sign out</a>
            </div>
        </aside>
        <div class="main">
            <header class="topbar">
                <button type="button" class="iconbtn" id="menuBtn" data-mobile-menu-toggle aria-label="Toggle navigation">☰</button>
                <div class="topbar-actions">
                    <button type="button" class="iconbtn" data-search-open aria-haspopup="dialog" aria-controls="searchModal" aria-label="Search">
                        <span aria-hidden="true">🔍</span>
                    </button>
                    <button type="button" class="iconbtn" aria-label="Help">?</button>
                    <details class="notification-details" data-notifications>
                        <summary class="iconbtn notification-button<?= $unreadCount ? ' notification-button--active' : ''; ?>" aria-label="Notifications">
                            <span aria-hidden="true">🔔</span>
                            <?php if ($unreadCount): ?>
                                <span class="notification-count"><?= $unreadCount; ?></span>
                            <?php endif; ?>
                        </summary>
                        <div class="notification-panel">
                            <header class="notification-panel__header">
                                <div class="notification-panel__title">
                                    <strong>Notifications</strong>
                                    <p>Latest activity in your workspace.</p>
                                </div>
                                <?php if ($notifications): ?>
                                    <form action="<?= e(url_for('dashboard')); ?>" method="post" class="notification-panel__mark">
                                        <input type="hidden" name="action" value="mark_notifications">
                                        <input type="hidden" name="redirect" value="<?= e($currentPath); ?>">
                                        <button type="submit" class="notification-panel__mark-btn">Mark all as read</button>
                                    </form>
                                <?php endif; ?>
                            </header>
                            <ul class="notification-panel__list">
                                <?php foreach ($notificationPreview as $note): ?>
                                    <li class="notification-panel__item<?= empty($note['read_at']) ? ' is-unread' : ''; ?>">
                                        <a class="notification-panel__link" href="<?= e($note['link'] ?? $notificationsLink); ?>">
                                            <span class="notification-panel__message"><?= e($note['message']); ?></span>
                                            <time class="notification-panel__time" datetime="<?= e($note['created_at']); ?>"><?= e(format_relative_time($note['created_at'])); ?></time>
                                        </a>
                                    </li>
                                <?php endforeach; ?>
                                <?php if (!$notificationPreview): ?>
                                    <li class="notification-panel__empty">You’re all caught up.</li>
                                <?php endif; ?>
                            </ul>
                            <footer class="notification-panel__footer">
                                <a class="notification-panel__show-all" href="<?= e($notificationsLink); ?>">Show all<span aria-hidden="true"> →</span></a>
                            </footer>
                        </div>
                    </details>
                    <a class="avatar" href="<?= e(url_for('profile')); ?>" aria-label="Profile &amp; security">
                        <?php if (!empty($user['avatar_url'])): ?>
                            <img src="<?= e($user['avatar_url']); ?>" alt="<?= e($user['name'] ?? 'Profile'); ?>" onerror="this.remove();">
                        <?php elseif (!empty($user['name'])): ?>
                            <span aria-hidden="true"><?= e(function_exists('mb_substr') ? mb_substr($user['name'], 0, 1) : substr($user['name'], 0, 1)); ?></span>
                        <?php else: ?>
                            <span aria-hidden="true"><?= e($brandInitials); ?></span>
                        <?php endif; ?>
                    </a>
                </div>
            </header>
            <main>
                <?= $content ?? ''; ?>
            </main>
        </div>
    </div>
    <div class="mobile-menu" id="mobileMenu" data-mobile-menu>
        <div class="mobile-sidebar">
            <div class="brand<?= $brandHasLogo ? ' brand--has-logo' : ''; ?>">
                <div class="sidebar-logo">
                    <?php if ($logo): ?>
                        <img src="<?= e($logo); ?>" alt="<?= e($company); ?> logo">
                    <?php else: ?>
                        <span class="sidebar-logo--placeholder" aria-hidden="true"><?= e($brandInitials); ?></span>
                    <?php endif; ?>
                </div>
                <?php if ($brandHasLogo): ?>
                    <span class="sr-only"><?= e($company); ?></span>
                <?php else: ?>
                    <span class="brand-name"><?= e($company); ?></span>
                <?php endif; ?>
            </div>
            <nav class="mobile-nav" aria-label="Mobile navigation">
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
    <div class="search-modal" id="searchModal" data-search-modal aria-hidden="true">
        <div class="search-modal__backdrop" data-search-dismiss></div>
        <div class="search-modal__dialog" role="dialog" aria-modal="true" aria-labelledby="searchModalTitle">
            <header class="search-modal__header">
                <div>
                    <h2 id="searchModalTitle">Search</h2>
                    <p class="search-modal__hint">Search for pages, services, orders, tickets, or messages.</p>
                </div>
                <button type="button" class="iconbtn" data-search-dismiss aria-label="Close search">
                    <span aria-hidden="true">✕</span>
                </button>
            </header>
            <form action="<?= e($searchAction); ?>" method="get" class="search-modal__form" role="search">
                <div class="search-modal__field">
                    <span class="search-modal__icon" aria-hidden="true">🔍</span>
                    <input type="search" name="q" value="<?= e($_GET['q'] ?? ''); ?>" placeholder="Search…" aria-label="Search the portal" data-search-input required>
                    <input type="hidden" name="scope" value="<?= e($activeScope); ?>" data-search-scope>
                    <button type="submit" class="btn primary">Search</button>
                </div>
                <div class="search-modal__chips" role="list">
                    <?php foreach ($searchScopes as $scope): ?>
                        <?php $isCurrent = ($scope['value'] ?? '') === $activeScope; ?>
                        <button type="button" class="search-chip<?= $isCurrent ? ' search-chip--active' : ''; ?>" data-search-chip data-value="<?= e($scope['value']); ?>" role="listitem">
                            <?= e($scope['label']); ?>
                        </button>
                    <?php endforeach; ?>
                </div>
            </form>
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
