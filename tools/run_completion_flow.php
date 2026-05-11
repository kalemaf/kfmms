<?php
require_once __DIR__ . '/../config.inc.php';

$connection = $GLOBALS['connection'] ?? null;
if (!$connection) {
    die("No DB connection available via config.inc.php\n");
}

// Insert a work order with Completed status
$desc = "Automated completion test (script)";
$desc_sql = mysqli_real_escape_string($connection, $desc);
$descriptive = "Automated completion test descriptive_text";
$descriptive_sql = mysqli_real_escape_string($connection, $descriptive);
$complete_date = date('Y-m-d');

$ins = "INSERT INTO work_orders (description, descriptive_text, wo_status, complete_date) VALUES ('{$desc_sql}', '{$descriptive_sql}', 'Completed', '{$complete_date}')";
$res = mysqli_query($connection, $ins) or die("Insert failed: " . mysqli_error($connection) . "\nSQL: $ins\n");
$saved_id = mysqli_insert_id($connection);

printf("Inserted work_order id=%d\n", $saved_id);

// Run completion flow (adapted from save.php)
$actor = isset($_SESSION['user']) ? mysqli_real_escape_string($connection, $_SESSION['user']) : 'script';
$completed_date = $complete_date;

$upd_pm = "UPDATE pm_instances SET status='Completed', completed_date='" . mysqli_real_escape_string($connection, $completed_date) . "' WHERE wo_id = " . (int)$saved_id;
$pm_res = mysqli_query($connection, $upd_pm);
$pm_affected = mysqli_affected_rows($connection);

$esc_cols_q = mysqli_query($connection, "SHOW COLUMNS FROM work_order_escalations");
$esc_cols = array();
while ($c = mysqli_fetch_assoc($esc_cols_q)) { $esc_cols[] = $c['Field']; }
$esc_sets = array();
$esc_sets[] = "status='resolved'";
if (in_array('resolved_at', $esc_cols)) { $esc_sets[] = 'resolved_at=NOW()'; }
if (in_array('resolved_by', $esc_cols)) { $esc_sets[] = "resolved_by='" . mysqli_real_escape_string($connection, $actor) . "'"; }
$upd_esc = "UPDATE work_order_escalations SET " . implode(',', $esc_sets) . " WHERE wo_id = " . (int)$saved_id . " AND status!='resolved'";
$esc_res = mysqli_query($connection, $upd_esc);
$esc_affected = mysqli_affected_rows($connection);

$clear_escalated = "UPDATE work_orders SET escalated=0 WHERE wo_id = " . (int)$saved_id;
mysqli_query($connection, $clear_escalated);

$details = mysqli_real_escape_string($connection, "completed work_order");
$audit_ins = "INSERT INTO audit_logs (actor, action, target_type, target_id, details, created_at) VALUES ('" . mysqli_real_escape_string($connection, $actor) . "', 'complete', 'work_order', " . intval($saved_id) . ", '" . $details . "', NOW())";
mysqli_query($connection, $audit_ins);
$audit_id = mysqli_insert_id($connection);

printf("PM instances updated: %d\nEscalations resolved: %d\nInserted audit_log id: %d\n", $pm_affected, $esc_affected, $audit_id);

// Show work_order row
$q = mysqli_query($connection, "SELECT * FROM work_orders WHERE wo_id = " . (int)$saved_id);
$row = mysqli_fetch_assoc($q);
print_r($row);

mysqli_close($connection);

?>