<?php
require 'config.inc.php';

$connection = new mysqli($hostName, $userName, $password, $databaseName);

echo "=== ADDING COSTS TO SPARES ===\n\n";

// Add realistic unit costs for each spare
$costs = [
    'Seals Kit' => 45.50,
    'gear box' => 150.00,
    'Ball Bearing 6206' => 12.75,
    'Shaft Coupling' => 28.30,
    'Drive Belt' => 35.00,
    'Oil Filter' => 18.50
];

foreach ($costs as $part_name => $cost) {
    $update_sql = "UPDATE parts_master 
                   SET unit_cost = {$cost}
                   WHERE part_name = '" . $connection->real_escape_string($part_name) . "'";
    
    if ($connection->query($update_sql)) {
        echo "✓ {$part_name}: \${$cost}\n";
    } else {
        echo "✗ Error updating {$part_name}: " . $connection->error . "\n";
    }
}

echo "\n=== SPARE COSTS UPDATED ===\n\n";

// Show updated costs
$result = $connection->query("
    SELECT part_name, unit_cost 
    FROM parts_master 
    WHERE id BETWEEN 16 AND 21
    ORDER BY id
");

echo "Current Costs:\n";
while ($row = $result->fetch_assoc()) {
    echo "  {$row['part_name']}: \${$row['unit_cost']}\n";
}

$connection->close();
?>
