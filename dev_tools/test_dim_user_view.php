<?php
// Simulate the dim user login and check what work orders they see

require_once 'config.inc.php';
require_once 'common.inc.php';

// Simulate logging in as user 'dim' (user_id = 79, company_id = 33)
$_SESSION['tenant_id'] = 33;
$_SESSION['user_id'] = 79;
$_SESSION['user'] = 'dim';

echo "=== SIMULATING LOGIN AS 'dim' USER (Tenant 33) ===\n\n";
echo "Session Tenant ID: " . $_SESSION['tenant_id'] . "\n";
echo "Session User: " . $_SESSION['user'] . "\n\n";

// Now check what dashboard shows
echo "=== WORK ORDERS VISIBLE TO dim USER ===\n";
echo str_repeat("-", 70) . "\n";

$query = "SELECT wo_id, descriptive_text, wo_status, priority FROM work_orders ORDER BY wo_id DESC LIMIT 10";
echo "Query before filtering: $query\n\n";

$filtered_query = apply_tenant_filter($query);
echo "Query after apply_tenant_filter():\n$filtered_query\n\n";

$result = safe_query_all($query);
echo "Result count: " . count($result) . "\n\n";

if (count($result) > 0) {
    echo "Work Orders Returned:\n";
    echo str_repeat("-", 70) . "\n";
    foreach ($result as $row) {
        echo sprintf("WO #%-2d | %-30s | Status: %-15s | Priority: %d\n", 
            $row['wo_id'], 
            substr($row['descriptive_text'], 0, 28), 
            $row['wo_status'],
            $row['priority']
        );
    }
} else {
    echo "NO WORK ORDERS FOUND - dim user sees an EMPTY dashboard!\n";
}

// Also check work_order_requests
echo "\n\n=== WORK ORDER REQUESTS VISIBLE TO dim USER ===\n";
echo str_repeat("-", 70) . "\n";

$req_query = "SELECT wor_id, request_name, status FROM work_order_requests ORDER BY wor_id DESC LIMIT 10";
$req_result = safe_query_all($req_query);
echo "Result count: " . count($req_result) . "\n\n";

if (count($req_result) > 0) {
    echo "Work Order Requests Returned:\n";
    echo str_repeat("-", 70) . "\n";
    foreach ($req_result as $row) {
        echo sprintf("WOR # | %-30s | Status: %s\n", 
            substr($row['request_name'] ?? 'N/A', 0, 28),
            $row['status']
        );
    }
} else {
    echo "NO WORK ORDER REQUESTS FOUND\n";
}

?>
