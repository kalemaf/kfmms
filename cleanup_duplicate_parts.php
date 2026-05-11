<?php
/**
 * Cleanup Duplicate Parts in parts_master
 * 
 * This script identifies and merges duplicate parts_master records that share
 * the same part_code, part_number, or part_name. Keeps the record with the most stock.
 */

require_once 'config.inc.php';
require_once 'common.inc.php';

if (!$connection) {
    die("Database connection failed.\n");
}

echo "=== DUPLICATE PARTS CLEANUP ===\n\n";

// Find duplicates by part_code
echo "Step 1: Finding duplicates by part_code...\n";
$duplicates_by_code = [];
$result = $connection->query("
    SELECT part_code, COUNT(*) as cnt, GROUP_CONCAT(id) as ids
    FROM parts_master
    WHERE part_code != '' AND part_code IS NOT NULL
    GROUP BY part_code
    HAVING COUNT(*) > 1
    ORDER BY cnt DESC
");

if ($result) {
    while ($row = $result->fetch_assoc()) {
        $duplicates_by_code[] = $row;
    }
}

echo "Found " . count($duplicates_by_code) . " duplicate part_codes\n\n";

// Find duplicates by part_number
echo "Step 2: Finding duplicates by part_number...\n";
$duplicates_by_number = [];
$result = $connection->query("
    SELECT part_number, COUNT(*) as cnt, GROUP_CONCAT(id) as ids
    FROM parts_master
    WHERE part_number != '' AND part_number IS NOT NULL
    GROUP BY part_number
    HAVING COUNT(*) > 1
    ORDER BY cnt DESC
");

if ($result) {
    while ($row = $result->fetch_assoc()) {
        $duplicates_by_number[] = $row;
    }
}

echo "Found " . count($duplicates_by_number) . " duplicate part_numbers\n\n";

// Find duplicates by part_name
echo "Step 3: Finding duplicates by part_name...\n";
$duplicates_by_name = [];
$result = $connection->query("
    SELECT part_name, COUNT(*) as cnt, GROUP_CONCAT(id) as ids
    FROM parts_master
    WHERE part_name != '' AND part_name IS NOT NULL
    GROUP BY LOWER(part_name)
    HAVING COUNT(*) > 1
    ORDER BY cnt DESC
");

if ($result) {
    while ($row = $result->fetch_assoc()) {
        $duplicates_by_name[] = $row;
    }
}

echo "Found " . count($duplicates_by_name) . " duplicate part_names\n\n";

    // Merge duplicates
    $merged = 0;
    $all_duplicates = array_merge($duplicates_by_code, $duplicates_by_number, $duplicates_by_name);
    
    echo "Step 4: Merging duplicates...\n";
    
    foreach ($all_duplicates as $dup) {
        $ids = explode(',', $dup['ids']);
        if (count($ids) <= 1) {
            continue;
        }
    
        // Sort IDs and get the first
        sort($ids);
        $keep_id = intval(array_shift($ids));
    
        // Get the record with the most stock to keep as primary
        $keep_query = "SELECT id, total_on_hand FROM parts_master WHERE id IN (" . implode(',', $ids) . ", $keep_id) ORDER BY total_on_hand DESC LIMIT 1";
        $keep_result = $connection->query($keep_query);
        if ($keep_result && $row = $keep_result->fetch_assoc()) {
            $keep_id = intval($row['id']);
        }
    
        echo "Merging IDs: " . implode(', ', $ids) . " into ID: $keep_id\n";
    
        // Consolidate stock from all duplicates into the primary record
        $total_stock = 0;
        $total_issued = 0;
        $total_reserved = 0;
    
        foreach (array_merge([$keep_id], $ids) as $id) {
            $id = intval($id);
            $stock_query = "SELECT total_on_hand, total_issued, total_reserved FROM parts_master WHERE id = $id";
            $stock_result = $connection->query($stock_query);
            if ($stock_result && $row = $stock_result->fetch_assoc()) {
                $total_stock += intval($row['total_on_hand']);
                $total_issued += intval($row['total_issued']);
                $total_reserved += intval($row['total_reserved']);
            }
        }
    
        // Update equipment_spares to point to the kept record
        foreach ($ids as $id) {
            $id = intval($id);
            echo "  Updating equipment_spares for ID $id...\n";
            if (!$connection->query("UPDATE equipment_spares SET part_id = $keep_id WHERE part_id = $id")) {
                echo "    Error: " . $connection->error . "\n";
            }
            
            // Merge stock_locales
            $sl_query = "SELECT * FROM stock_locales WHERE part_id = $id";
            $sl_result = $connection->query($sl_query);
            if ($sl_result) {
                while ($sl_row = $sl_result->fetch_assoc()) {
                    $loc_id = intval($sl_row['warehouse_location_id']);
                    $qty = intval($sl_row['quantity_on_hand']);
                    
                    // Check if stock_locale already exists for keep_id at this location
                    $existing = $connection->query("SELECT id FROM stock_locales WHERE part_id = $keep_id AND warehouse_location_id = $loc_id");
                    if ($existing && $existing->num_rows > 0) {
                        if (!$connection->query("UPDATE stock_locales SET quantity_on_hand = quantity_on_hand + $qty WHERE part_id = $keep_id AND warehouse_location_id = $loc_id")) {
                            echo "    Error updating stock_locales: " . $connection->error . "\n";
                        }
                    } else {
                        if (!$connection->query("INSERT INTO stock_locales (part_id, warehouse_location_id, quantity_on_hand, quantity_reserved) VALUES ($keep_id, $loc_id, $qty, 0)")) {
                            echo "    Error inserting stock_locales: " . $connection->error . "\n";
                        }
                    }
                }
            }
            
            // Merge inventory_transactions
            echo "  Updating inventory_transactions for ID $id...\n";
            if (!$connection->query("UPDATE inventory_transactions SET part_id = $keep_id WHERE part_id = $id")) {
                echo "    Error: " . $connection->error . "\n";
            }
            
            // Delete stock_locales first (foreign key constraint)
            echo "  Deleting stock_locales for ID $id...\n";
            if (!$connection->query("DELETE FROM stock_locales WHERE part_id = $id")) {
                echo "    Error: " . $connection->error . "\n";
            }
            
            // Delete the duplicate part record
            echo "  Deleting parts_master ID $id...\n";
            if (!$connection->query("DELETE FROM parts_master WHERE id = $id")) {
                echo "    Error deleting ID $id: " . $connection->error . "\n";
            }
        }
    
        // Update the kept record with consolidated totals
        echo "  Updating kept record ID $keep_id with totals...\n";
        if (!$connection->query("UPDATE parts_master SET total_on_hand = $total_stock, total_issued = $total_issued, total_reserved = $total_reserved WHERE id = $keep_id")) {
            echo "    Error: " . $connection->error . "\n";
        }
        
        $merged++;
    }


echo "\nStep 5: Verification...\n";

// Verify no more duplicates by part_code
$dup_codes = $connection->query("
    SELECT part_code, COUNT(*) as cnt
    FROM parts_master
    WHERE part_code != '' AND part_code IS NOT NULL
    GROUP BY part_code
    HAVING COUNT(*) > 1
")->num_rows;

// Verify no more duplicates by part_number
$dup_numbers = $connection->query("
    SELECT part_number, COUNT(*) as cnt
    FROM parts_master
    WHERE part_number != '' AND part_number IS NOT NULL
    GROUP BY part_number
    HAVING COUNT(*) > 1
")->num_rows;

// Verify no more duplicates by part_name (case-insensitive)
$dup_names = $connection->query("
    SELECT part_name, COUNT(*) as cnt
    FROM parts_master
    WHERE part_name != '' AND part_name IS NOT NULL
    GROUP BY LOWER(part_name)
    HAVING COUNT(*) > 1
")->num_rows;

echo "Remaining duplicates by code: $dup_codes\n";
echo "Remaining duplicates by number: $dup_numbers\n";
echo "Remaining duplicates by name: $dup_names\n";

echo "\n✅ Cleanup complete. Merged $merged duplicate groups.\n";
echo "\nYou can now reload the inventory page to see unified part listings.\n";
?>
