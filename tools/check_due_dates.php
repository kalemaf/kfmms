<?php
require_once 'config.inc.php';

$today = date('Y-m-d');
echo "Today's Date: $today\n\n";

echo "=== Active Schedules and Their Due Dates ===\n";
$result = $connection->query("SELECT id, title, next_due, active FROM pm_schedules WHERE active=1 ORDER BY next_due ASC");
while($r = $result->fetch_assoc()) {
    $is_due = $r['next_due'] <= $today ? '✓ DUE' : '✗ Future';
    echo "ID {$r['id']}: '{$r['title']}' due {$r['next_due']} — $is_due\n";
}

echo "\n=== To Manually Generate WO for Schedule 11 ===\n";
echo "You can manually create a work order by:\n";
echo "1. Going to schedule 11 and clicking 'Generate Work Order'\n";
echo "2. OR run: php -r \"require 'config.inc.php'; \\\$connection->query('INSERT INTO work_orders (pm_id, descriptive_text, mechanic_id, submit_date, wo_status) VALUES(0, \\\"PM: Schedule 11\\\", 0, NOW(), \\\"New\\\")');\"\n";
?>
