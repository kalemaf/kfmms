<?php
// Replicate exact dashboard.php initialization
session_start();

// Make sure session values exist
if (!isset($_SESSION['tenant_id'])) {
    $_SESSION['tenant_id'] = 1;
}

require_once 'config.inc.php';
require_once 'common.inc.php';
if (file_exists(__DIR__ . '/libraries/metrics.php')) {
    require_once __DIR__ . '/libraries/metrics.php';
}

echo "About to load predictive\n";

// Load predictive maintenance module
$predictive_loaded = false;
if (file_exists(__DIR__ . '/libraries/predictive_maintenance.php')) {
    echo "File exists\n";
    require_once __DIR__ . '/libraries/predictive_maintenance.php';
    echo "File loaded\n";
    $predictive_loaded = function_exists('get_asset_health_overview');
    echo "Function check done: " . ($predictive_loaded ? 'YES' : 'NO') . "\n";
}

echo "Done\n";

?> 