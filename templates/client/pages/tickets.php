<section class="page-section ticket-page">
    <?php foreach (['error', 'success'] as $flashType): ?>
        <?php if ($message = flash($flashType)): ?>
            <div class="alert alert--<?= $flashType; ?>"><?= e($message); ?></div>
        <?php endif; ?>
    <?php endforeach; ?>

    <header class="page-header page-header--split">
        <div>
            <h2>Support tickets</h2>
            <p>Track existing conversations or raise something new with our team.</p>
        </div>
        <div class="page-actions">
            <a class="button button--primary" href="<?= e(url_for('dashboard/tickets/new')); ?>">
                <span class="button__icon" aria-hidden="true">＋</span>
                New ticket
            </a>
        </div>
    </header>

    <div class="card ticket-list">
        <div class="table-wrapper">
            <table class="table table--tickets">
                <thead>
                    <tr>
                        <th scope="col">Subject</th>
                        <th scope="col">Updated</th>
                        <th scope="col">Status</th>
                        <th scope="col" class="sr-only">Open ticket</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($tickets as $ticket): ?>
                        <tr>
                            <td>
                                <a class="ticket-list__link" href="<?= e(url_for('dashboard/tickets/' . $ticket['id'])); ?>">
                                    <span class="ticket-list__subject"><?= e($ticket['subject']); ?></span>
                                    <span class="ticket-list__meta">Ticket #<?= (int) $ticket['id']; ?></span>
                                </a>
                            </td>
                            <td><?= e(format_datetime($ticket['updated_at'])); ?></td>
                            <td>
                                <span class="badge badge--<?= e($ticket['status']); ?>">
                                    <?= e(ucfirst($ticket['status'])); ?>
                                </span>
                            </td>
                            <td class="text-right">
                                <a class="button button--ghost" href="<?= e(url_for('dashboard/tickets/' . $ticket['id'])); ?>">Open</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (!$tickets): ?>
                        <tr>
                            <td colspan="4" class="table-empty">No tickets yet. Create your first support request to get started.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</section>
