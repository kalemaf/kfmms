#!/usr/bin/env php
<?php
/**
 * License Key Generation Verification Script
 * Run this to verify the fix is working properly
 */

require_once __DIR__ . '/config.inc.php';

echo "\n" . str_repeat("=", 70) . "\n";
echo "LICENSE KEY GENERATION - VERIFICATION SCRIPT\n";
echo str_repeat("=", 70) . "\n\n";

// Check if database is available
if (!$db_available) {
    echo "❌ ERROR: Database connection not available\n";
    echo "   Reason: $db_error\n";
    exit(1);
}

echo "✅ Database connection: ACTIVE\n";
echo "   Type: $db_type\n";

// Test 1: Verify tables exist
echo "\n" . str_repeat("-", 70) . "\n";
echo "TEST 1: Verify Required Tables Exist\n";
echo str_repeat("-", 70) . "\n";

$tables = ['companies', 'company_licenses', 'system_control'];
$all_exist = true;

foreach ($tables as $table) {
    if ($db_type === 'sqlite') {
        $result = $connection->query("SELECT name FROM sqlite_master WHERE type='table' AND name='$table'");
        $exists = $result->fetch(PDO::FETCH_ASSOC) !== false;
    } else {
        $result = $connection->query("SHOW TABLES LIKE '$table'");
        $exists = $result->num_rows > 0;
    }
    
    $status = $exists ? "✅ EXISTS" : "❌ MISSING";
    echo "  $table: $status\n";
    if (!$exists) $all_exist = false;
}

if (!$all_exist) {
    echo "\n⚠️  Some tables are missing. Run: php config.inc.php\n";
}

// Test 2: Count existing companies
echo "\n" . str_repeat("-", 70) . "\n";
echo "TEST 2: Existing Companies\n";
echo str_repeat("-", 70) . "\n";

try {
    $result = $connection->query("SELECT COUNT(*) as count FROM companies");
    if ($db_type === 'sqlite') {
        $row = $result->fetch(PDO::FETCH_ASSOC);
    } else {
        $row = $result->fetch_assoc();
    }
    $company_count = $row['count'] ?? 0;
    echo "  Total companies: $company_count\n";
} catch (Exception $e) {
    echo "  ❌ Error querying companies: " . $e->getMessage() . "\n";
    $company_count = 0;
}

// Test 3: Check for companies without licenses
echo "\n" . str_repeat("-", 70) . "\n";
echo "TEST 3: Companies Without Licenses\n";
echo str_repeat("-", 70) . "\n";

try {
    $query = "
        SELECT c.company_id, c.company_name, c.company_email, cl.license_key
        FROM companies c
        LEFT JOIN company_licenses cl ON c.company_id = cl.company_id AND cl.is_active = 1
        WHERE cl.license_id IS NULL
        ORDER BY c.created_at DESC
    ";
    $result = $connection->query($query);
    $missing_licenses = [];
    
    if ($db_type === 'sqlite') {
        while ($row = $result->fetch(PDO::FETCH_ASSOC)) {
            $missing_licenses[] = $row;
        }
    } else {
        while ($row = $result->fetch_assoc()) {
            $missing_licenses[] = $row;
        }
    }
    
    if (empty($missing_licenses)) {
        echo "  ✅ All companies have licenses!\n";
    } else {
        echo "  ⚠️  Found " . count($missing_licenses) . " companies without licenses:\n";
        foreach ($missing_licenses as $company) {
            echo "     - ID: " . $company['company_id'] . 
                 ", Name: " . $company['company_name'] . 
                 ", Email: " . $company['company_email'] . "\n";
        }
    }
} catch (Exception $e) {
    echo "  ⚠️  Error checking licenses: " . $e->getMessage() . "\n";
}

// Test 4: Sample valid companies with licenses
echo "\n" . str_repeat("-", 70) . "\n";
echo "TEST 4: Sample Companies with Valid Licenses\n";
echo str_repeat("-", 70) . "\n";

try {
    $query = "
        SELECT c.company_id, c.company_name, cl.license_key, cl.license_type, cl.purchased_seats, sc.feature_tier
        FROM companies c
        LEFT JOIN company_licenses cl ON c.company_id = cl.company_id AND cl.is_active = 1
        LEFT JOIN system_control sc ON c.company_id = sc.company_id
        WHERE cl.license_id IS NOT NULL
        ORDER BY c.created_at DESC
        LIMIT 5
    ";
    $result = $connection->query($query);
    $samples = [];
    
    if ($db_type === 'sqlite') {
        while ($row = $result->fetch(PDO::FETCH_ASSOC)) {
            $samples[] = $row;
        }
    } else {
        while ($row = $result->fetch_assoc()) {
            $samples[] = $row;
        }
    }
    
    if (empty($samples)) {
        echo "  ℹ️  No companies with licenses yet. Create one to test!\n";
    } else {
        echo "  Found " . count($samples) . " companies with licenses:\n";
        foreach ($samples as $idx => $company) {
            $idx++;
            echo "  \n  $idx. Company: " . $company['company_name'] . "\n";
            echo "     License Key: " . $company['license_key'] . "\n";
            echo "     Type: " . $company['license_type'] . "\n";
            echo "     Seats: " . $company['purchased_seats'] . "\n";
            echo "     Tier: " . $company['feature_tier'] . "\n";
        }
    }
} catch (Exception $e) {
    echo "  ⚠️  Error fetching samples: " . $e->getMessage() . "\n";
}

// Test 5: Test license generation function
echo "\n" . str_repeat("-", 70) . "\n";
echo "TEST 5: License Key Generation\n";
echo str_repeat("-", 70) . "\n";

if (function_exists('generate_license_key')) {
    $test_key = generate_license_key();
    echo "  Generated test key: $test_key\n";
    echo "  Format: " . strlen($test_key) . " characters\n";
    echo "  ✅ License generation function working\n";
} else {
    echo "  ❌ generate_license_key() function not found\n";
}

// Summary
echo "\n" . str_repeat("=", 70) . "\n";
echo "SUMMARY\n";
echo str_repeat("=", 70) . "\n";

if ($all_exist && $db_available) {
    echo "✅ READY: System is ready for company registration\n";
    echo "\nNext steps:\n";
    echo "1. Go to admin_roles.php\n";
    echo "2. Click 'Register New Company' tab\n";
    echo "3. Fill in company details and submit\n";
    echo "4. Verify license key appears\n";
} else {
    echo "❌ ISSUES FOUND: Some tables or connections are missing\n";
    echo "\nPlease:\n";
    echo "1. Check database connection\n";
    echo "2. Restart the application to auto-create tables\n";
    echo "3. Run this script again\n";
}

echo "\n";
?>
