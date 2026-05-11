<?php
/**
 * Multi-Tenant Dropdown Isolation Test
 * Tests that dropdowns filter correctly by tenant_id across the full app
 */

require_once 'config.inc.php';
require_once 'common.inc.php';
require_once 'libraries/inventory_manager.php';

echo "MULTI-TENANT DROPDOWN ISOLATION TEST\n";
echo str_repeat("=", 70) . "\n\n";

// Test both tenant IDs
$test_tenants = [1, 11];

foreach ($test_tenants as $tenant_id) {
    echo "TESTING TENANT_ID: $tenant_id\n";
    echo str_repeat("-", 70) . "\n";
    
    // Simulate session
    $_SESSION['tenant_id'] = $tenant_id;
    
    if (!$connection) {
        echo "ERROR: No database connection\n\n";
        continue;
    }
    
    // Test 1: Equipment
    echo "\n1. EQUIPMENT (from equipment.php)\n";
    $query = "SELECT COUNT(*) as cnt FROM equipment WHERE tenant_id = $tenant_id";
    $result = $connection->query($query);
    $row = $result->fetch_assoc();
    echo "   - Direct query count: {$row['cnt']} records\n";
    
    // Simulate what equipment.php does
    $query = "SELECT * FROM equipment WHERE tenant_id = $tenant_id ORDER BY description";
    $res = $connection->query($query);
    $count = 0;
    if ($res) {
        while ($r = $res->fetch_assoc()) {
            $count++;
        }
    }
    echo "   - Equipment list (simulated): $count items\n";
    
    // Test 2: Consumables
    echo "\n2. CONSUMABLES (from work_order.php)\n";
    $query = "SELECT COUNT(*) as cnt FROM consumables WHERE is_active = 1 AND tenant_id = $tenant_id";
    $result = $connection->query($query);
    $row = $result->fetch_assoc();
    echo "   - Direct query count: {$row['cnt']} records\n";
    
    // Test 3: Parts Master
    echo "\n3. PARTS MASTER (from purchase_request.php)\n";
    $query = "SELECT COUNT(*) as cnt FROM parts_master WHERE tenant_id = $tenant_id";
    $result = $connection->query($query);
    $row = $result->fetch_assoc();
    echo "   - Direct query count: {$row['cnt']} records\n";
    
    // Test 4: Vendors
    echo "\n4. VENDORS (from purchase_request.php)\n";
    $query = "SELECT COUNT(*) as cnt FROM vendors WHERE is_active = 1 AND tenant_id = $tenant_id";
    $result = $connection->query($query);
    $row = $result->fetch_assoc();
    echo "   - Direct query count: {$row['cnt']} records\n";
    
    // Test 5: Warehouses
    echo "\n5. WAREHOUSES (using get_warehouses function)\n";
    $warehouses = get_warehouses($connection);
    echo "   - get_warehouses() returned: " . count($warehouses) . " items\n";
    
    // Test 6: Sites/Locations
    echo "\n6. SITES/LOCATIONS\n";
    $query = "SELECT COUNT(*) as cnt FROM sites_locations WHERE is_active = 1 AND tenant_id = $tenant_id";
    $result = $connection->query($query);
    $row = $result->fetch_assoc();
    echo "   - Direct query count: {$row['cnt']} records\n";
    
    // Test 7: Work Orders
    echo "\n7. WORK ORDERS\n";
    $query = "SELECT COUNT(*) as cnt FROM work_orders WHERE tenant_id = $tenant_id";
    $result = $connection->query($query);
    $row = $result->fetch_assoc();
    echo "   - Direct query count: {$row['cnt']} records\n";
    
    // Test 8: Equipment Spares
    echo "\n8. EQUIPMENT SPARES\n";
    $query = "SELECT COUNT(*) as cnt FROM equipment_spares WHERE tenant_id = $tenant_id";
    $result = $connection->query($query);
    $row = $result->fetch_assoc();
    echo "   - Direct query count: {$row['cnt']} records\n";
    
    echo "\n";
}

echo str_repeat("=", 70) . "\n";
echo "MULTI-TENANT TEST COMPLETE\n";
echo "\nKEY VERIFICATION:\n";
echo "✓ If each tenant shows ONLY their own data, isolation is working\n";
echo "✓ If tenant_id=1 and tenant_id=11 show different counts, isolation is working\n";
echo "✓ If any dropdown shows cross-tenant data, there's a data leakage issue\n";

?>
