<?php
if (!defined('APP_BOOTSTRAPPED')) {
    require __DIR__ . '/../../../bootstrap.php';
}
?>
<div class="modal-overlay" data-invoice-modal hidden data-action="<?= e(url_for('dashboard')); ?>">
    <div class="modal-card">
        <button class="modal-close" type="button" data-invoice-close>&times;</button>
        <h3>Pay invoice <span data-invoice-number></span></h3>
        <p class="subtle" data-payment-intro>Complete your secure payment below.</p>
        <div class="invoice-summary subtle" data-invoice-summary></div>
        <div class="payment-provider" data-payment-provider hidden></div>
        <div class="payment-panels">
            <div class="payment-panel" data-paypal-container hidden></div>
            <div class="payment-panel" data-stripe-card hidden>
                <div class="stripe-card" data-stripe-card-element></div>
                <button type="button" class="btn primary" data-stripe-submit>Pay now</button>
                <p class="subtle small" data-stripe-feedback></p>
            </div>
            <div class="payment-panel" data-payment-request hidden>
                <div data-payment-request-button></div>
                <p class="subtle small">Google Pay is available on supported devices and browsers.</p>
            </div>
        </div>
        <p class="subtle small" data-invoice-feedback></p>
    </div>
</div>
