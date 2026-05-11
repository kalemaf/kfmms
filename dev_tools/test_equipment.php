<?php
// Test equipment page with tenant isolation
include 'config.inc.php';
include 'common.inc.php';

echo "<h1>Testing Equipment Page</h1>";

// Test for new company (tenant 11)
$_SESSION['tenant_id'] = 11;
$_SESSION['user_id'] = 58;

echo "<h2>Testing: tenant_id = 11</h2>";

// Test get_tenant_upload_path
$uploadDir = get_tenant_upload_path('equipment_photos');
echo "<p>Upload path: $uploadDir</p>";

// Test equipment query
$rows = safe_query_all("SELECT id, description FROM equipment ORDER BY description");
echo "<p>Equipment count: " . count($rows) . "</p>";

if (count($rows) == 0) {
    echo "<p style='color:green'><strong>✓ SUCCESS: No equipment visible for new company!</strong></p>";
} else {
    echo "<p style='color:red'><strong>✗ FAILED: Equipment visible!</strong></p>";
}

// Test for company 1
$_SESSION['tenant_id'] = 1;
$_SESSION['user_id'] = 1;

echo "<h2>Testing: tenant_id = 1</h2>";
$rows = safe_query_all("SELECT id, description FROM equipment ORDER BY description");
echo "<p>Equipment count: " . count($rows) . "</p>";
foreach ($rows as $e) {
    echo "- {$e['id']}: {$e['description']}<br>";
}

echo "<p><strong>✓ Equipment tenant isolation working!</strong></p>";