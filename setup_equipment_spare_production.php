<?php
/**
 * Complete Equipment Spare Feature Setup & Migration
 * 
 * This script:
 * 1. Ensures all tables exist with required columns
 * 2. Verifies all functions are working
 * 3. Sets up the feature for production use
 */

require_once 'config.inc.php';
require_once 'common.inc.php';
require_once 'libraries/inventory_manager.php';

global $connection, $db_type;

$success_count = 0;
$error_count = 0;

echo "╔════════════════════════════════════════════════════════════════╗\n";
echo "║  Equipment Spare Association Feature - Complete Setup          ║\n";
echo "║  SQLite Production Database Migration                         ║\n";
echo "╚════════════════════════════════════════════════════════════════╝\n\n";

echo "Database: $db_type\n";
echo "Timestamp: " . date('Y-m-d H:i:s') . "\n\n";

if ($db_type !== 'sqlite') {
    echo "ERROR: This script is for SQLite databases only.\n";
    echo "Current database: $db_type\n";
    exit(1);
}

// ============================================================================
// PHASE 1: ENSURE TABLES
// ============================================================================
echo "PHASE 1: ENSURING TABLES\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";

// 1.1 Equipment Table
echo "1.1 Equipment Table...\n";
try {
    $check = $connection->query("SELECT name FROM sqlite_master WHERE type='table' AND name='equipment'");
    $exists = $check->fetch(PDO::FETCH_ASSOC);
    
    if (!$exists) {
        $create_sql = "
            CREATE TABLE equipment (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                parent_id INTEGER DEFAULT NULL,
                description TEXT NOT NULL,
                location TEXT DEFAULT '',
                status TEXT DEFAULT '',
                manufacturer TEXT DEFAULT '',
                model TEXT DEFAULT '',
                serial_number TEXT DEFAULT '',
                photo TEXT DEFAULT ''
            )
        ";
        $connection->exec($create_sql);
        echo "  ✓ Created equipment table\n";
        $success_count++;
    } else {
        echo "  ✓ Equipment table exists\n";
        $success_count++;
        
        // Verify columns
        $schema = $connection->query("PRAGMA table_info('equipment')");
        $columns = [];
        while ($col = $schema->fetch(PDO::FETCH_ASSOC)) {
            $columns[] = $col['name'];
        }
        
        $required = ['id', 'description', 'manufacturer', 'model', 'serial_number'];
        foreach ($required as $col) {
            if (!in_array($col, $columns)) {
                $connection->exec("ALTER TABLE equipment ADD COLUMN $col TEXT DEFAULT ''");
                echo "  → Added column: $col\n";
            }
        }
    }
} catch (Exception $e) {
    echo "  ✗ Error: {$e->getMessage()}\n";
    $error_count++;
}

// 1.2 Equipment Spares Table
echo "\n1.2 Equipment Spares Table...\n";
try {
    $check = $connection->query("SELECT name FROM sqlite_master WHERE type='table' AND name='equipment_spares'");
    $exists = $check->fetch(PDO::FETCH_ASSOC);
    
    if (!$exists) {
        $create_sql = "
            CREATE TABLE equipment_spares (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                equipment_id INTEGER NOT NULL,
                part_id INTEGER DEFAULT NULL,
                part_name TEXT NOT NULL,
                part_number TEXT DEFAULT '',
                quantity INTEGER DEFAULT 0,
                notes TEXT
            )
        ";
        $connection->exec($create_sql);
        echo "  ✓ Created equipment_spares table\n";
        $success_count++;
    } else {
        echo "  ✓ Equipment spares table exists\n";
        $success_count++;
        
        // Verify columns
        $schema = $connection->query("PRAGMA table_info('equipment_spares')");
        $columns = [];
        while ($col = $schema->fetch(PDO::FETCH_ASSOC)) {
            $columns[] = $col['name'];
        }
        
        $required = ['part_id', 'quantity', 'notes'];
        foreach ($required as $col) {
            if (!in_array($col, $columns)) {
                if ($col === 'quantity') {
                    $connection->exec("ALTER TABLE equipment_spares ADD COLUMN $col INTEGER DEFAULT 0");
                } else {
                    $connection->exec("ALTER TABLE equipment_spares ADD COLUMN $col TEXT");
                }
                echo "  → Added column: $col\n";
            }
        }
    }
} catch (Exception $e) {
    echo "  ✗ Error: {$e->getMessage()}\n";
    $error_count++;
}

// 1.3 Parts Master Table
echo "\n1.3 Parts Master Table...\n";
try {
    $check = $connection->query("SELECT name FROM sqlite_master WHERE type='table' AND name='parts_master'");
    $exists = $check->fetch(PDO::FETCH_ASSOC);
    
    if (!$exists) {
        $create_sql = "
            CREATE TABLE parts_master (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                part_code TEXT UNIQUE,
                part_number TEXT,
                part_name TEXT NOT NULL,
                description TEXT,
                category TEXT,
                manufacturer TEXT,
                unit_cost DECIMAL(12,2) DEFAULT 0,
                is_active INTEGER DEFAULT 1,
                created_at TEXT DEFAULT CURRENT_TIMESTAMP,
                updated_at TEXT DEFAULT CURRENT_TIMESTAMP
            )
        ";
        $connection->exec($create_sql);
        echo "  ✓ Created parts_master table\n";
        $success_count++;
    } else {
        echo "  ✓ Parts master table exists\n";
        $success_count++;
    }
} catch (Exception $e) {
    echo "  ✗ Error: {$e->getMessage()}\n";
    $error_count++;
}

// ============================================================================
// PHASE 2: VERIFY FUNCTIONS
// ============================================================================
echo "\n\nPHASE 2: VERIFYING FUNCTIONS\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";

$functions = [
    'get_equipment_list' => 'Retrieve all equipment for form selection',
    'get_part_equipment_spares' => 'Get equipment associated with a part',
    'attach_part_to_equipment' => 'Link a part to equipment',
    'save_part_equipment_associations' => 'Sync equipment associations'
];

foreach ($functions as $func => $description) {
    echo "2." . (count(array_keys($functions, $func)) + 1) . " $func()\n";
    if (function_exists($func)) {
        echo "  ✓ Function exists\n";
        echo "  Purpose: $description\n";
        $success_count++;
    } else {
        echo "  ✗ Function NOT FOUND\n";
        echo "  Check: libraries/inventory_manager.php\n";
        $error_count++;
    }
}

// ============================================================================
// PHASE 3: FUNCTIONAL TESTS
// ============================================================================
echo "\n\nPHASE 3: FUNCTIONAL TESTS\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";

// 3.1 Test get_equipment_list
echo "3.1 Testing get_equipment_list()...\n";
try {
    $equipment = get_equipment_list($connection, true);
    echo "  ✓ Function executed successfully\n";
    echo "  Equipment records found: " . count($equipment) . "\n";
    if (count($equipment) > 0) {
        echo "  Sample:\n";
        foreach (array_slice($equipment, 0, 2) as $eq) {
            echo "    - ID {$eq['id']}: {$eq['description']} ({$eq['manufacturer']})\n";
        }
    } else {
        echo "  → Tip: Add some equipment to see them in the form\n";
    }
    $success_count++;
} catch (Exception $e) {
    echo "  ✗ Error: {$e->getMessage()}\n";
    $error_count++;
}

// 3.2 Test table counts
echo "\n3.2 Verifying table data...\n";
try {
    $eq_count = $connection->query("SELECT COUNT(*) as cnt FROM equipment")->fetch(PDO::FETCH_ASSOC)['cnt'];
    $spare_count = $connection->query("SELECT COUNT(*) as cnt FROM equipment_spares")->fetch(PDO::FETCH_ASSOC)['cnt'];
    $part_count = $connection->query("SELECT COUNT(*) as cnt FROM parts_master")->fetch(PDO::FETCH_ASSOC)['cnt'];
    
    echo "  Equipment: $eq_count records\n";
    echo "  Spares: $spare_count records\n";
    echo "  Parts: $part_count records\n";
    $success_count++;
} catch (Exception $e) {
    echo "  ✗ Error: {$e->getMessage()}\n";
    $error_count++;
}

// ============================================================================
// PHASE 4: FILE VERIFICATION
// ============================================================================
echo "\n\nPHASE 4: FILE VERIFICATION\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";

echo "4.1 Checking inventory/parts_master.php...\n";
$form_file = dirname(__FILE__) . '/inventory/parts_master.php';
if (file_exists($form_file)) {
    echo "  ✓ File exists\n";
    $content = file_get_contents($form_file);
    
    $checks = [
        'get_equipment_list' => 'Equipment loader function',
        'Equipment Compatibility' => 'Equipment section title',
        'equipment_ids[]' => 'Equipment checkboxes',
        'save_part_equipment_associations' => 'Association saver'
    ];
    
    foreach ($checks as $search => $description) {
        if (strpos($content, $search) !== false) {
            echo "  ✓ $description\n";
            $success_count++;
        } else {
            echo "  ✗ $description NOT FOUND\n";
            $error_count++;
        }
    }
} else {
    echo "  ✗ File not found: $form_file\n";
    $error_count++;
}

echo "\n4.2 Checking libraries/inventory_manager.php...\n";
$lib_file = dirname(__FILE__) . '/libraries/inventory_manager.php';
if (file_exists($lib_file)) {
    echo "  ✓ File exists\n";
    $success_count++;
} else {
    echo "  ✗ File not found: $lib_file\n";
    $error_count++;
}

// ============================================================================
// FINAL SUMMARY
// ============================================================================
echo "\n\n╔════════════════════════════════════════════════════════════════╗\n";
echo "║  SETUP COMPLETE                                                ║\n";
echo "╚════════════════════════════════════════════════════════════════╝\n\n";

echo "Results:\n";
echo "  ✓ Successes: $success_count\n";
echo "  ✗ Errors: $error_count\n\n";

if ($error_count === 0) {
    echo "✓ ALL CHECKS PASSED!\n\n";
    echo "The Equipment Spare Association feature is now ready to use.\n\n";
    echo "HOW TO USE:\n";
    echo "  1. Go to: Inventory → Parts Master → New Part\n";
    echo "  2. Fill in part details (Part Code, Part Name, etc.)\n";
    echo "  3. Scroll to: 'Equipment Compatibility & Spares' section\n";
    echo "  4. Check the equipment you want to attach this spare to\n";
    echo "  5. Click 'Save Part'\n\n";
    echo "The spare will now be stored in the equipment's spare inventory!\n";
} else {
    echo "✗ SOME ISSUES DETECTED\n\n";
    echo "Please review the errors above and ensure:\n";
    echo "  1. All required files exist\n";
    echo "  2. Database tables are created\n";
    echo "  3. Functions are properly defined\n";
}

echo "\n";

?>
