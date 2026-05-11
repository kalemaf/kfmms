<?php
/**
 * Create Company Tables
 */

require_once 'config.inc.php';

if (isset($connection) && is_object($connection)) {
    echo 'Creating companies table...' . PHP_EOL;
    $sql = 'CREATE TABLE IF NOT EXISTS companies (
        company_id INT(11) NOT NULL AUTO_INCREMENT,
        company_name VARCHAR(255) NOT NULL,
        company_email VARCHAR(255) NULL,
        contact_name VARCHAR(255) NULL,
        contact_phone VARCHAR(50) NULL,
        is_active BOOLEAN NOT NULL DEFAULT TRUE,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (company_id),
        UNIQUE KEY uk_company_name (company_name),
        INDEX idx_is_active (is_active)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci';

    if ($connection->query($sql)) {
        echo '✓ Companies table created successfully' . PHP_EOL;
    } else {
        echo '✗ Error creating companies table: ' . $connection->error . PHP_EOL;
    }

    echo 'Creating company_licenses table...' . PHP_EOL;
    $sql = 'CREATE TABLE IF NOT EXISTS company_licenses (
        license_id INT(11) NOT NULL AUTO_INCREMENT,
        company_id INT(11) NOT NULL,
        license_key VARCHAR(255) NOT NULL UNIQUE,
        purchased_seats INT(11) NOT NULL DEFAULT 0,
        used_seats INT(11) NOT NULL DEFAULT 0,
        license_type ENUM("trial","basic","professional","enterprise") NOT NULL DEFAULT "basic",
        payment_term ENUM("monthly","yearly","permanent") NOT NULL DEFAULT "monthly",
        expires_at DATETIME NULL,
        is_active BOOLEAN NOT NULL DEFAULT TRUE,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (license_id),
        FOREIGN KEY (company_id) REFERENCES companies (company_id) ON DELETE CASCADE,
        UNIQUE KEY uk_license_key (license_key),
        INDEX idx_company_id (company_id),
        INDEX idx_is_active (is_active),
        INDEX idx_expires_at (expires_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci';

    if ($connection->query($sql)) {
        echo '✓ Company_licenses table created successfully' . PHP_EOL;
    } else {
        echo '✗ Error creating company_licenses table: ' . $connection->error . PHP_EOL;
    }

    echo 'Adding company_id column to users table...' . PHP_EOL;
    $sql = 'ALTER TABLE users ADD COLUMN IF NOT EXISTS company_id INT(11) NULL AFTER user_id';
    if ($connection->query($sql)) {
        echo '✓ Company_id column added to users table' . PHP_EOL;
    } else {
        echo '✗ Error adding company_id column: ' . $connection->error . PHP_EOL;
    }

} else {
    echo 'Database connection not available.' . PHP_EOL;
}
?>