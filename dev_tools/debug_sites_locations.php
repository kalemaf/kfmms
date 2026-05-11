<?php
require_once __DIR__ . '/config.inc.php';
require_once __DIR__ . '/common.inc.php';

$_SESSION['tenant_id'] = 1;

$db_type = $GLOBALS['db_type'] ?? 'sqlite';
$c = $GLOBALS['c'];

echo "Debug: Sites Locations Dropdown Issue\n";
echo str_repeat("=", 60) . "\n\n";

// Check table structure
if ($db_type === 'sqlite') {
    echo "Checking sites_locations table columns...\n";
    $stmt = $c->query("PRAGMA table_info(sites_locations)");
    if ($stmt) {
        $columns = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $columns[] = $row['name'];
            echo "  - {$row['name']} ({$row['type']})\n";
        }
        echo "\nTotal columns: " . count($columns) . "\n";
    } else {
        echo "ERROR: Could not get table info\n";
    }
}

// Try to fetch sites_locations data
echo "\n" . str_repeat("-", 60) . "\n";
echo "Fetching sites_locations data...\n\n";

try {
    // Query without tenant filter first
    $query = "SELECT * FROM sites_locations LIMIT 5";
    echo "Query: $query\n\n";
    $stmt = $c->query($query);
    
    if ($stmt) {
        $count = 0;
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $count++;
            echo "Row $count:\n";
            foreach ($row as $key => $value) {
                echo "  $key: $value\n";
            }
            echo "\n";
        }
        echo "Total rows fetched: $count\n";
    } else {
        echo "ERROR: Query failed\n";
    }
} catch (Exception $e) {
    echo "EXCEPTION: " . $e->getMessage() . "\n";
}

// Now try with query_to_array
echo "\n" . str_repeat("-", 60) . "\n";
echo "Using query_to_array function...\n\n";

$tenant_id = 1;
$sites_list = query_to_array("SELECT id, full_location FROM sites_locations WHERE is_active = 1 AND tenant_id = $tenant_id ORDER BY full_location");

echo "Result from query_to_array:\n";
echo "Count: " . count($sites_list) . "\n";
if (count($sites_list) > 0) {
    echo "First item:\n";
    print_r($sites_list[0]);
} else {
    echo "NO DATA RETURNED\n";
}

// Try without tenant_id filter
echo "\n" . str_repeat("-", 60) . "\n";
echo "Trying without tenant_id filter...\n\n";

$sites_all = query_to_array("SELECT id, full_location FROM sites_locations WHERE is_active = 1 ORDER BY full_location");
echo "Count (no tenant filter): " . count($sites_all) . "\n";
if (count($sites_all) > 0) {
    echo "First 3 items:\n";
    for ($i = 0; $i < min(3, count($sites_all)); $i++) {
        echo "  " . ($i+1) . ": " . $sites_all[$i]['full_location'] . " (id=" . $sites_all[$i]['id'] . ")\n";
    }
}

// Check if tenant_id column exists
echo "\n" . str_repeat("-", 60) . "\n";
echo "Checking for tenant_id column...\n\n";

try {
    $stmt = $c->query("SELECT id, full_location, tenant_id FROM sites_locations LIMIT 1");
    if ($stmt) {
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row) {
            echo "✓ tenant_id column EXISTS\n";
            echo "Sample values:\n";
            foreach ($row as $key => $value) {
                echo "  $key: $value\n";
            }
        }
    }
} catch (Exception $e) {
    echo "✗ tenant_id column DOES NOT EXIST\n";
    echo "Error: " . $e->getMessage() . "\n";
}

?>
