<?php
require_once('config.inc.php');
require_once('common.inc.php');

$result = mysqli_query($connection, 'SHOW TABLES');
if ($result) {
    echo 'Current tables in database:' . "\n";
    while ($row = mysqli_fetch_array($result)) {
        echo '- ' . $row[0] . "\n";
    }
} else {
    echo 'Error: ' . mysqli_error($connection) . "\n";
}
?>