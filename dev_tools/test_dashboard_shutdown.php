<?php
error_reporting(E_ALL);
ini_set('display_errors', '1');

register_shutdown_function(function() {
    $err = error_get_last();
    if ($err) {
        echo "SHUTDOWN ERROR:\n";
        var_export($err);
    } else {
        echo "NO SHUTDOWN ERROR\n";
    }
});

session_start();
$_SESSION['user_id'] = 1;
$_SESSION['role'] = 'admin';
$_SESSION['tenant_id'] = 1;

require 'technician_performance_dashboard.php';
echo "DONE\n";
?>