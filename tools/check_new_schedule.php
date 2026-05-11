<?php
require_once 'config.inc.php';

echo "=== Recently Created PM Schedules ===\n";
$result = $connection->query("SELECT id, title, active, next_due, assigned_to FROM pm_schedules ORDER BY id DESC LIMIT 10");
if ($result && $result->num_rows > 0) {
    while($r = $result->fetch_assoc()) {
        echo "ID: {$r['id']}, Title: {$r['title']}, Active: {$r['active']}, Next_Due: {$r['next_due']}, Assigned: {$r['assigned_to']}\n";
    }
} else {
    echo "No schedules found\n";
}
?>
