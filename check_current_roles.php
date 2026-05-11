<?php
require 'config.inc.php';

// Check current roles in database
$roles = $connection->query('SELECT role_id, role_name, role_description FROM roles ORDER BY role_name')->fetchAll(PDO::FETCH_ASSOC);

echo "Current roles in database:\n";
echo "==========================\n";
foreach ($roles as $role) {
    echo "ID: {$role['role_id']}, Name: {$role['role_name']}, Description: {$role['role_description']}\n";
}
?>
