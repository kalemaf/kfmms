<?php
require_once 'config.inc.php';

$tables = ['work_order_spares', 'work_order_consumables', 'work_orders'];

foreach ($tables as $table) {
    echo "=== $table ===\n";
    $stmt = $connection->query("PRAGMA table_info('$table')");
    $columns = [];
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        echo "  - " . $row['name'] . " (" . $row['type'] . ")\n";
        $columns[] = $row['name'];
    }
    echo "Has tenant_id: " . (in_array('tenant_id', $columns) ? 'YES' : 'NO') . "\n\n";
}
?>
