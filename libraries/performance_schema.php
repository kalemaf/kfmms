<?php
/**
 * Performance Monitoring System - Database Schema & Setup
 * 
 * Creates all necessary tables for technician/supervisor SLA performance tracking
 * Implements multi-tenant data isolation throughout
 */

/**
 * Create SLA Policies Table
 * Defines response and resolution time targets for each priority level
 */
function ensure_sla_policies_table($connection) {
    try {
        $stmt = $connection->query("SELECT name FROM sqlite_master WHERE type='table' AND name='sla_policies'");
        $exists = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$exists) {
            $sql = "
                CREATE TABLE sla_policies (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    tenant_id INTEGER NOT NULL DEFAULT 1,
                    priority_level VARCHAR(20) NOT NULL,
                    response_time_minutes INTEGER NOT NULL,
                    resolution_time_minutes INTEGER NOT NULL,
                    repeat_failure_window_days INTEGER NOT NULL DEFAULT 30,
                    description TEXT,
                    is_active INTEGER NOT NULL DEFAULT 1,
                    created_at TEXT DEFAULT CURRENT_TIMESTAMP,
                    updated_at TEXT DEFAULT CURRENT_TIMESTAMP,
                    UNIQUE(tenant_id, priority_level)
                )
            ";
            $connection->exec($sql);
            
            // Insert default SLA policies
            $insert_sql = "
                INSERT INTO sla_policies (tenant_id, priority_level, response_time_minutes, resolution_time_minutes, description)
                VALUES 
                    (1, 'Critical', 15, 240, 'Critical equipment down - 15 min response, 4 hrs resolution'),
                    (1, 'High', 30, 480, 'High priority - 30 min response, 8 hrs resolution'),
                    (1, 'Medium', 120, 1440, 'Medium priority - 2 hrs response, 24 hrs resolution'),
                    (1, 'Low', 480, 2880, 'Low priority - 8 hrs response, 48 hrs resolution')
            ";
            $connection->exec($insert_sql);
        } else {
            // Check for missing columns
            $existing = [];
            $stmt = $connection->query("PRAGMA table_info('sla_policies')");
            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
                $existing[] = $row['name'];
            }
            
            if (!in_array('tenant_id', $existing, true)) {
                $connection->exec('ALTER TABLE sla_policies ADD COLUMN tenant_id INTEGER NOT NULL DEFAULT 1');
            }
        }
    } catch (Exception $e) {
        error_log("Error creating sla_policies table: " . $e->getMessage());
    }
}

/**
 * Create Work Order SLA Tracking Table
 * Tracks SLA compliance for each work order
 */
function ensure_work_order_sla_table($connection) {
    try {
        $stmt = $connection->query("SELECT name FROM sqlite_master WHERE type='table' AND name='work_order_sla'");
        $exists = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$exists) {
            $sql = "
                CREATE TABLE work_order_sla (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    tenant_id INTEGER NOT NULL DEFAULT 1,
                    work_order_id INTEGER NOT NULL,
                    sla_policy_id INTEGER NOT NULL,
                    assigned_at TEXT NOT NULL,
                    assigned_to_technician_id INTEGER,
                    acknowledged_at TEXT,
                    started_at TEXT,
                    completed_at TEXT,
                    closed_at TEXT,
                    response_sla_met INTEGER NOT NULL DEFAULT 0,
                    completion_sla_met INTEGER NOT NULL DEFAULT 0,
                    response_delay_minutes INTEGER,
                    completion_delay_minutes INTEGER,
                    response_time_minutes INTEGER,
                    completion_time_minutes INTEGER,
                    is_overdue INTEGER NOT NULL DEFAULT 0,
                    created_at TEXT DEFAULT CURRENT_TIMESTAMP,
                    updated_at TEXT DEFAULT CURRENT_TIMESTAMP,
                    UNIQUE(tenant_id, work_order_id)
                )
            ";
            $connection->exec($sql);
        } else {
            $existing = [];
            $stmt = $connection->query("PRAGMA table_info('work_order_sla')");
            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
                $existing[] = $row['name'];
            }
            
            if (!in_array('tenant_id', $existing, true)) {
                $connection->exec('ALTER TABLE work_order_sla ADD COLUMN tenant_id INTEGER NOT NULL DEFAULT 1');
            }
        }
    } catch (Exception $e) {
        error_log("Error creating work_order_sla table: " . $e->getMessage());
    }
}

/**
 * Create Repeat Failures Table
 * Tracks when same asset/fault code reoccurs within SLA window
 */
function ensure_repeat_failures_table($connection) {
    try {
        $stmt = $connection->query("SELECT name FROM sqlite_master WHERE type='table' AND name='repeat_failures'");
        $exists = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$exists) {
            $sql = "
                CREATE TABLE repeat_failures (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    tenant_id INTEGER NOT NULL DEFAULT 1,
                    asset_id INTEGER NOT NULL,
                    original_work_order_id INTEGER NOT NULL,
                    original_technician_id INTEGER,
                    repeat_work_order_id INTEGER NOT NULL,
                    repeat_technician_id INTEGER,
                    failure_category TEXT,
                    days_between_failures INTEGER,
                    is_same_technician INTEGER NOT NULL DEFAULT 0,
                    notes TEXT,
                    created_at TEXT DEFAULT CURRENT_TIMESTAMP
                )
            ";
            $connection->exec($sql);
        } else {
            $existing = [];
            $stmt = $connection->query("PRAGMA table_info('repeat_failures')");
            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
                $existing[] = $row['name'];
            }
            
            if (!in_array('tenant_id', $existing, true)) {
                $connection->exec('ALTER TABLE repeat_failures ADD COLUMN tenant_id INTEGER NOT NULL DEFAULT 1');
            }
        }
    } catch (Exception $e) {
        error_log("Error creating repeat_failures table: " . $e->getMessage());
    }
}

/**
 * Create Technician Performance Metrics Table (Cache)
 * Stores calculated performance metrics for quick dashboard loading
 */
function ensure_technician_performance_table($connection) {
    try {
        $stmt = $connection->query("SELECT name FROM sqlite_master WHERE type='table' AND name='technician_performance'");
        $exists = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$exists) {
            $sql = "
                CREATE TABLE technician_performance (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    tenant_id INTEGER NOT NULL DEFAULT 1,
                    technician_id INTEGER NOT NULL,
                    period_start TEXT NOT NULL,
                    period_end TEXT NOT NULL,
                    period_type VARCHAR(10) NOT NULL DEFAULT 'monthly',
                    total_assigned INTEGER NOT NULL DEFAULT 0,
                    total_completed INTEGER NOT NULL DEFAULT 0,
                    total_overdue INTEGER NOT NULL DEFAULT 0,
                    response_sla_met INTEGER NOT NULL DEFAULT 0,
                    completion_sla_met INTEGER NOT NULL DEFAULT 0,
                    repeat_failure_count INTEGER NOT NULL DEFAULT 0,
                    response_sla_percentage DECIMAL(5,2) NOT NULL DEFAULT 0,
                    completion_sla_percentage DECIMAL(5,2) NOT NULL DEFAULT 0,
                    first_time_fix_percentage DECIMAL(5,2) NOT NULL DEFAULT 0,
                    completion_rate_percentage DECIMAL(5,2) NOT NULL DEFAULT 0,
                    mttr_hours DECIMAL(10,2),
                    average_response_time_minutes DECIMAL(10,2),
                    overall_score DECIMAL(5,2) NOT NULL DEFAULT 0,
                    rating VARCHAR(10) NOT NULL DEFAULT 'Pending',
                    is_current INTEGER NOT NULL DEFAULT 1,
                    created_at TEXT DEFAULT CURRENT_TIMESTAMP,
                    updated_at TEXT DEFAULT CURRENT_TIMESTAMP,
                    UNIQUE(tenant_id, technician_id, period_start, period_end, period_type)
                )
            ";
            $connection->exec($sql);
        } else {
            $existing = [];
            $stmt = $connection->query("PRAGMA table_info('technician_performance')");
            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
                $existing[] = $row['name'];
            }
            
            if (!in_array('tenant_id', $existing, true)) {
                $connection->exec('ALTER TABLE technician_performance ADD COLUMN tenant_id INTEGER NOT NULL DEFAULT 1');
            }
        }
    } catch (Exception $e) {
        error_log("Error creating technician_performance table: " . $e->getMessage());
    }
}

/**
 * Create Performance History Table (for trends)
 * Stores historical performance data for trend analysis
 */
function ensure_performance_history_table($connection) {
    try {
        $stmt = $connection->query("SELECT name FROM sqlite_master WHERE type='table' AND name='performance_history'");
        $exists = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$exists) {
            $sql = "
                CREATE TABLE performance_history (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    tenant_id INTEGER NOT NULL DEFAULT 1,
                    technician_id INTEGER NOT NULL,
                    period_date TEXT NOT NULL,
                    daily_assignments INTEGER,
                    daily_completed INTEGER,
                    daily_sla_met INTEGER,
                    daily_overall_score DECIMAL(5,2),
                    created_at TEXT DEFAULT CURRENT_TIMESTAMP,
                    UNIQUE(tenant_id, technician_id, period_date)
                )
            ";
            $connection->exec($sql);
        } else {
            $existing = [];
            $stmt = $connection->query("PRAGMA table_info('performance_history')");
            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
                $existing[] = $row['name'];
            }
            
            if (!in_array('tenant_id', $existing, true)) {
                $connection->exec('ALTER TABLE performance_history ADD COLUMN tenant_id INTEGER NOT NULL DEFAULT 1');
            }
        }
    } catch (Exception $e) {
        error_log("Error creating performance_history table: " . $e->getMessage());
    }
}

/**
 * Initialize all performance monitoring tables
 */
function initialize_performance_monitoring_tables($connection) {
    ensure_sla_policies_table($connection);
    ensure_work_order_sla_table($connection);
    ensure_repeat_failures_table($connection);
    ensure_technician_performance_table($connection);
    ensure_performance_history_table($connection);
}
?>
