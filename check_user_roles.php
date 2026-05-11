<?php
require 'config.inc.php';

// Check what roles are assigned to users
$user_roles = $connection->query('SELECT DISTINCT role FROM users ORDER BY role')->fetchAll(PDO::FETCH_ASSOC);

echo "Roles assigned to users:\n";
echo "=========================\n";
foreach ($user_roles as $row) {
    echo "- {$row['role']}\n";
}

// Count users per role
echo "\nUser count per role:\n";
echo "====================\n";
$role_counts = $connection->query('SELECT role, COUNT(*) as count FROM users GROUP BY role ORDER BY role')->fetchAll(PDO::FETCH_ASSOC);
foreach ($role_counts as $row) {
    echo "{$row['role']}: {$row['count']} users\n";
}
?>
