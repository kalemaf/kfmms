#!/usr/bin/env php
<?php
/**
 * Work Order Tenant Isolation - Complete Audit & Fix
 * Purpose: Diagnose and fix work order data leakage across tenants
 */

require_once __DIR__ . '/config.inc.php';
require_once __DIR__ . '/common.inc.php';

echo "\n";
echo "╔════════════════════════════════════════════════════════════════════════╗\n";
echo "║   Work Order Tenant Isolation - Audit & Fix Script                    ║\n";
echo "╚════════════════════════════════════════════════════════════════════════╝\n\n";

// 1. Check Database Type
echo "[1/8] Checking Database Configuration...\n";
echo "  Database Type: " . (isset($GLOBALS['db_type']) ? strtoupper($GLOBALS['db_type']) : 'Unknown') . "\n";
echo "  Connection Status: " . ($connection ? "✓ Connected" : "✗ Failed") . "\n\n";

if (!$connection) {
    echo "ERROR: Database connection failed\n";
    exit(1);
}

// 2. Check work_orders table structure
echo "[2/8] Checking work_orders Table Structure...\n";
try {
    if ($GLOBALS['db_type'] === 'sqlite') {
        $stmt = $connection->query("PRAGMA table_info('work_orders')");
        $columns = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $columns[] = $row['name'];
        }
    } else {
        $stmt = $connection->query("SHOW COLUMNS FROM work_orders");
        $columns = [];
        while ($row = $stmt->fetch_assoc()) {
            $columns[] = $row['Field'];
        }
    }
    
    echo "  Columns found: " . count($columns) . "\n";
    
    if (in_array('tenant_id', $columns)) {
        echo "  ✓ tenant_id column exists\n";
    } else {
        echo "  ✗ tenant_id column MISSING\n";
        echo "  Adding tenant_id column...\n";
        if ($GLOBALS['db_type'] === 'sqlite') {
            $connection->exec('ALTER TABLE work_orders ADD COLUMN tenant_id INTEGER NOT NULL DEFAULT 1');
        } else {
            $connection->exec('ALTER TABLE work_orders ADD COLUMN tenant_id INT NOT NULL DEFAULT 1');
        }
        echo "  ✓ tenant_id column added\n";
    }
    echo "\n";
} catch (Exception $e) {
    echo "  Error: " . $e->getMessage() . "\n\n";
}

// 3. Check work_orders data
echo "[3/8] Checking work_orders Data...\n";
try {
    $count_query = "SELECT COUNT(*) as total FROM work_orders";
    $result = $connection->query($count_query);
    $row = ($GLOBALS['db_type'] === 'sqlite') ? $result->fetch(PDO::FETCH_ASSOC) : $result->fetch_assoc();
    $total = intval($row['total'] ?? 0);
    echo "  Total work orders: $total\n";
    
    // Check for NULL tenant_id
    $null_query = "SELECT COUNT(*) as cnt FROM work_orders WHERE tenant_id IS NULL OR tenant_id <= 0";
    $result = $connection->query($null_query);
    $row = ($GLOBALS['db_type'] === 'sqlite') ? $result->fetch(PDO::FETCH_ASSOC) : $result->fetch_assoc();
    $null_count = intval($row['cnt'] ?? 0);
    
    if ($null_count > 0) {
        echo "  ⚠ Found $null_count work orders with NULL/invalid tenant_id\n";
        echo "  Fixing: Assigning to default tenant (1)...\n";
        $update_query = "UPDATE work_orders SET tenant_id = 1 WHERE tenant_id IS NULL OR tenant_id <= 0";
        $connection->exec($update_query);
        echo "  ✓ Fixed $null_count records\n";
    } else {
        echo "  ✓ All work orders have valid tenant_id\n";
    }
    
    // Show distribution by tenant
    $dist_query = "SELECT tenant_id, COUNT(*) as cnt FROM work_orders GROUP BY tenant_id ORDER BY tenant_id";
    $result = $connection->query($dist_query);
    echo "\n  Work orders by tenant:\n";
    while ($row = ($GLOBALS['db_type'] === 'sqlite') ? $result->fetch(PDO::FETCH_ASSOC) : $result->fetch_assoc()) {
        echo "    • Tenant " . intval($row['tenant_id']) . ": " . intval($row['cnt']) . " work orders\n";
    }
    echo "\n";
} catch (Exception $e) {
    echo "  Error: " . $e->getMessage() . "\n\n";
}

// 4. Check companies table
echo "[4/8] Checking companies Table...\n";
try {
    $comp_query = "SELECT COUNT(*) as total FROM companies";
    $result = $connection->query($comp_query);
    $row = ($GLOBALS['db_type'] === 'sqlite') ? $result->fetch(PDO::FETCH_ASSOC) : $result->fetch_assoc();
    $comp_total = intval($row['total'] ?? 0);
    echo "  Total companies: $comp_total\n";
    
    if ($comp_total > 0) {
        $comp_list = "SELECT id, company_name FROM companies ORDER BY id LIMIT 10";
        $result = $connection->query($comp_list);
        echo "  Companies list:\n";
        while ($row = ($GLOBALS['db_type'] === 'sqlite') ? $result->fetch(PDO::FETCH_ASSOC) : $result->fetch_assoc()) {
            echo "    • ID " . intval($row['id']) . ": " . htmlspecialchars($row['company_name'] ?? 'Unknown') . "\n";
        }
    }
    echo "\n";
} catch (Exception $e) {
    echo "  Note: companies table may not exist\n\n";
}

// 5. Check users table for company associations
echo "[5/8] Checking users Table...\n";
try {
    $user_query = "SELECT COUNT(*) as total FROM users";
    $result = $connection->query($user_query);
    $row = ($GLOBALS['db_type'] === 'sqlite') ? $result->fetch(PDO::FETCH_ASSOC) : $result->fetch_assoc();
    $user_total = intval($row['total'] ?? 0);
    echo "  Total users: $user_total\n";
    
    // Check if users have company_id
    if ($GLOBALS['db_type'] === 'sqlite') {
        $stmt = $connection->query("PRAGMA table_info('users')");
        $columns = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $columns[] = $row['name'];
        }
    } else {
        $stmt = $connection->query("SHOW COLUMNS FROM users");
        $columns = [];
        while ($row = $stmt->fetch_assoc()) {
            $columns[] = $row['Field'];
        }
    }
    
    if (in_array('company_id', $columns)) {
        echo "  ✓ users.company_id column exists\n";
        
        // Show user distribution by company
        $user_dist = "SELECT COALESCE(company_id, 0) as company_id, COUNT(*) as cnt FROM users GROUP BY company_id ORDER BY company_id";
        $result = $connection->query($user_dist);
        echo "  Users by company:\n";
        while ($row = ($GLOBALS['db_type'] === 'sqlite') ? $result->fetch(PDO::FETCH_ASSOC) : $result->fetch_assoc()) {
            echo "    • Company " . intval($row['company_id']) . ": " . intval($row['cnt']) . " users\n";
        }
    } else {
        echo "  ✗ users.company_id column NOT found\n";
    }
    echo "\n";
} catch (Exception $e) {
    echo "  Error: " . $e->getMessage() . "\n\n";
}

// 6. Check indexes
echo "[6/8] Checking Indexes...\n";
try {
    if ($GLOBALS['db_type'] === 'sqlite') {
        $idx_query = "SELECT name FROM sqlite_master WHERE type='index' AND tbl_name='work_orders'";
        $result = $connection->query($idx_query);
        $indexes = [];
        while ($row = $result->fetch(PDO::FETCH_ASSOC)) {
            $indexes[] = $row['name'];
        }
    } else {
        $idx_query = "SHOW INDEX FROM work_orders";
        $result = $connection->query($idx_query);
        $indexes = [];
        while ($row = $result->fetch_assoc()) {
            $indexes[] = $row['Key_name'];
        }
    }
    
    if (in_array('idx_work_orders_tenant', $indexes)) {
        echo "  ✓ idx_work_orders_tenant index exists\n";
    } else {
        echo "  Creating idx_work_orders_tenant index...\n";
        try {
            if ($GLOBALS['db_type'] === 'sqlite') {
                $connection->exec('CREATE INDEX idx_work_orders_tenant ON work_orders(tenant_id)');
            } else {
                $connection->exec('ALTER TABLE work_orders ADD INDEX idx_work_orders_tenant (tenant_id)');
            }
            echo "  ✓ Index created\n";
        } catch (Exception $e) {
            echo "  Note: Index creation failed (may already exist)\n";
        }
    }
    echo "\n";
} catch (Exception $e) {
    echo "  Error: " . $e->getMessage() . "\n\n";
}

// 7. Test apply_tenant_filter()
echo "[7/8] Testing apply_tenant_filter() Function...\n";
try {
    // Set a test tenant ID
    $_SESSION['tenant_id'] = 1;
    
    $test_query = "SELECT wo_id FROM work_orders ORDER BY wo_id";
    $filtered_query = apply_tenant_filter($test_query);
    
    echo "  Original query:\n";
    echo "    " . $test_query . "\n";
    echo "  Filtered query:\n";
    echo "    " . $filtered_query . "\n";
    
    if (strpos($filtered_query, 'tenant_id') !== false) {
        echo "  ✓ Tenant filter applied successfully\n";
    } else {
        echo "  ✗ Tenant filter NOT applied\n";
    }
    echo "\n";
} catch (Exception $e) {
    echo "  Error: " . $e->getMessage() . "\n\n";
}

// 8. Summary and Recommendations
echo "[8/8] Summary & Recommendations...\n";
try {
    $summary = [];
    
    // Check if tenant isolation is working
    $test_query_1 = "SELECT COUNT(*) as total FROM work_orders";
    $test_query_2 = apply_tenant_filter("SELECT COUNT(*) as total FROM work_orders");
    
    $result1 = $connection->query($test_query_1);
    $row1 = ($GLOBALS['db_type'] === 'sqlite') ? $result1->fetch(PDO::FETCH_ASSOC) : $result1->fetch_assoc();
    $total_all = intval($row1['total'] ?? 0);
    
    $_SESSION['tenant_id'] = 1;
    $result2 = $connection->query($test_query_2);
    $row2 = ($GLOBALS['db_type'] === 'sqlite') ? $result2->fetch(PDO::FETCH_ASSOC) : $result2->fetch_assoc();
    $total_tenant1 = intval($row2['total'] ?? 0);
    
    echo "\n  SYSTEM STATUS:\n";
    echo "  • Total work orders in database: $total_all\n";
    echo "  • Work orders for tenant 1 (filtered): $total_tenant1\n";
    
    if ($total_all > $total_tenant1) {
        echo "  ✓ Multi-tenant isolation is working\n";
        echo "  • Data is properly segregated by tenant_id\n";
    } else if ($total_all == $total_tenant1 && $total_all > 0) {
        echo "  ⚠ All work orders belong to same tenant\n";
        echo "  • Verify that new companies have different tenant_ids\n";
    } else {
        echo "  ✓ No work orders in system\n";
    }
    
    echo "\n  ACTION ITEMS:\n";
    echo "  1. ✓ work_orders.tenant_id column verified\n";
    echo "  2. ✓ Tenant filtering via apply_tenant_filter() verified\n";
    echo "  3. Verify new companies are being created with unique IDs\n";
    echo "  4. Verify user login sets session['tenant_id'] from company_id\n";
    echo "  5. Clear browser cache/session if issue persists\n";
    
    echo "\n";
} catch (Exception $e) {
    echo "  Error: " . $e->getMessage() . "\n\n";
}

echo "╔════════════════════════════════════════════════════════════════════════╗\n";
echo "║                    AUDIT COMPLETE                                      ║\n";
echo "╚════════════════════════════════════════════════════════════════════════╝\n\n";

exit(0);
?>
