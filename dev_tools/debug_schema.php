<?php
require "config.inc.php";
require "common.inc.php";
echo "Checking SQLite database schema:\n";
try {
    echo "Tables in database:\n";
    $stmt = $connection->query("SELECT name FROM sqlite_master WHERE type='table'");
    $tables = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($tables as $table) {
        echo "  - " . $table["name"] . "\n";
    }
    
    echo "\nCompanies table schema:\n";
    $stmt = $connection->query("PRAGMA table_info(companies)");
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($columns as $col) {
        echo "  - " . $col["name"] . " (" . $col["type"] . ")\n";
    }
    
    echo "\nSample data from companies:\n";
    $stmt = $connection->query("SELECT * FROM companies LIMIT 3");
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    var_dump($rows);
} catch (Exception $e) {
    echo "Exception: " . $e->getMessage() . "\n";
}
?>
