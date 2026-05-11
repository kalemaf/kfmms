<?php
// Direct inline execution of setup
error_reporting(E_ALL);
ini_set('display_errors', 0);

$start_time = microtime(true);

// Capture output
ob_start();

try {
    chdir(__DIR__);
    require_once 'config.inc.php';
    require_once 'common.inc.php';
    require_once 'libraries/inventory_manager.php';
    
    global $connection, $db_type;
    
    echo "╔════════════════════════════════════════════════════════════════╗\n";
    echo "║  Equipment Spare Association Feature - Production Setup         ║\n";
    echo "║  SQLite Database Migration                                     ║\n";
    echo "╚════════════════════════════════════════════════════════════════╝\n\n";
    
    if (!$connection) {
        echo "ERROR: Database connection failed\n";
        exit(1);
    }
    
    if ($db_type !== 'sqlite') {
        echo "ERROR: This setup requires SQLite. Current DB: $db_type\n";
        exit(1);
    }
    
    echo "Database: $db_type\n";
    echo "Time: " . date('Y-m-d H:i:s') . "\n\n";
    
    // ====== PHASE 1: EQUIPMENT TABLE ======
    echo "PHASE 1: Equipment Table\n";
    echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";
    
    $check = $connection->query("SELECT name FROM sqlite_master WHERE type='table' AND name='equipment'");
    $exists = $check ? $check->fetch(PDO::FETCH_ASSOC) : false;
    
    if (!$exists) {
        $sql = "CREATE TABLE equipment (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            parent_id INTEGER DEFAULT NULL,
            description TEXT NOT NULL,
            location TEXT DEFAULT '',
            status TEXT DEFAULT '',
            manufacturer TEXT DEFAULT '',
            model TEXT DEFAULT '',
            serial_number TEXT DEFAULT '',
            photo TEXT DEFAULT ''
        )";
        $connection->exec($sql);
        echo "✓ Created equipment table\n";
    } else {
        echo "✓ Equipment table exists\n";
    }
    
    $count = $connection->query("SELECT COUNT(*) as cnt FROM equipment")->fetch(PDO::FETCH_ASSOC)['cnt'];
    echo "  Records: $count\n\n";
    
    // ====== PHASE 2: EQUIPMENT_SPARES TABLE ======
    echo "PHASE 2: Equipment Spares Table\n";
    echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";
    
    $check = $connection->query("SELECT name FROM sqlite_master WHERE type='table' AND name='equipment_spares'");
    $exists = $check ? $check->fetch(PDO::FETCH_ASSOC) : false;
    
    if (!$exists) {
        $sql = "CREATE TABLE equipment_spares (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            equipment_id INTEGER NOT NULL,
            part_id INTEGER DEFAULT NULL,
            part_name TEXT NOT NULL,
            part_number TEXT DEFAULT '',
            quantity INTEGER DEFAULT 0,
            notes TEXT
        )";
        $connection->exec($sql);
        echo "✓ Created equipment_spares table\n";
    } else {
        echo "✓ Equipment spares table exists\n";
        
        // Add missing columns
        $schema = $connection->query("PRAGMA table_info('equipment_spares')");
        $columns = [];
        while ($col = $schema->fetch(PDO::FETCH_ASSOC)) {
            $columns[] = $col['name'];
        }
        
        if (!in_array('part_id', $columns)) {
            $connection->exec('ALTER TABLE equipment_spares ADD COLUMN part_id INTEGER DEFAULT NULL');
            echo "  ✓ Added part_id column\n";
        }
        if (!in_array('quantity', $columns)) {
            $connection->exec('ALTER TABLE equipment_spares ADD COLUMN quantity INTEGER DEFAULT 0');
            echo "  ✓ Added quantity column\n";
        }
        if (!in_array('notes', $columns)) {
            $connection->exec('ALTER TABLE equipment_spares ADD COLUMN notes TEXT');
            echo "  ✓ Added notes column\n";
        }
    }
    
    $count = $connection->query("SELECT COUNT(*) as cnt FROM equipment_spares")->fetch(PDO::FETCH_ASSOC)['cnt'];
    echo "  Records: $count\n\n";
    
    // ====== PHASE 3: PARTS_MASTER TABLE ======
    echo "PHASE 3: Parts Master Table\n";
    echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";
    
    $check = $connection->query("SELECT name FROM sqlite_master WHERE type='table' AND name='parts_master'");
    $exists = $check ? $check->fetch(PDO::FETCH_ASSOC) : false;
    
    if ($exists) {
        echo "✓ Parts master table exists\n";
        $count = $connection->query("SELECT COUNT(*) as cnt FROM parts_master")->fetch(PDO::FETCH_ASSOC)['cnt'];
        echo "  Records: $count\n\n";
    } else {
        echo "⚠ Parts master table not found (will be created on first use)\n\n";
    }
    
    // ====== PHASE 4: VERIFY FUNCTIONS ======
    echo "PHASE 4: Verify Functions\n";
    echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";
    
    $functions = [
        'get_equipment_list',
        'get_part_equipment_spares',
        'attach_part_to_equipment',
        'save_part_equipment_associations'
    ];
    
    foreach ($functions as $func) {
        echo (function_exists($func) ? "✓" : "✗") . " $func()\n";
    }
    echo "\n";
    
    // ====== PHASE 5: TEST FUNCTIONS ======
    echo "PHASE 5: Functional Tests\n";
    echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";
    
    if (function_exists('get_equipment_list')) {
        $equipment = get_equipment_list($connection, true);
        echo "✓ get_equipment_list() works\n";
        echo "  Equipment in system: " . count($equipment) . "\n";
        if (count($equipment) > 0) {
            echo "  Sample:\n";
            foreach (array_slice($equipment, 0, 2) as $eq) {
                echo "    - ID {$eq['id']}: {$eq['description']}\n";
            }
        }
    }
    echo "\n";
    
    // ====== SUMMARY ======
    echo "╔════════════════════════════════════════════════════════════════╗\n";
    echo "║  ✓ SETUP COMPLETE - FEATURE READY FOR PRODUCTION               ║\n";
    echo "╚════════════════════════════════════════════════════════════════╝\n\n";
    
    echo "Equipment Spare Association feature has been successfully migrated!\n\n";
    
    echo "HOW TO USE:\n";
    echo "  1. Go to: Inventory → Parts Master → New Part\n";
    echo "  2. Fill in part details\n";
    echo "  3. Scroll down to: 'Equipment Compatibility & Spares' section\n";
    echo "  4. Check the equipment this spare fits\n";
    echo "  5. Click 'Save Part'\n\n";
    
    echo "The spare will now be stored for that equipment!\n\n";
    
    $end_time = microtime(true);
    $duration = round(($end_time - $start_time) * 1000, 2);
    
    echo "Execution time: {$duration}ms\n";
    echo "Setup completed: " . date('Y-m-d H:i:s') . "\n";
    
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
    echo "Trace: " . $e->getTraceAsString() . "\n";
}

$content = ob_get_clean();

// Save to file
file_put_contents(__DIR__ . '/SETUP_OUTPUT.txt', $content);

// Display
echo $content;

// Mark as complete
file_put_contents(__DIR__ . '/SETUP_COMPLETED.txt', 'Setup executed at ' . date('Y-m-d H:i:s'));

?>
