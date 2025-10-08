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
            <h2>Services catalogue</h2>
            <p>Select a service to get started. Each request opens a tailored intake form.</p>
        </div>
    </header>
    <div class="service-grid">
        <?php foreach ($services as $service): ?>
            <?php $fields = parse_form_schema($service['form_schema']); ?>
            <article class="card service-card" data-service-card>
                <header class="card-header">
                    <div>
                        <h3><?= e($service['name']); ?></h3>
                        <p><?= e($service['description']); ?></p>
                    </div>
                    <div class="service-price">
                        <strong><?= format_currency((float) $service['price']); ?></strong>
                        <span><?= $service['billing_interval'] === 'one_time' ? 'One-off' : ucfirst($service['billing_interval']); ?></span>
                    </div>
                </header>
                <div class="service-feedback" data-service-feedback hidden></div>
                <form
                    action="<?= e(url_for('dashboard')); ?>"
                    method="post"
                    class="form-grid"
                    data-service-order-form
                    data-service-name="<?= e($service['name']); ?>"
                    data-service-price="<?= number_format((float) $service['price'], 2, '.', ''); ?>"
                    data-service-currency="<?= e($currency); ?>"
                >
                    <input type="hidden" name="action" value="create_order">
                    <input type="hidden" name="service_id" value="<?= (int) $service['id']; ?>">
                    <input type="hidden" name="redirect" value="dashboard/services">
                    <?php foreach ($fields as $field): ?>
                        <label><?= e($field['label']); ?>
                            <input type="<?= e($field['type']); ?>" name="custom[<?= e($field['name']); ?>]" <?= !empty($field['required']) ? 'required' : ''; ?>>
                        </label>
                    <?php endforeach; ?>
                    <label>Payment method
                        <select name="payment_method">
                            <?php if ($hasPaypal): ?>
                                <option value="paypal">PayPal</option>
                            <?php endif; ?>
                            <option value="manual">Manual invoice</option>
                        </select>
                    </label>
                    <button type="submit" class="button button--primary">Submit order</button>
                </form>
            </article>
        <?php endforeach; ?>
        <?php if (!$services): ?>
            <p class="table-empty">No services are currently available.</p>
        <?php endif; ?>
    </div>
</section>
<?php if ($hasPaypal): ?>
    <?php include __DIR__ . '/../partials/payments_modal.php'; ?>
<?php endif; ?>
