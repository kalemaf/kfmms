<?php
// Run dashboard.php CLI-style to capture errors
ini_set('display_errors', '1');
error_reporting(E_ALL);

ob_start();

// Simulate the web server accessing the page
$_SERVER['REQUEST_METHOD'] = 'GET';
$_SERVER['REQUEST_URI'] = '/dashboard.php';
$_SERVER['SCRIPT_NAME'] = '/dashboard.php';
$_SERVER['SERVER_NAME'] = 'localhost';
$_SERVER['SERVER_PORT'] = '8000';
$_SERVER['PHP_SELF'] = '/dashboard.php';

try {
    include 'dashboard.php';
    echo "\n\n=== PAGE RENDERED SUCCESSFULLY ===\n";
} catch (Exception $e) {
    echo "\n\n=== EXCEPTION CAUGHT ===\n";
    echo "Error: " . $e->getMessage() . "\n";
    echo "File: " . $e->getFile() . "\n";
    echo "Line: " . $e->getLine() . "\n";
    echo "\nStack Trace:\n";
    echo $e->getTraceAsString() . "\n";
} catch (Throwable $t) {
    echo "\n\n=== THROWABLE CAUGHT ===\n";
    echo "Error: " . $t->getMessage() . "\n";
    echo "File: " . $t->getFile() . "\n";
    echo "Line: " . $t->getLine() . "\n";
    echo "\nStack Trace:\n";
    echo $t->getTraceAsString() . "\n";
}

$output = ob_get_clean();
echo "Output length: " . strlen($output) . " bytes\n";
if (strlen($output) > 0) {
    echo "First 500 chars:\n";
    echo substr($output, 0, 500) . "\n";
}

?>
