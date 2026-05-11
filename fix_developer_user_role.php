<?php
require 'config.inc.php';

// Update developer user to admin
$stmt = $connection->prepare("UPDATE users SET role = ? WHERE username = ?");
$new_role = 'admin';
$username = 'developer';
$stmt->bindParam(1, $new_role, PDO::PARAM_STR);
$stmt->bindParam(2, $username, PDO::PARAM_STR);

if ($stmt->execute()) {
    echo "✅ Successfully updated user 'developer' role from 'developer' to 'admin'\n";
} else {
    echo "❌ Failed to update user role\n";
}
?>
