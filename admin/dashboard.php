<?php
if (!defined('APP_BOOTSTRAPPED')) {
    require __DIR__ . '/../bootstrap.php';
}

$adminSidebar = [
    ['key' => 'overview', 'label' => 'Overview', 'href' => url_for('admin/overview')],
    ['key' => 'services', 'label' => 'Services', 'href' => url_for('admin/services')],
    ['key' => 'forms', 'label' => 'Forms', 'href' => url_for('admin/forms')],
    ['key' => 'orders', 'label' => 'Orders', 'href' => url_for('admin/orders')],
    ['key' => 'invoices', 'label' => 'Invoices', 'href' => url_for('admin/invoices')],
    ['key' => 'tickets', 'label' => 'Tickets', 'href' => url_for('admin/tickets')],
    ['key' => 'clients', 'label' => 'Clients', 'href' => url_for('admin/clients')],
    ['key' => 'automations', 'label' => 'Automations', 'href' => url_for('admin/automations')],
    ['key' => 'payments', 'label' => 'Payments', 'href' => url_for('admin/payments')],
    ['key' => 'administrators', 'label' => 'Admins', 'href' => url_for('admin/administrators')],
    ['key' => 'settings', 'label' => 'Settings', 'href' => url_for('admin/settings')],
];

$activeKey = $view === 'ticket' ? 'tickets' : $view;
$pageTitleMap = [
    'overview' => 'Admin overview',
    'services' => 'Services',
    'forms' => 'Forms',
    'orders' => 'Orders',
    'invoices' => 'Invoices',
    'tickets' => 'Tickets',
    'ticket' => 'Ticket detail',
    'clients' => 'Clients',
    'automations' => 'Automations',
    'payments' => 'Payments',
    'administrators' => 'Administrators',
    'settings' => 'Settings',
];
$pageTitle = $pageTitleMap[$view] ?? 'Admin overview';
if ($view === 'ticket' && isset($selectedTicket['id'])) {
    $pageTitle = 'Ticket #' . $selectedTicket['id'];
}
$sidebar = $adminSidebar;

ob_start();
switch ($view) {
    case 'services':
        include __DIR__ . '/views/services.php';
        break;
    case 'forms':
        include __DIR__ . '/views/forms.php';
        break;
    case 'orders':
        include __DIR__ . '/views/orders.php';
        break;
    case 'invoices':
        include __DIR__ . '/views/invoices.php';
        break;
    case 'tickets':
        include __DIR__ . '/views/tickets.php';
        break;
    case 'ticket':
        include __DIR__ . '/views/ticket_detail.php';
        break;
    case 'clients':
        include __DIR__ . '/views/clients.php';
        break;
    case 'automations':
        include __DIR__ . '/views/automations.php';
        break;
    case 'payments':
        include __DIR__ . '/views/payments.php';
        break;
    case 'administrators':
        include __DIR__ . '/views/administrators.php';
        break;
    case 'settings':
        include __DIR__ . '/views/settings.php';
        break;
    case 'overview':
    default:
        include __DIR__ . '/views/overview.php';
        break;
}
$content = ob_get_clean();

include __DIR__ . '/../templates/partials/dashboard_layout.php';
