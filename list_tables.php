<?php
require 'config.inc.php';

$tables = $connection->query("SELECT name FROM sqlite_master WHERE type='table' ORDER BY name")->fetchAll(PDO::FETCH_COLUMN);

foreach ($tables as $table) {
    $cols = $connection->query("PRAGMA table_info($table)")->fetchAll(PDO::FETCH_ASSOC);
    echo $table . " (" . count($cols) . " columns)\n";
}
?>
