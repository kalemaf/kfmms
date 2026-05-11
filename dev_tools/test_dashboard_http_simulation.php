<?php
/**
 * Test dashboard via HTTP by directly calling the PHP file through web request simulation
 */

echo "=== TESTING DASHBOARD VIA HTTP SIMULATION ===\n\n";

// Simulate a proper HTTP request with session
$_SERVER['REQUEST_METHOD'] = 'GET';
$_SERVER['REQUEST_URI'] = '/technician_performance_dashboard.php';
$_SERVER['SCRIPT_FILENAME'] = __DIR__ . '/technician_performance_dashboard.php';
$_SERVER['HTTP_HOST'] = 'localhost:8000';
$_SERVER['SERVER_NAME'] = 'localhost';
$_SERVER['SERVER_PORT'] = '8000';

// Set up session BEFORE any includes
session_start();
$_SESSION['user_id'] = 1;
$_SESSION['role'] = 'admin';
$_SESSION['tenant_id'] = 1;

echo "Session initialized\n";
echo "User ID: " . $_SESSION['user_id'] . "\n";
echo "Role: " . $_SESSION['role'] . "\n\n";

// Capture output
ob_start();
set_error_handler(function($errno, $errstr, $errfile, $errline) {
    echo "ERROR ($errno): $errstr in $errfile:$errline\n";
    return false;
});

try {
    // Include the dashboard
    include __DIR__ . '/technician_performance_dashboard.php';
    $output = ob_get_clean();
    
    // Check output
    if (empty($output)) {
        echo "ERROR: Dashboard output is empty\n";
    } else if (strpos($output, '<!DOCTYPE html>') === 0) {
        echo "✓ Dashboard HTML rendered successfully\n";
        echo "  Output size: " . strlen($output) . " bytes\n";
        echo "  First 200 chars:\n";
        echo "  " . substr($output, 0, 200) . "...\n";
    } else if (strpos($output, 'ERROR') === 0 || strpos($output, 'Fatal') === 0) {
        echo "✗ Dashboard returned error:\n";
        echo substr($output, 0, 500) . "\n";
    } else {
        echo "? Dashboard output unclear:\n";
        echo substr($output, 0, 300) . "\n";
    }
} catch (Throwable $e) {
    ob_end_clean();
    echo "EXCEPTION: " . $e->getMessage() . "\n";
    echo "File: " . $e->getFile() . ":" . $e->getLine() . "\n";
}

// Check for shutdown errors
register_shutdown_function(function() {
    $error = error_get_last();
    if ($error) {
        echo "\nSHUTDOWN ERROR:\n";
        echo "Type: " . $error['type'] . "\n";
        echo "Message: " . $error['message'] . "\n";
        echo "File: " . $error['file'] . ":" . $error['line'] . "\n";
    }
});

echo "\n=== HTTP SIMULATION TEST COMPLETE ===\n";
?>
