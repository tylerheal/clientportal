<?php
$sidebar = [
    ['label' => 'Overview', 'href' => '#overview', 'current' => true],
    ['label' => 'Services', 'href' => '#services'],
    ['label' => 'Orders', 'href' => '#orders'],
    ['label' => 'Invoices', 'href' => '#invoices'],
    ['label' => 'Tickets', 'href' => '#tickets'],
    ['label' => 'Clients', 'href' => '#clients'],
    ['label' => 'Automations', 'href' => '#automations'],
    ['label' => 'Payments', 'href' => '#payments'],
    ['label' => 'Administrators', 'href' => '#administrators'],
];
$activeServices = count(array_filter($services, fn($service) => (int) $service['active'] === 1));
$clientCount = count($clients);
$openTickets = count(array_filter($tickets, fn($ticket) => !in_array($ticket['status'], ['resolved', 'closed'], true)));
$pendingInvoices = count(array_filter($invoices, fn($invoice) => $invoice['status'] !== 'paid'));
$totalRevenue = array_sum(array_map(fn($order) => $order['payment_status'] === 'paid' ? (float) $order['total_amount'] : 0.0, $orders));

ob_start();
?>
<section id="overview" class="panel">
    <div class="panel-header">
        <h1>Admin control centre</h1>
        <p>Monitor performance and keep the portal humming.</p>
    </div>
    <div class="metrics-grid">
        <article class="metric">
            <span class="metric-label">Active services</span>
            <strong class="metric-value"><?= $activeServices; ?></strong>
            <p>Currently available.</p>
        </article>
        <article class="metric">
            <span class="metric-label">Clients</span>
            <strong class="metric-value"><?= $clientCount; ?></strong>
            <p>Accounts in the portal.</p>
        </article>
        <article class="metric">
            <span class="metric-label">Open tickets</span>
            <strong class="metric-value"><?= $openTickets; ?></strong>
            <p>Needing attention.</p>
        </article>
        <article class="metric">
            <span class="metric-label">Pending invoices</span>
            <strong class="metric-value"><?= $pendingInvoices; ?></strong>
            <p>Total collected so far: <?= format_currency($totalRevenue); ?></p>
        </article>
    </div>
</section>

<section id="services" class="panel">
    <div class="panel-header">
        <h2>Services</h2>
        <p>Keep your catalogue up to date.</p>
    </div>
    <div class="services-grid">
        <form action="<?= e(url_for('dashboard')); ?>" method="post" class="service-form">
            <input type="hidden" name="action" value="create_service">
            <h3>Create service</h3>
            <label>Name
                <input type="text" name="name" required>
            </label>
            <label>Description
                <textarea name="description" rows="3"></textarea>
            </label>
            <label>Price
                <input type="number" name="price" min="0" step="0.01" required>
            </label>
            <label>Billing interval
                <select name="billing_interval">
                    <option value="one_time">One-time</option>
                    <option value="monthly">Monthly</option>
                    <option value="annual">Annual</option>
                </select>
            </label>
            <label>Form builder (Label|name|type|required)
                <textarea name="form_builder" rows="4" placeholder="Website URL|website|url|required"></textarea>
            </label>
            <label class="inline">
                <input type="checkbox" name="active" checked> Active
            </label>
            <button type="submit">Add service</button>
        </form>
        <div class="table-scroll">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Price</th>
                        <th>Interval</th>
                        <th>Status</th>
                        <th>Form</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($services as $service): ?>
                        <?php $fields = parse_form_schema($service['form_schema']); ?>
                        <?php
                            $builderLines = [];
                            foreach ($fields as $field) {
                                $builderLines[] = sprintf('%s|%s|%s|%s', $field['label'], $field['name'], $field['type'], !empty($field['required']) ? 'required' : '');
                            }
                            $builderValue = implode("\n", array_filter($builderLines));
                        ?>
                        <tr>
                            <td><?= e($service['name']); ?></td>
                            <td><?= format_currency((float) $service['price']); ?></td>
                            <td><?= e(ucfirst(str_replace('_', ' ', $service['billing_interval']))); ?></td>
                            <td><span class="status status--<?= (int) $service['active'] === 1 ? 'active' : 'disabled'; ?>"><?= (int) $service['active'] === 1 ? 'Active' : 'Hidden'; ?></span></td>
                            <td>
                                <?php if ($fields): ?>
                                    <ul class="field-list">
                                        <?php foreach ($fields as $field): ?>
                                            <li><?= e($field['label']); ?> <span><?= e($field['type']); ?><?= !empty($field['required']) ? ' · required' : ''; ?></span></li>
                                        <?php endforeach; ?>
                                    </ul>
                                <?php else: ?>
                                    <span class="panel-empty">No custom fields</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <details>
                                    <summary>Edit</summary>
                                    <form action="<?= e(url_for('dashboard')); ?>" method="post" class="service-edit">
                                        <input type="hidden" name="action" value="update_service">
                                        <input type="hidden" name="service_id" value="<?= (int) $service['id']; ?>">
                                        <label>Name
                                            <input type="text" name="name" value="<?= e($service['name']); ?>" required>
                                        </label>
                                        <label>Description
                                            <textarea name="description" rows="3"><?= e($service['description']); ?></textarea>
                                        </label>
                                        <label>Price
                                            <input type="number" name="price" value="<?= e($service['price']); ?>" step="0.01" required>
                                        </label>
                                        <label>Billing interval
                                            <select name="billing_interval">
                                                <option value="one_time"<?= $service['billing_interval'] === 'one_time' ? ' selected' : ''; ?>>One-time</option>
                                                <option value="monthly"<?= $service['billing_interval'] === 'monthly' ? ' selected' : ''; ?>>Monthly</option>
                                                <option value="annual"<?= $service['billing_interval'] === 'annual' ? ' selected' : ''; ?>>Annual</option>
                                            </select>
                                        </label>
                                        <label>Form builder
                                            <textarea name="form_builder" rows="3"><?= e($builderValue); ?></textarea>
                                        </label>
                                        <label class="inline">
                                            <input type="checkbox" name="active" <?= (int) $service['active'] === 1 ? 'checked' : ''; ?>> Active
                                        </label>
                                        <div class="service-actions">
                                            <button type="submit">Save</button>
                                        </div>
                                    </form>
                                    <form action="<?= e(url_for('dashboard')); ?>" method="post" class="service-delete" onsubmit="return confirm('Delete this service?');">
                                        <input type="hidden" name="action" value="delete_service">
                                        <input type="hidden" name="service_id" value="<?= (int) $service['id']; ?>">
                                        <button type="submit" class="danger">Delete</button>
                                    </form>
                                </details>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (!$services): ?>
                        <tr><td colspan="6" class="panel-empty">No services created yet.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</section>

<section id="orders" class="panel">
    <div class="panel-header">
        <h2>Orders</h2>
        <p>Review and update payment status.</p>
    </div>
    <div class="table-scroll">
        <table class="data-table">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Client</th>
                    <th>Service</th>
                    <th>Placed</th>
                    <th>Status</th>
                    <th>Total</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($orders as $order): ?>
                    <tr>
                        <td>#<?= (int) $order['id']; ?></td>
                        <td><?= e($order['client_name']); ?></td>
                        <td><?= e($order['service_name']); ?></td>
                        <td><?= e(format_datetime($order['created_at'])); ?></td>
                        <td><span class="status status--<?= e($order['payment_status']); ?>"><?= e(ucfirst($order['payment_status'])); ?></span></td>
                        <td><?= format_currency((float) $order['total_amount']); ?></td>
                        <td>
                            <form action="<?= e(url_for('dashboard')); ?>" method="post" class="inline-form">
                                <input type="hidden" name="action" value="update_order_status">
                                <input type="hidden" name="order_id" value="<?= (int) $order['id']; ?>">
                                <select name="payment_status">
                                    <option value="pending"<?= $order['payment_status'] === 'pending' ? ' selected' : ''; ?>>Pending</option>
                                    <option value="paid"<?= $order['payment_status'] === 'paid' ? ' selected' : ''; ?>>Paid</option>
                                    <option value="overdue"<?= $order['payment_status'] === 'overdue' ? ' selected' : ''; ?>>Overdue</option>
                                </select>
                                <input type="text" name="payment_reference" value="<?= e($order['payment_reference']); ?>" placeholder="Reference">
                                <button type="submit">Update</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <?php if (!$orders): ?>
                    <tr><td colspan="7" class="panel-empty">No orders yet.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</section>

<section id="invoices" class="panel">
    <div class="panel-header">
        <h2>Invoices</h2>
        <p>Monitor recurring billing.</p>
    </div>
    <div class="table-scroll">
        <table class="data-table">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Client</th>
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
                        <td><?= e($invoice['client_name']); ?></td>
                        <td><?= e($invoice['service_name']); ?></td>
                        <td><?= e(format_datetime($invoice['due_at'])); ?></td>
                        <td><span class="status status--<?= e($invoice['status']); ?>"><?= e(ucfirst($invoice['status'])); ?></span></td>
                        <td><?= format_currency((float) $invoice['total']); ?></td>
                    </tr>
                <?php endforeach; ?>
                <?php if (!$invoices): ?>
                    <tr><td colspan="6" class="panel-empty">No invoices to show.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</section>

<section id="tickets" class="panel">
    <div class="panel-header">
        <h2>Support tickets</h2>
        <p>Reply directly to clients.</p>
    </div>
    <div class="support-list">
        <?php foreach ($tickets as $ticket): ?>
            <article class="support-card">
                <header>
                    <div>
                        <strong><?= e($ticket['subject']); ?></strong>
                        <span class="support-meta">Opened by <?= e($ticket['client_name']); ?> · <?= e(format_datetime($ticket['created_at'])); ?></span>
                    </div>
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
                    <input type="hidden" name="action" value="reply_ticket_admin">
                    <input type="hidden" name="ticket_id" value="<?= (int) $ticket['id']; ?>">
                    <label>Status
                        <select name="status">
                            <option value="open"<?= $ticket['status'] === 'open' ? ' selected' : ''; ?>>Open</option>
                            <option value="awaiting_client"<?= $ticket['status'] === 'awaiting_client' ? ' selected' : ''; ?>>Awaiting client</option>
                            <option value="resolved"<?= $ticket['status'] === 'resolved' ? ' selected' : ''; ?>>Resolved</option>
                            <option value="closed"<?= $ticket['status'] === 'closed' ? ' selected' : ''; ?>>Closed</option>
                        </select>
                    </label>
                    <label>Reply
                        <textarea name="message" rows="3" required></textarea>
                    </label>
                    <button type="submit">Send reply</button>
                </form>
            </article>
        <?php endforeach; ?>
        <?php if (!$tickets): ?>
            <p class="panel-empty">No tickets in the queue.</p>
        <?php endif; ?>
    </div>
</section>

<section id="clients" class="panel">
    <div class="panel-header">
        <h2>Clients</h2>
        <p>Contact details at a glance.</p>
    </div>
    <div class="table-scroll">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Name</th>
                    <th>Email</th>
                    <th>Company</th>
                    <th>Joined</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($clients as $client): ?>
                    <tr>
                        <td><?= e($client['name']); ?></td>
                        <td><?= e($client['email']); ?></td>
                        <td><?= e($client['company']); ?></td>
                        <td><?= e(format_datetime($client['created_at'])); ?></td>
                    </tr>
                <?php endforeach; ?>
                <?php if (!$clients): ?>
                    <tr><td colspan="4" class="panel-empty">No clients yet.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</section>

<section id="automations" class="panel">
    <div class="panel-header">
        <h2>Email templates</h2>
        <p>Personalise automated communications.</p>
    </div>
    <div class="template-grid">
        <?php foreach ($templates as $template): ?>
            <form action="<?= e(url_for('dashboard')); ?>" method="post" class="template-card">
                <input type="hidden" name="action" value="save_template">
                <input type="hidden" name="template_id" value="<?= (int) $template['id']; ?>">
                <label>Name
                    <input type="text" name="name" value="<?= e($template['name']); ?>" required>
                </label>
                <label>Slug
                    <input type="text" name="slug" value="<?= e($template['slug']); ?>" required>
                </label>
                <label>Subject
                    <input type="text" name="subject" value="<?= e($template['subject']); ?>" required>
                </label>
                <label>Body
                    <textarea name="body" rows="6" required><?= e($template['body']); ?></textarea>
                </label>
                <div class="template-actions">
                    <button type="submit">Save</button>
                </div>
            </form>
            <form action="<?= e(url_for('dashboard')); ?>" method="post" class="template-delete" onsubmit="return confirm('Delete this template?');">
                <input type="hidden" name="action" value="delete_template">
                <input type="hidden" name="template_id" value="<?= (int) $template['id']; ?>">
                <button type="submit" class="danger">Remove</button>
            </form>
        <?php endforeach; ?>
        <form action="<?= e(url_for('dashboard')); ?>" method="post" class="template-card">
            <input type="hidden" name="action" value="save_template">
            <h3>New template</h3>
            <label>Name
                <input type="text" name="name" required>
            </label>
            <label>Slug
                <input type="text" name="slug" required>
            </label>
            <label>Subject
                <input type="text" name="subject" required>
            </label>
            <label>Body
                <textarea name="body" rows="6" required></textarea>
            </label>
            <button type="submit">Create template</button>
        </form>
    </div>
</section>

<section id="payments" class="panel">
    <div class="panel-header">
        <h2>Payments</h2>
        <p>Store provider credentials securely.</p>
    </div>
    <form action="<?= e(url_for('dashboard')); ?>" method="post" class="payment-form">
        <input type="hidden" name="action" value="update_payments">
        <label>Stripe publishable key
            <input type="text" name="stripe_publishable_key" value="<?= e(get_setting('stripe_publishable_key', '')); ?>">
        </label>
        <label>Stripe secret key
            <input type="text" name="stripe_secret_key" value="<?= e(get_setting('stripe_secret_key', '')); ?>">
        </label>
        <label>PayPal client ID
            <input type="text" name="paypal_client_id" value="<?= e(get_setting('paypal_client_id', '')); ?>">
        </label>
        <label>PayPal client secret
            <input type="text" name="paypal_client_secret" value="<?= e(get_setting('paypal_client_secret', '')); ?>">
        </label>
        <button type="submit">Save payment settings</button>
    </form>
</section>

<section id="administrators" class="panel">
    <div class="panel-header">
        <h2>Administrators</h2>
        <p>Create a new admin account quickly.</p>
    </div>
    <form action="<?= e(url_for('dashboard')); ?>" method="post" class="admin-form">
        <input type="hidden" name="action" value="create_admin_user">
        <label>Name
            <input type="text" name="name" required>
        </label>
        <label>Email
            <input type="email" name="email" required>
        </label>
        <label>Password
            <input type="password" name="password" minlength="8" required>
        </label>
        <button type="submit">Create admin</button>
    </form>
</section>
<?php
$content = ob_get_clean();
include __DIR__ . '/../templates/partials/dashboard_layout.php';
