<?php if (!$selectedTicket): ?>
    <p class="table-empty">Ticket could not be found.</p>
    <?php return; ?>
<?php endif; ?>
<section class="page-section">
    <?php foreach (['error', 'success'] as $flashType): ?>
        <?php if ($message = flash($flashType)): ?>
            <div class="alert alert--<?= $flashType; ?>"><?= e($message); ?></div>
        <?php endif; ?>
    <?php endforeach; ?>
    <header class="page-header">
        <div>
            <a class="button button--ghost" href="<?= e(url_for('dashboard/tickets')); ?>">← Back to tickets</a>
            <h2><?= e($selectedTicket['subject']); ?></h2>
            <p>Ticket #<?= (int) $selectedTicket['id']; ?> · <?= e(ucfirst($selectedTicket['status'])); ?></p>
        </div>
        <div class="page-actions">
            <?php if ($selectedTicket['status'] !== 'closed'): ?>
                <form action="<?= e(url_for('dashboard')); ?>" method="post" class="inline-form">
                    <input type="hidden" name="action" value="close_ticket">
                    <input type="hidden" name="ticket_id" value="<?= (int) $selectedTicket['id']; ?>">
                    <input type="hidden" name="redirect" value="dashboard/tickets/<?= (int) $selectedTicket['id']; ?>">
                    <button type="submit" class="button button--ghost">Close ticket</button>
                </form>
            <?php endif; ?>
            <form action="<?= e(url_for('dashboard')); ?>" method="post" class="inline-form" onsubmit="return confirm('Delete this ticket?');">
                <input type="hidden" name="action" value="delete_ticket">
                <input type="hidden" name="ticket_id" value="<?= (int) $selectedTicket['id']; ?>">
                <input type="hidden" name="redirect" value="dashboard/tickets">
                <button type="submit" class="button button--ghost">Delete</button>
            </form>
        </div>
    </header>
    <div class="ticket-layout">
        <article class="card ticket-thread">
            <ol class="thread-list">
                <?php foreach ($selectedMessages as $message): ?>
                    <li>
                        <header>
                            <strong><?= e($message['name']); ?></strong>
                            <time><?= e(format_datetime($message['created_at'])); ?></time>
                        </header>
                        <p><?= nl2br(e($message['message'])); ?></p>
                    </li>
                <?php endforeach; ?>
            </ol>
            <form action="<?= e(url_for('dashboard')); ?>" method="post" class="form-grid">
                <input type="hidden" name="action" value="reply_ticket_client">
                <input type="hidden" name="ticket_id" value="<?= (int) $selectedTicket['id']; ?>">
                <input type="hidden" name="redirect" value="dashboard/tickets/<?= (int) $selectedTicket['id']; ?>">
                <label>Reply
                    <textarea name="message" rows="4" required></textarea>
                </label>
                <div class="form-actions">
                    <button type="submit" class="button button--primary">Send reply</button>
                </div>
            </form>
        </article>
        <aside class="card ticket-sidebar">
            <h3>Details</h3>
            <ul>
                <li><span>Status</span><strong><span class="badge badge--<?= e($selectedTicket['status']); ?>"><?= e(ucfirst($selectedTicket['status'])); ?></span></strong></li>
                <li><span>Created</span><strong><?= e(format_datetime($selectedTicket['created_at'])); ?></strong></li>
                <li><span>Updated</span><strong><?= e(format_datetime($selectedTicket['updated_at'])); ?></strong></li>
            </ul>
        </aside>
    </div>
</section>
