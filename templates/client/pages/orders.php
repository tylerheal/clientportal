<section class="page-section">
    <?php foreach (['error', 'success'] as $flashType): ?>
        <?php if ($message = flash($flashType)): ?>
            <div class="alert alert--<?= $flashType; ?>"><?= e($message); ?></div>
        <?php endif; ?>
    <?php endforeach; ?>
    <header class="page-header">
        <div>
            <h2>Orders</h2>
            <p>Track each order from submission through payment.</p>
        </div>
        <div class="page-actions">
            <a class="button button--primary" href="<?= e(url_for('dashboard/services')); ?>">New order</a>
        </div>
    </header>
    <div class="card">
        <div class="table-toolbar">
            <div class="toolbar-group">
                <label>Status
                    <select>
                        <option>All statuses</option>
                        <option>Pending</option>
                        <option>Paid</option>
                        <option>Failed</option>
                    </select>
                </label>
            </div>
        </div>
        <div class="table-wrapper">
            <table class="table">
                <thead>
                    <tr>
                        <th>Order</th>
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
                            <td><span class="badge badge--<?= e($order['payment_status']); ?>"><?= e(ucfirst($order['payment_status'])); ?></span></td>
                            <td><?= format_currency((float) $order['total_amount']); ?></td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (!$orders): ?>
                        <tr><td colspan="5" class="table-empty">No orders to display.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</section>
