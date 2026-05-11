<?php
/**
 * Multi-Tenant Migration Runner
 * 
 * This script safely migrates an existing KFMMS installation to a multi-tenant SaaS system.
 * 
 * Usage:
 * php run_multi_tenant_migration.php
 * 
 * What it does:
 * 1. Creates companies table
 * 2. Adds tenant_id to all tables
 * 3. Creates proper indexes
 * 4. Ensures data isolation
 * 5. Verifies integrity
 */

require_once __DIR__ . '/../config.inc.php';
require_once __DIR__ . '/multi_tenant_schema.php';

class MultiTenantMigration {
    private $connection;
    private $db_type;
    private $migrations;
    private $completed = [];
    private $errors = [];
    
    public function __construct($connection, $db_type, $migrations) {
        $this->connection = $connection;
        $this->db_type = $db_type;
        $this->migrations = $migrations;
    }
    
    /**
     * Run all migrations
     */
    public function run() {
        echo "🚀 Starting Multi-Tenant Migration\n";
        echo "Database: $this->db_type\n";
        echo str_repeat("=", 80) . "\n\n";
        
        $sql_variants = $this->migrations;
        
        foreach ($sql_variants as $migration_name => $sql_options) {
            $this->runMigration($migration_name, $sql_options);
        }
        
        $this->printReport();
    }
    
    /**
     * Run a single migration
     */
    private function runMigration($name, $sql_options) {
        try {
            $sql = $sql_options[$this->db_type] ?? null;
            
            if (!$sql) {
                echo "⏭️  SKIPPED: $name (Not applicable for $this->db_type)\n";
                return;
            }
            
            // Skip SQLite comments
            if (strpos($sql, '--') === 0) {
                echo "⏭️  SKIPPED: $name (SQLite)\n";
                return;
            }
            
            if ($this->db_type === 'sqlite') {
                // For SQLite, execute raw SQL
                $statements = array_filter(array_map('trim', explode(';', $sql)));
                foreach ($statements as $statement) {
                    if (!empty($statement) && strpos($statement, '--') !== 0) {
                        $this->connection->exec($statement);
                    }
                }
            } else {
                // For MySQL, use prepared statements
                $statements = array_filter(array_map('trim', explode(';', $sql)));
                foreach ($statements as $statement) {
                    if (!empty($statement)) {
                        $this->connection->query($statement);
                    }
                }
            }
            
            echo "✅ COMPLETED: $name\n";
            $this->completed[] = $name;
            
        } catch (Exception $e) {
            echo "❌ ERROR: $name\n";
            echo "   " . $e->getMessage() . "\n";
            $this->errors[$name] = $e->getMessage();
        }
    }
    
    /**
     * Print migration report
     */
    private function printReport() {
        echo "\n" . str_repeat("=", 80) . "\n";
        echo "📊 MIGRATION REPORT\n";
        echo str_repeat("=", 80) . "\n";
        echo "✅ Completed: " . count($this->completed) . "\n";
        echo "❌ Errors: " . count($this->errors) . "\n";
        
        if (!empty($this->errors)) {
            echo "\nFailed Migrations:\n";
            foreach ($this->errors as $name => $error) {
                echo "  - $name: $error\n";
            }
        }
        
        echo "\n✨ Migration finished!\n";
    }
}

// Initialize migration
try {
    global $connection, $db_type;
    
    if (!$connection) {
        throw new Exception("Database connection failed");
    }
    
    $migration = new MultiTenantMigration($connection, $db_type, $MIGRATIONS);
    $migration->run();
    
} catch (Exception $e) {
    die("Fatal Error: " . $e->getMessage() . "\n");
}
?>
