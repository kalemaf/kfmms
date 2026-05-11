<?php
require_once 'config.inc.php';

echo "=== Checking Dashboard Query Results ===\n";

// Run the exact dashboard query
$query = "SELECT 'legacy' as `system`, ps.id, ps.title, ps.next_due as `due_date`, m.lname, m.fname, GROUP_CONCAT(pi.wo_id SEPARATOR ',') as `wo_id`, CASE WHEN SUM(CASE WHEN pi.status = 'Pending' THEN 1 ELSE 0 END) > 0 THEN 'Pending' WHEN COUNT(pi.id) = 0 THEN 'Not Started' ELSE 'Completed' END as `status` FROM pm_schedules ps LEFT JOIN mechanics m ON ps.assigned_to = m.id LEFT JOIN pm_instances pi ON ps.id = pi.schedule_id WHERE ps.active=1 GROUP BY ps.id ORDER BY ps.next_due ASC LIMIT 20";
$result = $connection->query($query);

echo "Total rows: " . $result->num_rows . "\n\n";

$row_num = 1;
while($r = $result->fetch_assoc()) {
    echo "Row $row_num:\n";
    echo "  ID: {$r['id']}, Title: '{$r['title']}', Due: {$r['due_date']}, Mechanic: {$r['lname']} {$r['fname']}\n";
    echo "  WO: " . ($r['wo_id'] ? $r['wo_id'] : '-') . ", Status: {$r['status']}\n\n";
    $row_num++;
}
?>
