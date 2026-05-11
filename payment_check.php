<?php
require_once 'config.inc.php';
require_once 'common.inc.php';

$paymentConfig = get_payment_provider_config();
$paypalConfig = get_paypal_config();

$provider = $paymentConfig['provider'] ?? 'manual';
$paypalEnabled = $provider === 'paypal';
$payPalCredentialStatus = [
    'client_id' => !empty($paypalConfig['client_id']),
    'secret' => !empty($paypalConfig['secret']),
    'webhook_id' => !empty($paypalConfig['webhook_id']),
];

$hasPaymentOrders = false;
$paymentOrdersStatus = 'Unknown';
if ($db_available && is_object($connection)) {
    if ($db_type === 'sqlite') {
        $result = $connection->query("SELECT name FROM sqlite_master WHERE type='table' AND name='payment_orders'");
        $hasPaymentOrders = $result !== false && $result->fetch();
    } else {
        $result = $connection->query("SHOW TABLES LIKE 'payment_orders'");
        $hasPaymentOrders = $result !== false && $result->num_rows > 0;
    }
    $paymentOrdersStatus = $hasPaymentOrders ? 'Present' : 'Missing';
}

function safe($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

?><!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payment Provider Check</title>
    <style>
        body { font-family: Arial, sans-serif; background: #f4f7fb; color: #1f2937; margin: 0; padding: 32px; }
        .container { max-width: 900px; margin: 0 auto; background: #ffffff; border-radius: 14px; padding: 28px; box-shadow: 0 20px 40px rgba(15, 23, 42, 0.08); }
        h1 { margin-top: 0; }
        table { width: 100%; border-collapse: collapse; margin-top: 18px; }
        th, td { padding: 12px 14px; border: 1px solid #e5e7eb; text-align: left; }
        th { background: #eef2ff; }
        .status-ok { color: #047857; font-weight: 700; }
        .status-missing { color: #b91c1c; font-weight: 700; }
        .note { margin-top: 20px; color: #4b5563; }
        .link { display: inline-block; margin-top: 16px; color: #2563eb; text-decoration: none; }
    </style>
</head>
<body>
    <div class="container">
        <h1>Payment Provider Diagnostics</h1>
        <p>Use this page to verify whether the production deployment is configured for PayPal and whether the payment_orders schema is available.</p>

        <table>
            <tr><th>Environment</th><td><?php echo safe($app_env ?? env('APP_ENV', 'production')); ?></td></tr>
            <tr><th>Payment Provider</th><td><?php echo safe($provider); ?></td></tr>
            <tr><th>PayPal enabled</th><td><?php echo $paypalEnabled ? '<span class="status-ok">Yes</span>' : '<span class="status-missing">No</span>'; ?></td></tr>
            <tr><th>Detected DB type</th><td><?php echo safe($db_type ?? 'unknown'); ?></td></tr>
            <tr><th>payment_orders table</th><td><?php echo $paymentOrdersStatus === 'Present' ? '<span class="status-ok">Present</span>' : '<span class="status-missing">Missing</span>'; ?></td></tr>
        </table>

        <h2>PayPal credential status</h2>
        <table>
            <tr><th>PAYPAL_CLIENT_ID</th><td><?php echo $payPalCredentialStatus['client_id'] ? '<span class="status-ok">Set</span>' : '<span class="status-missing">Missing</span>'; ?></td></tr>
            <tr><th>PAYPAL_SECRET</th><td><?php echo $payPalCredentialStatus['secret'] ? '<span class="status-ok">Set</span>' : '<span class="status-missing">Missing</span>'; ?></td></tr>
            <tr><th>PAYPAL_WEBHOOK_ID</th><td><?php echo $payPalCredentialStatus['webhook_id'] ? '<span class="status-ok">Set</span>' : '<span class="status-missing">Missing</span>'; ?></td></tr>
            <tr><th>APP_URL</th><td><?php echo safe($app_url ?? env('APP_URL', 'http://127.0.0.1:8000')); ?></td></tr>
            <tr><th>PayPal return URL</th><td><?php echo safe($paypalConfig['success_url'] ?? ''); ?></td></tr>
            <tr><th>PayPal cancel URL</th><td><?php echo safe($paypalConfig['cancel_url'] ?? ''); ?></td></tr>
        </table>

        <div class="note">
            If <strong>Payment Provider</strong> is not <code>paypal</code>, the license gate will not show the PayPal checkout button.
            If the <strong>payment_orders</strong> table is missing, run <code>php migrations/run_pending_migrations.php</code> to apply the new migration.
        </div>

        <a class="link" href="license_gate.php">Back to License Gate</a>
    </div>
</body>
</html>
