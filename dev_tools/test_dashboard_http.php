<?php
/**
 * Test dashboard with error logging to file
 */
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/test_dashboard_error.log');

// Simulate logged-in session
$_SESSION['user_id'] = 1;
$_SESSION['role'] = 'admin';
$_SESSION['tenant_id'] = 1;

file_put_contents(__DIR__ . '/test_dashboard_debug.log', "Starting dashboard test...\n", FILE_APPEND);

// Capture all errors
set_error_handler(function($errno, $errstr, $errfile, $errline) {
    $msg = "ERROR ($errno): $errstr in $errfile:$errline\n";
    file_put_contents(__DIR__ . '/test_dashboard_debug.log', $msg, FILE_APPEND);
    return false;
});

try {
    file_put_contents(__DIR__ . '/test_dashboard_debug.log', "About to include dashboard...\n", FILE_APPEND);
    include 'technician_performance_dashboard.php';
    file_put_contents(__DIR__ . '/test_dashboard_debug.log', "Dashboard included successfully\n", FILE_APPEND);
} catch (Throwable $e) {
    $msg = "EXCEPTION: " . $e->getMessage() . " at " . $e->getFile() . ":" . $e->getLine() . "\n";
    $msg .= $e->getTraceAsString() . "\n";
    file_put_contents(__DIR__ . '/test_dashboard_debug.log', $msg, FILE_APPEND);
}

// Check for fatal errors
register_shutdown_function(function() {
    $error = error_get_last();
    if ($error) {
        $msg = "SHUTDOWN ERROR (" . $error['type'] . "): " . $error['message'] . " at " . $error['file'] . ":" . $error['line'] . "\n";
        file_put_contents(__DIR__ . '/test_dashboard_debug.log', $msg, FILE_APPEND);
    } else {
        file_put_contents(__DIR__ . '/test_dashboard_debug.log', "Shutdown complete - no fatal errors\n", FILE_APPEND);
    }
});

echo "Test complete - check test_dashboard_debug.log\n";
?>
