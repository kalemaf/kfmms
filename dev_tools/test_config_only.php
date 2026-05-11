<?php
echo "Starting...\n";
require 'config.inc.php';
echo "Config loaded\n";
echo "Connection type: " . (isset($connection) ? get_class($connection) : 'NULL') . "\n";
echo "Done\n";
?>
