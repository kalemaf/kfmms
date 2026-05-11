<?php
require_once 'config.inc.php';

echo "=== PASSWORD FIX FOR com@gmail.com ===\n\n";

// Hash the password
$password = 'Kalemaf123@@';
$hash = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);

echo "Password: $password\n";
echo "New Hash: $hash\n\n";

// Update the user's password_hash
try {
    if ($db_type === 'sqlite') {
        $stmt = $connection->prepare("UPDATE users SET password_hash = ? WHERE email = ? OR username = ?");
        $stmt->execute([$hash, 'com@gmail.com', 'com']);
        echo "✓ Password updated successfully\n";
        
        // Verify the update
        $stmt = $connection->prepare("SELECT user_id, email, password_hash FROM users WHERE email = ?");
        $stmt->execute(['com@gmail.com']);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        echo "\nVerification:\n";
        echo "  User ID: " . $result['user_id'] . "\n";
        echo "  Email: " . $result['email'] . "\n";
        echo "  New Hash: " . substr($result['password_hash'], 0, 40) . "...\n";
        echo "  Password Verify: " . (password_verify($password, $result['password_hash']) ? 'YES' : 'NO') . "\n";
    } else {
        $stmt = $connection->prepare("UPDATE users SET password_hash = ? WHERE email = ? OR username = ?");
        $stmt->bind_param("sss", $hash, $email1, $username1);
        $email1 = 'com@gmail.com';
        $username1 = 'com';
        $stmt->execute();
        echo "✓ Password updated successfully\n";
    }
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
}
?>
