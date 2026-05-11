<?php
include_once __DIR__ . '/../config.inc.php';
$c = $connection;
if (!$c) die("No DB connection\n");
$res = mysqli_query($c, "SELECT * FROM `groups` LIMIT 100");
if (!$res) { echo "Query failed: " . mysqli_error($c) . "\n"; exit(1); }
while($r = mysqli_fetch_assoc($res)) {
    print_r($r);
    echo "\n";
}
?>