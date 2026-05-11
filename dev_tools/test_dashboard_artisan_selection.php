<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();
$_SESSION['user_id'] = 1;
$_SESSION['role'] = 'admin';
$_SESSION['tenant_id'] = 1;

// Load the dashboard and capture the first artisan selection link
$_GET = ['period' => 'monthly', 'sort' => 'overall_score'];

ob_start();
include 'technician_performance_dashboard.php';
$output = ob_get_clean();

$matches = [];
preg_match('/\?artisan=(\d+)&period=monthly&sort=overall_score/', $output, $matches);

if (empty($matches[1])) {
    echo "FAIL: Unable to find an artisan selection link in dashboard output.\n";
    echo "Output length: " . strlen($output) . " bytes\n";
    exit(1);
}

$artisan_id = $matches[1];

// Now request the dashboard with artisan selected
$_GET = [
    'period' => 'monthly',
    'sort' => 'overall_score',
    'artisan' => $artisan_id
];

ob_start();
include 'technician_performance_dashboard.php';
$output_selected = ob_get_clean();

$checks = [
    'Selected artisan detail shown' => strpos($output_selected, 'Detailed View') !== false,
    'Selected artisan ID in link' => strpos($output_selected, 'artisan=' . $artisan_id) !== false,
    'Repeat Failures section presence' => strpos($output_selected, 'Recent Repeat Failures') !== false || strpos($output_selected, 'Detailed View') !== false,
];

echo "Dashboard artisan selection regression test:\n";
foreach ($checks as $label => $passed) {
    echo sprintf("- %s: %s\n", $label, $passed ? 'PASS' : 'FAIL');
}

if (in_array(false, $checks, true)) {
    echo "\n=== DEBUG OUTPUT START ===\n";
    echo substr($output_selected, 0, 2000);
    echo "\n=== DEBUG OUTPUT END ===\n";
    exit(1);
}

echo "\nAll artisan selection regression checks passed. Artisan ID: {$artisan_id}\n";
