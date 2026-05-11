<?php
/**
 * Test Suite: User Creation Flow
 * Tests the complete user authorization and creation process
 */
require 'config.inc.php';
require 'common.inc.php';

echo "\n";
echo "═══════════════════════════════════════════════════════════════\n";
echo "TEST SUITE: User Creation Flow\n";
echo "═══════════════════════════════════════════════════════════════\n";
echo "\n";

$test_results = [];
$test_num = 1;

// Test 1: Database connection
echo "Test $test_num: Database Connection\n";
try {
    $result = $connection->query("SELECT 1")->fetch();
    if ($result) {
        echo "✅ PASS: Database connection successful\n";
        $test_results[] = ['num' => $test_num, 'status' => 'PASS'];
    } else {
        echo "❌ FAIL: Database query returned no results\n";
        $test_results[] = ['num' => $test_num, 'status' => 'FAIL'];
    }
} catch (Exception $e) {
    echo "❌ FAIL: {$e->getMessage()}\n";
    $test_results[] = ['num' => $test_num, 'status' => 'FAIL'];
}
echo "\n";
$test_num++;

// Test 2: Table exists - user_creation_authorizations
echo "Test $test_num: Table user_creation_authorizations Exists\n";
try {
    $result = $connection->query("SELECT name FROM sqlite_master WHERE type='table' AND name='user_creation_authorizations'")->fetch();
    if ($result) {
        echo "✅ PASS: user_creation_authorizations table exists\n";
        $test_results[] = ['num' => $test_num, 'status' => 'PASS'];
    } else {
        echo "❌ FAIL: Table does not exist\n";
        $test_results[] = ['num' => $test_num, 'status' => 'FAIL'];
    }
} catch (Exception $e) {
    echo "❌ FAIL: {$e->getMessage()}\n";
    $test_results[] = ['num' => $test_num, 'status' => 'FAIL'];
}
echo "\n";
$test_num++;

// Test 3: Create authorization record
echo "Test $test_num: Create User Authorization\n";
try {
    $test_auth_code = 'TEST' . substr(md5(time()), 0, 2);
    $test_email = 'test_' . time() . '@example.com';
    $temp_password = password_hash('TempPassword123!', PASSWORD_BCRYPT);
    
    $stmt = $connection->prepare("INSERT INTO user_creation_authorizations (auth_code, pending_username, pending_email, password_hash, temp_password, role, phone, country_code, company_id, requestor_id, requestor_name, created_at, expires_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
    
    $requestor_id = 1;
    $requestor_name = 'admin';
    $now = date('Y-m-d H:i:s');
    $expires_at = date('Y-m-d H:i:s', strtotime('+7 days'));
    $company_id = 1;
    $role = 'technician';
    $phone = '256701234567';
    $country_code = '+256';
    $username = 'test_user_' . time();
    $temp_pass = 'TempPass123!';
    
    $stmt->bindParam(1, $test_auth_code, PDO::PARAM_STR);
    $stmt->bindParam(2, $username, PDO::PARAM_STR);
    $stmt->bindParam(3, $test_email, PDO::PARAM_STR);
    $stmt->bindParam(4, $temp_password, PDO::PARAM_STR);
    $stmt->bindParam(5, $temp_pass, PDO::PARAM_STR);
    $stmt->bindParam(6, $role, PDO::PARAM_STR);
    $stmt->bindParam(7, $phone, PDO::PARAM_STR);
    $stmt->bindParam(8, $country_code, PDO::PARAM_STR);
    $stmt->bindParam(9, $company_id, PDO::PARAM_INT);
    $stmt->bindParam(10, $requestor_id, PDO::PARAM_INT);
    $stmt->bindParam(11, $requestor_name, PDO::PARAM_STR);
    $stmt->bindParam(12, $now, PDO::PARAM_STR);
    $stmt->bindParam(13, $expires_at, PDO::PARAM_STR);
    
    if ($stmt->execute()) {
        echo "✅ PASS: Authorization record created\n";
        echo "   - Auth Code: $test_auth_code\n";
        echo "   - Email: $test_email\n";
        echo "   - Role: $role\n";
        $test_results[] = ['num' => $test_num, 'status' => 'PASS', 'auth_code' => $test_auth_code];
    } else {
        echo "❌ FAIL: Failed to insert authorization record\n";
        $test_results[] = ['num' => $test_num, 'status' => 'FAIL'];
    }
} catch (Exception $e) {
    echo "❌ FAIL: {$e->getMessage()}\n";
    $test_results[] = ['num' => $test_num, 'status' => 'FAIL'];
}
echo "\n";
$test_num++;

// Test 4: Verify authorization exists
echo "Test $test_num: Verify Authorization Exists\n";
try {
    $auth_code_to_check = $test_results[2]['auth_code'] ?? null;
    if (!$auth_code_to_check) {
        echo "⏭️  SKIP: No authorization code from previous test\n";
        $test_results[] = ['num' => $test_num, 'status' => 'SKIP'];
    } else {
        $stmt = $connection->prepare("SELECT auth_id, pending_username, pending_email, role FROM user_creation_authorizations WHERE auth_code = ? AND is_used = 0 LIMIT 1");
        $stmt->bindParam(1, $auth_code_to_check, PDO::PARAM_STR);
        $stmt->execute();
        
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row) {
            echo "✅ PASS: Authorization found and not yet used\n";
            echo "   - Auth ID: {$row['auth_id']}\n";
            echo "   - Username: {$row['pending_username']}\n";
            echo "   - Email: {$row['pending_email']}\n";
            echo "   - Role: {$row['role']}\n";
            $test_results[] = ['num' => $test_num, 'status' => 'PASS', 'username' => $row['pending_username']];
        } else {
            echo "❌ FAIL: Authorization not found or already used\n";
            $test_results[] = ['num' => $test_num, 'status' => 'FAIL'];
        }
    }
} catch (Exception $e) {
    echo "❌ FAIL: {$e->getMessage()}\n";
    $test_results[] = ['num' => $test_num, 'status' => 'FAIL'];
}
echo "\n";
$test_num++;

// Test 5: Simulate user creation from authorization
echo "Test $test_num: Create User from Authorization\n";
try {
    $username = $test_results[3]['username'] ?? null;
    if (!$username) {
        echo "⏭️  SKIP: No username from previous test\n";
        $test_results[] = ['num' => $test_num, 'status' => 'SKIP'];
    } else {
        // Get authorization details
        $auth_stmt = $connection->prepare("SELECT password_hash, role, phone, country_code, company_id FROM user_creation_authorizations WHERE pending_username = ? AND is_used = 0 LIMIT 1");
        $auth_stmt->bindParam(1, $username, PDO::PARAM_STR);
        $auth_stmt->execute();
        $auth_row = $auth_stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($auth_row) {
            // Create user from authorization
            $insert_stmt = $connection->prepare("INSERT INTO users (username, email, password_hash, role, phone, country_code, company_id, whatsapp_enabled, password_change_required) VALUES (?, ?, ?, ?, ?, ?, ?, 1, 1)");
            
            $email = 'test_' . time() . '_created@example.com';
            $insert_stmt->bindParam(1, $username, PDO::PARAM_STR);
            $insert_stmt->bindParam(2, $email, PDO::PARAM_STR);
            $insert_stmt->bindParam(3, $auth_row['password_hash'], PDO::PARAM_STR);
            $insert_stmt->bindParam(4, $auth_row['role'], PDO::PARAM_STR);
            $insert_stmt->bindParam(5, $auth_row['phone'], PDO::PARAM_STR);
            $insert_stmt->bindParam(6, $auth_row['country_code'], PDO::PARAM_STR);
            $insert_stmt->bindParam(7, $auth_row['company_id'], PDO::PARAM_INT);
            
            if ($insert_stmt->execute()) {
                echo "✅ PASS: User created successfully from authorization\n";
                echo "   - Username: $username\n";
                echo "   - Email: $email\n";
                echo "   - Role: {$auth_row['role']}\n";
                $test_results[] = ['num' => $test_num, 'status' => 'PASS'];
            } else {
                echo "❌ FAIL: Failed to create user\n";
                $test_results[] = ['num' => $test_num, 'status' => 'FAIL'];
            }
        } else {
            echo "❌ FAIL: Authorization not found\n";
            $test_results[] = ['num' => $test_num, 'status' => 'FAIL'];
        }
    }
} catch (Exception $e) {
    echo "❌ FAIL: {$e->getMessage()}\n";
    $test_results[] = ['num' => $test_num, 'status' => 'FAIL'];
}
echo "\n";
$test_num++;

// Test 6: Verify user exists
echo "Test $test_num: Verify User Exists in System\n";
try {
    $username = $test_results[3]['username'] ?? null;
    if (!$username) {
        echo "⏭️  SKIP: No username from previous test\n";
        $test_results[] = ['num' => $test_num, 'status' => 'SKIP'];
    } else {
        $stmt = $connection->prepare("SELECT user_id, username, email, role, is_active FROM users WHERE username = ? LIMIT 1");
        $stmt->bindParam(1, $username, PDO::PARAM_STR);
        $stmt->execute();
        
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row && $row['is_active']) {
            echo "✅ PASS: User exists and is active\n";
            echo "   - User ID: {$row['user_id']}\n";
            echo "   - Username: {$row['username']}\n";
            echo "   - Email: {$row['email']}\n";
            echo "   - Role: {$row['role']}\n";
            $test_results[] = ['num' => $test_num, 'status' => 'PASS'];
        } else {
            echo "❌ FAIL: User not found or inactive\n";
            $test_results[] = ['num' => $test_num, 'status' => 'FAIL'];
        }
    }
} catch (Exception $e) {
    echo "❌ FAIL: {$e->getMessage()}\n";
    $test_results[] = ['num' => $test_num, 'status' => 'FAIL'];
}
echo "\n";

// Summary
echo "═══════════════════════════════════════════════════════════════\n";
echo "TEST SUMMARY\n";
echo "═══════════════════════════════════════════════════════════════\n";

$passed = 0;
$failed = 0;
$skipped = 0;

foreach ($test_results as $result) {
    if ($result['status'] === 'PASS') $passed++;
    elseif ($result['status'] === 'FAIL') $failed++;
    else $skipped++;
}

echo "Total Tests: " . count($test_results) . "\n";
echo "✅ Passed:  $passed\n";
echo "❌ Failed:  $failed\n";
echo "⏭️  Skipped: $skipped\n";
echo "\n";

if ($failed === 0) {
    echo "🟢 USER CREATION FLOW: ALL TESTS PASSED\n";
} else {
    echo "🔴 USER CREATION FLOW: SOME TESTS FAILED\n";
}

echo "\n";
?>
