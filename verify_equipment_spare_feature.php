<?php
/**
 * Equipment Spare Feature - Quick Verification
 */

require_once 'config.inc.php';
require_once 'common.inc.php';
require_once 'libraries/inventory_manager.php';

global $connection, $db_type;

echo "Equipment Spare Association Feature - Verification\n";
echo "====================================================\n\n";

echo "Database Type: $db_type\n";
echo "Date: " . date('Y-m-d H:i:s') . "\n\n";

// ============================================================================
// TEST 1: Database Connection
// ============================================================================
echo "[TEST 1] Database Connection\n";
if ($connection) {
    echo "  ✓ Database connected\n";
} else {
    echo "  ✗ Database connection failed\n";
    exit(1);
}

// ============================================================================
// TEST 2: Tables Exist
// ============================================================================
echo "\n[TEST 2] Required Tables\n";

$tables = ['equipment', 'equipment_spares', 'parts_master'];
foreach ($tables as $table) {
    try {
        $result = $connection->query("SELECT COUNT(*) as cnt FROM $table");
        if ($result) {
            $count = ($db_type === 'sqlite') 
                ? $result->fetch(PDO::FETCH_ASSOC)['cnt']
                : $result->fetch_assoc()['cnt'];
            echo "  ✓ $table ($count records)\n";
        } else {
            echo "  ✗ $table query failed\n";
        }
    } catch (Exception $e) {
        echo "  ✗ $table error: {$e->getMessage()}\n";
    }
}

// ============================================================================
// TEST 3: Functions Exist
// ============================================================================
echo "\n[TEST 3] Required Functions\n";

$functions = [
    'get_equipment_list',
    'get_part_equipment_spares',
    'attach_part_to_equipment',
    'save_part_equipment_associations'
];

foreach ($functions as $func) {
    if (function_exists($func)) {
        echo "  ✓ $func()\n";
    } else {
        echo "  ✗ $func() NOT FOUND\n";
    }
}

// ============================================================================
// TEST 4: get_equipment_list() Works
// ============================================================================
echo "\n[TEST 4] Testing get_equipment_list()\n";

try {
    $equipment = get_equipment_list($connection, true);
    echo "  ✓ Function works\n";
    echo "  Equipment count: " . count($equipment) . "\n";
    
    if (count($equipment) > 0) {
        echo "  Sample:\n";
        foreach (array_slice($equipment, 0, 2) as $eq) {
            $id = $eq['id'] ?? 'N/A';
            $desc = $eq['description'] ?? 'N/A';
            echo "    - ID $id: $desc\n";
        }
    } else {
        echo "  (No equipment in system - add some to test)\n";
    }
} catch (Exception $e) {
    echo "  ✗ Error: {$e->getMessage()}\n";
}

// ============================================================================
// TEST 5: Form File Check
// ============================================================================
echo "\n[TEST 5] Parts Master Form File\n";

$form_file = dirname(__FILE__) . '/inventory/parts_master.php';
if (file_exists($form_file)) {
    echo "  ✓ File exists\n";
    
    $content = file_get_contents($form_file);
    
    // Check for critical components
    $checks = [
        'get_equipment_list' => 'get_equipment_list($connection',
        'Equipment Compat' => 'Equipment Compatibility',
        'equipment_ids[]' => 'equipment_ids[]',
        'save_part_equipment' => 'save_part_equipment_associations'
    ];
    
    foreach ($checks as $name => $search) {
        if (strpos($content, $search) !== false) {
            echo "  ✓ $name found in form\n";
        } else {
            echo "  ✗ $name NOT found in form\n";
        }
    }
} else {
    echo "  ✗ File not found: $form_file\n";
}

// ============================================================================
// SUMMARY
// ============================================================================
echo "\n====================================================\n";
echo "Verification Complete\n";
echo "\nIf all tests pass, the Equipment Spare Association\n";
echo "feature should now be visible in the Parts Master form.\n";
echo "\nTo use:\n";
echo "1. Go to: Inventory → Parts Master → New Part\n";
echo "2. Fill in basic part details\n";
echo "3. Scroll to: 'Equipment Compatibility & Spares' section\n";
echo "4. Select equipment and save\n";

?>
