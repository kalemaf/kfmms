<?php
include 'config.inc.php';

$db = new SQLite3('database/maintenix.db');

// Check parts_master tenant distribution
$result = $db->query("SELECT tenant_id, COUNT(*) as cnt FROM parts_master GROUP BY tenant_id ORDER BY tenant_id");
echo "Parts by tenant:\n";
while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
    echo "  Tenant " . $row['tenant_id'] . ": " . $row['cnt'] . " parts\n";
}

// Check consumables
$result = $db->query("SELECT tenant_id, COUNT(*) as cnt FROM consumables GROUP BY tenant_id ORDER BY tenant_id");
echo "Consumables by tenant:\n";
while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
    echo "  Tenant " . $row['tenant_id'] . ": " . $row['cnt'] . " consumables\n";
}

// Check inventory_transactions
$result = $db->query("SELECT tenant_id, COUNT(*) as cnt FROM inventory_transactions GROUP BY tenant_id ORDER BY tenant_id");
echo "Inventory transactions by tenant:\n";
while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
    echo "  Tenant " . $row['tenant_id'] . ": " . $row['cnt'] . " transactions\n";
}

// Check companies
$result = $db->query('SELECT company_id, company_name FROM companies ORDER BY company_id');
echo "Companies:\n";
while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
    echo "  Company " . $row['company_id'] . ": " . $row['company_name'] . "\n";
}
?>