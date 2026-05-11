<?php
require_once 'config.inc.php';
require_once 'common.inc.php';

echo "=== Login Debug Simulation ===\n\n";

$username = 'yes@gmail.com';
$password = '5%yaqVfA2a9Y';

echo "Testing login with:\n";
echo "  Username: " . $username . "\n";
echo "  Password: " . $password . "\n\n";

if (isset($connection) && is_object($connection)) {
    // Simulate the exact query from auth.php
    $stmt = $connection->prepare("SELECT user_id, username, email, password_hash, role, is_active, is_locked, phone, country_code, company_id, password_change_required, must_change_password FROM users WHERE email = ? OR username = ? LIMIT 1");
    
    if (!$stmt) {
        echo "✗ Prepare failed: " . $connection->error . "\n";
        exit;
    }
    
    $stmt->bind_param('ss', $username, $username);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($row = $result->fetch_assoc()) {
        echo "✓ User found\n\n";
        echo "User Data:\n";
        echo "  user_id: " . $row['user_id'] . "\n";
        echo "  username: " . $row['username'] . "\n";
        echo "  email: " . $row['email'] . "\n";
        echo "  is_active: " . ($row['is_active'] ? 'true' : 'false') . "\n";
        echo "  is_locked: " . ($row['is_locked'] ? 'true' : 'false') . "\n";
        echo "  must_change_password: " . ($row['must_change_password'] ? 'true' : 'false') . "\n";
        echo "  password_change_required: " . ($row['password_change_required'] ? 'true' : 'false') . "\n";
        echo "  role: " . $row['role'] . "\n";
        echo "  company_id: " . $row['company_id'] . "\n\n";

        // Check account status
        if (!$row['is_active'] || $row['is_locked']) {
            echo "✗ Account locked or inactive - would show error\n";
        } else {
            echo "✓ Account is active and not locked\n";
        }

        // Check company lock
        if (!empty($row['company_id'])) {
            echo "\nChecking company lock...\n";
            $ctrl_stmt = $connection->prepare("SELECT system_locked, lock_reason FROM system_control WHERE company_id = ? LIMIT 1");
            if ($ctrl_stmt) {
                $ctrl_stmt->bind_param('i', $row['company_id']);
                $ctrl_stmt->execute();
                $ctrl_result = $ctrl_stmt->get_result();
                if ($ctrl_row = $ctrl_result->fetch_assoc()) {
                    if ($ctrl_row['system_locked']) {
                        echo "✗ Company system is LOCKED: " . $ctrl_row['lock_reason'] . "\n";
                    } else {
                        echo "✓ Company system is not locked\n";
                    }
                } else {
                    echo "⚠ No system_control entry found for company\n";
                }
                $ctrl_stmt->close();
            }
        }

        // Test password verification
        echo "\nPassword Verification:\n";
        $valid = false;
        if (!empty($row['password_hash']) && password_verify($password, $row['password_hash'])) {
            $valid = true;
            echo "✓ Password VERIFIED - Login would succeed\n";
        } else {
            echo "✗ Password FAILED verification\n";
        }

        // Determine redirect
        if ($valid) {
            echo "\nRedirect Logic:\n";
            if (!empty($row['must_change_password'])) {
                echo "→ must_change_password = true → Redirect to: force_password_change.php\n";
            } elseif (!empty($row['password_change_required'])) {
                echo "→ password_change_required = true → Redirect to: change_password.php\n";
            } else {
                echo "→ Normal login → Redirect to: index.php\n";
            }
        }

    } else {
        echo "✗ User NOT found\n";
    }

    $stmt->close();
} else {
    echo "✗ Database connection failed\n";
}
?>
