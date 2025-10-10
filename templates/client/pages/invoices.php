<?php
$availability = payments_available();
$currency = currency_code();
$pageScripts = $pageScripts ?? [];
if (!empty($availability['paypal'])) {
    $paypalScript = sprintf(
        'https://www.paypal.com/sdk/js?client-id=%s&currency=%s&components=buttons&intent=capture&vault=true',
        rawurlencode(get_setting('paypal_client_id', '')),
        rawurlencode($currency)
    );
    $pageScripts[] = ['src' => $paypalScript];
}
if (!empty($availability['stripe'])) {
    $pageScripts[] = ['src' => 'https://js.stripe.com/v3/'];
}
$paymentReady = !empty($availability['paypal']) || !empty($availability['stripe']);
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
                            <td><?= e(format_invoice_number($invoice + ['client_name' => $user['name'] ?? ''])); ?></td>
                            <td><?= e($invoice['service_name']); ?></td>
                            <td><?= e(format_datetime($invoice['due_at'])); ?></td>
                            <td><span class="badge badge--<?= e($invoice['status']); ?>" data-invoice-status="<?= (int) $invoice['id']; ?>"><?= e(ucfirst($invoice['status'])); ?></span></td>
                            <td><?= format_currency((float) $invoice['total']); ?></td>
                            <td class="text-right">
                                <?php
                                    $method = strtolower($invoice['payment_method'] ?? 'manual');
                                    $canStripe = !empty($availability['stripe']);
                                    $canGooglePay = $canStripe && !empty($availability['google_pay']);
                                    $canPayPal = !empty($availability['paypal']);
                                    $supportsStripe = $method === 'stripe' && $canStripe;
                                    $supportsGooglePay = $method === 'google_pay' && $canGooglePay;
                                    $supportsPayPal = $method === 'paypal' && $canPayPal;
                                    $canPayOnline = ($supportsStripe || $supportsGooglePay || $supportsPayPal);
                                ?>
                                <?php if ($invoice['status'] === 'paid'): ?>
                                    <span class="badge badge--paid">Paid</span>
                                <?php elseif ($canPayOnline): ?>
                                    <button
                                        type="button"
                                        class="button button--primary"
                                        data-invoice-pay
                                        data-invoice-id="<?= (int) $invoice['id']; ?>"
                                        data-invoice-amount="<?= number_format((float) $invoice['total'], 2, '.', ''); ?>"
                                        data-invoice-currency="<?= e($currency); ?>"
                                        data-invoice-service="<?= e($invoice['service_name']); ?>"
                                        data-invoice-method="<?= e($method); ?>"
                                        data-invoice-subscription="<?= (int) ($invoice['subscription_id'] ?? 0); ?>"
                                        data-invoice-interval="<?= e($invoice['subscription_interval'] ?? ''); ?>"
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
<?php if ($paymentReady): ?>
    <?php include __DIR__ . '/../partials/payments_modal.php'; ?>
<?php endif; ?>
