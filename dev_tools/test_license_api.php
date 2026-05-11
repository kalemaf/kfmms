<?php
/**
 * License API Test Script
 * Run this to test the license_api.php endpoint
 */

ini_set('display_errors', 1);
error_reporting(E_ALL);

// Load dependencies first
require_once 'config.inc.php';
require_once 'common.inc.php';

echo "=== License API Test ===\n\n";

// Start session for testing
session_start();

// Simulate a session
$_SESSION['user_id'] = 1;
$_SESSION['role'] = 'admin';

// Test 1: Check GET status
echo "TEST 1: Get status of company 1\n";
$_REQUEST['action'] = 'get_status';
$_GET['company_id'] = 1;

echo "DB available: " . ($db_available ? 'yes' : 'no') . "\n";

// Check if system_control table exists
if ($db_type === 'sqlite') {
    $stmt = $connection->prepare("SELECT name FROM sqlite_master WHERE type='table' AND name='system_control'");
    $stmt->execute();
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    echo "system_control table exists: " . ($result ? 'yes' : 'no') . "\n";
    
    // Check if company_licenses table exists
    $stmt = $connection->prepare("SELECT name FROM sqlite_master WHERE type='table' AND name='company_licenses'");
    $stmt->execute();
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    echo "company_licenses table exists: " . ($result ? 'yes' : 'no') . "\n";
    
    // Check if company 1 exists
    $stmt = $connection->prepare("SELECT company_id FROM companies WHERE company_id = 1");
    $stmt->execute();
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    echo "company 1 exists: " . ($result ? 'yes' : 'no') . "\n";
    
    // Check system_control record for company 1
    $stmt = $connection->prepare("SELECT * FROM system_control WHERE company_id = 1");
    $stmt->execute();
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    echo "system_control record for company 1: " . ($result ? 'yes' : 'none') . "\n";
    
    // Check company_licenses record for company 1
    $stmt = $connection->prepare("SELECT * FROM company_licenses WHERE company_id = 1");
    $stmt->execute();
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    echo "company_licenses record for company 1: " . ($result ? 'yes' : 'none') . "\n";
    
    // Test the status query
    $status_query = "SELECT sc.system_activated, sc.system_locked, sc.subscription_status, cl.is_active as license_active, cl.license_key FROM system_control sc LEFT JOIN company_licenses cl ON sc.company_id = cl.company_id AND cl.is_active = 1 WHERE sc.company_id = ?";
    $stmt = $connection->prepare($status_query);
    $company_id = 1;
    $stmt->bindParam(1, $company_id, PDO::PARAM_INT);
    $stmt->execute();
    $status = $stmt->fetch(PDO::FETCH_ASSOC);
    echo "status query result: " . json_encode($status) . "\n";
    
    // Test the update query
    $update_query = "UPDATE system_control SET system_activated = 1, system_locked = 0, lock_reason = NULL, activation_date = CURRENT_TIMESTAMP WHERE company_id = 1";
    $stmt = $connection->prepare($update_query);
    try {
        $result = $stmt->execute();
        echo "update result: " . ($result ? 'success' : 'failed') . "\n";
    } catch (Exception $e) {
        echo "update error: " . $e->getMessage() . "\n";
    }
}

echo "About to require license_api.php\n";

// Load API
require 'license_api.php';

// Test 2: Test invalid company
echo "TEST 2: Get status of invalid company (ID=999999)\n";
$_GET['company_id'] = 999999;

ob_start();
require 'license_api.php';
$output = ob_get_clean();

$response = json_decode($output, true);
if ($response) {
    echo "✅ API returned valid JSON for invalid company\n";
    echo "Response: " . json_encode($response, JSON_PRETTY_PRINT) . "\n\n";
} else {
    echo "❌ API error\n";
    echo "Raw output: '" . $output . "'\n\n";
}

echo "=== Test Complete ===\n";
?>
