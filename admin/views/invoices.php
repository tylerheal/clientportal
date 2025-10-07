<section class="page-section">
    <header class="page-header">
        <div>
            <h2>Invoices</h2>
            <p>Monitor billing status across clients.</p>
        </div>
    </header>
    <div class="card">
        <div class="table-wrapper">
            <table class="table">
                <thead>
                    <tr>
                        <th>Invoice</th>
                        <th>Client</th>
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
                            <td><?= e($invoice['client_name']); ?></td>
                            <td><?= e($invoice['service_name']); ?></td>
                            <td><?= e(format_datetime($invoice['due_at'])); ?></td>
                            <td><span class="badge badge--<?= e($invoice['status']); ?>"><?= e(ucfirst($invoice['status'])); ?></span></td>
                            <td><?= format_currency((float) $invoice['total']); ?></td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (!$invoices): ?>
                        <tr><td colspan="6" class="table-empty">No invoices created.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</section>
