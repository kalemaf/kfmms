<?php
/**
 * VENDOR TENANT ISOLATION MIGRATION
 * 
 * Adds tenant_id columns to vendor management tables for multi-tenant support.
 * This ensures data isolation between different companies/tenants.
 * 
 * Compatible with both SQLite and MySQL
 */

require_once __DIR__ . '/../config.inc.php';

if (!$db_available) {
    die("Database not available: {$db_error}\n");
}

function column_exists($connection, $table, $column, $db_type) {
    if ($db_type === 'sqlite') {
        $check = $connection->query("PRAGMA table_info('{$table}')");
        $columns = [];
        while ($row = $check->fetch(PDO::FETCH_ASSOC)) {
            $columns[] = $row['name'];
        }
        return in_array($column, $columns);
    } else {
        // MySQL
        $check = $connection->query("SHOW COLUMNS FROM {$table} LIKE '{$column}'");
        return $check && $check->fetch() !== false;
    }
}

function add_column_if_not_exists($connection, $table, $column, $definition, $db_type) {
    if (column_exists($connection, $table, $column, $db_type)) {
        echo "→ Column {$column} already exists in {$table}\n";
        return true;
    }
    
    $sql = "ALTER TABLE {$table} ADD COLUMN {$column} {$definition}";
    try {
        $connection->exec($sql);
        echo "✓ Added {$column} to {$table}\n";
        return true;
    } catch (Exception $e) {
        echo "✗ Error adding {$column} to {$table}: {$e->getMessage()}\n";
        return false;
    }
}

function create_index_if_not_exists($connection, $index_name, $table, $columns, $db_type) {
    if ($db_type === 'sqlite') {
        $check = $connection->query("SELECT name FROM sqlite_master WHERE type='index' AND name='{$index_name}'");
        if ($check && $check->fetch()) {
            echo "→ Index {$index_name} already exists\n";
            return true;
        }
    } else {
        // MySQL
        $check = $connection->query("SHOW INDEX FROM {$table} WHERE Key_name='{$index_name}'");
        if ($check && $check->fetch()) {
            echo "→ Index {$index_name} already exists\n";
            return true;
        }
    }
    
    $sql = "CREATE INDEX {$index_name} ON {$table}({$columns})";
    try {
        $connection->exec($sql);
        echo "✓ Created index {$index_name}\n";
        return true;
    } catch (Exception $e) {
        echo "✗ Error creating index {$index_name}: {$e->getMessage()}\n";
        return false;
    }
}

echo "======================================================\n";
echo "VENDOR TENANT ISOLATION MIGRATION\n";
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

// Add tenant_id to vendors
echo "\n[1/2] Processing vendors table...\n";
$success = add_column_if_not_exists($connection, 'vendors', 'tenant_id', 'INTEGER NOT NULL DEFAULT 1', $db_type);
if ($success) {
    create_index_if_not_exists($connection, 'idx_vendors_tenant', 'vendors', 'tenant_id', $db_type);
}

// Add tenant_id to vendor_performance if it exists
echo "\n[2/2] Processing vendor_performance table (if exists)...\n";
if ($db_type === 'sqlite') {
    $check = $connection->query("SELECT name FROM sqlite_master WHERE type='table' AND name='vendor_performance'");
} else {
    $check = $connection->query("SHOW TABLES LIKE 'vendor_performance'");
}

if ($check && $check->fetch()) {
    $success = add_column_if_not_exists($connection, 'vendor_performance', 'tenant_id', 'INTEGER NOT NULL DEFAULT 1', $db_type);
    if ($success) {
        create_index_if_not_exists($connection, 'idx_vendor_performance_tenant', 'vendor_performance', 'tenant_id', $db_type);
    }
    echo "✓ vendor_performance table updated\n";
} else {
    echo "→ vendor_performance table does not exist (skipped)\n";
}

echo "\n======================================================\n";
echo "✓ MIGRATION COMPLETED SUCCESSFULLY\n";
echo "======================================================\n";
echo "\nVendor tables are now isolated by tenant_id.\n";
echo "Companies can only see their own vendor data.\n";
echo "\nNext: Run your application and create a new supplier.\n";

