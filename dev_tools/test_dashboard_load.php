<?php
error_reporting(E_ALL);
ini_set('display_errors', '1');

ob_start();

require_once 'config.inc.php';
require_once 'common.inc.php';

echo "Config loaded\n";

if (file_exists('libraries/metrics.php')) {
    require_once 'libraries/metrics.php';
    echo "Metrics loaded\n";
}

// Load predictive maintenance module
$predictive_loaded = false;
if (file_exists('libraries/predictive_maintenance.php')) {
    try {
        require_once 'libraries/predictive_maintenance.php';
        $predictive_loaded = function_exists('get_asset_health_overview');
        echo "Predictive loaded: " . ($predictive_loaded ? 'YES' : 'NO') . "\n";
    } catch (Exception $e) {
        echo "Predictive error: " . $e->getMessage() . "\n";
        $predictive_loaded = false;
    }
}

echo "Done\n";

ob_end_clean();
?>
