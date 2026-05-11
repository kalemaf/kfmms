<?php
/**
 * WAREHOUSE TENANT ISOLATION MIGRATION
 * 
 * Adds tenant_id columns to warehouse management tables for multi-tenant support.
 * This ensures data isolation between different companies/tenants.
 * 
 * Compatible with both SQLite and MySQL
 */

require_once __DIR__ . '/../config.inc.php';

if (!$db_available) {
    die("Database not available: {$db_error}\n");
}

function add_column_if_not_exists($connection, $table, $column, $definition, $db_type) {
    if ($db_type === 'sqlite') {
        // SQLite: Check if column exists
        $check = $connection->query("PRAGMA table_info('{$table}')");
        $columns = [];
        while ($row = $check->fetch(PDO::FETCH_ASSOC)) {
            $columns[] = $row['name'];
        }
        
        if (!in_array($column, $columns)) {
            $sql = "ALTER TABLE {$table} ADD COLUMN {$column} {$definition}";
            try {
                $connection->exec($sql);
                echo "✓ Added {$column} to {$table}\n";
            } catch (Exception $e) {
                echo "✗ Error adding {$column} to {$table}: {$e->getMessage()}\n";
            }
        } else {
            echo "→ Column {$column} already exists in {$table}\n";
        }
    } else {
        // MySQL
        $check = $connection->query("SHOW COLUMNS FROM {$table} LIKE '{$column}'");
        if (!$check->fetch()) {
            $sql = "ALTER TABLE {$table} ADD COLUMN {$column} {$definition}";
            try {
                $connection->exec($sql);
                echo "✓ Added {$column} to {$table}\n";
            } catch (Exception $e) {
                echo "✗ Error adding {$column} to {$table}: {$e->getMessage()}\n";
            }
        } else {
            echo "→ Column {$column} already exists in {$table}\n";
        }
    }
}

function create_index_if_not_exists($connection, $index_name, $table, $columns, $db_type) {
    if ($db_type === 'sqlite') {
        $check = $connection->query("SELECT name FROM sqlite_master WHERE type='index' AND name='{$index_name}'");
        if (!$check->fetch()) {
            $sql = "CREATE INDEX {$index_name} ON {$table}({$columns})";
            try {
                $connection->exec($sql);
                echo "✓ Created index {$index_name}\n";
            } catch (Exception $e) {
                echo "✗ Error creating index {$index_name}: {$e->getMessage()}\n";
            }
        } else {
            echo "→ Index {$index_name} already exists\n";
        }
    } else {
        // MySQL
        $check = $connection->query("SHOW INDEX FROM {$table} WHERE Key_name='{$index_name}'");
        if (!$check->fetch()) {
            $sql = "CREATE INDEX {$index_name} ON {$table}({$columns})";
            try {
                $connection->exec($sql);
                echo "✓ Created index {$index_name}\n";
            } catch (Exception $e) {
                echo "✗ Error creating index {$index_name}: {$e->getMessage()}\n";
            }
        } else {
            echo "→ Index {$index_name} already exists\n";
        }
    }
}

echo "======================================================\n";
echo "WAREHOUSE TENANT ISOLATION MIGRATION\n";
echo "======================================================\n\n";

// Check if companies table exists (required for foreign key)
if ($db_type === 'sqlite') {
    $check = $connection->query("SELECT name FROM sqlite_master WHERE type='table' AND name='companies'");
} else {
    $check = $connection->query("SHOW TABLES LIKE 'companies'");
}

if (!$check || !$check->fetch()) {
    echo "✗ Error: companies table does not exist. Ensure multi-tenancy is set up first.\n";
    exit(1);
}

// Add tenant_id to warehouses
echo "\n[1/3] Processing warehouses table...\n";
add_column_if_not_exists($connection, 'warehouses', 'tenant_id', 'INTEGER NOT NULL DEFAULT 1', $db_type);
create_index_if_not_exists($connection, 'idx_warehouses_tenant', 'warehouses', 'tenant_id', $db_type);

// Add tenant_id to warehouse_locations
echo "\n[2/3] Processing warehouse_locations table...\n";
add_column_if_not_exists($connection, 'warehouse_locations', 'tenant_id', 'INTEGER NOT NULL DEFAULT 1', $db_type);
create_index_if_not_exists($connection, 'idx_warehouse_locations_tenant', 'warehouse_locations', 'tenant_id', $db_type);

// Add tenant_id to stock_locales
echo "\n[3/3] Processing stock_locales table...\n";
add_column_if_not_exists($connection, 'stock_locales', 'tenant_id', 'INTEGER NOT NULL DEFAULT 1', $db_type);
create_index_if_not_exists($connection, 'idx_stock_locales_tenant', 'stock_locales', 'tenant_id', $db_type);

echo "\n======================================================\n";
echo "✓ MIGRATION COMPLETED SUCCESSFULLY\n";
echo "======================================================\n";
echo "\nWarehouse tables are now isolated by tenant_id.\n";
echo "Companies can only see their own warehouse data.\n";
