<?php
require_once 'config.inc.php';

echo "=== DATABASE STRUCTURE DEBUG ===\n\n";

if (!isset($connection)) {
    echo "ERROR: No database connection\n";
    exit(1);
}

if ($db_error) {
    echo "ERROR: $db_error\n";
    exit(1);
}

echo "Database Type: " . $db_type . "\n\n";

try {
    if ($db_type === 'sqlite') {
        // Get table structure
        echo "=== USERS TABLE STRUCTURE ===\n";
        $stmt = $connection->query("PRAGMA table_info('users')");
        $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        if (count($columns) > 0) {
            foreach ($columns as $col) {
                echo "  {$col['name']} ({$col['type']})\n";
            }
        } else {
            echo "  Table 'users' not found or empty\n";
        }
        
        // Get all table names
        echo "\n=== ALL TABLES IN DATABASE ===\n";
        $stmt = $connection->query("SELECT name FROM sqlite_master WHERE type='table' ORDER BY name");
        $tables = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        if (count($tables) > 0) {
            foreach ($tables as $table) {
                echo "  - {$table['name']}\n";
            }
        } else {
            echo "  No tables found\n";
        }
        
        // Try simple select from users
        echo "\n=== TESTING SIMPLE QUERY ===\n";
        $stmt = $connection->query("SELECT * FROM users LIMIT 1");
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($result) {
            echo "First user found, columns: " . implode(", ", array_keys($result)) . "\n";
        } else {
            echo "No users in table\n";
        }
    }
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
}
?>
