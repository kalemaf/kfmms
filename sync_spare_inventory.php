<?php
require 'config.inc.php';

$connection = new mysqli($hostName, $userName, $password, $databaseName);

echo "=== FIXING STOCK LOCALES ===\n\n";

// Get all equipment spares with their parts
$result = $connection->query("
    SELECT es.id, es.quantity, pm.id as part_id, pm.part_name
    FROM equipment_spares es
    JOIN parts_master pm ON es.part_id = pm.id
");

if ($result) {
    while ($row = $result->fetch_assoc()) {
        $part_id = $row['part_id'];
        $qty = $row['quantity'];
        $part_name = $row['part_name'];
        
        // Check if stock_locale exists for this part
        $stock_check = $connection->query("SELECT id FROM stock_locales WHERE part_id = {$part_id} AND warehouse_location_id = 1");
        
        if ($stock_check && $stock_check->num_rows > 0) {
            $stock = $stock_check->fetch_assoc();
            $stock_id = $stock['id'];
            
            // Update quantities to match equipment_spares
            $connection->query("UPDATE stock_locales 
                               SET quantity_on_hand = {$qty},
                                   quantity_available = {$qty},
                                   quantity_reserved = 0,
                                   quantity_on_order = 0,
                                   quantity_issued = 0
                               WHERE id = {$stock_id}");
            echo "✓ Updated: {$part_name} - {$qty} units\n";
        } else {
            // Create stock locale
            $connection->query("INSERT INTO stock_locales (part_id, warehouse_location_id, quantity_on_hand, quantity_available) 
                               VALUES ({$part_id}, 1, {$qty}, {$qty})");
            echo "✓ Created: {$part_name} - {$qty} units\n";
        }
    }
}

echo "\n=== FINAL INVENTORY STATE ===\n\n";

$result = $connection->query("
    SELECT 
        es.part_name,
        es.part_number,
        es.quantity as equipment_qty,
        pm.unit_cost,
        sl.quantity_on_hand,
        sl.quantity_available,
        sl.quantity_issued
    FROM equipment_spares es
    JOIN parts_master pm ON es.part_id = pm.id
    LEFT JOIN stock_locales sl ON pm.id = sl.part_id AND sl.warehouse_location_id = 1
");

if ($result && $result->num_rows > 0) {
    echo "Part | Qty (Equipment) | Qty (Stock) | Cost\n";
    echo str_repeat("-", 70) . "\n";
    while ($row = $result->fetch_assoc()) {
        $cost = $row['unit_cost'] ? '$' . number_format($row['unit_cost'], 2) : 'N/A';
        $stock_qty = $row['quantity_on_hand'] ?? 'ERROR';
        printf("%-25s | %15s | %11s | %s\n", 
            substr($row['part_name'], 0, 25),
            $row['equipment_qty'],
            $stock_qty,
            $cost
        );
    }
}

echo "\n✅ Spare inventory synchronized!\n";
$connection->close();
?>
