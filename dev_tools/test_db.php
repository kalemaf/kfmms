<?php
require 'config.inc.php';
require 'common.inc.php';
echo "Connection test:\n";
$result = $connection->query('SELECT id, company_name FROM companies LIMIT 1');
if($result) {
    echo "Query succeeded.\n";
    $row = $result->fetch_assoc();
    var_dump($row);
} else {
    echo "Query error: " . $connection->error . "\n";
}
?>
