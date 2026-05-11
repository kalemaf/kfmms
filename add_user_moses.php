<?php
require_once 'config.inc.php';

// Generate password hash for 'password123'
$password = 'password123';
$password_hash = password_hash($password, PASSWORD_BCRYPT);

// Insert user for Moses and Brothers Enterprises
$stmt = $connection->prepare('INSERT INTO users (user_id, username, email, password_hash, phone, role, is_active, is_locked, company_id, tenant_id, password_change_required) VALUES (?, ?, ?, ?, ?, ?, 1, 0, ?, ?, 0)');

// Find next user_id
$max_id_result = $connection->query("SELECT MAX(user_id) as max_id FROM users");
$max_id = $max_id_result->fetch(PDO::FETCH_ASSOC)['max_id'] ?? 0;
$new_user_id = $max_id + 1;

$stmt->execute([
    $new_user_id,
    'moses',
    'moses@mosesbrothers.com',
    $password_hash,
    '0772123456',
    'admin',
    13,  // company_id
    13   // tenant_id
]);

echo "Added user_id $new_user_id: moses (company_id=13, tenant_id=13)\n";
echo "Password: $password\n";