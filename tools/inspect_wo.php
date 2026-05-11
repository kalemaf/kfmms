<?php
// tools/inspect_wo.php
require_once __DIR__ . '/../config.inc.php';

$id = isset($argv[1]) ? (int)$argv[1] : 0;
if (!$id) { echo "Usage: php inspect_wo.php <wo_id>\n"; exit(2); }

$res = mysqli_query($connection, "SELECT * FROM work_orders WHERE wo_id=" . intval($id) . " LIMIT 1");
echo "--WORK_ORDER wo_id={$id}--\n";
if ($res && ($row = mysqli_fetch_assoc($res))) {
    foreach ($row as $k => $v) echo "$k: $v\n";
} else {
    echo "NO_WORK_ORDER_FOUND\n";
}

echo "\n--PM_INSTANCES WHERE wo_id={$id}--\n";
$r2 = mysqli_query($connection, "SELECT * FROM pm_instances WHERE wo_id=" . intval($id));
if ($r2 && mysqli_num_rows($r2) > 0) {
    while ($r = mysqli_fetch_assoc($r2)) {
        foreach ($r as $k => $v) echo "$k: $v\n";
        echo "----\n";
    }
} else {
    echo "<none>\n";
}

// Show any pm_instances that were created on the same date as the WO submit_date (possible unlinked instance)
$submit = null;
$wr = mysqli_query($connection, "SELECT submit_date FROM work_orders WHERE wo_id=" . intval($id) . " LIMIT 1");
if ($wr && ($wrrow = mysqli_fetch_assoc($wr))) { $submit = $wrrow['submit_date']; }
if ($submit) {
    echo "\n--PM_INSTANCES WITH created_date='{$submit}' (potentially related) --\n";
    $r3 = mysqli_query($connection, "SELECT * FROM pm_instances WHERE created_date='" . mysqli_real_escape_string($connection, $submit) . "'");
    if ($r3 && mysqli_num_rows($r3) > 0) {
        while ($r = mysqli_fetch_assoc($r3)) {
            foreach ($r as $k => $v) echo "$k: $v\n";
            echo "----\n";
        }
    } else {
        echo "<none>\n";
    }
}

// Tail logs for this WO
echo "\n--completion_workflow.log lines matching WO#{$id} --\n";
$log = __DIR__ . '/../logs/completion_workflow.log';
if (file_exists($log)) {
    $lines = file($log, FILE_IGNORE_NEW_LINES|FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $ln) {
        if (strpos($ln, "WO#{$id}") !== false) echo $ln . "\n";
    }
} else {
    echo "<log missing>\n";
}

echo "\n--email_send.log lines matching WO#{$id} --\n";
$elog = __DIR__ . '/../logs/email_send.log';
if (file_exists($elog)) {
    $lines = file($elog, FILE_IGNORE_NEW_LINES|FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $ln) {
        if (strpos($ln, "WO#{$id}") !== false || strpos($ln, "work_order_{$id}.pdf") !== false) echo $ln . "\n";
    }
} else {
    echo "<email log missing>\n";
}

// Check professional PM tables (pm_schedule_log) if present
echo "\n--PROFESSIONAL PM: pm_schedule_log rows for wo_id={$id} --\n";
$chk = mysqli_query($connection, "SHOW TABLES LIKE 'pm_schedule_log'");
if ($chk && mysqli_num_rows($chk) > 0) {
    $rpl = mysqli_query($connection, "SELECT * FROM pm_schedule_log WHERE wo_id=" . intval($id));
    if ($rpl && mysqli_num_rows($rpl) > 0) {
        while ($rp = mysqli_fetch_assoc($rpl)) {
            foreach ($rp as $k => $v) echo "$k: $v\n";
            echo "----\n";
        }
    } else {
        echo "<none>\n";
    }
} else {
    echo "<pm_schedule_log table not present>\n";
}

?>
