<?php
require_once 'config.inc.php';

echo "=== PM Schedule 'yyuyytytyty' (ID 10) ===\n";
$result = $connection->query("SELECT id, title, active, next_due, assigned_to FROM pm_schedules WHERE id=10");
if ($result && $r = $result->fetch_assoc()) {
    echo "ID: {$r['id']}, Title: {$r['title']}, Active: {$r['active']}, Next_Due: {$r['next_due']}, Assigned: {$r['assigned_to']}\n";
}

echo "\n=== PM Instances for Schedule 10 ===\n";
$result = $connection->query("SELECT id, schedule_id, wo_id, status FROM pm_instances WHERE schedule_id=10");
if ($result && $result->num_rows > 0) {
    while($r = $result->fetch_assoc()) {
        echo "Instance ID {$r['id']}: WO_ID={$r['wo_id']}, Status={$r['status']}\n";
    }
} else {
    echo "No pm_instances found - work order not generated yet\n";
}

echo "\n=== Why No Work Order? ===\n";
echo "Reason: generate_pm.php only runs when manually triggered or scheduled\n";
echo "To generate work order for schedule 10, run: php generate_pm.php\n";
?>
