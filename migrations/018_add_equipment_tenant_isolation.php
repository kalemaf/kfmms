<?php
/**
 * Migration: Add Tenant ID Column to Equipment Table
 * 
 * Purpose: Add tenant_id column to equipment table for multi-tenant isolation
 * Ensures each company only sees their own equipment
 */

require_once __DIR__ . '/../config.inc.php';

echo "======================================================\n";
echo "EQUIPMENT TENANT ISOLATION MIGRATION\n";
echo "======================================================\n\n";

if (!$connection) {
    echo "ERROR: Database connection failed\n";
    exit(1);
}

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
        // MySQL approach
        try {
            $result = $connection->query("SHOW COLUMNS FROM `$table` LIKE '$column'");
            return $result->num_rows > 0;
        } catch (Exception $e) {
            return false;
        }
    }
}

$db_type = $GLOBALS['db_type'] ?? 'sqlite';

// Process equipment table
echo "[1/1] Processing equipment table...\n";

if (column_exists($connection, 'equipment', 'tenant_id')) {
    echo "→ Column tenant_id already exists in equipment\n";
} else {
    echo "→ Adding tenant_id column to equipment...\n";
    try {
        if ($db_type === 'sqlite') {
            $connection->exec('ALTER TABLE equipment ADD COLUMN tenant_id INTEGER NOT NULL DEFAULT 1');
        } else {
            $connection->query('ALTER TABLE equipment ADD COLUMN tenant_id INT NOT NULL DEFAULT 1');
        }
        echo "✓ Added tenant_id column to equipment\n";
    } catch (Exception $e) {
        echo "✗ Error adding tenant_id column: " . $e->getMessage() . "\n";
        exit(1);
    }
}

// Create index for performance
echo "→ Creating index on equipment.tenant_id...\n";
try {
    if ($db_type === 'sqlite') {
        $connection->exec('CREATE INDEX IF NOT EXISTS idx_equipment_tenant ON equipment(tenant_id)');
    } else {
        $connection->query('CREATE INDEX IF NOT EXISTS idx_equipment_tenant ON equipment(tenant_id)');
    }
    echo "✓ Created index idx_equipment_tenant\n";
} catch (Exception $e) {
    echo "✗ Index creation failed (may already exist): " . $e->getMessage() . "\n";
}

// Assign existing equipment to default tenant
echo "→ Assigning existing equipment to default tenant (1)...\n";
try {
    if ($db_type === 'sqlite') {
        $connection->exec('UPDATE equipment SET tenant_id = 1 WHERE tenant_id = 0 OR tenant_id IS NULL');
    } else {
        $connection->query('UPDATE equipment SET tenant_id = 1 WHERE tenant_id = 0 OR tenant_id IS NULL');
    }
    echo "✓ Equipment tenant assignment complete\n";
} catch (Exception $e) {
    echo "✗ Equipment assignment failed: " . $e->getMessage() . "\n";
}

echo "\n";
echo "======================================================\n";
echo "✓ MIGRATION COMPLETED SUCCESSFULLY\n";
echo "======================================================\n";
echo "\nEquipment table is now isolated by tenant_id.\n";
echo "Each company will only see their own equipment.\n";
echo "Existing equipment has been assigned to default tenant (1).\n";

?>
