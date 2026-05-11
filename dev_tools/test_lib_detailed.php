<?php
error_reporting(E_ALL);
ini_set('display_errors', '1');

// Load min requirements
require_once 'config.inc.php';
require_once 'common.inc.php';

// Extract first 50 lines from predictive lib
$lib_code = file_get_contents('libraries/predictive_maintenance.php');

// Just try to eval a subset
$lines = explode("\n", $lib_code);

// Find where functions start
for ($i = 0; $i < count($lines); $i++) {
    if (strpos($lines[$i], 'function ') !== false) {
        echo "First function at line " . ($i + 1) . ": " . trim($lines[$i]) . "\n";
        break;
    }
}

// Count total lines
echo "Total lines: " . count($lines) . "\n";

// Now try to load it normally
echo "\nLoading library...\n";
require_once 'libraries/predictive_maintenance.php';
echo "Success!\n";
?>
