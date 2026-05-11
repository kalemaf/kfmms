<?php
// Test work_order_requests tenant filtering
include 'config.inc.php';
include 'common.inc.php';

echo "<h1>Testing work_order_requests Tenant Filtering</h1>";

// Test for new user saka (company 11)
$_SESSION['tenant_id'] = 11;
$_SESSION['user_id'] = 58; // saka

echo "<h2>Testing: saka (tenant_id = 11)</h2>";

$query = "SELECT r.request_id, r.descriptive_text, r.status FROM work_order_requests r ORDER BY r.submit_date DESC LIMIT 10";
$filtered = apply_tenant_filter($query);
echo "<p>Filtered query: $filtered</p>";

$rows = safe_query_all($query);
echo "<p>Requests visible: " . count($rows) . "</p>";

if (count($rows) == 0) {
    echo "<p style='color:green'><strong>✓ SUCCESS: No work order requests visible for new company!</strong></p>";
} else {
    echo "<p style='color:red'><strong>✗ FAILED: Requests visible!</strong></p>";
    print_r($rows);
}

// Test for company 1
$_SESSION['tenant_id'] = 1;
$_SESSION['user_id'] = 1;

echo "<h2>Testing: company 1 (tenant_id = 1)</h2>";
$rows = safe_query_all($query);
echo "<p>Requests visible: " . count($rows) . "</p>";
foreach ($rows as $r) {
    echo "- REQ #{$r['request_id']}: {$r['descriptive_text']} ({$r['status']})<br>";
}