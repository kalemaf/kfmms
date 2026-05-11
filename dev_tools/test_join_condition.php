<?php
require_once 'config.inc.php';

$_SESSION['tenant_id'] = 1;
$tenant_id = 1;
$wo_id = 5;

echo "Test 1: WITH tenant_id in JOIN condition\n";
$result1 = $connection->query("
    SELECT wos.spare_id, wos.quantity_used, COALESCE(es.part_name, '') AS spare_name
    FROM work_order_spares wos
    LEFT JOIN equipment_spares es ON wos.spare_id = es.id AND es.tenant_id = {$tenant_id}
    WHERE wos.wo_id = $wo_id AND wos.tenant_id = {$tenant_id}
");
echo "Results: " . ($result1->num_rows ?? 'error') . " rows\n";
if ($result1 && $result1->num_rows > 0) {
    while ($row = $result1->fetch_assoc()) {
        echo "  {$row['spare_id']}: {$row['spare_name']}\n";
    }
} else {
    echo "  No results\n";
}

echo "\nTest 2: WITHOUT tenant_id in JOIN (but in WHERE)\n";
$result2 = $connection->query("
    SELECT wos.spare_id, wos.quantity_used, COALESCE(es.part_name, '') AS spare_name
    FROM work_order_spares wos
    LEFT JOIN equipment_spares es ON wos.spare_id = es.id
    WHERE wos.wo_id = $wo_id AND wos.tenant_id = {$tenant_id}
");
echo "Results: " . ($result2->num_rows ?? 'error') . " rows\n";
if ($result2 && $result2->num_rows > 0) {
    while ($row = $result2->fetch_assoc()) {
        echo "  {$row['spare_id']}: {$row['spare_name']}\n";
    }
} else {
    echo "  No results\n";
}

echo "\nTest 3: Check if equipment_spares IDs 1 has tenant_id=1\n";
$check = $connection->query("SELECT id, tenant_id, part_name FROM equipment_spares WHERE id = 1");
while ($row = $check->fetch_assoc()) {
    echo "  ID: {$row['id']}, Tenant: {$row['tenant_id']}, Name: {$row['part_name']}\n";
}
?>
