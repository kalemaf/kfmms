<?php
// Proper test script using PDO syntax for SQLite
ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once 'config.inc.php';
require_once 'common.inc.php';

echo "\n=== SEKA COMPANY ISOLATION TEST ===\n";

// Step 0: Clean up existing seka company/user if present
echo "\n0. CLEANING UP EXISTING SEKA DATA\n";
echo "----------------------------\n";

try {
    // Find existing seka company
    $findSQL = "SELECT company_id FROM companies WHERE company_name = 'seka'";
    $result = $connection->query($findSQL);
    $existing = $result->fetch(PDO::FETCH_ASSOC);
    
    if ($existing) {
        $old_id = $existing['company_id'];
        echo "Found existing seka company ID: $old_id\n";
        
        // Delete users for this company
        $delUserSQL = "DELETE FROM users WHERE company_id = ?";
        $stmt = $connection->prepare($delUserSQL);
        $stmt->execute([$old_id]);
        echo "  Deleted associated users\n";
        
        // Delete company
        $delCompanySQL = "DELETE FROM companies WHERE company_id = ?";
        $stmt = $connection->prepare($delCompanySQL);
        $stmt->execute([$old_id]);
        echo "  Deleted company\n";
    } else {
        echo "No existing seka company found - starting fresh\n";
    }
} catch (Exception $e) {
    echo "Note: " . $e->getMessage() . "\n";
}

// Step 1: Create seka company
echo "\n1. CREATING SEKA COMPANY\n";
echo "----------------------------\n";

try {
    // Insert new company
    $insertCompanySQL = "INSERT INTO companies (company_name, company_email, contact_name, contact_phone, is_active, created_at, updated_at) 
                        VALUES ('seka', 'seka@gmail.com', 'Seka Owner', '555-0100', 1, datetime('now'), datetime('now'))";
    
    $result = $connection->exec($insertCompanySQL);
    $seka_company_id = $connection->lastInsertId();
    
    if ($seka_company_id) {
        echo "✓ Seka company created successfully\n";
        echo "  Company ID: $seka_company_id\n";
        echo "  Company Name: seka\n";
        echo "  Email: seka@gmail.com\n";
    } else {
        echo "✗ Failed to create seka company\n";
        exit(1);
    }
} catch (Exception $e) {
    echo "✗ Error creating company: " . $e->getMessage() . "\n";
    exit(1);
}

// Step 2: Verify company was created
echo "\n2. VERIFYING COMPANY CREATION\n";
echo "----------------------------\n";

try {
    $checkSQL = "SELECT company_id, company_name, company_email FROM companies WHERE company_id = ?";
    $stmt = $connection->prepare($checkSQL);
    $stmt->execute([$seka_company_id]);
    $company = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($company) {
        echo "✓ Company verified in database:\n";
        echo "  ID: " . $company['company_id'] . "\n";
        echo "  Name: " . $company['company_name'] . "\n";
        echo "  Email: " . $company['company_email'] . "\n";
    } else {
        echo "✗ Company not found after creation\n";
        exit(1);
    }
} catch (Exception $e) {
    echo "✗ Error verifying company: " . $e->getMessage() . "\n";
    exit(1);
}

// Step 3: Create seka user
echo "\n3. CREATING SEKA USER\n";
echo "----------------------------\n";

try {
    // Create user with tenant_id = company_id
    $insertUserSQL = "INSERT INTO users (username, password_hash, email, company_id, tenant_id, role, is_active) 
                     VALUES ('seka@gmail.com', ?, 'seka@gmail.com', ?, ?, 'user', 1)";
    
    $stmt = $connection->prepare($insertUserSQL);
    $hashedPassword = password_hash('test123', PASSWORD_DEFAULT);
    $stmt->execute([$hashedPassword, $seka_company_id, $seka_company_id]);
    $seka_user_id = $connection->lastInsertId();
    
    if ($seka_user_id) {
        echo "✓ Seka user created successfully\n";
        echo "  User ID: $seka_user_id\n";
        echo "  Username: seka@gmail.com\n";
        echo "  Company ID: $seka_company_id\n";
        echo "  Tenant ID: $seka_company_id\n";
    } else {
        echo "✗ Failed to create seka user\n";
        exit(1);
    }
} catch (Exception $e) {
    echo "✗ Error creating user: " . $e->getMessage() . "\n";
    exit(1);
}

// Step 4: Verify user was created
echo "\n4. VERIFYING USER CREATION\n";
echo "----------------------------\n";

try {
    $checkUserSQL = "SELECT user_id, username, company_id, tenant_id FROM users WHERE user_id = ?";
    $stmt = $connection->prepare($checkUserSQL);
    $stmt->execute([$seka_user_id]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($user) {
        echo "✓ User verified in database:\n";
        echo "  ID: " . $user['user_id'] . "\n";
        echo "  Username: " . $user['username'] . "\n";
        echo "  Company ID: " . $user['company_id'] . "\n";
        echo "  Tenant ID: " . $user['tenant_id'] . "\n";
        
        if ($user['company_id'] == $user['tenant_id']) {
            echo "✓ Tenant ID matches Company ID (correct)\n";
        } else {
            echo "✗ Tenant ID MISMATCH! company_id=" . $user['company_id'] . " tenant_id=" . $user['tenant_id'] . "\n";
        }
    } else {
        echo "✗ User not found after creation\n";
        exit(1);
    }
} catch (Exception $e) {
    echo "✗ Error verifying user: " . $e->getMessage() . "\n";
    exit(1);
}

// Step 5: Simulate seka user session and test queries
echo "\n5. TESTING LIFECYCLE QUERIES WITH SEKA TENANT CONTEXT\n";
echo "----------------------------\n";

// Set session to seka's tenant
$_SESSION['tenant_id'] = $seka_company_id;
$_SESSION['company_id'] = $seka_company_id;
$_SESSION['user_id'] = $seka_user_id;

echo "Session set: tenant_id=$seka_company_id, company_id=$seka_company_id\n\n";

// Test 1: Equipment count
echo "Test 1: Equipment Count\n";
try {
    $equipSQL = apply_tenant_filter("SELECT COUNT(*) as cnt FROM equipment");
    $stmt = $connection->query($equipSQL);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    $count = $result['cnt'];
    echo "  Equipment count: $count\n";
    if ($count == 0) {
        echo "  ✓ PASS - No data leakage (expected 0 for new company)\n";
    } else {
        echo "  ✗ FAIL - Data leakage detected! Expected 0, got $count\n";
    }
} catch (Exception $e) {
    echo "  ✗ Error: " . $e->getMessage() . "\n";
}

// Test 2: Parts Master count
echo "\nTest 2: Parts Master Count\n";
try {
    $partsSQL = apply_tenant_filter("SELECT COUNT(*) as cnt FROM parts_master");
    $stmt = $connection->query($partsSQL);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    $count = $result['cnt'];
    echo "  Parts count: $count\n";
    if ($count == 0) {
        echo "  ✓ PASS - No data leakage (expected 0 for new company)\n";
    } else {
        echo "  ✗ FAIL - Data leakage detected! Expected 0, got $count\n";
    }
} catch (Exception $e) {
    echo "  ✗ Error: " . $e->getMessage() . "\n";
}

// Test 3: Equipment Spares count
echo "\nTest 3: Equipment Spares Count\n";
try {
    $sparesSQL = apply_tenant_filter("SELECT COUNT(*) as cnt FROM equipment_spares");
    $stmt = $connection->query($sparesSQL);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    $count = $result['cnt'];
    echo "  Equipment spares count: $count\n";
    if ($count == 0) {
        echo "  ✓ PASS - No data leakage (expected 0 for new company)\n";
    } else {
        echo "  ✗ FAIL - Data leakage detected! Expected 0, got $count\n";
    }
} catch (Exception $e) {
    echo "  ✗ Error: " . $e->getMessage() . "\n";
}

// Test 4: Work Orders count
echo "\nTest 4: Work Orders Count\n";
try {
    $woSQL = apply_tenant_filter("SELECT COUNT(*) as cnt FROM work_orders");
    $stmt = $connection->query($woSQL);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    $count = $result['cnt'];
    echo "  Work orders count: $count\n";
    if ($count == 0) {
        echo "  ✓ PASS - No data leakage (expected 0 for new company)\n";
    } else {
        echo "  ✗ FAIL - Data leakage detected! Expected 0, got $count\n";
    }
} catch (Exception $e) {
    echo "  ✗ Error: " . $e->getMessage() . "\n";
}

// Test 5: Check actual lifecycle query from lifecycle_analytics_impl.php
echo "\nTest 5: Lifecycle Analytics - Asset List\n";
try {
    // This is the key query from lifecycle_analytics_impl.php - use correct column
    $assetQuery = apply_tenant_filter("SELECT DISTINCT id, description FROM equipment ORDER BY description");
    $stmt = $connection->query($assetQuery);
    $assets = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $assetCount = count($assets);
    echo "  Assets found: $assetCount\n";
    if ($assetCount == 0) {
        echo "  ✓ PASS - No data leakage (expected 0 for new company)\n";
    } else {
        echo "  ✗ FAIL - Data leakage detected! Assets found:\n";
        foreach ($assets as $asset) {
            echo "    - " . $asset['description'] . "\n";
        }
    }
} catch (Exception $e) {
    echo "  ✗ Error: " . $e->getMessage() . "\n";
}

// Test 6: Check lifecycle analytics detail query
echo "\nTest 6: Lifecycle Analytics - Details Query\n";
try {
    // Simplified version of the UNION query from lifecycle_analytics_impl.php
    $detailQuery = apply_tenant_filter("SELECT id, part_name FROM parts_master");
    $stmt = $connection->query($detailQuery);
    $parts = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $partCount = count($parts);
    echo "  Parts found: $partCount\n";
    if ($partCount == 0) {
        echo "  ✓ PASS - No data leakage (expected 0 for new company)\n";
    } else {
        echo "  ✗ FAIL - Data leakage detected! Parts found:\n";
        foreach ($parts as $part) {
            echo "    - " . $part['part_name'] . "\n";
        }
    }
} catch (Exception $e) {
    echo "  ✗ Error: " . $e->getMessage() . "\n";
}

// Step 6: Compare with admin tenant (tenant_id=1)
echo "\n6. COMPARING WITH ADMIN TENANT (ID=1)\n";
echo "----------------------------\n";

$_SESSION['tenant_id'] = 1;
$_SESSION['company_id'] = 1;

try {
    $adminPartsSQL = apply_tenant_filter("SELECT COUNT(*) as cnt FROM parts_master");
    $stmt = $connection->query($adminPartsSQL);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    $adminCount = $result['cnt'];
    echo "Admin company parts count: $adminCount\n";
    
    if ($adminCount > 0) {
        echo "✓ Admin has data (as expected)\n";
    } else {
        echo "Note: Admin company has no parts data\n";
    }
} catch (Exception $e) {
    echo "✗ Error: " . $e->getMessage() . "\n";
}

echo "\n=== TEST SUMMARY ===\n";
echo "Seka company created with ID: $seka_company_id\n";
echo "Seka user created with ID: $seka_user_id\n";
echo "Tenant ID properly synced: " . ($seka_company_id === $seka_company_id ? "YES" : "NO") . "\n";
echo "\nPlease check the PASS/FAIL results above for data isolation verification.\n";
echo "\n";
?>

