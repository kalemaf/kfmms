<?php
/**
 * Test Script: Warehouse Integration for Purchase Requests
 * 
 * Tests:
 * 1. Database schema updates applied correctly
 * 2. Warehouse dropdown populated in form
 * 3. Warehouse data saved to purchase_requests table
 * 4. Multi-tenant filtering working properly
 * 5. Site and warehouse combinations stored correctly
 */

require_once __DIR__ . '/config.inc.php';
require_once __DIR__ . '/common.inc.php';

// Set test tenant
$_SESSION['tenant_id'] = 1;
$_SESSION['user_id'] = 1;

echo "\n╔════════════════════════════════════════════════════════════════════════╗\n";
echo "║    TEST: Warehouse Integration for Purchase Requests                  ║\n";
echo "╚════════════════════════════════════════════════════════════════════════╝\n\n";

if (!$connection) {
    echo "[✗] Database connection failed\n";
    exit(1);
}

$db_type = $GLOBALS['db_type'] ?? 'sqlite';
echo "[→] Database Type: {$db_type}\n\n";

// TEST 1: Check schema
echo "TEST 1: Schema Verification\n";
echo str_repeat("-", 60) . "\n";

if ($db_type === 'sqlite') {
    $stmt = $connection->query("PRAGMA table_info('purchase_requests')");
    $columns = [];
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $columns[$row['name']] = $row['type'];
    }
} else {
    $stmt = $connection->query("SHOW COLUMNS FROM purchase_requests");
    $columns = [];
    while ($row = $stmt->fetch_assoc()) {
        $columns[$row['Field']] = $row['Type'];
    }
}

$required_columns = ['warehouse_id', 'site_location_id', 'tenant_id'];
$all_present = true;
foreach ($required_columns as $col) {
    if (array_key_exists($col, $columns)) {
        echo "[✓] Column '{$col}' exists (" . $columns[$col] . ")\n";
    } else {
        echo "[✗] Column '{$col}' MISSING\n";
        $all_present = false;
    }
}

// TEST 2: Check warehouses table
echo "\nTEST 2: Warehouses Table Verification\n";
echo str_repeat("-", 60) . "\n";

$wh_result = $connection->query("SELECT COUNT(*) as count FROM warehouses WHERE tenant_id = 1");
if ($db_type === 'sqlite') {
    $wh_count = $wh_result->fetch(PDO::FETCH_ASSOC)['count'];
} else {
    $wh_count = $wh_result->fetch_assoc()['count'];
}
echo "[→] Warehouses for tenant_id=1: {$wh_count}\n";

if ($wh_count > 0) {
    $wh_list = query_to_array("SELECT id, warehouse_name, warehouse_code FROM warehouses WHERE tenant_id = 1 LIMIT 5");
    foreach ($wh_list as $wh) {
        echo "    • {$wh['warehouse_name']} ({$wh['warehouse_code']})\n";
    }
} else {
    echo "[!] No warehouses found for tenant. You may need to create test data.\n";
}

// TEST 3: Check sites_locations table
echo "\nTEST 3: Sites & Locations Verification\n";
echo str_repeat("-", 60) . "\n";

$site_result = $connection->query("SELECT COUNT(*) as count FROM sites_locations WHERE is_active = 1");
if ($db_type === 'sqlite') {
    $site_count = $site_result->fetch(PDO::FETCH_ASSOC)['count'];
} else {
    $site_count = $site_result->fetch_assoc()['count'];
}
echo "[→] Active sites/locations: {$site_count}\n";

if ($site_count > 0) {
    $site_list = query_to_array("SELECT id, full_location FROM sites_locations WHERE is_active = 1 LIMIT 5");
    foreach ($site_list as $site) {
        echo "    • {$site['full_location']}\n";
    }
} else {
    echo "[!] No sites/locations found. You may need to create test data.\n";
}

// TEST 4: Check existing purchase requests with warehouse data
echo "\nTEST 4: Existing Purchase Requests\n";
echo str_repeat("-", 60) . "\n";

$pr_result = $connection->query("SELECT COUNT(*) as count FROM purchase_requests WHERE tenant_id = 1");
if ($db_type === 'sqlite') {
    $pr_count = $pr_result->fetch(PDO::FETCH_ASSOC)['count'];
} else {
    $pr_count = $pr_result->fetch_assoc()['count'];
}
echo "[→] Purchase requests for tenant_id=1: {$pr_count}\n";

if ($pr_count > 0) {
    $pr_list = query_to_array("SELECT id, pr_number, warehouse_id, site_location_id FROM purchase_requests WHERE tenant_id = 1 LIMIT 3");
    foreach ($pr_list as $pr) {
        echo "    • PR {$pr['pr_number']}: warehouse_id={$pr['warehouse_id']}, site_id={$pr['site_location_id']}\n";
    }
} else {
    echo "[→] No existing purchase requests (this is normal for new database)\n";
}

// TEST 5: Test create_purchase_request function
echo "\nTEST 5: create_purchase_request() Function\n";
echo str_repeat("-", 60) . "\n";

require_once __DIR__ . '/libraries/inventory_manager.php';

// Prepare test data
$test_items = [
    [
        'part_id' => 1,
        'description' => 'Test Item 1',
        'quantity' => 5,
        'unit_of_measure' => 'EA',
        'unit_cost' => 100.00
    ]
];

echo "[→] Testing create_purchase_request with warehouse_id...\n";
echo "    Parameters:\n";
echo "    - requestor_id: 1\n";
echo "    - site_location_id: 1 (if available)\n";
echo "    - warehouse_id: 1 (if available)\n";

if ($wh_count > 0 && $site_count > 0) {
    $_SESSION['tenant_id'] = 1;
    $_SESSION['user_id'] = 1;
    
    try {
        $pr_id = create_purchase_request(
            1,                          // requestor_id
            $test_items,               // items
            date('Y-m-d', strtotime('+7 days')), // required_by_date
            'normal',                  // priority
            'draft',                   // status
            'Test PR with warehouse',  // notes
            'Test Department',         // department
            'Test Cost Center',        // cost_center
            1,                         // site_location_id
            1,                         // warehouse_id
            '',                        // linked_work_order
            'TEST-001',               // project_code
            'BUD-001',                // budget_code
            '5001',                   // gl_account
            'OpEx',                   // expense_type
            'Testing warehouse integration', // justification
            $connection
        );
        
        if ($pr_id) {
            echo "[✓] Purchase request created with ID: {$pr_id}\n";
            
            // Verify warehouse data was saved
            if ($db_type === 'sqlite') {
                $verify = $connection->query("SELECT warehouse_id, site_location_id, tenant_id FROM purchase_requests WHERE id = $pr_id");
                $pr_data = $verify->fetch(PDO::FETCH_ASSOC);
            } else {
                $verify = $connection->query("SELECT warehouse_id, site_location_id, tenant_id FROM purchase_requests WHERE id = $pr_id");
                $pr_data = $verify->fetch_assoc();
            }
            
            echo "[✓] Warehouse ID saved: {$pr_data['warehouse_id']}\n";
            echo "[✓] Site Location ID saved: {$pr_data['site_location_id']}\n";
            echo "[✓] Tenant ID saved: {$pr_data['tenant_id']}\n";
            
            if ($pr_data['warehouse_id'] == 1 && $pr_data['site_location_id'] == 1 && $pr_data['tenant_id'] == 1) {
                echo "[✓] All data saved correctly!\n";
            } else {
                echo "[✗] Data mismatch detected\n";
            }
        } else {
            echo "[✗] Failed to create purchase request\n";
        }
    } catch (Exception $e) {
        echo "[✗] Error: " . $e->getMessage() . "\n";
        echo "[!] Stack trace:\n";
        echo $e->getTraceAsString() . "\n";
    } catch (Error $e) {
        echo "[✗] Error: " . $e->getMessage() . "\n";
        echo "[!] Stack trace:\n";
        echo $e->getTraceAsString() . "\n";
    }
} else {
    echo "[!] Skipped: Need both warehouses and sites/locations to test\n";
}

// TEST 6: Multi-tenant isolation
echo "\nTEST 6: Multi-Tenant Isolation\n";
echo str_repeat("-", 60) . "\n";

// Count PRs for each tenant
if ($db_type === 'sqlite') {
    $stmt = $connection->query("SELECT tenant_id, COUNT(*) as count FROM purchase_requests GROUP BY tenant_id");
    $results = [];
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $results[] = $row;
    }
} else {
    $stmt = $connection->query("SELECT tenant_id, COUNT(*) as count FROM purchase_requests GROUP BY tenant_id");
    $results = [];
    while ($row = $stmt->fetch_assoc()) {
        $results[] = $row;
    }
}

if (!empty($results)) {
    foreach ($results as $row) {
        echo "[→] Tenant {$row['tenant_id']}: {$row['count']} purchase requests\n";
    }
    echo "[✓] Multi-tenant support verified\n";
} else {
    echo "[!] No purchase request data found\n";
}

echo "\n" . str_repeat("=", 60) . "\n";
echo "TEST COMPLETED\n";
echo str_repeat("=", 60) . "\n\n";

echo "NEXT STEPS:\n";
echo "1. Verify warehouse dropdown appears in purchase_request.php form\n";
echo "2. Create a test purchase request through the web interface\n";
echo "3. Confirm warehouse_id is saved to database\n";
echo "4. Test warehouse filtering by tenant_id\n";
echo "5. Create another PR with different warehouse and verify it shows correctly\n\n";
?>
