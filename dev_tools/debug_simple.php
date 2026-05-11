<?php
// Minimal debug - no includes
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "Starting debug...<br>";

try {
    // Direct include
    include 'config.inc.php';
    echo "config.inc.php included<br>";
    
    // Check if $connection exists
    if (isset($connection)) {
        echo "Connection exists<br>";
        
        // Simple query
        $result = $connection->query("SELECT 1 as test");
        $row = $result->fetch(PDO::FETCH_ASSOC);
        echo "Query works: ";
        print_r($row);
    } else {
        echo "No connection object<br>";
    }
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "<br>";
    echo "Trace: " . $e->getTraceAsString();
}