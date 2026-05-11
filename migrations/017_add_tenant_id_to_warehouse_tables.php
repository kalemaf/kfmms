<?php
/**
 * Migration: Add tenant_id to warehouse and warehouse_locations tables
 * Ensures multi-tenant support for inventory management
 */

require_once __DIR__ . '/../config.inc.php';

$db = $connection;
$db_type = $GLOBALS['db_type'] ?? 'sqlite';

try {
    echo "[Migration] Adding tenant_id column to warehouse tables...\n";
    
    // Add tenant_id to warehouses table if it doesn't exist
    if ($db_type === 'sqlite') {
        // SQLite: Check if column exists
        $result = $db->query("PRAGMA table_info(warehouses)");
        $columns = [];
        while ($row = $result->fetch_assoc()) {
            $columns[] = $row['name'];
        }
        
        if (!in_array('tenant_id', $columns)) {
            echo "  - Adding tenant_id to warehouses...\n";
            $db->exec("ALTER TABLE warehouses ADD COLUMN tenant_id INTEGER NOT NULL DEFAULT 1");
            echo "    ✓ Done\n";
        }
        
        // Add tenant_id to warehouse_locations table if it doesn't exist
        $result = $db->query("PRAGMA table_info(warehouse_locations)");
        $columns = [];
        while ($row = $result->fetch_assoc()) {
            $columns[] = $row['name'];
        }
        
        if (!in_array('tenant_id', $columns)) {
            echo "  - Adding tenant_id to warehouse_locations...\n";
            $db->exec("ALTER TABLE warehouse_locations ADD COLUMN tenant_id INTEGER NOT NULL DEFAULT 1");
            echo "    ✓ Done\n";
        }
    } else {
        // MySQL: Use INFORMATION_SCHEMA
        $result = $db->query("SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_NAME='warehouses' AND COLUMN_NAME='tenant_id'");
        if ($result->num_rows === 0) {
            echo "  - Adding tenant_id to warehouses...\n";
            $db->query("ALTER TABLE warehouses ADD COLUMN tenant_id INT NOT NULL DEFAULT 1");
            echo "    ✓ Done\n";
        }
        
        $result = $db->query("SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_NAME='warehouse_locations' AND COLUMN_NAME='tenant_id'");
        if ($result->num_rows === 0) {
            echo "  - Adding tenant_id to warehouse_locations...\n";
            $db->query("ALTER TABLE warehouse_locations ADD COLUMN tenant_id INT NOT NULL DEFAULT 1");
            echo "    ✓ Done\n";
        }
    }
    
    echo "[Migration] ✓ Successfully completed warehouse tenant_id migration\n";
} catch (Exception $e) {
    echo "[Migration Error] " . $e->getMessage() . "\n";
    exit(1);
}
?>
