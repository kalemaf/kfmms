<?php
/**
 * Verify WO16 Display After Fixes
 */

require_once 'config.inc.php';
require_once 'common.inc.php';

$_SESSION['tenant_id'] = 35;
$tenant_id = 35;
$wo_id = 16;

echo "Verifying WO #16 Display After Fixes\n";
echo str_repeat("=", 100) . "\n\n";

// Get work order
$work_order = $connection->query("
    SELECT * FROM work_orders 
    WHERE wo_id = $wo_id AND tenant_id = $tenant_id LIMIT 1
")->fetch_assoc();

if (!$work_order) {
    echo "❌ Work order not found\n";
    exit;
}

echo "Work Order Details:\n";
echo "  WO #: {$work_order['wo_id']}\n";
echo "  Status: {$work_order['wo_status']}\n";
echo "  Equipment: {$work_order['equipment']}\n";
echo "  Tenant: {$work_order['tenant_id']}\n\n";

// Get spares used (using EXACT query from view_work_order.php after fix)
echo "Spares Display (using view_work_order.php query):\n";
echo str_repeat("-", 100) . "\n";

$spares_result = $connection->query("
    SELECT wos.spare_id, wos.quantity_used, COALESCE(es.part_name, '') AS spare_name
    FROM work_order_spares wos
    LEFT JOIN equipment_spares es ON wos.spare_id = es.id AND es.tenant_id = {$tenant_id}
    WHERE wos.wo_id = $wo_id AND wos.tenant_id = {$tenant_id}
    ORDER BY spare_name
");

$spares_used = [];
if ($spares_result) {
    while ($row = $spares_result->fetch_assoc()) {
        $spares_used[] = $row;
    }
}

if (count($spares_used) > 0) {
    echo "✅ Spares Found:\n";
    foreach ($spares_used as $spare) {
        $spare_name = !empty($spare['spare_name']) ? htmlspecialchars($spare['spare_name']) : 'Spare ID: ' . (int)$spare['spare_id'];
        echo "  - $spare_name: Qty " . (int)$spare['quantity_used'] . "\n";
    }
} else {
    echo "⚠️ No spares recorded\n";
}

echo "\n";
echo str_repeat("=", 100) . "\n";

// Verify inventory was reduced
echo "Inventory Verification:\n";
echo str_repeat("-", 100) . "\n";

$spare = $connection->query("
    SELECT id, part_name, quantity FROM equipment_spares 
    WHERE id = 11 AND tenant_id = $tenant_id
")->fetch_assoc();

if ($spare) {
    echo "Equipment Spare #11 ({$spare['part_name']}):\n";
    echo "  Current Quantity: {$spare['quantity']}\n";
    echo "  Expected after 2-unit reduction: 12\n";
    echo "  Status: " . ($spare['quantity'] == 12 ? "✅ CORRECT" : "❌ INCORRECT") . "\n";
} else {
    echo "❌ Spare not found\n";
}

echo "\n";
echo str_repeat("=", 100) . "\n";
echo "Verification Complete\n";
echo str_repeat("=", 100) . "\n";

?>
