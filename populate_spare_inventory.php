<?php
require 'config.inc.php';
require 'spare_integration_functions.php';

$connection = new mysqli($hostName, $userName, $password, $databaseName);

echo "=== CREATING SPARE ENTRIES IN GENERAL INVENTORY ===\n\n";

// Get all equipment spares
$result = $connection->query("SELECT * FROM equipment_spares WHERE part_id IS NULL");

if ($result) {
    $created = 0;
    while ($row = $result->fetch_assoc()) {
        echo "Processing: {$row['part_name']} ({$row['part_number']})\n";
        
        // Create/link to parts_master with dummy cost for now
        if (link_spare_to_parts_master(
            $row['id'],
            $row['part_name'],
            $row['part_number'],
            $connection,
            0 // Default cost, can be updated manually
        )) {
            // Get the part_id we just linked
            $part_result = $connection->query("SELECT part_id FROM equipment_spares WHERE id = {$row['id']}");
            $part_row = $part_result->fetch_assoc();
            $part_id = $part_row['part_id'];
            
            // Create stock_locale entry
            $stock_id = get_or_create_stock_locale($part_id, $connection, 1);
            
            // Set initial inventory in stock_locales to match equipment_spares
            $connection->query("UPDATE stock_locales 
                               SET quantity_on_hand = {$row['quantity']},
                                   quantity_available = {$row['quantity']}
                               WHERE id = {$stock_id}");
            
            echo "  ✓ Created in parts_master (ID: {$part_id})\n";
            echo "  ✓ Stock locale entry created\n";
            echo "  ✓ Initial inventory: {$row['quantity']} units\n";
            $created++;
        } else {
            echo "  ✗ Error creating spare\n";
        }
    }
    echo "\n✅ Successfully created {$created} spares in general inventory\n";
}

// Display the result
echo "\n=== INTEGRATED SPARE INVENTORY ===\n";
$result = $connection->query("
    SELECT 
        es.id,
        es.part_name,
        es.part_number,
        es.quantity as equipment_qty,
        pm.id as part_id,
        pm.unit_cost,
        sl.quantity_on_hand,
        sl.quantity_available
    FROM equipment_spares es
    JOIN parts_master pm ON es.part_id = pm.id
    LEFT JOIN stock_locales sl ON pm.id = sl.part_id AND sl.warehouse_location_id = 1
");

if ($result && $result->num_rows > 0) {
    echo "Part Name | Equipment Qty | Stock Qty | Cost\n";
    echo str_repeat("-", 70) . "\n";
    while ($row = $result->fetch_assoc()) {
        $stock_qty = $row['quantity_on_hand'] ?? 'N/A';
        $cost = $row['unit_cost'] ? '$' . number_format($row['unit_cost'], 2) : 'N/A';
        printf("%-25s | %-13s | %-9s | %s\n", 
            substr($row['part_name'], 0, 25),
            $row['equipment_qty'],
            $stock_qty,
            $cost
        );
    }
}

$connection->close();
?>
