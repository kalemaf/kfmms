<?php
require_once 'config.inc.php';
require_once 'common.inc.php';

echo "=== LOGIN SIMULATION TEST ===\n\n";

$username = 'com@gmail.com';
$password = 'Kalemaf123@@';

echo "Testing login with:\n";
echo "  Email/Username: $username\n";
echo "  Password: $password\n\n";

if (!isset($connection)) {
    echo "ERROR: No database connection\n";
    exit(1);
}

try {
    // Simulate the auth.php login flow
    echo "Step 1: Prepare query\n";
    $stmt = $connection->prepare("SELECT user_id, username, email, password_hash, role, is_active, is_locked, phone, country_code, company_id, password_change_required, must_change_password FROM users WHERE email = ? OR username = ? LIMIT 1");
    
    if (!$stmt) {
        echo "ERROR: Failed to prepare statement\n";
        if ($db_type === 'sqlite') {
            echo "PDO Error: " . implode(' ', $connection->errorInfo()) . "\n";
        }
        exit(1);
    }
    
    echo "  ✓ Query prepared\n\n";
    
    echo "Step 2: Bind parameters\n";
    if ($db_type === 'sqlite') {
        // For SQLite PDO wrapper
        $stmt->execute([$username, $username]);
    } else {
        // For MySQL
        $stmt->bind_param('ss', $username, $username);
        $stmt->execute();
    }
    echo "  ✓ Parameters bound and executed\n\n";
    
    echo "Step 3: Fetch result\n";
    if ($db_type === 'sqlite') {
        // PDO doesn't have get_result, fetch directly
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
    } else {
        // MySQL
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
    }
    
    if ($row) {
        echo "  ✓ User found\n";
        echo "    User ID: " . $row['user_id'] . "\n";
        echo "    Username: " . $row['username'] . "\n";
        echo "    Email: " . $row['email'] . "\n";
        echo "    Role: " . $row['role'] . "\n";
        echo "    Active: " . $row['is_active'] . "\n";
        echo "    Locked: " . $row['is_locked'] . "\n";
        echo "    Must Change Password: " . $row['must_change_password'] . "\n\n";
    } else {
        echo "  ✗ User NOT found\n";
        exit(1);
    }
    
    echo "Step 4: Check account status\n";
    if (!$row['is_active'] || $row['is_locked']) {
        echo "  ✗ Account is locked or inactive\n";
        exit(1);
    }
    echo "  ✓ Account is active and not locked\n\n";
    
    echo "Step 5: Verify password\n";
    echo "  Password hash: " . substr($row['password_hash'], 0, 40) . "...\n";
    echo "  Testing: password_verify('" . $password . "', hash)\n";
    
    if (password_verify($password, $row['password_hash'])) {
        echo "  ✓ PASSWORD VERIFICATION PASSED!\n\n";
    } else {
        echo "  ✗ Password verification FAILED\n";
        exit(1);
    }
    
    echo "Step 6: Check what should happen after login\n";
    if (!empty($row['must_change_password'])) {
        echo "  → User should be redirected to: force_password_change.php\n";
        echo "  → Session must_change_password = 1\n";
    } else if (!empty($row['password_change_required'])) {
        echo "  → User should be redirected to: change_password.php\n";
    } else {
        echo "  → User should be redirected to: index.php\n";
    }
    
    echo "\n✓✓✓ LOGIN TEST PASSED - User can now login successfully! ✓✓✓\n";
    
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
    exit(1);
}
?>
