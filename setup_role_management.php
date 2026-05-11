<?php
/**
 * Database Schema Setup Script
 * Ensures all required tables exist for the role management system
 */

require_once 'config.inc.php';

if (isset($connection) && is_object($connection)) {
    echo 'Database connection available.' . PHP_EOL;

    // Check if users table exists
    $result = $connection->query('SHOW TABLES LIKE "users"');
    if ($result && $result->num_rows > 0) {
        echo 'Users table exists.' . PHP_EOL;
    } else {
        echo 'Users table does not exist. Running security schema...' . PHP_EOL;

        // Run security schema
        $schema = file_get_contents('security_schema.sql');
        if ($schema) {
            $statements = array_filter(array_map('trim', explode(';', $schema)));
            foreach ($statements as $statement) {
                if (!empty($statement) && !preg_match('/^--/', $statement)) {
                    echo 'Executing: ' . substr($statement, 0, 50) . '...' . PHP_EOL;
                    if ($connection->query($statement) === TRUE) {
                        echo '✓ Success' . PHP_EOL;
                    } else {
                        echo '✗ Error: ' . $connection->error . PHP_EOL;
                    }
                }
            }
        }

        // Run license schema
        echo 'Running license schema...' . PHP_EOL;
        $license_schema = file_get_contents('license_schema.sql');
        if ($license_schema) {
            $statements = array_filter(array_map('trim', explode(';', $license_schema)));
            foreach ($statements as $statement) {
                if (!empty($statement) && !preg_match('/^--/', $statement)) {
                    echo 'Executing: ' . substr($statement, 0, 50) . '...' . PHP_EOL;
                    if ($connection->query($statement) === TRUE) {
                        echo '✓ Success' . PHP_EOL;
                    } else {
                        echo '✗ Error: ' . $connection->error . PHP_EOL;
                    }
                }
            }
        }
    }

    // Insert default permissions if they don't exist
    echo 'Checking default permissions...' . PHP_EOL;
    $permissions = [
        ['users', 'create'],
        ['users', 'read'],
        ['users', 'update'],
        ['users', 'delete'],
        ['companies', 'create'],
        ['companies', 'read'],
        ['companies', 'update'],
        ['companies', 'delete'],
        ['licenses', 'create'],
        ['licenses', 'read'],
        ['licenses', 'update'],
        ['licenses', 'delete'],
        ['work_orders', 'create'],
        ['work_orders', 'read'],
        ['work_orders', 'update'],
        ['work_orders', 'delete'],
        ['equipment', 'create'],
        ['equipment', 'read'],
        ['equipment', 'update'],
        ['equipment', 'delete'],
        ['inventory', 'create'],
        ['inventory', 'read'],
        ['inventory', 'update'],
        ['inventory', 'delete'],
        ['reports', 'read'],
        ['admin', 'access']
    ];

    foreach ($permissions as $perm) {
        $stmt = $connection->prepare("INSERT IGNORE INTO permissions (permission_name, resource, action, permission_description) VALUES (?, ?, ?, ?)");
        if ($stmt) {
            $name = $perm[0] . '_' . $perm[1];
            $desc = ucfirst($perm[1]) . ' ' . str_replace('_', ' ', $perm[0]);
            $stmt->bind_param('ssss', $name, $perm[0], $perm[1], $desc);
            $stmt->execute();
            $stmt->close();
        }
    }
    echo 'Default permissions checked/inserted.' . PHP_EOL;

    // Insert default roles if they don't exist
    echo 'Checking default roles...' . PHP_EOL;
    $roles = [
        ['admin', 'System Administrator with full access'],
        ['maintenance manager', 'Maintenance department manager'],
        ['supervisor', 'Team supervisor with approval rights'],
        ['technician', 'Field technician'],
        ['operator', 'Basic operator access'],
        ['developer', 'Application developer with all permissions']
    ];

    foreach ($roles as $role) {
        $stmt = $connection->prepare("INSERT IGNORE INTO roles (role_name, role_description) VALUES (?, ?)");
        if ($stmt) {
            $stmt->bind_param('ss', $role[0], $role[1]);
            $stmt->execute();
            $stmt->close();
        }
    }
    echo 'Default roles checked/inserted.' . PHP_EOL;

    // Assign all permissions to developer role
    echo 'Assigning permissions to developer role...' . PHP_EOL;
    $result = $connection->query("SELECT role_id FROM roles WHERE role_name = 'developer'");
    if ($result && $row = $result->fetch_assoc()) {
        $developer_role_id = $row['role_id'];

        // Get all permission IDs
        $perm_result = $connection->query("SELECT permission_id FROM permissions");
        if ($perm_result) {
            while ($perm_row = $perm_result->fetch_assoc()) {
                $stmt = $connection->prepare("INSERT IGNORE INTO role_permissions (role_id, permission_id, granted_by) VALUES (?, ?, 1)");
                if ($stmt) {
                    $stmt->bind_param('ii', $developer_role_id, $perm_row['permission_id']);
                    $stmt->execute();
                    $stmt->close();
                }
            }
        }
    }
    echo 'Developer permissions assigned.' . PHP_EOL;

} else {
    echo 'Database connection not available.' . PHP_EOL;
}
?>