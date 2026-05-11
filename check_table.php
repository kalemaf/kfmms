<?php
require_once 'config.inc.php';
if ($connection) {
    $result = $connection->query('SELECT name FROM sqlite_master WHERE type="table" AND name="wo_parts"');
    if ($result && $result->fetch(PDO::FETCH_ASSOC)) {
        echo "wo_parts table exists\n";
    } else {
        echo "wo_parts table does NOT exist\n";
    }
} else {
    echo "No database connection\n";
}

