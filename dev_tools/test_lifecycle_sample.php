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

// Find Lifecycle Analytics in output
$pos = strpos($output, 'Lifecycle Analytics');
if ($pos !== false) {
    echo "=== HTML SAMPLE AROUND 'Lifecycle Analytics' ===" . PHP_EOL;
    echo substr($output, max(0, $pos - 200), 600) . PHP_EOL;
    echo "" . PHP_EOL;
}

// Find page-header
$pos = strpos($output, 'page-header');
if ($pos !== false) {
    echo "=== HTML SAMPLE AROUND 'page-header' ===" . PHP_EOL;
    echo substr($output, max(0, $pos - 100), 400) . PHP_EOL;
    echo "" . PHP_EOL;
}

// Count major elements
echo "=== ELEMENT COUNTS ===" . PHP_EOL;
echo 'Number of divs: ' . substr_count($output, '<div') . PHP_EOL;
echo 'Number of forms: ' . substr_count($output, '<form') . PHP_EOL;
echo 'Number of input elements: ' . substr_count($output, '<input') . PHP_EOL;
echo 'Number of table cells: ' . substr_count($output, '<td') . PHP_EOL;
?>
