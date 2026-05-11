<?php
error_reporting(E_ALL);
ini_set('display_errors', '0');

require 'config.inc.php';

global $connection;

echo "📊 Database Tables in maintenix.db\n";
echo "===================================\n\n";

$result = $connection->query("SELECT name FROM sqlite_master WHERE type='table' ORDER BY name");

$tables = array();
while ($row = $result->fetch(PDO::FETCH_ASSOC)) {
    $tables[] = $row['name'];
}

echo "Total: " . count($tables) . " tables\n\n";

$i = 1;
foreach ($tables as $table) {
    try {
        $count_result = $connection->query("SELECT COUNT(*) as cnt FROM " . $table);
        $count_row = $count_result->fetch(PDO::FETCH_ASSOC);
        $count = $count_row['cnt'];
        echo sprintf("%2d. %-35s (%5d rows)\n", $i, $table, $count);
        $i++;
    } catch (Exception $e) {
        echo sprintf("%2d. %-35s (ERROR)\n", $i, $table);
        $i++;
    }
}
?>
