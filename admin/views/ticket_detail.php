<?php if (!$selectedTicket): ?>
    <p class="table-empty">Ticket not found.</p>
    <?php return; ?>
<?php endif; ?>
<section class="page-section ticket-detail">
    <?php foreach (['error', 'success'] as $flashType): ?>
        <?php if ($message = flash($flashType)): ?>
            <div class="alert alert--<?= $flashType; ?>"><?= e($message); ?></div>
        <?php endif; ?>
    <?php endforeach; ?>
    <header class="page-header ticket-detail__header">
        <div>
            <a class="page-back" href="<?= e(url_for('admin/tickets')); ?>">← Tickets</a>
            <h2><?= e($selectedTicket['subject']); ?></h2>
            <p class="ticket-detail__meta">
                <span>Ticket #<?= (int) $selectedTicket['id']; ?></span>
                <span><?= e($selectedTicket['client_name']); ?></span>
                <span><?= e(format_relative_time($selectedTicket['updated_at'] ?? $selectedTicket['created_at'])); ?></span>
            </p>
        </div>
        <div class="ticket-detail__header-actions">
            <span class="badge badge--<?= e($selectedTicket['status']); ?>">
                <?= e(ucfirst($selectedTicket['status'])); ?>
            </span>
            <form action="<?= e(url_for('dashboard')); ?>" method="post" class="inline-form" onsubmit="return confirm('Delete this ticket?');">
                <input type="hidden" name="action" value="delete_ticket">
                <input type="hidden" name="ticket_id" value="<?= (int) $selectedTicket['id']; ?>">
                <input type="hidden" name="redirect" value="admin/tickets">
                <button type="submit" class="button button--ghost">Delete</button>
            </form>
        </div>
    </header>
    <div class="ticket-layout">
        <article class="card ticket-thread">
            <div class="ticket-thread__header">
                <div>
                    <span class="ticket-thread__label">Conversation</span>
                    <h3><?= e($selectedTicket['client_name']); ?> &amp; team</h3>
                </div>
            </div>
            <ol class="ticket-messages">
                <?php foreach ($selectedMessages as $message): ?>
                    <?php
                        $isClient = (int) $message['user_id'] === (int) $selectedTicket['user_id'];
                        $messageDate = new DateTimeImmutable($message['created_at']);
                    ?>
                    <li class="ticket-message <?= $isClient ? 'ticket-message--client' : 'ticket-message--staff'; ?>">
                        <div class="ticket-message__meta">
                            <div class="ticket-message__author">
                                <div class="avatar"><span><?= e(brand_initials($message['name'] ?? '', $isClient ? 'CL' : 'ST')); ?></span></div>
                                <div>
                                    <strong><?= e($message['name']); ?></strong>
                                    <span><?= $isClient ? 'Client reply' : 'Team response'; ?></span>
                                </div>
                            </div>
                            <time datetime="<?= e($messageDate->format(DateTimeInterface::ATOM)); ?>">
                                <?= e($messageDate->format('M j, Y · g:i A')); ?>
                            </time>
                        </div>
                        <div class="ticket-message__body"><?= nl2br(e($message['message'])); ?></div>
                    </li>
                <?php endforeach; ?>
                <?php if (empty($selectedMessages)): ?>
                    <li class="ticket-message ticket-message--empty">
                        <div class="ticket-message__body">No replies yet. Start the conversation below.</div>
                    </li>
                <?php endif; ?>
            </ol>
            <div class="ticket-composer" data-ticket-composer>
                <div class="ticket-composer__prompt">
                    <div>
                        <h4>Reply to client</h4>
                        <p>Send an update to <?= e($selectedTicket['client_name']); ?>.</p>
                    </div>
                    <button type="button" class="button button--ghost" data-ticket-composer-toggle>Reply</button>
                </div>
                <form
                    action="<?= e(url_for('dashboard')); ?>"
                    method="post"
                    class="ticket-composer__form"
                    data-ticket-composer-form
                    hidden
                >
                    <input type="hidden" name="action" value="reply_ticket_admin">
                    <input type="hidden" name="ticket_id" value="<?= (int) $selectedTicket['id']; ?>">
                    <input type="hidden" name="redirect" value="admin/tickets/<?= (int) $selectedTicket['id']; ?>">
                    <div class="ticket-composer__fields">
                        <label>
                            <span>Status</span>
                            <select name="status">
                                <option value="open" <?= $selectedTicket['status'] === 'open' ? 'selected' : ''; ?>>Open</option>
                                <option value="pending" <?= $selectedTicket['status'] === 'pending' ? 'selected' : ''; ?>>Pending</option>
                                <option value="resolved" <?= $selectedTicket['status'] === 'resolved' ? 'selected' : ''; ?>>Resolved</option>
                                <option value="closed" <?= $selectedTicket['status'] === 'closed' ? 'selected' : ''; ?>>Closed</option>
                            </select>
                        </label>
                        <label>
                            <span>Message</span>
                            <textarea name="message" rows="5" placeholder="Write your response" required></textarea>
                        </label>
                    </div>
                    <div class="ticket-composer__actions">
                        <button type="submit" class="button button--primary">Send reply</button>
                        <button type="button" class="button button--ghost" data-ticket-composer-cancel>Cancel</button>
                    </div>
                </form>
            </div>
        </article>
        <aside class="card ticket-sidebar" id="ticket-details">
            <div class="ticket-sidebar__section">
                <h3>Details</h3>
                <ul class="ticket-details stacked-list">
                    <li class="stacked-list__item">
                        <span>Status</span>
                        <span class="ticket-details__value">
                            <span class="badge badge--<?= e($selectedTicket['status']); ?>">
                                <?= e(ucfirst($selectedTicket['status'])); ?>
                            </span>
                        </span>
                    </li>
                    <li class="stacked-list__item">
                        <span>Created</span>
                        <span class="ticket-details__value"><?= e(format_datetime($selectedTicket['created_at'])); ?></span>
                    </li>
                    <li class="stacked-list__item">
                        <span>Updated</span>
                        <span class="ticket-details__value"><?= e(format_datetime($selectedTicket['updated_at'])); ?></span>
                    </li>
                </ul>
            </div>
            <div class="ticket-sidebar__section">
                <h3>Client</h3>
                <ul class="ticket-people stacked-list">
                    <li class="stacked-list__item">
                        <div class="avatar"><span><?= e(brand_initials($selectedTicket['client_name'] ?? '', 'CL')); ?></span></div>
                        <div>
                            <strong><?= e($selectedTicket['client_name']); ?></strong>
                            <span>Requester</span>
                        </div>
                    </li>
                </ul>
            </div>
        </aside>
    </div>
</section>
