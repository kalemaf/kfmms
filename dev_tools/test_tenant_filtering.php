<?php
require_once 'config.inc.php';
require_once 'common.inc.php';

// Simulate login for company 1
$_SESSION['tenant_id'] = 1;

echo "Testing tenant filtering with tenant_id = 1\n\n";

// Test work_orders query
$query = "SELECT COUNT(*) as total FROM work_orders";
$filtered_query = apply_tenant_filter($query);
echo "Original: $query\n";
echo "Filtered: $filtered_query\n\n";

$result = safe_query_row("SELECT COUNT(*) as total FROM work_orders");
echo "Work orders count: " . ($result['total'] ?? 0) . "\n\n";

// Test equipment query
$result = safe_query_row("SELECT COUNT(*) as total FROM equipment");
echo "Equipment count: " . ($result['total'] ?? 0) . "\n\n";

// Simulate login for company 2 (should see empty tables)
$_SESSION['tenant_id'] = 2;

echo "Testing tenant filtering with tenant_id = 2\n\n";

$result = safe_query_row("SELECT COUNT(*) as total FROM work_orders");
echo "Work orders count for company 2: " . ($result['total'] ?? 0) . "\n\n";

$result = safe_query_row("SELECT COUNT(*) as total FROM equipment");
echo "Equipment count for company 2: " . ($result['total'] ?? 0) . "\n\n";

echo "Tenant filtering test complete!\n";
?>