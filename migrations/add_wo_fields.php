<?php
/* Migration script to add professional work order fields to work_orders table.
 * Run from CLI: php migrations/add_wo_fields.php
 * Or open in browser (as admin) to apply changes.
 */
include_once __DIR__ . '/../config.inc.php';

if (session_status() == PHP_SESSION_NONE) {
    @session_start();
}

$connection = $GLOBALS['connection'] ?? null;
if (!$connection) {
    // try including config again
    include_once __DIR__ . '/../config.inc.php';
    $connection = $GLOBALS['connection'] ?? null;
}
if (!$connection) {
    die("No database connection available. Run this from the application root where config.inc.php is accessible.");
}

$fields = [
    'asset_id' => "VARCHAR(128) DEFAULT NULL",
    'asset_name' => "VARCHAR(255) DEFAULT NULL",
    'location' => "VARCHAR(255) DEFAULT NULL",
    'department' => "VARCHAR(255) DEFAULT NULL",
    'problem_description' => "TEXT DEFAULT NULL",
    'failure_code' => "VARCHAR(128) DEFAULT NULL",
    'root_cause' => "TEXT DEFAULT NULL",
    'work_type' => "VARCHAR(50) DEFAULT NULL",
    'safety_requirements' => "TEXT DEFAULT NULL",
    'lockout_required' => "VARCHAR(10) DEFAULT NULL",
    'planned_hours' => "DECIMAL(8,2) DEFAULT NULL",
    'required_skills' => "VARCHAR(255) DEFAULT NULL",
    'estimated_cost' => "DECIMAL(10,2) DEFAULT NULL",
    'required_parts' => "TEXT DEFAULT NULL",
    'actual_start_time' => "DATETIME DEFAULT NULL",
    'actual_finish_time' => "DATETIME DEFAULT NULL",
    'downtime_duration' => "DECIMAL(8,2) DEFAULT NULL",
    'spare_parts_consumed' => "TEXT DEFAULT NULL",
    'labor_cost' => "DECIMAL(10,2) DEFAULT NULL",
    'material_cost' => "DECIMAL(10,2) DEFAULT NULL",
    'total_cost' => "DECIMAL(12,2) DEFAULT NULL",
    'downtime_cost' => "DECIMAL(12,2) DEFAULT NULL",
    'failure_type' => "VARCHAR(128) DEFAULT NULL",
    'cause_code' => "VARCHAR(128) DEFAULT NULL",
    'component_replaced' => "VARCHAR(255) DEFAULT NULL",
    'mttr_impact' => "VARCHAR(128) DEFAULT NULL",
    'repeat_failure' => "VARCHAR(10) DEFAULT NULL",
    'materials_summary' => "TEXT DEFAULT NULL",
    'notes' => "TEXT DEFAULT NULL"
];

echo "Applying migration: add professional work order fields to work_orders table\n\n";
foreach ($fields as $col => $type) {
    $check = mysqli_query($connection, "SHOW COLUMNS FROM `work_orders` LIKE '" . mysqli_real_escape_string($connection, $col) . "'");
    if ($check && mysqli_num_rows($check) > 0) {
        echo "- Column $col already exists, skipping.\n";
        continue;
    }
    $sql = "ALTER TABLE `work_orders` ADD COLUMN `$col` $type";
    if (mysqli_query($connection, $sql)) {
        echo "+ Added column $col\n";
    } else {
        echo "! Failed to add $col: " . mysqli_error($connection) . "\n";
    }
}

echo "\nMigration complete.\n";

?>