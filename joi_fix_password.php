<?php
/**
 * JOI@GMAIL.COM - LOGIN FIX TOOL
 * Usage: Run this script to check password and optionally reset it
 */

require_once 'config.inc.php';

echo "═══════════════════════════════════════════════════════════════\n";
echo "          JOI@GMAIL.COM - PASSWORD FIX TOOL\n";
echo "═══════════════════════════════════════════════════════════════\n\n";

$email = 'joi@gmail.com';

// Get current user info
$stmt = $connection->prepare("SELECT user_id, temporary_password, password_hash FROM users WHERE email = ?");
if ($db_type === 'sqlite') {
    $stmt->bindParam(1, $email, PDO::PARAM_STR);
    $stmt->execute();
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
} else {
    $stmt->bind_param('s', $email);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
}

if ($row) {
    echo "✓ User found: " . $email . "\n";
    echo "  User ID: " . $row['user_id'] . "\n";
    echo "  Current Temporary Password: " . $row['temporary_password'] . "\n";
    echo "  Hash: " . substr($row['password_hash'], 0, 40) . "...\n\n";
    
    echo "OPTION 1: LOGIN WITH CURRENT PASSWORD\n";
    echo "────────────────────────────────────────\n";
    echo "Email: " . $email . "\n";
    echo "Password: " . $row['temporary_password'] . "\n";
    echo "→ This password will work for login\n\n";
    
    echo "OPTION 2: RESET PASSWORD TO Kalemaf123@@\n";
    echo "────────────────────────────────────────\n";
    
    if (isset($_GET['reset']) && $_GET['reset'] === 'kalemaf') {
        // Hash Kalemaf123@@
        require_once 'app/PasswordManager.php';
        $new_password = 'Kalemaf123@@';
        $new_hash = PasswordManager::hashPassword($new_password);
        
        // Update database
        $update_stmt = $connection->prepare("UPDATE users SET password_hash = ?, temporary_password = ? WHERE email = ?");
        if ($db_type === 'sqlite') {
            $update_stmt->bindParam(1, $new_hash, PDO::PARAM_STR);
            $update_stmt->bindParam(2, $new_password, PDO::PARAM_STR);
            $update_stmt->bindParam(3, $email, PDO::PARAM_STR);
            $update_stmt->execute();
        } else {
            $update_stmt->bind_param('sss', $new_hash, $new_password, $email);
            $update_stmt->execute();
        }
        
        echo "✓ Password reset successfully!\n";
        echo "  Email: " . $email . "\n";
        echo "  New Password: " . $new_password . "\n";
        echo "  New Hash: " . substr($new_hash, 0, 40) . "...\n\n";
        
        // Verify
        if (password_verify($new_password, $new_hash)) {
            echo "✓ Verification: Password hash is correct\n\n";
            echo "You can now login with:\n";
            echo "  Email: " . $email . "\n";
            echo "  Password: " . $new_password . "\n";
        }
    } else {
        echo "Run with: ?reset=kalemaf\n";
        echo "Example: " . $_SERVER['PHP_SELF'] . "?reset=kalemaf\n\n";
        echo "This will:\n";
        echo "  1. Reset password hash to: Kalemaf123@@\n";
        echo "  2. Update temporary_password field\n";
        echo "  3. Allow login with email/Kalemaf123@@\n";
    }
    
} else {
    echo "✗ User not found\n";
}

echo "\n═══════════════════════════════════════════════════════════════\n";
?>