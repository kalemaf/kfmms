<?php
require_once 'config.inc.php';
require_once 'common.inc.php';

$payload = @file_get_contents('php://input');
$sigHeader = $_SERVER['HTTP_STRIPE_SIGNATURE'] ?? '';
$webhookSecret = env('STRIPE_WEBHOOK_SECRET', '');

if (empty($payload)) {
    http_response_code(400);
    echo 'Empty webhook payload';
    exit;
}

if (!empty($webhookSecret)) {
    $event = verify_stripe_signature($payload, $sigHeader, $webhookSecret);
    if ($event === null) {
        http_response_code(400);
        echo 'Webhook signature verification failed';
        exit;
    }
} else {
    $event = json_decode($payload, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        http_response_code(400);
        echo 'Invalid JSON payload';
        exit;
    }
}

$eventType = $event['type'] ?? '';
$result = ['success' => false, 'message' => 'Unhandled event type: ' . $eventType];

switch ($eventType) {
    case 'checkout.session.completed':
        $result = process_stripe_checkout_session_completed($event['data']['object'] ?? []);
        break;
    case 'invoice.payment_succeeded':
        $result = process_stripe_invoice_payment_succeeded($event['data']['object'] ?? []);
        break;
    default:
        // Allow Stripe to send other event types without failing the webhook.
        http_response_code(200);
        echo 'Event ignored: ' . $eventType;
        exit;
}

if (!empty($result['success'])) {
    http_response_code(200);
    echo 'Webhook processed successfully';
    exit;
}

log_event('Stripe webhook failed: ' . ($result['message'] ?? 'unknown'));
http_response_code(400);
echo $result['message'];
exit;
