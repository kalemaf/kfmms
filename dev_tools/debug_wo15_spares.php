<?php
/**
 * Debug: Work Order 15 Spares Reduction Issue
 */

require_once 'config.inc.php';
require_once 'common.inc.php';
require_once 'spare_integration_functions.php';

echo "\n";
echo str_repeat("=", 90) . "\n";
echo "DEBUG: WO #15 SPARES REDUCTION ISSUE\n";
echo str_repeat("=", 90) . "\n\n";

$_SESSION['tenant_id'] = 1;
$tenant_id = 1;
$wo_id = 15;

// Check if WO 15 exists
echo "STEP 1: Check if WO #15 exists\n";
echo str_repeat("-", 90) . "\n";
$wo = $connection->query("SELECT wo_id, equipment, descriptive_text, wo_status FROM work_orders WHERE wo_id = $wo_id AND tenant_id = $tenant_id")->fetch_assoc();
if (!$wo) {
    echo "❌ WO #15 NOT FOUND for tenant $tenant_id\n";
    exit(1);
}
echo "✅ WO #15 found: {$wo['descriptive_text']}, Status: {$wo['wo_status']}\n";
$equip_id = $wo['equipment'];
echo "   Equipment ID: $equip_id\n\n";

// Check equipment_spares for this equipment BEFORE completion
echo "STEP 2: Equipment spares for Equipment #$equip_id (BEFORE completion)\n";
echo str_repeat("-", 90) . "\n";
$spares = $connection->query("SELECT id, part_name, quantity FROM equipment_spares WHERE equipment_id = $equip_id AND tenant_id = $tenant_id ORDER BY id");
$spare_ids = [];
if ($spares) {
    $count = 0;
    while ($spare = $spares->fetch_assoc()) {
        $count++;
        $spare_ids[$spare['id']] = $spare['quantity'];
        echo "  Spare ID {$spare['id']}: {$spare['part_name']} - Qty: {$spare['quantity']}\n";
    }
    if ($count == 0) {
        echo "  No spares assigned to this equipment\n";
    }
}
echo "\n";

// Check work_order_spares for this WO (should be empty before manual completion)
echo "STEP 3: Current work_order_spares for WO #15\n";
echo str_repeat("-", 90) . "\n";
$wo_spares = $connection->query("SELECT spare_id, quantity_used FROM work_order_spares WHERE wo_id = $wo_id AND tenant_id = $tenant_id");
$current_spares = [];
if ($wo_spares) {
    $count = 0;
    while ($row = $wo_spares->fetch_assoc()) {
        $count++;
        $current_spares[$row['spare_id']] = $row['quantity_used'];
        echo "  Spare ID {$row['spare_id']}: Qty Used {$row['quantity_used']}\n";
    }
    if ($count == 0) {
        echo "  No spares recorded yet\n";
    }
}
echo "\n";

// Check stock_locales
echo "STEP 4: Stock locales for these parts\n";
echo str_repeat("-", 90) . "\n";
$stock_locales = $connection->query("
    SELECT sl.id, sl.part_id, pm.part_name, sl.quantity_on_hand, sl.quantity_issued
    FROM stock_locales sl
    LEFT JOIN parts_master pm ON sl.part_id = pm.id
    WHERE sl.part_id IN (SELECT part_id FROM equipment_spares WHERE equipment_id = $equip_id AND tenant_id = $tenant_id AND part_id IS NOT NULL)
");
if ($stock_locales) {
    $count = 0;
    while ($row = $stock_locales->fetch_assoc()) {
        $count++;
        echo "  Part {$row['part_id']} ({$row['part_name']}): On Hand: {$row['quantity_on_hand']}, Issued: {$row['quantity_issued']}\n";
    }
    if ($count == 0) {
        echo "  No stock locales found\n";
    }
}
echo "\n";

// Check parts_master totals
echo "STEP 5: Parts master totals\n";
echo str_repeat("-", 90) . "\n";
$parts_master = $connection->query("
    SELECT id, part_name, total_on_hand, total_issued
    FROM parts_master
    WHERE id IN (SELECT part_id FROM equipment_spares WHERE equipment_id = $equip_id AND tenant_id = $tenant_id AND part_id IS NOT NULL)
");
if ($parts_master) {
    $count = 0;
    while ($row = $parts_master->fetch_assoc()) {
        $count++;
        echo "  Part {$row['id']} ({$row['part_name']}): Total On Hand: {$row['total_on_hand']}, Total Issued: {$row['total_issued']}\n";
    }
    if ($count == 0) {
        echo "  No parts in parts_master\n";
    }
}
echo "\n";

echo str_repeat("=", 90) . "\n";
echo "RECOMMENDATIONS:\n";
echo str_repeat("-", 90) . "\n";
echo "1. Check if WO #15 spares dropdown is showing any spares\n";
echo "2. Check the work_order.php to see how spares are being selected\n";
echo "3. Manually select spares for WO #15 in the UI and observe behavior\n";
echo "4. Look for any DELETE triggers or constraints\n";
echo str_repeat("=", 90) . "\n\n";

?>
