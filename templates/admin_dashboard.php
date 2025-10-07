<?php
$portalNav = [
    ['label' => 'Overview', 'href' => '#overview'],
    ['label' => 'Services', 'href' => '#services'],
    ['label' => 'Orders', 'href' => '#orders'],
    ['label' => 'Tickets', 'href' => '#tickets'],
    ['label' => 'Clients', 'href' => '#clients'],
    ['label' => 'Branding', 'href' => '#branding'],
    ['label' => 'Email', 'href' => '#emails'],
    ['label' => 'Payments', 'href' => '#payments'],
];
$pageTitle = 'Admin dashboard';
include __DIR__ . '/partials/header.php';

$activeServices = count(array_filter($services, fn($service) => (int) $service['active'] === 1));
$clientCount = count($clients);
$openTickets = count(array_filter($tickets, fn($ticket) => !in_array($ticket['status'], ['resolved', 'closed'], true)));
$pendingInvoices = count(array_filter($orders, fn($order) => in_array($order['payment_status'], ['pending', 'overdue'], true)));
$totalRevenue = array_sum(array_map(function ($order) {
    return $order['payment_status'] === 'paid' ? (float) $order['total_amount'] : 0.0;
}, $orders));
?>

<section id="overview" class="portal-section portal-hero">
    <div class="grid gap-10 lg:grid-cols-[2fr,1.1fr]">
        <div class="flex flex-col gap-6">
            <div class="space-y-3">
                <p class="text-sm uppercase tracking-[0.4em] text-white/70">Admin control centre</p>
                <h1 class="text-3xl font-semibold tracking-tight text-white sm:text-4xl">Run your client portal with confidence</h1>
                <p class="max-w-2xl text-sm font-medium text-white/80">Monitor performance, configure services, and keep conversations flowing. Paid revenue currently sits at <span class="font-semibold text-white"><?= format_currency($totalRevenue); ?></span>.</p>
            </div>
            <div class="flex flex-wrap items-center gap-3">
                <a href="#services" class="btn-primary">Create a service</a>
                <a href="#emails" class="btn-secondary">Manage automations</a>
            </div>
        </div>
        <div class="stat-grid">
            <div class="stat-card">
                <span>Active services</span>
                <strong><?= $activeServices; ?></strong>
                <p class="text-xs text-white/70">Visible to clients right now.</p>
            </div>
            <div class="stat-card">
                <span>Total clients</span>
                <strong><?= $clientCount; ?></strong>
                <p class="text-xs text-white/70">Signed up to the portal.</p>
            </div>
            <div class="stat-card">
                <span>Open tickets</span>
                <strong><?= $openTickets; ?></strong>
                <p class="text-xs text-white/70">Needing your response.</p>
            </div>
            <div class="stat-card">
                <span>Pending invoices</span>
                <strong><?= $pendingInvoices; ?></strong>
                <p class="text-xs text-white/70">Follow up for cash flow.</p>
            </div>
        </div>
    </div>
</section>

<section id="services" class="portal-section">
    <div class="flex flex-col gap-4 md:flex-row md:items-end md:justify-between">
        <div>
            <p class="text-xs font-semibold uppercase tracking-[0.4em] text-slate-400">Services</p>
            <h2 class="mt-1 text-2xl font-semibold text-slate-900">Shape the catalogue your clients see</h2>
            <p class="mt-2 max-w-2xl text-sm text-slate-500">Update pricing, tweak intake forms, and toggle availability as your offers evolve.</p>
        </div>
    </div>
    <div class="mt-10 grid gap-8 lg:grid-cols-[minmax(0,2.15fr)_minmax(0,1fr)]">
        <div class="overflow-x-auto">
            <table class="portal-table text-sm">
                <thead>
                    <tr>
                        <th>Service</th>
                        <th>Price</th>
                        <th>Status</th>
                        <th>Form fields</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($services as $service): ?>
                    <?php $formFields = parse_form_schema($service['form_schema']); ?>
                    <tr>
                        <td>
                            <div class="font-semibold text-slate-900"><?= e($service['name']); ?></div>
                            <p class="mt-1 text-xs text-slate-500"><?= e($service['description']); ?></p>
                        </td>
                        <td class="font-semibold text-slate-900"><?= format_currency((float) $service['price']); ?></td>
                        <td>
                            <?php $statusClass = (int) $service['active'] === 1 ? 'status-pill status-active' : 'status-pill status-hidden'; ?>
                            <span class="<?= e($statusClass); ?>"><?= (int) $service['active'] === 1 ? 'Active' : 'Hidden'; ?></span>
                        </td>
                        <td class="text-xs text-slate-500">
                            <div class="flex flex-wrap gap-2">
                                <?php foreach ($formFields as $field): ?>
                                    <span class="chip">
                                        <span class="chip-label"><?= e($field['label']); ?></span>
                                        <span class="chip-meta"><?= e($field['type']); ?><?= !empty($field['required']) ? ' · required' : ''; ?></span>
                                    </span>
                                <?php endforeach; ?>
                                <?php if (!$formFields): ?>
                                    <span class="chip chip-muted">No custom fields</span>
                                <?php endif; ?>
                            </div>
                        </td>
                        <td class="text-right text-sm">
                            <details class="inline-block text-left">
                                <summary class="cursor-pointer font-semibold text-brand">Edit</summary>
                                <form action="dashboard.php" method="post" class="portal-form mt-4 space-y-3">
                                    <input type="hidden" name="action" value="update_service">
                                    <input type="hidden" name="service_id" value="<?= (int) $service['id']; ?>">
                                    <label class="text-xs font-semibold uppercase tracking-[0.28em] text-slate-500">Name
                                        <input type="text" name="name" value="<?= e($service['name']); ?>" class="mt-2">
                                    </label>
                                    <label class="text-xs font-semibold uppercase tracking-[0.28em] text-slate-500">Description
                                        <textarea name="description" rows="3" class="mt-2"><?= e($service['description']); ?></textarea>
                                    </label>
                                    <label class="text-xs font-semibold uppercase tracking-[0.28em] text-slate-500">Price
                                        <input type="number" min="0" step="0.01" name="price" value="<?= e($service['price']); ?>" class="mt-2">
                                    </label>
                                    <label class="text-xs font-semibold uppercase tracking-[0.28em] text-slate-500">Form builder (Label|name|type|required per line)
                                        <textarea name="form_builder" rows="4" class="mt-2"><?php foreach ($formFields as $field): ?><?= e($field['label']); ?>|<?= e($field['name']); ?>|<?= e($field['type']); ?>|<?= $field['required'] ? 'required' : ''; ?>
<?php endforeach; ?></textarea>
                                    </label>
                                    <label class="flex items-center gap-2 text-xs font-semibold uppercase tracking-[0.28em] text-slate-500">
                                        <input type="checkbox" name="active" value="1" <?= (int) $service['active'] === 1 ? 'checked' : ''; ?>> Active
                                    </label>
                                    <div class="flex items-center justify-between gap-3">
                                        <button type="submit" class="btn-primary flex-1 justify-center text-sm">Save</button>
                                        <button type="submit" form="delete-service-<?= (int) $service['id']; ?>" class="btn-danger text-xs font-semibold" onclick="return confirm('Delete this service?');">Delete</button>
                                    </div>
                                </form>
                            </details>
                            <form action="dashboard.php" method="post" id="delete-service-<?= (int) $service['id']; ?>" class="hidden">
                                <input type="hidden" name="action" value="delete_service">
                                <input type="hidden" name="service_id" value="<?= (int) $service['id']; ?>">
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <?php if (!$services): ?>
                    <tr>
                        <td colspan="5" class="py-10 text-center text-sm text-slate-500">No services yet. Use the form to create your first offering.</td>
                    </tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
        <div class="portal-aside-card">
            <h3>Create a service</h3>
            <p class="mt-2 text-sm text-slate-600">Launch new offers with guided pricing and intake fields.</p>
            <form action="dashboard.php" method="post" class="portal-form mt-5 space-y-4">
                <input type="hidden" name="action" value="create_service">
                <label class="text-xs font-semibold uppercase tracking-[0.28em] text-slate-500">Name
                    <input type="text" name="name" required class="mt-2">
                </label>
                <label class="text-xs font-semibold uppercase tracking-[0.28em] text-slate-500">Description
                    <textarea name="description" rows="3" class="mt-2"></textarea>
                </label>
                <label class="text-xs font-semibold uppercase tracking-[0.28em] text-slate-500">Price
                    <input type="number" step="0.01" min="0" name="price" value="0" class="mt-2">
                </label>
                <label class="text-xs font-semibold uppercase tracking-[0.28em] text-slate-500">Form builder (Label|name|type|required per line)
                    <textarea name="form_builder" rows="4" class="mt-2" placeholder="Website URL|website|url|required
Campaign goal|goal|text|"></textarea>
                </label>
                <label class="flex items-center gap-2 text-xs font-semibold uppercase tracking-[0.28em] text-slate-500">
                    <input type="checkbox" name="active" value="1" checked> Visible to clients
                </label>
                <button type="submit" class="btn-primary w-full justify-center">Create service</button>
            </form>
        </div>
    </div>
</section>

<section id="orders" class="portal-section">
    <div class="flex flex-col gap-4 md:flex-row md:items-end md:justify-between">
        <div>
            <p class="text-xs font-semibold uppercase tracking-[0.4em] text-slate-400">Orders</p>
            <h2 class="mt-1 text-2xl font-semibold text-slate-900">Stay on top of fulfilment and payments</h2>
            <p class="mt-2 max-w-2xl text-sm text-slate-500">Update payment statuses, add transaction references, and review submitted briefs in one view.</p>
        </div>
    </div>
    <div class="mt-8 overflow-x-auto">
        <table class="portal-table text-sm">
            <thead>
                <tr>
                    <th>Order</th>
                    <th>Client</th>
                    <th>Service</th>
                    <th>Payment</th>
                    <th>Placed</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($orders as $order): ?>
                <?php $statusClass = 'status-pill status-' . preg_replace('/[^a-z0-9]+/', '-', strtolower($order['payment_status'])); ?>
                <tr>
                    <td class="font-semibold text-slate-900">#<?= (int) $order['id']; ?></td>
                    <td>
                        <p class="font-semibold text-slate-900"><?= e($order['client_name']); ?></p>
                        <p class="text-xs uppercase tracking-[0.28em] text-slate-400"><?= e($order['payment_method']); ?></p>
                    </td>
                    <td>
                        <p class="font-semibold text-slate-900"><?= e($order['service_name']); ?></p>
                        <p class="text-xs text-slate-500">Total <?= format_currency((float) $order['total_amount']); ?></p>
                    </td>
                    <td>
                        <span class="<?= e($statusClass); ?>"><?= ucfirst($order['payment_status']); ?></span>
                        <form action="dashboard.php" method="post" class="portal-form mt-3 space-y-2 text-xs">
                            <input type="hidden" name="action" value="update_order_status">
                            <input type="hidden" name="order_id" value="<?= (int) $order['id']; ?>">
                            <label class="text-[0.65rem] font-semibold uppercase tracking-[0.28em] text-slate-500">Status
                                <select name="payment_status" class="mt-2">
                                    <?php foreach (['pending','processing','paid','overdue','refunded'] as $status): ?>
                                        <option value="<?= $status; ?>" <?= $order['payment_status'] === $status ? 'selected' : ''; ?>><?= ucfirst($status); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </label>
                            <label class="text-[0.65rem] font-semibold uppercase tracking-[0.28em] text-slate-500">Reference
                                <input type="text" name="payment_reference" value="<?= e($order['payment_reference']); ?>" placeholder="Transaction / invoice" class="mt-2">
                            </label>
                            <button type="submit" class="btn-primary w-full justify-center text-xs">Update</button>
                        </form>
                    </td>
                    <td class="text-xs text-slate-500"><?= format_datetime($order['created_at']); ?></td>
                    <td class="text-xs text-slate-500">
                        <?php if ($order['form_data']): ?>
                            <details>
                                <summary class="cursor-pointer font-semibold text-brand">Order form</summary>
                                <pre class="mt-2 max-h-48 overflow-auto rounded-xl bg-slate-900/5 p-3 text-xs leading-relaxed text-slate-600"><?= e(json_encode(json_decode($order['form_data'], true), JSON_PRETTY_PRINT)); ?></pre>
                            </details>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$orders): ?>
                <tr>
                    <td colspan="6" class="py-10 text-center text-sm text-slate-500">No orders yet.</td>
                </tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</section>

<section id="tickets" class="portal-section">
    <div class="flex flex-col gap-4 md:flex-row md:items-end md:justify-between">
        <div>
            <p class="text-xs font-semibold uppercase tracking-[0.4em] text-slate-400">Support</p>
            <h2 class="mt-1 text-2xl font-semibold text-slate-900">Reply with context and keep momentum</h2>
            <p class="mt-2 max-w-2xl text-sm text-slate-500">Each ticket aggregates every message so you can respond with clarity and update status in one go.</p>
        </div>
    </div>
    <div class="mt-8 portal-grid portal-grid--two">
        <?php foreach ($tickets as $ticket): ?>
            <?php $ticketStatusClass = 'status-pill status-' . preg_replace('/[^a-z0-9]+/', '-', strtolower($ticket['status'])); ?>
            <article class="portal-card space-y-5">
                <header class="flex flex-col gap-3 lg:flex-row lg:items-start lg:justify-between">
                    <div>
                        <h3 class="text-lg font-semibold text-slate-900"><?= e($ticket['subject']); ?></h3>
                        <p class="text-xs uppercase tracking-[0.28em] text-slate-400">Opened by <?= e($ticket['client_name']); ?> · <?= format_datetime($ticket['created_at']); ?></p>
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
                <form action="dashboard.php" method="post" class="portal-form space-y-4">
                    <input type="hidden" name="action" value="reply_ticket_admin">
                    <input type="hidden" name="ticket_id" value="<?= (int) $ticket['id']; ?>">
                    <textarea name="message" rows="3" required placeholder="Write a response..."></textarea>
                    <label class="text-xs font-semibold uppercase tracking-[0.28em] text-slate-500">Status
                        <select name="status" class="mt-2">
                            <?php foreach (['open','waiting','resolved','closed'] as $status): ?>
                                <option value="<?= $status; ?>" <?= $ticket['status'] === $status ? 'selected' : ''; ?>><?= ucfirst($status); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                    <button type="submit" class="btn-primary w-full justify-center text-sm">Send reply</button>
                </form>
            </article>
        <?php endforeach; ?>
        <?php if (!$tickets): ?>
            <div class="portal-card p-10 text-center text-sm text-slate-500">You're all caught up. No tickets to show.</div>
        <?php endif; ?>
    </div>
</section>

<section id="clients" class="portal-section">
    <div class="flex flex-col gap-4 md:flex-row md:items-end md:justify-between">
        <div>
            <p class="text-xs font-semibold uppercase tracking-[0.4em] text-slate-400">Clients</p>
            <h2 class="mt-1 text-2xl font-semibold text-slate-900">Keep track of who you're serving</h2>
            <p class="mt-2 max-w-2xl text-sm text-slate-500">Quickly reference contact details and onboarding dates.</p>
        </div>
    </div>
    <div class="mt-8 overflow-x-auto">
        <table class="portal-table text-sm">
            <thead>
                <tr>
                    <th>Client</th>
                    <th>Company</th>
                    <th>Joined</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($clients as $client): ?>
                <tr>
                    <td>
                        <p class="font-semibold text-slate-900"><?= e($client['name']); ?></p>
                        <p class="text-xs text-slate-500"><?= e($client['email']); ?></p>
                    </td>
                    <td class="text-slate-600"><?= e($client['company']); ?></td>
                    <td class="text-xs text-slate-500"><?= format_datetime($client['created_at']); ?></td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$clients): ?>
                <tr>
                    <td colspan="3" class="py-10 text-center text-sm text-slate-500">No clients yet.</td>
                </tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</section>

<section id="branding" class="portal-section">
    <div class="flex flex-col gap-4 md:flex-row md:items-end md:justify-between">
        <div>
            <p class="text-xs font-semibold uppercase tracking-[0.4em] text-slate-400">Branding</p>
            <h2 class="mt-1 text-2xl font-semibold text-slate-900">Tune the look and feel</h2>
            <p class="mt-2 max-w-2xl text-sm text-slate-500">Update your brand presence so every client touchpoint feels on-brand.</p>
        </div>
    </div>
    <form action="dashboard.php" method="post" class="portal-form mt-8 grid gap-4 sm:grid-cols-2">
        <input type="hidden" name="action" value="update_settings">
        <label class="text-xs font-semibold uppercase tracking-[0.28em] text-slate-500">Company name
            <input type="text" name="company_name" value="<?= e(get_setting('company_name', 'Service Portal')); ?>" class="mt-2">
        </label>
        <label class="text-xs font-semibold uppercase tracking-[0.28em] text-slate-500">Logo URL
            <input type="url" name="brand_logo_url" value="<?= e(get_setting('brand_logo_url', '')); ?>" class="mt-2">
        </label>
        <label class="text-xs font-semibold uppercase tracking-[0.28em] text-slate-500">Primary colour
            <input type="color" name="brand_primary_color" value="<?= e(get_setting('brand_primary_color', '#3b82f6')); ?>" class="mt-2 h-12">
        </label>
        <label class="text-xs font-semibold uppercase tracking-[0.28em] text-slate-500">Font family
            <input type="text" name="brand_font_family" value="<?= e(get_setting('brand_font_family', 'Inter, sans-serif')); ?>" class="mt-2">
        </label>
        <label class="text-xs font-semibold uppercase tracking-[0.28em] text-slate-500">Support email
            <input type="email" name="support_email" value="<?= e(get_setting('support_email', 'support@example.com')); ?>" class="mt-2">
        </label>
        <div class="sm:col-span-2 flex justify-end">
            <button type="submit" class="btn-primary px-6">Save branding</button>
        </div>
    </form>
</section>

<section id="emails" class="portal-section">
    <div class="flex flex-col gap-4 md:flex-row md:items-end md:justify-between">
        <div>
            <p class="text-xs font-semibold uppercase tracking-[0.4em] text-slate-400">Email templates</p>
            <h2 class="mt-1 text-2xl font-semibold text-slate-900">Craft messages that sound like you</h2>
            <p class="mt-2 max-w-2xl text-sm text-slate-500">Customise automatic notifications so every update feels personal.</p>
        </div>
    </div>
    <form action="dashboard.php" method="post" class="portal-form mt-8 space-y-4">
        <input type="hidden" name="action" value="update_email_templates">
        <label class="text-xs font-semibold uppercase tracking-[0.28em] text-slate-500">Order confirmation template
            <textarea name="email_template_order" rows="5" class="mt-2"><?= e(get_setting('email_template_order')); ?></textarea>
        </label>
        <label class="text-xs font-semibold uppercase tracking-[0.28em] text-slate-500">Ticket reply template
            <textarea name="email_template_ticket_reply" rows="5" class="mt-2"><?= e(get_setting('email_template_ticket_reply')); ?></textarea>
        </label>
        <button type="submit" class="btn-primary px-6">Save templates</button>
    </form>
</section>

<section id="payments" class="portal-section">
    <div class="flex flex-col gap-4 md:flex-row md:items-end md:justify-between">
        <div>
            <p class="text-xs font-semibold uppercase tracking-[0.4em] text-slate-400">Payments</p>
            <h2 class="mt-1 text-2xl font-semibold text-slate-900">Connect Stripe and PayPal once</h2>
            <p class="mt-2 max-w-2xl text-sm text-slate-500">Store credentials securely so you can generate invoices and follow up automatically.</p>
        </div>
    </div>
    <form action="dashboard.php" method="post" class="portal-form mt-8 grid gap-4 sm:grid-cols-2">
        <input type="hidden" name="action" value="update_payments">
        <label class="text-xs font-semibold uppercase tracking-[0.28em] text-slate-500">Stripe publishable key
            <input type="text" name="stripe_publishable_key" value="<?= e(get_setting('stripe_publishable_key', '')); ?>" class="mt-2">
        </label>
        <label class="text-xs font-semibold uppercase tracking-[0.28em] text-slate-500">Stripe secret key
            <input type="text" name="stripe_secret_key" value="<?= e(get_setting('stripe_secret_key', '')); ?>" class="mt-2">
        </label>
        <label class="text-xs font-semibold uppercase tracking-[0.28em] text-slate-500">PayPal client ID
            <input type="text" name="paypal_client_id" value="<?= e(get_setting('paypal_client_id', '')); ?>" class="mt-2">
        </label>
        <label class="text-xs font-semibold uppercase tracking-[0.28em] text-slate-500">PayPal client secret
            <input type="text" name="paypal_client_secret" value="<?= e(get_setting('paypal_client_secret', '')); ?>" class="mt-2">
        </label>
        <div class="sm:col-span-2 flex justify-end">
            <button type="submit" class="btn-primary px-6">Save payment settings</button>
        </div>
    </form>
    <p class="mt-4 text-xs text-slate-500">Tip: Use Stripe Checkout or PayPal Smart Buttons by embedding generated payment links into your service workflows. Update the payment status above as money arrives.</p>
</section>

<?php include __DIR__ . '/partials/footer.php'; ?>
