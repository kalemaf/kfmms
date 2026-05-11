<?php
require 'config.inc.php';

// Generate a new password hash for 'admin'
$password = 'admin';
$hash = password_hash($password, PASSWORD_BCRYPT, ['cost' => 10]);

echo "New hash for password 'admin': " . $hash . "\n";

// Update the database
if ($connection) {
    $stmt = $connection->prepare("UPDATE users SET password_hash = ? WHERE username = 'admin'");
    if ($stmt) {
        $stmt->bind_param('s', $hash);
        if ($stmt->execute()) {
            echo "Password updated successfully!\n";
        } else {
            echo "Error: " . $stmt->error . "\n";
        }
    }
}
?>
