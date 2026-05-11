<?php
require_once 'config.inc.php';
require_once 'common.inc.php';

echo "=== User Debug Info ===\n\n";

if (isset($connection)) {
    $email = 'yes@gmail.com';
    $stmt = $connection->prepare('SELECT user_id, username, email, is_active, is_locked, must_change_password, password_hash, temporary_password, company_id FROM users WHERE email = ? LIMIT 1');
    $stmt->bind_param('s', $email);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($row = $result->fetch_assoc()) {
        echo "✓ User Found:\n";
        echo "  User ID: " . $row['user_id'] . "\n";
        echo "  Email: " . $row['email'] . "\n";
        echo "  Username: " . $row['username'] . "\n";
        echo "  Active: " . ($row['is_active'] ? 'Yes ✓' : 'No ✗ LOCKED') . "\n";
        echo "  Locked: " . ($row['is_locked'] ? 'Yes ✗ LOCKED' : 'No ✓') . "\n";
        echo "  Must Change Password: " . ($row['must_change_password'] ? 'Yes ✓' : 'No') . "\n";
        echo "  Password Hash: " . (strlen($row['password_hash']) > 0 ? substr($row['password_hash'], 0, 20) . '...' : 'MISSING ✗') . "\n";
        echo "  Temporary Password (plain): " . (!empty($row['temporary_password']) ? $row['temporary_password'] : 'NOT STORED ✗') . "\n";
        echo "  Company ID: " . $row['company_id'] . "\n";
        
        // Test password verification with the stored temporary password
        echo "\n=== Password Verification Test ===\n";
        if (!empty($row['temporary_password'])) {
            $temp_pass = $row['temporary_password'];
            $verify_result = password_verify($temp_pass, $row['password_hash']);
            echo "Temporary password '" . $temp_pass . "' verification: " . ($verify_result ? 'PASS ✓' : 'FAIL ✗') . "\n";
        }
        
        $test_password = 'Test123!@';
        $verify_result = password_verify($test_password, $row['password_hash']);
        echo "Test password '" . $test_password . "' verification: " . ($verify_result ? 'PASS ✓' : 'FAIL ✗') . "\n";
    } else {
        echo "✗ User NOT found in database\n";
    }
    $stmt->close();
} else {
    echo "✗ Database connection failed\n";
}
?>
