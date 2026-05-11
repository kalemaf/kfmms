<?php
session_start();
$_SESSION['user_id'] = 1;
$_SESSION['role'] = 'admin';
$_SESSION['tenant_id'] = 1;

error_reporting(E_ALL);
ini_set('display_errors', 1);

try {
    require_once 'technician_performance_dashboard.php';
} catch (Throwable $e) {
    echo "FATAL: " . $e->getMessage() . "\n";
    echo "File: " . $e->getFile() . ":" . $e->getLine() . "\n";
    echo $e->getTraceAsString();
}
?>
