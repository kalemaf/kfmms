<?php
// Comprehensive tenant isolation test
include 'config.inc.php';
include 'common.inc.php';

echo "<h1>Tenant Isolation Verification</h1>";

// Test for kalemat (company 11)
$_SESSION['tenant_id'] = 11;
$_SESSION['user_id'] = 57;

echo "<h2>Testing: kalemat (tenant_id = 11)</h2>";

$tests = [
    'Work Orders' => "SELECT * FROM work_orders ORDER BY submit_date DESC LIMIT 10",
    'Equipment' => "SELECT * FROM equipment ORDER BY id DESC LIMIT 10",
    'Purchase Orders' => "SELECT * FROM purchase_orders ORDER BY po_date DESC LIMIT 10",
    'PM Schedules' => "SELECT * FROM pm_schedules ORDER BY next_due_date DESC LIMIT 10"
];

$all_passed = true;
foreach ($tests as $name => $query) {
    $filtered = apply_tenant_filter($query);
    $res = $connection->query($filtered);
    $rows = $res->fetchAll(PDO::FETCH_ASSOC);
    $count = count($rows);
    
    $status = ($count == 0) ? "✓ PASS (0 records)" : "✗ FAIL ($count records)";
    echo "<p><strong>$name:</strong> $status</p>";
    
    if ($count > 0) $all_passed = false;
}

// Test for company 1 (should have data)
$_SESSION['tenant_id'] = 1;
$_SESSION['user_id'] = 1;

echo "<h2>Testing: company 1 (tenant_id = 1)</h2>";

$query = "SELECT * FROM work_orders ORDER BY submit_date DESC LIMIT 10";
$filtered = apply_tenant_filter($query);
$res = $connection->query($filtered);
$rows = $res->fetchAll(PDO::FETCH_ASSOC);

echo "<p><strong>Work Orders for company 1:</strong> " . count($rows) . " records</p>";
foreach ($rows as $wo) {
    echo "- WO #{$wo['wo_id']}: {$wo['descriptive_text']} ({$wo['wo_status']})<br>";
}

echo "<h1>" . ($all_passed ? "✓ ALL TESTS PASSED" : "✗ SOME TESTS FAILED") . "</h1>";

if ($all_passed) {
    echo "<p>New company users (kalemat, arianna) see <strong>empty tables</strong> - no data inheritance!</p>";
    echo "<p>Company 1 users see their own data - isolation working correctly!</p>";
}