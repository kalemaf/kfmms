<?php
require_once 'config.inc.php';

$_SESSION['tenant_id'] = 1;
$tenant_id = 1;

echo "Attempting to create test WO...\n";

$sql = "INSERT INTO work_orders (equipment, descriptive_text, requestor, wo_status, submit_date, tenant_id)
        VALUES (1, 'Test WO for spares reduction', 'tester', 'Open', datetime('now'), $tenant_id)";

echo "SQL: $sql\n\n";

$result = $connection->query($sql);

if ($result === false) {
    echo "❌ Error: " . $connection->error . "\n";
} else {
    echo "✅ Success\n";
    echo "Insert ID: " . $connection->insert_id . "\n";
}

?>
