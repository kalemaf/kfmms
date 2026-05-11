<?php
require_once 'config.inc.php';
require_once 'common.inc.php';

$_SESSION['tenant_id'] = 1;
$tenant_id = 1;

// Check raw work_order_spares
echo "Raw work_order_spares records:\n";
$result = $connection->query("SELECT id, wo_id, spare_id, quantity_used, tenant_id FROM work_order_spares WHERE tenant_id = $tenant_id");
while ($row = $result->fetch_assoc()) {
    echo "  ID: {$row['id']}, WO: {$row['wo_id']}, Spare ID: {$row['spare_id']}, Qty: {$row['quantity_used']}, Tenant: {$row['tenant_id']}\n";
}

// Check if equipment_spares exist
echo "\nEquipment spares for tenant 1:\n";
$es_result = $connection->query("SELECT id, equipment_id, part_name, part_id FROM equipment_spares WHERE tenant_id = $tenant_id");
if ($es_result->num_rows == 0) {
    echo "  NO equipment_spares found!\n";
} else {
    while ($row = $es_result->fetch_assoc()) {
        echo "  ID: {$row['id']}, Equip: {$row['equipment_id']}, Name: {$row['part_name']}, Part ID: {$row['part_id']}\n";
    }
}

// Check parts_master
echo "\nParts master records:\n";
$pm_result = $connection->query("SELECT id, part_name, unit_cost FROM parts_master LIMIT 5");
if ($pm_result->num_rows == 0) {
    echo "  NO parts_master found!\n";
} else {
    while ($row = $pm_result->fetch_assoc()) {
        echo "  ID: {$row['id']}, Name: {$row['part_name']}, Cost: {$row['unit_cost']}\n";
    }
}
?>
