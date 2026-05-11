<?php
require 'config.inc.php';
$cols = $connection->query('PRAGMA table_info(user_creation_authorizations)')->fetchAll(PDO::FETCH_ASSOC);
foreach($cols as $col) {
    echo $col['name'] . ' (' . $col['type'] . ')' . PHP_EOL;
}
?>
