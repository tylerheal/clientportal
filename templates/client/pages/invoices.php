<?php
$paypalClientId = trim(get_setting('paypal_client_id', ''));
$hasPaypal = $paypalClientId !== '';
$currency = currency_code();
if ($hasPaypal) {
    $pageScripts = $pageScripts ?? [];
    $paypalScript = sprintf(
        'https://www.paypal.com/sdk/js?client-id=%s&currency=%s&components=buttons&intent=capture',
        rawurlencode($paypalClientId),
        rawurlencode($currency)
    );
    $pageScripts[] = ['src' => $paypalScript];
}
?>
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
                        <th class="text-right">Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($invoices as $invoice): ?>
                        <tr data-invoice-row="<?= (int) $invoice['id']; ?>">
                            <td>#<?= (int) $invoice['id']; ?></td>
                            <td><?= e($invoice['service_name']); ?></td>
                            <td><?= e(format_datetime($invoice['due_at'])); ?></td>
                            <td><span class="badge badge--<?= e($invoice['status']); ?>" data-invoice-status="<?= (int) $invoice['id']; ?>"><?= e(ucfirst($invoice['status'])); ?></span></td>
                            <td><?= format_currency((float) $invoice['total']); ?></td>
                            <td class="text-right">
                                <?php if ($invoice['status'] === 'paid'): ?>
                                    <span class="badge badge--paid">Paid</span>
                                <?php elseif ($hasPaypal && ($invoice['payment_method'] ?? 'paypal') === 'paypal'): ?>
                                    <button
                                        type="button"
                                        class="button button--primary"
                                        data-invoice-pay
                                        data-invoice-id="<?= (int) $invoice['id']; ?>"
                                        data-invoice-amount="<?= number_format((float) $invoice['total'], 2, '.', ''); ?>"
                                        data-invoice-currency="<?= e($currency); ?>"
                                        data-invoice-service="<?= e($invoice['service_name']); ?>"
                                    >Pay now</button>
                                <?php else: ?>
                                    <span class="badge badge--muted">Awaiting manual payment</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (!$invoices): ?>
                        <tr><td colspan="6" class="table-empty">No invoices available.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</section>
<div class="modal-overlay" data-invoice-modal hidden data-action="<?= e(url_for('dashboard')); ?>">
    <div class="modal-card">
        <button class="modal-close" type="button" data-invoice-close>&times;</button>
        <h3>Pay invoice <span data-invoice-number></span></h3>
        <p>We’ll redirect you after PayPal confirms the payment.</p>
        <div class="invoice-summary subtle" data-invoice-summary></div>
        <div id="paypal-button-container" data-paypal-container></div>
        <p class="subtle small" data-invoice-feedback></p>
    </div>
</div>
