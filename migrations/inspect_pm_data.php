<?php
include_once __DIR__ . '/../config.inc.php';
$c = $connection;
if (!$c) die("No DB connection\n");
function table_exists($c,$t){$db=mysqli_real_escape_string($c,$GLOBALS['databaseName']);$t=mysqli_real_escape_string($c,$t);$r=mysqli_query($c,"SELECT COUNT(*) AS cnt FROM information_schema.tables WHERE table_schema='".$db."' AND table_name='".$t."'");$row=mysqli_fetch_assoc($r);return ((int)$row['cnt'])>0;}
$tables = ['pm_schedules','pm_instances','pm_masters','pm_schedule_log'];
foreach($tables as $t){
    echo "=== $t ===\n";
    if (!table_exists($c,$t)) { echo "(missing)\n\n"; continue; }
    $res = mysqli_query($c, "SELECT COUNT(*) AS cnt FROM $t");
    $cnt = ($res && ($r=mysqli_fetch_assoc($res))) ? $r['cnt'] : 0;
    echo "Count: $cnt\n";
    $sample = mysqli_query($c, "SELECT * FROM $t LIMIT 5");
    while($row = mysqli_fetch_assoc($sample)){
        print_r($row);
        echo "\n";
    }
    echo "\n";
}
?>