<?php
// Comprehensive equipment isolation test
include 'config.inc.php';
include 'common.inc.php';

echo "<h1>Equipment Page Full Test</h1>";

// Simulate new company user (tenant 11)
$_SESSION['tenant_id'] = 11;
$_SESSION['user_id'] = 58;

echo "<h2>1. Testing get_tenant_upload_path()</h2>";
$uploadDir = get_tenant_upload_path('equipment_photos');
echo "✓ Upload dir: $uploadDir<br>";

echo "<h2>2. Testing equipment list query</h2>";
$rows = safe_query_all('SELECT * FROM equipment ORDER BY description');
echo "Equipment count: " . count($rows) . "<br>";

echo "<h2>3. Testing edit equipment (id=1)</h2>";
$row = safe_query_row("SELECT * FROM equipment WHERE id=1 LIMIT 1");
if ($row) {
    echo "✗ Found equipment from other tenant! (Data leak)<br>";
} else {
    echo "✓ Cannot access equipment from other tenant (correct)<br>";
}

echo "<h2>4. Testing edit equipment (id=999)</h2>";
$row = safe_query_row("SELECT * FROM equipment WHERE id=999 LIMIT 1");
if ($row) {
    echo "Found: " . $row['description'] . "<br>";
} else {
    echo "✓ Non-existent ID returns null (correct)<br>";
}

// Test for company 1
$_SESSION['tenant_id'] = 1;
$_SESSION['user_id'] = 1;

echo "<h2>5. Testing company 1 (should see data)</h2>";
$rows = safe_query_all('SELECT * FROM equipment ORDER BY description');
echo "Equipment count: " . count($rows) . "<br>";
foreach ($rows as $e) {
    echo "- ID {$e['id']}: {$e['description']}<br>";
}

echo "<h2>6. Testing edit equipment (id=1) as company 1</h2>";
$row = safe_query_row("SELECT * FROM equipment WHERE id=1 LIMIT 1");
if ($row) {
    echo "✓ Found: {$row['description']} (tenant_id: {$row['tenant_id']})<br>";
} else {
    echo "✗ Not found<br>";
}

echo "<p><strong>✓ Equipment tenant isolation complete!</strong></p>";