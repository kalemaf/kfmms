<?php
/**
 * Quick Verification Script for License API & SQLite Compatibility
 * Tests all database operations without requiring HTTP context
 */

require_once 'config.inc.php';
require_once 'common.inc.php';

echo "\n" . str_repeat("=", 80);
echo "\nLicense API & SQLite Compatibility Verification";
echo "\n" . str_repeat("=", 80) . "\n";

// Test 1: Database connection
echo "\n[1/5] Testing database connection...\n";
try {
    if ($db_type === 'sqlite') {
        $pdo = new PDO('sqlite:' . $db_file);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $result = $pdo->query("SELECT 1");
        echo "✅ SQLite connected: {$db_file}\n";
        echo "   Database size: " . filesize($db_file) / 1024 . " KB\n";
    } else {
        echo "ℹ️  Using MySQL (DB_TYPE={$db_type})\n";
    }
} catch (Exception $e) {
    echo "❌ Database connection failed: " . $e->getMessage() . "\n";
    exit(1);
}

// Test 2: Verify system_control table
echo "\n[2/5] Verifying system_control table schema...\n";
try {
    $stmt = $connection->prepare("SELECT 1 FROM system_control LIMIT 1");
    if ($stmt) {
        $stmt->execute();
        echo "✅ system_control table exists\n";
        
        // Check columns
        if ($db_type === 'sqlite') {
            $result = $pdo->query("PRAGMA table_info(system_control)");
            $columns = $result->fetchAll(PDO::FETCH_ASSOC);
            $col_names = array_column($columns, 'name');
            
            $required = ['company_id', 'system_locked', 'lock_reason', 'activation_date'];
            foreach ($required as $col) {
                if (in_array($col, $col_names)) {
                    echo "  ✓ Column: {$col}\n";
                } else {
                    echo "  ✗ Missing column: {$col}\n";
                }
            }
        }
    }
} catch (Exception $e) {
    echo "⚠️  system_control table not found: " . $e->getMessage() . "\n";
    echo "   This is expected on first run. Admin_roles.php will create it.\n";
}

// Test 3: Test date function compatibility
echo "\n[3/5] Testing date function compatibility...\n";
$timestamp_sql = get_current_timestamp_sql();
echo "✅ get_current_timestamp_sql() returns: {$timestamp_sql}\n";

try {
    // Build test query
    $test_query = "SELECT {$timestamp_sql} as now_value";
    if ($db_type === 'sqlite') {
        $result = $pdo->query($test_query);
        $row = $result->fetch(PDO::FETCH_ASSOC);
    } else {
        $result = $connection->query($test_query);
        $row = $result->fetch_assoc();
    }
    
    if ($row && !empty($row['now_value'])) {
        echo "✅ Timestamp function works: {$row['now_value']}\n";
    } else {
        echo "⚠️  Could not get timestamp value\n";
    }
} catch (Exception $e) {
    echo "⚠️  Timestamp test error: " . $e->getMessage() . "\n";
}

// Test 4: Verify auth.php company_id check
echo "\n[4/5] Verifying authentication system...\n";
try {
    $check_query = "SELECT user_id FROM users WHERE company_id IS NOT NULL LIMIT 1";
    if ($db_type === 'sqlite') {
        $result = $pdo->query($check_query);
        $row = $result->fetch(PDO::FETCH_ASSOC);
    } else {
        $result = $connection->query($check_query);
        $row = $result->fetch_assoc();
    }
    
    if ($row) {
        echo "✅ Users table has company_id column and data exists\n";
    } else {
        echo "ℹ️  No users with company_id yet (will be set during first login)\n";
    }
} catch (Exception $e) {
    echo "⚠️  Cannot verify users table: " . $e->getMessage() . "\n";
}

// Test 5: Check license_api.php syntax
echo "\n[5/5] Verifying license_api.php compatibility...\n";
$api_file = __DIR__ . '/license_api.php';
if (file_exists($api_file)) {
    $content = file_get_contents($api_file);
    
    // Check for NOW() - should be replaced
    if (preg_match('/NOW\(\)/i', $content)) {
        echo "❌ ERROR: Found NOW() function in license_api.php!\n";
        echo "   This will fail with SQLite. Please check lines with NOW()\n";
        preg_match_all('/NOW\(\)/i', $content, $matches, PREG_OFFSET_CAPTURE);
        foreach ($matches[0] as $match) {
            $line = substr_count(substr($content, 0, $match[1]), "\n") + 1;
            echo "   - Line {$line}: {$match[0]}\n";
        }
    } else {
        echo "✅ No NOW() functions found (proper SQLite compatibility)\n";
    }
    
    // Check for get_current_timestamp_sql
    if (strpos($content, 'get_current_timestamp_sql()') !== false) {
        echo "✅ Using get_current_timestamp_sql() for compatibility\n";
    } else {
        echo "⚠️  get_current_timestamp_sql() not found in license_api.php\n";
    }
    
    // Verify it's valid PHP
    $php_check = shell_exec("php -l {$api_file} 2>&1");
    if (strpos($php_check, 'No syntax errors') !== false) {
        echo "✅ license_api.php has valid PHP syntax\n";
    } else {
        echo "❌ PHP Syntax Error in license_api.php:\n{$php_check}\n";
    }
} else {
    echo "❌ license_api.php not found!\n";
}

// Summary
echo "\n" . str_repeat("=", 80);
echo "\nVerification Complete!\n";
echo "\nNext Steps:\n";
echo "1. Test login with an existing user account\n";
echo "2. Go to admin_roles.php and lock a company\n";
echo "3. Try to activate the locked company\n";
echo "4. Expected result: Success message (no JSON error)\n";
echo "5. Try to login as user from locked company\n";
echo "6. Expected result: Error - 'System is locked'\n";
echo "\nFor detailed logs, check: ";
echo ($db_type === 'sqlite' ? "SQLite database at {$db_file}" : "MySQL database");
echo "\n" . str_repeat("=", 80) . "\n";

exit(0);
?>
