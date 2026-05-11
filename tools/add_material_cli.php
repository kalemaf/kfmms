<?php
$root = dirname(__DIR__);
include($root . '/config.inc.php');
$wo = $argv[1] ?? 0;
if (!$wo) { echo "Usage: php add_material_cli.php <wo_id>\n"; exit(1); }
$item = 'Test Part';
$qty = 2;
$unit = 'ea';
$unit_cost = 12.5;
$total = $qty * $unit_cost;
$sql = "INSERT INTO work_order_materials (wo_id, item_name, quantity, unit, unit_cost, total_cost) VALUES (".(int)$wo.", '".mysqli_real_escape_string($connection,$item)."', ".(float)$qty.", '".mysqli_real_escape_string($connection,$unit)."', ".(float)$unit_cost.", ".(float)$total.")";
$res = mysqli_query($connection, $sql);
if (!$res) { echo "Insert failed: " . mysqli_error($connection) . "\n"; exit(1); }
echo "Inserted material id=".mysqli_insert_id($connection)." for wo=$wo\n";
?>