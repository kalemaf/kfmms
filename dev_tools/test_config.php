<?php
session_start();
$_SESSION['tenant_id'] = 1;
echo "Session set\n";

require 'config.inc.php';
echo "Config OK\n";
?>
