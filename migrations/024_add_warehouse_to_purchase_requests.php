<?php
/**
 * Migration 024: Add Warehouse Support to Purchase Requests
 * 
 * Adds warehouse_id and site_location_id columns to purchase_requests table
 * with proper multi-tenant support
 */

require_once __DIR__ . '/../config.inc.php';
require_once __DIR__ . '/../common.inc.php';

echo "\n╔════════════════════════════════════════════════════════════════════════╗\n";
echo "║    MIGRATION 024: Add Warehouse Support to Purchase Requests          ║\n";
echo "╚════════════════════════════════════════════════════════════════════════╝\n\n";

if (!$connection) {
    echo "ERROR: Database connection not available\n";
    exit(1);
}

$db_type = $GLOBALS['db_type'] ?? 'sqlite';

try {
    // Check if purchase_requests table exists
    if ($db_type === 'sqlite') {
        $check = $connection->query("SELECT name FROM sqlite_master WHERE type='table' AND name='purchase_requests'");
        $table_exists = $check && $check->fetch(PDO::FETCH_ASSOC);
    } else {
        $check = $connection->query("SHOW TABLES LIKE 'purchase_requests'");
        $table_exists = $check && $check->fetch();
    }
    
    if (!$table_exists) {
        echo "[!] purchase_requests table not found\n";
        exit(0);
    }
    
    // Check if columns already exist
    if ($db_type === 'sqlite') {
        $stmt = $connection->query("PRAGMA table_info('purchase_requests')");
        $columns = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $columns[] = $row['name'];
        }
    } else {
        $stmt = $connection->query("SHOW COLUMNS FROM purchase_requests");
        $columns = [];
        while ($row = $stmt->fetch_assoc()) {
            $columns[] = $row['Field'];
        }
    }
    
    $needs_warehouse = !in_array('warehouse_id', $columns);
    $needs_site = !in_array('site_location_id', $columns);
    $needs_tenant = !in_array('tenant_id', $columns);
    
    if ($needs_warehouse) {
        echo "[→] Adding warehouse_id column...\n";
        try {
            if ($db_type === 'sqlite') {
                $connection->exec("ALTER TABLE purchase_requests ADD COLUMN warehouse_id INTEGER DEFAULT NULL");
            } else {
                $connection->exec("ALTER TABLE purchase_requests ADD COLUMN warehouse_id INT DEFAULT NULL");
            }
            echo "[✓] warehouse_id column added\n";
        } catch (Exception $e) {
            echo "[!] warehouse_id add failed (may exist): " . $e->getMessage() . "\n";
        }
    } else {
        echo "[✓] warehouse_id column already exists\n";
    }
    
    if ($needs_site) {
        echo "[→] Adding site_location_id column...\n";
        try {
            if ($db_type === 'sqlite') {
                $connection->exec("ALTER TABLE purchase_requests ADD COLUMN site_location_id INTEGER DEFAULT NULL");
            } else {
                $connection->exec("ALTER TABLE purchase_requests ADD COLUMN site_location_id INT DEFAULT NULL");
            }
            echo "[✓] site_location_id column added\n";
        } catch (Exception $e) {
            echo "[!] site_location_id add failed (may exist): " . $e->getMessage() . "\n";
        }
    } else {
        echo "[✓] site_location_id column already exists\n";
    }
    
    if ($needs_tenant) {
        echo "[→] Adding tenant_id column for multi-tenant support...\n";
        try {
            if ($db_type === 'sqlite') {
                $connection->exec("ALTER TABLE purchase_requests ADD COLUMN tenant_id INTEGER DEFAULT 1");
            } else {
                $connection->exec("ALTER TABLE purchase_requests ADD COLUMN tenant_id INT DEFAULT 1");
            }
            echo "[✓] tenant_id column added\n";
        } catch (Exception $e) {
            echo "[!] tenant_id add failed (may exist): " . $e->getMessage() . "\n";
        }
    } else {
        echo "[✓] tenant_id column already exists\n";
    }
    
    // Create indexes for performance
    echo "[→] Creating indexes...\n";
    
    try {
        if ($db_type === 'sqlite') {
            $connection->exec("CREATE INDEX IF NOT EXISTS idx_pr_tenant ON purchase_requests(tenant_id)");
            $connection->exec("CREATE INDEX IF NOT EXISTS idx_pr_warehouse ON purchase_requests(warehouse_id, tenant_id)");
            $connection->exec("CREATE INDEX IF NOT EXISTS idx_pr_site ON purchase_requests(site_location_id, tenant_id)");
        } else {
            $connection->exec("CREATE INDEX IF NOT EXISTS idx_pr_tenant ON purchase_requests(tenant_id)");
            $connection->exec("CREATE INDEX IF NOT EXISTS idx_pr_warehouse ON purchase_requests(warehouse_id, tenant_id)");
            $connection->exec("CREATE INDEX IF NOT EXISTS idx_pr_site ON purchase_requests(site_location_id, tenant_id)");
        }
        echo "[✓] Indexes created\n";
    } catch (Exception $e) {
        echo "[!] Index creation: " . $e->getMessage() . "\n";
    }
    
    echo "\n[✓] Migration completed successfully!\n\n";
    
} catch (Exception $e) {
    echo "[✗] Error: " . $e->getMessage() . "\n";
    exit(1);
}
?>
