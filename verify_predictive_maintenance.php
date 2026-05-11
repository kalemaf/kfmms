#!/usr/bin/env php
<?php
/**
 * Verification Test for Predictive Maintenance System
 * Tests all major components to confirm installation and functionality
 */

echo "\n╔════════════════════════════════════════════════════════════════╗\n";
echo "║     PREDICTIVE MAINTENANCE SYSTEM - VERIFICATION TESTS         ║\n";
echo "╚════════════════════════════════════════════════════════════════╝\n\n";

require_once 'config.inc.php';
require_once 'common.inc.php';
require_once 'libraries/predictive_maintenance.php';

$_SESSION['tenant_id'] = 1;
$tenant_id = 1;
$pass_count = 0;
$fail_count = 0;

// Test 1: Database Connection
echo "TEST 1: Database Connection\n";
try {
    global $connection;
    $result = $connection->query("SELECT 1");
    echo "✅ PASS: Database connection successful\n";
    $pass_count++;
} catch (Exception $e) {
    echo "❌ FAIL: " . $e->getMessage() . "\n";
    $fail_count++;
}

// Test 2: Check all tables exist
echo "\nTEST 2: Database Tables\n";
$tables = ['asset_lifecycle', 'condition_monitoring', 'maintenance_schedule', 'part_lifecycle', 'asset_health_metrics', 'predictive_alerts'];
$missing = [];
foreach ($tables as $table) {
    try {
        $result = $connection->query("SELECT COUNT(*) FROM $table");
        $count = $result->fetch(PDO::FETCH_COLUMN);
        echo "  ✅ $table: $count rows\n";
        $pass_count++;
    } catch (Exception $e) {
        echo "  ❌ $table: MISSING\n";
        $missing[] = $table;
        $fail_count++;
    }
}

// Test 3: Core functions exist
echo "\nTEST 3: Core Functions\n";
$functions = [
    'create_predictive_maintenance_tables',
    'calculate_remaining_lifecycle',
    'calculate_usage_percentage',
    'get_health_status',
    'create_predictive_alert',
    'check_all_assets_for_alerts',
    'get_critical_alerts',
    'get_asset_health_overview',
    'get_upcoming_maintenance',
    'get_equipment_condition_trend',
    'health_indicator_html'
];

foreach ($functions as $func) {
    if (function_exists($func)) {
        echo "  ✅ $func\n";
        $pass_count++;
    } else {
        echo "  ❌ $func: NOT FOUND\n";
        $fail_count++;
    }
}

// Note: MTBF, MTTR, OEE calculations are in libraries/metrics.php
// (to avoid function redeclaration conflicts)

// Test 4: Get sample data
echo "\nTEST 4: Sample Data\n";
try {
    $overview = get_asset_health_overview();
    echo "  ✅ Asset Overview: " . $overview['total_assets'] . " assets, " . 
         $overview['healthy'] . " healthy, " . $overview['critical'] . " critical\n";
    echo "  ✅ Fleet Health Score: " . $overview['health_percentage'] . "%\n";
    $pass_count += 2;
} catch (Exception $e) {
    echo "  ❌ Asset Overview failed: " . $e->getMessage() . "\n";
    $fail_count += 2;
}

// Test 5: Get alerts
echo "\nTEST 5: Predictive Alerts\n";
try {
    $alerts = get_critical_alerts(10);
    echo "  ✅ Retrieved " . count($alerts) . " critical alerts\n";
    foreach ($alerts as $alert) {
        echo "     - {$alert['title']} ({$alert['severity']})\n";
    }
    $pass_count++;
} catch (Exception $e) {
    echo "  ❌ Failed to retrieve alerts: " . $e->getMessage() . "\n";
    $fail_count++;
}

// Summary
echo "\n╔════════════════════════════════════════════════════════════════╗\n";
echo "║                    TEST RESULTS SUMMARY                        ║\n";
echo "╠════════════════════════════════════════════════════════════════╣\n";
printf("║ ✅ PASSED: %-50s ║\n", $pass_count);
printf("║ ❌ FAILED: %-50s ║\n", $fail_count);
echo "╚════════════════════════════════════════════════════════════════╝\n\n";

if ($fail_count === 0) {
    echo "🎉 ALL TESTS PASSED! Predictive maintenance system is fully operational.\n\n";
    exit(0);
} else {
    echo "⚠️  Some tests failed. Please review the errors above.\n\n";
    exit(1);
}
?>
