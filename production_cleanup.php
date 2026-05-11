<?php
/**
 * PRODUCTION DATABASE CLEANUP
 *
 * This script clears all demo data from the CMMS database tables
 * to prepare for production use. All table structures are preserved
 * but all data is removed so users can start with a clean slate.
 *
 * Tables cleared:
 * - work_orders
 * - parts_master
 * - equipment
 * - equipment_spares
 * - work_order_spares
 * - wo_parts
 * - stock_locales
 * - warehouses
 * - warehouse_locations
 *
 * Auto-increment sequences have been reset.
 */

require_once 'config.inc.php';

echo "Starting production database cleanup...\n";

$tables = [
    'work_orders',
    'parts_master',
    'equipment',
    'equipment_spares',
    'work_order_spares',
    'wo_parts',
    'stock_locales',
    'warehouses',
    'warehouse_locations'
];

foreach ($tables as $table) {
    try {
        // Check if table exists first
        $exists = $connection->query("SELECT name FROM sqlite_master WHERE type='table' AND name='{$table}'");
        if ($exists && $exists->num_rows > 0) {
            $connection->query("DELETE FROM {$table}");
            echo "✓ Cleared table: {$table}\n";
        } else {
            echo "- Table {$table} does not exist (already cleared)\n";
        }
    } catch (Exception $e) {
        echo "✗ Error clearing {$table}: " . $e->getMessage() . "\n";
    }
}

// Reset auto-increment sequences
try {
    $connection->query("DELETE FROM sqlite_sequence");
    echo "✓ Reset auto-increment sequences\n";
} catch (Exception $e) {
    echo "✗ Error resetting sequences: " . $e->getMessage() . "\n";
}

echo "\n✅ Production database cleanup complete!\n";
echo "All demo data has been removed. Tables are ready for user data.\n";
?>