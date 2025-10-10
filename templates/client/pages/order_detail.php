<?php if (!$clientSelectedOrder): ?>
    <section class="page-section">
        <p class="table-empty">Order not found.</p>
    </section>
    <?php return; ?>
<?php endif; ?>
<section class="page-section">
    <?php foreach (['error', 'success'] as $flashType): ?>
        <?php if ($message = flash($flashType)): ?>
            <div class="alert alert--<?= $flashType; ?>"><?= e($message); ?></div>
        <?php endif; ?>
    <?php endforeach; ?>
    <header class="page-header order-detail__header">
        <div>
            <a class="button button--primary" href="<?= e(url_for('dashboard/orders')); ?>">← Back to orders</a>
            <h2>Order #<?= (int) $clientSelectedOrder['id']; ?></h2>
            <p><?= e($clientSelectedOrder['service_name']); ?></p>
        </div>
        <div class="page-actions">
            <span class="badge badge--<?= e($clientSelectedOrder['payment_status']); ?>"><?= e(ucfirst($clientSelectedOrder['payment_status'])); ?></span>
        </div>
    </header>
    <div class="order-layout">
        <article class="card order-overview">
            <section>
                <h3>Timeline</h3>
                <?php if ($clientSelectedOrderTimeline): ?>
                    <ol class="order-timeline">
                        <?php foreach ($clientSelectedOrderTimeline as $event): ?>
                            <li>
                                <div>
                                    <h4><?= e($event['title']); ?></h4>
                                    <?php if (!empty($event['description'])): ?>
                                        <p><?= e($event['description']); ?></p>
                                    <?php endif; ?>
                                    <?php if (!empty($event['timestamp'])): ?>
                                        <div class="order-timeline__meta">
                                            <time><?= e(format_datetime($event['timestamp'])); ?></time>
                                            <span><?= e(format_relative_time($event['timestamp'])); ?></span>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </li>
                        <?php endforeach; ?>
                    </ol>
                <?php else: ?>
                    <p class="subtle">No activity recorded yet.</p>
                <?php endif; ?>
            </section>
            <section>
                <h3>Your responses</h3>
                <?php if ($clientSelectedOrderFormEntries): ?>
                    <dl class="order-form">
                        <?php foreach ($clientSelectedOrderFormEntries as $entry): ?>
                            <div class="order-form__item">
                                <dt><?= e($entry['label']); ?></dt>
                                <?php if ($entry['value'] !== ''): ?>
                                    <dd><?= e($entry['value']); ?></dd>
                                <?php else: ?>
                                    <dd><span class="subtle">—</span></dd>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    </dl>
                <?php else: ?>
                    <p class="subtle">No additional form responses.</p>
                <?php endif; ?>
            </section>
        </article>
        <aside class="card order-sidebar">
            <section>
                <h3>Summary</h3>
                <dl class="order-meta">
                    <div class="order-meta__row">
                        <dt>Total</dt>
                        <dd><?= format_currency((float) $clientSelectedOrder['total_amount']); ?></dd>
                    </div>
                    <div class="order-meta__row">
                        <dt>Payment method</dt>
                        <dd><?= e(ucwords(str_replace(['_', '-'], ' ', (string) $clientSelectedOrder['payment_method']))); ?></dd>
                    </div>
                    <div class="order-meta__row">
                        <dt>Billing interval</dt>
                        <dd><?= e(ucwords(str_replace(['_', '-'], ' ', (string) $clientSelectedOrder['billing_interval']))); ?></dd>
                    </div>
                    <div class="order-meta__row">
                        <dt>Payment reference</dt>
                        <dd>
                            <?php if ($clientSelectedOrder['payment_reference']): ?>
                                <?php
                                    $reference = (string) $clientSelectedOrder['payment_reference'];
                                    $referenceDisplay = abbreviate_reference($reference);
                                ?>
                                <span class="order-meta__reference" title="<?= e($reference); ?>"><?= e($referenceDisplay); ?></span>
                                <button
                                    type="button"
                                    class="order-meta__copy"
                                    data-copy-text="<?= e($reference); ?>"
                                    aria-label="Copy payment reference"
                                >
                                    <i class="fa-regular fa-copy" aria-hidden="true"></i>
                                    <span class="sr-only">Copy</span>
                                </button>
                            <?php else: ?>
                                <span class="subtle">—</span>
                            <?php endif; ?>
                        </dd>
                    </div>
                    <div class="order-meta__row">
                        <dt>Created</dt>
                        <dd><?= e(format_datetime($clientSelectedOrder['created_at'])); ?></dd>
                    </div>
                    <div class="order-meta__row">
                        <dt>Updated</dt>
                        <dd><?= e(format_datetime($clientSelectedOrder['updated_at'])); ?></dd>
                    </div>
                </dl>
            </section>
            <section>
                <h3>Update order</h3>
                <form action="<?= e(url_for('dashboard')); ?>" method="post" class="order-manage-form">
                    <input type="hidden" name="action" value="update_order_reference">
                    <input type="hidden" name="order_id" value="<?= (int) $clientSelectedOrder['id']; ?>">
                    <input type="hidden" name="redirect" value="dashboard/orders/<?= (int) $clientSelectedOrder['id']; ?>">
                    <label>
                        <span>Payment reference</span>
                        <input type="text" name="payment_reference" value="<?= e($clientSelectedOrder['payment_reference']); ?>" placeholder="Add a payment reference">
                    </label>
                    <p class="subtle">If you paid manually, add a reference to help our team match the payment.</p>
                    <div class="form-actions">
                        <button type="submit" class="button button--primary">Save changes</button>
                    </div>
                </form>
            </section>
            <?php if ($clientSelectedOrderInvoices): ?>
                <section>
                    <h3>Invoices</h3>
                    <ul class="order-invoices">
                        <?php foreach ($clientSelectedOrderInvoices as $invoice): ?>
                            <?php $invoiceNumber = format_invoice_number($invoice + ['client_name' => $user['name'] ?? '']); ?>
                            <li>
                                <div>
                                    <strong>Invoice <?= e($invoiceNumber); ?></strong>
                                    <span class="badge badge--<?= e($invoice['status']); ?>"><?= e(ucfirst($invoice['status'])); ?></span>
                                </div>
                                <div class="order-invoices__meta">
                                    <span><?= format_currency((float) $invoice['total']); ?></span>
                                    <time><?= e(format_datetime($invoice['due_at'])); ?></time>
                                </div>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </section>
            <?php endif; ?>
        </aside>
    </div>
</section>
