<?php
require 'config.inc.php';

/**
 * SPARE PARTS INTEGRATION SYSTEM
 * 
 * Integrates equipment_spares with general parts_master inventory
 * Ensures spare usage is tracked in stock_locales and inventory_transactions
 */

$connection = new mysqli($hostName, $userName, $password, $databaseName);

// ============================================================================
// ALTER TABLE: Add part_id to equipment_spares
// ============================================================================

echo "Step 1: Adding part_id column to equipment_spares...\n";

// Check if column already exists
$result = $connection->query("SHOW COLUMNS FROM equipment_spares LIKE 'part_id'");
if ($result->num_rows === 0) {
    $alter_sql = "ALTER TABLE equipment_spares ADD COLUMN part_id INT DEFAULT NULL AFTER equipment_id";
    if ($connection->query($alter_sql)) {
        echo "✓ part_id column added\n";
    } else {
        echo "✗ Error adding part_id: " . $connection->error . "\n";
    }
} else {
    echo "✓ part_id column already exists\n";
}

// ============================================================================
// LINK equipment_spares TO parts_master
// ============================================================================

echo "\nStep 2: Link equipment spares to parts_master...\n";

$link_result = $connection->query("
    SELECT es.id, es.part_number, pm.id as part_master_id
    FROM equipment_spares es
    LEFT JOIN parts_master pm ON es.part_number = pm.part_number
    WHERE es.part_id IS NULL
");

$linked = 0;
if ($link_result) {
    while ($row = $link_result->fetch_assoc()) {
        if ($row['part_master_id']) {
            $connection->query("UPDATE equipment_spares SET part_id = " . $row['part_master_id'] . " WHERE id = " . $row['id']);
            echo "  Linked: {$row['part_number']} → Parts Master ID {$row['part_master_id']}\n";
            $linked++;
        } else {
            echo "  NOT FOUND in parts_master: {$row['part_number']}\n";
        }
    }
}
echo "Linked {$linked} spares to parts_master\n";

// ============================================================================
// CREATE/CHECK warehouse_locations TABLE
// ============================================================================

echo "\nStep 3: Checking warehouse_locations table...\n";

$result = $connection->query("SHOW TABLES LIKE 'warehouse_locations'");
if ($result->num_rows === 0) {
    echo "Creating warehouse_locations table...\n";
    $create_sql = "CREATE TABLE warehouse_locations (
        id INT PRIMARY KEY AUTO_INCREMENT,
        location_code VARCHAR(50) UNIQUE NOT NULL,
        location_name VARCHAR(100) NOT NULL,
        warehouse_name VARCHAR(100),
        is_active TINYINT(1) DEFAULT 1,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )";
    if ($connection->query($create_sql)) {
        echo "✓ warehouse_locations table created\n";
        
        // Add default location
        $connection->query("INSERT INTO warehouse_locations (location_code, location_name, warehouse_name) 
                          VALUES ('MAIN', 'Main Warehouse', 'Main Warehouse')");
        echo "✓ Default warehouse location added\n";
    } else {
        echo "✗ Error creating table: " . $connection->error . "\n";
    }
} else {
    // Check if it has data
    $result = $connection->query("SELECT COUNT(*) as count FROM warehouse_locations");
    $row = $result->fetch_assoc();
    if ($row['count'] == 0) {
        $connection->query("INSERT INTO warehouse_locations (location_code, location_name, warehouse_name) 
                          VALUES ('MAIN', 'Main Warehouse', 'Main Warehouse')");
        echo "✓ warehouse_locations table exists, added default location\n";
    } else {
        echo "✓ warehouse_locations table exists with " . $row['count'] . " locations\n";
    }
}

// ============================================================================
// FIX stock_locales: Set warehouse_location_id for records without one
// ============================================================================

echo "\nStep 4: Ensuring all stock_locales have warehouse_location_id...\n";

$connection->query("UPDATE stock_locales SET warehouse_location_id = 1 
                   WHERE warehouse_location_id IS NULL OR warehouse_location_id = 0");

echo "✓ Stock locations updated\n";

// ============================================================================
// VERIFY INTEGRATION
// ============================================================================

echo "\n=== INTEGRATION SUMMARY ===\n";

$result = $connection->query("
    SELECT es.id, es.part_name, es.part_number, es.quantity, 
           pm.id as part_id, pm.unit_cost, pm.description
    FROM equipment_spares es
    LEFT JOIN parts_master pm ON es.part_id = pm.id
    LIMIT 5
");

echo "\nEquipment Spares with Parts Master Link:\n";
while ($row = $result->fetch_assoc()) {
    $linked = $row['part_id'] ? "✓" : "✗";
    $cost = $row['unit_cost'] ? "$" . $row['unit_cost'] : "N/A";
    echo "$linked {$row['part_name']} ({$row['part_number']}) - Qty: {$row['quantity']} - Cost: $cost\n";
}

$connection->close();

echo "\n✅ Integration setup complete!\n";
echo "   - Equipment spares now link to parts_master\n";
echo "   - Stock management integrated with warehouse_locations\n";
echo "   - Ready for transaction tracking\n";
?>
