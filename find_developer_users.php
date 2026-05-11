<?php
require 'config.inc.php';

// Find users with developer role
$users = $connection->query("SELECT user_id, username, email, role FROM users WHERE role = 'developer'")->fetchAll(PDO::FETCH_ASSOC);

echo "Users with 'developer' role:\n";
foreach ($users as $user) {
    echo "  - {$user['username']} ({$user['email']})\n";
}
?>
