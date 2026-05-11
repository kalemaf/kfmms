<?php
/**
 * Update Database Schema for Developer Role
 */

require_once 'config.inc.php';

if (isset($connection) && is_object($connection)) {
    // Check if company_id column exists
    $result = $connection->query("SHOW COLUMNS FROM users LIKE 'company_id'");
    if ($result && $result->num_rows == 0) {
        // Add company_id column
        $query = "ALTER TABLE users ADD COLUMN company_id INT(11) NULL AFTER user_id";
        if ($connection->query($query)) {
            echo 'Company_id column added.' . PHP_EOL;
        } else {
            echo 'Failed to add company_id column: ' . $connection->error . PHP_EOL;
        }
    } else {
        echo 'Company_id column already exists.' . PHP_EOL;
    }

    // Check if foreign key exists
    $result = $connection->query("
        SELECT CONSTRAINT_NAME
        FROM INFORMATION_SCHEMA.TABLE_CONSTRAINTS
        WHERE TABLE_NAME = 'users'
        AND CONSTRAINT_TYPE = 'FOREIGN KEY'
        AND CONSTRAINT_NAME = 'fk_users_company'
    ");
    if ($result && $result->num_rows == 0) {
        // Add foreign key constraint
        $query = "ALTER TABLE users ADD CONSTRAINT fk_users_company FOREIGN KEY (company_id) REFERENCES companies(company_id) ON DELETE SET NULL";
        if ($connection->query($query)) {
            echo 'Foreign key constraint added.' . PHP_EOL;
        } else {
            echo 'Failed to add foreign key constraint: ' . $connection->error . PHP_EOL;
        }
    } else {
        echo 'Foreign key constraint already exists.' . PHP_EOL;
    }

    // Check if index exists
    $result = $connection->query("SHOW INDEX FROM users WHERE Key_name = 'idx_company_id'");
    if ($result && $result->num_rows == 0) {
        // Add index
        $query = "ALTER TABLE users ADD INDEX idx_company_id (company_id)";
        if ($connection->query($query)) {
            echo 'Company_id index added.' . PHP_EOL;
        } else {
            echo 'Failed to add company_id index: ' . $connection->error . PHP_EOL;
        }
    } else {
        echo 'Company_id index already exists.' . PHP_EOL;
    }

} else {
    echo 'Database connection not available.' . PHP_EOL;
}
?>