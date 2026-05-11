<?php
// Check database status for work orders
include("../config.inc.php");
include("../common.inc.php");

if (session_status() == PHP_SESSION_NONE) {
  @session_start();
}

echo '<table border="1" cellpadding="5">';
echo '<tr><th>WO#</th><th>Status</th><th>Complete Date</th><th>Last Modified</th></tr>';

$query = "SELECT wo_id, wo_status, complete_date, submit_date FROM work_orders ORDER BY wo_id DESC LIMIT 10";
$result = mysqli_query($connection, $query);

if ($result && mysqli_num_rows($result) > 0) {
  while ($row = mysqli_fetch_assoc($result)) {
    echo '<tr>';
    echo '<td>' . htmlspecialchars($row['wo_id']) . '</td>';
    echo '<td>' . htmlspecialchars($row['wo_status']) . '</td>';
    echo '<td>' . htmlspecialchars($row['complete_date'] ?? '(empty)') . '</td>';
    echo '<td>' . htmlspecialchars($row['submit_date'] ?? '(empty)') . '</td>';
    echo '</tr>';
  }
} else {
  echo '<tr><td colspan="4">No work orders found</td></tr>';
}

echo '</table>';
echo '<p><small>Last 10 work orders</small></p>';
