<?php
// Final verification of PM tenant isolation
include 'config.inc.php';
include 'common.inc.php';

echo "<h1>PM Tenant Isolation Final Verification</h1>";

// Test for new company (tenant 11)
$_SESSION['tenant_id'] = 11;
$_SESSION['user_id'] = 58;

echo "<h2>Testing: New Company (tenant_id = 11)</h2>";

$tests = [
    'pm_masters' => "SELECT * FROM pm_masters ORDER BY next_due_date DESC LIMIT 10",
    'pm_schedules' => "SELECT * FROM pm_schedules ORDER BY next_due_date DESC LIMIT 10",
    'work_orders' => "SELECT * FROM work_orders ORDER BY submit_date DESC LIMIT 10"
];

$all_passed = true;
foreach ($tests as $name => $query) {
    $rows = safe_query_all($query);
    $count = count($rows);
    $status = ($count == 0) ? "✓ PASS" : "✗ FAIL";
    echo "<p><strong>$name:</strong> $status ($count records)</p>";
    if ($count > 0) $all_passed = false;
}

// Test for company 1
$_SESSION['tenant_id'] = 1;
$_SESSION['user_id'] = 1;

echo "<h2>Testing: Company 1 (tenant_id = 1)</h2>";

$pm_masters = safe_query_all("SELECT pm_id, pm_title, asset_name FROM pm_masters ORDER BY next_due_date DESC LIMIT 10");
echo "<p><strong>pm_masters:</strong> " . count($pm_masters) . " records</p>";
foreach ($pm_masters as $pm) {
    echo "- {$pm['pm_title']}: {$pm['asset_name']}<br>";
}

$work_orders = safe_query_all("SELECT wo_id, descriptive_text FROM work_orders ORDER BY submit_date DESC LIMIT 5");
echo "<p><strong>work_orders:</strong> " . count($work_orders) . " records</p>";

echo "<h1>" . ($all_passed ? "✓ ALL TESTS PASSED" : "✗ SOME TESTS FAILED") . "</h1>";

if ($all_passed) {
    echo "<p>New company users see <strong>empty PM tables</strong> - no data inheritance!</p>";
}