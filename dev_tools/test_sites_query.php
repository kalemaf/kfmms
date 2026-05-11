<?php
require_once __DIR__ . '/config.inc.php';
require_once __DIR__ . '/common.inc.php';

$_SESSION['tenant_id'] = 1;

$tenant_id = (int)($_SESSION['tenant_id'] ?? 1);

echo "Test: Exact query from purchase_request.php\n";
echo str_repeat("=", 60) . "\n\n";

echo "tenant_id: $tenant_id\n\n";

$query = "SELECT id, full_location FROM sites_locations WHERE is_active = 1 AND tenant_id = $tenant_id ORDER BY full_location";
echo "Query: $query\n\n";

$sites_locations_list = query_to_array($query);

echo "Result count: " . count($sites_locations_list) . "\n\n";

if (count($sites_locations_list) > 0) {
    echo "First 5 items:\n";
    for ($i = 0; $i < min(5, count($sites_locations_list)); $i++) {
        $location = $sites_locations_list[$i];
        echo "  " . ($i+1) . ". id=" . $location['id'] . ", full_location=" . $location['full_location'] . "\n";
    }
} else {
    echo "NO ITEMS RETURNED!\n";
}

echo "\n" . str_repeat("-", 60) . "\n";
echo "HTML Simulation:\n\n";

echo '<select name="site_location_id" class="form-select" required>' . "\n";
echo '  <option value="">Choose site/location</option>' . "\n";

if (count($sites_locations_list) > 0) {
    foreach ($sites_locations_list as $location) {
        $id = htmlspecialchars($location['id']);
        $name = htmlspecialchars($location['full_location']);
        echo "  <option value=\"$id\">$name</option>\n";
    }
} else {
    echo "  <!-- NO OPTIONS -->\n";
}

echo '</select>' . "\n";

?>
