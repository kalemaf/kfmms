<?php
// Check and fix warehouses table
include 'config.inc.php';
include 'common.inc.php';

echo "<h1>Fixing warehouses Table</h1>";

// Check table structure
echo "<h2>Table Structure</h2>";
$info = $connection->query("PRAGMA table_info(warehouses)")->fetchAll(PDO::FETCH_ASSOC);
$has_tenant = false;
foreach ($info as $col) {
    echo "- {$col['name']}: {$col['type']}<br>";
    if ($col['name'] === 'tenant_id') $has_tenant = true;
}

echo "<p>Has tenant_id: " . ($has_tenant ? "YES" : "NO") . "</p>";

// Add tenant_id if missing
if (!$has_tenant) {
    echo "<p>Adding tenant_id column...</p>";
    $connection->exec("ALTER TABLE warehouses ADD COLUMN tenant_id INTEGER DEFAULT 1");
    echo "<p>✓ Added tenant_id column</p>";
}

// Also check warehouse_locations
echo "<h2>warehouse_locations Structure</h2>";
$info2 = $connection->query("PRAGMA table_info(warehouse_locations)")->fetchAll(PDO::FETCH_ASSOC);
$has_tenant2 = false;
foreach ($info2 as $col) {
    echo "- {$col['name']}: {$col['type']}<br>";
    if ($col['name'] === 'tenant_id') $has_tenant2 = true;
}

if (!$has_tenant2) {
    echo "<p>Adding tenant_id to warehouse_locations...</p>";
    $connection->exec("ALTER TABLE warehouse_locations ADD COLUMN tenant_id INTEGER DEFAULT 1");
    echo "<p>✓ Added tenant_id column</p>";
}

// Clear data for new companies
echo "<h2>Clearing Data for New Companies</h2>";
$connection->exec("DELETE FROM warehouses WHERE tenant_id > 3");
$connection->exec("DELETE FROM warehouse_locations WHERE tenant_id > 3");
echo "<p>✓ Cleared warehouses for companies > 3</p>";

// Verify
echo "<h2>Verification</h2>";
$wh = $connection->query("SELECT tenant_id, COUNT(*) as cnt FROM warehouses GROUP BY tenant_id")->fetchAll(PDO::FETCH_ASSOC);
print_r($wh);

// Test get_warehouses
echo "<h2>Testing get_warehouses()</h2>";
$_SESSION['tenant_id'] = 11;
try {
    $warehouses = get_warehouses($connection);
    echo "Warehouses for tenant 11: " . count($warehouses) . "<br>";
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "<br>";
}

$_SESSION['tenant_id'] = 1;
try {
    $warehouses = get_warehouses($connection);
    echo "Warehouses for tenant 1: " . count($warehouses) . "<br>";
    foreach ($warehouses as $w) {
        echo "- {$w['warehouse_name']}<br>";
    }
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "<br>";
}

echo "<p><strong>✓ Done!</strong></p>";