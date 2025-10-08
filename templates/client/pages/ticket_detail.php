<?php if (!$selectedTicket && !$creatingTicket): ?>
    <p class="table-empty">Ticket could not be found.</p>
    <?php return; ?>
<?php endif; ?>

<?php $companyName = get_setting('company_name', 'Support team'); ?>

<section class="page-section ticket-detail">
    <?php foreach (['error', 'success'] as $flashType): ?>
        <?php if ($message = flash($flashType)): ?>
            <div class="alert alert--<?= $flashType; ?>"><?= e($message); ?></div>
        <?php endif; ?>
    <?php endforeach; ?>

    <header class="page-header ticket-detail__header">
        <div>
            <a class="page-back" href="<?= e(url_for('dashboard/tickets')); ?>">← Tickets</a>
            <h2>
                <?= $creatingTicket ? 'New support ticket' : e($selectedTicket['subject']); ?>
            </h2>
            <p>
                <?= $creatingTicket
                    ? 'Let us know what you need help with and we\'ll be in touch.'
                    : 'Ticket #' . (int) $selectedTicket['id'] . ' · ' . e(ucfirst($selectedTicket['status'])); ?>
            </p>
        </div>
        <?php if (!$creatingTicket): ?>
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
        <?php endif; ?>
    </header>

    <div class="ticket-layout">
        <article class="card ticket-thread">
            <?php if ($creatingTicket): ?>
                <form action="<?= e(url_for('dashboard')); ?>" method="post" class="ticket-compose-form">
                    <input type="hidden" name="action" value="create_ticket">
                    <input type="hidden" name="redirect" value="dashboard/tickets">
                    <label>Subject
                        <input type="text" name="subject" placeholder="How can we help?" required>
                    </label>
                    <label>Message
                        <textarea name="message" rows="6" placeholder="Share the details so we can jump straight in." required></textarea>
                    </label>
                    <div class="form-actions">
                        <button type="submit" class="button button--primary">Submit ticket</button>
                    </div>
                </form>
            <?php else: ?>
                <?php $hasMessages = !empty($selectedMessages); ?>
                <ol class="ticket-messages">
                    <?php foreach ($selectedMessages as $message): ?>
                        <?php
                            $isClient = (int) ($message['user_id'] ?? 0) === (int) ($user['id'] ?? 0);
                            $messageDate = new DateTimeImmutable($message['created_at']);
                        ?>
                        <li class="ticket-message <?= $isClient ? 'ticket-message--client' : 'ticket-message--staff'; ?>">
                            <div class="ticket-message__meta">
                                <div class="ticket-message__author">
                                    <div class="avatar"><span><?= e(brand_initials($message['name'] ?? '', 'SP')); ?></span></div>
                                    <div>
                                        <strong><?= e($message['name']); ?></strong>
                                        <span><?= $isClient ? 'You' : 'Support team'; ?></span>
                                    </div>
                                </div>
                                <time datetime="<?= e($messageDate->format(DateTimeInterface::ATOM)); ?>">
                                    <?= e($messageDate->format('M j, Y · g:i A')); ?>
                                </time>
                            </div>
                            <div class="ticket-message__body"><?= nl2br(e($message['message'])); ?></div>
                        </li>
                    <?php endforeach; ?>
                    <?php if (!$hasMessages): ?>
                        <li class="ticket-message ticket-message--empty">
                            <div class="ticket-message__body">No replies yet. Add a message below to start the conversation.</div>
                        </li>
                    <?php endif; ?>
                </ol>
                <form action="<?= e(url_for('dashboard')); ?>" method="post" class="ticket-reply">
                    <input type="hidden" name="action" value="reply_ticket_client">
                    <input type="hidden" name="ticket_id" value="<?= (int) $selectedTicket['id']; ?>">
                    <input type="hidden" name="redirect" value="dashboard/tickets/<?= (int) $selectedTicket['id']; ?>">
                    <label for="ticket-reply">Message</label>
                    <textarea id="ticket-reply" name="message" rows="5" placeholder="Write your response" required></textarea>
                    <div class="form-actions">
                        <button type="submit" class="button button--primary">Send message</button>
                    </div>
                </form>
            <?php endif; ?>
        </article>

        <aside class="card ticket-sidebar">
            <div class="ticket-sidebar__section">
                <h3>Details</h3>
                <ul class="ticket-details">
                    <li>
                        <span>Status</span>
                        <span class="ticket-details__value">
                            <span class="badge badge--<?= e($creatingTicket ? 'open' : $selectedTicket['status']); ?>">
                                <?= $creatingTicket ? 'Draft' : e(ucfirst($selectedTicket['status'])); ?>
                            </span>
                        </span>
                    </li>
                    <li>
                        <span>Created</span>
                        <span class="ticket-details__value">
                            <?= $creatingTicket ? 'Will be recorded on submit' : e(format_datetime($selectedTicket['created_at'])); ?>
                        </span>
                    </li>
                    <li>
                        <span>Updated</span>
                        <span class="ticket-details__value">
                            <?= $creatingTicket ? '—' : e(format_datetime($selectedTicket['updated_at'])); ?>
                        </span>
                    </li>
                </ul>
            </div>
            <div class="ticket-sidebar__section">
                <h3>People</h3>
                <ul class="ticket-people">
                    <li>
                        <div class="avatar"><span><?= e(brand_initials($user['name'] ?? $user['email'] ?? '', 'ME')); ?></span></div>
                        <div>
                            <strong><?= e($user['name'] ?: $user['email']); ?></strong>
                            <span>Requester</span>
                        </div>
                    </li>
                    <li>
                        <div class="avatar"><span><?= e(brand_initials($companyName, 'SP')); ?></span></div>
                        <div>
                            <strong><?= e($companyName); ?></strong>
                            <span>Support</span>
                        </div>
                    </li>
                </ul>
            </div>
        </aside>
    </div>
</section>
