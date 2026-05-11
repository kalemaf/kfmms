#!/usr/bin/php
<?php
/**
 * Test: Add wo_number column
 */

require_once 'config.inc.php';

echo "Testing wo_number migration...\n\n";

try {
    $connection = get_database_connection();
    echo "✅ Database connection successful\n\n";
    
    // Check if column exists
    $check = $connection->query("PRAGMA table_info('work_orders')");
    echo "Checking existing columns...\n";
    $columns = [];
    while ($col = $check->fetch(PDO::FETCH_ASSOC)) {
        $columns[] = $col['name'];
    }
    
    if (in_array('wo_number', $columns)) {
        echo "✅ wo_number column already exists\n";
    } else {
        echo "⚠️  wo_number column missing, adding it...\n";
        $connection->exec("ALTER TABLE work_orders ADD COLUMN wo_number INTEGER DEFAULT 0");
        echo "✅ wo_number column added\n";
    }
    
    // Get all work orders
    $result = $connection->query("SELECT COUNT(*) as cnt FROM work_orders");
    $row = $result->fetch(PDO::FETCH_ASSOC);
    echo "Total work orders in database: " . $row['cnt'] . "\n\n";
    
    // Get tenant distribution
    $result = $connection->query("SELECT tenant_id, COUNT(*) as cnt FROM work_orders GROUP BY tenant_id ORDER BY tenant_id");
    echo "Work orders by tenant:\n";
    while ($row = $result->fetch(PDO::FETCH_ASSOC)) {
        echo "  Tenant " . $row['tenant_id'] . ": " . $row['cnt'] . " WOs\n";
    }
    
    echo "\n✅ Migration successful!\n";
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    exit(1);
}
?>
