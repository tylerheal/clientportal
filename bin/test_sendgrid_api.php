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
$apiKey = trim((string) $apiKey);

$fromAddress = getenv('SENDGRID_TEST_FROM') ?: 'test@example.com';
$fromName = getenv('SENDGRID_TEST_FROM_NAME') ?: 'SendGrid Tester';
$region = strtolower((string) (getenv('SENDGRID_REGION') ?: 'us'));

$email = new \SendGrid\Mail\Mail();
$email->setFrom($fromAddress, $fromName);
$email->setSubject('SendGrid API connectivity test');
$email->addTo($recipient);
$email->addContent('text/plain', 'and easy to do anywhere, even with PHP');
$email->addContent('text/html', '<strong>and easy to do anywhere, even with PHP</strong>');

$normalizeRegion = static function (string $value): string {
    $value = strtolower(trim($value));
    return $value === 'eu' ? 'eu' : 'global';
};

$resolvedRegion = $normalizeRegion($region);
$createClient = static function (string $targetRegion) use ($apiKey): \SendGrid {
    $normalized = strtolower($targetRegion) === 'eu' ? 'eu' : 'global';
    $options = [];
    if ($normalized === 'eu') {
        $options['host'] = 'https://api.eu.sendgrid.com';
    }
    $client = new \SendGrid($apiKey, $options);
    if ($normalized === 'eu' && method_exists($client, 'setDataResidency')) {
        $client->setDataResidency('eu');
    }
    return $client;
};

try {
    $response = $createClient($resolvedRegion)->send($email);
} catch (Throwable $error) {
    fwrite(STDERR, 'SendGrid API call failed: ' . $error->getMessage() . "\n");
    exit(1);
}

$status = $response->statusCode();
if ($status >= 200 && $status < 300) {
    echo "SendGrid API responded with HTTP {$status}. Email queued successfully.\n";
    exit(0);
}

$errorBody = trim((string) $response->body());
$isRegionalBlock = $status === 401 && stripos($errorBody, 'regional attribute') !== false;

if ($isRegionalBlock && $resolvedRegion !== 'eu') {
    try {
        $euResponse = $createClient('eu')->send($email);
    } catch (Throwable $retryError) {
        fwrite(
            STDERR,
            'Initial SendGrid request returned HTTP 401 due to a regional attribute mismatch and the EU retry failed: ' . $retryError->getMessage() . "\n"
            . "Switch to the EU region in Settings → Email delivery or export SENDGRID_REGION=eu before retrying.\n"
        );
        exit(1);
    }

    $euStatus = $euResponse->statusCode();
    if ($euStatus >= 200 && $euStatus < 300) {
        echo "SendGrid API responded with HTTP {$euStatus} after retrying the EU endpoint. Email queued successfully.\n";
        fwrite(
            STDERR,
            "Update SENDGRID_REGION=eu (or pick the EU region in Settings → Email delivery) to avoid the initial 401 response.\n"
        );
        exit(0);
    }

    $euBody = trim((string) $euResponse->body());
    fwrite(
        STDERR,
        sprintf(
            "SendGrid EU retry returned HTTP %d%s. Switch to the EU region in Settings → Email delivery or export SENDGRID_REGION=eu.\n",
            $euStatus,
            $euBody !== '' ? ': ' . $euBody : ''
        )
    );
    exit(1);
}

$headers = json_encode($response->headers(), JSON_PRETTY_PRINT);
$body = $response->body();
fwrite(
    STDERR,
    sprintf(
        "SendGrid API returned HTTP %d\nHeaders: %s\nBody: %s\n",
        $status,
        $headers,
        $body
    )
);

if ($isRegionalBlock) {
    fwrite(STDERR, "Switch to the EU region in Settings → Email delivery or export SENDGRID_REGION=eu before retrying.\n");
}

if ($status === 401) {
    $bodyLower = strtolower((string) $body);
    $decoded = json_decode((string) $body, true);
    if (
        strpos($bodyLower, 'authorization grant is invalid') !== false
        || strpos($bodyLower, 'invalid api key') !== false
        || strpos($bodyLower, 'unauthorized') !== false
    ) {
        fwrite(
            STDERR,
            "SendGrid rejected the request because the API key is invalid, expired, or revoked. Create a fresh key in the SendGrid dashboard and update SENDGRID_API_KEY before retrying.\n"
        );
    }

    if (is_array($decoded) && isset($decoded['errors']) && is_array($decoded['errors'])) {
        foreach ($decoded['errors'] as $error) {
            $detail = strtolower((string) ($error['message'] ?? ''));
            if (strpos($detail, 'ip') !== false && strpos($detail, 'allowlist') !== false) {
                fwrite(
                    STDERR,
                    "The response mentions an IP allowlist. Make sure this server's IP is allowlisted in SendGrid's IP Access Management settings.\n"
                );
            }
            if (strpos($detail, 'permission') !== false) {
                fwrite(
                    STDERR,
                    "Confirm the API key includes the Mail Send permission in the SendGrid dashboard.\n"
                );
            }
        }
    }
}

exit(1);
