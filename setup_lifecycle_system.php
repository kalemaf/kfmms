<?php
include 'config.inc.php';

echo "<html><head><title>Setup Spare Parts Lifecycle System</title><style>
body { font-family: Arial, sans-serif; margin: 20px; }
.success { color: green; }
.error { color: red; }
.info { color: blue; }
</style></head><body><h1>Spare Parts Lifecycle Tracking Setup</h1>
<p>This script will create all necessary tables for comprehensive spare parts lifecycle management.</p>";

// Check if tables already exist
$existing_tables = [];
$result = mysqli_query($connection, "SHOW TABLES LIKE 'spare_parts_%'");
while ($row = mysqli_fetch_array($result)) {
    $existing_tables[] = $row[0];
}

$result = mysqli_query($connection, "SHOW TABLES LIKE 'lifecycle_%'");
while ($row = mysqli_fetch_array($result)) {
    $existing_tables[] = $row[0];
}

$result = mysqli_query($connection, "SHOW TABLES LIKE 'supplier_performance'");
while ($row = mysqli_fetch_array($result)) {
    $existing_tables[] = $row[0];
}

$result = mysqli_query($connection, "SHOW TABLES LIKE 'maintenance_performance'");
while ($row = mysqli_fetch_array($result)) {
    $existing_tables[] = $row[0];
}

$result = mysqli_query($connection, "SHOW TABLES LIKE 'equipment_reliability'");
while ($row = mysqli_fetch_array($result)) {
    $existing_tables[] = $row[0];
}

if (!empty($existing_tables)) {
    echo "<p class='info'>Some lifecycle tables already exist: " . implode(', ', $existing_tables) . "</p>";
    echo "<p>Continuing with setup...</p>";
}

// Execute the schema
$sql = file_get_contents('spare_parts_lifecycle_schema_no_fk.sql');

if (mysqli_multi_query($connection, $sql)) {
    echo "<h2>Setup Complete</h2><p class='success'>✓ Spare Parts Lifecycle Tracking System has been set up successfully!</p>
    <h3>Features Enabled:</h3><ul>
    <li>Installation tracking with serial numbers and batch/lot numbers</li>
    <li>Replacement tracking with failure analysis</li>
    <li>Supplier performance monitoring</li>
    <li>Maintenance team performance metrics</li>
    <li>Equipment reliability analysis</li>
    <li>Automated alerts for performance issues</li>
    <li>Comprehensive analytics dashboard</li>
    </ul>
    <h3>Next Steps:</h3><ol>
    <li><a href='index.php?nav=lifecycle'>View Analytics Dashboard</a></li>
    <li>Create a new work order and add spare parts with lifecycle tracking</li>
    <li>Monitor supplier and maintenance performance over time</li>
    </ol>
    <p><a href='index.php'>Return to Main Application</a></p>";
} else {
    echo "<h2 class='error'>Setup Failed</h2><p class='error'>Error: " . mysqli_error($connection) . "</p>";
}

// Consume remaining results
do {
    if ($result = mysqli_store_result($connection)) {
        mysqli_free_result($result);
    }
} while (mysqli_next_result($connection));

echo "</body></html>";
?>