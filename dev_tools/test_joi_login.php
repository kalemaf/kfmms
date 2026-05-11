<?php
require_once 'config.inc.php';
session_save_path($session_save_path);
session_start();

echo "===============================================\n";
echo "       JOI@GMAIL.COM - COMPLETE LOGIN TEST\n";
echo "===============================================\n\n";

if (!isset($connection)) {
    echo "ERROR: Database not connected\n";
    exit(1);
}

$test_email = 'joi@gmail.com';
$test_password = 'Kalemaf123@@';

echo "Test Credentials:\n";
echo "  Email: " . $test_email . "\n";
echo "  Password: " . $test_password . "\n\n";

// Step 1: Find the user
echo "STEP 1: Query user from database\n";
echo "==================================\n";
$stmt = $connection->prepare("SELECT user_id, username, email, password_hash, is_active, is_locked, company_id, must_change_password FROM users WHERE email = ? OR username = ?");
$stmt->bind_param('ss', $test_email, $test_email);
$stmt->execute();
$result = $stmt->get_result();

if ($row = $result->fetch_assoc()) {
    echo "✓ User found\n";
    echo "  User ID: " . $row['user_id'] . "\n";
    echo "  Email: " . $row['email'] . "\n";
    echo "  Username: " . $row['username'] . "\n";
    echo "  Password Hash: " . substr($row['password_hash'], 0, 50) . "...\n";
    echo "  Is Active: " . ($row['is_active'] ? 'Yes' : 'No') . "\n";
    echo "  Is Locked: " . ($row['is_locked'] ? 'Yes' : 'No') . "\n";
    echo "  Company ID: " . $row['company_id'] . "\n";
    echo "  Must Change Password: " . ($row['must_change_password'] ? 'Yes' : 'No') . "\n\n";
    
    // Step 2: Check account status
    echo "STEP 2: Check account status\n";
    echo "=============================\n";
    if (!$row['is_active']) {
        echo "✗ FAIL: Account is not active\n";
        exit(1);
    }
    if ($row['is_locked']) {
        echo "✗ FAIL: Account is locked\n";
        exit(1);
    }
    echo "✓ Account is active and not locked\n\n";
    
    // Step 3: Check company
    echo "STEP 3: Check company system status\n";
    echo "====================================\n";
    if (!empty($row['company_id'])) {
        $ctrl_stmt = $connection->prepare("SELECT system_locked, lock_reason FROM system_control WHERE company_id = ?");
        $ctrl_stmt->bind_param('i', $row['company_id']);
        $ctrl_stmt->execute();
        $ctrl_result = $ctrl_stmt->get_result();
        if ($ctrl_row = $ctrl_result->fetch_assoc()) {
            if ($ctrl_row['system_locked']) {
                echo "✗ FAIL: Company system is locked\n";
                echo "  Reason: " . $ctrl_row['lock_reason'] . "\n";
                exit(1);
            }
        }
        echo "✓ Company system is not locked\n\n";
    }
    
    // Step 4: Verify password
    echo "STEP 4: Verify password\n";
    echo "========================\n";
    echo "Testing: password_verify('" . $test_password . "', hash)\n";
    echo "Hash (first 50 chars): " . substr($row['password_hash'], 0, 50) . "...\n";
    
    if (password_verify($test_password, $row['password_hash'])) {
        echo "✓ PASSWORD VERIFIED - Match!\n\n";
        
        echo "STEP 5: Set session\n";
        echo "===================\n";
        $_SESSION['user'] = $row['username'];
        $_SESSION['user_id'] = $row['user_id'];
        $_SESSION['email'] = $row['email'];
        $_SESSION['company_id'] = $row['company_id'];
        $_SESSION['must_change_password'] = !empty($row['must_change_password']) ? 1 : 0;
        
        echo "✓ Session set successfully\n";
        echo "  user: " . $_SESSION['user'] . "\n";
        echo "  user_id: " . $_SESSION['user_id'] . "\n";
        echo "  company_id: " . $_SESSION['company_id'] . "\n";
        echo "  must_change_password: " . $_SESSION['must_change_password'] . "\n\n";
        
        echo "STEP 6: Determine redirect\n";
        echo "===========================\n";
        if (!empty($row['must_change_password'])) {
            echo "→ REDIRECT to: force_password_change.php\n";
        } else {
            echo "→ REDIRECT to: index.php\n";
        }
        
        echo "\n✓✓✓ LOGIN TEST PASSED ✓✓✓\n";
        echo "User can login successfully!\n";
        
    } else {
        echo "✗ PASSWORD VERIFICATION FAILED\n";
        echo "  Password doesn't match hash\n";
        echo "  Login would FAIL\n";
        
        // Try to figure out what's in the DB
        echo "\n  Debugging password issue...\n";
        $temp_result = $connection->query("SELECT temporary_password FROM users WHERE user_id = " . $row['user_id']);
        if ($temp_result && $temp_row = $temp_result->fetch_assoc()) {
            echo "  Temporary password in DB: " . $temp_row['temporary_password'] . "\n";
            if (password_verify($temp_row['temporary_password'], $row['password_hash'])) {
                echo "  ✓ Temporary password MATCHES the hash\n";
                echo "    User should login with: " . $temp_row['temporary_password'] . "\n";
            } else {
                echo "  ✗ Temporary password DOESN'T match hash either\n";
                echo "    Hash appears to be wrong\n";
            }
        }
    }
    
} else {
    echo "✗ User not found in database\n";
    echo "  Searched for: email='" . $test_email . "' OR username='" . $test_email . "'\n";
}

echo "\n===============================================\n";
?>