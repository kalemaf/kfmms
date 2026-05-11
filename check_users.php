<?php
require_once 'config.inc.php';
global $connection;

$stmt = $connection->query("SELECT user_id, username, email, role, is_active, password_hash FROM users");
while($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    echo "ID: " . $row['user_id'] . "\n";
    echo "Username: " . $row['username'] . "\n";
    echo "Email: " . $row['email'] . "\n";
    echo "Role: " . $row['role'] . "\n";
    echo "Active: " . $row['is_active'] . "\n";
    echo "Hash: " . substr($row['password_hash'], 0, 30) . "...\n";
    echo "---\n";
}