<?php
require_once 'config.inc.php';

echo "ALL equipment_spares records:\n";
$result = $connection->query("SELECT id, equipment_id, part_name, part_id, tenant_id FROM equipment_spares LIMIT 20");
echo "Total records: " . $result->num_rows . "\n";
if ($result->num_rows == 0) {
    echo "  NO RECORDS!\n";
} else {
    while ($row = $result->fetch_assoc()) {
        echo "  ID: {$row['id']}, Equip: {$row['equipment_id']}, Name: {$row['part_name']}, Part ID: {$row['part_id']}, Tenant: {$row['tenant_id']}\n";
    }
}

echo "\n\nEquipment records:\n";
$eq_result = $connection->query("SELECT id, description, tenant_id FROM equipment LIMIT 10");
echo "Total equipment: " . $eq_result->num_rows . "\n";
while ($row = $eq_result->fetch_assoc()) {
    echo "  ID: {$row['id']}, Desc: {$row['description']}, Tenant: {$row['tenant_id']}\n";
}
?>
