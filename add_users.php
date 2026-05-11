<?php
// Add default users to the database
require_once 'config.inc.php';

if (!$connection || $db_error) {
    die("Database connection failed: $db_error\n");
}

$users = [
    ['username' => 'admin', 'password' => 'Kalemaf123@@', 'role' => 'admin', 'email' => 'admin@cmms.local'],
    ['username' => 'manager', 'password' => 'Kalemaf123@@', 'role' => 'manager', 'email' => 'manager@cmms.local'],
    ['username' => 'tech', 'password' => 'tech123', 'role' => 'technician', 'email' => 'tech@cmms.local'],
];

foreach ($users as $user) {
    $password_hash = password_hash($user['password'], PASSWORD_DEFAULT);
    $stmt = $connection->prepare("INSERT INTO users (username, email, password_hash, role) VALUES (?, ?, ?, ?) ON DUPLICATE KEY UPDATE password_hash = VALUES(password_hash)");
    $stmt->bind_param("ssss", $user['username'], $user['email'], $password_hash, $user['role']);
    $stmt->execute();
    echo "Added/Updated user: {$user['username']}\n";
}

echo "Users added successfully!\n";
?>