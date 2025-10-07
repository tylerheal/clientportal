<?php include __DIR__ . '/partials/header.php'; ?>
<div class="space-y-8">
    <section class="rounded-lg bg-white p-6 shadow">
        <h1 class="text-2xl font-semibold text-slate-900">Welcome back, <?= e($user['name']); ?> 👋</h1>
        <p class="mt-2 text-sm text-slate-600">Manage your services, orders, and support tickets from this portal.</p>
    </section>

    <section class="rounded-lg bg-white p-6 shadow">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h2 class="text-xl font-semibold text-slate-900">Available services</h2>
                <p class="text-sm text-slate-500">Select a service to submit a new order.</p>
            </div>
        </div>
        <div class="mt-6 grid gap-6 md:grid-cols-2">
            <?php foreach ($services as $service): ?>
                <article class="flex flex-col rounded border border-slate-200 p-5">
                    <header>
                        <h3 class="text-lg font-semibold text-slate-900"><?= e($service['name']); ?></h3>
                        <p class="mt-1 text-sm text-slate-600"><?= e($service['description']); ?></p>
                        <p class="mt-3 text-2xl font-semibold text-slate-900"><?= format_currency((float) $service['price']); ?></p>
                    </header>
                    <form action="dashboard.php" method="post" class="mt-4 flex flex-1 flex-col gap-3">
                        <input type="hidden" name="action" value="create_order">
                        <input type="hidden" name="service_id" value="<?= (int) $service['id']; ?>">
                        <?php foreach (parse_form_schema($service['form_schema']) as $field): ?>
                            <label class="text-sm font-medium text-slate-700">
                                <?= e($field['label']); ?>
                                <input type="<?= e($field['type']); ?>" name="custom[<?= e($field['name']); ?>]" <?= !empty($field['required']) ? 'required' : ''; ?> class="mt-1 w-full rounded border border-slate-300 px-3 py-2 focus:border-brand focus:ring-2 focus:ring-brand">
                            </label>
                        <?php endforeach; ?>
                        <label class="text-sm font-medium text-slate-700">Payment method
                            <select name="payment_method" class="mt-1 w-full rounded border border-slate-300 px-3 py-2">
                                <option value="stripe">Stripe</option>
                                <option value="paypal">PayPal</option>
                                <option value="invoice">Invoice me</option>
                            </select>
                        </label>
                        <button type="submit" class="mt-auto rounded bg-brand px-4 py-2 font-medium text-white">Submit order</button>
                    </form>
                </article>
            <?php endforeach; ?>
            <?php if (!$services): ?>
                <p class="text-sm text-slate-500">There are no active services right now. Check back soon!</p>
            <?php endif; ?>
        </div>
    </section>

    <section class="rounded-lg bg-white p-6 shadow">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h2 class="text-xl font-semibold text-slate-900">Your orders</h2>
                <p class="text-sm text-slate-500">Track progress and payment status.</p>
            </div>
        </div>
        <div class="mt-6 overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-200 text-sm">
                <thead class="bg-slate-50 text-left text-xs font-semibold uppercase text-slate-500">
                    <tr>
                        <th class="px-4 py-3">Order</th>
                        <th class="px-4 py-3">Service</th>
                        <th class="px-4 py-3">Amount</th>
                        <th class="px-4 py-3">Payment</th>
                        <th class="px-4 py-3">Placed</th>
                        <th class="px-4 py-3">Details</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-200">
                <?php foreach ($orders as $order): ?>
                    <tr>
                        <td class="px-4 py-3 font-semibold text-slate-900">#<?= (int) $order['id']; ?></td>
                        <td class="px-4 py-3 text-slate-700"><?= e($order['service_name']); ?></td>
                        <td class="px-4 py-3"><?= format_currency((float) $order['total_amount']); ?></td>
                        <td class="px-4 py-3">
                            <span class="rounded-full bg-slate-100 px-2 py-1 text-xs font-semibold text-slate-700"><?= ucfirst($order['payment_status']); ?></span>
                            <p class="text-xs text-slate-500">via <?= e($order['payment_method']); ?></p>
                        </td>
                        <td class="px-4 py-3 text-xs text-slate-500"><?= format_datetime($order['created_at']); ?></td>
                        <td class="px-4 py-3 text-xs">
                            <?php if ($order['form_data']): ?>
                                <details>
                                    <summary class="cursor-pointer text-brand">View form</summary>
                                    <pre class="mt-2 rounded bg-slate-100 p-2 text-xs"><?= e(json_encode(json_decode($order['form_data'], true), JSON_PRETTY_PRINT)); ?></pre>
                                </details>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <?php if (!$orders): ?>
                    <tr>
                        <td colspan="6" class="px-4 py-6 text-center text-sm text-slate-500">You haven't placed any orders yet.</td>
                    </tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </section>

    <section class="rounded-lg bg-white p-6 shadow">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h2 class="text-xl font-semibold text-slate-900">Support tickets</h2>
                <p class="text-sm text-slate-500">Reach out to our team whenever you need help.</p>
            </div>
        </div>
        <div class="mt-6 grid gap-6 lg:grid-cols-[2fr,1fr]">
            <div class="space-y-5">
                <?php foreach ($tickets as $ticket): ?>
                    <article class="rounded border border-slate-200 p-4">
                        <header class="flex items-start justify-between gap-3">
                            <div>
                                <h3 class="text-lg font-semibold text-slate-900"><?= e($ticket['subject']); ?></h3>
                                <p class="text-xs text-slate-500">Updated <?= format_datetime($ticket['updated_at']); ?></p>
                            </div>
                            <span class="rounded-full border border-brand px-2 py-1 text-xs font-semibold text-brand"><?= ucfirst($ticket['status']); ?></span>
                        </header>
                        <div class="mt-4 space-y-3 text-sm">
                            <?php foreach ($messagesByTicket[$ticket['id']] ?? [] as $message): ?>
                                <div class="rounded border border-slate-100 bg-slate-50 p-3">
                                    <p class="text-xs text-slate-500"><?= e($message['name']); ?> · <?= format_datetime($message['created_at']); ?></p>
                                    <p class="mt-2 whitespace-pre-line text-slate-700"><?= e($message['message']); ?></p>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        <form action="dashboard.php" method="post" class="mt-4 space-y-3">
                            <input type="hidden" name="action" value="reply_ticket_client">
                            <input type="hidden" name="ticket_id" value="<?= (int) $ticket['id']; ?>">
                            <textarea name="message" rows="3" required placeholder="Reply to this ticket..." class="w-full rounded border border-slate-300 px-3 py-2"></textarea>
                            <button type="submit" class="rounded bg-brand px-4 py-2 text-sm font-semibold text-white">Send reply</button>
                        </form>
                    </article>
                <?php endforeach; ?>
                <?php if (!$tickets): ?>
                    <p class="text-sm text-slate-500">No tickets yet. Use the form to start a conversation.</p>
                <?php endif; ?>
            </div>
            <div class="rounded border border-dashed border-brand bg-slate-50 p-4">
                <h3 class="text-sm font-semibold uppercase tracking-wide text-slate-500">Open a ticket</h3>
                <form action="dashboard.php" method="post" class="mt-3 space-y-3">
                    <input type="hidden" name="action" value="create_ticket">
                    <label class="text-sm font-medium text-slate-700">Subject
                        <input type="text" name="subject" required class="mt-1 w-full rounded border border-slate-300 px-3 py-2">
                    </label>
                    <label class="text-sm font-medium text-slate-700">Message
                        <textarea name="message" rows="4" required class="mt-1 w-full rounded border border-slate-300 px-3 py-2"></textarea>
                    </label>
                    <button type="submit" class="w-full rounded bg-brand px-4 py-2 font-medium text-white">Submit ticket</button>
                </form>
            </div>
        </div>
    </section>
</div>
<?php include __DIR__ . '/partials/footer.php'; ?>
