<section class="page-section">
    <?php foreach (['error', 'success'] as $flashType): ?>
        <?php if ($message = flash($flashType)): ?>
            <div class="alert alert--<?= $flashType; ?>"><?= e($message); ?></div>
        <?php endif; ?>
    <?php endforeach; ?>
    <header class="page-header">
        <div>
            <h2>Orders</h2>
            <p>Track payments and fulfilment.</p>
        </div>
    </header>
    <div class="card">
        <div class="table-wrapper">
            <table class="table">
                <thead>
                    <tr>
                        <th>Order</th>
                        <th>Client</th>
                        <th>Service</th>
                        <th>Status</th>
                        <th>Total</th>
                        <th>Details</th>
                        <th>Update</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($orders as $order): ?>
                        <tr>
                            <td>#<?= (int) $order['id']; ?></td>
                            <td><?= e($order['client_name']); ?></td>
                            <td><?= e($order['service_name']); ?></td>
                            <td><span class="badge badge--<?= e($order['payment_status']); ?>"><?= e(ucfirst($order['payment_status'])); ?></span></td>
                            <td><?= format_currency((float) $order['total_amount']); ?></td>
                            <td>
                                <?php
                                    $formData = [];
                                    if (!empty($order['form_data'])) {
                                        try {
                                            $decoded = json_decode($order['form_data'], true, 512, JSON_THROW_ON_ERROR);
                                            if (is_array($decoded)) {
                                                $formData = $decoded;
                                            }
                                        } catch (Throwable $e) {
                                            $formData = [];
                                        }
                                    }
                                ?>
                                <?php if ($formData): ?>
                                    <dl class="order-details">
                                        <?php foreach ($formData as $fieldKey => $fieldValue): ?>
                                            <?php
                                                $label = ucwords(str_replace(['_', '-'], ' ', (string) $fieldKey));
                                                if (is_array($fieldValue)) {
                                                    $value = implode(', ', array_map(static function ($item) {
                                                        return is_scalar($item) ? (string) $item : '';
                                                    }, $fieldValue));
                                                } elseif (is_bool($fieldValue)) {
                                                    $value = $fieldValue ? 'Yes' : 'No';
                                                } else {
                                                    $value = (string) $fieldValue;
                                                }
                                            ?>
                                            <div class="order-details__row">
                                                <dt><?= e($label); ?></dt>
                                                <?php if ($value !== ''): ?>
                                                    <dd><?= e($value); ?></dd>
                                                <?php else: ?>
                                                    <dd><span class="subtle">—</span></dd>
                                                <?php endif; ?>
                                            </div>
                                        <?php endforeach; ?>
                                    </dl>
                                <?php else: ?>
                                    <span class="subtle">—</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <form action="<?= e(url_for('dashboard')); ?>" method="post" class="inline-form">
                                    <input type="hidden" name="action" value="update_order_status">
                                    <input type="hidden" name="order_id" value="<?= (int) $order['id']; ?>">
                                    <input type="hidden" name="redirect" value="admin/orders">
                                    <select name="payment_status">
                                        <option value="pending" <?= $order['payment_status'] === 'pending' ? 'selected' : ''; ?>>Pending</option>
                                        <option value="paid" <?= $order['payment_status'] === 'paid' ? 'selected' : ''; ?>>Paid</option>
                                        <option value="failed" <?= $order['payment_status'] === 'failed' ? 'selected' : ''; ?>>Failed</option>
                                    </select>
                                    <input type="text" name="payment_reference" value="<?= e($order['payment_reference']); ?>" placeholder="Reference">
                                    <button type="submit" class="button button--ghost">Save</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (!$orders): ?>
                        <tr><td colspan="7" class="table-empty">No orders found.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</section>
