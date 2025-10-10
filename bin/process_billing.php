<?php
require __DIR__ . '/../bootstrap.php';

$pdo = get_db();
$now = new DateTimeImmutable();

$subs = $pdo->query('SELECT s.*, u.email, u.name, sv.name AS service_name, sv.price, o.payment_method FROM subscriptions s JOIN users u ON u.id = s.user_id JOIN services sv ON sv.id = s.service_id LEFT JOIN orders o ON o.id = s.order_id WHERE s.status = "active"')->fetchAll();
foreach ($subs as $subscription) {
    $result = process_subscription_cycle($pdo, $subscription, $now, false);
    if (!empty($result['skipped'])) {
        continue;
    }
}

$overdueCutoff = $now->modify('-2 days')->format(DateTimeInterface::ATOM);
$pending = $pdo->prepare('SELECT i.*, u.email, u.name, s.name AS service_name FROM invoices i JOIN users u ON u.id = i.user_id JOIN services s ON s.id = i.service_id WHERE i.status = "pending" AND i.due_at <= :cutoff');
$pending->execute(['cutoff' => $overdueCutoff]);
foreach ($pending->fetchAll() as $invoice) {
    $pdo->prepare('UPDATE invoices SET status = "overdue", updated_at = :updated WHERE id = :id')
        ->execute([
            'updated' => $now->format(DateTimeInterface::ATOM),
            'id' => $invoice['id'],
        ]);
    $invoiceNumber = format_invoice_number($invoice);
    $overdueReplacements = [
        '{{name}}' => $invoice['name'],
        '{{service}}' => $invoice['service_name'],
        '{{invoice}}' => $invoiceNumber,
        '{{company}}' => get_setting('company_name', 'Service Portal'),
    ];
    $overdueBody = sprintf("Hi %s,\n\nInvoice %s for %s is overdue. Please arrange payment at your earliest convenience.", $invoice['name'], $invoiceNumber, $invoice['service_name']);
    send_templated_email($pdo, 'invoice_overdue', $overdueReplacements, $invoice['email'], 'Invoice overdue', $overdueBody);
    record_notification($pdo, (int) $invoice['user_id'], 'Invoice ' . $invoiceNumber . ' is overdue', url_for('dashboard#invoices'));
}
