<?php
error_reporting(E_ALL);
ini_set('display_errors', '1');

session_start();
$_SESSION['user'] = 'admin@example.com';
$_SESSION['tenant_id'] = 1;
$_SESSION['company_id'] = 1;
$_SESSION['role'] = 'admin';
$_GET['nav'] = 'lifecycle';

// Suppress output from readfile and headers
ob_start();

// Mock some globals that index.php might use
$GLOBALS['_headers'] = [];

require_once 'c:/free-cmms 0.04/config.inc.php';
require_once 'c:/free-cmms 0.04/common.inc.php';

// Simulate the switch statement from index.php
switch ($_GET['nav'] ?? 'dashboard') {
    case 'lifecycle':
        require_once 'c:/free-cmms 0.04/lifecycle_analytics_impl.php';
        break;
    default:
        echo 'Unknown nav';
        break;
}

$output = ob_get_clean();

echo '=== LIFECYCLE ANALYTICS TEST RESULTS ===' . PHP_EOL;
echo 'Output length: ' . strlen($output) . ' bytes' . PHP_EOL;
echo 'Output starts with: ' . json_encode(substr($output, 0, 100)) . PHP_EOL;
echo 'Contains page-header: ' . (strpos($output, 'page-header') !== false ? 'YES' : 'NO') . PHP_EOL;
echo 'Contains Lifecycle Analytics title: ' . (strpos($output, 'Lifecycle Analytics') !== false ? 'YES' : 'NO') . PHP_EOL;
echo 'Contains form elements: ' . (strpos($output, '<form') !== false ? 'YES' : 'NO') . PHP_EOL;
echo 'Contains div elements: ' . (strpos($output, '<div') !== false ? 'YES' : 'NO') . PHP_EOL;
echo 'Output appears valid: ' . (strlen($output) > 10000 ? 'YES' : 'NO') . PHP_EOL;
echo '' . PHP_EOL;
echo 'Key indicators found in output:' . PHP_EOL;
echo '  - page-header: ' . (strpos($output, 'page-header') ? 'YES' : 'NO') . PHP_EOL;
echo '  - Lifecycle Analytics: ' . (strpos($output, 'Lifecycle Analytics') ? 'YES' : 'NO') . PHP_EOL;
echo '  - form elements: ' . (strpos($output, '<form') ? 'YES' : 'NO') . PHP_EOL;
?>
