<?php
include 'config.inc.php';

echo "Testing...\n";

// Simulate kalemat
$_SESSION['tenant_id'] = 11;

// Test apply_tenant_filter
$query = "SELECT * FROM work_orders ORDER BY submit_date DESC";
$filtered = apply_tenant_filter($query);

echo "Filtered: $filtered\n";

// Execute
$res = $connection->query($filtered);
$rows = $res->fetchAll(PDO::FETCH_ASSOC);
echo "Rows: " . count($rows) . "\n";

if (count($rows) == 0) {
    echo "SUCCESS: Tenant isolation working!\n";
} else {
    echo "FAILED: Data visible\n";
}