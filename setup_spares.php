<?php
require 'config.inc.php';
$connection = new mysqli($hostName, $userName, $password, $databaseName);

// Get equipment IDs
$result = $connection->query("SELECT id, description FROM equipment LIMIT 5");
echo "Equipment in system:\n";
$equip_ids = [];
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $equip_ids[] = $row['id'];
        echo "ID {$row['id']}: {$row['description']}\n";
    }
}

// Create sample spares for first equipment (ID 1)
if (count($equip_ids) > 0) {
    $equip_id = $equip_ids[0];
    
    // Clear existing spares for this equipment
    $connection->query("DELETE FROM equipment_spares WHERE equipment_id = {$equip_id}");
    
    // Add common spares
    $spares = [
        ['part_name' => 'Seals Kit', 'part_number' => 'SEAL-001', 'quantity' => 56],
        ['part_name' => 'Ball Bearing 6206', 'part_number' => 'BEAR-6206', 'quantity' => 12],
        ['part_name' => 'Shaft Coupling', 'part_number' => 'COUP-001', 'quantity' => 8],
        ['part_name' => 'Drive Belt', 'part_number' => 'BELT-001', 'quantity' => 5],
        ['part_name' => 'Oil Filter', 'part_number' => 'FILT-001', 'quantity' => 10],
    ];
    
    foreach ($spares as $spare) {
        $query = "INSERT INTO equipment_spares (equipment_id, part_name, part_number, quantity) 
                  VALUES ({$equip_id}, '{$spare['part_name']}', '{$spare['part_number']}', {$spare['quantity']})";
        if ($connection->query($query)) {
            echo "Added: {$spare['part_name']} (qty: {$spare['quantity']})\n";
        } else {
            echo "Error adding {$spare['part_name']}: " . $connection->error . "\n";
        }
    }
    
    echo "\nSpares created for Equipment {$equip_id}.\n";
}

// Verify
$result = $connection->query("SELECT * FROM equipment_spares WHERE equipment_id = 1");
echo "\nVerification - Equipment Spares for Equipment 1:\n";
if ($result) {
    while ($row = $result->fetch_assoc()) {
        echo "{$row['part_name']}: {$row['quantity']}\n";
    }
}

$connection->close();
?>