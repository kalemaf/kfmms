<?php
/**
 * Fix User Tenant IDs and Clear New Company Data
 */

require_once 'config.inc.php';

echo "<h1>Fixing User Tenant IDs</h1>";

// Update users set tenant_id = company_id where company_id > 0
$connection->query("UPDATE users SET tenant_id = COALESCE(company_id, 0) WHERE company_id > 0");

echo "<p>Updated users tenant_id to match company_id</p>";

echo "<h1>Companies and Their Data</h1>";

$companies = $connection->query("SELECT company_id, company_name FROM companies")->fetchAll(PDO::FETCH_ASSOC);

foreach ($companies as $co) {
    $tid = $co['company_id'];
    echo "<h2>Company $tid: {$co['company_name']}</h2>";
    
    // Work orders
    $wo = $connection->query("SELECT COUNT(*) as cnt FROM work_orders WHERE tenant_id = $tid")->fetch(PDO::FETCH_ASSOC);
    echo "<p>Work Orders: {$wo['cnt']}</p>";
    
    // Equipment
    $eq = $connection->query("SELECT COUNT(*) as cnt FROM equipment WHERE tenant_id = $tid")->fetch(PDO::FETCH_ASSOC);
    echo "<p>Equipment: {$eq['cnt']}</p>";
    
    // Inventory
    $inv = $connection->query("SELECT COUNT(*) as cnt FROM inventory WHERE tenant_id = $tid")->fetch(PDO::FETCH_ASSOC);
    echo "<p>Inventory: {$inv['cnt']}</p>";
    
    // Purchase Orders
    $po = $connection->query("SELECT COUNT(*) as cnt FROM purchase_orders WHERE tenant_id = $tid")->fetch(PDO::FETCH_ASSOC);
    echo "<p>Purchase Orders: {$po['cnt']}</p>";
}

echo "<h1>Users</h1>";
$users = $connection->query("SELECT user_id, username, company_id, tenant_id, role FROM users")->fetchAll(PDO::FETCH_ASSOC);
foreach ($users as $u) {
    echo "<p>User {$u['user_id']}: {$u['username']} (company: {$u['company_id']}, tenant: {$u['tenant_id']}, role: {$u['role']})</p>";
}