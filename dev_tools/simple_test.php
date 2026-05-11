<?php
/**
 * Simple License API Test
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

// Start session first
session_start();

// Simulate admin session
$_SESSION['user_id'] = 1;
$_SESSION['role'] = 'admin';

// Simulate activation POST
$_POST['action'] = 'activate';
$_POST['company_id'] = 1;

echo "=== Testing License API ===\n";

// Include config and common
require_once 'config.inc.php';
require_once 'common.inc.php';

echo "Config loaded\n";
echo "DB available: " . ($db_available ? 'yes' : 'no') . "\n";
echo "Connection: " . ($connection ? 'exists' : 'null') . "\n";

// Now test the API by including it
echo "About to include license_api.php...\n";

ob_start();
include 'license_api.php';
$output = ob_get_clean();

echo "API Output:\n";
echo $output;
echo "\n=== Test Complete ===\n";
?>