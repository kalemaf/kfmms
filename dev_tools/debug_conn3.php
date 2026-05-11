<?php
require "config.inc.php";
require "common.inc.php";
echo "Checking connection object status:\n";
echo "Is connection object set? " . (isset($connection) ? "Yes" : "No") . "\n";
if (isset($connection)) {
    echo "Connection type: " . get_class($connection) . "\n";
    echo "Host: " . $connection->host . "\n";
    echo "Port: " . $connection->port . "\n";
    echo "User: " . $connection->user . "\n";
    echo "Error: " . $connection->error . "\n";
    echo "Errno: " . $connection->errno . "\n";
    echo "Server info: " . $connection->server_info . "\n";
    echo "Client info: " . $connection->client_info . "\n";
    echo "Status: " . $connection->stat() . "\n";
}
?>
