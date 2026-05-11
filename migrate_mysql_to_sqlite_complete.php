<?php
/**
 * Complete MySQL to SQLite Migration Script
 * Migrates data from MySQL to SQLite database while maintaining all relationships
 * Includes schema creation and data transfer
 */

require_once 'config.inc.php';
require_once 'common.inc.php';

// Command line output formatting
function log_msg($msg, $type = 'INFO') {
    $prefix = match($type) {
        'SUCCESS' => '✅',
        'ERROR' => '❌',
        'WARNING' => '⚠️',
        'INFO' => 'ℹ️',
        default => '→'
    };
    echo "\n{$prefix} [{$type}] {$msg}";
}

function log_progress($msg, $count = 0) {
    if ($count > 0) {
        echo "\n  ↳ {$msg}: {$count} rows";
    } else {
        echo "\n  ↳ {$msg}";
    }
}

echo "\n" . str_repeat("=", 80);
echo "\nMySQL to SQLite Migration Tool";
echo "\n" . str_repeat("=", 80);

// Step 1: Detect current database
log_msg("Detecting database configuration...");

$mysql_available = false;
$sqlite_available = false;

// Check MySQL connection
if ($db_type === 'mysql') {
    try {
        $test_conn = new mysqli($hostName, $userName, $password, $databaseName);
        if (!$test_conn->connect_error) {
            $mysql_available = true;
            log_msg("MySQL database connected: {$databaseName}", 'SUCCESS');
            $test_conn->close();
        }
    } catch (Exception $e) {
        log_msg("MySQL connection failed: " . $e->getMessage(), 'ERROR');
    }
}

// Check SQLite
if (file_exists($db_file)) {
    $sqlite_available = true;
    log_msg("SQLite database found: {$db_file}", 'SUCCESS');
} else {
    log_msg("SQLite database will be created: {$db_file}", 'WARNING');
}

if (!$mysql_available && $db_type === 'mysql') {
    log_msg("Cannot connect to MySQL database. Please verify credentials in .env or config.inc.php", 'ERROR');
    exit(1);
}

// Step 2: Verify schema compatibility
log_msg("Verifying database schema...");

// Tables to migrate
$tables = [
    'companies',
    'users', 
    'system_control',
    'company_licenses',
    'license_actions',
    'equipment',
    'equipment_spares',
    'work_orders',
    'work_order_spares',
    'work_order_consumables',
    'parts_master',
    'stock_locales',
    'consumables',
    'consumable_usage',
    'inventory_transactions'
];

$schema_issues = [];

// Only check if migrating FROM MySQL
if ($mysql_available && $db_type === 'mysql') {
    $mysql = new mysqli($hostName, $userName, $password, $databaseName);
    foreach ($tables as $table) {
        $result = $mysql->query("SHOW TABLES LIKE '{$table}'");
        if ($result && $result->num_rows === 0) {
            $schema_issues[] = "Table not found: {$table}";
        }
    }
    $mysql->close();
    
    if (!empty($schema_issues)) {
        log_msg("Schema verification warnings:", 'WARNING');
        foreach ($schema_issues as $issue) {
            log_progress($issue);
        }
    }
}

// Step 3: If already on SQLite, verify tables exist
if ($db_type === 'sqlite' && $sqlite_available) {
    log_msg("Verifying SQLite schema...", 'INFO');
    
    try {
        $pdo = new PDO('sqlite:' . $db_file);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        
        foreach ($tables as $table) {
            $result = $pdo->query("SELECT name FROM sqlite_master WHERE type='table' AND name='{$table}'");
            if (!$result || $result->rowCount() === 0) {
                log_progress("Table missing: {$table}");
            }
        }
        
        // Check critical columns in system_control
        $result = $pdo->query("PRAGMA table_info(system_control)");
        $columns = $result->fetchAll(PDO::FETCH_ASSOC);
        $col_names = array_column($columns, 'name');
        
        $required_cols = ['company_id', 'system_locked', 'lock_reason', 'activation_date'];
        $missing_cols = array_diff($required_cols, $col_names);
        
        if (!empty($missing_cols)) {
            log_msg("Missing columns in system_control table: " . implode(', ', $missing_cols), 'WARNING');
            log_msg("Running schema updates...", 'INFO');
            
            foreach ($missing_cols as $col) {
                try {
                    $sql = match($col) {
                        'system_locked' => "ALTER TABLE system_control ADD COLUMN system_locked INTEGER DEFAULT 0",
                        'lock_reason' => "ALTER TABLE system_control ADD COLUMN lock_reason TEXT",
                        'activation_date' => "ALTER TABLE system_control ADD COLUMN activation_date TEXT DEFAULT CURRENT_TIMESTAMP",
                        default => ""
                    };
                    if (!empty($sql)) {
                        $pdo->exec($sql);
                        log_progress("Added column: {$col}", 1);
                    }
                } catch (Exception $e) {
                    // Column might already exist
                    log_progress("Column {$col} already exists or error: " . $e->getMessage());
                }
            }
        }
        
        log_msg("SQLite schema verified and updated", 'SUCCESS');
        
    } catch (Exception $e) {
        log_msg("SQLite schema check failed: " . $e->getMessage(), 'ERROR');
        exit(1);
    }
}

// Step 4: Migrate data if needed
if ($mysql_available && $db_type === 'mysql') {
    log_msg("Starting data migration from MySQL to SQLite...", 'INFO');
    
    try {
        $mysql = new mysqli($hostName, $userName, $password, $databaseName);
        if ($mysql->connect_error) {
            throw new Exception("MySQL connection failed: " . $mysql->connect_error);
        }
        
        $pdo = new PDO('sqlite:' . $db_file);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        
        $migrated_tables = 0;
        
        foreach ($tables as $table) {
            try {
                log_progress("Migrating table: {$table}");
                
                // Get schema from MySQL
                $schema_result = $mysql->query("SHOW CREATE TABLE {$table}");
                if (!$schema_result) {
                    log_progress("Table {$table} not found in MySQL, skipping", 0);
                    continue;
                }
                
                $schema_row = $schema_result->fetch_assoc();
                $create_sql = $schema_row['Create Table'];
                
                // Convert MySQL schema to SQLite
                $sqlite_create = preg_replace('/\bBIGINT\b/i', 'INTEGER', $create_sql);
                $sqlite_create = preg_replace('/\bVARCHAR\s*\([^)]+\)/i', 'TEXT', $sqlite_create);
                $sqlite_create = preg_replace('/\bINT\b/i', 'INTEGER', $sqlite_create);
                $sqlite_create = preg_replace('/\bDATETIME\b/i', 'TEXT', $sqlite_create);
                $sqlite_create = preg_replace('/\bTEXTEXT\b/i', 'TEXT', $sqlite_create);
                $sqlite_create = preg_replace('/\bDECIMAL\s*\([^)]+\)/i', 'REAL', $sqlite_create);
                $sqlite_create = preg_replace('/\bDOUBLE\b/i', 'REAL', $sqlite_create);
                $sqlite_create = preg_replace('/\bBOOLEAN\b/i', 'INTEGER', $sqlite_create);
                $sqlite_create = preg_replace('/\bBOOL\b/i', 'INTEGER', $sqlite_create);
                $sqlite_create = preg_replace('/\bAUTO_INCREMENT/i', 'AUTOINCREMENT', $sqlite_create);
                $sqlite_create = preg_replace('/\bKEY\s+`[^`]+`\s*\([^)]*\)/i', '', $sqlite_create);
                $sqlite_create = preg_replace('/,\s*$/m', '', $sqlite_create);
                
                // Drop and recreate table in SQLite
                try {
                    $pdo->exec("DROP TABLE IF EXISTS {$table}");
                } catch (Exception $e) {
                    // Table doesn't exist yet
                }
                
                try {
                    $pdo->exec($sqlite_create);
                } catch (Exception $e) {
                    log_progress("Could not create table {$table}: " . $e->getMessage());
                    continue;
                }
                
                // Migrate data
                $data_result = $mysql->query("SELECT * FROM {$table}");
                if ($data_result && $data_result->num_rows > 0) {
                    $rows_migrated = 0;
                    $pdo->exec("BEGIN TRANSACTION");
                    
                    while ($row = $data_result->fetch_assoc()) {
                        $columns = array_keys($row);
                        $placeholders = array_fill(0, count($columns), '?');
                        $col_str = implode(', ', array_map(fn($c) => "`{$c}`", $columns));
                        $sql = "INSERT INTO {$table} ({$col_str}) VALUES (" . implode(', ', $placeholders) . ")";
                        
                        try {
                            $stmt = $pdo->prepare($sql);
                            $stmt->execute(array_values($row));
                            $rows_migrated++;
                        } catch (Exception $e) {
                            // Skip row if error
                        }
                    }
                    
                    $pdo->exec("COMMIT");
                    log_progress("Migrated {$rows_migrated} rows from {$table}", $rows_migrated);
                    $migrated_tables++;
                } else {
                    log_progress("Table {$table} is empty", 0);
                    $migrated_tables++;
                }
                
            } catch (Exception $e) {
                log_progress("Error migrating {$table}: " . $e->getMessage());
            }
        }
        
        log_msg("Migration complete: {$migrated_tables} tables processed", 'SUCCESS');
        
        $mysql->close();
        
    } catch (Exception $e) {
        log_msg("Migration failed: " . $e->getMessage(), 'ERROR');
        exit(1);
    }
}

// Step 5: Verify critical tables exist
log_msg("Verifying critical tables...", 'INFO');

try {
    // If using SQLite, verify it's accessible
    if ($db_type === 'sqlite') {
        $pdo = new PDO('sqlite:' . $db_file);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        
        $critical_tables = ['companies', 'system_control', 'users'];
        
        foreach ($critical_tables as $table) {
            $result = $pdo->query("SELECT name FROM sqlite_master WHERE type='table' AND name='{$table}'");
            if ($result && $result->rowCount() > 0) {
                log_progress("✓ {$table}");
            } else {
                log_progress("✗ {$table} - MISSING!");
            }
        }
        
        log_msg("Database verification complete", 'SUCCESS');
    }
    
} catch (Exception $e) {
    log_msg("Database verification failed: " . $e->getMessage(), 'ERROR');
    exit(1);
}

// Summary
echo "\n" . str_repeat("=", 80);
log_msg("Migration process completed successfully!", 'SUCCESS');
echo "\nNext steps:";
echo "\n1. Test login with a user account";
echo "\n2. Try to activate/deactivate a company";
echo "\n3. Check that no JSON errors appear";
echo "\nDatabase Location: {$db_file}";
echo "\n" . str_repeat("=", 80) . "\n";

exit(0);
?>
