<?php
/**
 * COMPLETE SYSTEM FLOW TEST
 * Test: Company Creation → User Creation → Login Attempt
 */

require_once 'config.inc.php';
session_save_path($session_save_path);
session_start();

echo "===============================================================================\n";
echo "                    COMPLETE KFMMS FLOW TEST\n";
echo "                   Company → User → Login\n";
echo "===============================================================================\n\n";

if (!isset($connection)) {
    echo "ERROR: Database not connected\n";
    exit(1);
}

echo "Step 0: Check Current Users in Database\n";
echo "=========================================\n";
$query = "SELECT COUNT(*) as total FROM users";
$result = $connection->query($query);
if ($result) {
    $row = $result->fetch_assoc();
    echo "Total users in system: " . $row['total'] . "\n";
}

$recent = $connection->query("SELECT user_id, email, username, company_id, is_active, is_locked, created_at FROM users ORDER BY user_id DESC LIMIT 5");
if ($recent) {
    echo "\nLast 5 users created:\n";
    while ($user = $recent->fetch_assoc()) {
        echo "  - ID: " . $user['user_id'] . ", Email: " . $user['email'] . ", Company: " . $user['company_id'] . ", Active: " . $user['is_active'] . ", Locked: " . $user['is_locked'] . "\n";
    }
}

echo "\n\nStep 1: Check joi@gmail.com in Database\n";
echo "========================================\n";
$stmt = $connection->prepare("SELECT user_id, username, email, is_active, is_locked, company_id, password_hash, must_change_password FROM users WHERE email = ? LIMIT 1");
$stmt->bind_param('s', $joi_email);
$joi_email = 'joi@gmail.com';
$stmt->execute();
$result = $stmt->get_result();

if ($joi_row = $result->fetch_assoc()) {
    echo "✓ User joi@gmail.com FOUND in database\n";
    echo "  User ID: " . $joi_row['user_id'] . "\n";
    echo "  Username: " . $joi_row['username'] . "\n";
    echo "  Email: " . $joi_row['email'] . "\n";
    echo "  Company ID: " . $joi_row['company_id'] . "\n";
    echo "  Is Active: " . ($joi_row['is_active'] ? 'Yes' : 'No') . "\n";
    echo "  Is Locked: " . ($joi_row['is_locked'] ? 'Yes' : 'No') . "\n";
    echo "  Must Change Password: " . ($joi_row['must_change_password'] ? 'Yes' : 'No') . "\n";
    echo "  Password Hash: " . substr($joi_row['password_hash'], 0, 40) . "...\n";
    
    echo "\n  Testing password verification:\n";
    $test_pwd = 'Kalemaf123@@';
    if (password_verify($test_pwd, $joi_row['password_hash'])) {
        echo "  ✓ Password 'Kalemaf123@@' VERIFIED - Hash matches!\n";
    } else {
        echo "  ✗ Password 'Kalemaf123@@' FAILED - Hash doesn't match\n";
        echo "    Trying temporary_password column...\n";
        
        // Check if there's a temporary_password column
        $temp_result = $connection->query("SELECT temporary_password FROM users WHERE user_id = " . $joi_row['user_id']);
        if ($temp_result && $temp_row = $temp_result->fetch_assoc()) {
            echo "    Temporary password in DB: " . $temp_row['temporary_password'] . "\n";
            if (password_verify($temp_row['temporary_password'], $joi_row['password_hash'])) {
                echo "    ✓ Temporary password matches hash\n";
            } else {
                echo "    ✗ Temporary password also doesn't match hash\n";
            }
        }
    }
    
    // Check company status
    echo "\n  Checking company status:\n";
    $company_stmt = $connection->prepare("SELECT system_locked, lock_reason FROM system_control WHERE company_id = ? LIMIT 1");
    $company_stmt->bind_param('i', $joi_row['company_id']);
    $company_stmt->execute();
    $company_result = $company_stmt->get_result();
    if ($company_row = $company_result->fetch_assoc()) {
        echo "  Company system_locked: " . ($company_row['system_locked'] ? 'Yes (LOCKED)' : 'No') . "\n";
        if ($company_row['system_locked']) {
            echo "  Lock reason: " . $company_row['lock_reason'] . "\n";
        }
    }
    
} else {
    echo "✗ User joi@gmail.com NOT FOUND in database\n";
    echo "  Searching for similar users...\n";
    
    $search = $connection->query("SELECT user_id, email, username FROM users WHERE email LIKE '%joi%' OR username LIKE '%joi%'");
    if ($search && $search->num_rows > 0) {
        echo "  Found similar users:\n";
        while ($user = $search->fetch_assoc()) {
            echo "    - " . $user['email'] . " (username: " . $user['username'] . ")\n";
        }
    } else {
        echo "  No similar users found\n";
    }
}

echo "\n\nStep 2: Simulate Login Form Submission\n";
echo "=======================================\n";

$_SERVER['REQUEST_METHOD'] = 'POST';
$_POST['username'] = 'joi@gmail.com';
$_POST['password'] = 'Kalemaf123@@';
$_POST['csrf_token'] = generate_csrf_token();

echo "Form data:\n";
echo "  Username/Email: " . $_POST['username'] . "\n";
echo "  Password: " . $_POST['password'] . "\n";
echo "  CSRF Token: Valid\n\n";

$error = '';
$login_attempts = $_SESSION['login_attempts'] ?? 0;
$login_lock_until = $_SESSION['login_lock_until'] ?? 0;
$now = time();

echo "Step 3: Check Login Lockout\n";
echo "============================\n";
if ($login_lock_until > $now) {
    $waitMinutes = ceil(($login_lock_until - $now) / 60);
    echo "✗ Account locked from too many failed attempts\n";
    echo "  Wait time: " . $waitMinutes . " minute(s)\n";
    $error = "Too many failed login attempts.";
} else {
    echo "✓ No lockout in effect\n\n";
    
    echo "Step 4: Verify CSRF Token\n";
    echo "==========================\n";
    if (verify_csrf_token($_POST['csrf_token'])) {
        echo "✓ CSRF token valid\n\n";
        
        echo "Step 5: Query Database for User\n";
        echo "================================\n";
        $username_input = trim($_POST['username']);
        $password_input = trim($_POST['password']);
        
        $select_cols = "user_id, username, email, password_hash, role, is_active, is_locked, company_id, must_change_password";
        $login_stmt = $connection->prepare("SELECT $select_cols FROM users WHERE email = ? OR username = ? LIMIT 1");
        $login_stmt->bind_param('ss', $username_input, $username_input);
        $login_stmt->execute();
        $login_result = $login_stmt->get_result();
        
        if ($login_row = $login_result->fetch_assoc()) {
            echo "✓ User found: " . $login_row['email'] . "\n\n";
            
            echo "Step 6: Check Account Status\n";
            echo "=============================\n";
            if (!$login_row['is_active']) {
                echo "✗ Account not active - LOGIN FAILS\n";
                $error = "Account not active";
            } else if ($login_row['is_locked']) {
                echo "✗ Account locked - LOGIN FAILS\n";
                $error = "Account locked";
            } else {
                echo "✓ Account is active and not locked\n\n";
                
                echo "Step 7: Verify Password\n";
                echo "=======================\n";
                echo "Testing: password_verify('Kalemaf123@@', hash)\n";
                echo "Hash: " . substr($login_row['password_hash'], 0, 40) . "...\n";
                
                if (password_verify($password_input, $login_row['password_hash'])) {
                    echo "✓ PASSWORD VERIFIED - Login succeeds!\n\n";
                    
                    echo "Step 8: Set Session Variables\n";
                    echo "==============================\n";
                    $_SESSION['user'] = $login_row['username'];
                    $_SESSION['user_id'] = $login_row['user_id'];
                    $_SESSION['email'] = $login_row['email'];
                    $_SESSION['company_id'] = $login_row['company_id'];
                    $_SESSION['must_change_password'] = !empty($login_row['must_change_password']) ? 1 : 0;
                    echo "✓ Session variables set\n";
                    echo "  user = " . $_SESSION['user'] . "\n";
                    echo "  user_id = " . $_SESSION['user_id'] . "\n";
                    echo "  company_id = " . $_SESSION['company_id'] . "\n";
                    echo "  must_change_password = " . $_SESSION['must_change_password'] . "\n\n";
                    
                    echo "Step 9: Determine Redirect\n";
                    echo "==========================\n";
                    if (!empty($login_row['must_change_password'])) {
                        echo "→ Redirect to: force_password_change.php\n";
                    } else {
                        echo "→ Redirect to: index.php\n";
                    }
                    
                    echo "\n✓✓✓ LOGIN TEST PASSED ✓✓✓\n";
                    
                } else {
                    echo "✗ PASSWORD VERIFICATION FAILED\n";
                    echo "  Hash doesn't match password 'Kalemaf123@@'\n";
                    $error = "Invalid password";
                }
            }
        } else {
            echo "✗ User not found in database\n";
            echo "  Searched for: email='" . $username_input . "' OR username='" . $username_input . "'\n";
            $error = "Invalid username or password";
        }
    } else {
        echo "✗ CSRF token invalid\n";
        $error = "Invalid request";
    }
}

if (!empty($error)) {
    echo "\n\n❌❌❌ LOGIN WOULD FAIL ❌❌❌\n";
    echo "Error: " . $error . "\n";
}

echo "\n===============================================================================\n";
echo "                            END OF TEST\n";
echo "===============================================================================\n";
?>