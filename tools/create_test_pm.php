<?php
// tools/create_test_pm.php
require_once __DIR__ . '/../config.inc.php';

$title = 'TEST PM AUTO ' . time();
$today = date('Y-m-d');
$desc = 'Automated PM schedule created by test script';

$sql = "INSERT INTO pm_schedules (title,description,schedule_type,frequency,next_due,active,created_by) VALUES ('" . mysqli_real_escape_string($connection, $title) . "', '" . mysqli_real_escape_string($connection, $desc) . "', 'time', 'monthly', '" . $today . "', 1, 'test-run')";

if ($connection->query($sql)) {
    $id = $connection->insert_id;
    echo "CREATED_SCHEDULE_ID:" . $id . "\n";
    exit(0);
} else {
    echo "ERROR_CREATING_SCHEDULE:" . $connection->error . "\n";
    exit(2);
}

?>
