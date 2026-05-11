<?php
session_start();
$_SESSION['user_id'] = 1;
$_SESSION['role'] = 'admin';
$_SESSION['tenant_id'] = 1;

echo "Step 1: Loading config...\n";
require 'config.inc.php';
echo "Config OK\n";

echo "Step 2: About to load performanceService...\n";
require 'libraries/performanceService.php';
echo "performanceService loaded OK\n";

echo "Done!\n";
?>
