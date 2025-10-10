<?php if (!$selectedOrder): ?>
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
            <a class="button button--primary" href="<?= e(url_for('admin/orders')); ?>">← Back to orders</a>
            <h2>Order #<?= (int) $selectedOrder['id']; ?></h2>
            <p><?= e($selectedOrder['service_name']); ?> · <?= e($selectedOrder['client_name']); ?></p>
        </div>
        <div class="page-actions">
            <span class="badge badge--<?= e($selectedOrder['payment_status']); ?>"><?= e(ucfirst($selectedOrder['payment_status'])); ?></span>
        </div>
    </header>
    <div class="order-layout">
        <article class="card order-overview">
            <section>
                <h3>Timeline</h3>
                <?php if ($selectedOrderTimeline): ?>
                    <ol class="order-timeline">
                        <?php foreach ($selectedOrderTimeline as $event): ?>
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
                <h3>Order form</h3>
                <?php if ($selectedOrderFormEntries): ?>
                    <dl class="order-form">
                        <?php foreach ($selectedOrderFormEntries as $entry): ?>
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
                        <dd><?= format_currency((float) $selectedOrder['total_amount']); ?></dd>
                    </div>
                    <div class="order-meta__row">
                        <dt>Payment method</dt>
                        <dd><?= e(ucwords(str_replace(['_', '-'], ' ', (string) $selectedOrder['payment_method']))); ?></dd>
                    </div>
                    <div class="order-meta__row">
                        <dt>Billing interval</dt>
                        <dd><?= e(ucwords(str_replace(['_', '-'], ' ', (string) $selectedOrder['billing_interval']))); ?></dd>
                    </div>
                    <div class="order-meta__row">
                        <dt>Payment reference</dt>
                        <dd>
                            <?php if ($selectedOrder['payment_reference']): ?>
                                <?php
                                    $reference = (string) $selectedOrder['payment_reference'];
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
                        <dd><?= e(format_datetime($selectedOrder['created_at'])); ?></dd>
                    </div>
                    <div class="order-meta__row">
                        <dt>Updated</dt>
                        <dd><?= e(format_datetime($selectedOrder['updated_at'])); ?></dd>
                    </div>
                </dl>
            </section>
            <section>
                <h3>Update order</h3>
                <form action="<?= e(url_for('dashboard')); ?>" method="post" class="order-manage-form">
                    <input type="hidden" name="action" value="update_order_status">
                    <input type="hidden" name="order_id" value="<?= (int) $selectedOrder['id']; ?>">
                    <input type="hidden" name="redirect" value="admin/orders/<?= (int) $selectedOrder['id']; ?>">
                    <label>
                        <span>Status</span>
                        <select name="payment_status">
                            <option value="pending" <?= $selectedOrder['payment_status'] === 'pending' ? 'selected' : ''; ?>>Pending</option>
                            <option value="paid" <?= $selectedOrder['payment_status'] === 'paid' ? 'selected' : ''; ?>>Paid</option>
                            <option value="failed" <?= $selectedOrder['payment_status'] === 'failed' ? 'selected' : ''; ?>>Failed</option>
                        </select>
                    </label>
                    <label>
                        <span>Payment reference</span>
                        <input type="text" name="payment_reference" value="<?= e($selectedOrder['payment_reference']); ?>" placeholder="Optional reference">
                    </label>
                    <div class="form-actions">
                        <button type="submit" class="button button--primary">Save changes</button>
                    </div>
                </form>
            </section>
            <?php if ($selectedOrderInvoices): ?>
                <section>
                    <h3>Invoices</h3>
                    <ul class="order-invoices">
                        <?php foreach ($selectedOrderInvoices as $invoice): ?>
                            <li>
                                <div>
                                    <?php $invoiceNumber = format_invoice_number($invoice + ['client_name' => $selectedOrder['client_name'] ?? '']); ?>
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
            <section>
                <h3>Client</h3>
                <dl class="order-meta">
                    <div class="order-meta__row">
                        <dt>Name</dt>
                        <dd><?= e($selectedOrder['client_name']); ?></dd>
                    </div>
                    <div class="order-meta__row">
                        <dt>Email</dt>
                        <dd>
                            <?php if (!empty($selectedOrder['client_email'])): ?>
                                <a href="mailto:<?= e($selectedOrder['client_email']); ?>"><?= e($selectedOrder['client_email']); ?></a>
                            <?php else: ?>
                                <span class="subtle">—</span>
                            <?php endif; ?>
                        </dd>
                    </div>
                    <div class="order-meta__row">
                        <dt>Company</dt>
                        <dd>
                            <?php if (!empty($selectedOrder['client_company'])): ?>
                                <?= e($selectedOrder['client_company']); ?>
                            <?php else: ?>
                                <span class="subtle">—</span>
                            <?php endif; ?>
                        </dd>
                    </div>
                </dl>
            </section>
        </aside>
    </div>
</section>
