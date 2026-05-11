<?php
require_once 'config.inc.php';

echo "Checking spare_id references in work_order_spares:\n";
$result = $connection->query("SELECT DISTINCT spare_id FROM work_order_spares WHERE tenant_id = 1");
echo "Spare IDs: ";
$ids = [];
while ($row = $result->fetch_assoc()) {
    $ids[] = $row['spare_id'];
}
echo implode(', ', $ids) . "\n\n";

echo "Checking if these IDs exist in equipment_spares:\n";
foreach ($ids as $id) {
    $es = $connection->query("SELECT id FROM equipment_spares WHERE id = " . intval($id))->fetch_assoc();
    echo "  ID $id: " . ($es ? 'EXISTS' : 'MISSING') . "\n";
}

// Check what spare_name is stored
echo "\nSparse spare names:\n";
$all_spares = $connection->query("SELECT DISTINCT spare_id FROM work_order_spares WHERE tenant_id = 1");
while ($spare = $all_spares->fetch_assoc()) {
    $spare_id = $spare['spare_id'];
    // Find ONE work_order_spares record to see what name might be associated
    $sample = $connection->query("SELECT * FROM work_order_spares WHERE spare_id = $spare_id LIMIT 1")->fetch_assoc();
    if ($sample) {
        // Check maintenance_report.php query
        $lookup = $connection->query("SELECT es.part_name FROM equipment_spares es WHERE es.id = $spare_id");
        if ($lookup && $lookup->num_rows > 0) {
            $name = $lookup->fetch_assoc()['part_name'];
            echo "  Spare $spare_id: part_name = $name\n";
        } else {
            echo "  Spare $spare_id: NO part_name found in equipment_spares\n";
        }
    }
}
?>
