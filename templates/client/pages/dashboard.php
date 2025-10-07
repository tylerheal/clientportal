<?php
if (!defined('APP_BOOTSTRAPPED')) {
    exit('No direct script access.');
}

$title = $title ?? 'Client Dashboard';
$logo = $logo ?? '/static/img/logo.png';
$sidebar = $sidebar ?? [
    'Account' => [
        ['label' => 'Dashboard', 'href' => '#', 'active' => true],
        ['label' => 'Services', 'href' => '#'],
        ['label' => 'Invoices', 'href' => '#'],
        ['label' => 'Support', 'href' => '#'],
    ],
];
$notifications = $notifications ?? [];

ob_start();
?>
<section class="card">
    <div class="h1">Welcome back</div>
    <p class="subtle">Here’s what’s happening with your services.</p>
    <div class="kpis">
        <div class="kpi">
            <div class="label">Active Services</div>
            <div class="value">3</div>
        </div>
        <div class="kpi">
            <div class="label">Open Tickets</div>
            <div class="value">1</div>
        </div>
        <div class="kpi">
            <div class="label">Last Payment</div>
            <div class="value">Sep 28</div>
        </div>
    </div>
</section>

<section class="card">
    <div class="h1">Recent Activity</div>
    <table class="table">
        <thead>
            <tr>
                <th>Event</th>
                <th>Status</th>
                <th>Date</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td>Security scan – Main site</td>
                <td><span class="badge ok">Clean</span></td>
                <td>Oct 6</td>
            </tr>
            <tr>
                <td>Support ticket reply</td>
                <td><span class="badge muted">Awaiting customer</span></td>
                <td>Oct 4</td>
            </tr>
        </tbody>
    </table>
</section>
<?php
$content = ob_get_clean();

require __DIR__ . '/../../partials/dashboard_layout.php';
