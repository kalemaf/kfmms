<?php
/**
 * Professional PM Module Migration
 * Creates comprehensive Preventive Maintenance database structure
 *
 * PM Master Records + PM Tasks + PM Scheduling + PM Metrics
 */

include_once __DIR__ . '/../config.inc.php';

if (session_status() == PHP_SESSION_NONE) {
    @session_start();
}

$connection = $GLOBALS['c'] ?? null;
if (!$connection) {
    include_once __DIR__ . '/../config.inc.php';
    $connection = $GLOBALS['c'] ?? null;
}

if (!$connection) {
    die("No database connection available.");
}

// Determine database-specific syntax
global $db_type;
$auto_increment = ($db_type === 'sqlite') ? 'AUTOINCREMENT' : 'AUTO_INCREMENT';
$engine_clause = ($db_type === 'sqlite') ? '' : 'ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci';
$timestamp_default = ($db_type === 'sqlite') ? 'TEXT DEFAULT CURRENT_TIMESTAMP' : 'DATETIME DEFAULT CURRENT_TIMESTAMP';
$timestamp_update = ($db_type === 'sqlite') ? '' : 'ON UPDATE CURRENT_TIMESTAMP';

// Tables to create
$tables = [
    'pm_masters' => "
        CREATE TABLE IF NOT EXISTS pm_masters (
            pm_id INTEGER PRIMARY KEY {$auto_increment},
            asset_id VARCHAR(128),
            asset_name VARCHAR(255),
            pm_title VARCHAR(255) NOT NULL UNIQUE,
            description TEXT,
            maintenance_type VARCHAR(50) DEFAULT 'Preventive',
            status VARCHAR(20) DEFAULT 'Active',
            frequency_type VARCHAR(20) DEFAULT 'Time-Based',

            -- Time-Based Scheduling
            time_frequency_unit VARCHAR(20) DEFAULT 'Monthly',
            time_frequency_value INTEGER DEFAULT 30,

            -- Meter-Based Scheduling
            meter_type VARCHAR(128),
            meter_trigger_threshold DECIMAL(10,2),

            -- Hybrid calculation
            hybrid_logic VARCHAR(255),

            -- Scheduling Control
            start_date TEXT,
            next_due_date TEXT,
            grace_period_days INTEGER DEFAULT 3,

            -- Compliance Tracking
            last_completed_date TEXT,
            completion_count INTEGER DEFAULT 0,
            missed_count INTEGER DEFAULT 0,
            compliance_percentage DECIMAL(5,2) DEFAULT 100,
            average_delay_days DECIMAL(5,2) DEFAULT 0,

            -- Resource Planning
            planned_labor_hours DECIMAL(8,2),
            required_technician_skill VARCHAR(128),
            estimated_cost DECIMAL(10,2),

            -- Tracking
            created_date {$timestamp_default},
            modified_date {$timestamp_default} {$timestamp_update},
            created_by VARCHAR(128)
        ) {$engine_clause}
    ",

    'pm_tasks' => "
        CREATE TABLE IF NOT EXISTS pm_tasks (
            pm_task_id INTEGER PRIMARY KEY {$auto_increment},
            pm_id INTEGER NOT NULL,
            task_sequence INTEGER DEFAULT 1,
            task_description TEXT NOT NULL,
            estimated_labor_hours DECIMAL(8,2),
            required_skill VARCHAR(128),
            required_tools VARCHAR(255),
            safety_instructions TEXT,
            inspection_type VARCHAR(20) DEFAULT 'None',
            inspection_min_value DECIMAL(10,2),
            inspection_max_value DECIMAL(10,2),
            inspection_unit VARCHAR(50)
        ) {$engine_clause}
    ",

    'pm_required_parts' => "
        CREATE TABLE IF NOT EXISTS pm_required_parts (
            pm_part_id INTEGER PRIMARY KEY {$auto_increment},
            pm_id INTEGER NOT NULL,
            part_name VARCHAR(255),
            inventory_part_id INTEGER NULL,
            equipment_spare_id INTEGER NULL,
            quantity INTEGER DEFAULT 1,
            unit_cost DECIMAL(10,2),
            total_cost DECIMAL(10,2)
        ) {$engine_clause}
    ",

    'pm_schedule_log' => "
        CREATE TABLE IF NOT EXISTS pm_schedule_log (
            pm_log_id INTEGER PRIMARY KEY {$auto_increment},
            pm_id INTEGER NOT NULL,
            wo_id INTEGER,
            scheduled_date TEXT,
            due_date TEXT,
            completed_date TEXT,
            status VARCHAR(20) DEFAULT 'Pending',
            actual_labor_hours DECIMAL(8,2),
            actual_cost DECIMAL(10,2),
            delay_days INTEGER DEFAULT 0,
            notes TEXT,
            created_date {$timestamp_default}
        ) {$engine_clause}
    ",

    'pm_metrics' => "
        CREATE TABLE IF NOT EXISTS pm_metrics (
            pm_metric_id INTEGER PRIMARY KEY {$auto_increment},
            pm_id INTEGER NOT NULL,
            total_scheduled INTEGER DEFAULT 0,
            total_completed INTEGER DEFAULT 0,
            total_missed INTEGER DEFAULT 0,
            total_rescheduled INTEGER DEFAULT 0,
            compliance_percentage DECIMAL(5,2) DEFAULT 0,
            average_completion_days DECIMAL(5,2) DEFAULT 0,
            failures_prevented INTEGER DEFAULT 0,
            total_downtime_prevented_hours DECIMAL(10,2) DEFAULT 0,
            cost_savings DECIMAL(12,2) DEFAULT 0,
            calculated_date {$timestamp_default}
        ) {$engine_clause}
    "
];

// Execute table creation
echo "<h2>Professional PM Module Installation</h2>";
echo "<pre>";

foreach ($tables as $table_name => $sql) {
    try {
        $connection->exec($sql);
        echo "✓ Table '$table_name' created successfully\n";
    } catch (Exception $e) {
        echo "✗ Error creating '$table_name': " . $e->getMessage() . "\n";
    }
}

// Add PM reference to work_orders table if it doesn't exist
try {
    if ($db_type === 'sqlite') {
        $check_wo_pm = $connection->query("SELECT name FROM pragma_table_info('work_orders') WHERE name='pm_id'");
        $exists = $check_wo_pm && $check_wo_pm->fetch(PDO::FETCH_ASSOC);
    } else {
        $check_wo_pm = $connection->query("SHOW COLUMNS FROM work_orders WHERE Field='pm_id'");
        $exists = $check_wo_pm && $check_wo_pm->fetch(PDO::FETCH_ASSOC);
    }

    if (!$exists) {
        $add_pm_to_wo = "ALTER TABLE work_orders ADD COLUMN pm_id INTEGER DEFAULT NULL";
        $connection->exec($add_pm_to_wo);
        echo "✓ Added pm_id column to work_orders table\n";
    } else {
        echo "✓ pm_id column already exists in work_orders table\n";
    }
} catch (Exception $e) {
    echo "✗ Error checking/adding pm_id to work_orders: " . $e->getMessage() . "\n";
}

echo "</pre>";
echo "<p><strong>Professional PM Module installation complete!</strong></p>";
echo "<p><a href='../index.php'>Return to Home</a></p>";
