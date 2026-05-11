<?php
// tools/migrate_sync_pm_schedule_log.php
// Usage: php migrate_sync_pm_schedule_log.php
require_once __DIR__ . '/../config.inc.php';

error_reporting(E_ALL);
ini_set('display_errors', '1');

// Check table presence
$chk = mysqli_query($connection, "SHOW TABLES LIKE 'pm_schedule_log'");
if (!$chk || mysqli_num_rows($chk) == 0) {
    echo "pm_schedule_log table not present — nothing to do.\n";
    exit(0);
}

// Find mismatches between pm_schedule_log and pm_instances (by wo_id)
$sql = "SELECT p.pm_log_id, p.wo_id, p.status AS p_status, p.completed_date AS p_completed, pi.status AS pi_status, pi.completed_date AS pi_completed FROM pm_schedule_log p LEFT JOIN pm_instances pi ON p.wo_id = pi.wo_id WHERE pi.wo_id IS NOT NULL AND (CAST(p.status AS CHAR CHARACTER SET utf8mb4) COLLATE utf8mb4_general_ci <> CAST(pi.status AS CHAR CHARACTER SET utf8mb4) COLLATE utf8mb4_general_ci OR (pi.completed_date IS NOT NULL AND (p.completed_date IS NULL OR p.completed_date <> pi.completed_date)))";
$res = mysqli_query($connection, $sql);
if (!$res) {
    echo "Query failed: " . mysqli_error($connection) . "\n";
    exit(2);
}

$rows = [];
while ($r = mysqli_fetch_assoc($res)) $rows[] = $r;

if (count($rows) == 0) {
    echo "No mismatched pm_schedule_log rows found.\n";
    exit(0);
}

// Backup affected pm_schedule_log rows to CSV
$backupDir = __DIR__ . '/../logs/backups';
if (!is_dir($backupDir)) mkdir($backupDir, 0755, true);
$ts = date('Ymd_His');
$backupFile = $backupDir . '/pm_schedule_log_sync_backup_' . $ts . '.csv';
$fh = fopen($backupFile, 'w');
fputcsv($fh, array_keys($rows[0]));
foreach ($rows as $r) fputcsv($fh, $r);
fclose($fh);
echo "Backed up " . count($rows) . " rows to: logs/backups/" . basename($backupFile) . "\n";

// Build list of wo_ids to update
$wo_ids = array_map(function($r){ return (int)$r['wo_id']; }, $rows);
$wo_list = implode(',', $wo_ids);

// Perform update: copy status and completed_date from pm_instances to pm_schedule_log
$upd = "UPDATE pm_schedule_log p JOIN pm_instances pi ON p.wo_id = pi.wo_id SET p.status = pi.status, p.completed_date = pi.completed_date WHERE p.wo_id IN ($wo_list)";
$ok = mysqli_query($connection, $upd);
if (!$ok) {
    echo "Update failed: " . mysqli_error($connection) . "\n";
    exit(3);
}

echo "Synchronized pm_schedule_log rows for WO IDs: " . $wo_list . " — affected rows: " . mysqli_affected_rows($connection) . "\n";

// Log action
@file_put_contents(__DIR__ . '/../logs/completion_workflow.log', date('c') . " - migrate_sync_pm_schedule_log run — synced WO IDs: $wo_list\n", FILE_APPEND);

echo "Migration complete.\n";

?>
