<?php
$sidebar = [
    ['label' => 'Overview', 'href' => '#overview', 'current' => true],
    ['label' => 'Services', 'href' => '#services'],
    ['label' => 'Orders', 'href' => '#orders'],
    ['label' => 'Invoices', 'href' => '#invoices'],
    ['label' => 'Support', 'href' => '#tickets'],
];
$activeServicesCount = count($services);
$openOrdersCount = count(array_filter($orders, fn($order) => $order['payment_status'] !== 'paid'));
$openTicketsCount = count(array_filter($tickets, fn($ticket) => !in_array($ticket['status'], ['resolved', 'closed'], true)));
$unpaidInvoices = count(array_filter($invoices, fn($invoice) => $invoice['status'] !== 'paid'));

ob_start();
?>
<section id="overview" class="panel">
    <div class="panel-header">
        <h1>Welcome back, <?= e($user['name']); ?> 👋</h1>
        <p>Here's a snapshot of your portal today.</p>
    </div>
    <div class="metrics-grid">
        <article class="metric">
            <span class="metric-label">Active services</span>
            <strong class="metric-value"><?= $activeServicesCount; ?></strong>
            <p>Available for ordering.</p>
        </article>
        <article class="metric">
            <span class="metric-label">Open orders</span>
            <strong class="metric-value"><?= $openOrdersCount; ?></strong>
            <p>Awaiting action.</p>
        </article>
        <article class="metric">
            <span class="metric-label">Open tickets</span>
            <strong class="metric-value"><?= $openTicketsCount; ?></strong>
            <p>We'll keep you posted.</p>
        </article>
        <article class="metric">
            <span class="metric-label">Unpaid invoices</span>
            <strong class="metric-value"><?= $unpaidInvoices; ?></strong>
            <p>Due for payment.</p>
        </article>
    </div>
</section>

<section id="services" class="panel">
    <div class="panel-header">
        <h2>Services catalogue</h2>
        <p>Submit a brief to kick things off.</p>
    </div>
    <div class="card-grid">
        <?php foreach ($services as $service): ?>
            <article class="card">
                <header class="card-header">
                    <div class="card-title">
                        <span class="card-icon"><?= e(strtoupper(substr($service['name'], 0, 1))); ?></span>
                        <div>
                            <h3><?= e($service['name']); ?></h3>
                            <p><?= e($service['description']); ?></p>
                        </div>
                    </div>
                    <div class="card-price">
                        <strong><?= format_currency((float) $service['price']); ?></strong>
                        <span><?= $service['billing_interval'] === 'one_time' ? 'One-off' : ucfirst($service['billing_interval']); ?></span>
                    </div>
                </header>
                <form action="<?= e(url_for('dashboard')); ?>" method="post" class="card-form">
                    <input type="hidden" name="action" value="create_order">
                    <input type="hidden" name="service_id" value="<?= (int) $service['id']; ?>">
                    <?php foreach (parse_form_schema($service['form_schema']) as $field): ?>
                        <label><?= e($field['label']); ?>
                            <input type="<?= e($field['type']); ?>" name="custom[<?= e($field['name']); ?>]" <?= !empty($field['required']) ? 'required' : ''; ?>>
                        </label>
                    <?php endforeach; ?>
                    <label>Payment method
                        <select name="payment_method">
                            <option value="stripe">Stripe</option>
                            <option value="paypal">PayPal</option>
                        </select>
                    </label>
                    <button type="submit">Order service</button>
                </form>
            </article>
        <?php endforeach; ?>
        <?php if (!$services): ?>
            <p class="panel-empty">No services available right now.</p>
        <?php endif; ?>
    </div>
</section>

<section id="orders" class="panel">
    <div class="panel-header">
        <h2>Recent orders</h2>
        <p>Your latest submissions.</p>
    </div>
    <div class="table-scroll">
        <table class="data-table">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Service</th>
                    <th>Placed</th>
                    <th>Status</th>
                    <th>Total</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($orders as $order): ?>
                    <tr>
                        <td>#<?= (int) $order['id']; ?></td>
                        <td><?= e($order['service_name']); ?></td>
                        <td><?= e(format_datetime($order['created_at'])); ?></td>
                        <td><span class="status status--<?= e($order['payment_status']); ?>"><?= e(ucfirst($order['payment_status'])); ?></span></td>
                        <td><?= format_currency((float) $order['total_amount']); ?></td>
                    </tr>
                <?php endforeach; ?>
                <?php if (!$orders): ?>
                    <tr><td colspan="5" class="panel-empty">No orders yet.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</section>

<section id="invoices" class="panel">
    <div class="panel-header">
        <h2>Invoices</h2>
        <p>Track paid and outstanding invoices.</p>
    </div>
    <div class="table-scroll">
        <table class="data-table">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Service</th>
                    <th>Due</th>
                    <th>Status</th>
                    <th>Total</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($invoices as $invoice): ?>
                    <tr>
                        <td>#<?= (int) $invoice['id']; ?></td>
                        <td><?= e($invoice['service_name']); ?></td>
                        <td><?= e(format_datetime($invoice['due_at'])); ?></td>
                        <td><span class="status status--<?= e($invoice['status']); ?>"><?= e(ucfirst($invoice['status'])); ?></span></td>
                        <td><?= format_currency((float) $invoice['total']); ?></td>
                    </tr>
                <?php endforeach; ?>
                <?php if (!$invoices): ?>
                    <tr><td colspan="5" class="panel-empty">No invoices to show.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</section>

<section id="tickets" class="panel">
    <div class="panel-header">
        <h2>Support tickets</h2>
        <p>Reach out any time.</p>
    </div>
    <div class="support-grid">
        <form action="<?= e(url_for('dashboard')); ?>" method="post" class="support-form">
            <input type="hidden" name="action" value="create_ticket">
            <label>Subject
                <input type="text" name="subject" required>
            </label>
            <label>Message
                <textarea name="message" rows="5" required></textarea>
            </label>
            <button type="submit">Submit ticket</button>
        </form>
        <div class="support-list">
            <?php foreach ($tickets as $ticket): ?>
                <article class="support-card">
                    <header>
                        <strong><?= e($ticket['subject']); ?></strong>
                        <span class="status status--<?= e($ticket['status']); ?>"><?= e(ucfirst($ticket['status'])); ?></span>
                    </header>
                    <div class="support-messages">
                        <?php foreach ($messagesByTicket[$ticket['id']] ?? [] as $message): ?>
                            <div class="support-message">
                                <span class="support-author"><?= e($message['name']); ?></span>
                                <span class="support-time"><?= e(format_datetime($message['created_at'])); ?></span>
                                <p><?= nl2br(e($message['message'])); ?></p>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <form action="<?= e(url_for('dashboard')); ?>" method="post" class="support-reply">
                        <input type="hidden" name="action" value="reply_ticket_client">
                        <input type="hidden" name="ticket_id" value="<?= (int) $ticket['id']; ?>">
                        <label>Reply
                            <textarea name="message" rows="3" required></textarea>
                        </label>
                        <button type="submit">Send reply</button>
                    </form>
                </article>
            <?php endforeach; ?>
            <?php if (!$tickets): ?>
                <p class="panel-empty">No support tickets yet.</p>
            <?php endif; ?>
        </div>
    </div>
</section>
<?php
$content = ob_get_clean();
include __DIR__ . '/partials/dashboard_layout.php';
