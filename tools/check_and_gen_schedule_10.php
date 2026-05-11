<?php
require_once 'config.inc.php';

$result = $connection->query("SELECT id, title, next_due FROM pm_schedules WHERE id=10");
$r = $result->fetch_assoc();
$today = date('Y-m-d');
$due = $r['next_due'];
$is_due = ($due <= $today);

echo "Schedule 10: '{$r['title']}'\n";
echo "  Next Due: $due\n";
echo "  Today: $today\n";
echo "  Due Now: " . ($is_due ? "YES ✓" : "NO ✗ (future date)") . "\n\n";

if ($is_due) {
    echo "Running generate_pm.php...\n";
    system('php generate_pm.php 2>&1');
    
    echo "\n=== Checking if work order was created ===\n";
    $result = $connection->query("SELECT id, wo_id FROM pm_instances WHERE schedule_id=10");
    if ($result && $result->num_rows > 0) {
        $r = $result->fetch_assoc();
        echo "✓ Work Order #" . $r['wo_id'] . " created\n";
    } else {
        echo "✗ No work order found\n";
    }
} else {
    echo "Schedule is NOT yet due (date is in the future)\n";
    echo "The dashboard shows 'Not Started' because no work order will be generated\n";
    echo "until next_due date arrives.\n";
}
?>
