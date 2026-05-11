<?php
// Debug equipment page
include 'config.inc.php';
include 'common.inc.php';

echo "<h1>Debug Equipment Page</h1>";

$_SESSION['tenant_id'] = 11;
$_SESSION['user_id'] = 58;

echo "<h2>1. Testing get_warehouses()</h2>";
try {
    $warehouses = get_warehouses($connection);
    echo "Warehouses count: " . count($warehouses) . "<br>";
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "<br>";
}

echo "<h2>2. Testing equipment list</h2>";
try {
    $equipment = safe_query_all('SELECT * FROM equipment ORDER BY description');
    echo "Equipment count: " . count($equipment) . "<br>";
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "<br>";
}

echo "<h2>3. Testing safe_query_row for edit</h2>";
try {
    $row = safe_query_row("SELECT * FROM equipment WHERE id=1 LIMIT 1");
    if ($row) {
        echo "Found: " . $row['description'] . "<br>";
    } else {
        echo "Not found (expected for new company)<br>";
    }
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "<br>";
}

echo "<h2>4. Full equipment list query</h2>";
try {
    $query = 'SELECT * FROM equipment ORDER BY description';
    $filtered = apply_tenant_filter($query);
    echo "Filtered query: $filtered<br>";
    
    $rows = safe_query_all($query);
    echo "Rows: " . count($rows) . "<br>";
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "<br>";
}

echo "<p>Done!</p>";