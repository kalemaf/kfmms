<?php
// tools/update_pm_schedule_log.php
require_once __DIR__ . '/../config.inc.php';

$id = isset($argv[1]) ? (int)$argv[1] : 0;
if (!$id) { echo "Usage: php update_pm_schedule_log.php <wo_id>\n"; exit(2); }

$date = date('Y-m-d');
$sql = "UPDATE pm_schedule_log SET status='Completed', completed_date='" . mysqli_real_escape_string($connection, $date) . "' WHERE wo_id=" . intval($id);
if ($connection->query($sql)) {
    echo "UPDATED_ROWS:" . $connection->affected_rows . "\n";
} else {
    echo "ERROR:" . $connection->error . "\n";
}

?>
