<?php
/**
 * Artisan/Technician Management Schema
 * Creates tables for comprehensive technician management with skills, availability, 
 * certifications, and performance tracking
 */

function create_artisan_tables() {
    global $pdo;
    
    if (!$pdo) {
        die("Database connection not established");
    }
    
    try {
        // Main artisans table - links to users and extends technician info
        $sql = "CREATE TABLE IF NOT EXISTS artisans (
            artisan_id INTEGER PRIMARY KEY AUTOINCREMENT,
            user_id INTEGER UNIQUE,
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
            updated_by INTEGER,
            FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE,
            FOREIGN KEY (vendor_id) REFERENCES vendors(vendor_id) ON DELETE SET NULL,
            FOREIGN KEY (created_by) REFERENCES users(user_id) ON DELETE SET NULL,
            FOREIGN KEY (updated_by) REFERENCES users(user_id) ON DELETE SET NULL,
            UNIQUE (user_id, tenant_id)
        )";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute();
        echo "✓ Artisans table created\n";
        
        // Artisan skills table - many-to-many relationship with skills
        $sql = "CREATE TABLE IF NOT EXISTS artisan_skills (
            artisan_skill_id INTEGER PRIMARY KEY AUTOINCREMENT,
            artisan_id INTEGER NOT NULL,
            tenant_id INTEGER NOT NULL DEFAULT 1,
            skill_name VARCHAR(100) NOT NULL,
            skill_category VARCHAR(50),
            proficiency_level VARCHAR(20) DEFAULT 'intermediate',
            years_of_experience DECIMAL(5,2),
            last_verified_date DATE,
            is_verified BOOLEAN DEFAULT 0,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (artisan_id) REFERENCES artisans(artisan_id) ON DELETE CASCADE,
            UNIQUE (artisan_id, skill_name, tenant_id)
        )";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute();
        echo "✓ Artisan skills table created\n";
        
        // Artisan certifications table
        $sql = "CREATE TABLE IF NOT EXISTS artisan_certifications (
            certification_id INTEGER PRIMARY KEY AUTOINCREMENT,
            artisan_id INTEGER NOT NULL,
            tenant_id INTEGER NOT NULL DEFAULT 1,
            certification_name VARCHAR(200) NOT NULL,
            certification_number VARCHAR(100),
            issuing_body VARCHAR(150),
            issue_date DATE,
            expiry_date DATE,
            is_active BOOLEAN DEFAULT 1,
            compliance_requirement BOOLEAN DEFAULT 0,
            document_path VARCHAR(500),
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (artisan_id) REFERENCES artisans(artisan_id) ON DELETE CASCADE,
            UNIQUE (artisan_id, certification_number, tenant_id)
        )";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute();
        echo "✓ Artisan certifications table created\n";
        
        // Artisan availability schedule - recurring availability pattern
        $sql = "CREATE TABLE IF NOT EXISTS artisan_availability_schedule (
            schedule_id INTEGER PRIMARY KEY AUTOINCREMENT,
            artisan_id INTEGER NOT NULL,
            tenant_id INTEGER NOT NULL DEFAULT 1,
            day_of_week VARCHAR(10),
            start_time TIME,
            end_time TIME,
            is_available BOOLEAN DEFAULT 1,
            is_recurring BOOLEAN DEFAULT 1,
            note VARCHAR(255),
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (artisan_id) REFERENCES artisans(artisan_id) ON DELETE CASCADE
        )";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute();
        echo "✓ Artisan availability schedule table created\n";
        
        // Artisan site assignments - multi-site operations
        $sql = "CREATE TABLE IF NOT EXISTS artisan_site_assignments (
            assignment_id INTEGER PRIMARY KEY AUTOINCREMENT,
            artisan_id INTEGER NOT NULL,
            site_id INTEGER,
            tenant_id INTEGER NOT NULL DEFAULT 1,
            company_id INTEGER,
            location_name VARCHAR(150),
            assignment_start_date DATE,
            assignment_end_date DATE,
            is_primary_site BOOLEAN DEFAULT 0,
            is_active BOOLEAN DEFAULT 1,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (artisan_id) REFERENCES artisans(artisan_id) ON DELETE CASCADE,
            UNIQUE (artisan_id, site_id, tenant_id)
        )";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute();
        echo "✓ Artisan site assignments table created\n";
        
        // Artisan compliance audit trail
        $sql = "CREATE TABLE IF NOT EXISTS artisan_compliance_audit (
            audit_id INTEGER PRIMARY KEY AUTOINCREMENT,
            artisan_id INTEGER NOT NULL,
            tenant_id INTEGER NOT NULL DEFAULT 1,
            compliance_check_type VARCHAR(100),
            status VARCHAR(20),
            notes TEXT,
            checked_by INTEGER,
            check_date DATE,
            next_check_date DATE,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (artisan_id) REFERENCES artisans(artisan_id) ON DELETE CASCADE,
            FOREIGN KEY (checked_by) REFERENCES users(user_id) ON DELETE SET NULL
        )";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute();
        echo "✓ Artisan compliance audit table created\n";
        
        // Artisan work order assignment history
        $sql = "CREATE TABLE IF NOT EXISTS artisan_work_order_assignments (
            assignment_id INTEGER PRIMARY KEY AUTOINCREMENT,
            artisan_id INTEGER NOT NULL,
            work_order_id INTEGER NOT NULL,
            tenant_id INTEGER NOT NULL DEFAULT 1,
            assignment_date DATETIME,
            estimated_hours DECIMAL(8,2),
            actual_hours DECIMAL(8,2),
            assignment_reason VARCHAR(500),
            is_primary_assignee BOOLEAN DEFAULT 1,
            performance_impact DECIMAL(5,2),
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (artisan_id) REFERENCES artisans(artisan_id) ON DELETE CASCADE,
            FOREIGN KEY (work_order_id) REFERENCES work_orders(wo_id) ON DELETE CASCADE,
            UNIQUE (artisan_id, work_order_id, tenant_id)
        )";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute();
        echo "✓ Artisan work order assignments table created\n";
        
        // Add indexes for performance
        $indexes = [
            "CREATE INDEX IF NOT EXISTS idx_artisans_tenant ON artisans(tenant_id)",
            "CREATE INDEX IF NOT EXISTS idx_artisans_user ON artisans(user_id)",
            "CREATE INDEX IF NOT EXISTS idx_artisans_vendor ON artisans(vendor_id)",
            "CREATE INDEX IF NOT EXISTS idx_artisans_active ON artisans(is_active, tenant_id)",
            "CREATE INDEX IF NOT EXISTS idx_artisan_skills_artisan ON artisan_skills(artisan_id, tenant_id)",
            "CREATE INDEX IF NOT EXISTS idx_artisan_certs_artisan ON artisan_certifications(artisan_id, tenant_id)",
            "CREATE INDEX IF NOT EXISTS idx_artisan_schedule_artisan ON artisan_availability_schedule(artisan_id, tenant_id)",
            "CREATE INDEX IF NOT EXISTS idx_artisan_sites_artisan ON artisan_site_assignments(artisan_id, tenant_id)",
            "CREATE INDEX IF NOT EXISTS idx_artisan_compliance_audit ON artisan_compliance_audit(artisan_id, tenant_id)",
            "CREATE INDEX IF NOT EXISTS idx_artisan_wo_assignments ON artisan_work_order_assignments(artisan_id, tenant_id, work_order_id)"
        ];
        
        foreach ($indexes as $indexSql) {
            $stmt = $pdo->prepare($indexSql);
            $stmt->execute();
        }
        echo "✓ Indexes created\n";
        
        echo "\n✅ Artisan management schema created successfully!\n";
        return true;
        
    } catch (Exception $e) {
        echo "❌ Error creating artisan tables: " . $e->getMessage() . "\n";
        return false;
    }
}

// Execute if called directly
if (php_sapi_name() === 'cli' && basename(__FILE__) === basename($_SERVER['PHP_SELF'] ?? '')) {
    require_once __DIR__ . '/../config.inc.php';
    create_artisan_tables();
}
?>
