<?php
require_once 'config.inc.php';
require_once 'common.inc.php';

$result = $connection->query("SELECT user_id, username, email, company_id FROM users WHERE username = 'seka'");
if ($result && $row = $result->fetch_assoc()) {
    echo "User found: ";
    print_r($row);
} else {
    echo "User not found\n";
}

// Check companies
echo "\nCompanies:\n";
$companies = $connection->query("SELECT company_id, company_name FROM companies");
while ($c = $companies->fetch_assoc()) {
    echo "- Company {$c['company_id']}: {$c['company_name']}\n";
}

// Check parts_master tenant distribution
echo "\nParts by tenant:\n";
$parts = $connection->query("SELECT tenant_id, COUNT(*) as cnt FROM parts_master GROUP BY tenant_id");
while ($p = $parts->fetch_assoc()) {
    echo "- Tenant {$p['tenant_id']}: {$p['cnt']} parts\n";
}
?>