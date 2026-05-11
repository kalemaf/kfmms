<?php
error_reporting(E_ALL);
ini_set('display_errors', '1');

// Start session exactly like dashboard.php
session_start();

// Set required session values for tenant context
if (empty($_SESSION['tenant_id'])) {
    $_SESSION['tenant_id'] = 1;
}
if (empty($_SESSION['user_id'])) {
    $_SESSION['user_id'] = 1;
}
if (empty($_SESSION['username'])) {
    $_SESSION['username'] = 'admin';
}

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

echo "All loaded successfully\n";

?>
