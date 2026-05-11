<?php
echo "Starting...\n";
require 'config.inc.php';
echo "Config loaded\n";
echo "Connection type: " . (isset($connection) ? get_class($connection) : 'NULL') . "\n";

echo "About to include schema...\n";
require 'libraries/performance_schema.php';
echo "Schema included\n";

echo "Done\n";
?>
