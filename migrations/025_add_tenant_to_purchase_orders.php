<?php
/**
 * Migration: Add tenant_id to purchase_orders and purchase_order_items
 * Ensures purchase order data is properly isolated by tenant
 */

require_once __DIR__ . '/../config.inc.php';
require_once __DIR__ . '/../common.inc.php';

$migration_name = "025_add_tenant_to_purchase_orders";
$executed_flag = "migration_$migration_name";

// Check if already executed
$check_stmt = $connection->query("SELECT COUNT(*) as count FROM migrations WHERE name = '$migration_name'");
$is_executed = $check_stmt && $check_stmt->fetch_assoc()['count'] > 0;

if ($is_executed) {
    echo "Migration $migration_name already executed. Skipping.\n";
    exit;
}

echo "Running Migration: $migration_name\n";
echo str_repeat("=", 70) . "\n";

try {
    // Check if purchase_orders table exists
    $check = $connection->query("SELECT 1 FROM purchase_orders LIMIT 1");
    if (!$check) {
        echo "⚠ purchase_orders table does not exist. Skipping migration.\n";
        exit;
    }
    
    // Check if purchase_orders has tenant_id column
    $check_col = $connection->query("PRAGMA table_info(purchase_orders)");
    $has_tenant = false;
    while ($row = $check_col->fetch_assoc()) {
        if ($row['name'] === 'tenant_id') {
            $has_tenant = true;
            break;
        }
    }
    
    if (!$has_tenant) {
        echo "Adding tenant_id column to purchase_orders table...\n";
        $connection->query("ALTER TABLE purchase_orders ADD COLUMN tenant_id INTEGER DEFAULT 1");
        echo "✓ tenant_id added to purchase_orders\n";
    } else {
        echo "✓ tenant_id column already exists in purchase_orders\n";
    }
    
    // Check if purchase_order_items table exists
    $check = $connection->query("SELECT 1 FROM purchase_order_items LIMIT 1");
    if ($check) {
        // Check if purchase_order_items has tenant_id column
        $check_col = $connection->query("PRAGMA table_info(purchase_order_items)");
        $has_tenant = false;
        while ($row = $check_col->fetch_assoc()) {
            if ($row['name'] === 'tenant_id') {
                $has_tenant = true;
                break;
            }
        }
        
        if (!$has_tenant) {
            echo "Adding tenant_id column to purchase_order_items table...\n";
            $connection->query("ALTER TABLE purchase_order_items ADD COLUMN tenant_id INTEGER DEFAULT 1");
            echo "✓ tenant_id added to purchase_order_items\n";
        } else {
            echo "✓ tenant_id column already exists in purchase_order_items\n";
        }
    }
    
    // Create indexes for performance
    $connection->query("CREATE INDEX IF NOT EXISTS idx_po_tenant ON purchase_orders(tenant_id)");
    echo "✓ Index idx_po_tenant created\n";
    
    $connection->query("CREATE INDEX IF NOT EXISTS idx_poi_tenant ON purchase_order_items(tenant_id)");
    echo "✓ Index idx_poi_tenant created\n";
    
    // Record migration execution
    $connection->query("INSERT INTO migrations (name, executed_at) VALUES ('$migration_name', NOW())");
    
    echo "\n" . str_repeat("=", 70) . "\n";
    echo "Migration completed successfully!\n";
    echo "✓ purchase_orders and purchase_order_items now have tenant_id isolation\n";
    
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
    exit(1);
}

?>
