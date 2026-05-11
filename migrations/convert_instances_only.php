<?php
/**
 * Convert existing legacy pm_instances into professional pm_schedule_log entries.
 * Attempts to map pm_instances.schedule_id -> pm_masters.pm_id by matching pm_schedules.title/equipment_id.
 */
include_once __DIR__ . '/../config.inc.php';
$c = $connection;
if (!$c) die("No DB connection\n");

function table_exists($c,$t){$db=mysqli_real_escape_string($c,$GLOBALS['databaseName']);$t=mysqli_real_escape_string($c,$t);$r=mysqli_query($c,"SELECT COUNT(*) AS cnt FROM information_schema.tables WHERE table_schema='".$db."' AND table_name='".$t."'");$row=mysqli_fetch_assoc($r);return ((int)$row['cnt'])>0;}

if (!table_exists($c,'pm_instances')) { echo "No pm_instances table found.\n"; exit; }
if (!table_exists($c,'pm_schedule_log')) { echo "No pm_schedule_log target table found.\n"; exit; }

$res = mysqli_query($c, "SELECT pi.* , ps.title as sched_title, ps.equipment_id as equipment_id FROM pm_instances pi LEFT JOIN pm_schedules ps ON pi.schedule_id = ps.id");
$count = 0;
while($ins = mysqli_fetch_assoc($res)){
    // try to find matching pm_id
    $pm_id = null;
    if (!empty($ins['equipment_id'])){
        $r = mysqli_query($c, "SELECT pm_id FROM pm_masters WHERE asset_id = '".mysqli_real_escape_string($c,$ins['equipment_id'])."' LIMIT 1");
        if ($r && ($row = mysqli_fetch_assoc($r))) $pm_id = $row['pm_id'];
    }
    if (!$pm_id && !empty($ins['sched_title'])){
        $r = mysqli_query($c, "SELECT pm_id FROM pm_masters WHERE pm_title = '".mysqli_real_escape_string($c,$ins['sched_title'])."' LIMIT 1");
        if ($r && ($row = mysqli_fetch_assoc($r))) $pm_id = $row['pm_id'];
    }
    if (!$pm_id){
        // fallback: pick any pm_master
        $r = mysqli_query($c, "SELECT pm_id FROM pm_masters LIMIT 1");
        if ($r && ($row = mysqli_fetch_assoc($r))) $pm_id = $row['pm_id'];
    }

    if (!$pm_id) { echo "No pm_master found to map instance {$ins['id']}. Skipping.\n"; continue; }

    // Avoid duplicate insertion by checking wo_id or scheduled_date
    $chk = mysqli_query($c, "SELECT COUNT(*) AS cnt FROM pm_schedule_log WHERE wo_id = ".($ins['wo_id']? (int)$ins['wo_id'] : 0));
    $has = ($chk && ($rchk = mysqli_fetch_assoc($chk))) ? (int)$rchk['cnt'] : 0;
    if ($has > 0) { echo "Instance {$ins['id']} already converted (wo_id present).\n"; continue; }

    $scheduled_date = $ins['scheduled_date'] ? date('Y-m-d H:i:s', strtotime($ins['scheduled_date'])) : NULL;
    $due_date = $ins['due_date'] ? date('Y-m-d H:i:s', strtotime($ins['due_date'])) : $scheduled_date;
    $created_date = $ins['created_date'] ? date('Y-m-d H:i:s', strtotime($ins['created_date'])) : date('Y-m-d H:i:s');
    $completed_date = $ins['completed_date'] ? date('Y-m-d H:i:s', strtotime($ins['completed_date'])) : NULL;
    $status = $ins['status'] ?? 'Pending';
    $wo_id = $ins['wo_id'] ? (int)$ins['wo_id'] : 'NULL';

    $ins_sql = "INSERT INTO pm_schedule_log (pm_id, wo_id, scheduled_date, due_date, completed_date, status) VALUES (".$pm_id.", ".($wo_id).", ".($scheduled_date?"'".$scheduled_date."'":"NULL").", ".($due_date?"'".$due_date."'":"NULL").", ".($completed_date?"'".$completed_date."'":"NULL").", '".mysqli_real_escape_string($c,$status)."')";
    if (mysqli_query($c,$ins_sql)){
        $count++;
    } else {
        echo "Failed to insert pm_schedule_log for instance {$ins['id']}: " . mysqli_error($c) . "\n";
    }
}

echo "Converted {$count} instances into pm_schedule_log.\n";
