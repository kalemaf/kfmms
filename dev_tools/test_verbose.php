<?php
ini_set('display_errors', '1');
error_reporting(E_ALL);

session_start();
$_SESSION['tenant_id'] = 1;

echo "Loading config...\n";
require 'config.inc.php';
echo "Config loaded\n";

echo "About to require schema...\n";
@require 'libraries/performance_schema.php';
echo "Schema required\n";

echo "Done\n";
?>
