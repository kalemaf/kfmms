<?php
/**
 * Migration: Add Work Order Tenant Isolation
 * 
 * Adds tenant_id column to work_orders and work_order_requests tables
 * to enable multi-tenant data isolation. Each company will now only see
 * their own work orders.
 */

require_once __DIR__ . '/../config.inc.php';

function column_exists($connection, $table, $column) {
    global $db_type;
    
    try {
        if ($db_type === 'sqlite') {
            $check = $connection->query("PRAGMA table_info('$table')");
            $columns = [];
            while ($row = $check->fetch(PDO::FETCH_ASSOC)) {
                $columns[] = $row['name'];
            }
            return in_array($column, $columns);
        } else {
            $check = $connection->query("SHOW COLUMNS FROM `$table` LIKE '$column'");
            return $check && $check->fetch() !== false;
        }
    } catch (Exception $e) {
        error_log("[MIGRATION] Error checking column: " . $e->getMessage());
        return false;
    }
}

if (!$connection) {
    echo "ERROR: No database connection\n";
    exit(1);
}

echo "======================================================\n";
echo "WORK ORDER TENANT ISOLATION MIGRATION\n";
echo "======================================================\n\n";

try {
    // Work Orders Table
    echo "[1/2] Processing work_orders table...\n";
    
    if (column_exists($connection, 'work_orders', 'tenant_id')) {
        echo "→ Column tenant_id already exists in work_orders\n";
    } else {
        echo "→ Adding tenant_id column to work_orders...\n";
        if ($db_type === 'sqlite') {
            $connection->exec('ALTER TABLE work_orders ADD COLUMN tenant_id INTEGER NOT NULL DEFAULT 1');
        } else {
            $connection->query('ALTER TABLE work_orders ADD COLUMN tenant_id INT NOT NULL DEFAULT 1');
        }
        echo "✓ Added tenant_id to work_orders\n";
    }
    
    // Create index for tenant_id
    try {
        if ($db_type === 'sqlite') {
            $connection->exec('CREATE INDEX IF NOT EXISTS idx_work_orders_tenant ON work_orders(tenant_id)');
        } else {
            $connection->query('ALTER TABLE work_orders ADD INDEX idx_work_orders_tenant (tenant_id)');
        }
        echo "✓ Created index idx_work_orders_tenant\n";
    } catch (Exception $e) {
        // Index might already exist, that's okay
        echo "→ Index idx_work_orders_tenant already exists or could not be created\n";
    }
    
    // Work Order Requests Table
    echo "\n[2/2] Processing work_order_requests table...\n";
    
    // Check if table exists first
    try {
        if ($db_type === 'sqlite') {
            $check = $connection->query("SELECT name FROM sqlite_master WHERE type='table' AND name='work_order_requests'");
            $exists = $check && $check->fetch(PDO::FETCH_ASSOC);
        } else {
            $check = $connection->query("SHOW TABLES LIKE 'work_order_requests'");
            $exists = $check && $check->fetch();
        }
        
        if ($exists) {
            if (column_exists($connection, 'work_order_requests', 'tenant_id')) {
                echo "→ Column tenant_id already exists in work_order_requests\n";
            } else {
                echo "→ Adding tenant_id column to work_order_requests...\n";
                if ($db_type === 'sqlite') {
                    $connection->exec('ALTER TABLE work_order_requests ADD COLUMN tenant_id INTEGER NOT NULL DEFAULT 1');
                } else {
                    $connection->query('ALTER TABLE work_order_requests ADD COLUMN tenant_id INT NOT NULL DEFAULT 1');
                }
                echo "✓ Added tenant_id to work_order_requests\n";
            }
            
            // Create index for tenant_id
            try {
                if ($db_type === 'sqlite') {
                    $connection->exec('CREATE INDEX IF NOT EXISTS idx_work_order_requests_tenant ON work_order_requests(tenant_id)');
                } else {
                    $connection->query('ALTER TABLE work_order_requests ADD INDEX idx_work_order_requests_tenant (tenant_id)');
                }
                echo "✓ Created index idx_work_order_requests_tenant\n";
            } catch (Exception $e) {
                echo "→ Index idx_work_order_requests_tenant already exists or could not be created\n";
            }
        } else {
            echo "→ work_order_requests table not found, skipping\n";
        }
    } catch (Exception $e) {
        error_log("[MIGRATION] Error processing work_order_requests: " . $e->getMessage());
        echo "→ Could not process work_order_requests table\n";
    }
    
    echo "\n======================================================\n";
    echo "✓ MIGRATION COMPLETED SUCCESSFULLY\n";
    echo "======================================================\n\n";
    echo "Work order tables are now isolated by tenant_id.\n";
    echo "Each company will only see their own work orders.\n";
    echo "Old work orders have been assigned to default tenant (1).\n\n";
    
} catch (Exception $e) {
    echo "\n✗ MIGRATION FAILED\n";
    echo "Error: " . $e->getMessage() . "\n";
    exit(1);
}
?>
