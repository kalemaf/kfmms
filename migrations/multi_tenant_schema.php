<?php
/**
 * Multi-Tenant Database Schema Migrations
 * 
 * This file contains all SQL schemas needed for converting KFMMS
 * to a professional multi-tenant SaaS application.
 * 
 * Strategy:
 * 1. Create companies table (tenants)
 * 2. Add tenant_id to all existing tables
 * 3. Create proper foreign key relationships
 * 4. Add indexes for tenant_id on all tables
 * 5. Create storage isolation structure
 */

$MIGRATIONS = [
    // ============================================================================
    // 1. COMPANIES TABLE (CORE TENANT TABLE)
    // ============================================================================
    'create_companies_table' => [
        'mysql' => "CREATE TABLE IF NOT EXISTS companies (
            company_id INT(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(255) NOT NULL UNIQUE,
            email VARCHAR(255) NOT NULL UNIQUE,
            phone VARCHAR(20),
            address TEXT,
            city VARCHAR(100),
            state VARCHAR(100),
            country VARCHAR(100),
            postal_code VARCHAR(20),
            subscription_plan ENUM('trial', 'starter', 'professional', 'enterprise') DEFAULT 'trial',
            subscription_status ENUM('active', 'inactive', 'suspended', 'expired') DEFAULT 'active',
            subscription_starts_at DATETIME,
            subscription_ends_at DATETIME,
            max_users INT(11) DEFAULT 5,
            max_work_orders INT(11) DEFAULT 100,
            feature_tier VARCHAR(50) DEFAULT 'trial',
            is_active TINYINT(1) DEFAULT 1,
            is_locked TINYINT(1) DEFAULT 0,
            lock_reason TEXT,
            custom_domain VARCHAR(255) UNIQUE,
            logo_url VARCHAR(255),
            registration_source VARCHAR(50),
            payment_status VARCHAR(50) DEFAULT 'pending',
            created_by INT(11),
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_subscription_status (subscription_status),
            INDEX idx_is_active (is_active),
            INDEX idx_created_at (created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
        
        'sqlite' => "CREATE TABLE IF NOT EXISTS companies (
            company_id INTEGER PRIMARY KEY AUTOINCREMENT,
            name TEXT NOT NULL UNIQUE,
            email TEXT NOT NULL UNIQUE,
            phone TEXT,
            address TEXT,
            city TEXT,
            state TEXT,
            country TEXT,
            postal_code TEXT,
            subscription_plan TEXT DEFAULT 'trial' CHECK(subscription_plan IN ('trial', 'starter', 'professional', 'enterprise')),
            subscription_status TEXT DEFAULT 'active' CHECK(subscription_status IN ('active', 'inactive', 'suspended', 'expired')),
            subscription_starts_at DATETIME,
            subscription_ends_at DATETIME,
            max_users INTEGER DEFAULT 5,
            max_work_orders INTEGER DEFAULT 100,
            feature_tier TEXT DEFAULT 'trial',
            is_active INTEGER DEFAULT 1,
            is_locked INTEGER DEFAULT 0,
            lock_reason TEXT,
            custom_domain TEXT UNIQUE,
            logo_url TEXT,
            registration_source TEXT,
            payment_status TEXT DEFAULT 'pending',
            created_by INTEGER,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
        )"
    ],
    
    // ============================================================================
    // 2. TENANT_ID COLUMNS FOR ALL TABLES
    // ============================================================================
    'add_tenant_to_users' => [
        'mysql' => "ALTER TABLE users ADD COLUMN tenant_id INT(11) NOT NULL DEFAULT 0 AFTER user_id,
                   ADD UNIQUE INDEX idx_email_tenant (email, tenant_id),
                   ADD INDEX idx_tenant_id (tenant_id),
                   ADD FOREIGN KEY (tenant_id) REFERENCES companies(company_id) ON DELETE CASCADE",
        
        'sqlite' => "-- SQLite: Recreate users table with tenant_id
                    ALTER TABLE users ADD COLUMN tenant_id INTEGER NOT NULL DEFAULT 0"
    ],
    
    'add_tenant_to_work_orders' => [
        'mysql' => "ALTER TABLE work_orders ADD COLUMN tenant_id INT(11) NOT NULL DEFAULT 0 AFTER work_order_id,
                   ADD INDEX idx_tenant_id (tenant_id),
                   ADD FOREIGN KEY (tenant_id) REFERENCES companies(company_id) ON DELETE CASCADE",
        
        'sqlite' => "ALTER TABLE work_orders ADD COLUMN tenant_id INTEGER NOT NULL DEFAULT 0"
    ],
    
    'add_tenant_to_equipment' => [
        'mysql' => "ALTER TABLE equipment ADD COLUMN tenant_id INT(11) NOT NULL DEFAULT 0 AFTER equipment_id,
                   ADD INDEX idx_tenant_id (tenant_id),
                   ADD FOREIGN KEY (tenant_id) REFERENCES companies(company_id) ON DELETE CASCADE",
        
        'sqlite' => "ALTER TABLE equipment ADD COLUMN tenant_id INTEGER NOT NULL DEFAULT 0"
    ],
    
    'add_tenant_to_spare_parts' => [
        'mysql' => "ALTER TABLE spare_parts ADD COLUMN tenant_id INT(11) NOT NULL DEFAULT 0 AFTER spare_id,
                   ADD INDEX idx_tenant_id (tenant_id),
                   ADD FOREIGN KEY (tenant_id) REFERENCES companies(company_id) ON DELETE CASCADE",
        
        'sqlite' => "ALTER TABLE spare_parts ADD COLUMN tenant_id INTEGER NOT NULL DEFAULT 0"
    ],
    
    'add_tenant_to_inventory' => [
        'mysql' => "ALTER TABLE inventory ADD COLUMN tenant_id INT(11) NOT NULL DEFAULT 0 AFTER inventory_id,
                   ADD INDEX idx_tenant_id (tenant_id),
                   ADD FOREIGN KEY (tenant_id) REFERENCES companies(company_id) ON DELETE CASCADE",
        
        'sqlite' => "ALTER TABLE inventory ADD COLUMN tenant_id INTEGER NOT NULL DEFAULT 0"
    ],
    
    'add_tenant_to_purchase_orders' => [
        'mysql' => "ALTER TABLE purchase_orders ADD COLUMN tenant_id INT(11) NOT NULL DEFAULT 0 AFTER po_id,
                   ADD INDEX idx_tenant_id (tenant_id),
                   ADD FOREIGN KEY (tenant_id) REFERENCES companies(company_id) ON DELETE CASCADE",
        
        'sqlite' => "ALTER TABLE purchase_orders ADD COLUMN tenant_id INTEGER NOT NULL DEFAULT 0"
    ],
    
    'add_tenant_to_licenses' => [
        'mysql' => "ALTER TABLE company_licenses ADD COLUMN tenant_id INT(11) NOT NULL DEFAULT 0 AFTER license_id,
                   ADD INDEX idx_tenant_id (tenant_id),
                   ADD FOREIGN KEY (tenant_id) REFERENCES companies(company_id) ON DELETE CASCADE",
        
        'sqlite' => "ALTER TABLE company_licenses ADD COLUMN tenant_id INTEGER NOT NULL DEFAULT 0"
    ],
    
    'add_tenant_to_audit_logs' => [
        'mysql' => "ALTER TABLE audit_logs ADD COLUMN tenant_id INT(11) NOT NULL DEFAULT 0 AFTER log_id,
                   ADD INDEX idx_tenant_id (tenant_id),
                   ADD FOREIGN KEY (tenant_id) REFERENCES companies(company_id) ON DELETE CASCADE",
        
        'sqlite' => "ALTER TABLE audit_logs ADD COLUMN tenant_id INTEGER NOT NULL DEFAULT 0"
    ],
    
    // ============================================================================
    // 3. TENANT SYSTEM CONTROL TABLE
    // ============================================================================
    'update_system_control' => [
        'mysql' => "ALTER TABLE system_control 
                   MODIFY company_id INT(11) NOT NULL,
                   ADD UNIQUE INDEX idx_company_id (company_id)",
        
        'sqlite' => "-- SQLite: system_control already exists with company_id"
    ]
];

return $MIGRATIONS;
?>
