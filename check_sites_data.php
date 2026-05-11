<?php
require_once __DIR__ . '/config.inc.php';
require_once __DIR__ . '/common.inc.php';

$_SESSION['tenant_id'] = 1;

echo "Complete Sites/Locations Data Check\n";
echo str_repeat("=", 70) . "\n\n";

$query = "SELECT * FROM sites_locations WHERE tenant_id = 1 ORDER BY id";
$stmt = $GLOBALS['c']->query($query);

echo "Query: $query\n\n";

$count = 0;
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $count++;
    echo "Record $count:\n";
    echo "  id: " . $row['id'] . "\n";
    echo "  full_location: " . $row['full_location'] . "\n";
    echo "  is_active: " . $row['is_active'] . "\n";
    echo "  site_name: " . $row['site_name'] . "\n";
    echo "  location_name: " . $row['location_name'] . "\n";
    echo "  tenant_id: " . $row['tenant_id'] . "\n";
    echo "\n";
}

echo "\nTotal records with tenant_id=1: $count\n\n";

// Now check what the query with the same filter returns
echo str_repeat("-", 70) . "\n";
echo "Exact Query from purchase_request.php:\n\n";

$tenant_id = 1;
$filtered_query = "SELECT id, full_location FROM sites_locations WHERE is_active = 1 AND tenant_id = $tenant_id ORDER BY full_location";
echo "Query: $filtered_query\n\n";

$sites_locations_list = query_to_array($filtered_query);
echo "Result count: " . count($sites_locations_list) . "\n";

if (count($sites_locations_list) > 0) {
    echo "\nResults:\n";
    foreach ($sites_locations_list as $idx => $location) {
        echo "  " . ($idx+1) . ". " . $location['full_location'] . " (id=" . $location['id'] . ")\n";
    }
}

?>
