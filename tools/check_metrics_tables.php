<?php
require_once __DIR__ . '/../config.inc.php';
$tables = ['failures','repairs','Uptime_Record'];
foreach($tables as $t) {
    $r = mysqli_query($connection, "SHOW CREATE TABLE `$t`");
    if($r && mysqli_num_rows($r)) {
        $row = mysqli_fetch_assoc($r);
        echo "\n--- $t ---\n" . $row['Create Table'] . "\n";
        $count = mysqli_num_rows(mysqli_query($connection, "SELECT * FROM `$t`"));
        echo "rows: $count\n";
    } else {
        echo "$t not found\n";
    }
}
