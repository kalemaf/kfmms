<?php
require_once 'config.inc.php';
require_once 'common.inc.php';

// Simulate user seka's session
$_SESSION['tenant_id'] = 11;
$_SESSION['company_id'] = 11;
$_SESSION['user'] = 'seka';

echo "=== Testing Tenant Filter for User Seka ===\n";
echo "Session tenant_id: {$_SESSION['tenant_id']}\n";
echo "Session company_id: {$_SESSION['company_id']}\n\n";

// Test the apply_tenant_filter function
$test_query = "SELECT part_code, part_name, tenant_id FROM parts_master";
echo "Original query:\n$test_query\n\n";

$filtered = apply_tenant_filter($test_query);
echo "Filtered query:\n$filtered\n\n";

// Execute both
echo "=== Original Results ===\n";
$res1 = $connection->query($test_query);
while ($row = $res1->fetch_assoc()) {
    echo "- {$row['part_code']}: {$row['part_name']} (tenant_id={$row['tenant_id']})\n";
}

echo "\n=== Filtered Results ===\n";
$res2 = $connection->query($filtered);
$count = 0;
while ($row = $res2->fetch_assoc()) {
    echo "- {$row['part_code']}: {$row['part_name']} (tenant_id={$row['tenant_id']})\n";
    $count++;
}
if ($count === 0) {
    echo "(No results - tenant filter working correctly)\n";
}
?>