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

$chartPayload = [
    'labels' => $chartLabels,
    'series' => $chartSeries,
];
$chartJson = json_encode($chartPayload, JSON_THROW_ON_ERROR | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT);
$currencySample = format_currency(1234.56);
$currencyPrefix = '';
$currencySuffix = '';
if (preg_match('/^[^0-9]+/u', $currencySample, $matches)) {
    $currencyPrefix = $matches[0];
}
if (preg_match('/[^0-9,\.\s]+$/u', $currencySample, $matches)) {
    $currencySuffix = $matches[0];
}

$pageScripts[] = ['src' => url_for('static/js/chart.umd.min.js')];
$inlineScripts[] = <<<'JS'
document.addEventListener('DOMContentLoaded', function () {
    const canvas = document.getElementById('ordersChart');
    if (!canvas || typeof Chart === 'undefined') {
        return;
    }
    const payload = canvas.dataset.chart ? JSON.parse(canvas.dataset.chart) : { labels: [], series: [] };
    const currencyPrefix = canvas.dataset.prefix || '';
    const currencySuffix = canvas.dataset.suffix || '';
    const formatter = new Intl.NumberFormat(undefined, {
        minimumFractionDigits: 0,
        maximumFractionDigits: 2
    });
    const existing = Chart.getChart(canvas);
    if (existing) {
        existing.destroy();
    }
    const chart = new Chart(canvas, {
        type: 'bar',
        data: {
            labels: payload.labels,
            datasets: [{
                label: 'Revenue',
                data: payload.series,
                backgroundColor: '#2f6bff',
                borderRadius: 6,
                borderSkipped: false,
                barThickness: 18
            }]
        },
        options: {
            maintainAspectRatio: false,
            animation: false,
            scales: {
                x: {
                    grid: { display: false },
                    ticks: { color: '#aab3c2' }
                },
                y: {
                    grid: { color: 'rgba(255,255,255,0.06)' },
                    ticks: {
                        color: '#aab3c2',
                        callback: function (value) {
                            return currencyPrefix + formatter.format(value) + currencySuffix;
                        }
                    }
                }
            },
            plugins: {
                legend: { display: false },
                tooltip: {
                    callbacks: {
                        label: function (context) {
                            return currencyPrefix + formatter.format(context.parsed.y) + currencySuffix;
                        }
                    }
                }
            }
        }
    });
});
JS;
?>
<section class="page-section">
    <?php foreach (['error', 'success'] as $flashType): ?>
        <?php if ($message = flash($flashType)): ?>
            <div class="alert alert--<?= $flashType; ?>"><?= e($message); ?></div>
        <?php endif; ?>
    <?php endforeach; ?>

    <article class="card chart-card">
        <div class="toolbar" style="justify-content: space-between;">
            <div class="range">
                <input id="dateRangeText" value="<?= e($rangeText); ?>" readonly>
                <button class="portal-icon-button" type="button" title="Today" aria-label="Jump to today">
                    <svg viewBox="0 0 448 512" role="presentation" focusable="false">
                        <path d="M128 0c17.7 0 32 14.3 32 32v32h128V32c0-17.7 14.3-32 32-32s32 14.3 32 32v32h40c26.5 0 48 21.5 48 48v48H0v-48C0 85.5 21.5 64 48 64h40V32C88 14.3 102.3 0 120 0h8Zm320 224v208c0 26.5-21.5 48-48 48H48c-26.5 0-48-21.5-48-48V224h448ZM64 288v32h96v-32H64Zm160 0v32h96v-32h-96ZM64 384v32h96v-32H64Zm160 0v32h96v-32h-96Z" />
                    </svg>
                </button>
            </div>
            <div style="display:flex;gap:10px;flex-wrap:wrap;">
                <div class="menu" data-menu>
                    <button class="button button--ghost" data-menu-toggle>Reports ▾</button>
                    <div class="dropdown">
                        <a href="<?= e(url_for('admin/orders')); ?>">Sales report</a>
                        <a href="<?= e(url_for('admin/clients')); ?>">New clients</a>
                    </div>
                </div>
                <div class="menu" data-menu>
                    <button class="button button--ghost" data-menu-toggle>Export ▾</button>
                    <div class="dropdown">
                        <a href="<?= e(url_for('admin/orders')); ?>">Export CSV</a>
                        <a href="<?= e(url_for('admin/orders')); ?>">Export JSON</a>
                    </div>
                </div>
            </div>
        </div>
        <div style="margin-top:14px;height:240px;">
            <canvas id="ordersChart" data-chart='<?= $chartJson; ?>' data-prefix="<?= e($currencyPrefix); ?>" data-suffix="<?= e($currencySuffix); ?>"></canvas>
        </div>
        <div class="kpis" style="margin-top:14px;">
            <div class="kpi">
                <div class="label">Revenue</div>
                <div class="value"><?= e(format_currency($revenue)); ?></div>
                <div class="small subtle">Last 30 days</div>
            </div>
            <div class="kpi">
                <div class="label">New clients</div>
                <div class="value"><?= number_format($newClients); ?></div>
                <div class="small subtle">Joined in range</div>
            </div>
            <div class="kpi">
                <div class="label">Average order</div>
                <div class="value"><?= e(format_currency($averageOrder)); ?></div>
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
                <a class="button button--ghost" href="<?= e(url_for('admin/orders')); ?>">Manage</a>
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
