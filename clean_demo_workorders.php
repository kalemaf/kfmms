<?php
require_once __DIR__ . '/config.inc.php';

echo "Removing demo work orders and resetting counter...\n\n";

try {
    // For SQLite, temporarily disable foreign key constraints
    if ($db_type === 'sqlite') {
        $connection->exec("PRAGMA foreign_keys=OFF");
    }
    
    // Delete all work orders
    $connection->exec("DELETE FROM work_orders");
    echo "✓ Deleted demo work orders\n";
    
    // Reset the auto-increment counter
    $connection->exec("DELETE FROM sqlite_sequence WHERE name='work_orders'");
    echo "✓ Reset work order counter to start at 1\n";
    
    // Re-enable foreign key constraints
    if ($db_type === 'sqlite') {
        $connection->exec("PRAGMA foreign_keys=ON");
    }
    
    // Verify
    $result = $connection->query("SELECT COUNT(*) FROM work_orders");
    $count = $result->fetch(PDO::FETCH_COLUMN);
    echo "\nFinal status:\n";
    echo "✓ Work orders in database: $count\n";
    echo "✓ Next WO ID will be: 1\n";
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    exit(1);
}
?>
