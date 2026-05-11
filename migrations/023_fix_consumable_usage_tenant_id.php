<?php
/**
 * Migration: Fix Consumable Usage Tenant ID
 * 
 * Fixes existing consumable_usage records that have NULL or invalid tenant_id
 */

require_once __DIR__ . '/../config.inc.php';
require_once __DIR__ . '/../common.inc.php';

echo "\n╔════════════════════════════════════════════════════════════════════════╗\n";
echo "║    FIXING CONSUMABLE USAGE TENANT ID                                  ║\n";
echo "╚════════════════════════════════════════════════════════════════════════╝\n\n";

if (!$connection) {
    echo "ERROR: Database connection not available\n";
    exit(1);
}

try {
    // Check for problematic records
    $check = $connection->query("SELECT COUNT(*) as cnt FROM consumable_usage WHERE tenant_id IS NULL OR tenant_id <= 0")->fetch(PDO::FETCH_ASSOC);
    $count = intval($check['cnt'] ?? 0);
    
    if ($count > 0) {
        echo "[→] Found $count consumable usage records with invalid tenant_id\n";
        echo "[→] Fixing by setting tenant_id = 1 (default tenant)\n";
        
        $connection->exec("UPDATE consumable_usage SET tenant_id = 1 WHERE tenant_id IS NULL OR tenant_id <= 0");
        
        // Verify
        $check_after = $connection->query("SELECT COUNT(*) as cnt FROM consumable_usage WHERE tenant_id IS NULL OR tenant_id <= 0")->fetch(PDO::FETCH_ASSOC);
        $count_after = intval($check_after['cnt'] ?? 0);
        
        if ($count_after == 0) {
            echo "[✓] Fixed $count records successfully\n";
        } else {
            echo "[!] Warning: " . $count_after . " records still have invalid tenant_id\n";
        }
    } else {
        echo "[✓] No consumable usage records with invalid tenant_id found\n";
    }
    
    // Verify index exists
    $index_check = $connection->query("SELECT name FROM sqlite_master WHERE type='index' AND name='idx_consumable_usage_tenant'")->fetch(PDO::FETCH_ASSOC);
    
    if ($index_check) {
        echo "[✓] Index idx_consumable_usage_tenant exists\n";
    } else {
        echo "[→] Creating index for performance...\n";
        try {
            $connection->exec("CREATE INDEX idx_consumable_usage_tenant ON consumable_usage(tenant_id)");
            echo "[✓] Index created\n";
        } catch (Exception $e) {
            echo "[!] Index creation failed (may already exist): " . $e->getMessage() . "\n";
        }
    }
    
    echo "\n[✓] Migration completed successfully!\n\n";
    
} catch (Exception $e) {
    echo "[✗] Error: " . $e->getMessage() . "\n";
    exit(1);
}
?>
