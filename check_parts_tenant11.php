<?php
require 'config.inc.php';
$result = $connection->query('SELECT id, part_code, part_name, tenant_id FROM parts_master WHERE tenant_id = 11');
echo "Parts for Tenant 11:\n";
while ($row = $result->fetch_assoc()) {
    echo "  ID: {$row['id']}, Code: {$row['part_code']}, Name: {$row['part_name']}, Tenant: {$row['tenant_id']}\n";
}
?>
