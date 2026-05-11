<?php
// Test predictive_maintenance_dashboard.php loading
error_reporting(E_ALL);
ini_set('display_errors', '1');

echo "=== Testing predictive_maintenance_dashboard.php ===\n\n";

$_SESSION['tenant_id'] = 1;

echo "Step 1: Loading config and common...\n";
try {
    require 'config.inc.php';
    require 'common.inc.php';
    echo "✓ Config and common loaded\n\n";
} catch (Throwable $e) {
    echo "✗ Error: " . $e->getMessage() . "\n";
    exit(1);
}

echo "Step 2: Loading predictive libraries...\n";
try {
    require 'libraries/predictive_maintenance.php';
    require 'libraries/predictive_integration.php';
    echo "✓ Predictive libraries loaded\n\n";
} catch (Throwable $e) {
    echo "✗ Error: " . $e->getMessage() . "\n";
    exit(1);
}

echo "Step 3: Getting equipment dashboard metrics...\n";
try {
    $metrics = get_equipment_dashboard_metrics();
    echo "✓ Metrics retrieved: ";
    echo json_encode($metrics) . "\n\n";
} catch (Throwable $e) {
    echo "✗ Error: " . $e->getMessage() . "\n";
    echo "Backtrace: " . $e->getTraceAsString() . "\n";
    exit(1);
}

echo "Step 4: Getting critical alerts...\n";
try {
    $alerts = get_critical_alerts(10);
    echo "✓ Alerts retrieved: " . count($alerts) . " alerts\n\n";
} catch (Throwable $e) {
    echo "✗ Error: " . $e->getMessage() . "\n";
    echo "Backtrace: " . $e->getTraceAsString() . "\n";
    exit(1);
}

echo "Step 5: Getting upcoming maintenance...\n";
try {
    $upcoming = get_upcoming_maintenance(30);
    echo "✓ Upcoming maintenance retrieved: " . count($upcoming) . " tasks\n\n";
} catch (Throwable $e) {
    echo "✗ Error: " . $e->getMessage() . "\n";
    echo "Backtrace: " . $e->getTraceAsString() . "\n";
    exit(1);
}

echo "ALL TESTS PASSED!\n";
