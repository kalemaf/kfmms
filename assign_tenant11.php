<?php
require_once 'config.inc.php';
require_once 'common.inc.php';

echo "=== Reassigning ALL Data to Tenant 11 (kalema company) ===\n\n";

$tables = [
    'parts_master', 'equipment', 'work_orders', 'vendors', 'warehouses',
    'warehouse_locations', 'stock_locales', 'equipment_spares', 'goods_receipts',
    'purchase_orders', 'purchase_requests', 'work_order_spares', 'work_order_consumables', 'wo_parts'
];

foreach ($tables as $table) {
    $check = $connection->query("SELECT name FROM sqlite_master WHERE type='table' AND name='$table'");
    if (!$check || !$check->fetch(PDO::FETCH_ASSOC)) continue;
    
    $col_check = $connection->query("PRAGMA table_info($table)");
    $has_tenant_id = false;
    while ($col = $col_check->fetch(PDO::FETCH_ASSOC)) {
        if ($col['name'] === 'tenant_id') { $has_tenant_id = true; break; }
    }
    if (!$has_tenant_id) continue;
    
    $result = $connection->query("UPDATE $table SET tenant_id = 11");
    $changes = $connection->changes();
    echo "✅ $table: $changes rows → tenant_id = 11\n";
}

echo "\n=== Final Verification ===\n";
foreach ($tables as $table) {
    $check = $connection->query("SELECT name FROM sqlite_master WHERE type='table' AND name='$table'");
    if (!$check || !$check->fetch(PDO::FETCH_ASSOC)) continue;
    $col_check = $connection->query("PRAGMA table_info($table)");
    $has_tenant_id = false;
    while ($col = $col_check->fetch(PDO::FETCH_ASSOC)) {
        if ($col['name'] === 'tenant_id') { $has_tenant_id = true; break; }
    }
    if (!$has_tenant_id) continue;
    
    $dist = $connection->query("SELECT tenant_id, COUNT(*) as cnt FROM $table GROUP BY tenant_id");
    $str = [];
    while ($d = $dist->fetch(PDO::FETCH_ASSOC)) {
        $str[] = "tenant_{$d['tenant_id']}={$d['cnt']}";
    }
    echo "$table: " . implode(', ', $str) . "\n";
}

echo "\n🎉 Done! All data now belongs to tenant 11 for user seka.\n";
?>