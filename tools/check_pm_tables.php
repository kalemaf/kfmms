<?php
require 'config.inc.php';

$r1 = $connection->query('SELECT COUNT(*) as cnt FROM pm_schedules');
$row1 = $r1->fetch_assoc();
echo "pm_schedules rows: " . $row1['cnt'] . "\n";

$r2 = $connection->query('SELECT COUNT(*) as cnt FROM pm_schedule_log');
$row2 = $r2->fetch_assoc();
echo "pm_schedule_log rows: " . $row2['cnt'] . "\n";

$r3 = $connection->query('SELECT COUNT(*) as cnt FROM pm_instances');
$row3 = $r3->fetch_assoc();
echo "pm_instances rows: " . $row3['cnt'] . "\n";

$r4 = $connection->query('SELECT COUNT(*) as cnt FROM pm_masters');
$row4 = $r4->fetch_assoc();
echo "pm_masters rows: " . $row4['cnt'] . "\n";

echo "\n---Active schedules (pm_schedules with next_due <= today)---\n";
$r5 = $connection->query("SELECT id, title, next_due FROM pm_schedules WHERE active=1 AND next_due <= '2026-02-17' LIMIT 10");
while($row = $r5->fetch_assoc()) {
    echo "ID {$row['id']}: {$row['title']} (due: {$row['next_due']})\n";
}
?>
