<?php
$activeServices = count(array_filter($services, fn($service) => (int) $service['active'] === 1));
$clientCount = count($clients);
$openTickets = count(array_filter($tickets, fn($ticket) => !in_array($ticket['status'], ['resolved', 'closed'], true)));
$pendingInvoices = count(array_filter($invoices, fn($invoice) => $invoice['status'] !== 'paid'));
$totalRevenue = array_sum(array_map(fn($order) => $order['payment_status'] === 'paid' ? (float) $order['total_amount'] : 0.0, $orders));
?>
<section class="page-section">
    <?php foreach (['error', 'success'] as $flashType): ?>
        <?php if ($message = flash($flashType)): ?>
            <div class="alert alert--<?= $flashType; ?>"><?= e($message); ?></div>
        <?php endif; ?>
    <?php endforeach; ?>
    <div class="card metric-grid">
        <div class="metric-tile">
            <span class="metric-label">Active services</span>
            <strong class="metric-value"><?= $activeServices; ?></strong>
            <p class="metric-description">Services available to clients.</p>
        </div>
        <div class="metric-tile">
            <span class="metric-label">Clients</span>
            <strong class="metric-value"><?= $clientCount; ?></strong>
            <p class="metric-description">Accounts in the portal.</p>
        </div>
        <div class="metric-tile">
            <span class="metric-label">Open tickets</span>
            <strong class="metric-value"><?= $openTickets; ?></strong>
            <p class="metric-description">Needing a response.</p>
        </div>
        <div class="metric-tile">
            <span class="metric-label">Pending invoices</span>
            <strong class="metric-value"><?= $pendingInvoices; ?></strong>
            <p class="metric-description">Collected <?= format_currency($totalRevenue); ?> to date.</p>
        </div>
    </div>
    <div class="page-columns">
        <article class="card column">
            <header class="card-header">
                <div>
                    <h2>Recent orders</h2>
                    <p>Latest five client purchases.</p>
                </div>
                <a class="button button--ghost" href="<?= e(url_for('admin/orders')); ?>">Manage</a>
            </header>
            <div class="table-wrapper">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Order</th>
                            <th>Client</th>
                            <th>Service</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach (array_slice($orders, 0, 5) as $order): ?>
                            <tr>
                                <td>#<?= (int) $order['id']; ?></td>
                                <td><?= e($order['client_name']); ?></td>
                                <td><?= e($order['service_name']); ?></td>
                                <td><span class="badge badge--<?= e($order['payment_status']); ?>"><?= e(ucfirst($order['payment_status'])); ?></span></td>
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
                    <h2>Tickets</h2>
                    <p>Keep response times tight.</p>
                </div>
                <a class="button button--ghost" href="<?= e(url_for('admin/tickets')); ?>">View all</a>
            </header>
            <ul class="ticket-summary">
                <?php foreach (array_slice($tickets, 0, 5) as $ticket): ?>
                    <li>
                        <div>
                            <strong><?= e($ticket['subject']); ?></strong>
                            <span><?= e($ticket['client_name']); ?> · <?= e(format_datetime($ticket['updated_at'])); ?></span>
                        </div>
                        <span class="badge badge--<?= e($ticket['status']); ?>"><?= e(ucfirst($ticket['status'])); ?></span>
                    </li>
                <?php endforeach; ?>
                <?php if (!$tickets): ?>
                    <li class="table-empty">No tickets yet.</li>
                <?php endif; ?>
            </ul>
        </article>
    </div>
</section>
