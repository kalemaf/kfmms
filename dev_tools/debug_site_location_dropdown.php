<?php
require_once __DIR__ . '/config.inc.php';
require_once __DIR__ . '/common.inc.php';

$_SESSION['tenant_id'] = 1;
$tenant_id = 1;

echo "SITE/LOCATION DROPDOWN - DETAILED DEBUG\n";
echo str_repeat("=", 70) . "\n\n";

// Test 1: Direct database query
echo "1. DATABASE QUERY CHECK\n";
echo str_repeat("-", 70) . "\n";
$c = $GLOBALS['c'];
$query = "SELECT id, full_location, is_active, tenant_id FROM sites_locations WHERE is_active = 1 AND tenant_id = $tenant_id ORDER BY full_location";
echo "Query: $query\n\n";

$stmt = $c->query($query);
echo "Results:\n";
$count = 0;
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $count++;
    echo "  $count. id={$row['id']}, full_location={$row['full_location']}, is_active={$row['is_active']}, tenant_id={$row['tenant_id']}\n";
}
echo "\nTotal: $count records\n\n";

// Test 2: query_to_array function
echo "2. QUERY_TO_ARRAY FUNCTION CHECK\n";
echo str_repeat("-", 70) . "\n";
$sites_locations_list = query_to_array("SELECT id, full_location FROM sites_locations WHERE is_active = 1 AND tenant_id = $tenant_id ORDER BY full_location");
echo "Count: " . count($sites_locations_list) . "\n";
if (count($sites_locations_list) > 0) {
    echo "Sample item structure:\n";
    print_r($sites_locations_list[0]);
} else {
    echo "NO DATA RETURNED\n";
}

// Test 3: Form rendering
echo "\n3. FORM HTML RENDERING\n";
echo str_repeat("-", 70) . "\n";

ob_start();
?>
<select name="site_location_id" class="form-select" required>
    <option value="">Choose site/location</option>
    <?php foreach ($sites_locations_list as $location): ?>
        <option value="<?php echo htmlspecialchars($location['id']); ?>"><?php echo htmlspecialchars($location['full_location']); ?></option>
    <?php endforeach; ?>
</select>
<?php
$html = ob_get_clean();

// Count options
$option_count = substr_count($html, '<option');
echo "Total <option> tags: $option_count\n";
echo "Has 'required' attribute: " . (strpos($html, 'required') !== false ? 'YES' : 'NO') . "\n";
echo "Has 'name=\"site_location_id\"': " . (strpos($html, 'name="site_location_id"') !== false ? 'YES' : 'NO') . "\n\n";

echo "HTML:\n";
echo $html . "\n\n";

// Test 4: Form submission capture
echo "4. FORM SUBMISSION - EXPECTED BEHAVIOR\n";
echo str_repeat("-", 70) . "\n";
echo "When user selects a site/location:\n";
echo "  - POST param: site_location_id\n";
echo "  - Value: id of selected location (e.g., 1-7)\n";
echo "  - Validation: required (must have a value)\n\n";

// Test 5: Check if there's a form validation issue
echo "5. POTENTIAL ISSUES\n";
echo str_repeat("-", 70) . "\n";

// Check if the dropdown is in the right position in form
echo "Checking form structure...\n";

if (!isset($_GET['action']) || $_GET['action'] !== 'create') {
    echo "[~] Test not run in 'create' action context\n";
} else {
    // Simulate form load
    $_GET['action'] = 'create';
    $_SERVER['REQUEST_METHOD'] = 'GET';
    
    ob_start();
    include 'purchase_request.php';
    $page_html = ob_get_clean();
    
    // Check for site_location_id in the HTML
    $site_loc_count = substr_count($page_html, 'site_location_id');
    echo "[~] 'site_location_id' appears $site_loc_count times in page HTML\n";
    
    if (strpos($page_html, 'Requester & Organizational Info') !== false) {
        echo "[✓] Requester & Organizational Info section found\n";
    }
}

?>
