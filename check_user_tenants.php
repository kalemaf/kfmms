<?php
require_once 'config.inc.php';

echo "=== User Company/Tenant Mapping ===\n\n";
$result = $connection->query("SELECT user_id, username, company_id, tenant_id FROM users ORDER BY company_id, username");
while ($row = $result->fetch(PDO::FETCH_ASSOC)) {
    echo "User: {$row['username']} (user_id: {$row['user_id']})\n";
    echo "  company_id: {$row['company_id']}\n";
    echo "  tenant_id: {$row['tenant_id']}\n";
    echo "\n";
}