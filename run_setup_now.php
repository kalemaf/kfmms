<?php
// Output to file
$output_file = __DIR__ . '/setup_output.txt';
ob_start();

// Change to app directory
chdir(__DIR__);

// Include setup
include 'execute_equipment_setup.php';

// Get output
$content = ob_get_clean();

// Save to file
file_put_contents($output_file, $content);

// Also output to stdout
echo $content;

// Create a marker file to indicate completion
file_put_contents(__DIR__ . '/setup_complete.txt', 'Setup completed at ' . date('Y-m-d H:i:s'));

?>
