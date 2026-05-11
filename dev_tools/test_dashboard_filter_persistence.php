<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Simulate a web request with session and GET filter values
session_start();
$_SESSION['user_id'] = 1;
$_SESSION['role'] = 'admin';
$_SESSION['tenant_id'] = 1;
$_GET = [
    'period' => 'weekly',
    'sort' => 'completion_sla_percentage'
];

try {
    ob_start();
    require_once 'technician_performance_dashboard.php';
    $output = ob_get_clean();

    $checks = [
        'This Week' => strpos($output, 'This Week') !== false,
        'Sort selected' => strpos($output, 'value="completion_sla_percentage"') !== false && strpos($output, 'selected') !== false,
        'Sort preserved on artisan row' => strpos($output, '&sort=completion_sla_percentage') !== false,
        'Dashboard headline' => strpos($output, 'Artisan Performance Dashboard') !== false
    ];

    echo "Dashboard filter persistence regression test:\n";
    foreach ($checks as $label => $passed) {
        echo sprintf("- %s: %s\n", $label, $passed ? 'PASS' : 'FAIL');
    }

    if (in_array(false, $checks, true)) {
        echo "\n=== DEBUG OUTPUT START ===\n";
        echo substr($output, 0, 2000);
        echo "\n=== DEBUG OUTPUT END ===\n";
        exit(1);
    }

    echo "\nAll regression checks passed.\n";
} catch (Throwable $e) {
    echo "FATAL ERROR: " . $e->getMessage() . "\n";
    echo "File: " . $e->getFile() . "\n";
    echo "Line: " . $e->getLine() . "\n";
    echo "Trace:\n" . $e->getTraceAsString() . "\n";
    exit(1);
}
