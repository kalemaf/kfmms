<?php
// Allow framing for development (localhost/127.0.0.1)
$is_localhost = isset($_SERVER['HTTP_HOST']) && preg_match('/^(localhost|127\.0\.0\.1)(:\d+)?$/', $_SERVER['HTTP_HOST']);
if ($is_localhost) {
    // In development, allow framing from localhost
    header('Access-Control-Allow-Origin: http://127.0.0.1:8000', false);
    header('Access-Control-Allow-Credentials: true', false);
} else {
    // In production, use DENY to prevent clickjacking
    header('X-Frame-Options: DENY', false);
}

// Suppress display of errors to prevent breaking page layout
error_reporting(E_ALL);
ini_set('display_errors', '0');  // Don't display errors on page
ini_set('log_errors', '1');      // Log errors to error_log

require_once 'config.inc.php';
session_save_path($session_save_path);
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once 'common.inc.php';

$paypalConfig = get_paypal_config();
if ($paypalConfig['provider'] !== 'paypal') {
    header('Location: license_gate.php?error=' . urlencode('PayPal is not enabled.'));
    exit;
}

$orderId = trim($_GET['token'] ?? '');
if (empty($orderId)) {
    if (!empty($_GET['cancel']) || !empty($_GET['cancelled'])) {
        header('Location: license_gate.php?error=' . urlencode('PayPal checkout was canceled.'));
        exit;
    }

    header('Location: license_gate.php?error=' . urlencode('Invalid PayPal response.'));
    exit;
}

$paymentOrder = get_payment_order($orderId);
if (empty($paymentOrder)) {
    header('Location: license_gate.php?error=' . urlencode('PayPal order not found.'));
    exit;
}

if ($paymentOrder['status'] === 'completed') {
    header('Location: license_gate.php?after_payment=1&message=' . urlencode('Payment already captured. Please use your license key to activate your subscription.'));
    exit;
}

$captureResult = capture_paypal_order($orderId);
if (!$captureResult['success']) {
    header('Location: license_gate.php?error=' . urlencode('Unable to complete PayPal payment: ' . $captureResult['message']));
    exit;
}

$companyId = !empty($paymentOrder['company_id']) ? (int)$paymentOrder['company_id'] : resolve_company_id_for_payment(
    ['user_id' => $paymentOrder['user_id'] ?? null],
    $paymentOrder['customer_email'] ?? null
);

if (empty($companyId)) {
    header('Location: license_gate.php?error=' . urlencode('Unable to resolve your company after payment. Please contact support.'));
    exit;
}

$result = create_company_license_from_payment(
    $companyId,
    $paymentOrder['plan_key'],
    $captureResult['transaction_id'],
    $orderId,
    $paymentOrder['customer_email'] ?? ''
);

if (!$result['success']) {
    header('Location: license_gate.php?error=' . urlencode('Payment succeeded but license activation failed: ' . $result['message']));
    exit;
}

$notificationEmail = trim($paypalConfig['notification_email'] ?? '');
if (!empty($notificationEmail) && is_valid_email($notificationEmail)) {
    $company = get_company_by_id($companyId);
    $planName = get_subscription_plans()[$paymentOrder['plan_key']]['name'] ?? ucfirst($paymentOrder['plan_key']);
    $receiptData = [
        'company_name' => $company['company_name'] ?? 'Unknown Company',
        'plan_name' => $planName,
        'seats' => get_subscription_plans()[$paymentOrder['plan_key']]['seats'] ?? 'N/A',
        'amount' => $captureResult['amount'],
        'currency' => $captureResult['currency'],
        'provider' => 'PayPal',
        'transaction_id' => $captureResult['transaction_id'],
        'order_id' => $orderId,
        'customer_email' => $paymentOrder['customer_email'] ?? '',
        'license_key' => $result['license_key'],
        'expires_at' => $result['license']['expires_at'] ?? null,
    ];
    $pdfContent = generate_receipt_pdf($receiptData);
    $subject = 'KFMMS Payment Receipt - ' . $orderId;
    $body = "Attached is the PDF receipt for the recent PayPal payment.\n\n" .
            "Order ID: {$orderId}\n" .
            "Transaction ID: {$captureResult['transaction_id']}\n" .
            "Amount: {$captureResult['amount']} {$captureResult['currency']}\n" .
            "Customer: {$paymentOrder['customer_email']}\n" .
            "Plan: {$planName}\n";
    send_system_email($notificationEmail, $subject, $body, null, null, $pdfContent, 'receipt_' . $orderId . '.pdf');
}

header('Location: license_gate.php?after_payment=1&message=' . urlencode('Payment completed successfully. Check your email for the license key.'));
exit;
