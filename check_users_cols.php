<?php
require_once 'config.inc.php';

echo "=== Users Table Columns ===\n";
$result = $connection->query("PRAGMA table_info(users)");
while ($row = $result->fetch(PDO::FETCH_ASSOC)) {
    echo $row['name'] . "\n";
}