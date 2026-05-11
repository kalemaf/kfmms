<?php
/**
 * Verify Tenant Isolation
 */

require_once 'config.inc.php';
require_once 'common.inc.php';

echo "<h1>Tenant Isolation Verification</h1>";

echo "<h2>Companies</h2>";
$companies = $connection->query("SELECT company_id, name, email FROM companies")->fetchAll(PDO::FETCH_ASSOC);
echo "<table border='1' cellpadding='5'>";
echo "<tr><th>ID</th><th>Name</th><th>Email</th><th>Work Orders</th><th>Equipment</th><th>Inventory</th></tr>";

foreach ($companies as $co) {
    $tid = $co['company_id'];
    $wo_count = $connection->query("SELECT COUNT(*) as cnt FROM work_orders WHERE tenant_id = $tid")->fetch(PDO::FETCH_ASSOC);
    $eq_count = $connection->query("SELECT COUNT(*) as cnt FROM equipment WHERE tenant_id = $tid")->fetch(PDO::FETCH_ASSOC);
    $inv_count = $connection->query("SELECT COUNT(*) as cnt FROM inventory WHERE tenant_id = $tid")->fetch(PDO::FETCH_ASSOC);
    
    echo "<tr><td>{$tid}</td><td>{$co['name']}</td><td>{$co['email']}</td><td>{$wo_count['cnt']}</td><td>{$eq_count['cnt']}</td><td>{$inv_count['cnt']}</td></tr>";
}
echo "</table>";

echo "<h2>Users by Tenant</h2>";
foreach ($companies as $co) {
    $tid = $co['company_id'];
    $users = $connection->query("SELECT user_id, username, email, role FROM users WHERE company_id = $tid OR (company_id = 0 AND tenant_id = $tid)")->fetchAll(PDO::FETCH_ASSOC);
    echo "<h3>Company $tid - {$co['name']}</h3>";
    if (empty($users)) {
        echo "<p>No users</p>";
    } else {
        echo "<ul>";
        foreach ($users as $u) {
            echo "<li>{$u['username']} ({$u['role']}) - {$u['email']}</li>";
        }
        echo "</ul>";
    }
}