<?php
// Check and fix equipment table
include 'config.inc.php';
include 'common.inc.php';

echo "<h1>Fixing Equipment Tenant Isolation</h1>";

// Check table structure
echo "<h2>Table Structure</h2>";
$info = $connection->query("PRAGMA table_info(equipment)")->fetchAll(PDO::FETCH_ASSOC);
$has_tenant = false;
foreach ($info as $col) {
    echo "- {$col['name']}: {$col['type']}<br>";
    if ($col['name'] === 'tenant_id') $has_tenant = true;
}

echo "<p>Has tenant_id: " . ($has_tenant ? "YES" : "NO") . "</p>";

// Add tenant_id if missing
if (!$has_tenant) {
    echo "<p>Adding tenant_id column...</p>";
    $connection->exec("ALTER TABLE equipment ADD COLUMN tenant_id INTEGER DEFAULT 1");
    echo "<p>✓ Added tenant_id column</p>";
}

// Clear data for new companies
echo "<h2>Clearing Data for New Companies</h2>";
$connection->exec("DELETE FROM equipment WHERE tenant_id > 3");
echo "<p>✓ Cleared equipment for companies > 3</p>";

// Verify
echo "<h2>Verification</h2>";
$eq = $connection->query("SELECT tenant_id, COUNT(*) as cnt FROM equipment GROUP BY tenant_id")->fetchAll(PDO::FETCH_ASSOC);
print_r($eq);

// Test filtering
echo "<h2>Testing Tenant Filtering</h2>";

$_SESSION['tenant_id'] = 11;
$rows = safe_query_all("SELECT id, description FROM equipment ORDER BY description LIMIT 10");
echo "<p>Equipment for tenant 11: " . count($rows) . "</p>";

$_SESSION['tenant_id'] = 1;
$rows = safe_query_all("SELECT id, description FROM equipment ORDER BY description LIMIT 10");
echo "<p>Equipment for tenant 1: " . count($rows) . "</p>";
foreach ($rows as $e) {
    echo "- {$e['id']}: {$e['description']}<br>";
}

echo "<p><strong>✓ Done!</strong></p>";