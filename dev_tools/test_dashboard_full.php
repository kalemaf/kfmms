<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Simulate a web request
session_start();
$_SESSION['user_id'] = 1;
$_SESSION['role'] = 'admin';
$_SESSION['tenant_id'] = 1;
$_GET = ['period' => 'monthly', 'sort' => 'overall_score'];

try {
    ob_start();
    require_once 'technician_performance_dashboard.php';
    $output = ob_get_clean();
    echo "Dashboard loaded successfully\n";
    echo "Output length: " . strlen($output) . " bytes\n";
    echo "First 500 chars:\n";
    echo substr($output, 0, 500) . "\n";
    echo "...OK\n";
} catch (Throwable $e) {
    echo "FATAL ERROR: " . $e->getMessage() . "\n";
    echo "File: " . $e->getFile() . "\n";
    echo "Line: " . $e->getLine() . "\n";
    echo "\nFull trace:\n";
    echo $e->getTraceAsString() . "\n";
}
?>
