<?php
/**
 * Check kalemat user and company data
 */
require_once 'config.inc.php';
require_once 'common.inc.php';

// Check kalemat user
$user = $connection->query("SELECT user_id, username, email, company_id, tenant_id, role FROM users WHERE username = 'kalemat'")->fetch(PDO::FETCH_ASSOC);
echo "<h2>User: kalemat</h2>";
echo "<pre>";
print_r($user);
echo "</pre>";

// Get company info
if ($user) {
    $company = $connection->query("SELECT * FROM companies WHERE id = " . (int)$user['company_id'])->fetch(PDO::FETCH_ASSOC);
    echo "<h2>Company Info (ID: {$user['company_id']})</h2>";
    echo "<pre>";
    print_r($company);
    echo "</pre>";
}

// Check work orders by tenant
echo "<h2>Work Orders by Tenant</h2>";
$wo_by_tenant = $connection->query("SELECT tenant_id, COUNT(*) as cnt FROM work_orders GROUP BY tenant_id ORDER BY tenant_id")->fetchAll(PDO::FETCH_ASSOC);
echo "<pre>";
print_r($wo_by_tenant);
echo "</pre>";

// Check all companies
echo "<h2>All Companies</h2>";
$companies = $connection->query("SELECT id, company_name FROM companies ORDER BY id")->fetchAll(PDO::FETCH_ASSOC);
echo "<pre>";
print_r($companies);
echo "</pre>";

// Check users with company_id
echo "<h2>Users by Company</h2>";
$users = $connection->query("SELECT user_id, username, company_id, tenant_id, role FROM users ORDER BY company_id, user_id")->fetchAll(PDO::FETCH_ASSOC);
echo "<pre>";
print_r($users);
echo "</pre>";