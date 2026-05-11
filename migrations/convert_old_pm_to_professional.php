<?php
/**
 * Convert legacy PM tables (pm_schedules, pm_instances) to new professional PM tables
 * Usage:
 * 1) Run migrations/add_pm_professional_structure.php to create new tables
 * 2) Run this script: php migrations/convert_old_pm_to_professional.php
 */

include_once __DIR__ . '/../config.inc.php';

$c = $connection;
if (!$c) die("No DB connection available.\n");

function table_exists($c, $table) {
    $db = mysqli_real_escape_string($c, $GLOBALS['databaseName']);
    $t = mysqli_real_escape_string($c, $table);
    $sql = "SELECT COUNT(*) AS cnt FROM information_schema.tables WHERE table_schema='".$db."' AND table_name='".$t."'";
    $r = mysqli_query($c, $sql);
    $row = mysqli_fetch_assoc($r);
    return ((int)$row['cnt']) > 0;
}

echo "Starting conversion of legacy PM tables...\n";

if (!table_exists($c, 'pm_schedules') || !table_exists($c, 'pm_instances')) {
    echo "Legacy tables not found (pm_schedules / pm_instances). Nothing to convert.\n";
    exit(0);
}

if (!table_exists($c, 'pm_masters') || !table_exists($c, 'pm_schedule_log')) {
    echo "Target professional PM tables not found. Run migrations/add_pm_professional_structure.php first.\n";
    exit(1);
}

// Map legacy schedule id -> new pm_id
$map = [];

$sched_res = mysqli_query($c, "SELECT * FROM pm_schedules");
$created = 0;
while ($s = mysqli_fetch_assoc($sched_res)) {
    $pm_title = mysqli_real_escape_string($c, $s['title'] ?? ('Schedule '.$s['id']));
    $description = mysqli_real_escape_string($c, $s['description'] ?? '');

    // Determine frequency_type and time fields
    $frequency_type = ($s['schedule_type'] == 'meter') ? 'Meter-Based' : 'Time-Based';
    $time_frequency_unit = null;
    $time_frequency_value = null;
    if (!empty($s['frequency'])) {
        // map legacy enum values to professional values
        $f = $s['frequency'];
        if ($f == 'daily') { $time_frequency_unit = 'Daily'; $time_frequency_value = 1; }
        elseif ($f == 'weekly') { $time_frequency_unit = 'Weekly'; $time_frequency_value = 1; }
        elseif ($f == 'monthly') { $time_frequency_unit = 'Monthly'; $time_frequency_value = 1; }
    }

    $next_due = !empty($s['next_due']) ? date('Y-m-d H:i:s', strtotime($s['next_due'])) : NULL;
    $start_date = NULL;

    $sql = "INSERT INTO pm_masters (asset_id, asset_name, pm_title, description, maintenance_type, status, frequency_type, time_frequency_unit, time_frequency_value, start_date, next_due_date, created_date) VALUES ('".
        mysqli_real_escape_string($c, $s['equipment_id'] ?? '')."', '".
        mysqli_real_escape_string($c, $s['title'] ?? '')."', '".
        $pm_title."', '".
        $description."', 'Preventive', 'Active', '".$frequency_type."', '".
        ($time_frequency_unit ? $time_frequency_unit : '')."', ".($time_frequency_value ? (int)$time_frequency_value : 'NULL').", ".($start_date?"'".$start_date."'":"NULL").", ".($next_due?"'".$next_due."'":"NULL").", NOW())";

    if (mysqli_query($c, $sql)) {
        $new_pm_id = mysqli_insert_id($c);
        $map[(int)$s['id']] = $new_pm_id;
        $created++;
    } else {
        echo "Failed to create pm_master for schedule id {$s['id']}: " . mysqli_error($c) . "\n";
    }
}

echo "Created {$created} pm_master records.\n";

// Now convert instances -> pm_schedule_log
$inst_res = mysqli_query($c, "SELECT * FROM pm_instances");
$inst_count = 0;
while ($ins = mysqli_fetch_assoc($inst_res)) {
    $old_sched_id = (int)$ins['schedule_id'];
    if (!isset($map[$old_sched_id])) {
        echo "Skipping instance {$ins['id']}: no mapped pm_master for schedule {$old_sched_id}\n";
        continue;
    }
    $pm_id = (int)$map[$old_sched_id];
    $wo_id = isset($ins['wo_id']) ? (int)$ins['wo_id'] : NULL;
    $scheduled_date = !empty($ins['scheduled_date']) ? date('Y-m-d H:i:s', strtotime($ins['scheduled_date'])) : NULL;
    $created_date = !empty($ins['created_date']) ? date('Y-m-d H:i:s', strtotime($ins['created_date'])) : date('Y-m-d H:i:s');
    $due_date = !empty($ins['due_date']) ? date('Y-m-d H:i:s', strtotime($ins['due_date'])) : $scheduled_date;
    $completed_date = !empty($ins['completed_date']) ? date('Y-m-d H:i:s', strtotime($ins['completed_date'])) : NULL;
    $status = isset($ins['status']) ? $ins['status'] : 'Pending';

    $ins_sql = "INSERT INTO pm_schedule_log (pm_id, wo_id, scheduled_date, due_date, completed_date, status, created_date) VALUES (".
        $pm_id.", ".($wo_id ? $wo_id : 'NULL').", ".($scheduled_date?"'".$scheduled_date."'":"NULL").", ".($due_date?"'".$due_date."'":"NULL").", ".($completed_date?"'".$completed_date."'":"NULL").", '".mysqli_real_escape_string($c,$status)."', '".$created_date."')";

    if (mysqli_query($c, $ins_sql)) {
        $inst_count++;
    } else {
        echo "Failed to insert pm_schedule_log for instance {$ins['id']}: " . mysqli_error($c) . "\n";
    }
}

echo "Converted {$inst_count} instances into pm_schedule_log.\n";

echo "Conversion completed. Please review pm.php and pm_metrics.php for expected results.\n";

?>
