<?php
session_start();
$_SESSION['tenant_id'] = 1;

echo "Step 1: Loading config\n";
require 'config.inc.php';
echo "Config OK\n";

echo "Step 2: Loading schema\n";
require 'libraries/performance_schema.php';
echo "Schema OK\n";

echo "Step 3: Initializing tables\n";
initialize_performance_monitoring_tables($connection);
echo "Tables initialized OK\n";

echo "Step 4: Loading performance service\n";
require 'libraries/performanceService.php';
echo "Performance service OK\n";

echo "Step 5: Loading SLA service\n";
require 'libraries/slaService.php';
echo "SLA service OK\n";

echo "Step 6: Loading repeat failure service\n";
require 'libraries/repeatFailureService.php';
echo "Repeat failure service OK\n";

echo "All components loaded successfully!\n";
?>
