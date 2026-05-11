<?php
/**
 * Equipment Spare Feature - Quick Runner
 * Executes setup in production database
 */

// Ensure we're in the right directory
chdir(__DIR__);

echo "Running Equipment Spare Production Setup...\n";
echo str_repeat('=', 70) . "\n\n";

// Include the setup script
ob_start();
include 'setup_equipment_spare_production.php';
$output = ob_get_clean();

echo $output;

echo "\n" . str_repeat('=', 70) . "\n";
echo "Setup execution completed.\n";

?>
