<?php
/**
 * SQLite spare integration helper
 *
 * This script links existing equipment_spares entries to parts_master records
 * and ensures spare part inventory is visible for work order spare reduction.
 */

require_once 'config.inc.php';

if (!$connection || $db_type !== 'sqlite') {
    echo "This script must be run against a SQLite database with DB_TYPE=sqlite.\n";
    exit(1);
}

echo "Linking equipment spares to parts_master...\n";

$updates = 0;
$spares = $connection->query("SELECT id, part_name, part_number FROM equipment_spares WHERE part_id IS NULL OR part_id = ''");
if ($spares) {
    while ($spare = $spares->fetch(PDO::FETCH_ASSOC)) {
        $part_id = null;

        if (!empty($spare['part_number'])) {
            $part = $connection->query("SELECT id FROM parts_master WHERE part_number = '" . $connection->real_escape_string($spare['part_number']) . "' LIMIT 1");
            if ($part && $row = $part->fetch(PDO::FETCH_ASSOC)) {
                $part_id = $row['id'];
            }
        }

        if (empty($part_id) && !empty($spare['part_name'])) {
            $part = $connection->query("SELECT id FROM parts_master WHERE part_name = '" . $connection->real_escape_string($spare['part_name']) . "' LIMIT 1");
            if ($part && $row = $part->fetch(PDO::FETCH_ASSOC)) {
                $part_id = $row['id'];
            }
        }

        if ($part_id) {
            $connection->query("UPDATE equipment_spares SET part_id = {$part_id} WHERE id = " . intval($spare['id']));
            $updates++;
            echo "  Linked spare ID {$spare['id']} to part_id {$part_id}\n";
        } else {
            echo "  No parts_master match for spare ID {$spare['id']} ({$spare['part_name']} / {$spare['part_number']})\n";
        }
    }
}

echo "Linked {$updates} equipment spares to parts_master.\n";

// Ensure warehouse location 1 exists for inventory transactions
$location = $connection->query("SELECT id FROM warehouse_locations WHERE id = 1");
if (!$location || $location->fetch(PDO::FETCH_ASSOC) === null) {
    echo "Creating default warehouse location...\n";
    $connection->query("INSERT INTO warehouse_locations (warehouse_code, warehouse_name, location_name, is_active) VALUES ('MAIN', 'Main Warehouse', 'Main Warehouse', 1)");
}

echo "Done. You can now run work order completion to apply spare reductions across equipment spares and inventory.\n";
