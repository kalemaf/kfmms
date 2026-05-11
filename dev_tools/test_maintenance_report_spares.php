<?php
/**
 * TEST: Maintenance Report Spares Display
 * Verify maintenance report correctly shows spares used from completed work orders
 */

require_once 'config.inc.php';
require_once 'common.inc.php';
require_once 'spare_integration_functions.php';

echo "\n";
echo str_repeat("=", 80) . "\n";
echo "MAINTENANCE REPORT SPARES DISPLAY TEST\n";
echo str_repeat("=", 80) . "\n\n";

$_SESSION['tenant_id'] = 1;
$tenant_id = 1;

echo "TEST: Retrieving spares used for completed work orders\n";
echo str_repeat("-", 80) . "\n\n";

// Get all completed work orders for tenant 1
$wo_result = $connection->query("SELECT wo_id, descriptive_text, equipment FROM work_orders WHERE wo_status = 'Completed' AND tenant_id = $tenant_id ORDER BY wo_id DESC LIMIT 5");

if (!$wo_result) {
    echo "✗ Failed to query work orders\n";
    exit(1);
}

$completed_count = 0;
$spares_count = 0;

while ($wo = $wo_result->fetch_assoc()) {
    $wo_id = $wo['wo_id'];
    $completed_count++;
    
    echo "Work Order #$wo_id - {$wo['descriptive_text']}\n";
    
    // Query spares using the SAME query as maintenance_report.php
    $spares_query = "SELECT wos.quantity_used, COALESCE(es.part_name, 'Unknown Spare') as part_name, pm.unit_cost,
                           (wos.quantity_used * COALESCE(pm.unit_cost, 0)) as total_cost
                    FROM work_order_spares wos
                    LEFT JOIN equipment_spares es ON wos.spare_id = es.id
                    LEFT JOIN parts_master pm ON es.part_id = pm.id
                    WHERE wos.wo_id = " . intval($wo_id);
    
    // Apply tenant filtering (same as maintenance_report.php)
    $spares_query = apply_tenant_filter($spares_query);
    
    $spares_result = $connection->query($spares_query);
    
    if ($spares_result && $spares_result->num_rows > 0) {
        while ($spare_row = $spares_result->fetch_assoc()) {
            $spares_count++;
            $spare_name = $spare_row['part_name'];
            $qty = intval($spare_row['quantity_used']);
            $cost = floatval($spare_row['total_cost'] ?? 0);
            echo "  - $spare_name: Qty $qty (Cost: \$$cost)\n";
        }
    } else {
        echo "  - No spares recorded\n";
    }
    echo "\n";
}

echo str_repeat("=", 80) . "\n";
echo "TEST RESULTS:\n";
echo str_repeat("-", 80) . "\n";
echo "Completed Work Orders Found: $completed_count\n";
echo "Total Spares Recorded: $spares_count\n\n";

// Check the raw work_order_spares table
$raw_spares = $connection->query("SELECT COUNT(*) as cnt FROM work_order_spares WHERE tenant_id = $tenant_id")->fetch_assoc()['cnt'];
echo "Raw work_order_spares records (tenant 1): $raw_spares\n";

// Check for orphaned records (work_order_spares without equipment_spares)
$orphaned = $connection->query("
    SELECT COUNT(*) as cnt FROM work_order_spares wos
    WHERE wos.tenant_id = $tenant_id
    AND NOT EXISTS (SELECT 1 FROM equipment_spares es WHERE es.id = wos.spare_id)
")->fetch_assoc()['cnt'];
echo "Orphaned spares (no equipment_spares record): $orphaned\n\n";

if ($spares_count > 0) {
    echo "✅ MAINTENANCE REPORT TEST PASSED - Spares are being displayed\n";
} elseif ($raw_spares > 0) {
    echo "⚠️ WARNING: Spares exist in database but not showing in report\n";
    echo "   This could be due to:\n";
    echo "   - JOIN condition failing (equipment_spares not found)\n";
    echo "   - Orphaned records (work_order_spares without equipment_spares)\n";
} else {
    echo "ℹ️ INFO: No spares have been recorded yet for completed work orders\n";
}

echo str_repeat("=", 80) . "\n\n";

?>
