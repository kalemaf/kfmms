<?php
require_once 'config.inc.php';

$_SESSION['tenant_id'] = 1;

echo "Existing work orders:\n";
$result = $connection->query("SELECT wo_id, equipment, descriptive_text, wo_status FROM work_orders WHERE tenant_id = 1 ORDER BY wo_id DESC LIMIT 20");
while ($row = $result->fetch_assoc()) {
    echo "  WO #{$row['wo_id']}: {$row['descriptive_text']} (Equipment: {$row['equipment']}, Status: {$row['wo_status']})\n";
}
?>
