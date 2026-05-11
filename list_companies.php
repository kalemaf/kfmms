<?php
require_once 'config.inc.php';

echo "=== All Companies ===\n";
$result = $connection->query("SELECT company_id, company_name FROM companies ORDER BY company_id");
while ($row = $result->fetch(PDO::FETCH_ASSOC)) {
    echo "company_id: {$row['company_id']} = {$row['company_name']}\n";
}