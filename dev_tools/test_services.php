<?php
session_start();
$_SESSION['user_id'] = 1;
$_SESSION['role'] = 'admin';
$_SESSION['tenant_id'] = 1;

echo "Loading config...\n";
require 'config.inc.php';
echo "Config OK\n";

echo "Loading services...\n";
require 'libraries/performanceService.php';
require 'libraries/slaService.php';
require 'libraries/repeatFailureService.php';
echo "Services loaded\n";

echo "Getting team performance...\n";
$team = get_team_performance_summary('overall_score');
echo "Team performance: " . count($team) . " records\n";

echo "Getting chronic assets...\n";
$chronic = get_chronic_failure_assets(3);
echo "Chronic assets: " . count($chronic) . " records\n";

echo "All OK!\n";
?>
