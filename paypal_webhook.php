<?php
require_once 'config.inc.php';
require_once 'common.inc.php';

$payload = @file_get_contents('php://input');
if (empty($payload)) {
    http_response_code(400);
    echo 'Empty webhook payload';
    exit;
}

$paypalConfig = get_paypal_config();
if ($paypalConfig['provider'] !== 'paypal') {
    http_response_code(400);
    echo 'PayPal provider is not enabled';
    exit;
}

$headers = [
    'auth_algo' => $_SERVER['HTTP_PAYPAL_AUTH_ALGO'] ?? '',
    'cert_url' => $_SERVER['HTTP_PAYPAL_CERT_URL'] ?? '',
    'transmission_id' => $_SERVER['HTTP_PAYPAL_TRANSMISSION_ID'] ?? '',
    'transmission_sig' => $_SERVER['HTTP_PAYPAL_TRANSMISSION_SIG'] ?? '',
    'transmission_time' => $_SERVER['HTTP_PAYPAL_TRANSMISSION_TIME'] ?? '',
];

$verification = verify_paypal_webhook_signature($headers, $payload, $paypalConfig);
if (empty($verification['success'])) {
    log_event('PayPal webhook verification failed: ' . ($verification['message'] ?? 'unknown'), 'ERROR');
    http_response_code(400);
    echo 'Webhook signature verification failed';
    exit;
}

$event = json_decode($payload, true);
if (json_last_error() !== JSON_ERROR_NONE) {
    http_response_code(400);
    echo 'Invalid JSON payload';
    exit;
}

$result = process_paypal_webhook_event($event);
if (!empty($result['success'])) {
    http_response_code(200);
    echo 'Webhook processed successfully';
    exit;
}

log_event('PayPal webhook processing failed: ' . ($result['message'] ?? 'unknown'), 'ERROR');
http_response_code(400);
echo 'Webhook processing failed: ' . ($result['message'] ?? 'unknown');
exit;
