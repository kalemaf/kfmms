<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();
$_SESSION['user_id'] = 1;
$_SESSION['role'] = 'admin';
$_SESSION['tenant_id'] = 1;

try {
    echo "Loading config...\n";
    require_once 'config.inc.php';
    echo "Config loaded OK\n";
    
    echo "Loading performance schema...\n";
    require_once 'libraries/performance_schema.php';
    echo "Performance schema loaded OK\n";
    
    echo "Initializing tables...\n";
    initialize_performance_monitoring_tables($connection);
    echo "Tables initialized OK\n";
    
    echo "Loading performance service...\n";
    require_once 'libraries/performanceService.php';
    echo "Performance service loaded OK\n";
    
    echo "Getting team performance...\n";
    $team_perf = get_team_performance_summary('overall_score');
    echo "Got team performance: " . count($team_perf) . " records\n";
    var_dump($team_perf);
    
} catch (Throwable $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
    echo "File: " . $e->getFile() . "\n";
    echo "Line: " . $e->getLine() . "\n";
    echo "Trace:\n" . $e->getTraceAsString();
}
?>
