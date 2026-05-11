<?php
/**
 * Fix WO16 Spares Issues
 * 1. Remove duplicate work_order_spares record
 * 2. Verify spares reduction
 */

require_once 'config.inc.php';
require_once 'common.inc.php';
require_once 'spare_integration_functions.php';

$_SESSION['tenant_id'] = 35;
$_SESSION['user_id'] = 1;
$tenant_id = 35;
$wo_id = 16;
$spare_id = 11;
$quantity_used = 2;

echo "Fixing WO #16 Spares Issues\n";
echo str_repeat("=", 100) . "\n\n";

// STEP 1: Check current state
echo "BEFORE FIX:\n";
echo str_repeat("-", 100) . "\n";

$wo_spares = $connection->query("SELECT id, spare_id, quantity_used FROM work_order_spares WHERE wo_id = $wo_id AND tenant_id = $tenant_id");
$spare_records = [];
while ($row = $wo_spares->fetch_assoc()) {
    $spare_records[] = $row['id'];
    echo "  Record ID {$row['id']}: spare_id={$row['spare_id']}, qty={$row['quantity_used']}\n";
}
echo "Total records: " . count($spare_records) . "\n";

$spare_before = $connection->query("SELECT quantity FROM equipment_spares WHERE id = $spare_id")->fetch_assoc()['quantity'];
echo "Equipment spare quantity: $spare_before\n\n";

// STEP 2: Remove duplicates (keep only first record)
echo "Removing duplicate records...\n";
echo str_repeat("-", 100) . "\n";
if (count($spare_records) > 1) {
    // Keep the first, delete others
    for ($i = 1; $i < count($spare_records); $i++) {
        $del_id = $spare_records[$i];
        $connection->query("DELETE FROM work_order_spares WHERE id = $del_id");
        echo "  Deleted duplicate record ID $del_id\n";
    }
    echo "Duplicate removal complete\n\n";
} else {
    echo "  No duplicates found\n\n";
}

// STEP 3: Verify spares reduction
echo "VERIFYING SPARES REDUCTION:\n";
echo str_repeat("-", 100) . "\n";

// Check if inventory was already reduced
$current_qty = $connection->query("SELECT quantity FROM equipment_spares WHERE id = $spare_id")->fetch_assoc()['quantity'];
echo "Current equipment spare quantity: $current_qty\n";
echo "Expected after reduction: " . ($spare_before - $quantity_used) . "\n";

if ($current_qty == ($spare_before - $quantity_used)) {
    echo "✅ Spares already correctly reduced!\n";
} elseif ($current_qty == $spare_before) {
    echo "❌ Spares NOT reduced - inventory unchanged\n";
    echo "   Attempting to reduce now...\n";
    reduce_spare_inventory($spare_id, $quantity_used, $wo_id, $_SESSION['user_id'], 'Fix WO#' . $wo_id, $connection);
    $new_qty = $connection->query("SELECT quantity FROM equipment_spares WHERE id = $spare_id")->fetch_assoc()['quantity'];
    echo "   New quantity: $new_qty\n";
} else {
    echo "⚠️ Inventory has unexpected value: $current_qty\n";
}

echo "\n";
echo str_repeat("=", 100) . "\n";
echo "Fix Complete\n";
echo str_repeat("=", 100) . "\n";

?>
