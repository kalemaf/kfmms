<?php
// Test equipment_health.php
error_reporting(E_ALL);
ini_set('display_errors', '1');

$_SESSION['tenant_id'] = 1;
$_GET['id'] = 1;

echo "Testing equipment_health.php loading...\n\n";

try {
    ob_start();
    include 'equipment_health.php';
    $output = ob_get_clean();
    echo "✓ File loaded\n";
    echo "Output length: " . strlen($output) . "\n";
} catch (Throwable $e) {
    ob_end_clean();
    echo "✗ Error: " . $e->getMessage() . "\n";
    echo "Line: " . $e->getLine() . "\n";
    echo $e->getTraceAsString() . "\n";
}
