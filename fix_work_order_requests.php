<?php
// Check and fix work_order_requests table
include 'config.inc.php';
include 'common.inc.php';

echo "<h1>Fixing work_order_requests Tenant Isolation</h1>";

// Check table structure
echo "<h2>Table Structure</h2>";
$info = $connection->query("PRAGMA table_info(work_order_requests)")->fetchAll(PDO::FETCH_ASSOC);
$has_tenant = false;
foreach ($info as $col) {
    echo "- {$col['name']}: {$col['type']}<br>";
    if ($col['name'] === 'tenant_id') $has_tenant = true;
}

// Add tenant_id if missing
if (!$has_tenant) {
    echo "<p>Adding tenant_id column...</p>";
    $connection->exec("ALTER TABLE work_order_requests ADD COLUMN tenant_id INTEGER DEFAULT 1");
    echo "<p>✓ Added tenant_id column</p>";
}

// Clear data for new companies
echo "<h2>Clearing Data for New Companies</h2>";
$connection->exec("DELETE FROM work_order_requests WHERE tenant_id > 3");
echo "<p>✓ Cleared work_order_requests for companies > 3</p>";

// Verify
echo "<h2>Verification</h2>";
$reqs = $connection->query("SELECT tenant_id, COUNT(*) as cnt FROM work_order_requests GROUP BY tenant_id")->fetchAll(PDO::FETCH_ASSOC);
print_r($reqs);

echo "<p><strong>✓ Done!</strong></p>";