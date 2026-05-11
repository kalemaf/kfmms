<?php
// Debug warehouses issue
include 'config.inc.php';
include 'common.inc.php';

echo "<h1>Debug Warehouses</h1>";

// Check actual data
echo "<h2>Warehouses Data</h2>";
$wh = $connection->query("SELECT * FROM warehouses")->fetchAll(PDO::FETCH_ASSOC);
print_r($wh);

echo "<h2>Testing filter manually</h2>";
$_SESSION['tenant_id'] = 1;

// Test the exact query from get_warehouses
$query = "SELECT w.*, COUNT(wl.id) as location_count FROM warehouses w
          LEFT JOIN warehouse_locations wl ON w.id = wl.warehouse_id
          WHERE w.is_active = 1
          GROUP BY w.id
          ORDER BY w.warehouse_name";
echo "Original: $query<br>";

$filtered = apply_tenant_filter($query);
echo "Filtered: $filtered<br>";

try {
    $res = $connection->query($filtered);
    $rows = $res->fetchAll(PDO::FETCH_ASSOC);
    echo "Rows: " . count($rows) . "<br>";
    print_r($rows);
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "<br>";
}