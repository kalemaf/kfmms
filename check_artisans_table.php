<?php
require "config.inc.php";
$result = $pdo->query("SELECT name FROM sqlite_master WHERE type='table' AND name='artisans'");
echo $result->fetchColumn() ? "Table exists" : "Table does not exist";
?>