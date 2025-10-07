<?php
$activeServicesCount = count($services);
$openOrdersCount = count(array_filter($orders, fn($order) => $order['payment_status'] !== 'paid'));
$openTicketsCount = count(array_filter($tickets, fn($ticket) => !in_array($ticket['status'], ['resolved', 'closed'], true)));
$unpaidInvoices = count(array_filter($invoices, fn($invoice) => $invoice['status'] !== 'paid'));
?>
<section class="page-section">
    <?php foreach (['error', 'success'] as $flashType): ?>
        <?php if ($message = flash($flashType)): ?>
            <div class="alert alert--<?= $flashType; ?>"><?= e($message); ?></div>
        <?php endif; ?>
    <?php endforeach; ?>
    <div class="card">
        <div class="metric-grid">
            <div class="metric-tile">
                <span class="metric-label">Active services</span>
                <strong class="metric-value"><?= $activeServicesCount; ?></strong>
                <p class="metric-description">Available to order today.</p>
            </div>
            <div class="metric-tile">
                <span class="metric-label">Open orders</span>
                <strong class="metric-value"><?= $openOrdersCount; ?></strong>
                <p class="metric-description">Awaiting payment or fulfilment.</p>
            </div>
            <div class="metric-tile">
                <span class="metric-label">Open tickets</span>
                <strong class="metric-value"><?= $openTicketsCount; ?></strong>
                <p class="metric-description">We’ll keep you posted.</p>
            </div>
            <div class="metric-tile">
                <span class="metric-label">Unpaid invoices</span>
                <strong class="metric-value"><?= $unpaidInvoices; ?></strong>
                <p class="metric-description">Due for settlement.</p>
            </div>
        </div>
    </div>
    <div class="page-columns">
        <article class="card column">
            <header class="card-header">
                <div>
                    <h2>Latest orders</h2>
                    <p>Your five most recent orders.</p>
                </div>
                <a class="button button--ghost" href="<?= e(url_for('dashboard/orders')); ?>">View all</a>
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
                        <?php foreach (array_slice($orders, 0, 5) as $order): ?>
                            <tr>
                                <td>#<?= (int) $order['id']; ?></td>
                                <td><?= e($order['service_name']); ?></td>
                                <td><span class="badge badge--<?= e($order['payment_status']); ?>"><?= e(ucfirst($order['payment_status'])); ?></span></td>
                                <td><?= e(format_datetime($order['created_at'])); ?></td>
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
                <a class="button button--ghost" href="<?= e(url_for('dashboard/tickets')); ?>">Manage</a>
            </header>
            <ul class="ticket-summary">
                <?php foreach (array_slice($tickets, 0, 5) as $ticket): ?>
                    <li>
                        <div>
                            <strong><?= e($ticket['subject']); ?></strong>
                            <span><?= e(format_datetime($ticket['updated_at'])); ?></span>
                        </div>
                        <span class="badge badge--<?= e($ticket['status']); ?>"><?= e(ucfirst($ticket['status'])); ?></span>
                    </li>
                <?php endforeach; ?>
                <?php if (!$tickets): ?>
                    <li class="table-empty">No support tickets.</li>
                <?php endif; ?>
            </ul>
        </article>
    </div>
</section>
