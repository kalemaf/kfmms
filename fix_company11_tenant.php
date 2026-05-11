<?php
require_once 'config.inc.php';
require_once 'common.inc.php';

echo "=== Fixing Tenant Data for Company 11 (kalema) ===\n\n";

// Get all tables that need tenant_id update for company 11
$tables_to_fix = [
    'parts_master',
    'equipment',
    'work_orders',
    'vendors',
    'warehouses',
    'warehouse_locations',
    'stock_locales',
    'equipment_spares',
    'goods_receipts',
    'purchase_orders',
    'purchase_requests'
];

foreach ($tables_to_fix as $table) {
    // Check if table exists
    $check = $connection->query("SELECT name FROM sqlite_master WHERE type='table' AND name='$table'");
    if (!$check || !$check->fetch(PDO::FETCH_ASSOC)) {
        continue;
    }
    
    // Check if tenant_id column exists
    $col_check = $connection->query("PRAGMA table_info($table)");
    $has_tenant_id = false;
    while ($col = $col_check->fetch(PDO::FETCH_ASSOC)) {
        if ($col['name'] === 'tenant_id') {
            $has_tenant_id = true;
            break;
        }
    }
    
    if (!$has_tenant_id) {
        echo "⏭️  $table: no tenant_id column\n";
        continue;
    }
    
    // Count rows with tenant_id = 0 or NULL
    $count_result = $connection->query("SELECT COUNT(*) as cnt FROM $table WHERE tenant_id = 0 OR tenant_id IS NULL");
    $count_row = $count_result->fetch(PDO::FETCH_ASSOC);
    $unassigned = intval($count_row['cnt']);
    
    if ($unassigned > 0) {
        // Assign unassigned data to company 11 (kalema)
        $connection->query("UPDATE $table SET tenant_id = 11 WHERE tenant_id = 0 OR tenant_id IS NULL");
        echo "✅ $table: Assigned $unassigned rows to tenant_id = 11\n";
    } else {
        // Check current distribution
        $dist = $connection->query("SELECT tenant_id, COUNT(*) as cnt FROM $table GROUP BY tenant_id");
        $dist_str = [];
        while ($d = $dist->fetch(PDO::FETCH_ASSOC)) {
            $dist_str[] = "tenant_{$d['tenant_id']}={$d['cnt']}";
        }
        echo "⏭️  $table: " . implode(', ', $dist_str) . "\n";
    }
}

echo "\n=== Verification for Company 11 ===\n";
$verify = $connection->query("SELECT tenant_id, COUNT(*) as cnt FROM parts_master GROUP BY tenant_id");
while ($v = $verify->fetch(PDO::FETCH_ASSOC)) {
    echo "- Tenant {$v['tenant_id']}: {$v['cnt']} parts\n";
}
?>