<?php
/**
 * Equipment Spare Feature - Direct Execution Test
 * 
 * This script directly executes all setup operations
 */

// Set up error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Change to the application directory
chdir(dirname(__FILE__));

// Load configuration and common files
require_once 'config.inc.php';
require_once 'common.inc.php';
require_once 'libraries/inventory_manager.php';

global $connection, $db_type;

$log = [];
$errors = [];

function log_msg($msg) {
    global $log;
    $log[] = $msg;
    echo $msg . "\n";
}

function log_error($msg) {
    global $errors;
    $errors[] = $msg;
    echo "ERROR: " . $msg . "\n";
}

// ============================================================================
// HEADER
// ============================================================================
log_msg("╔════════════════════════════════════════════════════════════════╗");
log_msg("║  Equipment Spare Association Feature - Production Setup         ║");
log_msg("║  Direct SQLite Migration                                       ║");
log_msg("╚════════════════════════════════════════════════════════════════╝");
log_msg("");
log_msg("Timestamp: " . date('Y-m-d H:i:s'));
log_msg("Database Type: " . $db_type);
log_msg("");

// Verify database
if (!$connection) {
    log_error("No database connection");
    exit(1);
}

if ($db_type !== 'sqlite') {
    log_error("This setup is for SQLite. Current DB: $db_type");
    exit(1);
}

log_msg("✓ Database connected: SQLite");
log_msg("");

// ============================================================================
// PHASE 1: CREATE/VERIFY EQUIPMENT TABLE
// ============================================================================
log_msg("PHASE 1: Equipment Table");
log_msg("━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━");
log_msg("");

try {
    $check = $connection->query("SELECT name FROM sqlite_master WHERE type='table' AND name='equipment'");
    $exists = $check ? $check->fetch(PDO::FETCH_ASSOC) : false;
    
    if ($exists) {
        log_msg("✓ Equipment table exists");
    } else {
        log_msg("Creating equipment table...");
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
        log_msg("✓ Equipment table created");
    }
    
    // Get count
    $count = $connection->query("SELECT COUNT(*) as cnt FROM equipment")->fetch(PDO::FETCH_ASSOC)['cnt'];
    log_msg("  Records: $count");
    log_msg("");
    
} catch (Exception $e) {
    log_error("Equipment table: " . $e->getMessage());
}

// ============================================================================
// PHASE 2: CREATE/VERIFY EQUIPMENT_SPARES TABLE
// ============================================================================
log_msg("PHASE 2: Equipment Spares Table");
log_msg("━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━");
log_msg("");

try {
    $check = $connection->query("SELECT name FROM sqlite_master WHERE type='table' AND name='equipment_spares'");
    $exists = $check ? $check->fetch(PDO::FETCH_ASSOC) : false;
    
    if ($exists) {
        log_msg("✓ Equipment spares table exists");
        
        // Check for part_id column
        $schema = $connection->query("PRAGMA table_info('equipment_spares')");
        $columns = [];
        while ($col = $schema->fetch(PDO::FETCH_ASSOC)) {
            $columns[] = $col['name'];
        }
        
        if (!in_array('part_id', $columns)) {
            log_msg("  Adding part_id column...");
            $connection->exec('ALTER TABLE equipment_spares ADD COLUMN part_id INTEGER DEFAULT NULL');
            log_msg("  ✓ part_id column added");
        } else {
            log_msg("  ✓ part_id column exists");
        }
        
        if (!in_array('quantity', $columns)) {
            log_msg("  Adding quantity column...");
            $connection->exec('ALTER TABLE equipment_spares ADD COLUMN quantity INTEGER DEFAULT 0');
            log_msg("  ✓ quantity column added");
        } else {
            log_msg("  ✓ quantity column exists");
        }
        
        if (!in_array('notes', $columns)) {
            log_msg("  Adding notes column...");
            $connection->exec('ALTER TABLE equipment_spares ADD COLUMN notes TEXT');
            log_msg("  ✓ notes column added");
        } else {
            log_msg("  ✓ notes column exists");
        }
    } else {
        log_msg("Creating equipment_spares table...");
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
        log_msg("✓ Equipment spares table created");
    }
    
    // Get count
    $count = $connection->query("SELECT COUNT(*) as cnt FROM equipment_spares")->fetch(PDO::FETCH_ASSOC)['cnt'];
    log_msg("  Records: $count");
    log_msg("");
    
} catch (Exception $e) {
    log_error("Equipment spares table: " . $e->getMessage());
}

// ============================================================================
// PHASE 3: VERIFY PARTS_MASTER TABLE
// ============================================================================
log_msg("PHASE 3: Parts Master Table");
log_msg("━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━");
log_msg("");

try {
    $check = $connection->query("SELECT name FROM sqlite_master WHERE type='table' AND name='parts_master'");
    $exists = $check ? $check->fetch(PDO::FETCH_ASSOC) : false;
    
    if ($exists) {
        log_msg("✓ Parts master table exists");
        $count = $connection->query("SELECT COUNT(*) as cnt FROM parts_master")->fetch(PDO::FETCH_ASSOC)['cnt'];
        log_msg("  Records: $count");
    } else {
        log_msg("Creating parts master table...");
        $sql = "CREATE TABLE parts_master (
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
        )";
        $connection->exec($sql);
        log_msg("✓ Parts master table created");
    }
    log_msg("");
    
} catch (Exception $e) {
    log_error("Parts master table: " . $e->getMessage());
}

// ============================================================================
// PHASE 4: VERIFY FUNCTIONS
// ============================================================================
log_msg("PHASE 4: Required Functions");
log_msg("━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━");
log_msg("");

$functions = [
    'get_equipment_list',
    'get_part_equipment_spares',
    'attach_part_to_equipment',
    'save_part_equipment_associations'
];

$func_errors = 0;
foreach ($functions as $func) {
    if (function_exists($func)) {
        log_msg("✓ $func()");
    } else {
        log_error("Missing function: $func()");
        $func_errors++;
    }
}

if ($func_errors === 0) {
    log_msg("");
    log_msg("✓ All required functions present");
}
log_msg("");

// ============================================================================
// PHASE 5: FUNCTIONAL TESTS
// ============================================================================
log_msg("PHASE 5: Functional Tests");
log_msg("━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━");
log_msg("");

try {
    log_msg("Testing get_equipment_list()...");
    $equipment = get_equipment_list($connection, true);
    log_msg("✓ Function works");
    log_msg("  Equipment found: " . count($equipment));
    
    if (count($equipment) > 0) {
        log_msg("  First equipment:");
        $eq = $equipment[0];
        log_msg("    ID: {$eq['id']}, Description: {$eq['description']}, Mfr: {$eq['manufacturer']}");
    } else {
        log_msg("  (No equipment in system - add some to use the feature)");
    }
    log_msg("");
    
} catch (Exception $e) {
    log_error("get_equipment_list(): " . $e->getMessage());
}

// ============================================================================
// FINAL SUMMARY
// ============================================================================
log_msg("╔════════════════════════════════════════════════════════════════╗");
log_msg("║  SETUP COMPLETE                                                ║");
log_msg("╚════════════════════════════════════════════════════════════════╝");
log_msg("");

if (count($errors) === 0) {
    log_msg("✓ SUCCESS: All operations completed without errors");
    log_msg("");
    log_msg("The Equipment Spare Association feature is now active!");
    log_msg("");
    log_msg("USAGE:");
    log_msg("  1. Go to: Inventory → Parts Master → New Part");
    log_msg("  2. Fill in part details");
    log_msg("  3. Scroll to: 'Equipment Compatibility & Spares' section");
    log_msg("  4. Select equipment and save");
    log_msg("");
    log_msg("The spare will now be stored for that equipment!");
} else {
    log_msg("✗ ERRORS DETECTED: " . count($errors) . " issues found");
    log_msg("");
    foreach ($errors as $err) {
        log_msg("  - $err");
    }
}

log_msg("");
log_msg("Setup completed at: " . date('Y-m-d H:i:s'));

?>
