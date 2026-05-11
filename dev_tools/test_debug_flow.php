<?php
error_reporting(E_ALL);
ini_set('display_errors', '1');

echo "Script started\n";

session_start();
echo "Session started\n";

$_SESSION['user'] = 'admin@example.com';
$_SESSION['tenant_id'] = 1;
$_SESSION['company_id'] = 1;
$_SESSION['role'] = 'admin';
$_GET['nav'] = 'lifecycle';

echo "Session set\n";

// Suppress output from readfile and headers
ob_start();

// Mock some globals that index.php might use
$GLOBALS['_headers'] = [];

echo "Before config.inc.php\n";

require_once 'c:/free-cmms 0.04/config.inc.php';

echo "After config.inc.php\n";

require_once 'c:/free-cmms 0.04/common.inc.php';

echo "After common.inc.php\n";

// Simulate the switch statement from index.php
switch ($_GET['nav'] ?? 'dashboard') {
    case 'lifecycle':
        echo "Loading lifecycle_analytics_impl.php\n";
        require_once 'c:/free-cmms 0.04/lifecycle_analytics_impl.php';
        break;
    default:
        echo 'Unknown nav';
        break;
}

echo "After switch\n";

$output = ob_get_clean();

echo 'Output length: ' . strlen($output) . ' bytes' . PHP_EOL;
echo 'Starts with HTML: ' . (strpos($output, '<') === 0 || strpos($output, ' ') === 0 ? 'YES' : 'NO') . PHP_EOL;
echo 'Contains page-header: ' . (strpos($output, 'page-header') !== false ? 'YES' : 'NO') . PHP_EOL;
echo 'Contains Lifecycle Analytics title: ' . (strpos($output, 'Lifecycle Analytics') !== false ? 'YES' : 'NO') . PHP_EOL;
echo 'Output appears valid: ' . (strlen($output) > 10000 ? 'YES' : 'NO') . PHP_EOL;
?>
