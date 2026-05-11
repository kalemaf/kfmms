<?php
include(__DIR__ . '/../config.inc.php');
$r=mysqli_query($connection,'SELECT * FROM work_orders ORDER BY wo_id DESC LIMIT 1');
$wo=mysqli_fetch_assoc($r);
echo "WORK_ORDER:\n";
print_r($wo);
$a=mysqli_query($connection,'SELECT * FROM work_order_attachments ORDER BY id DESC LIMIT 5');
echo "\nATTACHMENTS:\n";
while($row=mysqli_fetch_assoc($a)) { print_r($row); }
?>