<?php
echo "Step 1: Require config\n";
require "config.inc.php";
echo "Step 2: Require common\n";
require "common.inc.php";
echo "Step 3: Check connection\n";
if ($connection) {
    echo "Connection object exists\n";
    echo "Step 4: Execute query\n";
    $res = $connection->query("SELECT id, company_name FROM companies LIMIT 1");
    if ($res) {
        echo "Query succeeded\n";
    } else {
        echo "Query failed: " . $connection->error . "\n";
        exit(1);
    }
} else {
    echo "No connection object\n";
    exit(1);
}
?>
