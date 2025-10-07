<?php
require __DIR__ . '/bootstrap.php';
require_login();

$pdo = get_db();
$current = current_user();
$stmt = $pdo->prepare('SELECT * FROM users WHERE id = :id LIMIT 1');
$stmt->execute(['id' => $current['id']]);
$user = $stmt->fetch();

if (!$user) {
    logout();
    flash('error', 'Your session has expired. Please sign in again.');
    redirect('login');
}

if (($current['name'] ?? '') !== $user['name'] || ($current['email'] ?? '') !== $user['email'] || ($current['totp_enabled'] ?? false) !== (bool) $user['totp_enabled']) {
    login($user);
}

$pathInfo = trim(request_path(), '/');
$segments = $pathInfo === '' ? [] : explode('/', $pathInfo);
$context = $segments[0] ?? '';
if ($context === 'dashboard' || $context === 'admin') {
    array_shift($segments);
}
$viewSource = $_GET['view'] ?? null;
if ($context === 'admin') {
    $viewSource = $_GET['admin_view'] ?? $viewSource;
}
$viewInput = strtolower((string) ($viewSource ?? ($segments[0] ?? 'overview')));
$view = preg_replace('/[^a-z\-]+/', '', $viewInput) ?: 'overview';
$isAdminRoute = ($context === 'admin');
if ($isAdminRoute) {
    $_GET['admin_view'] = $view;
}
$userRole = $user['role'] ?? 'client';
if ($isAdminRoute && $userRole !== 'admin') {
    flash('error', 'You are not authorised to access that area.');
    redirect('dashboard');
}
$resourceId = (int) ($_GET['resource_id'] ?? ($segments[1] ?? 0));
$ticketDetailId = null;
if ($view === 'tickets' && $resourceId > 0) {
    $view = 'ticket';
    $ticketDetailId = $resourceId;
}

function admin_user_ids(PDO $pdo): array
{
    $ids = [];
    foreach ($pdo->query("SELECT id FROM users WHERE role = 'admin'") as $row) {
        $ids[] = (int) $row['id'];
    }
    return $ids;
}

function notify_admins(PDO $pdo, string $message, ?string $link = null): void
{
    foreach (admin_user_ids($pdo) as $adminId) {
        record_notification($pdo, $adminId, $message, $link);
    }
}

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
    $redirectTarget = trim($_POST['redirect'] ?? '', '/');

    try {
        switch ($action) {
            case 'create_service':
                require_login('admin');
                $name = trim($_POST['name'] ?? '');
                $price = (float) ($_POST['price'] ?? 0);
                $description = trim($_POST['description'] ?? '');
                $builder = trim($_POST['form_builder'] ?? '');
                $billing = $_POST['billing_interval'] ?? 'one_time';
                if (!in_array($billing, ['one_time', 'monthly', 'annual'], true)) {
                    $billing = 'one_time';
                }
                if ($name === '') {
                    throw new RuntimeException('Service name is required.');
                }
                $schema = $builder !== '' ? parse_builder_lines($builder) : json_encode([], JSON_THROW_ON_ERROR);
                $now = (new \DateTimeImmutable())->format(\DateTimeInterface::ATOM);
                $stmt = $pdo->prepare('INSERT INTO services (name, description, price, form_schema, billing_interval, active, created_by, created_at, updated_at) VALUES (:name, :description, :price, :form_schema, :billing, :active, :created_by, :created_at, :updated_at)');
                $stmt->execute([
                    'name' => $name,
                    'description' => $description,
                    'price' => $price,
                    'form_schema' => $schema,
                    'billing' => $billing,
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
                $billing = $_POST['billing_interval'] ?? 'one_time';
                if (!in_array($billing, ['one_time', 'monthly', 'annual'], true)) {
                    $billing = 'one_time';
                }
                if ($serviceId <= 0) {
                    throw new RuntimeException('Invalid service specified.');
                }
                $schema = $builder !== '' ? parse_builder_lines($builder) : json_encode([], JSON_THROW_ON_ERROR);
                $stmt = $pdo->prepare('UPDATE services SET name = :name, description = :description, price = :price, form_schema = :form_schema, billing_interval = :billing, active = :active, updated_at = :updated_at WHERE id = :id');
                $stmt->execute([
                    'id' => $serviceId,
                    'name' => $name,
                    'description' => $description,
                    'price' => $price,
                    'form_schema' => $schema,
                    'billing' => $billing,
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
                $companyName = trim($_POST['company_name'] ?? '');
                $primaryColor = trim($_POST['brand_primary_color'] ?? '#3b82f6');
                $fontFamily = trim($_POST['brand_font_family'] ?? 'Inter, sans-serif');
                $supportEmail = trim($_POST['support_email'] ?? '');
                $logoInput = trim($_POST['brand_logo_url'] ?? '');
                $clearLogo = isset($_POST['clear_logo']) && $_POST['clear_logo'] === '1';
                $currentLogo = get_setting('brand_logo_url', '');
                $logoPath = $clearLogo ? '' : $logoInput;

                if (!empty($_FILES['brand_logo_file']) && ($_FILES['brand_logo_file']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
                    $file = $_FILES['brand_logo_file'];
                    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
                        throw new RuntimeException('Unable to upload logo. Please try again.');
                    }

                    $tmp = $file['tmp_name'] ?? '';
                    if ($tmp === '' || !is_uploaded_file($tmp)) {
                        throw new RuntimeException('Invalid logo upload.');
                    }

                    $mime = mime_content_type($tmp) ?: '';
                    $allowed = [
                        'image/png' => 'png',
                        'image/jpeg' => 'jpg',
                        'image/jpg' => 'jpg',
                        'image/webp' => 'webp',
                        'image/svg+xml' => 'svg',
                    ];

                    if (!isset($allowed[$mime])) {
                        throw new RuntimeException('Logo must be PNG, JPG, SVG, or WebP.');
                    }

                    $uploadDir = __DIR__ . '/static/uploads';
                    if (!is_dir($uploadDir) && !mkdir($uploadDir, 0775, true) && !is_dir($uploadDir)) {
                        throw new RuntimeException('Unable to prepare logo directory.');
                    }

                    $filename = sprintf('brand-%s-%s.%s', (new DateTimeImmutable())->format('YmdHis'), bin2hex(random_bytes(4)), $allowed[$mime]);
                    $destination = $uploadDir . '/' . $filename;

                    if (!move_uploaded_file($tmp, $destination)) {
                        throw new RuntimeException('Failed to store the uploaded logo.');
                    }

                    $logoPath = 'static/uploads/' . $filename;

                    if ($currentLogo !== '' && $currentLogo !== $logoPath) {
                        $existingPath = local_asset_path($currentLogo);
                        if ($existingPath && is_file($existingPath)) {
                            @unlink($existingPath);
                        }
                    }
                } elseif ($clearLogo && $currentLogo !== '') {
                    $existingPath = local_asset_path($currentLogo);
                    if ($existingPath && is_file($existingPath)) {
                        @unlink($existingPath);
                    }
                }

                $settings = [
                    'company_name' => $companyName !== '' ? $companyName : 'Service Portal',
                    'brand_logo_url' => $logoPath,
                    'brand_primary_color' => $primaryColor !== '' ? $primaryColor : '#3b82f6',
                    'brand_font_family' => $fontFamily !== '' ? $fontFamily : 'Inter, sans-serif',
                    'support_email' => $supportEmail,
                ];

                foreach ($settings as $key => $value) {
                    set_setting($key, $value);
                }
                flash('success', 'Brand settings updated.');
                break;
            case 'save_template':
                require_login('admin');
                $templateId = (int) ($_POST['template_id'] ?? 0);
                $name = trim($_POST['name'] ?? '');
                $slug = trim($_POST['slug'] ?? '');
                $subject = trim($_POST['subject'] ?? '');
                $body = trim($_POST['body'] ?? '');
                if ($name === '' || $slug === '' || $subject === '' || $body === '') {
                    throw new RuntimeException('All template fields are required.');
                }
                $now = (new \DateTimeImmutable())->format(\DateTimeInterface::ATOM);
                if ($templateId > 0) {
                    $stmt = $pdo->prepare('UPDATE email_templates SET name = :name, slug = :slug, subject = :subject, body = :body, updated_at = :updated WHERE id = :id');
                    $stmt->execute([
                        'id' => $templateId,
                        'name' => $name,
                        'slug' => $slug,
                        'subject' => $subject,
                        'body' => $body,
                        'updated' => $now,
                    ]);
                } else {
                    $stmt = $pdo->prepare('INSERT INTO email_templates (name, slug, subject, body, created_at, updated_at) VALUES (:name, :slug, :subject, :body, :created, :updated)');
                    $stmt->execute([
                        'name' => $name,
                        'slug' => $slug,
                        'subject' => $subject,
                        'body' => $body,
                        'created' => $now,
                        'updated' => $now,
                    ]);
                }
                flash('success', 'Email template saved.');
                break;
            case 'delete_template':
                require_login('admin');
                $templateId = (int) ($_POST['template_id'] ?? 0);
                if ($templateId > 0) {
                    $pdo->prepare('DELETE FROM email_templates WHERE id = :id')->execute(['id' => $templateId]);
                    flash('success', 'Template removed.');
                }
                break;
            case 'update_payments':
                require_login('admin');
                set_setting('stripe_publishable_key', $_POST['stripe_publishable_key'] ?? '');
                set_setting('stripe_secret_key', $_POST['stripe_secret_key'] ?? '');
                set_setting('paypal_client_id', $_POST['paypal_client_id'] ?? '');
                set_setting('paypal_client_secret', $_POST['paypal_client_secret'] ?? '');
                flash('success', 'Payment settings saved.');
                break;
            case 'create_admin_user':
                require_login('admin');
                $email = strtolower(trim($_POST['email'] ?? ''));
                $name = trim($_POST['name'] ?? '');
                $password = $_POST['password'] ?? '';
                if ($email === '' || $name === '' || strlen($password) < 8) {
                    throw new RuntimeException('Provide a name, valid email, and a password of at least 8 characters.');
                }
                if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    throw new RuntimeException('Provide a valid email address.');
                }
                $stmt = $pdo->prepare('SELECT COUNT(*) FROM users WHERE email = :email');
                $stmt->execute(['email' => $email]);
                if ((int) $stmt->fetchColumn() > 0) {
                    throw new RuntimeException('That email address is already in use.');
                }
                $now = (new \DateTimeImmutable())->format(\DateTimeInterface::ATOM);
                $pdo->prepare('INSERT INTO users (email, password_hash, name, role, created_at, updated_at) VALUES (:email, :password, :name, :role, :created, :updated)')->execute([
                    'email' => $email,
                    'password' => password_hash($password, PASSWORD_DEFAULT),
                    'name' => $name,
                    'role' => 'admin',
                    'created' => $now,
                    'updated' => $now,
                ]);
                flash('success', 'New admin account created.');
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
                $pdo->prepare('INSERT INTO orders (user_id, service_id, payment_method, total_amount, form_data, billing_interval, created_at, updated_at) VALUES (:user_id, :service_id, :payment_method, :total_amount, :form_data, :interval, :created_at, :updated_at)')
                    ->execute([
                        'user_id' => $user['id'],
                        'service_id' => $service['id'],
                        'payment_method' => $paymentMethod,
                        'total_amount' => $service['price'],
                        'form_data' => json_encode($custom, JSON_THROW_ON_ERROR),
                        'interval' => $service['billing_interval'] ?? 'one_time',
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]);
                $orderId = (int) $pdo->lastInsertId();

                $templateData = [
                    '{{name}}' => $user['name'],
                    '{{service}}' => $service['name'],
                    '{{company}}' => get_setting('company_name', 'Service Portal'),
                ];
                $fallbackOrderBody = sprintf("Hi %s,\n\nThanks for your order of %s.", $user['name'], $service['name']);
                send_templated_email($pdo, 'order_confirmation', $templateData, $user['email'], 'Order received', $fallbackOrderBody);
                notify_admins($pdo, 'New order #' . $orderId . ' from ' . $user['name'], url_for('admin/orders'));
                record_notification($pdo, $user['id'], 'Order #' . $orderId . ' placed for ' . $service['name'], url_for('dashboard/orders'));

                if (in_array($service['billing_interval'], ['monthly', 'annual'], true)) {
                    $intervalSpec = $service['billing_interval'] === 'monthly' ? '+1 month' : '+1 year';
                    $nextBilling = (new \DateTimeImmutable())->modify($intervalSpec);
                    $pdo->prepare('INSERT INTO subscriptions (order_id, user_id, service_id, interval, next_billing_at, status, created_at, updated_at) VALUES (:order_id, :user_id, :service_id, :interval, :next_billing_at, :status, :created_at, :updated_at)')->execute([
                        'order_id' => $orderId,
                        'user_id' => $user['id'],
                        'service_id' => $service['id'],
                        'interval' => $service['billing_interval'],
                        'next_billing_at' => $nextBilling->format(\DateTimeInterface::ATOM),
                        'status' => 'active',
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]);
                    $subscriptionId = (int) $pdo->lastInsertId();
                } else {
                    $subscriptionId = null;
                }

                $invoiceStmt = $pdo->prepare('INSERT INTO invoices (subscription_id, order_id, user_id, service_id, total, status, due_at, created_at, updated_at) VALUES (:subscription_id, :order_id, :user_id, :service_id, :total, :status, :due_at, :created_at, :updated_at)');
                $invoiceStmt->execute([
                    'subscription_id' => $subscriptionId,
                    'order_id' => $orderId,
                    'user_id' => $user['id'],
                    'service_id' => $service['id'],
                    'total' => $service['price'],
                    'status' => 'pending',
                    'due_at' => $now,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);

                flash('success', 'Thanks! Your order has been submitted. We will follow up shortly.');
                break;
            case 'update_order_status':
                require_login('admin');
                $orderId = (int) ($_POST['order_id'] ?? 0);
                $status = trim($_POST['payment_status'] ?? 'pending');
                $reference = trim($_POST['payment_reference'] ?? '');
                if ($orderId > 0) {
                    $now = (new \DateTimeImmutable())->format(\DateTimeInterface::ATOM);
                    $pdo->prepare('UPDATE orders SET payment_status = :status, payment_reference = :reference, updated_at = :updated_at WHERE id = :id')
                        ->execute([
                            'status' => $status,
                            'reference' => $reference,
                            'updated_at' => $now,
                            'id' => $orderId,
                        ]);
                    if ($status === 'paid') {
                        $invoiceStmt = $pdo->prepare('UPDATE invoices SET status = "paid", paid_at = :paid_at, updated_at = :updated WHERE order_id = :order_id');
                        $invoiceStmt->execute([
                            'paid_at' => $now,
                            'updated' => $now,
                            'order_id' => $orderId,
                        ]);
                        $orderUser = $pdo->prepare('SELECT u.id AS user_id, u.email, u.name, s.name AS service_name, i.id AS invoice_id FROM orders o JOIN users u ON u.id = o.user_id JOIN services s ON s.id = o.service_id LEFT JOIN invoices i ON i.order_id = o.id WHERE o.id = :id LIMIT 1');
                        $orderUser->execute(['id' => $orderId]);
                        if ($row = $orderUser->fetch()) {
                            $paymentReplacements = [
                                '{{name}}' => $row['name'],
                                '{{service}}' => $row['service_name'],
                                '{{invoice}}' => (string) $row['invoice_id'],
                                '{{company}}' => get_setting('company_name', 'Service Portal'),
                            ];
                            $paymentBody = sprintf("Hi %s,\n\nWe've recorded your payment for invoice #%s covering %s.", $row['name'], $row['invoice_id'], $row['service_name']);
                            send_templated_email($pdo, 'invoice_payment_success', $paymentReplacements, $row['email'], 'Payment received', $paymentBody);
                            record_notification($pdo, (int) $row['user_id'], 'Invoice #' . $row['invoice_id'] . ' paid successfully.', url_for('dashboard/orders'));
                        }
                    }
                    flash('success', 'Order updated.');
                }
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

                notify_admins($pdo, 'New ticket #' . $ticketId . ' from ' . $user['name'], url_for('admin/tickets'));
                record_notification($pdo, $user['id'], 'Ticket #' . $ticketId . ' created', url_for('dashboard/tickets'));
                flash('success', 'Support ticket created. We will be in touch soon.');
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

                $ticket = $pdo->prepare('SELECT t.subject, u.email, u.name, u.id AS user_id FROM tickets t JOIN users u ON u.id = t.user_id WHERE t.id = :id');
                $ticket->execute(['id' => $ticketId]);
                if ($row = $ticket->fetch()) {
                    $ticketReplacements = [
                        '{{name}}' => $row['name'],
                        '{{subject}}' => $row['subject'],
                        '{{message}}' => $message,
                        '{{company}}' => get_setting('company_name', 'Service Portal'),
                    ];
                    $ticketBody = sprintf("Hi %s,\n\nWe've replied to your ticket '%s':\n\n%s", $row['name'], $row['subject'], $message);
                    send_templated_email($pdo, 'ticket_reply', $ticketReplacements, $row['email'], 'Ticket update', $ticketBody);
                    record_notification($pdo, (int) $row['user_id'], 'New reply on ticket #' . $ticketId, url_for('dashboard/tickets/' . $ticketId));
                }
                flash('success', 'Reply sent.');
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
                notify_admins($pdo, 'Client replied to ticket #' . $ticketId, url_for('admin/tickets/' . $ticketId));
                flash('success', 'Your reply has been posted.');
                break;
            case 'close_ticket':
                $ticketId = (int) ($_POST['ticket_id'] ?? 0);
                if ($ticketId > 0) {
                    $pdo->prepare('UPDATE tickets SET status = "closed", updated_at = :updated WHERE id = :id AND user_id = :user_id')
                        ->execute([
                            'updated' => (new \DateTimeImmutable())->format(\DateTimeInterface::ATOM),
                            'id' => $ticketId,
                            'user_id' => $user['id'],
                        ]);
                    flash('success', 'Ticket closed.');
                }
                break;
            case 'bulk_ticket_action':
                $ticketIds = array_values(array_unique(array_filter(array_map('intval', $_POST['ticket_ids'] ?? []))));
                $mode = $_POST['mode'] ?? 'close';
                if (!in_array($mode, ['close', 'delete'], true)) {
                    $mode = 'close';
                }
                if (!$ticketIds) {
                    throw new RuntimeException('Select at least one ticket to manage.');
                }
                $allowedIds = $ticketIds;
                if (!is_admin($user)) {
                    $placeholders = implode(',', array_fill(0, count($ticketIds), '?'));
                    $check = $pdo->prepare("SELECT id FROM tickets WHERE id IN ($placeholders) AND user_id = ?");
                    $params = $ticketIds;
                    $params[] = $user['id'];
                    $check->execute($params);
                    $allowedIds = array_map('intval', array_column($check->fetchAll(), 'id'));
                }
                if (!$allowedIds) {
                    throw new RuntimeException('No permitted tickets were selected.');
                }
                $now = (new \DateTimeImmutable())->format(\DateTimeInterface::ATOM);
                if ($mode === 'delete') {
                    $deleteMessages = $pdo->prepare('DELETE FROM ticket_messages WHERE ticket_id = :id');
                    if (is_admin($user)) {
                        $deleteTicket = $pdo->prepare('DELETE FROM tickets WHERE id = :id');
                        foreach ($allowedIds as $ticketId) {
                            $deleteMessages->execute(['id' => $ticketId]);
                            $deleteTicket->execute(['id' => $ticketId]);
                        }
                    } else {
                        $deleteTicket = $pdo->prepare('DELETE FROM tickets WHERE id = :id AND user_id = :user');
                        foreach ($allowedIds as $ticketId) {
                            $deleteMessages->execute(['id' => $ticketId]);
                            $deleteTicket->execute(['id' => $ticketId, 'user' => $user['id']]);
                        }
                    }
                    flash('success', 'Selected tickets deleted.');
                } else {
                    if (is_admin($user)) {
                        $updateTicket = $pdo->prepare("UPDATE tickets SET status = 'closed', updated_at = :updated WHERE id = :id");
                        foreach ($allowedIds as $ticketId) {
                            $updateTicket->execute([
                                'updated' => $now,
                                'id' => $ticketId,
                            ]);
                        }
                    } else {
                        $updateTicket = $pdo->prepare("UPDATE tickets SET status = 'closed', updated_at = :updated WHERE id = :id AND user_id = :user");
                        foreach ($allowedIds as $ticketId) {
                            $updateTicket->execute([
                                'updated' => $now,
                                'id' => $ticketId,
                                'user' => $user['id'],
                            ]);
                        }
                    }
                    flash('success', 'Selected tickets closed.');
                }
                break;
            case 'delete_ticket':
                $ticketId = (int) ($_POST['ticket_id'] ?? 0);
                if ($ticketId > 0) {
                    if (is_admin($user)) {
                        $pdo->prepare('DELETE FROM ticket_messages WHERE ticket_id = :id')->execute(['id' => $ticketId]);
                        $pdo->prepare('DELETE FROM tickets WHERE id = :id')->execute(['id' => $ticketId]);
                    } else {
                        $owner = $pdo->prepare('SELECT user_id FROM tickets WHERE id = :id LIMIT 1');
                        $owner->execute(['id' => $ticketId]);
                        $ownerId = (int) $owner->fetchColumn();
                        if ($ownerId !== (int) $user['id']) {
                            throw new RuntimeException('You cannot delete that ticket.');
                        }
                        $pdo->prepare('DELETE FROM ticket_messages WHERE ticket_id = :id')->execute(['id' => $ticketId]);
                        $pdo->prepare('DELETE FROM tickets WHERE id = :id AND user_id = :user')
                            ->execute(['id' => $ticketId, 'user' => $user['id']]);
                    }
                    flash('success', 'Ticket deleted.');
                }
                break;
            case 'mark_notifications':
                mark_notifications_read($pdo, (int) $user['id']);
                flash('success', 'Notifications cleared.');
                break;
        }
    } catch (Throwable $e) {
        flash('error', $e->getMessage());
    }

    if ($redirectTarget === '') {
        $redirectTarget = $user['role'] === 'admin' ? 'admin/overview' : 'dashboard';
    }
    redirect($redirectTarget);
}

$notifications = get_notifications($pdo, (int) $user['id']);
$unreadNotifications = array_filter($notifications, fn($notification) => empty($notification['read_at']));

if ($user['role'] === 'admin') {
    $adminViews = ['overview', 'services', 'orders', 'invoices', 'tickets', 'ticket', 'clients', 'automations', 'payments', 'administrators', 'forms', 'settings'];
    if (!in_array($view, $adminViews, true)) {
        $view = 'overview';
    }

    $services = $pdo->query('SELECT * FROM services ORDER BY created_at DESC')->fetchAll();
    $clients = $pdo->query("SELECT id, name, email, company, created_at FROM users WHERE role = 'client' ORDER BY created_at DESC")->fetchAll();
    $admins = $pdo->query("SELECT id, name, email, created_at FROM users WHERE role = 'admin' ORDER BY created_at DESC")->fetchAll();
    $orders = $pdo->query('SELECT o.*, s.name AS service_name, u.name AS client_name FROM orders o JOIN services s ON s.id = o.service_id JOIN users u ON u.id = o.user_id ORDER BY o.created_at DESC')->fetchAll();
    $tickets = $pdo->query('SELECT t.*, u.name AS client_name FROM tickets t JOIN users u ON u.id = t.user_id ORDER BY t.updated_at DESC')->fetchAll();
    $ticketMessages = $pdo->query('SELECT m.*, u.name FROM ticket_messages m JOIN users u ON u.id = m.user_id ORDER BY m.created_at')->fetchAll();
    $messagesByTicket = [];
    foreach ($ticketMessages as $message) {
        $messagesByTicket[$message['ticket_id']][] = $message;
    }
    $templates = $pdo->query('SELECT * FROM email_templates ORDER BY name')->fetchAll();
    $invoices = $pdo->query('SELECT i.*, u.name AS client_name, s.name AS service_name FROM invoices i JOIN users u ON u.id = i.user_id JOIN services s ON s.id = i.service_id ORDER BY i.created_at DESC')->fetchAll();

    $selectedTicket = null;
    $selectedMessages = [];
    if ($view === 'ticket' && $ticketDetailId) {
        foreach ($tickets as $ticket) {
            if ((int) $ticket['id'] === $ticketDetailId) {
                $selectedTicket = $ticket;
                $selectedMessages = $messagesByTicket[$ticket['id']] ?? [];
                break;
            }
        }
        if (!$selectedTicket) {
            flash('error', 'That ticket could not be found.');
            redirect('admin/tickets');
        }
    }

    $searchAction = $view === 'overview' ? url_for('admin/overview') : url_for('admin/' . ($view === 'ticket' ? 'tickets' : $view));
    include __DIR__ . '/admin/dashboard.php';
    exit;
}

$services = $pdo->query('SELECT * FROM services WHERE active = 1 ORDER BY name')->fetchAll();
$orderStmt = $pdo->prepare('SELECT o.*, s.name AS service_name FROM orders o JOIN services s ON s.id = o.service_id WHERE o.user_id = :user ORDER BY o.created_at DESC');
$orderStmt->execute(['user' => $user['id']]);
$orders = $orderStmt->fetchAll();

$invoiceStmt = $pdo->prepare('SELECT i.*, s.name AS service_name FROM invoices i JOIN services s ON s.id = i.service_id WHERE i.user_id = :user ORDER BY i.created_at DESC');
$invoiceStmt->execute(['user' => $user['id']]);
$invoices = $invoiceStmt->fetchAll();

$ticketStmt = $pdo->prepare('SELECT * FROM tickets WHERE user_id = :user ORDER BY updated_at DESC');
$ticketStmt->execute(['user' => $user['id']]);
$tickets = $ticketStmt->fetchAll();

$messagesStmt = $pdo->prepare('SELECT m.*, u.name FROM ticket_messages m JOIN users u ON u.id = m.user_id WHERE m.ticket_id IN (SELECT id FROM tickets WHERE user_id = :user) ORDER BY m.created_at');
$messagesStmt->execute(['user' => $user['id']]);
$messagesByTicket = [];
foreach ($messagesStmt->fetchAll() as $message) {
    $messagesByTicket[$message['ticket_id']][] = $message;
}

$clientViews = ['overview', 'services', 'forms', 'orders', 'invoices', 'tickets', 'ticket'];
if (!in_array($view, $clientViews, true)) {
    $view = 'overview';
}

$selectedTicket = null;
$selectedMessages = [];
if ($view === 'ticket' && $ticketDetailId) {
    foreach ($tickets as $ticket) {
        if ((int) $ticket['id'] === $ticketDetailId) {
            $selectedTicket = $ticket;
            $selectedMessages = $messagesByTicket[$ticket['id']] ?? [];
            break;
        }
    }
    if (!$selectedTicket) {
        flash('error', 'That ticket could not be found.');
        redirect('dashboard/tickets');
    }
}

$clientSidebar = [
    ['key' => 'overview', 'label' => 'Overview', 'href' => url_for('dashboard')],
    ['key' => 'services', 'label' => 'Services', 'href' => url_for('dashboard/services')],
    ['key' => 'forms', 'label' => 'Forms', 'href' => url_for('dashboard/forms')],
    ['key' => 'orders', 'label' => 'Orders', 'href' => url_for('dashboard/orders')],
    ['key' => 'invoices', 'label' => 'Invoices', 'href' => url_for('dashboard/invoices')],
    ['key' => 'tickets', 'label' => 'Support', 'href' => url_for('dashboard/tickets')],
];

$pageTitleMap = [
    'overview' => 'Dashboard',
    'services' => 'Services',
    'forms' => 'Forms',
    'orders' => 'Orders',
    'invoices' => 'Invoices',
    'tickets' => 'Support tickets',
];
$pageTitle = $pageTitleMap[$view] ?? 'Dashboard';
if ($view === 'ticket' && $selectedTicket) {
    $pageTitle = 'Ticket #' . $selectedTicket['id'];
}

$activeKey = $view === 'ticket' ? 'tickets' : $view;
$clientView = $view;
$searchAction = $view === 'overview' ? url_for('dashboard') : url_for('dashboard/' . ($view === 'ticket' ? 'tickets' : $view));

include __DIR__ . '/templates/client/layout.php';
