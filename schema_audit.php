<?php
require 'config.inc.php';

echo "=== CURRENT DATABASE SCHEMA ===\n\n";

$tables = $connection->query("SELECT name FROM sqlite_master WHERE type='table' ORDER BY name")->fetchAll(PDO::FETCH_COLUMN);

foreach ($tables as $table) {
    $cols = $connection->query("PRAGMA table_info($table)")->fetchAll(PDO::FETCH_ASSOC);
    echo "$table:\n";
    foreach ($cols as $col) {
        echo "  - {$col['name']} ({$col['type']})\n";
    }
    echo "\n";
}
?>
