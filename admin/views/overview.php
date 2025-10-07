<?php
if (!defined('APP_BOOTSTRAPPED')) {
    exit('No direct script access.');
}

$title = 'WP Secure Admin – Dashboard';
$logo = '/static/img/logo.png';
$sidebar = [
    'Main' => [
        ['label' => 'Dashboard', 'href' => '#', 'active' => true],
        ['label' => 'Orders', 'href' => '#'],
        ['label' => 'Tickets', 'href' => '#'],
        ['label' => 'Clients', 'href' => '#'],
    ],
    'Setup' => [
        ['label' => 'Services', 'href' => '#'],
        ['label' => 'Forms', 'href' => '#'],
        ['label' => 'Settings', 'href' => '#'],
    ],
];
$notifications = $notifications ?? [
    ['title' => 'Security scan completed', 'message' => 'No threats detected on 12 sites.'],
    ['title' => 'New ticket: Malware cleanup', 'message' => 'Assigned to Riley – due tomorrow.'],
];

ob_start();
?>
<section id="dashboard-overview" class="card chart-card">
    <div class="toolbar" style="justify-content:space-between">
        <div class="range">
            <input id="dateRangeText" readonly>
            <button class="iconbtn" id="todayBtn" title="Today" type="button">📅</button>
        </div>
        <div style="display:flex;gap:10px">
            <div class="menu" id="reportsMenu">
                <button class="btn ghost" id="reportsBtn" type="button">Reports ▾</button>
                <div class="dropdown">
                    <a href="#" data-report="sales">Sales report</a>
                    <a href="#" data-report="clients">New clients</a>
                </div>
            </div>
            <div class="menu" id="exportMenu">
                <button class="btn ghost" id="exportBtn" type="button">Export ▾</button>
                <div class="dropdown">
                    <a href="#" data-export="csv">CSV</a>
                    <a href="#" data-export="json">JSON</a>
                </div>
            </div>
        </div>
    </div>

    <canvas id="ordersChart" height="120" style="margin-top:14px"></canvas>

    <div class="kpis">
        <div class="kpi">
            <div class="label">Revenue</div>
            <div class="value" id="kpiRevenue">—</div>
        </div>
        <div class="kpi">
            <div class="label">New Clients</div>
            <div class="value" id="kpiClients">—</div>
        </div>
        <div class="kpi">
            <div class="label">Average Order</div>
            <div class="value" id="kpiAOV">—</div>
        </div>
    </div>
</section>

<section class="card">
    <div class="h1">Recent Orders</div>
    <table class="table small">
        <thead>
            <tr>
                <th>Client</th>
                <th>Service</th>
                <th>Status</th>
                <th>Date</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td>Ruth Watson</td>
                <td>Site Care</td>
                <td><span class="badge ok">Active</span></td>
                <td>Oct 6</td>
            </tr>
            <tr>
                <td>John Doe</td>
                <td>Malware Cleanup</td>
                <td><span class="badge warn">Pending</span></td>
                <td>Oct 5</td>
            </tr>
        </tbody>
    </table>
</section>
<?php
$content = ob_get_clean();

require __DIR__ . '/../../templates/partials/dashboard_layout.php';
