<?php
$activeServicesCount = count($services);
$openOrdersCount = count(array_filter($orders, fn($order) => ($order['fulfilment_status'] ?? 'open') !== 'complete'));
$openTicketsCount = count(array_filter($tickets, fn($ticket) => !in_array($ticket['status'], ['resolved', 'closed'], true)));
$unpaidInvoices = count(array_filter($invoices, fn($invoice) => $invoice['status'] !== 'paid'));
$recentOrders = array_slice($orders, 0, 5);
$recentTickets = array_slice($tickets, 0, 5);
?>
<section class="page-section">
    <?php foreach (['error', 'success'] as $flashType): ?>
        <?php if ($message = flash($flashType)): ?>
            <div class="alert alert--<?= $flashType; ?>"><?= e($message); ?></div>
        <?php endif; ?>
    <?php endforeach; ?>
    <div class="card">
        <div class="metric-grid metric-grid--linked">
            <a class="metric-tile metric-tile--link" href="<?= e(url_for('dashboard/services')); ?>">
                <span class="metric-label">Active services</span>
                <strong class="metric-value"><?= $activeServicesCount; ?></strong>
                <p class="metric-description">Available to order today.</p>
            </a>
            <a class="metric-tile metric-tile--link" href="<?= e(url_for('dashboard/orders')); ?>">
                <span class="metric-label">Open orders</span>
                <strong class="metric-value"><?= $openOrdersCount; ?></strong>
                <p class="metric-description">Awaiting fulfilment.</p>
            </a>
            <a class="metric-tile metric-tile--link" href="<?= e(url_for('dashboard/tickets')); ?>">
                <span class="metric-label">Open tickets</span>
                <strong class="metric-value"><?= $openTicketsCount; ?></strong>
                <p class="metric-description">We’ll keep you posted.</p>
            </a>
            <a class="metric-tile metric-tile--link" href="<?= e(url_for('dashboard/invoices')); ?>">
                <span class="metric-label">Unpaid invoices</span>
                <strong class="metric-value"><?= $unpaidInvoices; ?></strong>
                <p class="metric-description">Due for settlement.</p>
            </a>
        </div>
    </div>
    <div class="page-columns">
        <article class="card column">
            <header class="card-header">
                <div>
                    <h2>Latest orders</h2>
                    <p>Your five most recent orders.</p>
                </div>
                <a class="button button--primary" href="<?= e(url_for('dashboard/orders')); ?>">View all</a>
            </header>
            <div class="table-wrapper">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Order</th>
                            <th>Service</th>
                            <th>Status</th>
                            <th>Placed</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($recentOrders as $order): ?>
                            <tr class="table-row table-row--link" data-row-link="<?= e(url_for('dashboard/orders/' . (int) $order['id'])); ?>" tabindex="0" role="link" aria-label="View order #<?= (int) $order['id']; ?>">
                                <td>#<?= (int) $order['id']; ?></td>
                                <td>
                                    <div class="table-cell-primary">
                                        <span><?= e($order['service_name']); ?></span>
                                        <span class="table-subline">Placed <?= e(format_datetime($order['created_at'])); ?></span>
                                    </div>
                                </td>
                                <td>
                                    <?php [$statusSlug, $statusLabel] = client_order_status($order); ?>
                                    <span class="badge badge--<?= e($statusSlug); ?>"><?= e($statusLabel); ?></span>
                                </td>
                                <td><?= e(format_relative_time($order['created_at'])); ?></td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if (!$orders): ?>
                            <tr><td colspan="4" class="table-empty">No orders yet.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </article>
        <article class="card column">
            <header class="card-header">
                <div>
                    <h2>Support tickets</h2>
                    <p>Stay on top of conversations.</p>
                </div>
                <a class="button button--primary" href="<?= e(url_for('dashboard/tickets')); ?>">Manage</a>
            </header>
            <div class="table-wrapper">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Subject</th>
                            <th>Updated</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($recentTickets as $ticket): ?>
                            <tr class="table-row table-row--link" data-row-link="<?= e(url_for('dashboard/tickets/' . (int) $ticket['id'])); ?>" tabindex="0" role="link" aria-label="View ticket <?= (int) $ticket['id']; ?>">
                                <td>
                                    <div class="table-cell-primary">
                                        <span><?= e($ticket['subject']); ?></span>
                                        <span class="table-subline">Ticket #<?= (int) $ticket['id']; ?></span>
                                    </div>
                                </td>
                                <td>
                                    <div class="table-cell-primary">
                                        <span><?= e(format_datetime($ticket['updated_at'])); ?></span>
                                        <span class="table-subline">Updated <?= e(format_relative_time($ticket['updated_at'])); ?></span>
                                    </div>
                                </td>
                                <td>
                                    <span class="badge badge--<?= e($ticket['status']); ?>"><?= e(ucfirst($ticket['status'])); ?></span>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if (!$tickets): ?>
                            <tr><td colspan="3" class="table-empty">No support tickets.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </article>
    </div>
</section>
