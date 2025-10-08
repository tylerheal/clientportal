<?php
$notificationCount = count($notifications);
$hasNotifications = $notificationCount > 0;
$notificationsRedirect = 'dashboard/notifications';
?>
<section class="page-section">
    <?php foreach (['error', 'success'] as $flashType): ?>
        <?php if ($message = flash($flashType)): ?>
            <div class="alert alert--<?= $flashType; ?>"><?= e($message); ?></div>
        <?php endif; ?>
    <?php endforeach; ?>

    <article class="card">
        <header class="card-header notifications-card__actions">
            <div>
                <h2>Notifications</h2>
                <p>Catch up on recent updates and alerts.</p>
            </div>
            <?php if ($hasNotifications): ?>
                <form action="<?= e(url_for('dashboard')); ?>" method="post">
                    <input type="hidden" name="action" value="mark_notifications">
                    <input type="hidden" name="redirect" value="<?= e($notificationsRedirect); ?>">
                    <button type="submit" class="button button--ghost">Mark all as read</button>
                </form>
            <?php endif; ?>
        </header>

        <?php if ($hasNotifications): ?>
            <ul class="notification-feed">
                <?php foreach ($notifications as $note): ?>
                    <li class="notification-feed__item<?= empty($note['read_at']) ? ' is-unread' : ''; ?>">
                        <a class="notification-feed__link" href="<?= e($note['link'] ?? url_for('dashboard')); ?>">
                            <span class="notification-feed__message"><?= e($note['message']); ?></span>
                            <span class="notification-feed__meta">
                                <time datetime="<?= e($note['created_at']); ?>"><?= e(format_relative_time($note['created_at'])); ?></time>
                                <span class="notification-feed__cta">Open<span aria-hidden="true"> →</span></span>
                            </span>
                        </a>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php else: ?>
            <div class="notification-empty">
                <strong>You’re all caught up.</strong>
                <p>We’ll let you know as soon as something needs your attention.</p>
            </div>
        <?php endif; ?>
    </article>
</section>
