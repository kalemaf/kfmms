<?php
session_start();
$_SESSION['tenant_id'] = 1;

echo "Starting...\n";

try {
    echo "Loading config...\n";
    require 'config.inc.php';
    echo "Config OK\n";
    
    echo "Loading schema...\n";
    require 'libraries/performance_schema.php';
    echo "Schema OK\n";
    
    echo "Loading service...\n";
    require 'libraries/performanceService.php';
    echo "Service OK\n";
    
    echo "All loaded successfully\n";
} catch (Exception $e) {
    echo "Exception caught: " . $e->getMessage() . "\n";
} catch (Throwable $e) {
    echo "Error caught: " . $e->getMessage() . "\n";
}

echo "Done\n";
?>
