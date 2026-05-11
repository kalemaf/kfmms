<?php
/**
 * Standardize Roles Across System
 * Ensures all roles are consistent between database and UI
 */
require 'config.inc.php';

echo "Role Standardization Script\n";
echo "============================\n\n";

// Define standard roles
$standard_roles = [
    ['operator', 'Basic operator access'],
    ['technician', 'Technical role for maintenance work'],
    ['supervisor', 'Team supervisor with approval rights'],
    ['maintenance manager', 'Maintenance department manager'],
    ['admin', 'Full system administrator with all permissions']
];

// Step 1: Get all existing roles
echo "Step 1: Identify roles to delete (duplicates/unwanted)...\n";
$all_roles = $connection->query('SELECT role_id, role_name FROM roles ORDER BY role_name')->fetchAll(PDO::FETCH_ASSOC);

$role_ids_to_keep = [];
$role_ids_to_delete = [];
$role_names_to_delete = [];

foreach ($all_roles as $role) {
    $role_name = strtolower(trim($role['role_name']));
    $is_standard = false;
    
    foreach ($standard_roles as $std_role) {
        if (strtolower(trim($std_role[0])) === $role_name) {
            $is_standard = true;
            $role_ids_to_keep[] = $role['role_id'];
            break;
        }
    }
    
    if (!$is_standard) {
        $role_ids_to_delete[] = $role['role_id'];
        $role_names_to_delete[] = $role['role_name'];
        echo "  - Will DELETE: {$role['role_name']} (ID: {$role['role_id']})\n";
    }
}

// Handle duplicate role IDs (keep oldest, delete duplicates)
echo "\nStep 2: Identify duplicate role names...\n";
$role_name_counts = [];
foreach ($all_roles as $role) {
    $name_lower = strtolower(trim($role['role_name']));
    if (!isset($role_name_counts[$name_lower])) {
        $role_name_counts[$name_lower] = [];
    }
    $role_name_counts[$name_lower][] = $role['role_id'];
}

foreach ($role_name_counts as $name => $ids) {
    if (count($ids) > 1) {
        echo "  - Duplicate role name: '$name' has IDs: " . implode(', ', $ids) . "\n";
        // Keep first (oldest), delete rest
        for ($i = 1; $i < count($ids); $i++) {
            if (!in_array($ids[$i], $role_ids_to_delete)) {
                $role_ids_to_delete[] = $ids[$i];
                echo "    → Will delete duplicate ID: {$ids[$i]}\n";
            }
        }
    }
}

// Step 3: Delete unwanted roles
if (!empty($role_ids_to_delete)) {
    echo "\nStep 3: Deleting unwanted roles...\n";
    foreach ($role_ids_to_delete as $role_id) {
        // Delete from role_permissions first (foreign key)
        $stmt = $connection->prepare("DELETE FROM role_permissions WHERE role_id = ?");
        $stmt->bindParam(1, $role_id, PDO::PARAM_INT);
        if ($stmt->execute()) {
            echo "  ✓ Deleted permissions for role ID: $role_id\n";
        } else {
            echo "  ✗ Failed to delete permissions for role ID: $role_id\n";
        }
        
        // Delete the role
        $stmt = $connection->prepare("DELETE FROM roles WHERE role_id = ?");
        $stmt->bindParam(1, $role_id, PDO::PARAM_INT);
        if ($stmt->execute()) {
            echo "  ✓ Deleted role ID: $role_id\n";
        } else {
            echo "  ✗ Failed to delete role ID: $role_id\n";
        }
    }
}

// Step 4: Ensure all standard roles exist
echo "\nStep 4: Ensuring all standard roles exist...\n";
foreach ($standard_roles as $role) {
    $role_name = $role[0];
    $role_desc = $role[1];
    
    $check = $connection->prepare("SELECT role_id FROM roles WHERE LOWER(role_name) = ?");
    $role_name_lower = strtolower($role_name);
    $check->bindParam(1, $role_name_lower, PDO::PARAM_STR);
    $check->execute();
    
    if ($check->fetch(PDO::FETCH_ASSOC) === false) {
        // Insert if doesn't exist
        $stmt = $connection->prepare("INSERT INTO roles (role_name, role_description) VALUES (?, ?)");
        $stmt->bindParam(1, $role_name, PDO::PARAM_STR);
        $stmt->bindParam(2, $role_desc, PDO::PARAM_STR);
        if ($stmt->execute()) {
            echo "  ✓ Created role: $role_name\n";
        } else {
            echo "  ✗ Failed to create role: $role_name\n";
        }
    } else {
        echo "  ✓ Role exists: $role_name\n";
    }
}

// Step 5: Display final roles
echo "\nStep 5: Final roles in database...\n";
$final_roles = $connection->query('SELECT role_id, role_name, role_description FROM roles ORDER BY role_name')->fetchAll(PDO::FETCH_ASSOC);
foreach ($final_roles as $role) {
    echo "  - {$role['role_name']}: {$role['role_description']}\n";
}

echo "\n✅ Role standardization complete!\n";
?>
