<?php
/* Migration script to add department field to equipment table.
 * Run from CLI: php migrations/add_department_to_equipment.php
 * Or open in browser (as admin) to apply changes.
 */
include_once __DIR__ . '/../config.inc.php';

if (session_status() == PHP_SESSION_NONE) {
    @session_start();
}

$connection = $GLOBALS['connection'] ?? null;
if (!$connection) {
    // try including config again
    include_once __DIR__ . '/../config.inc.php';
    $connection = $GLOBALS['connection'] ?? null;
}
if (!$connection) {
    die("No database connection available. Run this from the application root where config.inc.php is accessible.");
}

// Check if department column already exists
$result = mysqli_query($connection, "SHOW COLUMNS FROM equipment LIKE 'department'");
$exists = mysqli_num_rows($result) > 0;

if ($exists) {
    echo "<h2>Department field already exists in equipment table</h2>";
    echo "<p>No changes needed.</p>";
} else {
    echo "<h2>Adding department field to equipment table...</h2>";

    $sql = "ALTER TABLE equipment ADD COLUMN department VARCHAR(255) DEFAULT NULL AFTER location";

    if (mysqli_query($connection, $sql)) {
        echo "<p style='color:green;'>✓ Successfully added department field to equipment table</p>";
        echo "<p>You can now enter department information for each piece of equipment.</p>";
    } else {
        echo "<p style='color:red;'>✗ Error adding department field: " . mysqli_error($connection) . "</p>";
    }
}

echo "<br><a href='../equipment.php'>Return to Equipment Management</a>";
?>