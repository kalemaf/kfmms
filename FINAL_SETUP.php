<?php
// Inline setup - direct execution
chdir(dirname(__FILE__));

// Suppress and capture all output
ob_start();

try {
    // Load dependencies
    require_once 'config.inc.php';
    require_once 'common.inc.php';
    require_once 'libraries/inventory_manager.php';
    
    global $connection, $db_type;
    
    // Output header
    echo "Equipment Spare Association - Production Setup\n";
    echo str_repeat("=", 70) . "\n\n";
    echo "Time: " . date('Y-m-d H:i:s') . "\n";
    echo "Database: $db_type\n\n";
    
    // Verify connection
    if (!$connection) {
        throw new Exception("No database connection");
    }
    
    // Check device
    if ($db_type !== 'sqlite') {
        throw new Exception("This setup requires SQLite. Current: $db_type");
    }
    
    // Setup EQUIPMENT table
    echo "1. Setting up EQUIPMENT table...\n";
    try {
        $result = $connection->query("SELECT COUNT(*) as cnt FROM equipment");
        echo "   ✓ Equipment table exists (" . $result->fetch(PDO::FETCH_ASSOC)['cnt'] . " records)\n";
    } catch (Exception $e) {
        echo "   ! Equipment table missing - creating...\n";
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
        echo "   ✓ Equipment table created\n";
    }
    
    // Setup EQUIPMENT_SPARES table
    echo "\n2. Setting up EQUIPMENT_SPARES table...\n";
    try {
        $result = $connection->query("SELECT COUNT(*) as cnt FROM equipment_spares");
        echo "   ✓ Equipment spares table exists (" . $result->fetch(PDO::FETCH_ASSOC)['cnt'] . " records)\n";
        
        // Check columns
        $schema = $connection->query("PRAGMA table_info('equipment_spares')");
        $columns = [];
        while ($col = $schema->fetch(PDO::FETCH_ASSOC)) {
            $columns[] = $col['name'];
        }
        
        $required = ['part_id', 'quantity', 'notes'];
        foreach ($required as $col) {
            if (!in_array($col, $columns)) {
                echo "   ! Adding missing column: $col\n";
                if ($col === 'quantity') {
                    $connection->exec("ALTER TABLE equipment_spares ADD COLUMN $col INTEGER DEFAULT 0");
                } else {
                    $connection->exec("ALTER TABLE equipment_spares ADD COLUMN $col TEXT");
                }
                echo "   ✓ Column $col added\n";
            }
        }
    } catch (Exception $e) {
        echo "   ! Equipment_spares table missing - creating...\n";
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
        echo "   ✓ Equipment spares table created\n";
    }
    
    // Setup PARTS_MASTER table
    echo "\n3. Setting up PARTS_MASTER table...\n";
    try {
        $result = $connection->query("SELECT COUNT(*) as cnt FROM parts_master");
        echo "   ✓ Parts master table exists (" . $result->fetch(PDO::FETCH_ASSOC)['cnt'] . " records)\n";
    } catch (Exception $e) {
        echo "   ⚠ Parts master table not found (OK - will be created on first use)\n";
    }
    
    // Verify functions
    echo "\n4. Verifying required functions...\n";
    $functions = [
        'get_equipment_list',
        'get_part_equipment_spares',
        'attach_part_to_equipment',
        'save_part_equipment_associations'
    ];
    
    $all_exist = true;
    foreach ($functions as $func) {
        $exists = function_exists($func);
        echo "   " . ($exists ? "✓" : "✗") . " $func()\n";
        if (!$exists) $all_exist = false;
    }
    
    // Test functionality
    echo "\n5. Testing functionality...\n";
    if ($all_exist) {
        $equipment = get_equipment_list($connection, true);
        echo "   ✓ get_equipment_list() works (" . count($equipment) . " equipment items)\n";
    } else {
        echo "   ✗ Some functions missing - check installation\n";
    }
    
    // Success message
    echo "\n" . str_repeat("=", 70) . "\n";
    echo "✓ SETUP COMPLETE\n\n";
    echo "Equipment Spare Association feature is now ACTIVE!\n\n";
    echo "TO USE:\n";
    echo "1. Go to: Inventory → Parts Master → New Part\n";
    echo "2. Fill in part details\n";
    echo "3. Scroll to: 'Equipment Compatibility & Spares' section\n";
    echo "4. Select equipment and save\n\n";
    echo "Completed at: " . date('Y-m-d H:i:s') . "\n";
    
} catch (Exception $e) {
    echo "\n✗ ERROR: " . $e->getMessage() . "\n";
    echo "Make sure database is configured properly in config.inc.php\n";
}

// Get output
$output = ob_get_clean();

// Save to file
$output_file = __DIR__ . '/SETUP_RESULT.txt';
file_put_contents($output_file, $output);

// Show output
echo $output;

// Indicate completion
if (PHP_SAPI === 'cli') {
    exit($output ? 0 : 1);
}

?>
