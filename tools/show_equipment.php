<?php
require_once __DIR__ . '/../config.inc.php';
$res = $connection->query("SHOW CREATE TABLE equipment");
if(!$res) { die($connection->error); }
$row = $res->fetch_assoc();
echo $row['Create Table'];
