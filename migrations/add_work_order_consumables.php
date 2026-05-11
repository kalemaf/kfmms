<?php
/**
 * Migration: Add Work Order Consumables Support
 * 
 * Creates:
 * 1. work_order_consumables table - Links consumables to work orders
 * 2. Auto-reduction on work order completion
 */

require_once __DIR__ . '/../config.inc.php';

if (!$connection) {
    die("Database connection failed.\n");
}

echo "[Migration] Adding Work Order Consumables Support...\n";

// ============================================================================
// 1. Create work_order_consumables table
// ============================================================================

if ($GLOBALS['db_type'] === 'sqlite') {
    $createTableSQL = "
    CREATE TABLE IF NOT EXISTS work_order_consumables (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        work_order_id INTEGER NOT NULL,
        consumable_id INTEGER NOT NULL,
        quantity_required DECIMAL(12, 2) NOT NULL,
        quantity_used DECIMAL(12, 2) DEFAULT 0.00,
        unit_cost DECIMAL(12, 2) DEFAULT 0.00,
        notes TEXT,
        is_consumed INTEGER DEFAULT 0,
        consumed_at TIMESTAMP,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (work_order_id) REFERENCES work_orders(id) ON DELETE CASCADE,
        FOREIGN KEY (consumable_id) REFERENCES consumables(id) ON DELETE RESTRICT
    );
    ";
    
    $indexSQL1 = "CREATE INDEX IF NOT EXISTS idx_woc_work_order_id ON work_order_consumables(work_order_id);";
    $indexSQL2 = "CREATE INDEX IF NOT EXISTS idx_woc_consumable_id ON work_order_consumables(consumable_id);";
    $indexSQL3 = "CREATE INDEX IF NOT EXISTS idx_woc_is_consumed ON work_order_consumables(is_consumed);";
} else {
    // MySQL version
    $createTableSQL = "
    CREATE TABLE IF NOT EXISTS work_order_consumables (
        id INT AUTO_INCREMENT PRIMARY KEY,
        work_order_id INT NOT NULL,
        consumable_id INT NOT NULL,
        quantity_required DECIMAL(12, 2) NOT NULL,
        quantity_used DECIMAL(12, 2) DEFAULT 0.00,
        unit_cost DECIMAL(12, 2) DEFAULT 0.00,
        notes TEXT,
        is_consumed INT DEFAULT 0,
        consumed_at TIMESTAMP NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (work_order_id) REFERENCES work_orders(id) ON DELETE CASCADE,
        FOREIGN KEY (consumable_id) REFERENCES consumables(id) ON DELETE RESTRICT,
        INDEX idx_woc_work_order_id (work_order_id),
        INDEX idx_woc_consumable_id (consumable_id),
        INDEX idx_woc_is_consumed (is_consumed)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ";
    
    $indexSQL1 = "";
    $indexSQL2 = "";
    $indexSQL3 = "";
}

try {
    if ($connection->query($createTableSQL)) {
        echo "[✓] work_order_consumables table created successfully.\n";
    } else {
        echo "[✗] Failed to create work_order_consumables table: " . implode(' ', $connection->errorInfo()) . "\n";
    }
    
    if ($indexSQL1 && !$connection->query($indexSQL1)) {
        echo "[!] Warning: Could not create index on work_order_id: " . implode(' ', $connection->errorInfo()) . "\n";
    }
    
    if ($indexSQL2 && !$connection->query($indexSQL2)) {
        echo "[!] Warning: Could not create index on consumable_id: " . implode(' ', $connection->errorInfo()) . "\n";
    }
    
    if ($indexSQL3 && !$connection->query($indexSQL3)) {
        echo "[!] Warning: Could not create index on is_consumed: " . implode(' ', $connection->errorInfo()) . "\n";
    }
} catch (Exception $e) {
    echo "[✗] Error creating work_order_consumables table: " . $e->getMessage() . "\n";
}

echo "[Migration] Complete!\n";
?>
