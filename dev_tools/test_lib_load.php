<?php
error_reporting(E_ALL);
ini_set('display_errors', '1');

// Suppress config.inc.php session warnings from CLI
ob_start();

echo "Loading config...\n";
require_once 'config.inc.php';
echo "Config loaded. Connection: " . (isset($connection) ? 'YES' : 'NO') . "\n";

echo "Loading common...\n";
require_once 'common.inc.php';
echo "Common loaded. Table_exists function: " . (function_exists('table_exists') ? 'YES' : 'NO') . "\n";

echo "Loading predictive lib...\n";

// Use @ to suppress errors and try to load
set_error_handler(function($errno, $errstr, $errfile, $errline) {
    echo "ERROR [{$errno}]: {$errstr} in {$errfile} on line {$errline}\n";
    return true;
});

// Check each function from the lib
echo "Checking if library file exists: " . (file_exists('libraries/predictive_maintenance.php') ? 'YES' : 'NO') . "\n";

// Try to compile the file
echo "Attempting to compile library file...\n";
$code = file_get_contents('libraries/predictive_maintenance.php');
if ($code === false) {
    echo "ERROR: Cannot read library file\n";
    exit(1);
}
echo "Library file read: " . strlen($code) . " bytes\n";

// Now require it
require_once 'libraries/predictive_maintenance.php';
echo "Predictive lib loaded.\n";

echo "Checking for create_predictive_maintenance_tables function...\n";
if (function_exists('create_predictive_maintenance_tables')) {
    echo "Function exists!\n";
} else {
    echo "Function does NOT exist!\n";
}

echo "\nSuccess!\n";
ob_end_clean();
?>
