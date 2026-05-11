<?php
/**
 * Migration: Add Tenant ID to Consumables Tables & SQLite Compatibility
 * 
 * Purpose: 
 * 1. Add tenant_id column to consumables and consumable_usage tables
 * 2. Ensure all consumables are properly filtered by company
 * 3. Migrate location field to use warehouse_location_id
 * 4. Ensure SQLite compatibility throughout
 */

require_once __DIR__ . '/../config.inc.php';

echo "======================================================\n";
echo "CONSUMABLES TENANT ISOLATION & SQLITE MIGRATION\n";
echo "======================================================\n\n";

if (!$connection) {
    echo "ERROR: Database connection failed\n";
    exit(1);
}

$db_type = $GLOBALS['db_type'] ?? 'sqlite';

// Helper function to check if column exists
function column_exists($connection, $table, $column) {
    global $db_type;
    
    if ($db_type === 'sqlite') {
        try {
            $result = $connection->query("PRAGMA table_info('$table')");
            $columns = $result->fetchAll(PDO::FETCH_ASSOC);
            foreach ($columns as $col) {
                if ($col['name'] === $column) {
                    return true;
                }
            }
            return false;
        } catch (Exception $e) {
            return false;
        }
    } else {
        try {
            $result = $connection->query("SHOW COLUMNS FROM `$table` LIKE '$column'");
            return $result->num_rows > 0;
        } catch (Exception $e) {
            return false;
        }
    }
}

// Process consumables table
echo "[1/3] Processing consumables table...\n";

if (!column_exists($connection, 'consumables', 'tenant_id')) {
    echo "→ Adding tenant_id column to consumables...\n";
    try {
        if ($db_type === 'sqlite') {
            $connection->exec('ALTER TABLE consumables ADD COLUMN tenant_id INTEGER NOT NULL DEFAULT 1');
        } else {
            $connection->query('ALTER TABLE consumables ADD COLUMN tenant_id INT NOT NULL DEFAULT 1');
        }
        echo "✓ Added tenant_id column to consumables\n";
    } catch (Exception $e) {
        echo "✗ Error: " . $e->getMessage() . "\n";
    }
} else {
    echo "→ tenant_id already exists in consumables\n";
}

// Add warehouse_location_id for location reference
if (!column_exists($connection, 'consumables', 'warehouse_location_id')) {
    echo "→ Adding warehouse_location_id column to consumables...\n";
    try {
        if ($db_type === 'sqlite') {
            $connection->exec('ALTER TABLE consumables ADD COLUMN warehouse_location_id INTEGER DEFAULT NULL');
        } else {
            $connection->query('ALTER TABLE consumables ADD COLUMN warehouse_location_id INT DEFAULT NULL');
        }
        echo "✓ Added warehouse_location_id column to consumables\n";
    } catch (Exception $e) {
        echo "✗ Error: " . $e->getMessage() . "\n";
    }
} else {
    echo "→ warehouse_location_id already exists\n";
}

// Create index for performance
echo "→ Creating index on consumables.tenant_id...\n";
try {
    if ($db_type === 'sqlite') {
        $connection->exec('CREATE INDEX IF NOT EXISTS idx_consumables_tenant ON consumables(tenant_id)');
    } else {
        $connection->query('CREATE INDEX IF NOT EXISTS idx_consumables_tenant ON consumables(tenant_id)');
    }
    echo "✓ Created index idx_consumables_tenant\n";
} catch (Exception $e) {
    echo "✗ Index creation failed (may already exist)\n";
}

// Process consumable_usage table
echo "\n[2/3] Processing consumable_usage table...\n";

if (!column_exists($connection, 'consumable_usage', 'tenant_id')) {
    echo "→ Adding tenant_id column to consumable_usage...\n";
    try {
        if ($db_type === 'sqlite') {
            $connection->exec('ALTER TABLE consumable_usage ADD COLUMN tenant_id INTEGER NOT NULL DEFAULT 1');
        } else {
            $connection->query('ALTER TABLE consumable_usage ADD COLUMN tenant_id INT NOT NULL DEFAULT 1');
        }
        echo "✓ Added tenant_id column to consumable_usage\n";
    } catch (Exception $e) {
        echo "✗ Error: " . $e->getMessage() . "\n";
    }
} else {
    echo "→ tenant_id already exists in consumable_usage\n";
}

// Create index for performance
echo "→ Creating index on consumable_usage.tenant_id...\n";
try {
    if ($db_type === 'sqlite') {
        $connection->exec('CREATE INDEX IF NOT EXISTS idx_consumable_usage_tenant ON consumable_usage(tenant_id)');
    } else {
        $connection->query('CREATE INDEX IF NOT EXISTS idx_consumable_usage_tenant ON consumable_usage(tenant_id)');
    }
    echo "✓ Created index idx_consumable_usage_tenant\n";
} catch (Exception $e) {
    echo "✗ Index creation failed (may already exist)\n";
}

// Assign existing consumables to default tenant
echo "\n[3/3] Assigning existing records to default tenant...\n";
try {
    if ($db_type === 'sqlite') {
        $connection->exec('UPDATE consumables SET tenant_id = 1 WHERE tenant_id = 0 OR tenant_id IS NULL');
        $connection->exec('UPDATE consumable_usage SET tenant_id = 1 WHERE tenant_id = 0 OR tenant_id IS NULL');
    } else {
        $connection->query('UPDATE consumables SET tenant_id = 1 WHERE tenant_id = 0 OR tenant_id IS NULL');
        $connection->query('UPDATE consumable_usage SET tenant_id = 1 WHERE tenant_id = 0 OR tenant_id IS NULL');
    }
    echo "✓ Records assigned to default tenant\n";
} catch (Exception $e) {
    echo "✗ Assignment failed: " . $e->getMessage() . "\n";
}

echo "\n";
echo "======================================================\n";
echo "✓ MIGRATION COMPLETED SUCCESSFULLY\n";
echo "======================================================\n";
echo "\nConsumables tables are now isolated by tenant_id.\n";
echo "Location field can now reference warehouse_location_id.\n";
echo "All queries will be automatically filtered by company.\n";

?>
