<?php
/**
 * Debug WO16 Spares Issue
 */

require_once 'config.inc.php';
require_once 'common.inc.php';

$_SESSION['tenant_id'] = 1;
$tenant_id = 1;
$wo_id = 16;

echo "Debugging WO #$wo_id Spares Issue\n";
echo str_repeat("=", 100) . "\n\n";

// Check work_order_spares for WO16
echo "STEP 1: work_order_spares records for WO#$wo_id\n";
echo str_repeat("-", 100) . "\n";
$wo_spares = $connection->query("
    SELECT id, spare_id, quantity_used, tenant_id 
    FROM work_order_spares 
    WHERE wo_id = $wo_id
");
$wo_spare_count = 0;
while ($row = $wo_spares->fetch_assoc()) {
    $wo_spare_count++;
    echo "  Record $wo_spare_count: spare_id={$row['spare_id']}, qty={$row['quantity_used']}, tenant={$row['tenant_id']}\n";
}
echo "Total work_order_spares records: $wo_spare_count\n\n";

// Check equipment_spares for the roller bearing
echo "STEP 2: Equipment spares - Check 'roller' spares\n";
echo str_repeat("-", 100) . "\n";
$eq_spares = $connection->query("
    SELECT id, equipment_id, part_name, part_number, part_id, quantity, tenant_id 
    FROM equipment_spares 
    WHERE part_name LIKE '%roller%' AND tenant_id = $tenant_id
");
$count = 0;
while ($row = $eq_spares->fetch_assoc()) {
    $count++;
    echo "  ID {$row['id']}: name='{$row['part_name']}', partnum='{$row['part_number']}', part_id={$row['part_id']}, qty={$row['quantity']}, equip={$row['equipment_id']}, tenant={$row['tenant_id']}\n";
}
echo "Total roller spares: $count\n\n";

// Check parts_master for roller
echo "STEP 3: Parts master - Check 'roller' parts\n";
echo str_repeat("-", 100) . "\n";
$pm_spares = $connection->query("
    SELECT id, part_name, part_code, total_on_hand, total_issued, tenant_id 
    FROM parts_master 
    WHERE part_name LIKE '%roller%' OR part_code LIKE '%roller%'
");
$pm_count = 0;
while ($row = $pm_spares->fetch_assoc()) {
    $pm_count++;
    echo "  ID {$row['id']}: name='{$row['part_name']}', code='{$row['part_code']}', on_hand={$row['total_on_hand']}, issued={$row['total_issued']}, tenant={$row['tenant_id']}\n";
}
echo "Total parts_master roller entries: $pm_count\n\n";

// Check stock_locales
echo "STEP 4: Stock locales for roller parts\n";
echo str_repeat("-", 100) . "\n";
$stock = $connection->query("
    SELECT sl.id, sl.part_id, pm.part_name, sl.quantity_on_hand, sl.quantity_issued 
    FROM stock_locales sl
    LEFT JOIN parts_master pm ON sl.part_id = pm.id
    WHERE pm.part_name LIKE '%roller%'
");
$stock_count = 0;
while ($row = $stock->fetch_assoc()) {
    $stock_count++;
    echo "  StockID {$row['id']}: part_id={$row['part_id']}, part='{$row['part_name']}', on_hand={$row['quantity_on_hand']}, issued={$row['quantity_issued']}\n";
}
echo "Total stock_locales: $stock_count\n\n";

// Check if equipment_spares records are linked to parts_master
echo "STEP 5: Equipment spares linking to parts_master\n";
echo str_repeat("-", 100) . "\n";
$eq_spares2 = $connection->query("
    SELECT es.id, es.part_name, es.part_id, pm.id as pm_id, pm.part_name as pm_name
    FROM equipment_spares es
    LEFT JOIN parts_master pm ON es.part_id = pm.id
    WHERE es.part_name LIKE '%roller%' AND es.tenant_id = $tenant_id
");
while ($row = $eq_spares2->fetch_assoc()) {
    if ($row['pm_id']) {
        echo "  ✅ ES ID {$row['id']} ({$row['part_name']}) → PM ID {$row['pm_id']} ({$row['pm_name']})\n";
    } else {
        echo "  ❌ ES ID {$row['id']} ({$row['part_name']}) → UNLINKED (part_id={$row['part_id']})\n";
    }
}

?>
