<?php
/**
 * Scheduler Verification Script
 * Checks if your system is ready for automated PM generation
 */

echo "========================================\n";
echo "Maintenix - Scheduler Setup Verification\n";
echo "========================================\n\n";

$checks_passed = 0;
$checks_total = 0;

// Check 1: PHP executable exists
$checks_total++;
echo "1. Checking PHP installation...\n";
$php_exe = 'C:\php\php.exe';
if (file_exists($php_exe)) {
    echo "   ✓ PHP found at: $php_exe\n\n";
    $checks_passed++;
} else {
    echo "   ✗ PHP not found at: $php_exe\n";
    echo "   (It may be installed elsewhere, check your PHP installation path)\n\n";
}

// Check 2: generate_pm.php exists
$checks_total++;
echo "2. Checking generate_pm.php exists...\n";
$gpm_file = dirname(__DIR__) . '/generate_pm.php';
if (file_exists($gpm_file)) {
    echo "   ✓ Found at: $gpm_file\n\n";
    $checks_passed++;
} else {
    echo "   ✗ Not found at: $gpm_file\n\n";
}

// Check 3: schedule_pm_generation.php exists
$checks_total++;
echo "3. Checking schedule_pm_generation.php exists...\n";
$sched_file = dirname(__DIR__) . '/schedule_pm_generation.php';
if (file_exists($sched_file)) {
    echo "   ✓ Found at: $sched_file\n\n";
    $checks_passed++;
} else {
    echo "   ✗ Not found at: $sched_file\n\n";
}

// Check 4: logs directory writable
$checks_total++;
echo "4. Checking logs directory is writable...\n";
$logs_dir = dirname(__DIR__) . '/logs';
if (!is_dir($logs_dir)) {
    mkdir($logs_dir, 0755, true);
}
if (is_writable($logs_dir)) {
    echo "   ✓ Logs directory is writable\n\n";
    $checks_passed++;
} else {
    echo "   ✗ Logs directory is NOT writable\n";
    echo "   (Fix: Right-click logs folder → Properties → Security)\n\n";
}

// Check 5: Test run generate_pm.php
$checks_total++;
echo "5. Test running generate_pm.php...\n";
ob_start();
try {
    $test_start = date('Y-m-d H:i:s');
    include dirname(__DIR__) . '/generate_pm.php';
    $output = ob_get_clean();
    
    if (strpos($output, 'PM generation') !== false) {
        echo "   ✓ generate_pm.php ran successfully\n";
        echo "   Output: " . trim(substr($output, 0, 100)) . "...\n\n";
        $checks_passed++;
    } else {
        ob_end_clean();
        echo "   ✗ generate_pm.php ran but produced unexpected output\n\n";
    }
} catch (Exception $e) {
    ob_end_clean();
    echo "   ✗ Error running generate_pm.php: " . $e->getMessage() . "\n\n";
}

// Check 6: Database connectivity
$checks_total++;
echo "6. Checking database connectivity...\n";
require_once dirname(__DIR__) . '/config.inc.php';
try {
    $result = $connection->query("SELECT 1");
    if ($result) {
        echo "   ✓ Database connected\n\n";
        $checks_passed++;
    }
} catch (Exception $e) {
    echo "   ✗ Database connection failed: " . $e->getMessage() . "\n\n";
}

// Check 7: Count active schedules
$checks_total++;
echo "7. Checking active PM schedules...\n";
try {
    $result = $connection->query("SELECT COUNT(*) as cnt FROM pm_schedules WHERE active=1");
    $row = $result->fetch_assoc();
    $count = $row['cnt'];
    echo "   ✓ Found $count active PM schedules\n\n";
    $checks_passed++;
} catch (Exception $e) {
    echo "   ✗ Error: " . $e->getMessage() . "\n\n";
}

// Summary
echo "========================================\n";
echo "VERIFICATION SUMMARY\n";
echo "========================================\n";
echo "Passed: $checks_passed / $checks_total\n\n";

if ($checks_passed === $checks_total) {
    echo "✓ All checks passed! Your system is ready for scheduler setup.\n";
    echo "\nNext steps:\n";
    echo "1. Read SCHEDULER_SETUP.txt for detailed instructions\n";
    echo "2. Set up Windows Task Scheduler to run schedule_pm_generation.php daily\n";
    echo "3. The task should run at 1:00 AM (or preferred time)\n";
} else {
    echo "⚠ Some checks failed. Please review the items marked with ✗\n";
    echo "  Fix these issues before setting up the scheduler.\n";
}

echo "\n========================================\n";
?>
