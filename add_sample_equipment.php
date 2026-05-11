<?php
/**
 * Quick Equipment Setup for Testing
 * Adds sample equipment so you can see the Equipment Compatibility section
 */

require_once 'config.inc.php';

if (!$connection) {
    die("Database connection failed");
}

try {
    // Check if equipment table exists
    $result = $connection->query("SELECT name FROM sqlite_master WHERE type='table' AND name='equipment'");
    if ($result->fetch(PDO::FETCH_ASSOC)) {
        echo "✓ Equipment table exists\n";
    } else {
        echo "✗ Equipment table missing - run setup first\n";
        exit;
    }

    // Check current equipment count
    $result = $connection->query("SELECT COUNT(*) as count FROM equipment");
    $count = $result->fetch(PDO::FETCH_ASSOC)['count'];
    echo "Current equipment count: $count\n";

    if ($count == 0) {
        // Add sample equipment
        $equipment = [
            ['Pump-101', 'Centrifugal Pump', 'ACME Pumps', 'CP-500', 'PUMP-ROOM-1', 'Active', 'SN-12345'],
            ['Motor-200', 'Electric Motor', 'Siemens', '1LA7-123', 'MOTOR-BAY-2', 'Active', 'SN-67890'],
            ['Compressor-15', 'Air Compressor', 'Atlas Copco', 'GA-15', 'COMP-ROOM-3', 'Active', 'SN-54321'],
            ['Generator-05', 'Backup Generator', 'Caterpillar', 'C32', 'GEN-ROOM-4', 'Active', 'SN-09876']
        ];

        $stmt = $connection->prepare("INSERT INTO equipment (description, manufacturer, model, location, status, serial_number) VALUES (?, ?, ?, ?, ?, ?)");

        foreach ($equipment as $eq) {
            $stmt->execute($eq);
            echo "✓ Added: {$eq[0]} - {$eq[1]}\n";
        }

        echo "\n✅ Sample equipment added successfully!\n";
        echo "Now refresh your Parts Master form and scroll down - you should see the Equipment Compatibility section with checkboxes.\n";

    } else {
        echo "Equipment already exists. The Equipment Compatibility section should be visible.\n";
    }

} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>