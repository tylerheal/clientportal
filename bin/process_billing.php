<?php
require __DIR__ . '/../bootstrap.php';

$pdo = get_db();
$now = new DateTimeImmutable();

$subs = $pdo->query('SELECT s.*, u.email, u.name, sv.name AS service_name, sv.price FROM subscriptions s JOIN users u ON u.id = s.user_id JOIN services sv ON sv.id = s.service_id WHERE s.status = "active"')->fetchAll();
foreach ($subs as $subscription) {
    $next = new DateTimeImmutable($subscription['next_billing_at']);
    if ($next > $now) {
        continue;
    }
    $intervalSpec = $subscription['interval'] === 'annual' ? '+1 year' : '+1 month';
    $dueAt = $now;
    $pdo->prepare('INSERT INTO invoices (subscription_id, order_id, user_id, service_id, total, status, due_at, created_at, updated_at) VALUES (:subscription_id, :order_id, :user_id, :service_id, :total, :status, :due_at, :created_at, :updated_at)')
        ->execute([
            'subscription_id' => $subscription['id'],
            'order_id' => $subscription['order_id'],
            'user_id' => $subscription['user_id'],
            'service_id' => $subscription['service_id'],
            'total' => (float) $subscription['price'],
            'status' => 'pending',
            'due_at' => $dueAt->format(DateTimeInterface::ATOM),
            'created_at' => $dueAt->format(DateTimeInterface::ATOM),
            'updated_at' => $dueAt->format(DateTimeInterface::ATOM),
        ]);
    $pdo->prepare('UPDATE subscriptions SET next_billing_at = :next, updated_at = :updated WHERE id = :id')
        ->execute([
            'next' => $next->modify($intervalSpec)->format(DateTimeInterface::ATOM),
            'updated' => $now->format(DateTimeInterface::ATOM),
            'id' => $subscription['id'],
        ]);
    $invoiceId = (int) $pdo->lastInsertId();
    record_notification($pdo, (int) $subscription['user_id'], 'Invoice #' . $invoiceId . ' generated for ' . $subscription['service_name'], url_for('dashboard#invoices'));
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
    $overdueReplacements = [
        '{{name}}' => $invoice['name'],
        '{{service}}' => $invoice['service_name'],
        '{{invoice}}' => (string) $invoice['id'],
        '{{company}}' => get_setting('company_name', 'Service Portal'),
    ];
    $overdueBody = sprintf("Hi %s,\n\nInvoice #%s for %s is overdue. Please arrange payment at your earliest convenience.", $invoice['name'], $invoice['id'], $invoice['service_name']);
    send_templated_email($pdo, 'invoice_overdue', $overdueReplacements, $invoice['email'], 'Invoice overdue', $overdueBody);
    record_notification($pdo, (int) $invoice['user_id'], 'Invoice #' . $invoice['id'] . ' is overdue', url_for('dashboard#invoices'));
}
