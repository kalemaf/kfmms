<?php
/**
 * Update Artisan Schema - Make user_id nullable
 */

require_once 'config.inc.php';

echo "Updating artisan schema...\n";

try {
    // For SQLite, we need to recreate the table since ALTER COLUMN is limited
    $pdo->exec('PRAGMA foreign_keys = OFF');

    // Create new table with nullable user_id
    $pdo->exec("CREATE TABLE artisans_temp (
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
    )");

    // Copy data
    $pdo->exec("INSERT INTO artisans_temp SELECT * FROM artisans");

    // Drop old table and rename
    $pdo->exec("DROP TABLE artisans");
    $pdo->exec("ALTER TABLE artisans_temp RENAME TO artisans");

    // Recreate indexes
    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_artisans_user_id ON artisans(user_id)");
    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_artisans_tenant_id ON artisans(tenant_id)");
    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_artisans_active ON artisans(is_active, tenant_id)");

    $pdo->exec('PRAGMA foreign_keys = ON');

    echo "✅ Schema updated successfully! user_id is now nullable.\n";

} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}
?></content>
<parameter name="filePath">c:\free-cmms 0.04\migrate_artisan_schema.php