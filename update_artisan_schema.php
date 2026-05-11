<?php
/**
 * Update Artisan Schema - Make user_id nullable
 * Run this script to update the artisans table schema
 * Usage: php update_artisan_schema.php
 */

require_once 'config.inc.php';

echo "==================================\n";
echo "Updating Artisan Schema\n";
echo "==================================\n\n";

try {
    // Check if artisans table exists
    $check_sql = "SELECT name FROM sqlite_master WHERE type='table' AND name='artisans'";
    $result = $pdo->query($check_sql);

    if ($result->fetchColumn()) {
        echo "Artisans table exists. Updating schema...\n";

        // Make user_id nullable (SQLite doesn't support DROP CONSTRAINT directly)
        // We'll recreate the table with the new schema
        $pdo->exec('PRAGMA foreign_keys = OFF');

        // Create new table with updated schema
        $create_sql = "CREATE TABLE artisans_new (
            artisan_id INTEGER PRIMARY KEY AUTOINCREMENT,
            user_id INTEGER,
            tenant_id INTEGER NOT NULL DEFAULT 1,
            first_name VARCHAR(100) NOT NULL,
            last_name VARCHAR(100) NOT NULL,
            employee_id VARCHAR(50),
            phone VARCHAR(20),
            mobile_phone VARCHAR(20),
            email VARCHAR(255),
            birth_date DATE,
            hire_date DATE,
            vendor_id INTEGER,
            hourly_rate DECIMAL(10,2),
            cost_center VARCHAR(50),
            sms_enabled BOOLEAN DEFAULT 0,
            push_notifications_enabled BOOLEAN DEFAULT 0,
            is_active BOOLEAN DEFAULT 1,
            is_available_today BOOLEAN DEFAULT 1,
            availability_status VARCHAR(50) DEFAULT 'available',
            available_from_date DATE,
            available_to_date DATE,
            performance_score DECIMAL(5,2) DEFAULT 0,
            compliance_status VARCHAR(50) DEFAULT 'compliant',
            certification_expiry_date DATE,
            assigned_sites JSON,
            emergency_contact_phone VARCHAR(20),
            emergency_contact_name VARCHAR(100),
            notes TEXT,
            last_assigned_date DATETIME,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            created_by INTEGER,
            updated_by INTEGER
        )";

        $pdo->exec($create_sql);

        // Copy data from old table to new table
        $insert_sql = "INSERT INTO artisans_new (
            artisan_id, user_id, tenant_id, first_name, last_name, employee_id,
            phone, mobile_phone, email, birth_date, hire_date, vendor_id,
            hourly_rate, cost_center, sms_enabled, push_notifications_enabled,
            is_active, is_available_today, availability_status, available_from_date,
            available_to_date, performance_score, compliance_status, certification_expiry_date,
            assigned_sites, emergency_contact_phone, emergency_contact_name, notes,
            last_assigned_date, created_at, updated_at, created_by, updated_by
        ) SELECT
            artisan_id, user_id, tenant_id, first_name, last_name, employee_id,
            phone, mobile_phone, email, birth_date, hire_date, vendor_id,
            hourly_rate, cost_center, sms_enabled, push_notifications_enabled,
            is_active, is_available_today, availability_status, available_from_date,
            available_to_date, performance_score, compliance_status, certification_expiry_date,
            assigned_sites, emergency_contact_phone, emergency_contact_name, notes,
            last_assigned_date, created_at, updated_at, created_by, updated_by
        FROM artisans";

        $pdo->exec($insert_sql);

        // Drop old table and rename new table
        $pdo->exec('DROP TABLE artisans');
        $pdo->exec('ALTER TABLE artisans_new RENAME TO artisans');

        // Recreate indexes
        $pdo->exec('CREATE INDEX IF NOT EXISTS idx_artisans_user_id ON artisans(user_id)');
        $pdo->exec('CREATE INDEX IF NOT EXISTS idx_artisans_tenant_id ON artisans(tenant_id)');
        $pdo->exec('CREATE INDEX IF NOT EXISTS idx_artisans_active ON artisans(is_active, tenant_id)');

        $pdo->exec('PRAGMA foreign_keys = ON');

        echo "✅ Schema updated successfully!\n";
        echo "✅ user_id column is now nullable\n";
    } else {
        echo "Artisans table does not exist. Please run init_artisan_system.php first.\n";
    }

} catch (Exception $e) {
    echo "❌ Error updating schema: " . $e->getMessage() . "\n";
    exit(1);
}

echo "\n📝 Schema update complete!\n";
?></content>
<parameter name="filePath">c:\free-cmms 0.04\update_artisan_schema.php