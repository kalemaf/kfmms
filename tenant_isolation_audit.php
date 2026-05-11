<?php
/**
 * Tenant Isolation Audit Report
 * 
 * This script verifies that all inventory and work order tables have proper
 * tenant_id columns and filtering. Use this to ensure complete multi-tenancy.
 */

require_once __DIR__ . '/config.inc.php';

if (session_status() === PHP_SESSION_NONE) {
    session_save_path($session_save_path);
    session_start();
}

// Allow CLI execution or admin users
$is_cli = php_sapi_name() === 'cli';
$is_admin = !empty($_SESSION['user']) && $_SESSION['role'] === 'admin';

if (!$is_cli && !$is_admin) {
    die("ERROR: This script is for administrators only.\n");
}

echo "======================================================\n";
echo "MULTI-TENANT ISOLATION AUDIT REPORT\n";
echo "======================================================\n\n";

if (!$connection) {
    echo "ERROR: Database connection failed\n";
    exit(1);
}

// Helper function to check if column exists
function column_exists($connection, $table, $column) {
    global $db_type;
    
    if ($db_type === 'sqlite') {
        try {
            $result = $connection->query("PRAGMA table_info('$table')");
            $columns = $result->fetchAll(PDO::FETCH_ASSOC);
            foreach ($columns as $col) {
                if ($col['name'] === $column) {
                    return true;
                }
            }
            return false;
        } catch (Exception $e) {
            return false;
        }
    } else {
        try {
            $result = $connection->query("SHOW COLUMNS FROM `$table` LIKE '$column'");
            return $result->num_rows > 0;
        } catch (Exception $e) {
            return false;
        }
    }
}

// Key tables that should have tenant_id for multi-tenancy
$critical_tables = [
    'work_orders' => 'Must filter by tenant_id',
    'work_order_requests' => 'Must filter by tenant_id',
    'equipment' => 'Each company has separate equipment',
    'inventory' => 'Stock levels per company',
    'parts_master' => 'Inventory parts per company',
    'vendors' => 'Vendor relationships per company',
    'warehouses' => 'Warehouse locations per company',
    'consumables' => 'Consumable inventory per company',
    'pm_masters' => 'Preventive maintenance per company',
    'purchase_requests' => 'Purchase requests per company',
];

$db_type = $GLOBALS['db_type'] ?? 'sqlite';

echo "CRITICAL TABLE AUDIT\n";
echo "-------------------------------------------\n";

$all_good = true;
foreach ($critical_tables as $table => $description) {
    echo "\n$table: $description\n";
    
    // Check if table exists
    if ($db_type === 'sqlite') {
        $check_query = "SELECT name FROM sqlite_master WHERE type='table' AND name='$table'";
    } else {
        $check_query = "SHOW TABLES LIKE '$table'";
    }
    
    try {
        $result = $connection->query($check_query);
        $table_exists = $db_type === 'sqlite' ? $result->fetch(PDO::FETCH_ASSOC) : ($result->num_rows > 0);
    } catch (Exception $e) {
        $table_exists = false;
    }
    
    if (!$table_exists) {
        echo "  ✗ TABLE DOES NOT EXIST\n";
        $all_good = false;
        continue;
    }
    
    echo "  ✓ Table exists\n";
    
    // Check for tenant_id column
    if (column_exists($connection, $table, 'tenant_id')) {
        echo "  ✓ Has tenant_id column\n";
    } else {
        echo "  ✗ MISSING tenant_id column - CRITICAL!\n";
        $all_good = false;
    }
    
    // Show record distribution
    try {
        $count_query = "SELECT COUNT(*) as total FROM $table";
        $count_result = $connection->query($count_query);
        if ($count_result) {
            if ($db_type === 'sqlite') {
                $row = $count_result->fetch(PDO::FETCH_ASSOC);
                $total = $row['total'] ?? 0;
            } else {
                $row = $count_result->fetch_assoc();
                $total = $row['total'] ?? 0;
            }
            echo "  → Total records: $total\n";
        }
    } catch (Exception $e) {
        echo "  → Could not count records\n";
    }
}

echo "\n\n";
echo "TENANT DISTRIBUTION\n";
echo "-------------------------------------------\n";

// Show work order distribution by tenant
echo "\nWork Orders by Tenant:\n";
try {
    $query = "SELECT tenant_id, COUNT(*) as wo_count FROM work_orders GROUP BY tenant_id ORDER BY tenant_id";
    $result = $connection->query($query);
    $found_data = false;
    
    if ($result) {
        while ($row = $db_type === 'sqlite' ? $result->fetch(PDO::FETCH_ASSOC) : $result->fetch_assoc()) {
            $found_data = true;
            echo "  Tenant {$row['tenant_id']}: {$row['wo_count']} work orders\n";
        }
    }
    
    if (!$found_data) {
        echo "  (No work orders found)\n";
    }
} catch (Exception $e) {
    echo "  Error: " . $e->getMessage() . "\n";
}

// Show equipment distribution by tenant
echo "\nEquipment by Tenant:\n";
try {
    $query = "SELECT tenant_id, COUNT(*) as eq_count FROM equipment GROUP BY tenant_id ORDER BY tenant_id";
    $result = $connection->query($query);
    $found_data = false;
    
    if ($result) {
        while ($row = $db_type === 'sqlite' ? $result->fetch(PDO::FETCH_ASSOC) : $result->fetch_assoc()) {
            $found_data = true;
            echo "  Tenant {$row['tenant_id']}: {$row['eq_count']} equipment items\n";
        }
    }
    
    if (!$found_data) {
        echo "  (No equipment found)\n";
    }
} catch (Exception $e) {
    echo "  Error: " . $e->getMessage() . "\n";
}

// Show vendors distribution by tenant
echo "\nVendors by Tenant:\n";
try {
    $query = "SELECT tenant_id, COUNT(*) as vendor_count FROM vendors GROUP BY tenant_id ORDER BY tenant_id";
    $result = $connection->query($query);
    $found_data = false;
    
    if ($result) {
        while ($row = $db_type === 'sqlite' ? $result->fetch(PDO::FETCH_ASSOC) : $result->fetch_assoc()) {
            $found_data = true;
            echo "  Tenant {$row['tenant_id']}: {$row['vendor_count']} vendors\n";
        }
    }
    
    if (!$found_data) {
        echo "  (No vendors found)\n";
    }
} catch (Exception $e) {
    echo "  Error: " . $e->getMessage() . "\n";
}

echo "\n\n";
echo "======================================================\n";

if ($all_good) {
    echo "✓ AUDIT PASSED - All critical tables configured\n";
} else {
    echo "✗ AUDIT FAILED - Some tables need configuration\n";
}

echo "======================================================\n";

echo "\n\nRECOMMENDATIONS\n";
echo "-------------------------------------------\n";
echo "1. When creating new items (equipment, work orders, vendors):\n";
echo "   - Always ensure tenant_id is set to current session tenant\n";
echo "   - Use apply_tenant_filter() on all SELECT queries\n\n";
echo "2. When querying data:\n";
echo "   - Use safe_query_row() or safe_query_all() functions\n";
echo "   - These automatically apply tenant filtering\n\n";
echo "3. When adding new tables:\n";
echo "   - Add tenant_id INTEGER NOT NULL DEFAULT 1 column\n";
echo "   - Add table name to apply_tenant_filter() function\n";
echo "   - Include tenant_id in all INSERT statements\n\n";
echo "4. To create a new company:\n";
echo "   - Use admin_roles.php to assign a new tenant_id\n";
echo "   - Each company should have separate tenant_id\n";
echo "   - Existing records assigned to tenant 1\n\n";

?>
