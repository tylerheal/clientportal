<?php
if (!defined('APP_BOOTSTRAPPED')) {
    require __DIR__ . '/../../../bootstrap.php';
}
?>
<div class="modal-overlay" data-invoice-modal hidden data-action="<?= e(url_for('dashboard')); ?>">
    <div class="modal-card">
        <button class="modal-close" type="button" data-invoice-close>&times;</button>
        <h3>Pay invoice <span data-invoice-number></span></h3>
        <p>We’ll redirect you after PayPal confirms the payment.</p>
        <div class="invoice-summary subtle" data-invoice-summary></div>
        <div data-paypal-container></div>
        <p class="subtle small" data-invoice-feedback></p>
    </div>
</div>
