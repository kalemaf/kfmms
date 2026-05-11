<?php
require_once 'config.inc.php';

$_SESSION['tenant_id'] = 1;
$tenant_id = 1;
$wo_id = 5;

echo "Test 1: WITH tenant_id in JOIN condition\n";
$result1 = $connection->query("
    SELECT wos.spare_id, wos.quantity_used, COALESCE(es.part_name, '') AS spare_name
    FROM work_order_spares wos
    LEFT JOIN equipment_spares es ON wos.spare_id = es.id AND es.tenant_id = {$tenant_id}
    WHERE wos.wo_id = $wo_id AND wos.tenant_id = {$tenant_id}
");
$count1 = 0;
if ($result1) {
    while ($row = $result1->fetch_assoc()) {
        $count1++;
        echo "  {$row['spare_id']}: {$row['spare_name']}\n";
    }
}
echo "Total: $count1 rows\n";

echo "\nTest 2: WITHOUT tenant_id in JOIN (but in WHERE)\n";
$result2 = $connection->query("
    SELECT wos.spare_id, wos.quantity_used, COALESCE(es.part_name, '') AS spare_name
    FROM work_order_spares wos
    LEFT JOIN equipment_spares es ON wos.spare_id = es.id
    WHERE wos.wo_id = $wo_id AND wos.tenant_id = {$tenant_id}
");
$count2 = 0;
if ($result2) {
    while ($row = $result2->fetch_assoc()) {
        $count2++;
        echo "  {$row['spare_id']}: {$row['spare_name']}\n";
    }
}
echo "Total: $count2 rows\n";
?>
