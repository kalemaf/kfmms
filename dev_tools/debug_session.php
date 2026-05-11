<?php
require_once 'config.inc.php';
session_save_path($session_save_path);
require_once 'common.inc.php';

echo "=== Session Debug ===\n\n";

$username = 'yes@gmail.com';
$password = '5%yaqVfA2a9Y';

echo "Testing login process:\n";
echo "  Username: " . $username . "\n";
echo "  Password: " . $password . "\n\n";

// Simulate the exact login process from auth.php
if (isset($connection) && is_object($connection)) {
    $stmt = $connection->prepare("SELECT user_id, username, email, password_hash, role, is_active, is_locked, phone, country_code, company_id, password_change_required, must_change_password FROM users WHERE email = ? OR username = ? LIMIT 1");
    $stmt->bind_param('ss', $username, $username);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($row = $result->fetch_assoc()) {
        echo "User found. Setting session variables:\n";
        
        // Simulate session setup from auth.php
        $_SESSION['user'] = $row['username'];
        $_SESSION['user_id'] = $row['user_id'];
        $_SESSION['email'] = $row['email'];
        $_SESSION['phone'] = $row['phone'];
        $_SESSION['country_code'] = $row['country_code'] ?? '+256';
        $_SESSION['company_id'] = $row['company_id'] ?? 0;
        $_SESSION['tenant_id'] = (int)($row['company_id'] ?? 0);
        $_SESSION['role'] = strtolower($row['role']);
        $_SESSION['group'] = strtolower($row['role']);
        $_SESSION['permissions'] = [];
        $_SESSION['password_change_required'] = !empty($row['password_change_required']) ? 1 : 0;
        $_SESSION['must_change_password'] = !empty($row['must_change_password']) ? 1 : 0;
        
        echo "  \$_SESSION['user'] = " . $_SESSION['user'] . "\n";
        echo "  \$_SESSION['user_id'] = " . $_SESSION['user_id'] . "\n";
        echo "  \$_SESSION['must_change_password'] = " . $_SESSION['must_change_password'] . " (type: " . gettype($_SESSION['must_change_password']) . ")\n";
        echo "  \$row['must_change_password'] = " . $row['must_change_password'] . " (type: " . gettype($row['must_change_password']) . ")\n";
        echo "  !empty(\$row['must_change_password']) = " . (!empty($row['must_change_password']) ? 'true' : 'false') . "\n\n";
        
        echo "Checking redirect conditions:\n";
        if (!empty($row['must_change_password'])) {
            echo "✓ \$row['must_change_password'] is NOT empty\n";
            echo "  Would redirect to: force_password_change.php\n";
        } else {
            echo "✗ \$row['must_change_password'] IS empty\n";
            echo "  Status: " . var_export($row['must_change_password'], true) . "\n";
        }
        
        if (!empty($row['password_change_required'])) {
            echo "✓ \$row['password_change_required'] is NOT empty\n";
            echo "  Would redirect to: change_password.php\n";
        } else {
            echo "✓ \$row['password_change_required'] IS empty (as expected)\n";
        }
    }

    $stmt->close();
}
?>
