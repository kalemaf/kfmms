<?php
/**
 * Check Companies Table Structure
 */
require_once 'config.inc.php';

echo "<h1>Companies Table</h1>";

try {
    // Get table info
    $result = $connection->query("PRAGMA table_info(companies)");
    if ($result) {
        $columns = $result->fetchAll(PDO::FETCH_ASSOC);
        echo "<h2>Columns</h2><pre>";
        print_r($columns);
        echo "</pre>";
    }
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}

echo "<h1>Companies Data</h1>";
try {
    $result = $connection->query("SELECT * FROM companies");
    if ($result) {
        $companies = $result->fetchAll(PDO::FETCH_ASSOC);
        echo "<pre>";
        print_r($companies);
        echo "</pre>";
    }
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}