<?php
require_once 'config.inc.php';

echo "WO #16 Details:\n";
$wo = $connection->query("SELECT * FROM work_orders WHERE wo_id = 16")->fetch_assoc();
echo "  Equipment: {$wo['equipment']}\n";
echo "  Status: {$wo['wo_status']}\n";
echo "  Tenant: {$wo['tenant_id']}\n\n";

// Get equipment 
$eq = $connection->query("SELECT id, description, tenant_id FROM equipment WHERE id = {$wo['equipment']}")->fetch_assoc();
echo "Equipment Details:\n";
echo "  ID: {$eq['id']}, Name: {$eq['description']}, Tenant: {$eq['tenant_id']}\n\n";

// Check spare_id=11
echo "Checking spare_id=11:\n";
$spare = $connection->query("SELECT id, part_name, equipment_id, tenant_id FROM equipment_spares WHERE id = 11")->fetch_assoc();
if ($spare) {
    echo "  ✅ Found: {$spare['part_name']}, Equipment: {$spare['equipment_id']}, Tenant: {$spare['tenant_id']}\n";
} else {
    echo "  ❌ Spare ID 11 NOT FOUND in equipment_spares\n";
}

// Check what spares are for equipment id in WO
echo "\nSpares for Equipment {$wo['equipment']}:\n";
$spares = $connection->query("SELECT id, part_name, equipment_id, tenant_id FROM equipment_spares WHERE equipment_id = {$wo['equipment']}")->fetch_assoc();
if ($spares) {
    echo "  ID: {$spares['id']}, Name: {$spares['part_name']}, Equipment: {$spares['equipment_id']}, Tenant: {$spares['tenant_id']}\n";
} else {
    echo "  No spares found\n";
}

?>
