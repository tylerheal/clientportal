<?php
$stripePublishable = get_setting('stripe_publishable_key', '');
$stripeSecret = get_setting('stripe_secret_key', '');
$paypalClient = get_setting('paypal_client_id', '');
$paypalSecret = get_setting('paypal_client_secret', '');
$enableStripe = is_truthy_setting('payments_enable_stripe');
$enablePaypal = is_truthy_setting('payments_enable_paypal');
$enableGooglePay = is_truthy_setting('payments_enable_google_pay');
$paypalMode = strtolower(get_setting('paypal_mode', 'sandbox')) === 'live' ? 'live' : 'sandbox';
?>
<section class="page-section">
    <?php foreach (['error', 'success'] as $flashType): ?>
        <?php if ($message = flash($flashType)): ?>
            <div class="alert alert--<?= $flashType; ?>"><?= e($message); ?></div>
        <?php endif; ?>
    <?php endforeach; ?>
    <header class="page-header">
        <div>
            <h2>Payment integrations</h2>
            <p>Connect Stripe and PayPal to enable checkout flows.</p>
        </div>
    </header>
    <div class="card">
        <form action="<?= e(url_for('dashboard')); ?>" method="post" class="form-grid">
            <input type="hidden" name="action" value="update_payments">
            <input type="hidden" name="redirect" value="admin/payments">
            <h3>Stripe</h3>
            <label class="switch">
                <input type="checkbox" name="enable_stripe"<?= $enableStripe ? ' checked' : ''; ?>>
                <span>Enable Stripe card payments</span>
            </label>
            <label>Publishable key
                <input type="text" name="stripe_publishable_key" value="<?= e($stripePublishable); ?>">
            </label>
            <label>Secret key
                <input type="text" name="stripe_secret_key" value="<?= e($stripeSecret); ?>">
            </label>
            <label class="switch">
                <input type="checkbox" name="enable_google_pay"<?= $enableGooglePay ? ' checked' : ''; ?><?= !$enableStripe ? ' disabled' : ''; ?>>
                <span>Offer Google Pay via Stripe</span>
            </label>
            <p class="hint">Requires Stripe to be enabled and will display automatically on supported devices.</p>
            <h3>PayPal</h3>
            <label class="switch">
                <input type="checkbox" name="enable_paypal"<?= $enablePaypal ? ' checked' : ''; ?>>
                <span>Enable PayPal checkout</span>
            </label>
            <label>Client ID
                <input type="text" name="paypal_client_id" value="<?= e($paypalClient); ?>">
            </label>
            <label>Client secret
                <input type="text" name="paypal_client_secret" value="<?= e($paypalSecret); ?>">
            </label>
            <label>Environment
                <select name="paypal_mode">
                    <option value="sandbox"<?= $paypalMode === 'sandbox' ? ' selected' : ''; ?>>Sandbox</option>
                    <option value="live"<?= $paypalMode === 'live' ? ' selected' : ''; ?>>Live</option>
                </select>
            </label>
            <div class="form-actions">
                <button type="submit" class="button button--primary">Save settings</button>
            </div>
        </form>
    </div>
</section>
