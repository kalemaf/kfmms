<?php
require_once 'config.inc.php';
require_once 'common.inc.php';

$tenant_id = (int)($_SESSION['tenant_id'] ?? 1);
echo "Current Tenant ID: $tenant_id\n\n";

// Check recent work orders with spares/consumables
echo "=== RECENT WORK ORDERS WITH SPARES/CONSUMABLES ===\n";
$result = $connection->query("
    SELECT 
        wo.wo_id,
        wo.equipment,
        wo.tenant_id as wo_tenant,
        COUNT(DISTINCT wos.id) as spare_count,
        COUNT(DISTINCT woc.id) as consumable_count,
        GROUP_CONCAT(DISTINCT wos.spare_id) as spare_ids,
        GROUP_CONCAT(DISTINCT wos.tenant_id) as spare_tenants,
        GROUP_CONCAT(DISTINCT woc.tenant_id) as consumable_tenants
    FROM work_orders wo
    LEFT JOIN work_order_spares wos ON wo.wo_id = wos.wo_id
    LEFT JOIN work_order_consumables woc ON wo.wo_id = woc.work_order_id
    WHERE wo.tenant_id = $tenant_id
    GROUP BY wo.wo_id
    ORDER BY wo.wo_id DESC
    LIMIT 5
");

if ($result) {
    while ($row = $result->fetch(PDO::FETCH_ASSOC)) {
        echo "WO #{$row['wo_id']}: ";
        echo "Equipment={$row['equipment']}, ";
        echo "Spares={$row['spare_count']}, ";
        echo "Consumables={$row['consumable_count']}\n";
        if ($row['spare_ids']) {
            echo "  Spare IDs: {$row['spare_ids']}\n";
            echo "  Spare Tenants: {$row['spare_tenants']}\n";
        }
        if ($row['consumable_tenants']) {
            echo "  Consumable Tenants: {$row['consumable_tenants']}\n";
        }
        echo "\n";
    }
}

// Check if equipment_spares have tenant_id
echo "=== EQUIPMENT_SPARES TABLE INFO ===\n";
$result = $connection->query("PRAGMA table_info('equipment_spares')");
$has_tenant = false;
while ($row = $result->fetch(PDO::FETCH_ASSOC)) {
    if ($row['name'] === 'tenant_id') $has_tenant = true;
}
echo "Has tenant_id column: " . ($has_tenant ? "YES" : "NO") . "\n";

// Check equipment_spares for current tenant
echo "\n=== EQUIPMENT_SPARES FOR CURRENT TENANT ===\n";
$result = $connection->query("
    SELECT COUNT(*) as cnt FROM equipment_spares 
    WHERE tenant_id = $tenant_id
");
$row = $result->fetch(PDO::FETCH_ASSOC);
echo "Total equipment spares for tenant $tenant_id: {$row['cnt']}\n";

// Check sample data
$result = $connection->query("
    SELECT id, equipment_id, part_name, tenant_id 
    FROM equipment_spares 
    WHERE tenant_id = $tenant_id 
    LIMIT 3
");
echo "\nSample equipment spares:\n";
while ($row = $result->fetch(PDO::FETCH_ASSOC)) {
    echo "  - ID {$row['id']}: {$row['part_name']} (Equip: {$row['equipment_id']}, Tenant: {$row['tenant_id']})\n";
}
?>
