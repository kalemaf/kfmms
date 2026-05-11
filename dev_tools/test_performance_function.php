<?php
/**
 * Test get_team_performance_summary function
 */
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Session setup before config
$_SESSION['user_id'] = 1;
$_SESSION['role'] = 'admin';
$_SESSION['tenant_id'] = 1;

require_once 'config.inc.php';
require_once 'libraries/performance_schema.php';
require_once 'libraries/performanceService.php';

file_put_contents(__DIR__ . '/test_performance_debug.log', "Config loaded\n", FILE_APPEND);
file_put_contents(__DIR__ . '/test_performance_debug.log', "About to initialize tables\n", FILE_APPEND);

initialize_performance_monitoring_tables($connection);

file_put_contents(__DIR__ . '/test_performance_debug.log', "Tables initialized\n", FILE_APPEND);
file_put_contents(__DIR__ . '/test_performance_debug.log', "About to call get_team_performance_summary\n", FILE_APPEND);

$result = get_team_performance_summary('overall_score');

file_put_contents(__DIR__ . '/test_performance_debug.log', "Result: " . json_encode($result) . "\n", FILE_APPEND);
file_put_contents(__DIR__ . '/test_performance_debug.log', "Test complete\n", FILE_APPEND);

echo "Done\n";
?>
