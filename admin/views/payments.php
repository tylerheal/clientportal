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
            <p>Connect Stripe, Google Pay, and PayPal so clients can pay invoices and service orders directly from the portal.</p>
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
            <p class="hint">Recurring services are billed by creating a PaymentIntent each cycle with the saved customer and payment method. You only need Stripe’s subscription item API if you later decide to manage Stripe subscriptions directly.</p>
            <label>Publishable key
                <input type="text" name="stripe_publishable_key" value="<?= e($stripePublishable); ?>">
            </label>
            <label>Secret key
                <input type="text" name="stripe_secret_key" value="<?= e($stripeSecret); ?>">
            </label>
            <h3>Google Pay</h3>
            <p class="hint">Google Pay is powered by your Stripe account. Enable Stripe above, then toggle it on here to surface the Google Pay button for supported browsers and devices.</p>
            <label class="switch">
                <input type="checkbox" name="enable_google_pay"<?= $enableGooglePay ? ' checked' : ''; ?><?= !$enableStripe ? ' disabled' : ''; ?>>
                <span><?= $enableStripe ? 'Offer Google Pay alongside cards' : 'Enable Stripe to unlock Google Pay'; ?></span>
            </label>
            <p class="hint">No additional keys are needed—Stripe handles the verification once this toggle is on.</p>
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
    <?php if (!empty($subscriptionSummaries)): ?>
        <div class="card">
            <header class="section-header">
                <div>
                    <h3>Subscription renewals</h3>
                    <p class="hint">Use the test action to raise the next invoice immediately and attempt an automatic charge with your sandbox credentials before going live.</p>
                </div>
            </header>
            <div class="table-wrapper">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Client</th>
                            <th>Service</th>
                            <th>Interval</th>
                            <th>Next billing</th>
                            <th>Status</th>
                            <th>Test</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($subscriptionSummaries as $subscription): ?>
                            <tr>
                                <td>
                                    <strong><?= e($subscription['client_name']); ?></strong>
                                    <div class="subtle"><?= e($subscription['email']); ?></div>
                                </td>
                                <td>
                                    <strong><?= e($subscription['service_name']); ?></strong>
                                    <div class="subtle"><?= e(format_currency((float) $subscription['price'])); ?></div>
                                </td>
                                <td><?= e(ucfirst($subscription['interval'])); ?></td>
                                <td><?= e(format_datetime($subscription['next_billing_at'])); ?></td>
                                <td><?= e(ucfirst($subscription['status'])); ?></td>
                                <td>
                                    <form action="<?= e(url_for('dashboard')); ?>" method="post" class="inline-form">
                                        <input type="hidden" name="action" value="test_subscription_cycle">
                                        <input type="hidden" name="redirect" value="admin/payments">
                                        <input type="hidden" name="subscription_id" value="<?= (int) $subscription['id']; ?>">
                                        <input type="hidden" name="force" value="1">
                                        <button type="submit" class="button button--primary">Run test charge</button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    <?php else: ?>
        <div class="card">
            <p class="subtle">No active subscriptions yet. Once a client purchases a recurring service, it will appear here for testing.</p>
        </div>
    <?php endif; ?>
</section>
