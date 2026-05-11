<?php
include 'config.inc.php';

$db = new SQLite3('database/maintenix.db');

// Get companies with company_id > 3
$result = $db->query("SELECT company_id, company_name FROM companies WHERE company_id > 3 ORDER BY company_id");
$companies = [];
while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
    $companies[] = $row;
}

echo "<h2>Companies to clear inventory for: " . count($companies) . "</h2>";

// Inventory-related tables to clear
$tables = [
    'parts_master',
    'inventory',
    'inventory_transactions',
    'consumables',
    'consumable_usage',
    'purchase_requests',
    'purchase_request_items',
    'purchase_orders',
    'purchase_order_items',
    'goods_receipts',
    'goods_receipt_items',
    'part_vendors',
    'warehouse_locations',
    'warehouses',
    'vendors'
];

foreach ($companies as $c) {
    $cid = $c['company_id'];
    echo "<h3>Company $cid: {$c['company_name']}</h3>";
    
    foreach ($tables as $table) {
        try {
            // Check if table exists and has tenant_id
            $info_result = $db->query("PRAGMA table_info($table)");
            $has_tenant = false;
            while ($row = $info_result->fetchArray(SQLITE3_ASSOC)) {
                if ($row['name'] === 'tenant_id') {
                    $has_tenant = true;
                    break;
                }
            }
            
            if ($has_tenant) {
                $stmt = $db->prepare("DELETE FROM $table WHERE tenant_id = ?");
                $stmt->bindValue(1, $cid, SQLITE3_INTEGER);
                $result = $stmt->execute();
                $changes = $db->changes();
                if ($changes > 0) echo "  $table: $changes deleted<br>";
            } else {
                echo "  $table: No tenant_id column<br>";
            }
        } catch (Exception $e) {
            echo "  $table: Error - " . $e->getMessage() . "<br>";
        }
    }
}

// Verify inventory data by tenant
echo "<h2>Parts Master by Tenant</h2>";
$result = $db->query("SELECT tenant_id, COUNT(*) as cnt FROM parts_master GROUP BY tenant_id ORDER BY tenant_id");
while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
    echo "Tenant {$row['tenant_id']}: {$row['cnt']} parts<br>";
}

echo "<h2>Consumables by Tenant</h2>";
$result = $db->query("SELECT tenant_id, COUNT(*) as cnt FROM consumables GROUP BY tenant_id ORDER BY tenant_id");
while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
    echo "Tenant {$row['tenant_id']}: {$row['cnt']} consumables<br>";
}

echo "<h2>Inventory Transactions by Tenant</h2>";
$result = $db->query("SELECT tenant_id, COUNT(*) as cnt FROM inventory_transactions GROUP BY tenant_id ORDER BY tenant_id");
while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
    echo "Tenant {$row['tenant_id']}: {$row['cnt']} transactions<br>";
}

echo "<h2>Inventory isolation complete!</h2>";
?>