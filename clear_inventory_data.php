<?php
require_once 'config.inc.php';
require_once 'common.inc.php';

echo "Clearing all inventory data for fresh start...\n\n";

// Disable foreign key checks temporarily
$connection->query("SET FOREIGN_KEY_CHECKS = 0");

// Tables to clear (in reverse dependency order)
$tables_to_clear = [
    'goods_receipt_items',
    'goods_receipts',
    'purchase_order_items',
    'purchase_orders',
    'purchase_request_items',
    'purchase_requests',
    'inventory_transactions',
    'inventory_summary',
    'stock_locales',
    'part_vendors',
    'parts_master',
    'warehouse_locations',
    'warehouses',
    'vendors',
    'inventory'
];

foreach ($tables_to_clear as $table) {
    $sql = "TRUNCATE TABLE `$table`";
    if ($connection->query($sql)) {
        echo "✓ Cleared table: $table\n";
    } else {
        echo "✗ Error clearing $table: " . $connection->error . "\n";
    }
}

// Re-enable foreign key checks
$connection->query("SET FOREIGN_KEY_CHECKS = 1");

echo "\nAll inventory data has been cleared. Ready for fresh start!\n";
?>