<?php
// Check and fix pm_masters table
include 'config.inc.php';
include 'common.inc.php';

echo "<h1>Fixing pm_masters Tenant Isolation</h1>";

// Check table structure
echo "<h2>Table Structure</h2>";
$info = $connection->query("PRAGMA table_info(pm_masters)")->fetchAll(PDO::FETCH_ASSOC);
$has_tenant = false;
foreach ($info as $col) {
    echo "- {$col['name']}: {$col['type']}<br>";
    if ($col['name'] === 'tenant_id') $has_tenant = true;
}

echo "<p>Has tenant_id: " . ($has_tenant ? "YES" : "NO") . "</p>";

// Add tenant_id if missing
if (!$has_tenant) {
    echo "<p>Adding tenant_id column...</p>";
    $connection->exec("ALTER TABLE pm_masters ADD COLUMN tenant_id INTEGER DEFAULT 1");
    echo "<p>✓ Added tenant_id column</p>";
}

// Clear data for new companies
echo "<h2>Clearing Data for New Companies</h2>";
$connection->exec("DELETE FROM pm_masters WHERE tenant_id > 3");
echo "<p>✓ Cleared pm_masters for companies > 3</p>";

// Verify
echo "<h2>Verification</h2>";
$pm = $connection->query("SELECT tenant_id, COUNT(*) as cnt FROM pm_masters GROUP BY tenant_id")->fetchAll(PDO::FETCH_ASSOC);
print_r($pm);

// Test filtering
echo "<h2>Testing Tenant Filtering</h2>";

$_SESSION['tenant_id'] = 11;
$query = "SELECT pm_id, pm_title, asset_name FROM pm_masters ORDER BY next_due_date DESC LIMIT 10";
$filtered = apply_tenant_filter($query);
echo "<p>Filtered: $filtered</p>";

$rows = safe_query_all($query);
echo "<p>PMs for tenant 11: " . count($rows) . "</p>";

$_SESSION['tenant_id'] = 1;
$rows = safe_query_all($query);
echo "<p>PMs for tenant 1: " . count($rows) . "</p>";
foreach ($rows as $p) {
    echo "- {$p['pm_title']}: {$p['asset_name']}<br>";
}

echo "<p><strong>✓ Done!</strong></p>";