<?php
// Simple clear - no output buffering
include 'config.inc.php';

echo "Clearing new companies...\n";

// Delete work orders for companies 4-11
$connection->exec("DELETE FROM work_orders WHERE tenant_id > 3");
echo "work_orders cleared\n";

$connection->exec("DELETE FROM equipment WHERE tenant_id > 3");
echo "equipment cleared\n";

$connection->exec("DELETE FROM inventory WHERE tenant_id > 3");
echo "inventory cleared\n";

$connection->exec("DELETE FROM purchase_requests WHERE tenant_id > 3");
echo "purchase_requests cleared\n";

$connection->exec("DELETE FROM purchase_orders WHERE tenant_id > 3");
echo "purchase_orders cleared\n";

$connection->exec("DELETE FROM pm_schedules WHERE tenant_id > 3");
echo "pm_schedules cleared\n";

// Verify
$wo = $connection->query("SELECT tenant_id, COUNT(*) as cnt FROM work_orders GROUP BY tenant_id")->fetchAll(PDO::FETCH_ASSOC);
echo "\nWork Orders by Tenant:\n";
print_r($wo);

echo "\nDone!\n";