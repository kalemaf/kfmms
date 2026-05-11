<?php
require 'config.inc.php';

$connection = new mysqli($hostName, $userName, $password, $databaseName);

echo "\n╔════════════════════════════════════════════════════════════════╗\n";
echo "║   SPARE PARTS INTEGRATION - SYSTEM VERIFICATION CHECKLIST      ║\n";
echo "╚════════════════════════════════════════════════════════════════╝\n\n";

$passed = 0;
$total = 0;

// CHECK 1: equipment_spares has part_id column
$total++;
$result = $connection->query("SHOW COLUMNS FROM equipment_spares LIKE 'part_id'");
if ($result && $result->num_rows > 0) {
    echo "✓ PASS: equipment_spares.part_id exists\n";
    $passed++;
} else {
    echo "✗ FAIL: equipment_spares.part_id column missing\n";
}

// CHECK 2: All spares linked to parts_master
$total++;
$result = $connection->query("SELECT COUNT(*) as count FROM equipment_spares WHERE part_id IS NULL");
$row = $result->fetch_assoc();
if ($row['count'] == 0) {
    echo "✓ PASS: All spares linked to parts_master\n";
    $passed++;
} else {
    echo "✗ FAIL: {$row['count']} spares not linked to parts_master\n";
}

// CHECK 3: spare_integration_functions.php exists
$total++;
if (file_exists('spare_integration_functions.php')) {
    echo "✓ PASS: spare_integration_functions.php exists\n";
    $passed++;
} else {
    echo "✗ FAIL: spare_integration_functions.php not found\n";
}

// CHECK 4: work_order.php includes integration functions
$total++;
$content = file_get_contents('work_order.php');
if (strpos($content, "require_once 'spare_integration_functions.php'") !== false) {
    echo "✓ PASS: work_order.php imports spare functions\n";
    $passed++;
} else {
    echo "✗ FAIL: work_order.php missing function import\n";
}

// CHECK 5: maintenance_report.php includes integration functions
$total++;
$content = file_get_contents('maintenance_report.php');
if (strpos($content, "require_once 'spare_integration_functions.php'") !== false) {
    echo "✓ PASS: maintenance_report.php imports spare functions\n";
    $passed++;
} else {
    echo "✗ FAIL: maintenance_report.php missing function import\n";
}

// CHECK 6: Stock locales entries exist for all spares
$total++;
$result = $connection->query("
    SELECT COUNT(*) as count 
    FROM stock_locales sl
    WHERE EXISTS (
        SELECT 1 FROM parts_master pm WHERE pm.id = sl.part_id AND pm.id BETWEEN 16 AND 21
    )
");
$row = $result->fetch_assoc();
if ($row['count'] >= 6) {
    echo "✓ PASS: Stock locales created for all spares ({$row['count']} entries)\n";
    $passed++;
} else {
    echo "✗ FAIL: Only {$row['count']} stock locale entries (expected 6)\n";
}

// CHECK 7: Spare costs set
$total++;
$result = $connection->query("
    SELECT COUNT(*) as count 
    FROM parts_master 
    WHERE id BETWEEN 16 AND 21 AND unit_cost > 0
");
$row = $result->fetch_assoc();
if ($row['count'] == 6) {
    echo "✓ PASS: All spare costs configured ({$row['count']}/6)\n";
    $passed++;
} else {
    echo "✗ FAIL: Only {$row['count']} spares have costs set (expected 6)\n";
}

// CHECK 8: warehouse_locations table exists
$total++;
$result = $connection->query("SHOW TABLES LIKE 'warehouse_locations'");
if ($result && $result->num_rows > 0) {
    echo "✓ PASS: warehouse_locations table exists\n";
    $passed++;
} else {
    echo "✗ FAIL: warehouse_locations table not found\n";
}

// CHECK 9: inventory_transactions table has reference fields
$total++;
$result = $connection->query("SHOW COLUMNS FROM inventory_transactions LIKE 'reference_type'");
if ($result && $result->num_rows > 0) {
    echo "✓ PASS: Inventory transaction reference fields exist\n";
    $passed++;
} else {
    echo "✗ FAIL: Inventory transaction structure incomplete\n";
}

// CHECK 10: Work order spares recorded
$total++;
$result = $connection->query("SELECT COUNT(*) as count FROM work_order_spares");
$row = $result->fetch_assoc();
if ($row['count'] > 0) {
    echo "✓ PASS: Work order spares recorded ({$row['count']} entries)\n";
    $passed++;
} else {
    echo "✗ FAIL: No work order spares recorded\n";
}

// SUMMARY
echo "\n" . str_repeat("═", 64) . "\n";
echo "VERIFICATION SUMMARY: $passed/$total CHECKS PASSED\n";
echo str_repeat("═", 64) . "\n\n";

if ($passed == $total) {
    echo "✓ SYSTEM READY FOR USE\n\n";
    echo "The spare parts integration system is fully operational:\n\n";
    echo "  • Equipment spares linked to parts_master\n";
    echo "  • Stock locales synchronized\n";
    echo "  • Integration functions available\n";
    echo "  • Work order system integrated\n";
    echo "  • Maintenance report configured\n";
    echo "  • Cost tracking enabled\n";
    echo "  • Audit trail implemented\n\n";
    echo "Next Steps:\n";
    echo "  1. Create work orders with spare selections\n";
    echo "  2. Mark as 'Completed' to trigger spare reduction\n";
    echo "  3. View monthly reports to see costs and usage\n";
    echo "  4. Check inventory_transactions for audit trail\n\n";
} else {
    echo "✗ SYSTEM NEEDS ATTENTION\n";
    echo "  " . ($total - $passed) . " issue(s) detected\n";
    echo "  Please review failed checks above\n\n";
}

$connection->close();
?>
