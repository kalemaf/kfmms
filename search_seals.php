<?php
require 'config.inc.php';
$connection = new mysqli($hostName, $userName, $password, $databaseName);

// Check all tables for spares/seals
echo "Searching for 'seal' references:\n";
$tables = ['equipment_spares', 'parts_master', 'work_order_spares', 'inventory_transactions', 'stock_locales'];

foreach ($tables as $table) {
    $result = $connection->query("SELECT * FROM $table WHERE part_name LIKE '%seal%' OR description LIKE '%seal%' LIMIT 1");
    if ($result && $result->num_rows > 0) {
        echo "Found in $table\n";
    }
}

// Also check what data exists in parts_master
echo "\nParts Master (first 10):\n";
$result = $connection->query("SELECT * FROM parts_master LIMIT 10");
while ($row = $result->fetch_assoc()) {
    echo "Part {$row['part_number']}: {$row['description']}\n";
}

// Check inventory
echo "\nInventory (first 10):\n";
$result = $connection->query("SELECT * FROM inventory_transactions LIMIT 10");
if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        echo "Qty: {$row['quantity']}, Type: {$row['transaction_type']}\n";
    }
} else {
    echo "No inventory transactions\n";
}

$connection->close();
?>