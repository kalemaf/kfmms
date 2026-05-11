<?php
$db = new SQLite3('database/maintenix.db');

echo "work_orders columns (after migration):\n";
$result = $db->query('PRAGMA table_info(work_orders);');
while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
    echo $row['name'] . " - " . $row['type'] . "\n";
}

echo "\nequipment columns (after migration):\n";
$result = $db->query('PRAGMA table_info(equipment);');
while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
    echo $row['name'] . " - " . $row['type'] . "\n";
}

echo "\nSample data check:\n";
$result = $db->query('SELECT COUNT(*) as count FROM work_orders WHERE tenant_id = 1');
$row = $result->fetchArray(SQLITE3_ASSOC);
echo "Work orders with tenant_id=1: " . $row['count'] . "\n";

$result = $db->query('SELECT COUNT(*) as count FROM equipment WHERE tenant_id = 1');
$row = $result->fetchArray(SQLITE3_ASSOC);
echo "Equipment with tenant_id=1: " . $row['count'] . "\n";
?>