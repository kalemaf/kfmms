<?php
require_once 'config.inc.php';

$_SESSION['tenant_id'] = 1;
$tenant_id = 1;

// Try simpler insert
$sql = "INSERT INTO work_orders (equipment, descriptive_text, requestor, wo_status, submit_date, tenant_id) 
        VALUES (1, 'Test', 'test', 'Open', CURRENT_TIMESTAMP, 1)";

echo "SQL: $sql\n\n";

$result = $connection->query($sql);
if (!$result) {
    echo "Error: " . $connection->error . "\n";
    // Check what columns work_orders has
    $cols = $connection->query("PRAGMA table_info(work_orders)");
    echo "\nwork_orders columns:\n";
    while ($col = $cols->fetch_assoc()) {
        echo "  {$col['name']} ({$col['type']})\n";
    }
} else {
    echo "Success! Insert ID: " . $connection->lastInsertId() . "\n";
}

?>
