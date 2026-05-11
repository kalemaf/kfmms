<?php
/**
 * SaaS Database Setup & Verification
 * Sets up all required SaaS tables and verifies database integrity
 */

require_once 'config.inc.php';
require_once 'common.inc.php';

global $db_type, $connection;

$results = [
    'success' => [],
    'errors' => [],
    'tables' => [],
];

echo "=== CMMS SaaS Database Setup ===\n\n";

// Table definitions based on database type
$tables = [
    'companies' => [
        'mysql' => "CREATE TABLE IF NOT EXISTS companies (
            company_id INT(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
            company_name VARCHAR(255) NOT NULL UNIQUE,
            company_email VARCHAR(255) UNIQUE,
            contact_name VARCHAR(255),
            contact_phone VARCHAR(50),
            industry VARCHAR(100),
            company_size VARCHAR(50),
            is_active BOOLEAN DEFAULT TRUE,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_is_active (is_active)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
        'sqlite' => "CREATE TABLE IF NOT EXISTS companies (
            company_id INTEGER PRIMARY KEY AUTOINCREMENT,
            company_name TEXT NOT NULL UNIQUE,
            company_email TEXT UNIQUE,
            contact_name TEXT,
            contact_phone TEXT,
            industry TEXT,
            company_size TEXT,
            is_active INTEGER DEFAULT 1,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
        )"
    ],
    'company_licenses' => [
        'mysql' => "CREATE TABLE IF NOT EXISTS company_licenses (
            license_id INT(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
            company_id INT(11) NOT NULL,
            license_key VARCHAR(255) NOT NULL UNIQUE,
            purchased_seats INT(11) DEFAULT 0,
            used_seats INT(11) DEFAULT 0,
            license_type ENUM('trial','basic','professional','enterprise') DEFAULT 'trial',
            payment_term ENUM('monthly','yearly','permanent') DEFAULT 'monthly',
            expires_at DATETIME,
            is_active BOOLEAN DEFAULT TRUE,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (company_id) REFERENCES companies(company_id) ON DELETE CASCADE,
            INDEX idx_company_id (company_id),
            INDEX idx_is_active (is_active)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
        'sqlite' => "CREATE TABLE IF NOT EXISTS company_licenses (
            license_id INTEGER PRIMARY KEY AUTOINCREMENT,
            company_id INTEGER NOT NULL REFERENCES companies(company_id) ON DELETE CASCADE,
            license_key TEXT NOT NULL UNIQUE,
            purchased_seats INTEGER DEFAULT 0,
            used_seats INTEGER DEFAULT 0,
            license_type TEXT DEFAULT 'trial',
            payment_term TEXT DEFAULT 'monthly',
            expires_at DATETIME,
            is_active INTEGER DEFAULT 1,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
        )"
    ],
    'system_control' => [
        'mysql' => "CREATE TABLE IF NOT EXISTS system_control (
            control_id INT(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
            company_id INT(11) NOT NULL UNIQUE,
            system_activated BOOLEAN DEFAULT FALSE,
            system_locked BOOLEAN DEFAULT FALSE,
            lock_reason VARCHAR(255),
            activation_date DATETIME,
            subscription_status ENUM('trial','active','expired','suspended') DEFAULT 'trial',
            subscription_expires_at DATETIME,
            max_users INT(11) DEFAULT 5,
            current_users INT(11) DEFAULT 0,
            feature_tier VARCHAR(100) DEFAULT 'trial',
            system_version VARCHAR(50),
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (company_id) REFERENCES companies(company_id) ON DELETE CASCADE,
            INDEX idx_system_activated (system_activated)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
        'sqlite' => "CREATE TABLE IF NOT EXISTS system_control (
            control_id INTEGER PRIMARY KEY AUTOINCREMENT,
            company_id INTEGER NOT NULL UNIQUE REFERENCES companies(company_id) ON DELETE CASCADE,
            system_activated INTEGER DEFAULT 0,
            system_locked INTEGER DEFAULT 0,
            lock_reason TEXT,
            activation_date DATETIME,
            subscription_status TEXT DEFAULT 'trial',
            subscription_expires_at DATETIME,
            max_users INTEGER DEFAULT 5,
            current_users INTEGER DEFAULT 0,
            feature_tier TEXT DEFAULT 'trial',
            system_version TEXT,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
        )"
    ],
    'subscription_payments' => [
        'mysql' => "CREATE TABLE IF NOT EXISTS subscription_payments (
            payment_id INT(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
            company_id INT(11) NOT NULL,
            amount DECIMAL(12,2) NOT NULL,
            payment_date DATETIME DEFAULT CURRENT_TIMESTAMP,
            payment_method VARCHAR(50),
            subscription_period_start DATETIME,
            subscription_period_end DATETIME,
            payment_status ENUM('pending','completed','failed','refunded') DEFAULT 'pending',
            processed_by INT(11),
            stripe_session_id VARCHAR(255),
            notes TEXT,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (company_id) REFERENCES companies(company_id) ON DELETE CASCADE,
            INDEX idx_company_id (company_id),
            INDEX idx_payment_date (payment_date)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
        'sqlite' => "CREATE TABLE IF NOT EXISTS subscription_payments (
            payment_id INTEGER PRIMARY KEY AUTOINCREMENT,
            company_id INTEGER NOT NULL REFERENCES companies(company_id) ON DELETE CASCADE,
            amount DECIMAL(12,2) NOT NULL,
            payment_date DATETIME DEFAULT CURRENT_TIMESTAMP,
            payment_method TEXT,
            subscription_period_start DATETIME,
            subscription_period_end DATETIME,
            payment_status TEXT DEFAULT 'pending',
            processed_by INTEGER,
            stripe_session_id TEXT,
            notes TEXT,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        )"
    ],
    'roles' => [
        'mysql' => "CREATE TABLE IF NOT EXISTS roles (
            role_id INT(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
            role_name VARCHAR(100) NOT NULL UNIQUE,
            role_description TEXT,
            is_system_role BOOLEAN DEFAULT FALSE,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_role_name (role_name)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
        'sqlite' => "CREATE TABLE IF NOT EXISTS roles (
            role_id INTEGER PRIMARY KEY AUTOINCREMENT,
            role_name TEXT NOT NULL UNIQUE,
            role_description TEXT,
            is_system_role INTEGER DEFAULT 0,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
        )"
    ],
    'permissions' => [
        'mysql' => "CREATE TABLE IF NOT EXISTS permissions (
            permission_id INT(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
            resource VARCHAR(100) NOT NULL,
            action VARCHAR(100) NOT NULL,
            description TEXT,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY uk_resource_action (resource, action)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
        'sqlite' => "CREATE TABLE IF NOT EXISTS permissions (
            permission_id INTEGER PRIMARY KEY AUTOINCREMENT,
            resource TEXT NOT NULL,
            action TEXT NOT NULL,
            description TEXT,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            UNIQUE(resource, action)
        )"
    ],
    'system_updates' => [
        'mysql' => "CREATE TABLE IF NOT EXISTS system_updates (
            update_id INT(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
            version VARCHAR(50) NOT NULL,
            update_type ENUM('feature','bugfix','security') DEFAULT 'feature',
            description TEXT NOT NULL,
            changelog TEXT,
            is_mandatory BOOLEAN DEFAULT FALSE,
            release_date DATETIME,
            created_by INT(11),
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_version (version)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
        'sqlite' => "CREATE TABLE IF NOT EXISTS system_updates (
            update_id INTEGER PRIMARY KEY AUTOINCREMENT,
            version TEXT NOT NULL,
            update_type TEXT DEFAULT 'feature',
            description TEXT NOT NULL,
            changelog TEXT,
            is_mandatory INTEGER DEFAULT 0,
            release_date DATETIME,
            created_by INTEGER,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        )"
    ],
    'license_actions' => [
        'mysql' => "CREATE TABLE IF NOT EXISTS license_actions (
            action_id INT(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
            license_id INT(11) NOT NULL,
            user_id INT(11),
            action VARCHAR(50) NOT NULL,
            action_details TEXT,
            action_date DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (license_id) REFERENCES company_licenses(license_id) ON DELETE CASCADE,
            INDEX idx_license_id (license_id),
            INDEX idx_action_date (action_date)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
        'sqlite' => "CREATE TABLE IF NOT EXISTS license_actions (
            action_id INTEGER PRIMARY KEY AUTOINCREMENT,
            license_id INTEGER NOT NULL REFERENCES company_licenses(license_id) ON DELETE CASCADE,
            user_id INTEGER,
            action TEXT NOT NULL,
            action_details TEXT,
            action_date DATETIME DEFAULT CURRENT_TIMESTAMP
        )"
    ]
];

// Create tables
foreach ($tables as $table_name => $sql_variants) {
    $sql = $sql_variants[$db_type] ?? null;
    
    if (!$sql) {
        $results['errors'][] = "No SQL definition for $table_name on $db_type";
        continue;
    }
    
    try {
        if ($db_type === 'sqlite') {
            $connection->exec($sql);
        } else {
            $connection->query($sql);
        }
        $results['success'][] = "✅ Table '$table_name' created/verified";
        $results['tables'][] = $table_name;
    } catch (Exception $e) {
        $results['errors'][] = "❌ Failed to create '$table_name': " . $e->getMessage();
    }
}

// Add company_id to users if not exists (MySQL)
if ($db_type === 'mysql') {
    $check_column = $connection->query("SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_NAME='users' AND COLUMN_NAME='company_id'");
    if ($check_column->num_rows == 0) {
        if ($connection->query("ALTER TABLE users ADD COLUMN company_id INT(11) NULL")) {
            $results['success'][] = "✅ Added company_id column to users";
        } else {
            $results['errors'][] = "❌ Failed to add company_id to users";
        }
    }
}

// SQLite: Add company_id to users
if ($db_type === 'sqlite') {
    try {
        $check = $connection->query("PRAGMA table_info(users)");
        $has_company = false;
        while ($row = $check->fetch(PDO::FETCH_ASSOC)) {
            if ($row['name'] === 'company_id') {
                $has_company = true;
                break;
            }
        }
        
        if (!$has_company) {
            $connection->exec("ALTER TABLE users ADD COLUMN company_id INTEGER NULL");
            $results['success'][] = "✅ Added company_id column to users";
        }
    } catch (Exception $e) {
        // Column likely exists
    }
}

// Output results
echo "\n📊 SaaS Database Setup Results\n";
echo "════════════════════════════════\n\n";

if (!empty($results['success'])) {
    echo "✅ SUCCESS (" . count($results['success']) . "):\n";
    foreach ($results['success'] as $msg) {
        echo "  $msg\n";
    }
    echo "\n";
}

if (!empty($results['errors'])) {
    echo "❌ ERRORS (" . count($results['errors']) . "):\n";
    foreach ($results['errors'] as $msg) {
        echo "  $msg\n";
    }
    echo "\n";
} else {
    echo "✅ No errors encountered!\n\n";
}

echo "📋 Tables Set Up: " . implode(', ', $results['tables']) . "\n\n";
echo "✨ SaaS Database setup complete!\n";
?>
