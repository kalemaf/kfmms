<?php
session_start();
$_SESSION['tenant_id'] = 1;
echo "Session set\n";

require 'config.inc.php';
echo "Config loaded\n";

@include 'libraries/performance_schema.php';
echo "Performance schema loaded (or error suppressed)\n";

require 'libraries/performanceService.php';
echo "Performance service loaded\n";

echo "All OK\n";
?>
