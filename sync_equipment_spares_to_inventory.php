<?php
/**
 * Sync equipment_spares quantities into the inventory system
 * Updates stock_locales and parts_master.total_on_hand
 */

require_once 'config.inc.php';

if (!$connection) {
    die("Database connection failed.\n");
}

echo "=== SYNCING EQUIPMENT SPARES TO INVENTORY ===\n\n";

// Get all equipment spares with part_id linked
$spares = $connection->query("
    SELECT es.id, es.part_id, es.quantity, pm.total_on_hand
    FROM equipment_spares es
    JOIN parts_master pm ON es.part_id = pm.id
    WHERE es.part_id IS NOT NULL
    ORDER BY es.part_id
");

if (!$spares) {
    die("Error querying equipment_spares: " . $connection->error . "\n");
}

$synced = 0;
while ($spare = $spares->fetch_assoc()) {
    $part_id = intval($spare['part_id']);
    $spare_qty = intval($spare['quantity']);
    $pm_qty = intval($spare['total_on_hand']);
    
    echo "Spare ID {$spare['id']}: part_id=$part_id, quantity=$spare_qty, current_parts_master=$pm_qty\n";
    
    // Check if stock_locales entry exists
    $stock_check = $connection->query("
        SELECT id, quantity_on_hand
        FROM stock_locales
        WHERE part_id = $part_id AND warehouse_location_id = 1
    ");
    
    if ($stock_check && $stock_check->num_rows > 0) {
        $stock = $stock_check->fetch_assoc();
        $stock_qty = intval($stock['quantity_on_hand']);
        $stock_id = intval($stock['id']);
        
        // Update stock_locales with spare quantity (spare is source of truth)
        if ($stock_qty != $spare_qty) {
            echo "  Updating stock_locales ID $stock_id: $stock_qty -> $spare_qty\n";
            $connection->query("UPDATE stock_locales SET quantity_on_hand = $spare_qty WHERE id = $stock_id");
        }
    } else {
        echo "  Creating stock_locales entry with quantity=$spare_qty\n";
        $connection->query("
            INSERT INTO stock_locales (part_id, warehouse_location_id, quantity_on_hand, quantity_reserved)
            VALUES ($part_id, 1, $spare_qty, 0)
        ");
    }
    
    // Update parts_master total_on_hand
    if ($pm_qty != $spare_qty) {
        echo "  Updating parts_master ID $part_id: $pm_qty -> $spare_qty\n";
        $connection->query("UPDATE parts_master SET total_on_hand = $spare_qty WHERE id = $part_id");
    }
    
    $synced++;
}

echo "\n=== VERIFICATION ===\n";

// Verify the sync worked
$verify = $connection->query("
    SELECT pm.id, pm.part_code, pm.part_name, pm.total_on_hand, es.quantity
    FROM parts_master pm
    LEFT JOIN equipment_spares es ON pm.id = es.part_id
    WHERE pm.id IN (SELECT DISTINCT part_id FROM equipment_spares WHERE part_id IS NOT NULL)
");

if ($verify) {
    while ($row = $verify->fetch_assoc()) {
        $pm_qty = intval($row['total_on_hand']);
        $es_qty = intval($row['quantity'] ?? 0);
        $match = ($pm_qty === $es_qty) ? "✓" : "✗";
        echo "$match Parts ID {$row['id']}: {$row['part_code']} - parts_master=$pm_qty, equipment_spare=$es_qty\n";
    }
}

echo "\n✅ Sync complete. Synced $synced equipment spare quantities.\n";
echo "\nInventory page will now show equipment spare quantities in stock.\n";
?>
