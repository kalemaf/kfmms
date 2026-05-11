<?php
// simple seeder for MTTR/MTBF demo data
require_once __DIR__ . '/../config.inc.php';
session_save_path($session_save_path);

// pick two existing equipment IDs from the main equipment table if available
$eids = [];
$res = mysqli_query($connection, "SELECT id FROM equipment LIMIT 2");
while ($r = mysqli_fetch_assoc($res)) {
    $eids[] = $r['id'];
}
if (count($eids) < 2) {
    // fallback to arbitrary numeric ids
    $eids = [1,2];
}

function addIncident($eid, $start, $end) {
    global $connection;
    // insert into failures/repairs
    $connection->query("INSERT INTO failures (equipment_id,failure_datetime,description) VALUES($eid,'$start','demo failure')");
    $fid = $connection->insert_id;
    $connection->query("INSERT INTO repairs (failure_id,mechanic_id,start_datetime,end_datetime,notes) VALUES($fid,0,'$start','$end','demo repair')");
    // also add corresponding uptime record assuming 1 day afterwards
    $next = date('Y-m-d H:i:s', strtotime($end . ' +1 day'));
    $dur = strtotime($next) - strtotime($end);
    $connection->query("INSERT INTO Uptime_Record (equipment_id,start_time,end_time,uptime) VALUES($eid,'$end','$next',$dur)");
}

// add a few incidents on different weeks for each ID
$e1 = $eids[0];
$e2 = $eids[1];
addIncident($e1,'2026-02-01 08:00:00','2026-02-01 12:00:00');
addIncident($e1,'2026-02-10 09:00:00','2026-02-10 11:30:00');
addIncident($e1,'2026-02-20 07:30:00','2026-02-20 10:30:00');
addIncident($e2,'2026-02-05 14:00:00','2026-02-05 16:00:00');
addIncident($e2,'2026-02-18 10:00:00','2026-02-18 13:00:00');

echo "Seeded demo MTTR/MTBF data (including uptime records)\n";
