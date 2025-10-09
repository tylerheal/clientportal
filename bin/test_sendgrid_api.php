#!/usr/bin/env php
<?php
declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

$recipient = $argv[1] ?? null;
if ($recipient === null || !filter_var($recipient, FILTER_VALIDATE_EMAIL)) {
    fwrite(
        STDERR,
        "Usage: php bin/test_sendgrid_api.php recipient@example.com\n"
        . "Set SENDGRID_API_KEY (and optionally SENDGRID_REGION=eu) before running.\n"
    );
    exit(1);
}

$apiKey = getenv('SENDGRID_API_KEY');
if ($apiKey === false || trim((string) $apiKey) === '') {
    fwrite(STDERR, "SENDGRID_API_KEY environment variable is not set.\n");
    exit(1);
}

$fromAddress = getenv('SENDGRID_TEST_FROM') ?: 'test@example.com';
$fromName = getenv('SENDGRID_TEST_FROM_NAME') ?: 'SendGrid Tester';
$region = strtolower((string) (getenv('SENDGRID_REGION') ?: 'us'));

$email = new \SendGrid\Mail\Mail();
$email->setFrom($fromAddress, $fromName);
$email->setSubject('SendGrid API connectivity test');
$email->addTo($recipient);
$email->addContent('text/plain', 'and easy to do anywhere, even with PHP');
$email->addContent('text/html', '<strong>and easy to do anywhere, even with PHP</strong>');

$options = [];
if ($region === 'eu') {
    $options['host'] = 'https://api.eu.sendgrid.com';
}

$sendgrid = new \SendGrid($apiKey, $options);
if ($region === 'eu' && method_exists($sendgrid, 'setDataResidency')) {
    $sendgrid->setDataResidency('eu');
}

try {
    $response = $sendgrid->send($email);
} catch (Throwable $error) {
    fwrite(STDERR, 'SendGrid API call failed: ' . $error->getMessage() . "\n");
    exit(1);
}

$status = $response->statusCode();
if ($status < 200 || $status >= 300) {
    fwrite(
        STDERR,
        sprintf(
            "SendGrid API returned HTTP %d\nHeaders: %s\nBody: %s\n",
            $status,
            json_encode($response->headers(), JSON_PRETTY_PRINT),
            $response->body()
        )
    );
    exit(1);
}

echo "SendGrid API responded with HTTP {$status}. Email queued successfully.\n";
exit(0);
