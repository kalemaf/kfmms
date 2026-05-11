<?php
/**
 * Debug Tenant Session
 */
require_once 'config.inc.php';
require_once 'common.inc.php';

echo "<h1>Current Session Debug</h1>";

echo "<h2>Session Variables</h2>";
echo "<pre>";
print_r($_SESSION);
echo "</pre>";

echo "<h2>tenant_id() function</h2>";
try {
    $tid = tenant_id();
    echo "tenant_id() = $tid";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}

echo "<h2>User Details</h2>";
if (isset($_SESSION['user_id'])) {
    $user = safe_query_row("SELECT * FROM users WHERE user_id = " . intval($_SESSION['user_id']));
    echo "<pre>";
    print_r($user);
    echo "</pre>";
}