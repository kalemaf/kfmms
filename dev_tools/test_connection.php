<?php
require_once('config.inc.php');
if ($connection === null) {
    echo 'ERROR: No database connection: ' . $db_error . "\n";
    exit(1);
} else {
    echo "Connection successful\n";
    $result = $connection->query('SELECT name FROM sqlite_master WHERE type="table" LIMIT 10');
    echo "Tables found:\n";
    while ($row = $result->fetchArray()) {
        echo '  - ' . $row['name'] . "\n";
    }
}
?>
