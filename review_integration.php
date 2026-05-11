<?php
require 'config.inc.php';

echo "=== SCHEMA REVIEW ===\n\n";

echo "EQUIPMENT_SPARES TABLE:\n";
if (table_exists('equipment_spares')) {
    if ($db_type === 'sqlite') {
        $result = $connection->query("PRAGMA table_info('equipment_spares')");
        while ($row = $result->fetch(PDO::FETCH_ASSOC)) {
            echo "  " . $row['name'] . " (" . $row['type'] . ")\n";
        }
    } else {
        $result = $connection->query("DESCRIBE equipment_spares");
        while ($row = $result->fetch(PDO::FETCH_ASSOC)) {
            echo "  " . $row['Field'] . " (" . $row['Type'] . ")\n";
        }
    }
} else {
    echo "  TABLE DOES NOT EXIST\n";
}

echo "\nPARTS_MASTER TABLE:\n";
if (table_exists('parts_master')) {
    if ($db_type === 'sqlite') {
        $result = $connection->query("PRAGMA table_info('parts_master')");
        while ($row = $result->fetch(PDO::FETCH_ASSOC)) {
            echo "  " . $row['name'] . " (" . $row['type'] . ")\n";
        }
    } else {
        $result = $connection->query("DESCRIBE parts_master");
        while ($row = $result->fetch(PDO::FETCH_ASSOC)) {
            echo "  " . $row['Field'] . " (" . $row['Type'] . ")\n";
        }
    }
} else {
    echo "  TABLE DOES NOT EXIST\n";
}

echo "\nSTOCK_LOCALES TABLE:\n";
if (table_exists('stock_locales')) {
    if ($db_type === 'sqlite') {
        $result = $connection->query("PRAGMA table_info('stock_locales')");
        while ($row = $result->fetch(PDO::FETCH_ASSOC)) {
            echo "  " . $row['name'] . " (" . $row['type'] . ")\n";
        }
    } else {
        $result = $connection->query("DESCRIBE stock_locales");
        while ($row = $result->fetch(PDO::FETCH_ASSOC)) {
            echo "  " . $row['Field'] . " (" . $row['Type'] . ")\n";
        }
    }
} else {
    echo "  TABLE DOES NOT EXIST\n";
}

echo "\nINVENTORY_TRANSACTIONS TABLE:\n";
if (table_exists('inventory_transactions')) {
    if ($db_type === 'sqlite') {
        $result = $connection->query("PRAGMA table_info('inventory_transactions')");
        while ($row = $result->fetch(PDO::FETCH_ASSOC)) {
            echo "  " . $row['name'] . " (" . $row['type'] . ")\n";
        }
    } else {
        $result = $connection->query("DESCRIBE inventory_transactions");
        while ($row = $result->fetch(PDO::FETCH_ASSOC)) {
            echo "  " . $row['Field'] . " (" . $row['Type'] . ")\n";
        }
    }
} else {
    echo "  TABLE DOES NOT EXIST\n";
}

echo "\nWORK_ORDER_SPARES TABLE:\n";
if (table_exists('work_order_spares')) {
    if ($db_type === 'sqlite') {
        $result = $connection->query("PRAGMA table_info('work_order_spares')");
        while ($row = $result->fetch(PDO::FETCH_ASSOC)) {
            echo "  " . $row['name'] . " (" . $row['type'] . ")\n";
        }
    } else {
        $result = $connection->query("DESCRIBE work_order_spares");
        while ($row = $result->fetch(PDO::FETCH_ASSOC)) {
            echo "  " . $row['Field'] . " (" . $row['Type'] . ")\n";
        }
    }
} else {
    echo "  TABLE DOES NOT EXIST\n";
}

echo "\nWO_PARTS TABLE:\n";
if (table_exists('wo_parts')) {
    if ($db_type === 'sqlite') {
        $result = $connection->query("PRAGMA table_info('wo_parts')");
        while ($row = $result->fetch(PDO::FETCH_ASSOC)) {
            echo "  " . $row['name'] . " (" . $row['type'] . ")\n";
        }
    } else {
        $result = $connection->query("DESCRIBE wo_parts");
        while ($row = $result->fetch(PDO::FETCH_ASSOC)) {
            echo "  " . $row['Field'] . " (" . $row['Type'] . ")\n";
        }
    }
} else {
    echo "  TABLE DOES NOT EXIST\n";
}

echo "\n=== SAMPLE DATA ===\n";

echo "\nEquipment Spares (first 3):\n";
$result = $connection->query("SELECT * FROM equipment_spares LIMIT 3");
while ($row = $result->fetch_assoc()) {
    echo "  ID {$row['id']}: {$row['part_name']} ({$row['part_number']}) - Qty: {$row['quantity']}\n";
}

echo "\nParts Master (first 3):\n";
$result = $connection->query("SELECT * FROM parts_master LIMIT 3");
while ($row = $result->fetch_assoc()) {
    echo "  ID {$row['id']}: {$row['part_name']} ({$row['part_number']}) - Cost: {$row['unit_cost']}\n";
}

echo "\nStock Locales (first 3):\n";
$result = $connection->query("SELECT sl.*, pm.part_name FROM stock_locales sl JOIN parts_master pm ON sl.part_id = pm.id LIMIT 3");
while ($row = $result->fetch_assoc()) {
    echo "  {$row['part_name']}: {$row['quantity_on_hand']} on hand, {$row['quantity_available']} available\n";
}

$connection->close();
?>