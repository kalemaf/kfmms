<?php
/**
 * FINAL TEST: Complete Spares Display Flow
 * Verify spares show correctly in both view_work_order.php and maintenance_report.php
 */

require_once 'config.inc.php';
require_once 'common.inc.php';

echo "\n";
echo str_repeat("=", 90) . "\n";
echo "FINAL TEST: COMPLETE SPARES DISPLAY FLOW\n";
echo str_repeat("=", 90) . "\n\n";

$_SESSION['tenant_id'] = 1;
$tenant_id = 1;

// ========== TEST 1: view_work_order.php Query ==========
echo "TEST 1: view_work_order.php Spares Query (for WO #5)\n";
echo str_repeat("-", 90) . "\n\n";

$wo_id = 5;
$spares_used = [];
if ($connection) {
    $spares_result = $connection->query("
        SELECT wos.spare_id, wos.quantity_used, COALESCE(es.part_name, '') AS spare_name
        FROM work_order_spares wos
        LEFT JOIN equipment_spares es ON wos.spare_id = es.id AND es.tenant_id = {$tenant_id}
        WHERE wos.wo_id = $wo_id AND wos.tenant_id = {$tenant_id}
        ORDER BY spare_name
    ");
    if ($spares_result) {
        while ($row = $spares_result->fetch_assoc()) {
            $spares_used[] = $row;
        }
    }
}

if (count($spares_used) > 0) {
    echo "✅ Spares found for WO #$wo_id:\n";
    foreach ($spares_used as $spare) {
        echo "   - {$spare['spare_name']}: Qty {$spare['quantity_used']}\n";
    }
} else {
    echo "✗ No spares found for WO #$wo_id\n";
}

// ========== TEST 2: maintenance_report.php Query ==========
echo "\n\nTEST 2: maintenance_report.php Spares Query (for all completed WOs)\n";
echo str_repeat("-", 90) . "\n\n";

$completed_count = 0;
$report_spares_count = 0;

$wo_result = $connection->query("
    SELECT wo_id, descriptive_text FROM work_orders 
    WHERE wo_status = 'Completed' AND tenant_id = $tenant_id 
    ORDER BY wo_id DESC LIMIT 5
");

while ($wo = $wo_result->fetch_assoc()) {
    $wo_id = $wo['wo_id'];
    $completed_count++;
    
    // maintenance_report.php query
    $spares_query = "SELECT wos.quantity_used, COALESCE(es.part_name, 'Unknown Spare') as part_name, pm.unit_cost,
                           (wos.quantity_used * COALESCE(pm.unit_cost, 0)) as total_cost
                    FROM work_order_spares wos
                    LEFT JOIN equipment_spares es ON wos.spare_id = es.id AND es.tenant_id = {$tenant_id}
                    LEFT JOIN parts_master pm ON es.part_id = pm.id AND pm.tenant_id = {$tenant_id}
                    WHERE wos.wo_id = " . intval($wo_id) . " AND wos.tenant_id = {$tenant_id}";
    
    $spares_result = $connection->query($spares_query);
    
    echo "WO #$wo_id: {$wo['descriptive_text']}\n";
    if ($spares_result) {
        $spare_count = 0;
        while ($spare_row = $spares_result->fetch_assoc()) {
            $spare_count++;
            $report_spares_count++;
            echo "  - {$spare_row['part_name']}: Qty {$spare_row['quantity_used']}\n";
        }
        if ($spare_count == 0) {
            echo "  - No spares\n";
        }
    }
}

// ========== TEST 3: Multi-Tenant Isolation ==========
echo "\n\nTEST 3: Multi-Tenant Isolation\n";
echo str_repeat("-", 90) . "\n\n";

// Check tenant 1 spares
$t1_spares = $connection->query("
    SELECT COUNT(*) as cnt FROM work_order_spares 
    WHERE tenant_id = 1
")->fetch_assoc()['cnt'];

// Check tenant 11 spares (if exists)
$t11_spares = $connection->query("
    SELECT COUNT(*) as cnt FROM work_order_spares 
    WHERE tenant_id = 11
")->fetch_assoc()['cnt'];

echo "Tenant 1 work_order_spares: $t1_spares\n";
echo "Tenant 11 work_order_spares: $t11_spares\n";

// Switch to tenant 11 and query
$_SESSION['tenant_id'] = 11;
$tenant_id = 11;

$t11_query_result = $connection->query("
    SELECT wos.spare_id, wos.quantity_used, COALESCE(es.part_name, '') AS spare_name
    FROM work_order_spares wos
    LEFT JOIN equipment_spares es ON wos.spare_id = es.id AND es.tenant_id = {$tenant_id}
    WHERE wos.tenant_id = {$tenant_id}
");

$t11_visible = 0;
if ($t11_query_result) {
    while ($row = $t11_query_result->fetch_assoc()) {
        $t11_visible++;
    }
}

echo "Tenant 11 visible spares (via query): $t11_visible\n";

// ========== RESULTS ==========
echo "\n";
echo str_repeat("=", 90) . "\n";
echo "FINAL RESULTS:\n";
echo str_repeat("-", 90) . "\n";
echo "✅ view_work_order.php query: " . (count($spares_used) > 0 ? "WORKING" : "FAILED") . "\n";
echo "✅ maintenance_report.php query: " . ($report_spares_count > 0 ? "WORKING ($report_spares_count spares found)" : "FAILED") . "\n";
echo "✅ Tenant isolation: " . ($t11_visible == 0 ? "WORKING (Tenant 11 sees 0 spares)" : "FAILED") . "\n";

if (count($spares_used) > 0 && $report_spares_count > 0 && $t11_visible == 0) {
    echo "\n🎉 ALL TESTS PASSED - Spares display system is working correctly!\n";
} else {
    echo "\n⚠️ SOME TESTS FAILED - Review results above\n";
}

echo str_repeat("=", 90) . "\n\n";

?>
