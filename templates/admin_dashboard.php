<?php include __DIR__ . '/partials/header.php'; ?>
<div class="grid gap-8">
    <section class="rounded-lg bg-white p-6 shadow">
        <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
            <h1 class="text-2xl font-semibold text-slate-900">Admin overview</h1>
            <p class="text-sm text-slate-500">Quick insights across your portal.</p>
        </div>
        <div class="mt-6 grid gap-4 sm:grid-cols-4">
            <div class="rounded border border-slate-200 p-4">
                <p class="text-xs uppercase text-slate-500">Active services</p>
                <p class="mt-2 text-3xl font-semibold text-slate-900"><?= count(array_filter($services, fn($s) => (int) $s['active'] === 1)); ?></p>
            </div>
            <div class="rounded border border-slate-200 p-4">
                <p class="text-xs uppercase text-slate-500">Clients</p>
                <p class="mt-2 text-3xl font-semibold text-slate-900"><?= count($clients); ?></p>
            </div>
            <div class="rounded border border-slate-200 p-4">
                <p class="text-xs uppercase text-slate-500">Open tickets</p>
                <p class="mt-2 text-3xl font-semibold text-slate-900"><?= count(array_filter($tickets, fn($t) => $t['status'] === 'open')); ?></p>
            </div>
            <div class="rounded border border-slate-200 p-4">
                <p class="text-xs uppercase text-slate-500">Pending payments</p>
                <p class="mt-2 text-3xl font-semibold text-slate-900"><?= count(array_filter($orders, fn($o) => $o['payment_status'] === 'pending')); ?></p>
            </div>
        </div>
    </section>

    <section class="rounded-lg bg-white p-6 shadow">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h2 class="text-xl font-semibold text-slate-900">Services</h2>
                <p class="text-sm text-slate-500">Create and manage client-facing services.</p>
            </div>
        </div>
        <div class="mt-6 grid gap-6 lg:grid-cols-[2fr,1fr]">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-200 text-sm">
                    <thead class="bg-slate-50 text-left text-xs font-semibold uppercase text-slate-500">
                        <tr>
                            <th class="px-4 py-3">Service</th>
                            <th class="px-4 py-3">Price</th>
                            <th class="px-4 py-3">Status</th>
                            <th class="px-4 py-3">Form fields</th>
                            <th class="px-4 py-3"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-200">
                    <?php foreach ($services as $service): ?>
                        <tr>
                            <td class="px-4 py-3">
                                <p class="font-medium text-slate-900"><?= e($service['name']); ?></p>
                                <p class="text-sm text-slate-500"><?= e($service['description']); ?></p>
                            </td>
                            <td class="px-4 py-3"><?= format_currency((float) $service['price']); ?></td>
                            <td class="px-4 py-3">
                                <?php if ((int) $service['active'] === 1): ?>
                                    <span class="inline-flex rounded-full bg-green-100 px-2 py-1 text-xs font-semibold text-green-700">Active</span>
                                <?php else: ?>
                                    <span class="inline-flex rounded-full bg-slate-200 px-2 py-1 text-xs font-semibold text-slate-600">Hidden</span>
                                <?php endif; ?>
                            </td>
                            <td class="px-4 py-3 text-xs text-slate-500">
                                <?php foreach (parse_form_schema($service['form_schema']) as $field): ?>
                                    <span class="mr-2 inline-flex items-center gap-1 rounded-full bg-slate-100 px-2 py-1">
                                        <span class="font-medium text-slate-700"><?= e($field['label']); ?></span>
                                        <span><?= e($field['type']); ?></span>
                                    </span>
                                <?php endforeach; ?>
                            </td>
                            <td class="px-4 py-3 text-right text-xs">
                                <details class="inline-block text-left">
                                    <summary class="cursor-pointer text-brand">Edit</summary>
                                    <form action="dashboard.php" method="post" class="mt-3 space-y-3 rounded border border-slate-200 bg-slate-50 p-3">
                                        <input type="hidden" name="action" value="update_service">
                                        <input type="hidden" name="service_id" value="<?= (int) $service['id']; ?>">
                                        <label class="block text-xs font-semibold">Name
                                            <input type="text" name="name" value="<?= e($service['name']); ?>" class="mt-1 w-full rounded border border-slate-300 px-2 py-1">
                                        </label>
                                        <label class="block text-xs font-semibold">Description
                                            <textarea name="description" rows="3" class="mt-1 w-full rounded border border-slate-300 px-2 py-1"><?= e($service['description']); ?></textarea>
                                        </label>
                                        <label class="block text-xs font-semibold">Price
                                            <input type="number" min="0" step="0.01" name="price" value="<?= e($service['price']); ?>" class="mt-1 w-full rounded border border-slate-300 px-2 py-1">
                                        </label>
                                        <label class="block text-xs font-semibold">Form builder (Label|name|type|required)
                                            <textarea name="form_builder" rows="4" class="mt-1 w-full rounded border border-slate-300 px-2 py-1"><?php foreach (parse_form_schema($service['form_schema']) as $field): ?><?= e($field['label']); ?>|<?= e($field['name']); ?>|<?= e($field['type']); ?>|<?= $field['required'] ? 'required' : ''; ?>
<?php endforeach; ?></textarea>
                                        </label>
                                        <label class="inline-flex items-center gap-2 text-xs font-semibold">
                                            <input type="checkbox" name="active" value="1" <?= (int) $service['active'] === 1 ? 'checked' : ''; ?>> Active
                                        </label>
                                        <div class="flex justify-between">
                                            <button type="submit" class="rounded bg-brand px-3 py-1 text-xs font-semibold text-white">Save</button>
                                        </div>
                                    </form>
                                    <form action="dashboard.php" method="post" class="mt-2 text-right">
                                        <input type="hidden" name="action" value="delete_service">
                                        <input type="hidden" name="service_id" value="<?= (int) $service['id']; ?>">
                                        <button type="submit" class="text-xs text-red-500 hover:text-red-700" onclick="return confirm('Delete this service?');">Delete</button>
                                    </form>
                                </details>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (!$services): ?>
                        <tr>
                            <td colspan="5" class="px-4 py-6 text-center text-sm text-slate-500">No services yet. Use the form to create your first service.</td>
                        </tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
            <div>
                <h3 class="text-sm font-semibold uppercase tracking-wide text-slate-500">Add service</h3>
                <form action="dashboard.php" method="post" class="mt-3 space-y-4 rounded border border-dashed border-brand bg-slate-50 p-4">
                    <input type="hidden" name="action" value="create_service">
                    <label class="block text-sm font-medium">Name
                        <input type="text" name="name" required class="mt-1 w-full rounded border border-slate-300 px-3 py-2">
                    </label>
                    <label class="block text-sm font-medium">Description
                        <textarea name="description" rows="3" class="mt-1 w-full rounded border border-slate-300 px-3 py-2"></textarea>
                    </label>
                    <label class="block text-sm font-medium">Price
                        <input type="number" step="0.01" min="0" name="price" value="0" class="mt-1 w-full rounded border border-slate-300 px-3 py-2">
                    </label>
                    <label class="block text-sm font-medium">Form builder (Label|name|type|required per line)
                        <textarea name="form_builder" rows="4" placeholder="Website URL|website|url|required
Campaign goal|goal|text|" class="mt-1 w-full rounded border border-slate-300 px-3 py-2"></textarea>
                    </label>
                    <label class="inline-flex items-center gap-2 text-sm">
                        <input type="checkbox" name="active" value="1" checked> Visible to clients
                    </label>
                    <button type="submit" class="w-full rounded bg-brand px-4 py-2 font-medium text-white">Create service</button>
                </form>
            </div>
        </div>
    </section>

    <section class="rounded-lg bg-white p-6 shadow">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h2 class="text-xl font-semibold text-slate-900">Orders</h2>
                <p class="text-sm text-slate-500">Review client orders and manage payment statuses.</p>
            </div>
        </div>
        <div class="mt-6 overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-200 text-sm">
                <thead class="bg-slate-50 text-left text-xs font-semibold uppercase text-slate-500">
                    <tr>
                        <th class="px-4 py-3">Order</th>
                        <th class="px-4 py-3">Client</th>
                        <th class="px-4 py-3">Service</th>
                        <th class="px-4 py-3">Payment</th>
                        <th class="px-4 py-3">Placed</th>
                        <th class="px-4 py-3"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-200">
                <?php foreach ($orders as $order): ?>
                    <tr>
                        <td class="px-4 py-3 font-semibold text-slate-900">#<?= (int) $order['id']; ?></td>
                        <td class="px-4 py-3">
                            <p class="font-medium text-slate-900"><?= e($order['client_name']); ?></p>
                            <p class="text-xs text-slate-500"><?= e($order['payment_method']); ?></p>
                        </td>
                        <td class="px-4 py-3">
                            <p class="font-medium text-slate-900"><?= e($order['service_name']); ?></p>
                            <p class="text-xs text-slate-500">Total <?= format_currency((float) $order['total_amount']); ?></p>
                        </td>
                        <td class="px-4 py-3">
                            <form action="dashboard.php" method="post" class="space-y-2">
                                <input type="hidden" name="action" value="update_order_status">
                                <input type="hidden" name="order_id" value="<?= (int) $order['id']; ?>">
                                <select name="payment_status" class="w-full rounded border border-slate-300 px-2 py-1 text-xs">
                                    <?php foreach (['pending','processing','paid','overdue','refunded'] as $status): ?>
                                        <option value="<?= $status; ?>" <?= $order['payment_status'] === $status ? 'selected' : ''; ?>><?= ucfirst($status); ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <input type="text" name="payment_reference" value="<?= e($order['payment_reference']); ?>" placeholder="Reference or transaction" class="w-full rounded border border-slate-300 px-2 py-1 text-xs">
                                <button type="submit" class="w-full rounded bg-slate-900 px-2 py-1 text-xs font-semibold text-white">Update</button>
                            </form>
                        </td>
                        <td class="px-4 py-3 text-xs text-slate-500"><?= format_datetime($order['created_at']); ?></td>
                        <td class="px-4 py-3 text-xs text-slate-500">
                            <?php if ($order['form_data']): ?>
                                <details>
                                    <summary class="cursor-pointer text-brand">Order form</summary>
                                    <pre class="mt-2 rounded bg-slate-100 p-2 text-xs"><?= e(json_encode(json_decode($order['form_data'], true), JSON_PRETTY_PRINT)); ?></pre>
                                </details>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <?php if (!$orders): ?>
                    <tr>
                        <td colspan="6" class="px-4 py-6 text-center text-sm text-slate-500">No orders yet.</td>
                    </tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </section>

    <section class="rounded-lg bg-white p-6 shadow">
        <h2 class="text-xl font-semibold text-slate-900">Support tickets</h2>
        <p class="text-sm text-slate-500">Respond to clients and manage ticket statuses.</p>
        <div class="mt-6 grid gap-6 lg:grid-cols-2">
            <?php foreach ($tickets as $ticket): ?>
                <article class="rounded border border-slate-200 p-4">
                    <header class="flex items-start justify-between gap-3">
                        <div>
                            <h3 class="text-lg font-semibold text-slate-900"><?= e($ticket['subject']); ?></h3>
                            <p class="text-xs text-slate-500">Opened by <?= e($ticket['client_name']); ?> · <?= format_datetime($ticket['created_at']); ?></p>
                        </div>
                        <span class="rounded-full border border-brand px-2 py-1 text-xs font-semibold text-brand"><?= ucfirst($ticket['status']); ?></span>
                    </header>
                    <div class="mt-4 space-y-4 text-sm">
                        <?php foreach ($messagesByTicket[$ticket['id']] ?? [] as $message): ?>
                            <div class="rounded border border-slate-100 bg-slate-50 p-3">
                                <p class="text-xs text-slate-500"><?= e($message['name']); ?> · <?= format_datetime($message['created_at']); ?></p>
                                <p class="mt-2 whitespace-pre-line text-slate-700"><?= e($message['message']); ?></p>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <form action="dashboard.php" method="post" class="mt-4 space-y-3">
                        <input type="hidden" name="action" value="reply_ticket_admin">
                        <input type="hidden" name="ticket_id" value="<?= (int) $ticket['id']; ?>">
                        <textarea name="message" rows="3" required placeholder="Write a response..." class="w-full rounded border border-slate-300 px-3 py-2"></textarea>
                        <div class="flex items-center justify-between">
                            <select name="status" class="rounded border border-slate-300 px-3 py-2 text-sm">
                                <?php foreach (['open','waiting','resolved','closed'] as $status): ?>
                                    <option value="<?= $status; ?>" <?= $ticket['status'] === $status ? 'selected' : ''; ?>><?= ucfirst($status); ?></option>
                                <?php endforeach; ?>
                            </select>
                            <button type="submit" class="rounded bg-brand px-4 py-2 text-sm font-semibold text-white">Send reply</button>
                        </div>
                    </form>
                </article>
            <?php endforeach; ?>
            <?php if (!$tickets): ?>
                <p class="text-sm text-slate-500">You're all caught up. No tickets to show.</p>
            <?php endif; ?>
        </div>
    </section>

    <section class="rounded-lg bg-white p-6 shadow">
        <h2 class="text-xl font-semibold text-slate-900">Clients</h2>
        <div class="mt-4 overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-200 text-sm">
                <thead class="bg-slate-50 text-left text-xs font-semibold uppercase text-slate-500">
                    <tr>
                        <th class="px-4 py-3">Client</th>
                        <th class="px-4 py-3">Company</th>
                        <th class="px-4 py-3">Joined</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-200">
                <?php foreach ($clients as $client): ?>
                    <tr>
                        <td class="px-4 py-3">
                            <p class="font-medium text-slate-900"><?= e($client['name']); ?></p>
                            <p class="text-xs text-slate-500"><?= e($client['email']); ?></p>
                        </td>
                        <td class="px-4 py-3 text-slate-600"><?= e($client['company']); ?></td>
                        <td class="px-4 py-3 text-xs text-slate-500"><?= format_datetime($client['created_at']); ?></td>
                    </tr>
                <?php endforeach; ?>
                <?php if (!$clients): ?>
                    <tr>
                        <td colspan="3" class="px-4 py-6 text-center text-sm text-slate-500">No clients yet.</td>
                    </tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </section>

    <section class="rounded-lg bg-white p-6 shadow">
        <h2 class="text-xl font-semibold text-slate-900">Brand &amp; account settings</h2>
        <form action="dashboard.php" method="post" class="mt-4 grid gap-4 sm:grid-cols-2">
            <input type="hidden" name="action" value="update_settings">
            <label class="text-sm font-medium">Company name
                <input type="text" name="company_name" value="<?= e(get_setting('company_name', 'Service Portal')); ?>" class="mt-1 w-full rounded border border-slate-300 px-3 py-2">
            </label>
            <label class="text-sm font-medium">Logo URL
                <input type="url" name="brand_logo_url" value="<?= e(get_setting('brand_logo_url', '')); ?>" class="mt-1 w-full rounded border border-slate-300 px-3 py-2">
            </label>
            <label class="text-sm font-medium">Primary colour
                <input type="color" name="brand_primary_color" value="<?= e(get_setting('brand_primary_color', '#3b82f6')); ?>" class="mt-1 h-11 w-full rounded border border-slate-300 px-3 py-2">
            </label>
            <label class="text-sm font-medium">Font family
                <input type="text" name="brand_font_family" value="<?= e(get_setting('brand_font_family', 'Inter, sans-serif')); ?>" class="mt-1 w-full rounded border border-slate-300 px-3 py-2">
            </label>
            <label class="text-sm font-medium">Support email
                <input type="email" name="support_email" value="<?= e(get_setting('support_email', 'support@example.com')); ?>" class="mt-1 w-full rounded border border-slate-300 px-3 py-2">
            </label>
            <div class="sm:col-span-2 flex justify-end">
                <button type="submit" class="rounded bg-brand px-4 py-2 font-medium text-white">Save settings</button>
            </div>
        </form>
    </section>

    <section class="rounded-lg bg-white p-6 shadow">
        <h2 class="text-xl font-semibold text-slate-900">Email templates</h2>
        <form action="dashboard.php" method="post" class="mt-4 space-y-4">
            <input type="hidden" name="action" value="update_email_templates">
            <label class="block text-sm font-medium">Order confirmation template
                <textarea name="email_template_order" rows="5" class="mt-2 w-full rounded border border-slate-300 px-3 py-2"><?= e(get_setting('email_template_order')); ?></textarea>
            </label>
            <label class="block text-sm font-medium">Ticket reply template
                <textarea name="email_template_ticket_reply" rows="5" class="mt-2 w-full rounded border border-slate-300 px-3 py-2"><?= e(get_setting('email_template_ticket_reply')); ?></textarea>
            </label>
            <button type="submit" class="rounded bg-brand px-4 py-2 font-medium text-white">Save templates</button>
        </form>
    </section>

    <section class="rounded-lg bg-white p-6 shadow">
        <h2 class="text-xl font-semibold text-slate-900">Payment integrations</h2>
        <p class="text-sm text-slate-500">Store your Stripe and PayPal credentials to power checkout links and automations.</p>
        <form action="dashboard.php" method="post" class="mt-4 grid gap-4 sm:grid-cols-2">
            <input type="hidden" name="action" value="update_payments">
            <label class="text-sm font-medium">Stripe publishable key
                <input type="text" name="stripe_publishable_key" value="<?= e(get_setting('stripe_publishable_key', '')); ?>" class="mt-1 w-full rounded border border-slate-300 px-3 py-2">
            </label>
            <label class="text-sm font-medium">Stripe secret key
                <input type="text" name="stripe_secret_key" value="<?= e(get_setting('stripe_secret_key', '')); ?>" class="mt-1 w-full rounded border border-slate-300 px-3 py-2">
            </label>
            <label class="text-sm font-medium">PayPal client ID
                <input type="text" name="paypal_client_id" value="<?= e(get_setting('paypal_client_id', '')); ?>" class="mt-1 w-full rounded border border-slate-300 px-3 py-2">
            </label>
            <label class="text-sm font-medium">PayPal client secret
                <input type="text" name="paypal_client_secret" value="<?= e(get_setting('paypal_client_secret', '')); ?>" class="mt-1 w-full rounded border border-slate-300 px-3 py-2">
            </label>
            <div class="sm:col-span-2 flex justify-end">
                <button type="submit" class="rounded bg-brand px-4 py-2 font-medium text-white">Save payment settings</button>
            </div>
        </form>
        <p class="mt-4 text-xs text-slate-500">Tip: Use Stripe Checkout or PayPal Smart Buttons by embedding the generated payment links into your service forms or follow-up emails. Payment status can be updated manually above.</p>
    </section>
</div>
<?php include __DIR__ . '/partials/footer.php'; ?>
