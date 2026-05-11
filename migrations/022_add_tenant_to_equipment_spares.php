#!/usr/bin/env php
<?php
/**
 * Migration: Add Tenant ID to Equipment Spares
 * 
 * Adds tenant_id column to equipment_spares table to enable proper multi-tenant isolation
 */

require_once __DIR__ . '/../config.inc.php';
require_once __DIR__ . '/../common.inc.php';

echo "\n╔════════════════════════════════════════════════════════════════════════╗\n";
echo "║    ADDING TENANT_ID TO EQUIPMENT_SPARES                               ║\n";
echo "╚════════════════════════════════════════════════════════════════════════╝\n\n";

if (!$connection) {
    echo "ERROR: Database connection not available\n";
    exit(1);
}

$db_type = $GLOBALS['db_type'] ?? 'sqlite';

try {
    // Check if table exists
    if ($db_type === 'sqlite') {
        $check = $connection->query("SELECT name FROM sqlite_master WHERE type='table' AND name='equipment_spares'");
        $exists = $check && $check->fetch(PDO::FETCH_ASSOC);
    } else {
        $check = $connection->query("SHOW TABLES LIKE 'equipment_spares'");
        $exists = $check && $check->fetch();
    }
    
    if (!$exists) {
        echo "[!] equipment_spares table not found\n";
        exit(0);
    }
    
    // Check if tenant_id column already exists
    if ($db_type === 'sqlite') {
        $stmt = $connection->query("PRAGMA table_info('equipment_spares')");
        $columns = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $columns[] = $row['name'];
        }
        $has_tenant_id = in_array('tenant_id', $columns);
    } else {
        $stmt = $connection->query("SHOW COLUMNS FROM equipment_spares LIKE 'tenant_id'");
        $has_tenant_id = $stmt && $stmt->fetch() !== false;
    }
    
    if ($has_tenant_id) {
        echo "[✓] tenant_id column already exists in equipment_spares\n";
    } else {
        echo "[→] Adding tenant_id column to equipment_spares...\n";
        
        if ($db_type === 'sqlite') {
            $connection->exec("ALTER TABLE equipment_spares ADD COLUMN tenant_id INTEGER NOT NULL DEFAULT 1");
        } else {
            $connection->exec("ALTER TABLE equipment_spares ADD COLUMN tenant_id INT NOT NULL DEFAULT 1");
        }
        
        echo "[✓] tenant_id column added successfully\n";
    }
    
    // Fix any NULL tenant_id values
    $count_result = $connection->query("SELECT COUNT(*) as cnt FROM equipment_spares WHERE tenant_id IS NULL OR tenant_id <= 0");
    $count_row = ($db_type === 'sqlite') ? $count_result->fetch(PDO::FETCH_ASSOC) : $count_row = $count_result->fetch_assoc();
    $null_count = intval($count_row['cnt'] ?? 0);
    
    if ($null_count > 0) {
        echo "[→] Fixing $null_count equipment spares with invalid tenant_id...\n";
        $connection->exec("UPDATE equipment_spares SET tenant_id = 1 WHERE tenant_id IS NULL OR tenant_id <= 0");
        echo "[✓] Fixed invalid tenant_id values\n";
    }
    
    // Create index for performance
    if ($db_type === 'sqlite') {
        $index_check = $connection->query("SELECT name FROM sqlite_master WHERE type='index' AND name='idx_equipment_spares_tenant'");
        $has_index = $index_check && $index_check->fetch(PDO::FETCH_ASSOC);
    } else {
        $index_check = $connection->query("SHOW INDEX FROM equipment_spares WHERE Key_name='idx_equipment_spares_tenant'");
        $has_index = $index_check && $index_check->fetch();
    }
    
    if ($has_index) {
        echo "[✓] Index idx_equipment_spares_tenant already exists\n";
    } else {
        echo "[→] Creating index for tenant_id on equipment_spares...\n";
        if ($db_type === 'sqlite') {
            $connection->exec("CREATE INDEX idx_equipment_spares_tenant ON equipment_spares(tenant_id)");
        } else {
            $connection->exec("ALTER TABLE equipment_spares ADD INDEX idx_equipment_spares_tenant (tenant_id)");
        }
        echo "[✓] Index created successfully\n";
    }
    
    echo "\n[✓] Migration completed successfully!\n\n";
    
} catch (Exception $e) {
    echo "[✗] Error: " . $e->getMessage() . "\n";
    exit(1);
}
?>
