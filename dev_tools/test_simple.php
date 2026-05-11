<?php
echo "START\n";
flush();

try {
    require_once 'config.inc.php';
    echo "After config\n";
    flush();
    
    require_once 'common.inc.php';
    echo "After common\n";
    flush();
    
    if (file_exists('libraries/metrics.php')) {
        require_once 'libraries/metrics.php';
        echo "After metrics\n";
        flush();
    }
    
    echo "About to load predictive\n";
    flush();
    
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
    
    echo "END\n";
    flush();
} catch (Exception $e) {
    echo "EXCEPTION: " . $e->getMessage() . "\n";
    echo "FILE: " . $e->getFile() . "\n";
    echo "LINE: " . $e->getLine() . "\n";
    flush();
}

?>
