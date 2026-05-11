<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();
$_SESSION['user_id'] = 1;
$_SESSION['role'] = 'admin';
$_SESSION['tenant_id'] = 1;

require_once 'config.inc.php';
require_once 'common.inc.php';
require_once 'libraries/performance_schema.php';
require_once 'libraries/performanceService.php';
require_once 'libraries/slaService.php';
require_once 'libraries/repeatFailureService.php';

echo "All includes loaded OK\n\n";

// Test each function
echo "Testing initialize_performance_monitoring_tables...\n";
initialize_performance_monitoring_tables($connection);
echo "OK\n\n";

echo "Testing get_team_performance_summary...\n";
$team_perf = get_team_performance_summary('overall_score');
echo "Got " . count($team_perf) . " records\n";
echo "OK\n\n";

echo "Testing get_performance_trend...\n";
$trend = get_performance_trend(1, 6);
echo "Got " . count($trend) . " records\n";
echo "OK\n\n";

echo "Testing get_technician_repeat_failures...\n";
$failures = get_technician_repeat_failures(1, date('Y-m-01'), date('Y-m-t'));
echo "Got " . count($failures) . " records\n";
echo "OK\n\n";

echo "Testing get_chronic_failure_assets...\n";
$chronic = get_chronic_failure_assets(3);
echo "Got " . count($chronic) . " records\n";
echo "OK\n\n";

echo "All functions working!\n";
?>
