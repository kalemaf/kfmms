<?php
/**
 * Populate missing SLA due dates and complete dates for work orders
 */
require_once __DIR__ . '/config.inc.php';
require_once __DIR__ . '/common.inc.php';

if (!$connection) {
    die("Database connection failed");
}

echo "<h2>SLA Data Population</h2>";

// 1. Populate missing sla_due_date with submit_date + 2 days
$updateSlaQuery = "
    UPDATE work_orders 
    SET sla_due_date = DATE_ADD(submit_date, INTERVAL 2 DAY)
    WHERE sla_due_date IS NULL AND submit_date IS NOT NULL
";

if ($connection->query($updateSlaQuery)) {
    $affected = $connection->affected_rows;
    echo "<p style='color:green;'><strong>✓ Updated $affected work orders with SLA due date (submit_date + 2 days)</strong></p>";
} else {
    echo "<p style='color:red;'><strong>✗ Error updating SLA due dates: " . $connection->error . "</strong></p>";
}

// 2. Populate missing complete_date for Completed work orders with submit_date
$updateCompleteQuery = "
    UPDATE work_orders 
    SET complete_date = submit_date
    WHERE wo_status IN ('Completed', 'Closed') 
    AND complete_date IS NULL 
    AND submit_date IS NOT NULL
";

if ($connection->query($updateCompleteQuery)) {
    $affected = $connection->affected_rows;
    echo "<p style='color:green;'><strong>✓ Updated $affected completed work orders with complete date (set to submit_date)</strong></p>";
} else {
    echo "<p style='color:red;'><strong>✗ Error updating complete dates: " . $connection->error . "</strong></p>";
}

// 3. Verify the updates
echo "<h3>Verification After Update:</h3>";

$verifyQuery = "
    SELECT 
        SUM(CASE
            WHEN wo_status IN ('Completed','Closed')
                 AND complete_date IS NOT NULL
                 AND complete_date <= COALESCE(sla_due_date, DATE_ADD(submit_date, INTERVAL 1 DAY))
            THEN 1 ELSE 0 END) AS completed,
        SUM(CASE
            WHEN wo_status NOT IN ('Completed','Closed','Rejected','Canceled')
                 AND sla_due_date IS NOT NULL
                 AND NOW() <= sla_due_date
            THEN 1 ELSE 0 END) AS on_track,
        SUM(CASE
            WHEN (wo_status NOT IN ('Completed','Closed','Rejected','Canceled')
                    AND sla_due_date IS NOT NULL
                    AND NOW() > sla_due_date)
                 OR (wo_status IN ('Completed','Closed')
                    AND complete_date IS NOT NULL
                    AND sla_due_date IS NOT NULL
                    AND complete_date > sla_due_date)
            THEN 1 ELSE 0 END) AS breached,
        COUNT(*) AS total
    FROM work_orders
";

$verifyResult = $connection->query($verifyQuery);
if ($verifyResult) {
    $stats = $verifyResult->fetch_assoc();
    echo "<table border='1' cellpadding='10' style='border-collapse:collapse;'>";
    echo "<tr><td><strong>Total Work Orders:</strong></td><td>" . $stats['total'] . "</td></tr>";
    echo "<tr><td><strong>Completed (On Time):</strong></td><td>" . ($stats['completed'] ?? 0) . "</td></tr>";
    echo "<tr><td><strong>On Track:</strong></td><td>" . ($stats['on_track'] ?? 0) . "</td></tr>";
    echo "<tr><td><strong>Breached:</strong></td><td>" . ($stats['breached'] ?? 0) . "</td></tr>";
    echo "</table>";
}

// 4. Show some sample work orders after update
echo "<h3>Sample Work Orders After Update:</h3>";
$sampleResult = $connection->query("
    SELECT wo_id, wo_status, submit_date, sla_due_date, complete_date
    FROM work_orders 
    ORDER BY wo_id DESC
    LIMIT 10
");

echo "<table border='1' cellpadding='8' style='border-collapse:collapse;'>";
echo "<tr><th>WO ID</th><th>Status</th><th>Submit Date</th><th>SLA Due</th><th>Complete Date</th></tr>";
while ($row = $sampleResult->fetch_assoc()) {
    echo "<tr>";
    echo "<td>" . htmlspecialchars($row['wo_id']) . "</td>";
    echo "<td>" . htmlspecialchars($row['wo_status']) . "</td>";
    echo "<td>" . htmlspecialchars($row['submit_date']) . "</td>";
    echo "<td>" . htmlspecialchars($row['sla_due_date'] ?: 'NULL') . "</td>";
    echo "<td>" . htmlspecialchars($row['complete_date'] ?: 'NULL') . "</td>";
    echo "</tr>";
}
echo "</table>";

echo "<h3>SLA Data is now populated. The SLA Status Distribution chart should now show data.</h3>";

?>
