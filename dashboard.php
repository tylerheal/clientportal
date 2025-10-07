<?php
require __DIR__ . '/bootstrap.php';
require_login();

$user = current_user();

function parse_builder_lines(string $input): string
{
    $lines = array_filter(array_map('trim', preg_split('/\r?\n/', $input)));
    $fields = [];
    foreach ($lines as $line) {
        [$label, $name, $type, $required] = array_pad(explode('|', $line), 4, '');
        $fields[] = [
            'label' => trim($label) ?: trim($name),
            'name' => trim($name) ?: strtolower(preg_replace('/[^a-z0-9]+/i', '_', $label)),
            'type' => trim($type) ?: 'text',
            'required' => strtolower(trim($required)) === 'required',
        ];
    }
    return json_encode($fields, JSON_THROW_ON_ERROR);
}

if (is_post()) {
    $action = $_POST['action'] ?? '';

    try {
        switch ($action) {
            case 'create_service':
                require_login('admin');
                $name = trim($_POST['name'] ?? '');
                $price = (float) ($_POST['price'] ?? 0);
                $description = trim($_POST['description'] ?? '');
                $builder = trim($_POST['form_builder'] ?? '');
                if ($name === '') {
                    throw new RuntimeException('Service name is required.');
                }
                $schema = $builder !== '' ? parse_builder_lines($builder) : json_encode([], JSON_THROW_ON_ERROR);
                $now = (new \DateTimeImmutable())->format(\DateTimeInterface::ATOM);
                $stmt = $pdo->prepare('INSERT INTO services (name, description, price, form_schema, active, created_by, created_at, updated_at)
                    VALUES (:name, :description, :price, :form_schema, :active, :created_by, :created_at, :updated_at)');
                $stmt->execute([
                    'name' => $name,
                    'description' => $description,
                    'price' => $price,
                    'form_schema' => $schema,
                    'active' => isset($_POST['active']) ? 1 : 0,
                    'created_by' => $user['id'],
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
                flash('success', 'Service created successfully.');
                break;
            case 'update_service':
                require_login('admin');
                $serviceId = (int) ($_POST['service_id'] ?? 0);
                $name = trim($_POST['name'] ?? '');
                $price = (float) ($_POST['price'] ?? 0);
                $description = trim($_POST['description'] ?? '');
                $builder = trim($_POST['form_builder'] ?? '');
                if ($serviceId <= 0) {
                    throw new RuntimeException('Invalid service specified.');
                }
                $schema = $builder !== '' ? parse_builder_lines($builder) : json_encode([], JSON_THROW_ON_ERROR);
                $stmt = $pdo->prepare('UPDATE services SET name = :name, description = :description, price = :price, form_schema = :form_schema, active = :active, updated_at = :updated_at WHERE id = :id');
                $stmt->execute([
                    'id' => $serviceId,
                    'name' => $name,
                    'description' => $description,
                    'price' => $price,
                    'form_schema' => $schema,
                    'active' => isset($_POST['active']) ? 1 : 0,
                    'updated_at' => (new \DateTimeImmutable())->format(\DateTimeInterface::ATOM),
                ]);
                flash('success', 'Service updated.');
                break;
            case 'delete_service':
                require_login('admin');
                $serviceId = (int) ($_POST['service_id'] ?? 0);
                if ($serviceId > 0) {
                    $pdo->prepare('DELETE FROM services WHERE id = :id')->execute(['id' => $serviceId]);
                    flash('success', 'Service removed.');
                }
                break;
            case 'update_settings':
                require_login('admin');
                $settings = [
                    'company_name' => trim($_POST['company_name'] ?? ''),
                    'brand_logo_url' => trim($_POST['brand_logo_url'] ?? ''),
                    'brand_primary_color' => trim($_POST['brand_primary_color'] ?? '#3b82f6'),
                    'brand_font_family' => trim($_POST['brand_font_family'] ?? 'Inter, sans-serif'),
                    'support_email' => trim($_POST['support_email'] ?? ''),
                ];
                foreach ($settings as $key => $value) {
                    set_setting($key, $value);
                }
                flash('success', 'Brand settings updated.');
                break;
            case 'update_email_templates':
                require_login('admin');
                set_setting('email_template_order', $_POST['email_template_order'] ?? '');
                set_setting('email_template_ticket_reply', $_POST['email_template_ticket_reply'] ?? '');
                flash('success', 'Email templates saved.');
                break;
            case 'update_payments':
                require_login('admin');
                set_setting('stripe_publishable_key', $_POST['stripe_publishable_key'] ?? '');
                set_setting('stripe_secret_key', $_POST['stripe_secret_key'] ?? '');
                set_setting('paypal_client_id', $_POST['paypal_client_id'] ?? '');
                set_setting('paypal_client_secret', $_POST['paypal_client_secret'] ?? '');
                flash('success', 'Payment settings saved.');
                break;
            case 'update_order_status':
                require_login('admin');
                $orderId = (int) ($_POST['order_id'] ?? 0);
                $status = trim($_POST['payment_status'] ?? 'pending');
                $reference = trim($_POST['payment_reference'] ?? '');
                if ($orderId > 0) {
                    $pdo->prepare('UPDATE orders SET payment_status = :status, payment_reference = :reference, updated_at = :updated_at WHERE id = :id')
                        ->execute([
                            'status' => $status,
                            'reference' => $reference,
                            'updated_at' => (new \DateTimeImmutable())->format(\DateTimeInterface::ATOM),
                            'id' => $orderId,
                        ]);
                    flash('success', 'Order updated.');
                }
                break;
            case 'reply_ticket_admin':
                require_login('admin');
                $ticketId = (int) ($_POST['ticket_id'] ?? 0);
                $message = trim($_POST['message'] ?? '');
                $status = trim($_POST['status'] ?? 'open');
                if ($ticketId <= 0 || $message === '') {
                    throw new RuntimeException('Message text is required.');
                }
                $pdo->beginTransaction();
                $pdo->prepare('UPDATE tickets SET status = :status, updated_at = :updated_at WHERE id = :id')
                    ->execute([
                        'status' => $status,
                        'updated_at' => (new \DateTimeImmutable())->format(\DateTimeInterface::ATOM),
                        'id' => $ticketId,
                    ]);
                $pdo->prepare('INSERT INTO ticket_messages (ticket_id, user_id, message, created_at) VALUES (:ticket_id, :user_id, :message, :created_at)')
                    ->execute([
                        'ticket_id' => $ticketId,
                        'user_id' => $user['id'],
                        'message' => $message,
                        'created_at' => (new \DateTimeImmutable())->format(\DateTimeInterface::ATOM),
                    ]);
                $pdo->commit();

                $ticket = $pdo->prepare('SELECT t.subject, u.email, u.name FROM tickets t JOIN users u ON u.id = t.user_id WHERE t.id = :id');
                $ticket->execute(['id' => $ticketId]);
                if ($row = $ticket->fetch()) {
                    $body = get_setting('email_template_ticket_reply');
                    $replacements = [
                        '{{name}}' => $row['name'],
                        '{{subject}}' => $row['subject'],
                        '{{message}}' => $message,
                        '{{company}}' => get_setting('company_name', 'Service Portal'),
                    ];
                    send_notification_email($row['email'], 'Ticket update: ' . $row['subject'], strtr($body, $replacements));
                }
                flash('success', 'Reply sent.');
                break;
            case 'create_order':
                $serviceId = (int) ($_POST['service_id'] ?? 0);
                $paymentMethod = trim($_POST['payment_method'] ?? 'stripe');
                $serviceStmt = $pdo->prepare('SELECT * FROM services WHERE id = :id AND active = 1');
                $serviceStmt->execute(['id' => $serviceId]);
                $service = $serviceStmt->fetch();
                if (!$service) {
                    throw new RuntimeException('The selected service is not available.');
                }
                $custom = $_POST['custom'] ?? [];
                if (!is_array($custom)) {
                    $custom = [];
                }
                $now = (new \DateTimeImmutable())->format(\DateTimeInterface::ATOM);
                $pdo->prepare('INSERT INTO orders (user_id, service_id, payment_method, total_amount, form_data, created_at, updated_at)
                    VALUES (:user_id, :service_id, :payment_method, :total_amount, :form_data, :created_at, :updated_at)')
                    ->execute([
                        'user_id' => $user['id'],
                        'service_id' => $service['id'],
                        'payment_method' => $paymentMethod,
                        'total_amount' => $service['price'],
                        'form_data' => json_encode($custom, JSON_THROW_ON_ERROR),
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]);
                $orderId = (int) $pdo->lastInsertId();

                $adminEmail = get_setting('support_email', 'support@example.com');
                $template = get_setting('email_template_order');
                $replacements = [
                    '{{name}}' => $user['name'],
                    '{{service}}' => $service['name'],
                    '{{company}}' => get_setting('company_name', 'Service Portal'),
                ];
                send_notification_email($adminEmail, 'New order #' . $orderId, $user['name'] . ' placed an order for ' . $service['name'] . '.');
                send_notification_email($user['email'], 'Order received: ' . $service['name'], strtr($template, $replacements));
                flash('success', 'Thanks! Your order has been submitted. We will follow up shortly.');
                break;
            case 'create_ticket':
                $subject = trim($_POST['subject'] ?? '');
                $message = trim($_POST['message'] ?? '');
                if ($subject === '' || $message === '') {
                    throw new RuntimeException('Please provide a subject and message for your ticket.');
                }
                $now = (new \DateTimeImmutable())->format(\DateTimeInterface::ATOM);
                $pdo->beginTransaction();
                $pdo->prepare('INSERT INTO tickets (user_id, subject, status, created_at, updated_at) VALUES (:user_id, :subject, :status, :created_at, :updated_at)')
                    ->execute([
                        'user_id' => $user['id'],
                        'subject' => $subject,
                        'status' => 'open',
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]);
                $ticketId = (int) $pdo->lastInsertId();
                $pdo->prepare('INSERT INTO ticket_messages (ticket_id, user_id, message, created_at) VALUES (:ticket_id, :user_id, :message, :created_at)')
                    ->execute([
                        'ticket_id' => $ticketId,
                        'user_id' => $user['id'],
                        'message' => $message,
                        'created_at' => $now,
                    ]);
                $pdo->commit();

                send_notification_email(get_setting('support_email', 'support@example.com'), 'New support ticket #' . $ticketId, $subject . "\n\n" . $message);
                flash('success', 'Support ticket created. We will be in touch soon.');
                break;
            case 'reply_ticket_client':
                $ticketId = (int) ($_POST['ticket_id'] ?? 0);
                $message = trim($_POST['message'] ?? '');
                if ($ticketId <= 0 || $message === '') {
                    throw new RuntimeException('Please enter your reply.');
                }
                $pdo->prepare('INSERT INTO ticket_messages (ticket_id, user_id, message, created_at) VALUES (:ticket_id, :user_id, :message, :created_at)')
                    ->execute([
                        'ticket_id' => $ticketId,
                        'user_id' => $user['id'],
                        'message' => $message,
                        'created_at' => (new \DateTimeImmutable())->format(\DateTimeInterface::ATOM),
                    ]);
                $pdo->prepare('UPDATE tickets SET status = :status, updated_at = :updated_at WHERE id = :id AND user_id = :user_id')
                    ->execute([
                        'status' => 'open',
                        'updated_at' => (new \DateTimeImmutable())->format(\DateTimeInterface::ATOM),
                        'id' => $ticketId,
                        'user_id' => $user['id'],
                    ]);
                send_notification_email(get_setting('support_email', 'support@example.com'), 'Ticket reply #' . $ticketId, $message);
                flash('success', 'Your reply has been posted.');
                break;
        }
    } catch (Throwable $e) {
        flash('error', $e->getMessage());
    }

    redirect('dashboard.php');
}

if ($user['role'] === 'admin') {
    $services = $pdo->query('SELECT * FROM services ORDER BY created_at DESC')->fetchAll();
    $clients = $pdo->query("SELECT id, name, email, company, created_at FROM users WHERE role = 'client' ORDER BY created_at DESC")->fetchAll();
    $orders = $pdo->query('SELECT o.*, s.name AS service_name, u.name AS client_name FROM orders o JOIN services s ON s.id = o.service_id JOIN users u ON u.id = o.user_id ORDER BY o.created_at DESC')->fetchAll();
    $tickets = $pdo->query('SELECT t.*, u.name AS client_name FROM tickets t JOIN users u ON u.id = t.user_id ORDER BY t.updated_at DESC')->fetchAll();
    $ticketMessages = $pdo->query('SELECT m.*, u.name FROM ticket_messages m JOIN users u ON u.id = m.user_id ORDER BY m.created_at')->fetchAll();
    $messagesByTicket = [];
    foreach ($ticketMessages as $message) {
        $messagesByTicket[$message['ticket_id']][] = $message;
    }
    $pageTitle = 'Admin dashboard';
    include __DIR__ . '/templates/admin_dashboard.php';
    exit;
}

$services = $pdo->query('SELECT * FROM services WHERE active = 1 ORDER BY name')->fetchAll();
$orderStmt = $pdo->prepare('SELECT o.*, s.name AS service_name FROM orders o JOIN services s ON s.id = o.service_id WHERE o.user_id = :user ORDER BY o.created_at DESC');
$orderStmt->execute(['user' => $user['id']]);
$orders = $orderStmt->fetchAll();

$ticketStmt = $pdo->prepare('SELECT * FROM tickets WHERE user_id = :user ORDER BY updated_at DESC');
$ticketStmt->execute(['user' => $user['id']]);
$tickets = $ticketStmt->fetchAll();

$messagesStmt = $pdo->prepare('SELECT m.*, u.name FROM ticket_messages m JOIN users u ON u.id = m.user_id WHERE m.ticket_id IN (SELECT id FROM tickets WHERE user_id = :user) ORDER BY m.created_at');
$messagesStmt->execute(['user' => $user['id']]);
$messagesByTicket = [];
foreach ($messagesStmt->fetchAll() as $message) {
    $messagesByTicket[$message['ticket_id']][] = $message;
}

$pageTitle = 'Client dashboard';
include __DIR__ . '/templates/client_dashboard.php';
