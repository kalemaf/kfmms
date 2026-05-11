<?php
session_start();
$_SESSION['user_id'] = 1;
$_SESSION['role'] = 'admin';
$_SESSION['tenant_id'] = 1;

echo "Step 1: Loading config...\n";
require 'config.inc.php';
echo "Config OK\n";

echo "Step 2: Loading performanceService...\n";
require 'libraries/performanceService.php';
echo "performanceService OK\n";

echo "Step 3: Loading slaService...\n";
require 'libraries/slaService.php';
echo "slaService OK\n";

echo "Step 4: Loading repeatFailureService...\n";
require 'libraries/repeatFailureService.php';
echo "repeatFailureService OK\n";

echo "All services loaded!\n";
?>
