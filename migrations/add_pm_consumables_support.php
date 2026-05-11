<?php
/**
 * Migration: Add PM Consumables Support
 * 
 * Adds:
 * 1. pm_consumables table - Links consumables to PM masters
 * 2. start_time and next_due_time columns to pm_masters (for time-specific scheduling)
 */

require_once __DIR__ . '/../config.inc.php';

if (!$connection) {
    die("Database connection failed.\n");
}

$db_type = 'sqlite';
if (isset($GLOBALS['db_type'])) {
    $db_type = $GLOBALS['db_type'];
} elseif (isset($_ENV['DB_TYPE'])) {
    $db_type = $_ENV['DB_TYPE'];
}

echo "[Migration] Adding PM Consumables Support...\n";

// ============================================================================
// 1. Create pm_consumables table
// ============================================================================

if ($db_type === 'sqlite') {
    $createTableSQL = "
    CREATE TABLE IF NOT EXISTS pm_consumables (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        pm_id INTEGER NOT NULL,
        consumable_id INTEGER NOT NULL,
        quantity_required DECIMAL(12, 2) NOT NULL,
        unit_cost DECIMAL(12, 2) DEFAULT 0.00,
        notes TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (pm_id) REFERENCES pm_masters(pm_id) ON DELETE CASCADE,
        FOREIGN KEY (consumable_id) REFERENCES consumables(id) ON DELETE RESTRICT
    );
    ";
    
    $indexSQL = "
    CREATE INDEX IF NOT EXISTS idx_pm_consumables_pm_id ON pm_consumables(pm_id);
    ";
    
    $indexSQL2 = "
    CREATE INDEX IF NOT EXISTS idx_pm_consumables_consumable_id ON pm_consumables(consumable_id);
    ";
} else {
    // MySQL version
    $createTableSQL = "
    CREATE TABLE IF NOT EXISTS pm_consumables (
        id INT AUTO_INCREMENT PRIMARY KEY,
        pm_id INT NOT NULL,
        consumable_id INT NOT NULL,
        quantity_required DECIMAL(12, 2) NOT NULL,
        unit_cost DECIMAL(12, 2) DEFAULT 0.00,
        notes TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (pm_id) REFERENCES pm_masters(pm_id) ON DELETE CASCADE,
        FOREIGN KEY (consumable_id) REFERENCES consumables(id) ON DELETE RESTRICT,
        INDEX idx_pm_consumables_pm_id (pm_id),
        INDEX idx_pm_consumables_consumable_id (consumable_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ";
    
    $indexSQL = "";
    $indexSQL2 = "";
}

try {
    if ($connection->query($createTableSQL)) {
        echo "[✓] pm_consumables table created successfully.\n";
    } else {
        echo "[✗] Failed to create pm_consumables table: " . implode(' ', $connection->errorInfo()) . "\n";
    }
    
    if ($indexSQL && !$connection->query($indexSQL)) {
        echo "[!] Warning: Could not create index on pm_id: " . implode(' ', $connection->errorInfo()) . "\n";
    }
    
    if ($indexSQL2 && !$connection->query($indexSQL2)) {
        echo "[!] Warning: Could not create index on consumable_id: " . implode(' ', $connection->errorInfo()) . "\n";
    }
} catch (Exception $e) {
    echo "[✗] Error creating pm_consumables table: " . $e->getMessage() . "\n";
}

// ============================================================================
// 2. Add time columns to pm_masters if they don't exist
// ============================================================================

$checkStartTimeCol = false;
$checkNextDueTimeCol = false;

try {
    if ($db_type === 'sqlite') {
        $pragmaStmt = $connection->query("PRAGMA table_info(pm_masters)");
        foreach ($pragmaStmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            if ($row['name'] === 'start_time') {
                $checkStartTimeCol = true;
            }
            if ($row['name'] === 'next_due_time') {
                $checkNextDueTimeCol = true;
            }
        }
    } else {
        // MySQL version
        $showStmt = $connection->query("SHOW COLUMNS FROM pm_masters LIKE 'start_time'");
        if ($showStmt && $showStmt->fetch(PDO::FETCH_ASSOC)) {
            $checkStartTimeCol = true;
        }
        
        $showStmt2 = $connection->query("SHOW COLUMNS FROM pm_masters LIKE 'next_due_time'");
        if ($showStmt2 && $showStmt2->fetch(PDO::FETCH_ASSOC)) {
            $checkNextDueTimeCol = true;
        }
    }
} catch (Exception $e) {
    echo "[!] Could not check existing columns: " . $e->getMessage() . "\n";
}

// Add start_time column if needed
if (!$checkStartTimeCol) {
    try {
        $alterSQL = "ALTER TABLE pm_masters ADD COLUMN start_time TIME DEFAULT '08:00:00'";
        if ($connection->query($alterSQL)) {
            echo "[✓] Added start_time column to pm_masters.\n";
        } else {
            echo "[✗] Failed to add start_time: " . implode(' ', $connection->errorInfo()) . "\n";
        }
    } catch (Exception $e) {
        echo "[✗] Error adding start_time: " . $e->getMessage() . "\n";
    }
} else {
    echo "[✓] start_time column already exists in pm_masters.\n";
}

// Add next_due_time column if needed
if (!$checkNextDueTimeCol) {
    try {
        $alterSQL = "ALTER TABLE pm_masters ADD COLUMN next_due_time TIME DEFAULT '08:00:00'";
        if ($connection->query($alterSQL)) {
            echo "[✓] Added next_due_time column to pm_masters.\n";
        } else {
            echo "[✗] Failed to add next_due_time: " . implode(' ', $connection->errorInfo()) . "\n";
        }
    } catch (Exception $e) {
        echo "[✗] Error adding next_due_time: " . $e->getMessage() . "\n";
    }
} else {
    echo "[✓] next_due_time column already exists in pm_masters.\n";
}

echo "[Migration] Complete!\n";
?>
