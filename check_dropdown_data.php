<?php
require_once __DIR__ . '/config.inc.php';
require_once __DIR__ . '/common.inc.php';

$_SESSION['tenant_id'] = 1;
$_SESSION['user_id'] = 1;
$_SESSION['user'] = 'developer';

$tenant_id = 1;

echo "DROPDOWN DATA AVAILABILITY CHECK\n";
echo str_repeat("=", 70) . "\n\n";

// Check 1: Sites/Locations
echo "1. SITES/LOCATIONS\n";
echo str_repeat("-", 70) . "\n";
$query = "SELECT id, full_location FROM sites_locations WHERE is_active = 1 AND tenant_id = $tenant_id ORDER BY full_location";
echo "Query: $query\n";
$result = query_to_array($query);
echo "Count: " . count($result) . "\n";
if (count($result) > 0) {
    echo "✓ Sample: {$result[0]['full_location']}\n";
} else {
    echo "✗ NO DATA\n";
}

// Check 2: Warehouses  
echo "\n2. WAREHOUSES\n";
echo str_repeat("-", 70) . "\n";
$query = "SELECT id, warehouse_name, warehouse_code FROM warehouses WHERE is_active = 1 AND tenant_id = $tenant_id ORDER BY warehouse_name";
echo "Query: $query\n";
$result = query_to_array($query);
echo "Count: " . count($result) . "\n";
if (count($result) > 0) {
    echo "✓ Sample: {$result[0]['warehouse_name']} ({$result[0]['warehouse_code']})\n";
} else {
    echo "✗ NO DATA\n";
}

// Check 3: Work Orders
echo "\n3. WORK ORDERS\n";
echo str_repeat("-", 70) . "\n";
$query = "SELECT wo_id, descriptive_text FROM work_orders WHERE wo_status IN ('Assigned', 'Pending Approval') AND tenant_id = $tenant_id ORDER BY submit_date DESC";
echo "Query: $query\n";
$result = query_to_array($query);
echo "Count: " . count($result) . "\n";
if (count($result) > 0) {
    echo "✓ Sample: {$result[0]['wo_id']} - {$result[0]['descriptive_text']}\n";
} else {
    echo "✗ NO DATA\n";
}

// Check what work orders exist regardless of status
echo "\n4. WORK ORDERS - ALL STATUSES (debug)\n";
echo str_repeat("-", 70) . "\n";
$query = "SELECT DISTINCT wo_status FROM work_orders WHERE tenant_id = $tenant_id";
echo "Available statuses: ";
$statuses = query_to_array($query);
foreach ($statuses as $row) {
    echo "'{$row['wo_status']}' ";
}
echo "\n\n";

$query = "SELECT COUNT(*) as cnt FROM work_orders WHERE tenant_id = $tenant_id";
$result = query_to_array($query);
echo "Total work orders for tenant_id=$tenant_id: " . $result[0]['cnt'] . "\n";

// Count by status
$query = "SELECT wo_status, COUNT(*) as cnt FROM work_orders WHERE tenant_id = $tenant_id GROUP BY wo_status";
$results = query_to_array($query);
foreach ($results as $row) {
    echo "  - {$row['wo_status']}: {$row['cnt']}\n";
}

// Check 5: Parts (for items section)
echo "\n5. PARTS/INVENTORY\n";
echo str_repeat("-", 70) . "\n";
$query = "SELECT id, part_code, part_name FROM parts_master WHERE is_active = 1 AND tenant_id = $tenant_id ORDER BY part_name";
echo "Query: $query\n";
$result = query_to_array($query);
echo "Count: " . count($result) . "\n";
if (count($result) > 0) {
    echo "✓ Sample: {$result[0]['part_code']} - {$result[0]['part_name']}\n";
} else {
    echo "✗ NO DATA\n";
}

// Check 6: Vendors
echo "\n6. VENDORS\n";
echo str_repeat("-", 70) . "\n";
$query = "SELECT id, vendor_name FROM vendors WHERE is_active = 1 AND tenant_id = $tenant_id ORDER BY vendor_name";
echo "Query: $query\n";
$result = query_to_array($query);
echo "Count: " . count($result) . "\n";
if (count($result) > 0) {
    echo "✓ Sample: {$result[0]['vendor_name']}\n";
} else {
    echo "✗ NO DATA\n";
}

echo "\n" . str_repeat("=", 70) . "\n";
echo "SUMMARY\n";
echo str_repeat("=", 70) . "\n";

// Check which lists have data and which don't
$sites = query_to_array("SELECT COUNT(*) as cnt FROM sites_locations WHERE is_active = 1 AND tenant_id = $tenant_id");
$warehouses = query_to_array("SELECT COUNT(*) as cnt FROM warehouses WHERE is_active = 1 AND tenant_id = $tenant_id");
$work_orders = query_to_array("SELECT COUNT(*) as cnt FROM work_orders WHERE wo_status IN ('Assigned', 'Pending Approval') AND tenant_id = $tenant_id");
$parts = query_to_array("SELECT COUNT(*) as cnt FROM parts_master WHERE is_active = 1 AND tenant_id = $tenant_id");
$vendors = query_to_array("SELECT COUNT(*) as cnt FROM vendors WHERE is_active = 1 AND tenant_id = $tenant_id");

echo "Form Dropdown Status for tenant_id=$tenant_id:\n";
echo "  Sites/Locations: " . ($sites[0]['cnt'] > 0 ? "✓ " . $sites[0]['cnt'] : "✗ 0") . " records\n";
echo "  Warehouses: " . ($warehouses[0]['cnt'] > 0 ? "✓ " . $warehouses[0]['cnt'] : "✗ 0") . " records\n";
echo "  Work Orders (Assigned/Pending): " . ($work_orders[0]['cnt'] > 0 ? "✓ " . $work_orders[0]['cnt'] : "✗ 0") . " records\n";
echo "  Parts: " . ($parts[0]['cnt'] > 0 ? "✓ " . $parts[0]['cnt'] : "✗ 0") . " records\n";
echo "  Vendors: " . ($vendors[0]['cnt'] > 0 ? "✓ " . $vendors[0]['cnt'] : "✗ 0") . " records\n";

?>
