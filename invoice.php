<?php
declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

$token = isset($_GET['token']) ? trim((string) $_GET['token']) : '';
if ($token === '') {
    http_response_code(400);
    echo 'Missing invoice token.';
    return;
}

$pdo = get_db();

$stmt = $pdo->prepare('SELECT i.*, u.name AS client_name FROM invoices i JOIN users u ON u.id = i.user_id WHERE i.download_token = :token LIMIT 1');
$stmt->execute(['token' => $token]);
$invoice = $stmt->fetch();

if (!$invoice) {
    http_response_code(404);
    echo 'Invoice not found.';
    return;
}

$pdfContent = generate_invoice_pdf($pdo, (int) $invoice['id'], true);
if ($pdfContent === null) {
    http_response_code(500);
    echo 'Unable to render invoice PDF.';
    return;
}

$filename = format_invoice_number($invoice);
if ($filename === '') {
    $filename = 'invoice-' . (int) $invoice['id'];
}

header('Content-Type: application/pdf');
header('Content-Disposition: inline; filename="' . $filename . '.pdf"');
header('Content-Length: ' . strlen($pdfContent));
echo $pdfContent;
exit;
