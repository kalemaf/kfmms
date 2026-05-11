<?php
require_once 'config.inc.php';

// Activate the new schedule
$connection->query("UPDATE pm_schedules SET active=1 WHERE id=9 AND title='ttttttt'");

echo "=== Schedule Activated ===\n";
$result = $connection->query("SELECT id, title, active, next_due FROM pm_schedules WHERE id=9");
if ($result && $r = $result->fetch_assoc()) {
    echo "ID: {$r['id']}, Title: {$r['title']}, Active: {$r['active']}, Next_Due: {$r['next_due']}\n";
}

echo "\n=== Now running generate_pm.php to create work order ===\n";
system('php generate_pm.php 2>&1');
?>
