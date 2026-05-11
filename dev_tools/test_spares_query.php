<?php
require_once 'config.inc.php';
require_once 'common.inc.php';

$_SESSION['tenant_id'] = 1;
$tenant_id = 1;

// Test the exact query from view_work_order.php
$wo_id = 5;

echo "Testing query for WO #$wo_id:\n";
echo str_repeat("-", 80) . "\n\n";

$spares_result = $connection->query("
    SELECT wos.spare_id, wos.quantity_used, COALESCE(es.part_name, '') AS spare_name
    FROM work_order_spares wos
    LEFT JOIN equipment_spares es ON wos.spare_id = es.id AND es.tenant_id = {$tenant_id}
    WHERE wos.wo_id = $wo_id AND wos.tenant_id = {$tenant_id}
    ORDER BY spare_name
");

if (!$spares_result) {
    echo "Query error: " . $connection->error . "\n";
} else {
    echo "Spares for WO #$wo_id:\n";
    if ($spares_result->num_rows == 0) {
        echo "  No spares found\n";
    } else {
        while ($row = $spares_result->fetch_assoc()) {
            echo "  Spare ID: {$row['spare_id']}, Qty: {$row['quantity_used']}, Name: '{$row['spare_name']}'\n";
        }
    }
}

echo "\n" . str_repeat("=", 80) . "\n\n";

// Check what the raw data looks like
echo "Raw work_order_spares data for WO #$wo_id:\n";
$raw_result = $connection->query("SELECT id, wo_id, spare_id, quantity_used, tenant_id FROM work_order_spares WHERE wo_id = $wo_id AND tenant_id = $tenant_id");
while ($row = $raw_result->fetch_assoc()) {
    echo "  ID: {$row['id']}, WO: {$row['wo_id']}, Spare ID: {$row['spare_id']}, Qty: {$row['quantity_used']}, Tenant: {$row['tenant_id']}\n";
}

echo "\n";
echo "Equipment spares referenced:\n";
$es_result = $connection->query("SELECT id, part_name FROM equipment_spares WHERE id IN (SELECT DISTINCT spare_id FROM work_order_spares WHERE wo_id = $wo_id AND tenant_id = $tenant_id)");
while ($row = $es_result->fetch_assoc()) {
    echo "  ID: {$row['id']}, Name: {$row['part_name']}\n";
}
?>
