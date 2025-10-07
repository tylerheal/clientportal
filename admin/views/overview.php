<?php
$activeServices = count(array_filter($services, fn($service) => (int) $service['active'] === 1));
$clientCount = count($clients);
$openTickets = count(array_filter($tickets, fn($ticket) => !in_array($ticket['status'], ['resolved', 'closed'], true)));
$pendingInvoices = count(array_filter($invoices, fn($invoice) => $invoice['status'] !== 'paid'));

$today = new DateTimeImmutable('today');
$start = $today->sub(new DateInterval('P29D'));
$period = new DatePeriod($start, new DateInterval('P1D'), $today->add(new DateInterval('P1D')));
$dailyTotals = [];
foreach ($period as $day) {
    $dailyTotals[$day->format('Y-m-d')] = 0.0;
}

$paidOrdersCount = 0;
foreach ($orders as $order) {
    if (($order['payment_status'] ?? '') !== 'paid') {
        continue;
    }
    try {
        $created = new DateTimeImmutable($order['created_at']);
    } catch (Exception $e) {
        continue;
    }
    $key = $created->format('Y-m-d');
    if (!array_key_exists($key, $dailyTotals)) {
        continue;
    }
    $dailyTotals[$key] += (float) $order['total_amount'];
    $paidOrdersCount++;
}

$chartLabels = [];
$chartSeries = [];
foreach ($dailyTotals as $key => $value) {
    $date = DateTimeImmutable::createFromFormat('Y-m-d', $key) ?: null;
    $chartLabels[] = $date ? $date->format('M j') : $key;
    $chartSeries[] = round($value, 2);
}

$revenue = array_sum($dailyTotals);
$newClients = 0;
foreach ($clients as $client) {
    if (empty($client['created_at'])) {
        continue;
    }
    try {
        $created = new DateTimeImmutable($client['created_at']);
    } catch (Exception $e) {
        continue;
    }
    if ($created >= $start && $created <= $today->setTime(23, 59, 59)) {
        $newClients++;
    }
}
$averageOrder = $paidOrdersCount > 0 ? $revenue / $paidOrdersCount : 0.0;
$rangeText = sprintf('%s – %s', $start->format('M j, Y'), $today->format('M j, Y'));

$currencySample = format_currency(1234.56);
$currencyPrefix = '';
$currencySuffix = '';
if (preg_match('/^[^0-9]+/u', $currencySample, $matches)) {
    $currencyPrefix = $matches[0];
}
if (preg_match('/[^0-9,\.\s]+$/u', $currencySample, $matches)) {
    $currencySuffix = $matches[0];
}

$dashboardPayload = [
    'labels' => $chartLabels,
    'series' => $chartSeries,
    'revenue' => round($revenue, 2),
    'newClients' => $newClients,
    'averageOrder' => round($averageOrder, 2),
    'rangeText' => $rangeText,
    'currencyPrefix' => $currencyPrefix,
    'currencySuffix' => $currencySuffix,
];
$dashboardJson = json_encode($dashboardPayload, JSON_THROW_ON_ERROR | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT);

$pageScripts[] = [
    'src' => url_for('static/js/chart.umd.min.js'),
    'defer' => true,
];
?>
<section class="page-section">
    <?php foreach (['error', 'success'] as $flashType): ?>
        <?php if ($message = flash($flashType)): ?>
            <div class="alert alert--<?= $flashType; ?>"><?= e($message); ?></div>
        <?php endif; ?>
    <?php endforeach; ?>

    <article class="card chart-card">
        <div class="toolbar">
            <div class="range">
                <input id="dateRangeText" value="<?= e($rangeText); ?>" readonly>
                <button class="iconbtn" type="button" id="todayBtn" title="Today" aria-label="Jump to today">📅</button>
            </div>
            <div class="toolbar-actions">
                <div class="menu" data-menu>
                    <button class="btn ghost" type="button" data-menu-toggle>Reports ▾</button>
                    <div class="dropdown">
                        <a href="<?= e(url_for('admin/orders')); ?>">Sales report</a>
                        <a href="<?= e(url_for('admin/clients')); ?>">New clients</a>
                    </div>
                </div>
                <div class="menu" data-menu>
                    <button class="btn ghost" type="button" data-menu-toggle>Export ▾</button>
                    <div class="dropdown">
                        <a href="#" data-export="csv">Export CSV</a>
                        <a href="#" data-export="json">Export JSON</a>
                    </div>
                </div>
            </div>
        </div>
        <div class="chart-shell">
            <canvas
                id="ordersChart"
                data-dashboard='<?= $dashboardJson; ?>'
                data-prefix="<?= e($currencyPrefix); ?>"
                data-suffix="<?= e($currencySuffix); ?>"
                height="200"
            ></canvas>
        </div>
        <div class="kpis">
            <div class="kpi">
                <div class="label">Revenue</div>
                <div class="value" id="kpiRevenue"><?= e(format_currency($revenue)); ?></div>
                <div class="small subtle">Last 30 days</div>
            </div>
            <div class="kpi">
                <div class="label">New clients</div>
                <div class="value" id="kpiClients"><?= number_format($newClients); ?></div>
                <div class="small subtle">Joined in range</div>
            </div>
            <div class="kpi">
                <div class="label">Average order</div>
                <div class="value" id="kpiAOV"><?= e(format_currency($averageOrder)); ?></div>
                <div class="small subtle">Paid orders only</div>
            </div>
        </div>
    </article>

    <div class="grid cols-2">
        <article class="card">
            <header class="card-header">
                <div>
                    <h2>Portal snapshot</h2>
                    <p>Key metrics across your workspace.</p>
                </div>
            </header>
            <div class="grid cols-2">
                <div class="kpi">
                    <div class="label">Active services</div>
                    <div class="value"><?= $activeServices; ?></div>
                    <div class="small subtle">Services available to sell</div>
                </div>
                <div class="kpi">
                    <div class="label">Clients</div>
                    <div class="value"><?= $clientCount; ?></div>
                    <div class="small subtle">Accounts in the portal</div>
                </div>
                <div class="kpi">
                    <div class="label">Open tickets</div>
                    <div class="value"><?= $openTickets; ?></div>
                    <div class="small subtle">Awaiting response</div>
                </div>
                <div class="kpi">
                    <div class="label">Pending invoices</div>
                    <div class="value"><?= $pendingInvoices; ?></div>
                    <div class="small subtle">Need attention</div>
                </div>
            </div>
        </article>
        <article class="card">
            <header class="card-header">
                <div>
                    <h2>Quick actions</h2>
                    <p>Jump straight into daily workflows.</p>
                </div>
            </header>
            <div class="row cols-2">
                <a class="btn" href="<?= e(url_for('admin/services')); ?>">Add service</a>
                <a class="btn" href="<?= e(url_for('admin/forms')); ?>">Create form</a>
                <a class="btn primary" href="<?= e(url_for('admin/tickets')); ?>">View tickets</a>
                <a class="btn" href="<?= e(url_for('admin/settings')); ?>">Brand settings</a>
            </div>
        </article>
    </div>

    <div class="grid cols-2">
        <article class="card">
            <header class="card-header">
                <div>
                    <h2>Recent orders</h2>
                    <p>Latest five purchases.</p>
                </div>
                <a class="btn ghost" href="<?= e(url_for('admin/orders')); ?>">Manage</a>
            </header>
            <div class="table-wrapper">
                <table class="table small">
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
        <article class="card">
            <header class="card-header">
                <div>
                    <h2>Support queue</h2>
                    <p>Keep response times tight.</p>
                </div>
                <a class="btn ghost" href="<?= e(url_for('admin/tickets')); ?>">View all</a>
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
