<?php
session_start();
$_SESSION['tenant_id'] = 1;
echo "Session set\n";

require 'config.inc.php';
echo "Config loaded\n";

require 'libraries/performance_schema.php';
echo "Performance schema loaded\n";

require 'libraries/performanceService.php';
echo "Performance service loaded\n";

echo "All OK\n";
?>
