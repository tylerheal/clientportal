<?php
$portalNav = [
    ['label' => 'Overview', 'href' => '#overview'],
    ['label' => 'Services', 'href' => '#services'],
    ['label' => 'Orders', 'href' => '#orders'],
    ['label' => 'Support', 'href' => '#support'],
];
$pageTitle = 'Client dashboard';
include __DIR__ . '/partials/header.php';
$activeServicesCount = count(array_filter($services, fn($service) => (int) $service['active'] === 1));
$openOrdersCount = count(array_filter($orders, fn($order) => $order['payment_status'] !== 'paid'));
$openTicketsCount = count(array_filter($tickets, fn($ticket) => !in_array($ticket['status'], ['resolved', 'closed'], true)));
?>

<section id="overview" class="portal-section portal-hero">
    <div class="grid gap-10 lg:grid-cols-[2fr,1.1fr]">
        <div class="flex flex-col gap-6">
            <div class="space-y-3">
                <p class="text-sm uppercase tracking-[0.4em] text-white/70">Dashboard</p>
                <h1 class="text-3xl font-semibold tracking-tight text-white sm:text-4xl">Welcome back, <?= e($user['name']); ?> 👋</h1>
                <p class="max-w-xl text-sm font-medium text-white/80">Your personalised hub to track services, monitor orders, and stay connected with our support team. Jump back into your projects or start something new in seconds.</p>
            </div>
            <div class="flex flex-wrap items-center gap-3">
                <a href="#services" class="btn-primary">Place a new order</a>
                <a href="#support" class="btn-secondary">Open support ticket</a>
            </div>
        </div>
        <div class="stat-grid">
            <div class="stat-card">
                <span>Active services</span>
                <strong><?= $activeServicesCount; ?></strong>
                <p class="text-xs text-white/70">Currently available to order.</p>
            </div>
            <div class="stat-card">
                <span>Outstanding orders</span>
                <strong><?= $openOrdersCount; ?></strong>
                <p class="text-xs text-white/70">Awaiting payment or fulfilment.</p>
            </div>
            <div class="stat-card">
                <span>Open tickets</span>
                <strong><?= $openTicketsCount; ?></strong>
                <p class="text-xs text-white/70">Our team will keep you updated.</p>
            </div>
        </div>
    </div>
</section>

<section id="services" class="portal-section">
    <div class="flex flex-col gap-4 md:flex-row md:items-end md:justify-between">
        <div>
            <p class="text-xs font-semibold uppercase tracking-[0.4em] text-slate-400">Services</p>
            <h2 class="mt-1 text-2xl font-semibold text-slate-900">Order professional services in a click</h2>
            <p class="mt-2 max-w-2xl text-sm text-slate-500">Each service is tailored to help you launch faster. Complete the quick form and our team will kick things off right away.</p>
        </div>
    </div>
    <div class="mt-8 portal-grid portal-grid--two">
        <?php foreach ($services as $service): ?>
            <article class="portal-card service-card">
                <header class="flex flex-col gap-4">
                    <div class="flex items-start justify-between gap-4">
                        <div class="flex items-center gap-3">
                            <?php $serviceInitial = strtoupper(function_exists('mb_substr') ? mb_substr($service['name'], 0, 1) : substr($service['name'], 0, 1)); ?>
                            <span class="service-icon"><?= e($serviceInitial); ?></span>
                            <div>
                                <h3 class="text-lg font-semibold text-slate-900"><?= e($service['name']); ?></h3>
                                <p class="mt-1 text-sm text-slate-500"><?= e($service['description']); ?></p>
                            </div>
                        </div>
                        <p class="text-xl font-semibold text-slate-900"><?= format_currency((float) $service['price']); ?></p>
                    </div>
                </header>
                <form action="dashboard.php" method="post" class="portal-form flex flex-1 flex-col gap-3">
                    <input type="hidden" name="action" value="create_order">
                    <input type="hidden" name="service_id" value="<?= (int) $service['id']; ?>">
                    <?php foreach (parse_form_schema($service['form_schema']) as $field): ?>
                        <label class="text-xs font-semibold uppercase tracking-[0.28em] text-slate-500">
                            <?= e($field['label']); ?>
                            <input type="<?= e($field['type']); ?>" name="custom[<?= e($field['name']); ?>]" <?= !empty($field['required']) ? 'required' : ''; ?> class="mt-2">
                        </label>
                    <?php endforeach; ?>
                    <label class="text-xs font-semibold uppercase tracking-[0.28em] text-slate-500">Payment method
                        <select name="payment_method" class="mt-2">
                            <option value="stripe">Stripe</option>
                            <option value="paypal">PayPal</option>
                            <option value="invoice">Invoice me</option>
                        </select>
                    </label>
                    <button type="submit" class="btn-primary mt-2 w-full justify-center">Submit order</button>
                </form>
            </article>
        <?php endforeach; ?>
    </div>
    <?php if (!$services): ?>
        <div class="mt-8 rounded-2xl border border-dashed border-slate-300 bg-slate-50/80 p-8 text-center text-sm text-slate-500">There are no active services right now. Check back soon!</div>
    <?php endif; ?>
</section>

<section id="orders" class="portal-section">
    <div class="flex flex-col gap-4 md:flex-row md:items-end md:justify-between">
        <div>
            <p class="text-xs font-semibold uppercase tracking-[0.4em] text-slate-400">Orders</p>
            <h2 class="mt-1 text-2xl font-semibold text-slate-900">Track every deliverable at a glance</h2>
            <p class="mt-2 max-w-2xl text-sm text-slate-500">Stay informed on payment status, timelines, and submitted briefs. Expand an order to revisit the information you shared with us.</p>
        </div>
    </div>
    <div class="mt-8 overflow-x-auto">
        <table class="portal-table text-sm">
            <thead>
                <tr>
                    <th>Order</th>
                    <th>Service</th>
                    <th>Amount</th>
                    <th>Payment</th>
                    <th>Placed</th>
                    <th>Details</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($orders as $order): ?>
                <?php $paymentStatusClass = 'status-pill status-' . preg_replace('/[^a-z0-9]+/', '-', strtolower($order['payment_status'])); ?>
                <tr>
                    <td class="font-semibold text-slate-900">#<?= (int) $order['id']; ?></td>
                    <td class="text-slate-700"><?= e($order['service_name']); ?></td>
                    <td class="font-semibold text-slate-900"><?= format_currency((float) $order['total_amount']); ?></td>
                    <td class="space-y-1">
                        <span class="<?= e($paymentStatusClass); ?>"><?= ucfirst($order['payment_status']); ?></span>
                        <p class="text-xs uppercase tracking-[0.2em] text-slate-400">via <?= e($order['payment_method']); ?></p>
                    </td>
                    <td class="text-xs text-slate-500"><?= format_datetime($order['created_at']); ?></td>
                    <td class="text-xs">
                        <?php if ($order['form_data']): ?>
                            <details>
                                <summary class="cursor-pointer font-semibold text-brand">View form</summary>
                                <pre class="mt-2 max-h-48 overflow-auto rounded-xl bg-slate-900/5 p-3 text-xs leading-relaxed text-slate-600"><?= e(json_encode(json_decode($order['form_data'], true), JSON_PRETTY_PRINT)); ?></pre>
                            </details>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$orders): ?>
                <tr>
                    <td colspan="6" class="py-10 text-center text-sm text-slate-500">You haven't placed any orders yet. Head to the services section to get started.</td>
                </tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</section>

<section id="support" class="portal-section">
    <div class="flex flex-col gap-4 md:flex-row md:items-end md:justify-between">
        <div>
            <p class="text-xs font-semibold uppercase tracking-[0.4em] text-slate-400">Support</p>
            <h2 class="mt-1 text-2xl font-semibold text-slate-900">Start a conversation with our team</h2>
            <p class="mt-2 max-w-2xl text-sm text-slate-500">We respond quickly and keep everything organised by ticket so it's easy to follow up and share updates.</p>
        </div>
    </div>
    <div class="mt-8 grid gap-8 lg:grid-cols-[minmax(0,2fr)_minmax(0,1fr)]">
        <div class="space-y-6">
            <?php foreach ($tickets as $ticket): ?>
                <?php $ticketStatusClass = 'status-pill status-' . preg_replace('/[^a-z0-9]+/', '-', strtolower($ticket['status'])); ?>
                <article class="portal-card space-y-5">
                    <header class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                        <div>
                            <h3 class="text-lg font-semibold text-slate-900"><?= e($ticket['subject']); ?></h3>
                            <p class="text-xs uppercase tracking-[0.28em] text-slate-400">Last updated <?= format_datetime($ticket['updated_at']); ?></p>
                        </div>
                        <span class="<?= e($ticketStatusClass); ?>"><?= ucfirst($ticket['status']); ?></span>
                    </header>
                    <div class="ticket-thread text-sm text-slate-700">
                        <?php foreach ($messagesByTicket[$ticket['id']] ?? [] as $message): ?>
                            <div class="ticket-message">
                                <header><?= e($message['name']); ?> · <?= format_datetime($message['created_at']); ?></header>
                                <p class="whitespace-pre-line leading-relaxed text-slate-700"><?= e($message['message']); ?></p>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <form action="dashboard.php" method="post" class="portal-form space-y-3">
                        <input type="hidden" name="action" value="reply_ticket_client">
                        <input type="hidden" name="ticket_id" value="<?= (int) $ticket['id']; ?>">
                        <textarea name="message" rows="3" required placeholder="Reply to this ticket..."></textarea>
                        <button type="submit" class="btn-primary w-full justify-center text-sm">Send reply</button>
                    </form>
                </article>
            <?php endforeach; ?>
            <?php if (!$tickets): ?>
                <div class="portal-card p-10 text-center text-sm text-slate-500">No tickets yet. Use the form to start a conversation with our success team.</div>
            <?php endif; ?>
        </div>
        <div class="portal-aside-card">
            <h3>Open a ticket</h3>
            <p class="mt-2 text-sm text-slate-600">Share a quick summary and our specialists will be in touch. You'll receive email updates for every reply.</p>
            <form action="dashboard.php" method="post" class="portal-form mt-5 space-y-4">
                <input type="hidden" name="action" value="create_ticket">
                <label class="text-xs font-semibold uppercase tracking-[0.28em] text-slate-500">Subject
                    <input type="text" name="subject" required class="mt-2">
                </label>
                <label class="text-xs font-semibold uppercase tracking-[0.28em] text-slate-500">Message
                    <textarea name="message" rows="4" required class="mt-2"></textarea>
                </label>
                <button type="submit" class="btn-primary w-full justify-center">Submit ticket</button>
            </form>
        </div>
    </div>
</section>

<?php include __DIR__ . '/partials/footer.php'; ?>
