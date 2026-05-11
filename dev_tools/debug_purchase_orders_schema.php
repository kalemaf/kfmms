<?php
/**
 * Debug: Check purchase orders tables
 */

require_once 'config.inc.php';
require_once 'common.inc.php';

echo "Checking purchase orders database structure...\n\n";

// Check if purchase_orders exists
try {
    $result = $connection->query("SELECT COUNT(*) as cnt FROM purchase_orders");
    if ($result) {
        $row = $result->fetch_assoc();
        echo "✓ purchase_orders table exists - $row[cnt] records\n";
    }
} catch (Exception $e) {
    echo "✗ purchase_orders table query failed: " . $e->getMessage() . "\n";
}

// Check columns in purchase_orders
echo "\nColumns in purchase_orders:\n";
$result = $connection->query("PRAGMA table_info(purchase_orders)");
if ($result) {
    $has_tenant = false;
    while ($row = $result->fetch_assoc()) {
        echo "  - " . $row['name'] . " (" . $row['type'] . ")\n";
        if ($row['name'] === 'tenant_id') $has_tenant = true;
    }
    echo "\nTenant_id column exists: " . ($has_tenant ? "YES" : "NO") . "\n";
    
    if (!$has_tenant) {
        echo "\nAttempting to add tenant_id column...\n";
        try {
            $connection->query("ALTER TABLE purchase_orders ADD COLUMN tenant_id INTEGER DEFAULT 1");
            echo "✓ Successfully added tenant_id to purchase_orders\n";
        } catch (Exception $e) {
            echo "✗ Failed to add tenant_id: " . $e->getMessage() . "\n";
        }
    }
}

// Check if purchase_order_items exists
echo "\n\nChecking purchase_order_items table...\n";
try {
    $result = $connection->query("SELECT COUNT(*) as cnt FROM purchase_order_items");
    if ($result) {
        $row = $result->fetch_assoc();
        echo "✓ purchase_order_items table exists - $row[cnt] records\n";
    }
} catch (Exception $e) {
    echo "✗ purchase_order_items table query failed: " . $e->getMessage() . "\n";
}

// Check columns in purchase_order_items
echo "\nColumns in purchase_order_items:\n";
$result = $connection->query("PRAGMA table_info(purchase_order_items)");
if ($result) {
    $has_tenant = false;
    while ($row = $result->fetch_assoc()) {
        echo "  - " . $row['name'] . " (" . $row['type'] . ")\n";
        if ($row['name'] === 'tenant_id') $has_tenant = true;
    }
    echo "\nTenant_id column exists: " . ($has_tenant ? "YES" : "NO") . "\n";
    
    if (!$has_tenant) {
        echo "\nAttempting to add tenant_id column...\n";
        try {
            $connection->query("ALTER TABLE purchase_order_items ADD COLUMN tenant_id INTEGER DEFAULT 1");
            echo "✓ Successfully added tenant_id to purchase_order_items\n";
        } catch (Exception $e) {
            echo "✗ Failed to add tenant_id: " . $e->getMessage() . "\n";
        }
    }
}

?>
