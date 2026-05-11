<?php
require_once __DIR__ . '/../config.inc.php';
$sql = file_get_contents(__DIR__ . '/../schema_mttr_mtbf.sql');
if(!$sql) die('could not read schema file');
if (mysqli_multi_query($connection, $sql)) {
    // flush results
    do {
        if ($res = mysqli_store_result($connection)) {
            mysqli_free_result($connection);
        }
    } while (mysqli_next_result($connection));
    echo "Schema loaded\n";
} else {
    die("Schema load failed: " . mysqli_error($connection));
}