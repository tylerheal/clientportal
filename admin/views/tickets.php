<section class="page-section">
    <?php foreach (['error', 'success'] as $flashType): ?>
        <?php if ($message = flash($flashType)): ?>
            <div class="alert alert--<?= $flashType; ?>"><?= e($message); ?></div>
        <?php endif; ?>
    <?php endforeach; ?>
    <header class="page-header">
        <div>
            <h2>Support tickets</h2>
            <p>Coordinate responses and bulk-manage status.</p>
        </div>
    </header>
    <div class="card">
        <form action="<?= e(url_for('dashboard')); ?>" method="post">
            <input type="hidden" name="action" value="bulk_ticket_action">
            <input type="hidden" name="redirect" value="admin/tickets">
            <div class="table-toolbar">
                <div class="toolbar-group">
                    <button type="button" class="button button--ghost">Filter</button>
                </div>
                <div class="toolbar-group">
                    <select name="mode">
                        <option value="close">Close selected</option>
                        <option value="delete">Delete selected</option>
                    </select>
                    <button type="submit" class="button button--ghost">Apply</button>
                </div>
            </div>
            <div class="table-wrapper">
                <table class="table">
                    <thead>
                        <tr>
                            <th><input type="checkbox" onclick="var master=this;document.querySelectorAll('.ticket-checkbox').forEach(function(cb){cb.checked=master.checked;});"></th>
                            <th>Ticket</th>
                            <th>Status</th>
                            <th>Updated</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($tickets as $ticket): ?>
                            <tr>
                                <td><input class="ticket-checkbox" type="checkbox" name="ticket_ids[]" value="<?= (int) $ticket['id']; ?>"></td>
                                <td>
                                    <strong><?= e($ticket['subject']); ?></strong>
                                    <p class="table-subline">#<?= (int) $ticket['id']; ?> · <?= e($ticket['client_name']); ?></p>
                                </td>
                                <td><span class="badge badge--<?= e($ticket['status']); ?>"><?= e(ucfirst($ticket['status'])); ?></span></td>
                                <td><?= e(format_datetime($ticket['updated_at'])); ?></td>
                                <td class="table-actions">
                                    <a class="button button--ghost" href="<?= e(url_for('admin/tickets/' . $ticket['id'])); ?>">Open</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if (!$tickets): ?>
                            <tr><td colspan="6" class="table-empty">No tickets found.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </form>
    </div>
</section>
