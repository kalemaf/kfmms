<?php
$db = new SQLite3('database/maintenix.db');

// Tables that need tenant_id column
$tenant_tables = [
    'work_orders',
    'equipment',
    'inventory',
    'inventory_transactions',
    'parts_master',
    'purchase_requests',
    'purchase_request_items',
    'purchase_orders',
    'purchase_order_items',
    'goods_receipts',
    'goods_receipt_items',
    'pm_schedules',
    'pm_tasks',
    'pm_required_parts',
    'pm_consumables',
    'work_order_spares',
    'work_order_consumables',
    'wo_parts',
    'equipment_spares',
    'consumables',
    'consumable_usage',
    'vendors',
    'part_vendors',
    'warehouses',
    'warehouse_locations',
    'stock_locations',
    'stock_locales',
    'mechanics',
    'personnel',
    'sites_locations',
    'work_order_requests',
    'hot_jobs',
    'vendor_performance',
    'goods_receipt_notes',
    'payment_orders'
];

echo "Adding tenant_id columns to operational tables...\n";

foreach ($tenant_tables as $table) {
    try {
        // Check if column already exists
        $result = $db->query("PRAGMA table_info($table)");
        $has_tenant_id = false;
        while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
            if ($row['name'] === 'tenant_id') {
                $has_tenant_id = true;
                break;
            }
        }

        if (!$has_tenant_id) {
            echo "Adding tenant_id to $table...\n";
            $db->exec("ALTER TABLE $table ADD COLUMN tenant_id INTEGER DEFAULT 0");
        } else {
            echo "tenant_id already exists in $table\n";
        }
    } catch (Exception $e) {
        echo "Error with $table: " . $e->getMessage() . "\n";
    }
}

echo "\nDone! All operational tables now have tenant_id columns.\n";

// Set default tenant_id for existing data (assuming it belongs to company_id 1)
echo "Setting default tenant_id for existing data...\n";
foreach ($tenant_tables as $table) {
    try {
        $db->exec("UPDATE $table SET tenant_id = 1 WHERE tenant_id = 0 OR tenant_id IS NULL");
        echo "Updated $table\n";
    } catch (Exception $e) {
        echo "Error updating $table: " . $e->getMessage() . "\n";
    }
}

echo "\nMigration complete!\n";
?>