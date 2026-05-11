<?php
require_once 'config.inc.php';
global $connection;

if ($connection) {
    echo "Syncing parts_master total_on_hand with stock_locales...\n";

    // Get all parts that have stock
    $result = $connection->query("
        SELECT pm.id, pm.part_name, COALESCE(SUM(sl.quantity_on_hand), 0) as actual_total
        FROM parts_master pm
        LEFT JOIN stock_locales sl ON pm.id = sl.part_id
        GROUP BY pm.id
        HAVING actual_total > 0
    ");

    $updated = 0;
    while ($row = $result->fetch_assoc()) {
        $part_id = $row['id'];
        $actual_total = $row['actual_total'];

        // Update parts_master total_on_hand
        $connection->query("UPDATE parts_master SET total_on_hand = $actual_total WHERE id = $part_id");
        echo "Updated {$row['part_name']} (ID: $part_id): $actual_total\n";
        $updated++;
    }

    echo "\nSync complete! Updated $updated parts.\n";
} else {
    echo "No database connection\n";
}
?>