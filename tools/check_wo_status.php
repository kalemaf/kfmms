<?php
// Check WO #98 and related PM records
require_once 'config.inc.php';

$wo_id = 98;

// Check work_orders table
$result = $connection->query("SELECT wo_id, wo_status, complete_date FROM work_orders WHERE wo_id = $wo_id");
if ($result && $row = $result->fetch_assoc()) {
    echo "=== WORK_ORDERS ===\n";
    echo "WO #$wo_id: status='{$row['wo_status']}', complete_date='{$row['complete_date']}'\n\n";
} else {
    echo "WO #$wo_id not found\n";
}

// Check pm_instances for this WO
$result = $connection->query("SELECT id, schedule_id, wo_id, status, completed_date FROM pm_instances WHERE wo_id = $wo_id");
if ($result && $result->num_rows > 0) {
    echo "=== PM_INSTANCES ===\n";
    while ($row = $result->fetch_assoc()) {
        echo "Instance ID {$row['id']}: schedule_id={$row['schedule_id']}, status='{$row['status']}', completed_date='{$row['completed_date']}'\n";
    }
} else {
    echo "No pm_instances found for WO #$wo_id\n";
}
echo "\n";

// Check pm_schedule_log for this WO
$result = $connection->query("SELECT pm_log_id, wo_id, status, completed_date FROM pm_schedule_log WHERE wo_id = $wo_id");
if ($result && $result->num_rows > 0) {
    echo "=== PM_SCHEDULE_LOG ===\n";
    while ($row = $result->fetch_assoc()) {
        echo "Log ID {$row['pm_log_id']}: status='{$row['status']}', completed_date='{$row['completed_date']}'\n";
    }
} else {
    echo "No pm_schedule_log found for WO #$wo_id\n";
}

// Check if there's a log entry for this WO completion
echo "\n=== COMPLETION_WORKFLOW.LOG ===\n";
$log_file = __DIR__ . '/../logs/completion_workflow.log';
if (file_exists($log_file)) {
    $lines = file($log_file);
    $found = false;
    foreach ($lines as $line) {
        if (strpos($line, "WO#$wo_id") !== false) {
            echo trim($line) . "\n";
            $found = true;
        }
    }
    if (!$found) {
        echo "No entries found for WO#$wo_id\n";
    }
} else {
    echo "Log file not found\n";
}
?>
