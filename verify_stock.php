<?php
require_once 'config.inc.php';
global $connection;

if ($connection) {
    $result = $connection->query("
        SELECT part_code, part_name, total_on_hand
        FROM parts_master
        WHERE part_code IN ('BEAR-6206', 'BELT-001', '564rt')
        AND is_active = 1
    ");

    echo "Stock levels for the parts you mentioned:\n";
    while ($row = $result->fetch_assoc()) {
        echo "{$row['part_code']}: {$row['part_name']} - {$row['total_on_hand']} units\n";
    }
}
?>