#!/usr/bin/env php
<?php
/**
 * Work Order Tenant Isolation - Detailed Diagnostic
 * Shows which work orders belong to which tenants
 */

require_once __DIR__ . '/config.inc.php';

echo "\n";
echo "╔════════════════════════════════════════════════════════════════════════╗\n";
echo "║   Work Order Tenant Diagnostic - Detailed Report                      ║\n";
echo "╚════════════════════════════════════════════════════════════════════════╝\n\n";

if (!$connection) {
    echo "ERROR: Database connection failed\n";
    exit(1);
}

// Get all work orders with their tenant info
echo "ALL WORK ORDERS IN DATABASE:\n";
echo str_repeat("─", 80) . "\n\n";

$query = "SELECT wo_id, descriptive_text, tenant_id, requestor, submit_date FROM work_orders ORDER BY wo_id";
$result = $connection->query($query);

if ($GLOBALS['db_type'] === 'sqlite') {
    $work_orders = [];
    while ($row = $result->fetch(PDO::FETCH_ASSOC)) {
        $work_orders[] = $row;
    }
} else {
    $work_orders = [];
    while ($row = $result->fetch_assoc()) {
        $work_orders[] = $row;
    }
}

foreach ($work_orders as $wo) {
    echo "WO #" . intval($wo['wo_id']) . " - Tenant " . intval($wo['tenant_id']) . "\n";
    echo "  Title: " . htmlspecialchars($wo['descriptive_text'] ?? '(no title)') . "\n";
    echo "  Requestor: " . htmlspecialchars($wo['requestor'] ?? '(unknown)') . "\n";
    echo "  Date: " . htmlspecialchars($wo['submit_date'] ?? '(unknown)') . "\n";
    echo "\n";
}

// Show user-company mapping
echo "\nUSER TO COMPANY MAPPING:\n";
echo str_repeat("─", 80) . "\n\n";

$user_query = "SELECT user_id, username, email, company_id FROM users ORDER BY user_id";
$result = $connection->query($user_query);

if ($GLOBALS['db_type'] === 'sqlite') {
    $users = [];
    while ($row = $result->fetch(PDO::FETCH_ASSOC)) {
        $users[] = $row;
    }
} else {
    $users = [];
    while ($row = $result->fetch_assoc()) {
        $users[] = $row;
    }
}

foreach ($users as $user) {
    $company_id = intval($user['company_id'] ?? 0);
    echo "User #" . intval($user['user_id']) . " - Company " . $company_id . "\n";
    echo "  Username: " . htmlspecialchars($user['username'] ?? '(unknown)') . "\n";
    echo "  Email: " . htmlspecialchars($user['email'] ?? '(unknown)') . "\n";
    echo "  Session tenant_id will be: $company_id\n";
    echo "\n";
}

// Summary
echo "\nSUMMARY:\n";
echo str_repeat("─", 80) . "\n\n";

$tenant_query = "SELECT DISTINCT tenant_id FROM work_orders ORDER BY tenant_id";
$result = $connection->query($tenant_query);

if ($GLOBALS['db_type'] === 'sqlite') {
    $tenants = [];
    while ($row = $result->fetch(PDO::FETCH_ASSOC)) {
        $tenants[] = $row;
    }
} else {
    $tenants = [];
    while ($row = $result->fetch_assoc()) {
        $tenants[] = $row;
    }
}

foreach ($tenants as $t) {
    $tenant_id = intval($t['tenant_id']);
    $wo_count_query = "SELECT COUNT(*) as cnt FROM work_orders WHERE tenant_id = $tenant_id";
    $result = $connection->query($wo_count_query);
    $count_row = ($GLOBALS['db_type'] === 'sqlite') ? $result->fetch(PDO::FETCH_ASSOC) : $result->fetch_assoc();
    $wo_count = intval($count_row['cnt']);
    
    $user_count_query = "SELECT COUNT(*) as cnt FROM users WHERE company_id = $tenant_id";
    $result = $connection->query($user_count_query);
    $user_row = ($GLOBALS['db_type'] === 'sqlite') ? $result->fetch(PDO::FETCH_ASSOC) : $result->fetch_assoc();
    $user_count = intval($user_row['cnt']);
    
    echo "Tenant #$tenant_id:\n";
    echo "  Work Orders: $wo_count\n";
    echo "  Users: $user_count\n";
    echo "\n";
}

echo "\nDIAGNOSTIC COMPLETE\n";
echo "\n";

exit(0);
?>
