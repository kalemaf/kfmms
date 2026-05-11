<?php
require_once 'config.inc.php';

echo "=== COM USER DEBUG ===\n";
echo "Database Type: " . env('DB_TYPE', 'sqlite') . "\n";
echo "Database File: " . (env('DB_TYPE', 'sqlite') === 'sqlite' ? env('DB_FILE', __DIR__ . '/database/maintenix.db') : 'MySQL') . "\n\n";

if (!isset($connection)) {
    echo "ERROR: No database connection\n";
    exit(1);
}

if ($db_error) {
    echo "ERROR: $db_error\n";
    exit(1);
}

echo "✓ Database connected successfully\n\n";

// Check if user exists
$email = 'com@gmail.com';
echo "Searching for user: $email\n\n";

try {
    if ($db_type === 'sqlite') {
        // SQLite query - use correct column names
        $stmt = $connection->prepare("SELECT user_id, username, email, password_hash, temporary_password, must_change_password, is_active, role, company_id FROM users WHERE email = ? OR username = ?");
        if (!$stmt) {
            echo "Prepare error: " . $connection->errorInfo()[2] . "\n";
            exit(1);
        }
        
        $stmt->execute([$email, $email]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($result) {
            echo "✓ User Found in SQLite:\n";
            echo "  User ID: " . $result['user_id'] . "\n";
            echo "  Email: " . $result['email'] . "\n";
            echo "  Username: " . $result['username'] . "\n";
            echo "  Role: " . $result['role'] . "\n";
            echo "  Company ID: " . $result['company_id'] . "\n";
            echo "  Active: " . $result['is_active'] . "\n";
            echo "  Must Change Password: " . $result['must_change_password'] . "\n";
            echo "  Temporary Password: " . $result['temporary_password'] . "\n";
            echo "  Password Hash: " . (empty($result['password_hash']) ? 'EMPTY' : substr($result['password_hash'], 0, 30) . '...') . "\n\n";
            
            // Test password verification
            $test_password = 'Kalemaf123@@';
            echo "Testing password: '$test_password'\n";
            
            if (password_verify($test_password, $result['password_hash'])) {
                echo "✓ Password verification PASSED\n";
            } else {
                echo "✗ Password verification FAILED\n";
                
                if (empty($result['password_hash'])) {
                    echo "  Reason: password_hash is EMPTY\n";
                    echo "  This means the password was never hashed during user creation\n";
                    echo "  ACTION NEEDED: Re-create user with proper password hashing\n";
                } else {
                    echo "  Hash exists but doesn't match\n";
                }
            }
        } else {
            echo "✗ User NOT found\n";
        }
    } else {
        // MySQL query - use correct column names
        $stmt = $connection->prepare("SELECT user_id, username, email, password_hash, temporary_password, must_change_password, is_active, role, company_id FROM users WHERE email = ? OR username = ?");
        $stmt->bind_param("ss", $email, $email);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            $user = $result->fetch_assoc();
            echo "✓ User Found in MySQL:\n";
            echo "  User ID: " . $user['user_id'] . "\n";
            echo "  Email: " . $user['email'] . "\n";
            echo "  Username: " . $user['username'] . "\n";
            echo "  Role: " . $user['role'] . "\n";
            echo "  Company ID: " . $user['company_id'] . "\n";
            echo "  Active: " . $user['is_active'] . "\n";
            echo "  Must Change Password: " . $user['must_change_password'] . "\n";
            echo "  Temporary Password: " . $user['temporary_password'] . "\n";
            echo "  Password Hash: " . (empty($user['password_hash']) ? 'EMPTY' : substr($user['password_hash'], 0, 30) . '...') . "\n\n";
            
            // Test password verification
            $test_password = 'Kalemaf123@@';
            echo "Testing password: '$test_password'\n";
            
            if (password_verify($test_password, $user['password_hash'])) {
                echo "✓ Password verification PASSED\n";
            } else {
                echo "✗ Password verification FAILED\n";
                
                if (empty($user['password_hash'])) {
                    echo "  Reason: password_hash is EMPTY\n";
                    echo "  This means the password was never hashed during user creation\n";
                }
            }
        } else {
            echo "✗ User NOT found\n";
        }
        $stmt->close();
    }
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
    exit(1);
}

// List all users
echo "\n\n=== ALL USERS IN SYSTEM ===\n";
try {
    if ($db_type === 'sqlite') {
        $result = $connection->query("SELECT user_id, email, username, company_id, is_active FROM users ORDER BY user_id DESC LIMIT 20");
        $rows = $result->fetchAll(PDO::FETCH_ASSOC);
        if (count($rows) > 0) {
            foreach ($rows as $row) {
                echo "ID: {$row['user_id']}, Email: {$row['email']}, Username: {$row['username']}, Company: {$row['company_id']}, Active: {$row['is_active']}\n";
            }
        } else {
            echo "No users found\n";
        }
    } else {
        $result = $connection->query("SELECT user_id, email, username, company_id, is_active FROM users ORDER BY user_id DESC LIMIT 20");
        if ($result->num_rows > 0) {
            while ($row = $result->fetch_assoc()) {
                echo "ID: {$row['user_id']}, Email: {$row['email']}, Username: {$row['username']}, Company: {$row['company_id']}, Active: {$row['is_active']}\n";
            }
        } else {
            echo "No users found\n";
        }
    }
} catch (Exception $e) {
    echo "Error listing users: " . $e->getMessage() . "\n";
}
?>
