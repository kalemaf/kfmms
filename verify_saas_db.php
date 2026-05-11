<?php
/**
 * CMMS SaaS Database Verification & Recovery Script
 * Run this to verify and fix any database schema issues
 */

require_once 'config.inc.php';
require_once 'common.inc.php';

global $db_type, $connection;

if (!isset($connection)) {
    die("Database connection failed\n");
}

echo "=== CMMS SaaS Database Verification ===\n\n";

$verification = [
    'tables_found' => [],
    'tables_missing' => [],
    'tables_fixed' => [],
    'errors' => []
];

// List of required SaaS tables
$required_tables = [
    'companies',
    'company_licenses',
    'system_control',
    'subscription_payments',
    'roles',
    'permissions',
    'system_updates',
    'license_actions'
];

// Check each table
foreach ($required_tables as $table) {
    if ($db_type === 'sqlite') {
        try {
            $result = $connection->query("SELECT name FROM sqlite_master WHERE type='table' AND name='{$table}'");
            $exists = $result && $result->fetch(PDO::FETCH_ASSOC) !== false;
        } catch (Exception $e) {
            $exists = false;
        }
    } else {
        $result = $connection->query("SHOW TABLES LIKE '{$table}'");
        $exists = $result && $result->num_rows > 0;
    }
    
    if ($exists) {
        $verification['tables_found'][] = $table;
        echo "✅ Table '$table' exists\n";
    } else {
        $verification['tables_missing'][] = $table;
        echo "❌ Table '$table' NOT FOUND\n";
    }
}

echo "\n";

// Try to create missing tables
if (!empty($verification['tables_missing'])) {
    echo "🔧 Attempting to create missing tables...\n\n";
    
    // Create companies table
    if (in_array('companies', $verification['tables_missing'])) {
        $sql = $db_type === 'sqlite' 
            ? "CREATE TABLE companies (
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
            : "CREATE TABLE companies (
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
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
        
        try {
            if ($db_type === 'sqlite') {
                $connection->exec($sql);
            } else {
                $connection->query($sql);
            }
            $verification['tables_fixed'][] = 'companies';
            echo "✅ Created 'companies' table\n";
        } catch (Exception $e) {
            $verification['errors'][] = "Failed to create companies: " . $e->getMessage();
        }
    }
    
    // Create company_licenses table
    if (in_array('company_licenses', $verification['tables_missing'])) {
        $sql = $db_type === 'sqlite'
            ? "CREATE TABLE company_licenses (
                license_id INTEGER PRIMARY KEY AUTOINCREMENT,
                company_id INTEGER NOT NULL,
                license_key TEXT NOT NULL UNIQUE,
                purchased_seats INTEGER DEFAULT 0,
                used_seats INTEGER DEFAULT 0,
                license_type TEXT DEFAULT 'trial',
                payment_term TEXT DEFAULT 'monthly',
                expires_at DATETIME,
                is_active INTEGER DEFAULT 1,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (company_id) REFERENCES companies(company_id) ON DELETE CASCADE
            )"
            : "CREATE TABLE company_licenses (
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
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
        
        try {
            if ($db_type === 'sqlite') {
                $connection->exec($sql);
            } else {
                $connection->query($sql);
            }
            $verification['tables_fixed'][] = 'company_licenses';
            echo "✅ Created 'company_licenses' table\n";
        } catch (Exception $e) {
            $verification['errors'][] = "Failed to create company_licenses: " . $e->getMessage();
        }
    }
    
    // Create system_control table
    if (in_array('system_control', $verification['tables_missing'])) {
        $sql = $db_type === 'sqlite'
            ? "CREATE TABLE system_control (
                control_id INTEGER PRIMARY KEY AUTOINCREMENT,
                company_id INTEGER NOT NULL UNIQUE,
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
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (company_id) REFERENCES companies(company_id) ON DELETE CASCADE
            )"
            : "CREATE TABLE system_control (
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
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
        
        try {
            if ($db_type === 'sqlite') {
                $connection->exec($sql);
            } else {
                $connection->query($sql);
            }
            $verification['tables_fixed'][] = 'system_control';
            echo "✅ Created 'system_control' table\n";
        } catch (Exception $e) {
            $verification['errors'][] = "Failed to create system_control: " . $e->getMessage();
        }
    }
    
    // Create other tables
    $other_tables = [
        'subscription_payments' => $db_type === 'sqlite'
            ? "CREATE TABLE subscription_payments (
                payment_id INTEGER PRIMARY KEY AUTOINCREMENT,
                company_id INTEGER NOT NULL,
                amount DECIMAL(12,2) NOT NULL,
                payment_date DATETIME DEFAULT CURRENT_TIMESTAMP,
                payment_method TEXT,
                subscription_period_start DATETIME,
                subscription_period_end DATETIME,
                payment_status TEXT DEFAULT 'pending',
                processed_by INTEGER,
                stripe_session_id TEXT,
                notes TEXT,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (company_id) REFERENCES companies(company_id) ON DELETE CASCADE
            )"
            : "CREATE TABLE subscription_payments (
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
                INDEX idx_company_id (company_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
        'roles' => $db_type === 'sqlite'
            ? "CREATE TABLE roles (
                role_id INTEGER PRIMARY KEY AUTOINCREMENT,
                role_name TEXT NOT NULL UNIQUE,
                role_description TEXT,
                is_system_role INTEGER DEFAULT 0,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
            )"
            : "CREATE TABLE roles (
                role_id INT(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
                role_name VARCHAR(100) NOT NULL UNIQUE,
                role_description TEXT,
                is_system_role BOOLEAN DEFAULT FALSE,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                INDEX idx_role_name (role_name)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
        'permissions' => $db_type === 'sqlite'
            ? "CREATE TABLE permissions (
                permission_id INTEGER PRIMARY KEY AUTOINCREMENT,
                resource TEXT NOT NULL,
                action TEXT NOT NULL,
                description TEXT,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                UNIQUE(resource, action)
            )"
            : "CREATE TABLE permissions (
                permission_id INT(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
                resource VARCHAR(100) NOT NULL,
                action VARCHAR(100) NOT NULL,
                description TEXT,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                UNIQUE KEY uk_resource_action (resource, action)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
        'system_updates' => $db_type === 'sqlite'
            ? "CREATE TABLE system_updates (
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
            : "CREATE TABLE system_updates (
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
        'license_actions' => $db_type === 'sqlite'
            ? "CREATE TABLE license_actions (
                action_id INTEGER PRIMARY KEY AUTOINCREMENT,
                license_id INTEGER,
                user_id INTEGER,
                action TEXT NOT NULL,
                action_details TEXT,
                action_date DATETIME DEFAULT CURRENT_TIMESTAMP
            )"
            : "CREATE TABLE license_actions (
                action_id INT(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
                license_id INT(11),
                user_id INT(11),
                action VARCHAR(50) NOT NULL,
                action_details TEXT,
                action_date DATETIME DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (license_id) REFERENCES company_licenses(license_id) ON DELETE CASCADE,
                INDEX idx_license_id (license_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
    ];
    
    foreach ($other_tables as $table => $sql) {
        if (in_array($table, $verification['tables_missing'])) {
            try {
                if ($db_type === 'sqlite') {
                    $connection->exec($sql);
                } else {
                    $connection->query($sql);
                }
                $verification['tables_fixed'][] = $table;
                echo "✅ Created '$table' table\n";
            } catch (Exception $e) {
                $verification['errors'][] = "Failed to create $table: " . $e->getMessage();
            }
        }
    }
}

echo "\n";

// Add company_id to users if missing
echo "🔍 Checking users table...\n";
if ($db_type === 'sqlite') {
    try {
        $pragma = $connection->query("PRAGMA table_info(users)");
        $has_company = false;
        while ($col = $pragma->fetch(PDO::FETCH_ASSOC)) {
            if ($col['name'] === 'company_id') {
                $has_company = true;
                break;
            }
        }
        
        if (!$has_company) {
            $connection->exec("ALTER TABLE users ADD COLUMN company_id INTEGER");
            echo "✅ Added company_id column to users table\n";
        } else {
            echo "✅ users table has company_id column\n";
        }
    } catch (Exception $e) {
        echo "⚠️  " . $e->getMessage() . "\n";
    }
} else {
    $result = $connection->query("SHOW COLUMNS FROM users LIKE 'company_id'");
    if ($result && $result->num_rows === 0) {
        if ($connection->query("ALTER TABLE users ADD COLUMN company_id INT(11) NULL")) {
            echo "✅ Added company_id column to users table\n";
        } else {
            echo "❌ Failed to add company_id to users\n";
        }
    } else {
        echo "✅ users table has company_id column\n";
    }
}

echo "\n=== Summary ===\n";
echo "Tables Found: " . count($verification['tables_found']) . "\n";
echo "Tables Missing: " . count($verification['tables_missing']) . "\n";
echo "Tables Fixed: " . count($verification['tables_fixed']) . "\n";
echo "Errors: " . count($verification['errors']) . "\n";

if (!empty($verification['errors'])) {
    echo "\n❌ Errors:\n";
    foreach ($verification['errors'] as $error) {
        echo "  - $error\n";
    }
}

if (count($verification['tables_fixed']) > 0) {
    echo "\n✅ Fixed tables: " . implode(', ', $verification['tables_fixed']) . "\n";
}

echo "\n✨ Database verification complete!\n";
?>
