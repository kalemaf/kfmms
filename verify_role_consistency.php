<?php
/**
 * Verify Role Consistency
 * Compares roles in database with hardcoded roles in UI
 */
require 'config.inc.php';

echo "Role Consistency Verification\n";
echo "==============================\n\n";

// Get roles from database
$db_roles = $connection->query('SELECT role_name FROM roles ORDER BY role_name')->fetchAll(PDO::FETCH_ASSOC);
$db_role_names = array_map(function($r) { return strtolower(trim($r['role_name'])); }, $db_roles);
sort($db_role_names);

// Expected roles in UI
$ui_roles = ['operator', 'technician', 'supervisor', 'maintenance manager', 'admin'];
$ui_role_names = array_map(function($r) { return strtolower(trim($r)); }, $ui_roles);
sort($ui_role_names);

echo "Roles in Database:\n";
foreach ($db_role_names as $role) {
    echo "  - $role\n";
}

echo "\nRoles in UI (expected):\n";
foreach ($ui_role_names as $role) {
    echo "  - $role\n";
}

echo "\n";
if ($db_role_names === $ui_role_names) {
    echo "✅ SUCCESS: Database and UI roles are consistent!\n";
} else {
    echo "❌ ERROR: Roles are NOT consistent!\n";
    $missing_in_db = array_diff($ui_role_names, $db_role_names);
    $extra_in_db = array_diff($db_role_names, $ui_role_names);
    
    if (!empty($missing_in_db)) {
        echo "\nMissing in database:\n";
        foreach ($missing_in_db as $role) {
            echo "  - $role\n";
        }
    }
    
    if (!empty($extra_in_db)) {
        echo "\nExtra in database:\n";
        foreach ($extra_in_db as $role) {
            echo "  - $role\n";
        }
    }
}

// Verify users table
echo "\nVerifying users' assigned roles:\n";
$user_roles = $connection->query('SELECT DISTINCT role FROM users ORDER BY role')->fetchAll(PDO::FETCH_ASSOC);
$invalid_user_roles = [];
foreach ($user_roles as $row) {
    $role = strtolower(trim($row['role']));
    if (!in_array($role, $ui_role_names)) {
        $invalid_user_roles[] = $row['role'];
    }
}

if (empty($invalid_user_roles)) {
    echo "✅ All users have valid roles\n";
} else {
    echo "❌ Users have invalid roles:\n";
    foreach ($invalid_user_roles as $role) {
        echo "  - $role\n";
    }
}

echo "\n";
echo "Verification Complete!\n";
?>
