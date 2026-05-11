<?php
// Test integration loading
error_reporting(E_ALL);
ini_set('display_errors', '1');

echo "TEST: Starting integration load test\n";

$_SESSION['tenant_id'] = 1;

echo "1. Loading config...\n";
require 'config.inc.php';
echo "   ✓ Config loaded\n";

echo "2. Loading common...\n";
require 'common.inc.php';
echo "   ✓ Common loaded\n";

echo "3. Loading predictive_maintenance...\n";
require 'libraries/predictive_maintenance.php';
echo "   ✓ Predictive maintenance loaded\n";

echo "4. Loading predictive_integration...\n";
require 'libraries/predictive_integration.php';
echo "   ✓ Predictive integration loaded\n";

echo "\nFUNCTIONS CHECK:\n";
echo "   - function_exists('get_asset_health_overview'): " . (function_exists('get_asset_health_overview') ? 'YES' : 'NO') . "\n";
echo "   - function_exists('update_equipment_from_workorder'): " . (function_exists('update_equipment_from_workorder') ? 'YES' : 'NO') . "\n";
echo "   - function_exists('table_exists'): " . (function_exists('table_exists') ? 'YES' : 'NO') . "\n";

echo "\nDATABASE TABLES CHECK:\n";
$tables = ['asset_lifecycle', 'condition_monitoring', 'maintenance_schedule', 'part_lifecycle', 'asset_health_metrics', 'predictive_alerts'];
foreach ($tables as $table) {
    $exists = table_exists($table);
    echo "   - $table: " . ($exists ? 'EXISTS' : 'MISSING') . "\n";
}

echo "\nINTEGRATION TEST COMPLETE!\n";
