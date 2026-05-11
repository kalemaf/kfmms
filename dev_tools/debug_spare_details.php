<?php
require_once 'config.inc.php';

echo "Equipment spares with IDs 1, 4, 6:\n";
$result = $connection->query("SELECT id, equipment_id, part_name, part_number, part_id, tenant_id FROM equipment_spares WHERE id IN (1, 4, 6)");
while ($row = $result->fetch_assoc()) {
    echo "  ID: {$row['id']}, Equip: {$row['equipment_id']}, Name: '{$row['part_name']}', PartNum: '{$row['part_number']}', PartID: {$row['part_id']}, Tenant: {$row['tenant_id']}\n";
}

echo "\n\nChecking if part_id values are valid:\n";
$result2 = $connection->query("SELECT id, equipment_id, part_name, part_number, part_id, tenant_id FROM equipment_spares WHERE id IN (1, 4, 6)");
while ($row = $result2->fetch_assoc()) {
    $part_id = $row['part_id'];
    if ($part_id) {
        $part = $connection->query("SELECT id, part_name FROM parts_master WHERE id = " . intval($part_id))->fetch_assoc();
        if ($part) {
            echo "  Equipment spare ID {$row['id']} -> Part ID {$part_id} -> {$part['part_name']}\n";
        } else {
            echo "  Equipment spare ID {$row['id']} -> Part ID {$part_id} -> NOT FOUND\n";
        }
    } else {
        echo "  Equipment spare ID {$row['id']} -> No part_id\n";
    }
}
?>
