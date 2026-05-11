<?php
/**
 * Create test WO and investigate spares reduction
 */

require_once 'config.inc.php';
require_once 'common.inc.php';

$_SESSION['tenant_id'] = 1;
$tenant_id = 1;

echo "Creating test work order...\n";

// Insert a test WO
$result = $connection->query("
    INSERT INTO work_orders (equipment, descriptive_text, requestor, wo_status, submit_date, tenant_id)
    VALUES (1, 'Test WO for spares reduction', 'tester', 'Open', NOW(), $tenant_id)
");

if ($result === false) {
    echo "Error creating WO: " . $connection->error . "\n";
    exit(1);
}

// Get the inserted WO ID
$wo_id = $connection->insert_id;
echo "✅ Created WO #$wo_id\n\n";

// Check spares for equipment 1
echo "Equipment 1 spares:\n";
$spares = $connection->query("SELECT id, part_name, quantity FROM equipment_spares WHERE equipment_id = 1 AND tenant_id = $tenant_id");
$spare_info = [];
while ($spare = $spares->fetch_assoc()) {
    $spare_info[$spare['id']] = [
        'name' => $spare['part_name'],
        'qty_before' => $spare['quantity']
    ];
    echo "  ID {$spare['id']}: {$spare['part_name']} - Qty: {$spare['quantity']}\n";
}

echo "\nDone. WO #$wo_id ready for testing.\n";
echo "You can now:\n";
echo "1. Go to work_order.php to edit WO #$wo_id\n";
echo "2. Add spares and save\n";
echo "3. Complete the work order\n";
echo "4. Check if inventory is reduced\n";

?>
