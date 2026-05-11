<?php
/**
 * Work Order Tenant Data Cleanup Script
 * 
 * This script helps verify and fix work order tenant assignments.
 * Use this to ensure each company only sees their own work orders.
 */

require_once __DIR__ . '/config.inc.php';

if (session_status() === PHP_SESSION_NONE) {
    session_save_path($session_save_path);
    session_start();
}

if (empty($_SESSION['user']) || $_SESSION['role'] !== 'admin') {
    die("ERROR: This script is for administrators only.\n");
}

echo "======================================================\n";
echo "WORK ORDER TENANT DATA CLEANUP\n";
echo "======================================================\n\n";

if (!$connection) {
    echo "ERROR: Database connection failed\n";
    exit(1);
}

// Show current distribution
echo "Current Work Order Distribution by Tenant:\n";
echo "-------------------------------------------\n";

if ($GLOBALS['db_type'] === 'sqlite') {
    $query = "SELECT tenant_id, COUNT(*) as wo_count FROM work_orders GROUP BY tenant_id ORDER BY tenant_id";
} else {
    $query = "SELECT tenant_id, COUNT(*) as wo_count FROM work_orders GROUP BY tenant_id ORDER BY tenant_id";
}

$result = $connection->query($query);
$total_wos = 0;
$tenant_counts = [];

if ($result) {
    while ($row = $result->fetch_assoc()) {
        $tenant_id = $row['tenant_id'] ?? 'NULL';
        $count = $row['wo_count'];
        $tenant_counts[$tenant_id] = $count;
        $total_wos += $count;
        echo "  Tenant $tenant_id: $count work orders\n";
    }
}

echo "\nTotal Work Orders: $total_wos\n";
echo "\n";

// Show work orders with missing or default tenant
echo "Work Orders with Tenant ID = 1 (may need review):\n";
echo "-------------------------------------------\n";

$query_1 = "SELECT wo_id, descriptive_text, tenant_id, submit_date FROM work_orders WHERE tenant_id = 1 ORDER BY submit_date DESC LIMIT 10";
$result_1 = $connection->query($query_1);
$count_1 = 0;

if ($result_1) {
    while ($row = $result_1->fetch_assoc()) {
        $count_1++;
        echo "  WO #{$row['wo_id']}: {$row['descriptive_text']} (Tenant: {$row['tenant_id']}, Date: {$row['submit_date']})\n";
    }
}

if ($count_1 === 0) {
    echo "  (No work orders with tenant_id = 1)\n";
}

echo "\n";
echo "======================================================\n";
echo "INFORMATION\n";
echo "======================================================\n";
echo "\nTo fix work orders for a new company:\n";
echo "1. If you created work orders in the wrong company context,\n";
echo "   they may have been assigned to the wrong tenant.\n";
echo "2. Create NEW work orders after logging in to the correct company.\n";
echo "3. If needed, contact your administrator to manually reassign\n";
echo "   work orders to the correct tenant using the Update Work Order feature.\n";
echo "\nRecommended Action:\n";
echo "- Each company should have a separate tenant_id\n";
echo "- Always ensure users are logged into the correct company\n";
echo "- New work orders created will automatically be assigned\n";
echo "  to your company's tenant_id\n";

echo "\n";
?>
