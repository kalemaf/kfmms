<?php
/**
 * Diagnostic: Consumable Usage Recording Issue
 * 
 * Checks:
 * 1. Does consumable_usage table have tenant_id column?
 * 2. Are consumable usage records being inserted?
 * 3. Do they have the correct tenant_id?
 * 4. Is get_consumable_usage() finding them?
 */

require_once 'config.inc.php';
require_once 'common.inc.php';

$_SESSION['tenant_id'] = 1;
$tenant_id = 1;

echo "\n╔════════════════════════════════════════════════════════════════════════╗\n";
echo "║    DIAGNOSTIC: Consumable Usage Not Showing                           ║\n";
echo "╚════════════════════════════════════════════════════════════════════════╝\n\n";

// Check table structure
echo "=== CHECKING consumable_usage TABLE STRUCTURE ===\n";
$stmt = $connection->query("PRAGMA table_info('consumable_usage')");
$columns = [];
$has_tenant_id = false;
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $columns[] = $row['name'];
    if ($row['name'] === 'tenant_id') {
        $has_tenant_id = true;
    }
}
echo "Columns: " . implode(', ', $columns) . "\n";
echo "Has tenant_id: " . ($has_tenant_id ? "YES ✓" : "NO ✗") . "\n\n";

// Check records in consumable_usage
echo "=== CHECKING consumable_usage RECORDS ===\n";
$all_records = $connection->query("SELECT COUNT(*) as cnt FROM consumable_usage")->fetch(PDO::FETCH_ASSOC);
$tenant1_records = $connection->query("SELECT COUNT(*) as cnt FROM consumable_usage WHERE tenant_id = 1")->fetch(PDO::FETCH_ASSOC);
$null_tenant = $connection->query("SELECT COUNT(*) as cnt FROM consumable_usage WHERE tenant_id IS NULL OR tenant_id <= 0")->fetch(PDO::FETCH_ASSOC);

echo "Total records in consumable_usage: " . $all_records['cnt'] . "\n";
echo "Records with tenant_id = 1: " . $tenant1_records['cnt'] . "\n";
echo "Records with NULL/invalid tenant_id: " . $null_tenant['cnt'] . "\n\n";

if ($all_records['cnt'] > 0) {
    echo "Sample records:\n";
    $sample = $connection->query("SELECT * FROM consumable_usage LIMIT 3");
    while ($row = $sample->fetch(PDO::FETCH_ASSOC)) {
        echo "  [" . $row['id'] . "] WO#{$row['work_order_id']} Qty={$row['quantity_used']} Tenant={$row['tenant_id']} Date={$row['usage_date']}\n";
    }
} else {
    echo "No records found\n";
}

echo "\n=== CHECKING get_consumable_usage() FUNCTION ===\n";

// Test the get_consumable_usage function
require_once 'libraries/inventory_manager.php';
$usage_records = get_consumable_usage($connection, 10);
echo "get_consumable_usage() returned: " . count($usage_records) . " records\n";

if (count($usage_records) > 0) {
    echo "[✓] Function works, records found:\n";
    foreach ($usage_records as $record) {
        echo "  [{$record['id']}] {$record['consumable_name']} Qty={$record['quantity_used']} on WO#{$record['work_order_id']}\n";
    }
} else {
    echo "[✗] Function returned no records\n";
    echo "    This is because tenant filtering is applied but records may not have tenant_id\n";
}

echo "\n=== THE PROBLEM ===\n";
if ($has_tenant_id && $null_tenant['cnt'] > 0) {
    echo "[!] consumable_usage table HAS tenant_id column\n";
    echo "[!] But " . $null_tenant['cnt'] . " records have NULL/invalid tenant_id\n";
    echo "[!] These are invisible to get_consumable_usage() which filters by current tenant\n";
    echo "\n[✗] ROOT CAUSE: record_consumable_usage() is NOT including tenant_id in INSERT\n";
} elseif ($all_records['cnt'] > 0 && $tenant1_records['cnt'] == 0) {
    echo "[!] Records exist but NONE have tenant_id = 1\n";
    echo "[!] They're probably all NULL or wrong tenant_id\n";
    echo "\n[✗] ROOT CAUSE: record_consumable_usage() is NOT including tenant_id in INSERT\n";
} else {
    echo "[✓] Table structure looks OK\n";
}

echo "\n=== CHECKING record_consumable_usage() FUNCTION CODE ===\n";
$func_file = 'libraries/inventory_manager.php';
$content = file_get_contents($func_file);
if (strpos($content, 'INSERT INTO consumable_usage') !== false) {
    // Find the INSERT statement
    preg_match('/INSERT INTO consumable_usage \((.*?)\)/is', $content, $matches);
    if ($matches) {
        $columns_in_insert = $matches[1];
        echo "INSERT columns: " . trim($columns_in_insert) . "\n";
        if (strpos($columns_in_insert, 'tenant_id') === false) {
            echo "[✗] CONFIRMED: tenant_id is NOT in the INSERT statement!\n";
        }
    }
}

echo "\n";
?>
