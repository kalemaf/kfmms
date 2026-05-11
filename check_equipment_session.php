<?php
require_once 'config.inc.php';
require_once 'common.inc.php';

echo "Current Session:<br>";
echo "tenant_id: " . ($_SESSION['tenant_id'] ?? 'NOT SET') . "<br>";
echo "user_id: " . ($_SESSION['user_id'] ?? 'NOT SET') . "<br>";
echo "<hr>";

echo "All Equipment in database:<br>";
$all_equipment = $connection->query("SELECT id, description, tenant_id FROM equipment ORDER BY tenant_id, description")->fetchAll(PDO::FETCH_ASSOC);
foreach ($all_equipment as $eq) {
    echo "ID {$eq['id']}: {$eq['description']} (tenant {$eq['tenant_id']})<br>";
}

echo "<hr>";
echo "Equipment visible to current session (tenant {$_SESSION['tenant_id']}):<br>";
$visible = safe_query_all('SELECT id, description, tenant_id FROM equipment WHERE tenant_id=' . (int)($_SESSION['tenant_id'] ?? 1) . ' ORDER BY description');
if (empty($visible)) {
    echo "No equipment visible (TENANT MISMATCH?)<br>";
} else {
    foreach ($visible as $eq) {
        echo "ID {$eq['id']}: {$eq['description']}<br>";
    }
}
?>
