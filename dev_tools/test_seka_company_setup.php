<?php
/**
 * SYSTEMATIC TEST: Create Seka Company, Setup User, Verify Lifecycle Isolation
 * Step-by-step debugging to identify where tenant_id breaks
 */

require_once 'config.inc.php';
require_once 'common.inc.php';

echo "\n╔════════════════════════════════════════════════════════════════════════════╗\n";
echo "║              SEKA COMPANY CREATION & LIFECYCLE ISOLATION TEST             ║\n";
echo "╚════════════════════════════════════════════════════════════════════════════╝\n\n";

// Step 1: Check if seka company exists
echo "STEP 1: Check for existing 'seka' company\n";
echo "─────────────────────────────────────────\n";

$checkRes = $connection->query("SELECT id, company_name, tenant_id FROM companies WHERE company_name LIKE '%seka%' OR company_name LIKE '%Seka%'");
if ($checkRes && $checkRes->num_rows > 0) {
    $existing = $checkRes->fetch_assoc();
    echo "✓ Found existing company: ID=" . $existing['id'] . ", Name=" . $existing['company_name'] . ", tenant_id=" . $existing['tenant_id'] . "\n";
    $seka_company_id = $existing['id'];
} else {
    echo "✗ No 'seka' company found - will create new one\n";
    $seka_company_id = null;
}

// Step 2: Create seka company if it doesn't exist
echo "\nSTEP 2: Create 'seka' company\n";
echo "────────────────────────────\n";

if ($seka_company_id === null) {
    $stmt = $connection->prepare("INSERT INTO companies (company_name, company_email, company_phone, company_address, created_at) VALUES (?, ?, ?, ?, NOW())");
    if ($stmt) {
        $email = "info@seka.local";
        $phone = "+256-000-0000";
        $address = "Seka Company Address";
        $stmt->bind_param("ssss", $name, $email, $phone, $address);
        $name = "Seka Enterprise";
        if ($stmt->execute()) {
            $seka_company_id = $connection->insert_id;
            echo "✓ Created company 'Seka Enterprise' with ID=" . $seka_company_id . "\n";
        } else {
            echo "✗ Failed to create company: " . $stmt->error . "\n";
            exit(1);
        }
    }
}

// Step 3: Create system_control record for seka company
echo "\nSTEP 3: Ensure system_control record for seka company\n";
echo "────────────────────────────────────────────────────\n";

$checkSys = $connection->query("SELECT id FROM system_control WHERE company_id = {$seka_company_id}");
if ($checkSys && $checkSys->num_rows > 0) {
    echo "✓ system_control record already exists for seka company\n";
} else {
    echo "⚠ Creating system_control record...\n";
    $stmt = $connection->prepare("INSERT INTO system_control (company_id, tenant_id, feature_flag, feature_name) VALUES (?, ?, 1, 'license_ok')");
    if ($stmt) {
        $stmt->bind_param("ii", $seka_company_id, $seka_company_id);
        $stmt->execute();
        echo "✓ Created system_control record\n";
    }
}

// Step 4: Check/Create user seka@gmail.com
echo "\nSTEP 4: Create user 'seka@gmail.com'\n";
echo "────────────────────────────────────\n";

$userEmail = "seka@gmail.com";
$userCheck = $connection->query("SELECT user_id, username, company_id, tenant_id FROM users WHERE email = '{$userEmail}'");
if ($userCheck && $userCheck->num_rows > 0) {
    $user = $userCheck->fetch_assoc();
    echo "✓ User exists: user_id=" . $user['user_id'] . ", company_id=" . $user['company_id'] . ", tenant_id=" . $user['tenant_id'] . "\n";
    
    if ($user['company_id'] != $seka_company_id || $user['tenant_id'] != $seka_company_id) {
        echo "⚠ User company/tenant mismatch! Updating...\n";
        $stmt = $connection->prepare("UPDATE users SET company_id = ?, tenant_id = ? WHERE user_id = ?");
        $stmt->bind_param("iii", $seka_company_id, $seka_company_id, $user['user_id']);
        $stmt->execute();
        echo "✓ Updated user company_id and tenant_id to " . $seka_company_id . "\n";
    }
} else {
    echo "✗ User doesn't exist - creating...\n";
    $stmt = $connection->prepare("INSERT INTO users (username, password, email, company_id, tenant_id, role, is_active) VALUES (?, ?, ?, ?, ?, 'user', 1)");
    if ($stmt) {
        $username = "seka";
        $password = password_hash("seka123", PASSWORD_DEFAULT);
        $role = "user";
        $stmt->bind_param("sssii", $username, $password, $userEmail, $seka_company_id, $seka_company_id);
        if ($stmt->execute()) {
            $seka_user_id = $connection->insert_id;
            echo "✓ Created user 'seka' with user_id=" . $seka_user_id . ", company_id=" . $seka_company_id . ", tenant_id=" . $seka_company_id . "\n";
        } else {
            echo "✗ Failed to create user: " . $stmt->error . "\n";
        }
    }
}

// Step 5: Verify database state
echo "\nSTEP 5: Verify database state for seka company\n";
echo "──────────────────────────────────────────────\n";

$stmt = $connection->prepare("SELECT user_id, username, email, company_id, tenant_id FROM users WHERE company_id = ?");
$stmt->bind_param("i", $seka_company_id);
$stmt->execute();
$result = $stmt->get_result();

echo "Users in seka company (company_id=$seka_company_id):\n";
while ($row = $result->fetch_assoc()) {
    echo "  - user_id=" . $row['user_id'] . ", username=" . $row['username'] . ", email=" . $row['email'];
    echo ", company_id=" . $row['company_id'] . ", tenant_id=" . $row['tenant_id'];
    if ($row['company_id'] == $row['tenant_id']) {
        echo " ✓\n";
    } else {
        echo " ✗ MISMATCH!\n";
    }
}

// Step 6: Simulate login and check lifecycle queries
echo "\nSTEP 6: Simulate login as seka user\n";
echo "───────────────────────────────────\n";

$_SESSION['tenant_id'] = $seka_company_id;
$_SESSION['company_id'] = $seka_company_id;
$_SESSION['user_id'] = $seka_company_id; // placeholder
echo "✓ Session set: tenant_id=" . $_SESSION['tenant_id'] . ", company_id=" . $_SESSION['company_id'] . "\n";

// Step 7: Test lifecycle analytics queries for seka
echo "\nSTEP 7: Test lifecycle_analytics_impl.php queries for seka (tenant_id=$seka_company_id)\n";
echo "────────────────────────────────────────────────────────────────────────────────────\n";

$from = date('Y-m-d', strtotime('-30 days'));
$to = date('Y-m-d');

// Test parts_master count
$res = $connection->query(apply_tenant_filter("SELECT COUNT(*) as cnt FROM parts_master WHERE is_active = 1"));
if ($res && ($row = $res->fetch_assoc())) {
    $seka_parts = $row['cnt'];
    echo "Parts Master (tenant_id=$seka_company_id): " . $seka_parts . " records\n";
} else {
    echo "✗ Error querying parts_master\n";
}

// Test equipment
$res = $connection->query(apply_tenant_filter("SELECT COUNT(*) as cnt FROM equipment"));
if ($res && ($row = $res->fetch_assoc())) {
    $seka_equip = $row['cnt'];
    echo "Equipment (tenant_id=$seka_company_id): " . $seka_equip . " records\n";
} else {
    echo "✗ Error querying equipment\n";
}

// Test work_orders
$res = $connection->query(apply_tenant_filter("SELECT COUNT(*) as cnt FROM work_orders"));
if ($res && ($row = $res->fetch_assoc())) {
    $seka_wo = $row['cnt'];
    echo "Work Orders (tenant_id=$seka_company_id): " . $seka_wo . " records\n";
} else {
    echo "✗ Error querying work_orders\n";
}

// Test stock_locales
$res = $connection->query(apply_tenant_filter("SELECT COUNT(*) as cnt FROM stock_locales"));
if ($res && ($row = $res->fetch_assoc())) {
    $seka_stock = $row['cnt'];
    echo "Stock Locales (tenant_id=$seka_company_id): " . $seka_stock . " records\n";
} else {
    echo "✗ Error querying stock_locales\n";
}

// Step 8: Check for unfiltered queries showing data
echo "\nSTEP 8: Direct query to show all data (ignoring tenant_id)\n";
echo "──────────────────────────────────────────────────────────\n";

$res = $connection->query("SELECT COUNT(*) as cnt, MAX(tenant_id) as max_tenant FROM parts_master");
if ($res && ($row = $res->fetch_assoc())) {
    echo "Total parts_master in database (ALL tenants): " . $row['cnt'];
    echo " (max tenant_id: " . $row['max_tenant'] . ")\n";
}

$res = $connection->query("SELECT COUNT(*) as cnt, MAX(tenant_id) as max_tenant FROM work_orders");
if ($res && ($row = $res->fetch_assoc())) {
    echo "Total work_orders in database (ALL tenants): " . $row['cnt'];
    echo " (max tenant_id: " . $row['max_tenant'] . ")\n";
}

echo "\n╔════════════════════════════════════════════════════════════════════════════╗\n";
echo "║                          TEST COMPLETE                                    ║\n";
echo "╚════════════════════════════════════════════════════════════════════════════╝\n\n";

echo "SEKA COMPANY DETAILS:\n";
echo "  Company ID: $seka_company_id\n";
echo "  Tenant ID: $seka_company_id\n";
echo "  Email: seka@gmail.com\n";
echo "  User: seka\n\n";

?>
