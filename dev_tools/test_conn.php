<?php
require "config.inc.php";
require "common.inc.php";
echo "Connection test:\n";
$res = $connection->query("SELECT id, company_name FROM companies LIMIT 1");
if($res) {
    echo "Query succeeded.\n";
    $row = $res->fetch_assoc();
    var_dump($row);
} else {
    echo "Query error: " . $connection->error . "\n";
}
?>
