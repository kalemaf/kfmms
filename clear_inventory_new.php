<?php
// Clear inventory data for new companies - no output until after includes
ob_start();
include 'config.inc.php';
ob_end_clean();

echo "Connected to: " . DB_TYPE . "<br>";

// Get companies with company_id > 3
try {
    $companies_result = $connection->query("SELECT company_id, company_name FROM companies WHERE company_id > 3 ORDER BY company_id");
    $companies = [];
    while ($row = $companies_result->fetch(PDO::FETCH_ASSOC)) {
        $companies[] = $row;
    }
    echo "<h2>Companies to clear inventory for: " . count($companies) . "</h2>";
} catch (Exception $e) {
    echo "Error getting companies: " . $e->getMessage() . "<br>";
    exit;
}

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
            $info = $connection->query("PRAGMA table_info($table)")->fetchAll(PDO::FETCH_ASSOC);
            $has_tenant = false;
            foreach ($info as $col) {
                if ($col['name'] === 'tenant_id') {
                    $has_tenant = true;
                    break;
                }
            }

            if ($has_tenant) {
                $stmt = $connection->prepare("DELETE FROM $table WHERE tenant_id = ?");
                $stmt->execute([$cid]);
                $cnt = $stmt->rowCount();
                if ($cnt > 0) echo "  $table: $cnt deleted<br>";
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
$parts = $connection->query("SELECT tenant_id, COUNT(*) as cnt FROM parts_master GROUP BY tenant_id ORDER BY tenant_id")->fetchAll(PDO::FETCH_ASSOC);
foreach ($parts as $p) {
    echo "Tenant {$p['tenant_id']}: {$p['cnt']} parts<br>";
}

echo "<h2>Consumables by Tenant</h2>";
$consumables = $connection->query("SELECT tenant_id, COUNT(*) as cnt FROM consumables GROUP BY tenant_id ORDER BY tenant_id")->fetchAll(PDO::FETCH_ASSOC);
foreach ($consumables as $c) {
    echo "Tenant {$c['tenant_id']}: {$c['cnt']} consumables<br>";
}

echo "<h2>Inventory Transactions by Tenant</h2>";
$transactions = $connection->query("SELECT tenant_id, COUNT(*) as cnt FROM inventory_transactions GROUP BY tenant_id ORDER BY tenant_id")->fetchAll(PDO::FETCH_ASSOC);
foreach ($transactions as $t) {
    echo "Tenant {$t['tenant_id']}: {$t['cnt']} transactions<br>";
}

echo "<h2>Inventory isolation complete!</h2>";
?>