<?php
include 'config.inc.php';

$db = new SQLite3('database/maintenix.db');
$result = $db->query('PRAGMA table_info(companies)');
echo "Companies table columns:\n";
while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
    echo $row['name'] . ' - ' . $row['type'] . "\n";
}

echo "\nCompanies data:\n";
$result = $db->query('SELECT * FROM companies LIMIT 10');
while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
    echo "ID: " . ($row['company_id'] ?? $row['id'] ?? 'unknown') . " - " . $row['company_name'] . "\n";
}
?>