<?php
/**
 * Production Database Clearing Script
 * Clears all data from tables while preserving schema for clean production deployment
 */

require_once __DIR__ . '/config.inc.php';
require_once __DIR__ . '/common.inc.php';

if (PHP_SAPI !== 'cli') {
    echo "This script is CLI-only for security.\n";
    exit(1);
}

if (!$db_available) {
    fwrite(STDERR, "Database unavailable: {$db_error}\n");
    exit(1);
}

echo "Starting production database clearing process...\n";
echo "Database Type: {$db_type}\n\n";

$options = getopt('', ['dry-run', 'help']);
$dryRun = isset($options['dry-run']);
$showHelp = isset($options['help']);

if ($showHelp) {
    echo "Usage: php clear_production_data.php [--dry-run]\n";
    echo "  --dry-run     Show what would be cleared without actually doing it.\n";
    exit(0);
}

if ($dryRun) {
    echo "DRY RUN MODE - No changes will be made\n\n";
}

// Helper function for table existence check (if not already defined)
if (!function_exists('table_exists')) {
    function table_exists($table) {
        global $connection, $db_type;
        try {
            if ($db_type === 'sqlite') {
                $stmt = $connection->query("SELECT name FROM sqlite_master WHERE type='table' AND name='" . sanitize_input($table) . "'");
                return $stmt && $stmt->fetch(PDO::FETCH_ASSOC) !== false;
            } else {
                $result = $connection->query("SHOW TABLES LIKE '" . sanitize_input($table) . "'");
                return $result && $result->fetch(PDO::FETCH_ASSOC) !== false;
            }
        } catch (Exception $e) {
            return false;
        }
    }
}

// Tables to clear (excluding system/user tables that should keep minimal data)
$tables_to_clear = [
    'equipment',
    'work_orders',
    'hot_jobs',
    'mechanics',
    'next_wo',
    'play',
    'trouble_calls',
    'pm_masters',
    'pm_tasks',
    'pm_required_parts',
    'pm_schedule_log',
    'parts_master',
    'inventory_transactions',
    'stock_locales',
    'equipment_spares',
    'work_order_spares',
    'wo_parts',
    'purchase_requests',
    'purchase_request_items',
    'purchase_orders',
    'purchase_order_items',
    'goods_receipt_notes',
    'goods_receipt_items',
    'vendors',
    'vendor_types',
    'audit_logs'
];

$cleared_tables = 0;
$errors = 0;

foreach ($tables_to_clear as $table) {
    if (!table_exists($table)) {
        echo "Table '{$table}' does not exist - skipping\n";
        continue;
    }

    if ($dryRun) {
        // Count records that would be deleted
        try {
            $count_sql = "SELECT COUNT(*) as count FROM {$table}";
            $stmt = $connection->query($count_sql);
            if ($stmt) {
                $row = $stmt->fetch(PDO::FETCH_ASSOC);
                $count = $row['count'];
                echo "Would clear {$count} records from '{$table}'\n";
            }
        } catch (Exception $e) {
            echo "Error counting records in '{$table}': " . $e->getMessage() . "\n";
        }
    } else {
        // Actually clear the table
        try {
            $delete_sql = "DELETE FROM {$table}";
            $result = $connection->exec($delete_sql);

            if ($result !== false) {
                echo "Cleared {$result} records from '{$table}'\n";
                $cleared_tables++;
            } else {
                echo "Error clearing '{$table}': " . implode(' ', $connection->errorInfo()) . "\n";
                $errors++;
            }
        } catch (Exception $e) {
            echo "Error clearing '{$table}': " . $e->getMessage() . "\n";
            $errors++;
        }
    }
}

// Reset auto-increment counters for key tables
$reset_tables = [
    'equipment' => 1,
    'work_orders' => 1,
    'hot_jobs' => 1,
    'mechanics' => 1,
    'parts_master' => 1,
    'purchase_requests' => 1,
    'purchase_orders' => 1
];

if (!$dryRun) {
    echo "\nResetting auto-increment counters...\n";

    foreach ($reset_tables as $table => $start_value) {
        if (table_exists($table)) {
            try {
                if ($db_type === 'sqlite') {
                    // SQLite doesn't support ALTER TABLE AUTO_INCREMENT, but we can use this approach
                    $connection->exec("DELETE FROM sqlite_sequence WHERE name='{$table}'");
                    echo "Reset auto-increment for '{$table}'\n";
                } else {
                    // MySQL
                    $connection->exec("ALTER TABLE {$table} AUTO_INCREMENT = {$start_value}");
                    echo "Reset auto-increment for '{$table}' to {$start_value}\n";
                }
            } catch (Exception $e) {
                echo "Warning: Could not reset auto-increment for '{$table}': " . $e->getMessage() . "\n";
            }
        }
    }
}

if ($dryRun) {
    echo "\nDRY RUN COMPLETE - No changes were made\n";
} else {
    echo "\nProduction database clearing complete!\n";
    echo "Tables cleared: {$cleared_tables}\n";
    if ($errors > 0) {
        echo "Errors encountered: {$errors}\n";
        exit(1);
    }
    echo "\nYour production database is now clean and ready for use.\n";
}