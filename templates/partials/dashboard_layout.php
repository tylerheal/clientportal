<?php
if (!defined('APP_BOOTSTRAPPED')) {
    exit('No direct script access.');
}

$sidebar = $sidebar ?? [];
$notifications = $notifications ?? [];
$title = $title ?? 'Dashboard';
$logo = $logo ?? null;
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= htmlspecialchars($title, ENT_QUOTES, 'UTF-8'); ?></title>
    <link rel="stylesheet" href="/static/css/app.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js" defer></script>
    <script defer src="/static/js/app.js"></script>
</head>
<body>
<div class="layout">
    <aside class="sidebar" aria-label="Primary">
        <div class="brand">
            <?php if ($logo): ?>
                <img src="<?= htmlspecialchars($logo, ENT_QUOTES, 'UTF-8'); ?>" alt="" loading="lazy">
            <?php endif; ?>
            <span><?= htmlspecialchars($title, ENT_QUOTES, 'UTF-8'); ?></span>
        </div>
        <nav class="nav">
            <?php foreach ($sidebar as $group => $items): ?>
                <?php if ($group !== '' && !is_numeric($group)): ?>
                    <div class="group"><?= htmlspecialchars($group, ENT_QUOTES, 'UTF-8'); ?></div>
                <?php endif; ?>
                <?php foreach ($items as $item): ?>
                    <?php
                    $label = $item['label'] ?? '';
                    $href = $item['href'] ?? '#';
                    $active = !empty($item['active']);
                    ?>
                    <a href="<?= htmlspecialchars($href, ENT_QUOTES, 'UTF-8'); ?>" class="<?= $active ? 'active' : ''; ?>">
                        <?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8'); ?>
                    </a>
                <?php endforeach; ?>
            <?php endforeach; ?>
        </nav>
    </aside>

    <div class="shell">
        <header class="topbar">
            <button class="iconbtn" id="menuBtn" type="button" aria-label="Toggle menu">☰</button>
            <form class="search" method="get" action="/search">
                <input type="search" name="q" placeholder="Search…" value="<?= htmlspecialchars($_GET['q'] ?? '', ENT_QUOTES, 'UTF-8'); ?>">
            </form>
            <button class="iconbtn" type="button" data-help>?</button>
            <button class="iconbtn" type="button" data-notification-toggle>🔔
                <?php if (!empty($notifications)): ?>
                    <span class="sr-only">You have <?= count($notifications); ?> notifications</span>
                <?php endif; ?>
            </button>
            <span class="avatar" aria-hidden="true"></span>
        </header>

        <main>
            <?php if (!empty($notifications)): ?>
                <section class="card notifications" data-notification-panel hidden>
                    <div class="h1">Notifications</div>
                    <ul>
                        <?php foreach ($notifications as $note): ?>
                            <li>
                                <strong><?= htmlspecialchars($note['title'] ?? '', ENT_QUOTES, 'UTF-8'); ?></strong>
                                <p class="subtle"><?= htmlspecialchars($note['message'] ?? '', ENT_QUOTES, 'UTF-8'); ?></p>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </section>
            <?php endif; ?>

            <?= $content ?? ''; ?>
        </main>
    </div>
</div>

<div class="mobile-menu" id="mobileMenu" hidden>
    <div class="mobile-sidebar">
        <div class="brand">
            <?php if ($logo): ?>
                <img src="<?= htmlspecialchars($logo, ENT_QUOTES, 'UTF-8'); ?>" alt="" loading="lazy">
            <?php endif; ?>
            <span><?= htmlspecialchars($title, ENT_QUOTES, 'UTF-8'); ?></span>
        </div>
        <?php foreach ($sidebar as $group => $items): ?>
            <?php if ($group !== '' && !is_numeric($group)): ?>
                <div class="group"><?= htmlspecialchars($group, ENT_QUOTES, 'UTF-8'); ?></div>
            <?php endif; ?>
            <?php foreach ($items as $item): ?>
                <a href="<?= htmlspecialchars($item['href'] ?? '#', ENT_QUOTES, 'UTF-8'); ?>">
                    <?= htmlspecialchars($item['label'] ?? '', ENT_QUOTES, 'UTF-8'); ?>
                </a>
            <?php endforeach; ?>
        <?php endforeach; ?>
    </div>
</div>
</body>
</html>
