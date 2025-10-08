<?php
if (!defined('APP_BOOTSTRAPPED')) {
    require __DIR__ . '/../../bootstrap.php';
}

$view = $clientView ?? 'overview';
$sidebar = $clientSidebar ?? [];
$pageTitle = $pageTitle ?? 'Dashboard';
$activeKey = $activeKey ?? 'overview';

ob_start();
switch ($view) {
    case 'services':
        include __DIR__ . '/pages/services.php';
        break;
    case 'forms':
        include __DIR__ . '/pages/forms.php';
        break;
    case 'orders':
        include __DIR__ . '/pages/orders.php';
        break;
    case 'notifications':
        include __DIR__ . '/pages/notifications.php';
        break;
    case 'invoices':
        include __DIR__ . '/pages/invoices.php';
        break;
    case 'tickets':
        include __DIR__ . '/pages/tickets.php';
        break;
    case 'ticket':
        include __DIR__ . '/pages/ticket_detail.php';
        break;
    case 'overview':
    default:
        include __DIR__ . '/pages/overview.php';
        break;
}
$content = ob_get_clean();

include __DIR__ . '/../partials/dashboard_layout.php';
