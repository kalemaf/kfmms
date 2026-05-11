<?php
/**
 * INTEGRATION TEST: Spares Reduction Workflow
 * Verifies that spares are reduced exactly once when completing a work order
 */

require_once 'config.inc.php';
require_once 'common.inc.php';
require_once 'spare_integration_functions.php';

echo "\n";
echo str_repeat("=", 100) . "\n";
echo "INTEGRATION TEST: SPARES REDUCTION WORKFLOW\n";
echo str_repeat("=", 100) . "\n\n";

$_SESSION['tenant_id'] = 1;
$_SESSION['user_id'] = 1;
$tenant_id = 1;
$user_id = 1;

// SETUP: Create or use existing equipment with spares
echo "SETUP: Preparing test environment\n";
echo str_repeat("-", 100) . "\n\n";

// Get equipment 1
$equipment = $connection->query("SELECT id, description FROM equipment WHERE id = 1 AND tenant_id = $tenant_id")->fetch_assoc();
if (!$equipment) {
    echo "❌ Equipment 1 not found\n";
    exit(1);
}
echo "✅ Using Equipment: {$equipment['description']} (ID: {$equipment['id']})\n";

// Get equipment spares
$spares = $connection->query("SELECT id, part_name, quantity FROM equipment_spares WHERE equipment_id = 1 AND tenant_id = $tenant_id ORDER BY id LIMIT 1")->fetch_assoc();
if (!$spares) {
    echo "❌ No spares found for this equipment\n";
    exit(1);
}
echo "✅ Spare Available: {$spares['part_name']} (ID: {$spares['id']}, Qty: {$spares['quantity']})\n\n";

$spare_id = $spares['id'];
$spare_qty_before = $spares['quantity'];

// Get the stock_locales entry for this spare's part
$stock_entry = $connection->query("
    SELECT sl.id, sl.quantity_on_hand FROM stock_locales sl
    WHERE sl.part_id = (SELECT part_id FROM equipment_spares WHERE id = $spare_id)
    LIMIT 1
")->fetch_assoc();

$stock_qty_before = 0;
if ($stock_entry) {
    $stock_qty_before = $stock_entry['quantity_on_hand'];
}

echo "BASELINE INVENTORY:\n";
echo "  Equipment Spare Qty: $spare_qty_before\n";
echo "  Stock Locale Qty: $stock_qty_before\n\n";

// TEST: Complete a work order with spare reduction
echo str_repeat("=", 100) . "\n";
echo "TEST: Completing Work Order with Spare Reduction\n";
echo str_repeat("-", 100) . "\n\n";

// Create a test work order
echo "Step 1: Creating test work order\n";
$test_wo_sql = "INSERT INTO work_orders (equipment, descriptive_text, requestor, wo_status, submit_date, tenant_id)
                VALUES (1, 'Test WO - Spares Reduction', 'tester', 'Open', datetime('now'), $tenant_id)";
$connection->query($test_wo_sql);
$test_wo_id = $connection->lastInsertId();
echo "✅ Created WO #$test_wo_id\n\n";

// Simulate complete_work_order.php flow
echo "Step 2: Processing work order completion (simulating complete_work_order.php)\n";

// Get the work order data
$workOrder = $connection->query("
    SELECT * FROM work_orders WHERE wo_id = $test_wo_id AND tenant_id = $tenant_id LIMIT 1
")->fetch_assoc();

if (!$workOrder) {
    echo "❌ Work order not found\n";
    exit(1);
}
echo "✅ Retrieved work order details\n\n";

// Simulate user selecting 1 unit of the spare in complete_work_order.php
echo "Step 3: User selected spare in complete_work_order.php\n";
$reduction_qty = 1;

// Record the spare usage in work_order_spares
$connection->query("
    INSERT INTO work_order_spares (wo_id, spare_id, quantity_used, tenant_id) 
    VALUES ($test_wo_id, $spare_id, $reduction_qty, $tenant_id)
");
echo "✅ Recorded spare usage in work_order_spares: Qty $reduction_qty\n\n";

// Reduce the spare inventory
echo "Step 4: Reducing inventory via reduce_spare_inventory()\n";
reduce_spare_inventory($spare_id, $reduction_qty, $test_wo_id, $user_id, 'Test WO #' . $test_wo_id, $connection);
echo "✅ Inventory reduction completed\n\n";

// Check results
echo str_repeat("=", 100) . "\n";
echo "VERIFICATION: Checking if spares were reduced correctly\n";
echo str_repeat("-", 100) . "\n\n";

// Get updated equipment spare quantity
$spare_after = $connection->query("SELECT quantity FROM equipment_spares WHERE id = $spare_id")->fetch_assoc();
$spare_qty_after = $spare_after['quantity'];

// Get updated stock_locales quantity
$stock_after = $connection->query("SELECT quantity_on_hand FROM stock_locales WHERE id = " . ($stock_entry['id'] ?? 0))->fetch_assoc();
$stock_qty_after = $stock_after ? $stock_after['quantity_on_hand'] : $stock_qty_before;

echo "RESULT COMPARISON:\n";
echo "  Equipment Spare: $spare_qty_before → $spare_qty_after (Expected: " . ($spare_qty_before - $reduction_qty) . ")\n";
echo "  Stock Locale: $stock_qty_before → $stock_qty_after (Expected: " . ($stock_qty_before - $reduction_qty) . ")\n";
echo "  Reduction Amount: " . ($spare_qty_before - $spare_qty_after) . " (Expected: $reduction_qty)\n\n";

// Verify
$spare_reduction_ok = ($spare_qty_after == ($spare_qty_before - $reduction_qty));
$stock_reduction_ok = ($stock_qty_after == ($stock_qty_before - $reduction_qty));
$reduction_count_ok = (($spare_qty_before - $spare_qty_after) == $reduction_qty);

echo str_repeat("-", 100) . "\n";
if ($spare_reduction_ok && $stock_reduction_ok && $reduction_count_ok) {
    echo "✅ TEST PASSED - Spares reduced correctly\n";
    echo "   - Equipment spare reduced by exactly $reduction_qty\n";
    echo "   - Stock locale reduced by exactly $reduction_qty\n";
    echo "   - NO duplicate reductions detected\n";
} else {
    echo "❌ TEST FAILED\n";
    if (!$spare_reduction_ok) echo "   ✗ Equipment spare reduction incorrect\n";
    if (!$stock_reduction_ok) echo "   ✗ Stock locale reduction incorrect\n";
    if (!$reduction_count_ok) echo "   ✗ Reduction amount incorrect\n";
}

echo "\n";
echo str_repeat("=", 100) . "\n";
echo "TEST COMPLETE\n";
echo str_repeat("=", 100) . "\n\n";

?>
