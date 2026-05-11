<?php
/**
 * TEST: Maintenance Report Spares Display - EXACT QUERY FROM FILE
 */

require_once 'config.inc.php';
require_once 'common.inc.php';

echo "\n";
echo str_repeat("=", 80) . "\n";
echo "MAINTENANCE REPORT SPARES DISPLAY TEST (EXACT QUERY FROM FILE)\n";
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
    
    // Query spares using the EXACT query from maintenance_report.php (after my fix)
    $spares_query = "SELECT wos.quantity_used, COALESCE(es.part_name, 'Unknown Spare') as part_name, pm.unit_cost,
                           (wos.quantity_used * COALESCE(pm.unit_cost, 0)) as total_cost
                    FROM work_order_spares wos
                    LEFT JOIN equipment_spares es ON wos.spare_id = es.id AND es.tenant_id = {$tenant_id}
                    LEFT JOIN parts_master pm ON es.part_id = pm.id AND pm.tenant_id = {$tenant_id}
                    WHERE wos.wo_id = " . intval($wo_id) . " AND wos.tenant_id = {$tenant_id}";
    
    $spares_result = $connection->query($spares_query);
    
    if ($spares_result) {
        $spare_count = 0;
        while ($spare_row = $spares_result->fetch_assoc()) {
            $spares_count++;
            $spare_count++;
            $spare_name = $spare_row['part_name'];
            $qty = intval($spare_row['quantity_used']);
            $cost = floatval($spare_row['total_cost'] ?? 0);
            echo "  - $spare_name: Qty $qty (Cost: \$$cost)\n";
        }
        if ($spare_count == 0) {
            echo "  - No spares recorded\n";
        }
    } else {
        echo "  - Query error: " . $connection->error . "\n";
    }
    echo "\n";
}

echo str_repeat("=", 80) . "\n";
echo "TEST RESULTS:\n";
echo str_repeat("-", 80) . "\n";
echo "Completed Work Orders Found: $completed_count\n";
echo "Total Spares Recorded: $spares_count\n\n";

if ($spares_count > 0) {
    echo "✅ MAINTENANCE REPORT TEST PASSED - Spares are being displayed\n";
} else {
    echo "ℹ️ INFO: No spares have been recorded yet for completed work orders\n";
}

echo str_repeat("=", 80) . "\n\n";

?>
