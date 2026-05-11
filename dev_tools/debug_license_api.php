<?php
/**
 * Debug License API - Direct Output
 * Simulates activation request and captures output/errors
 */

ini_set('display_errors', 1);
error_reporting(E_ALL);

// Enable error logging
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/license_api_errors.log');

// Start session
session_start();

// Simulate admin session
$_SESSION['user_id'] = 1;
$_SESSION['role'] = 'admin';

// Simulate activation POST
$_POST['action'] = 'activate';
$_POST['company_id'] = 1;

// Capture all output
ob_start();

try {
    // Include the API
    include 'license_api.php';
    
    $output = ob_get_clean();
    echo "=== DIRECT OUTPUT ===\n";
    echo "Length: " . strlen($output) . "\n";
    echo "Content:\n";
    echo $output . "\n";
    echo "=== END OUTPUT ===\n";
    
} catch (Exception $e) {
    ob_end_clean();
    echo "EXCEPTION: " . $e->getMessage() . "\n";
    echo "File: " . $e->getFile() . "\n";
    echo "Line: " . $e->getLine() . "\n";
}
?>
