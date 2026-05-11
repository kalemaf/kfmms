<?php
session_start();
$_SESSION['tenant_id'] = 1;

echo "Starting...\n";

try {
    echo "Loading config...\n";
    require 'config.inc.php';
    echo "Config OK\n";
    echo "Connection type: " . get_class($connection) . "\n";
    
    // Test connection
    echo "Testing connection.query()...\n";
    $result = $connection->query("SELECT 1 as test");
    echo "Query OK: " . ($result ? "true" : "false") . "\n";
    
    echo "Loading schema...\n";
    require 'libraries/performance_schema.php';
    echo "Schema OK\n";
    
} catch (Exception $e) {
    echo "Exception caught: " . $e->getMessage() . "\n";
    echo "File: " . $e->getFile() . "\n";
    echo "Line: " . $e->getLine() . "\n";
} catch (Throwable $e) {
    echo "Error caught: " . $e->getMessage() . "\n";
    echo "File: " . $e->getFile() . "\n";
    echo "Line: " . $e->getLine() . "\n";
}

echo "Done\n";
?>
