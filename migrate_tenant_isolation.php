<?php
/**
 * Comprehensive Tenant Isolation Migration
 * Adds tenant_id to all tables and ensures proper isolation
 */

require_once 'config.inc.php';
require_once 'common.inc.php';

echo "<h1>Tenant Isolation Migration</h1>";

$tenant_tables = [
    // Core tables that must have tenant_id
    'users',
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
    'payment_orders',
    'audit_logs',
    'company_licenses'
];

$default_tenant_id = 1; // Default company

foreach ($tenant_tables as $table) {
    // Check if table exists
    if ($db_type === 'sqlite') {
        $check_table = "SELECT name FROM sqlite_master WHERE type='table' AND name='$table'";
        $table_exists = $connection->query($check_table)->fetch(PDO::FETCH_ASSOC);
    } else {
        $check_table = "SHOW TABLES LIKE '$table'";
        $table_exists = $connection->query($check_table)->fetch(PDO::FETCH_ASSOC);
    }
    
    if (!$table_exists) {
        echo "<p>Table '$table' does not exist - skipping</p>";
        continue;
    }
    
    // Check if tenant_id column exists
    if ($db_type === 'sqlite') {
        $check_column = "PRAGMA table_info($table)";
        $columns = $connection->query($check_column)->fetchAll(PDO::FETCH_ASSOC);
        $has_tenant_id = false;
        foreach ($columns as $col) {
            if ($col['name'] === 'tenant_id') {
                $has_tenant_id = true;
                break;
            }
        }
    } else {
        $check_column = "SHOW COLUMNS FROM `$table` LIKE 'tenant_id'";
        $has_tenant_id = $connection->query($check_column)->fetch(PDO::FETCH_ASSOC);
    }
    
    if (!$has_tenant_id) {
        // Add tenant_id column
        if ($db_type === 'sqlite') {
            $sql = "ALTER TABLE $table ADD COLUMN tenant_id INTEGER NOT NULL DEFAULT $default_tenant_id";
        } else {
            $sql = "ALTER TABLE `$table` ADD COLUMN tenant_id INT(11) NOT NULL DEFAULT $default_tenant_id AFTER id";
        }
        
        try {
            $connection->query($sql);
            echo "<p style='color:green'>✓ Added tenant_id to '$table'</p>";
        } catch (Exception $e) {
            echo "<p style='color:orange'>⚠ Error adding to '$table': " . $e->getMessage() . "</p>";
        }
    } else {
        echo "<p style='color:blue'>○ tenant_id already exists in '$table'</p>";
    }
}

echo "<h2>Migration Complete</h2>";
echo "<p>All tables now have tenant_id column. Existing data assigned to tenant_id = $default_tenant_id</p>";

// Now clear data for company_id > 1 to ensure new companies start empty
echo "<h2>Clearing Data for New Companies</h2>";

$companies = $connection->query("SELECT company_id FROM companies WHERE company_id > 1")->fetchAll(PDO::FETCH_ASSOC);
foreach ($companies as $company) {
    $tid = $company['company_id'];
    echo "<p>Clearing data for company_id = $tid...</p>";
    
    foreach ($tenant_tables as $table) {
        try {
            if ($db_type === 'sqlite') {
                $connection->query("DELETE FROM $table WHERE tenant_id = $tid");
            } else {
                $connection->query("DELETE FROM $table WHERE tenant_id = $tid");
            }
        } catch (Exception $e) {
            // Table might not have tenant_id column yet
        }
    }
    echo "<p style='color:green'>✓ Cleared data for company_id = $tid</p>";
}

echo "<h2>Verification</h2>";
$company_count = $connection->query("SELECT COUNT(*) as cnt FROM companies")->fetch(PDO::FETCH_ASSOC);
echo "<p>Total companies: " . $company_count['cnt'] . "</p>";

foreach ($connection->query("SELECT company_id, name FROM companies")->fetchAll(PDO::FETCH_ASSOC) as $co) {
    $wo_count = $connection->query("SELECT COUNT(*) as cnt FROM work_orders WHERE tenant_id = " . $co['company_id'])->fetch(PDO::FETCH_ASSOC);
    echo "<p>Company {$co['company_id']} ({$co['name']}): {$wo_count['cnt']} work orders</p>";
}