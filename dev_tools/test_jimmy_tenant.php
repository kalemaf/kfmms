<?php
require_once 'config.inc.php';
require_once 'common.inc.php';

// Simulate jimmy's session (company_id=14, tenant_id=14)
$_SESSION['tenant_id'] = 14;
$_SESSION['company_id'] = 14;
$_SESSION['user'] = 'jimmy';

echo "=== Testing Tenant Filter for User Jimmy (tenant_id=14) ===\n\n";

// Test the apply_tenant_filter function
$test_query = "SELECT part_code, part_name, tenant_id FROM parts_master";
echo "Filtered query: " . apply_tenant_filter($test_query) . "\n\n";

echo "=== Results for tenant_id=14 ===\n";
$filtered = apply_tenant_filter($test_query);
$res = $connection->query($filtered);
$count = 0;
while ($row = $res->fetch_assoc()) {
    echo "- {$row['part_code']}: {$row['part_name']} (tenant_id={$row['tenant_id']})\n";
    $count++;
}
echo "Total: $count records\n";

if ($count === 0) {
    echo "\n✓ CORRECT: No data leakage - empty result for new company\n";
} else {
    echo "\n✗ ERROR: Data leakage detected!\n";
}