<?php
/**
 * Simple Cleanup of Duplicate Parts
 * Keeps the first record (lowest ID) and deletes the rest
 */

require_once 'config.inc.php';

if (!$connection) {
    die("Database connection failed.\n");
}

echo "=== SIMPLE DUPLICATE CLEANUP ===\n\n";

// Get the specific duplicate group
$dup = $connection->query("
    SELECT GROUP_CONCAT(id) as ids
    FROM parts_master
    WHERE part_code = '6208zz'
")->fetch_assoc();

if (!$dup || !$dup['ids']) {
    echo "No duplicates found.\n";
    exit;
}

$ids = explode(',', $dup['ids']);
$keep_id = intval($ids[0]);
$delete_ids = array_slice($ids, 1);

echo "Found " . count($ids) . " records with part_code = '6208zz'\n";
echo "Keeping ID: $keep_id\n";
echo "Deleting IDs: " . implode(', ', $delete_ids) . "\n\n";

// Update references
foreach ($delete_ids as $del_id) {
    $del_id = intval($del_id);
    
    // Update equipment_spares
    $result = $connection->query("UPDATE equipment_spares SET part_id = $keep_id WHERE part_id = $del_id");
    if ($connection->error) {
        echo "Error updating equipment_spares: " . $connection->error . "\n";
    } else {
        echo "Updated equipment_spares\n";
    }
    
    // Move stock_locales
    $sl = $connection->query("SELECT * FROM stock_locales WHERE part_id = $del_id");
    if ($sl) {
        while ($row = $sl->fetch_assoc()) {
            $loc_id = $row['warehouse_location_id'];
            $qty = $row['quantity_on_hand'];
            
            $existing = $connection->query("SELECT id, quantity_on_hand FROM stock_locales WHERE part_id = $keep_id AND warehouse_location_id = $loc_id");
            if ($existing && $existing->num_rows > 0) {
                $connection->query("UPDATE stock_locales SET quantity_on_hand = quantity_on_hand + $qty WHERE part_id = $keep_id AND warehouse_location_id = $loc_id");
                echo "Merged stock_locales qty: $qty\n";
            } else {
                $connection->query("INSERT INTO stock_locales (part_id, warehouse_location_id, quantity_on_hand) VALUES ($keep_id, $loc_id, $qty)");
                echo "Moved stock_locales qty: $qty to keep_id\n";
            }
        }
    }
    
    // Update inventory_transactions
    $connection->query("UPDATE inventory_transactions SET part_id = $keep_id WHERE part_id = $del_id");
    echo "Updated inventory_transactions\n";
    
    // Delete stock_locales
    $connection->query("DELETE FROM stock_locales WHERE part_id = $del_id");
    
    // Delete the duplicate part
    $result = $connection->query("DELETE FROM parts_master WHERE id = $del_id");
    if ($connection->error) {
        echo "Error deleting parts_master ID $del_id: " . $connection->error . "\n";
    } else {
        echo "Deleted parts_master ID $del_id\n";
    }
}

// Verify
echo "\n=== VERIFICATION ===\n";
$check = $connection->query("SELECT COUNT(*) as cnt FROM parts_master WHERE part_code = '6208zz'")->fetch_assoc();
echo "Remaining '6208zz' records: " . $check['cnt'] . "\n";

$total = $connection->query("SELECT COUNT(*) as cnt FROM parts_master")->fetch_assoc();
echo "Total parts_master records: " . $total['cnt'] . "\n";

echo "\n✅ Cleanup complete.\n";
?>
