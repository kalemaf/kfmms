<?php
include 'config.inc.php';
include 'common.inc.php';

$db = new SQLite3('database/maintenix.db');

// Check users count
$result = $db->query('SELECT COUNT(*) as cnt FROM users');
$row = $result->fetchArray(SQLITE3_ASSOC);
echo "Total users: " . $row['cnt'] . "\n";

// Check parts_master
$result = $db->query('SELECT COUNT(*) as cnt FROM parts_master');
$row = $result->fetchArray(SQLITE3_ASSOC);
echo "Total parts_master: " . $row['cnt'] . "\n";

// Check if parts_master has tenant_id
$result = $db->query("PRAGMA table_info(parts_master)");
$has_tenant = false;
while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
    if ($row['name'] == 'tenant_id') {
        $has_tenant = true;
        break;
    }
}
echo "parts_master has tenant_id: " . ($has_tenant ? 'YES' : 'NO') . "\n";

// Check tenant distribution in parts_master
if ($has_tenant) {
    $result = $db->query('SELECT tenant_id, COUNT(*) as cnt FROM parts_master GROUP BY tenant_id');
    echo "Parts by tenant:\n";
    while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
        echo "  Tenant " . $row['tenant_id'] . ": " . $row['cnt'] . " parts\n";
    }
}
?>