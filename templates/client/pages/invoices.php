<section class="page-section">
    <?php foreach (['error', 'success'] as $flashType): ?>
        <?php if ($message = flash($flashType)): ?>
            <div class="alert alert--<?= $flashType; ?>"><?= e($message); ?></div>
        <?php endif; ?>
    <?php endforeach; ?>
    <header class="page-header">
        <div>
            <h2>Invoices</h2>
            <p>Download and review invoice history.</p>
        </div>
    </header>
    <div class="card">
        <div class="table-toolbar">
            <div class="toolbar-group">
                <label>Status
                    <select>
                        <option>All invoices</option>
                        <option>Paid</option>
                        <option>Pending</option>
                        <option>Overdue</option>
                    </select>
                </label>
            </div>
        </div>
        <div class="table-wrapper">
            <table class="table">
                <thead>
                    <tr>
                        <th>Invoice</th>
                        <th>Service</th>
                        <th>Due</th>
                        <th>Status</th>
                        <th>Total</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($invoices as $invoice): ?>
                        <tr>
                            <td>#<?= (int) $invoice['id']; ?></td>
                            <td><?= e($invoice['service_name']); ?></td>
                            <td><?= e(format_datetime($invoice['due_at'])); ?></td>
                            <td><span class="badge badge--<?= e($invoice['status']); ?>"><?= e(ucfirst($invoice['status'])); ?></span></td>
                            <td><?= format_currency((float) $invoice['total']); ?></td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (!$invoices): ?>
                        <tr><td colspan="5" class="table-empty">No invoices available.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</section>
