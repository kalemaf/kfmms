<?php
// Test predictive_maintenance_dashboard.php directly
error_reporting(E_ALL);
ini_set('display_errors', '1');

ob_start();  // Start output buffering to catch output

$_SESSION['tenant_id'] = 1;

echo "Loading predictive_maintenance_dashboard.php...\n";

try {
    include 'predictive_maintenance_dashboard.php';
    $output = ob_get_clean();
    echo "✓ File loaded successfully\n";
    echo "Output length: " . strlen($output) . " bytes\n";
} catch (Throwable $e) {
    ob_end_clean();
    echo "✗ Error loading file:\n";
    echo "Type: " . get_class($e) . "\n";
    echo "Message: " . $e->getMessage() . "\n";
    echo "File: " . $e->getFile() . "\n";
    echo "Line: " . $e->getLine() . "\n";
    echo "\nFull backtrace:\n";
    echo $e->getTraceAsString() . "\n";
}
