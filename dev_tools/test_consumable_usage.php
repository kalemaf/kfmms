<?php
/**
 * Test: Consumable Usage Recording with Tenant ID
 * 
 * Tests:
 * 1. Create a work order
 * 2. Add consumable to it
 * 3. Complete the work order (triggers consume_work_order_consumables)
 * 4. Verify usage is recorded with correct tenant_id
 * 5. Verify it shows in get_consumable_usage()
 */

require_once 'config.inc.php';
require_once 'common.inc.php';
require_once 'libraries/inventory_manager.php';

$_SESSION['tenant_id'] = 1;
$tenant_id = 1;

echo "\n╔════════════════════════════════════════════════════════════════════════╗\n";
echo "║    TEST: Consumable Usage Recording with Tenant ID                    ║\n";
echo "╚════════════════════════════════════════════════════════════════════════╝\n\n";

// Step 1: Create a test work order
echo "=== STEP 1: Create Test Work Order ===\n";
try {
    $sql = "INSERT INTO work_orders (descriptive_text, description, equipment, wo_status, submit_date, tenant_id) 
            VALUES (?, ?, ?, ?, date('now'), ?)";
    $stmt = $connection->prepare($sql);
    $stmt->execute(['Consumable Test WO', 'Test Description', 1, 'Pending Approval', $tenant_id]);
    $test_wo_id = $connection->lastInsertId();
    echo "[✓] Created WO #{$test_wo_id}\n\n";
} catch (Exception $e) {
    echo "[✗] Error creating WO: " . $e->getMessage() . "\n";
    exit(1);
}

// Step 2: Find a consumable to use
echo "=== STEP 2: Find Test Consumable ===\n";
$cons = $connection->query("SELECT id, name, current_stock FROM consumables WHERE tenant_id = $tenant_id LIMIT 1")->fetch(PDO::FETCH_ASSOC);
if (!$cons) {
    echo "[!] No consumables found\n";
    exit(0);
}
$consumable_id = $cons['id'];
$initial_stock = $cons['current_stock'];
echo "[✓] Using consumable: #{$consumable_id} ({$cons['name']}) - Current stock: {$initial_stock}\n\n";

// Step 3: Add consumable to work order
echo "=== STEP 3: Add Consumable to Work Order ===\n";
$qty_to_use = 3;
$sql = "INSERT INTO work_order_consumables (work_order_id, consumable_id, quantity_required, tenant_id) 
        VALUES ($test_wo_id, $consumable_id, $qty_to_use, $tenant_id)";
$connection->exec($sql);
echo "[✓] Added consumable to WO: Qty={$qty_to_use}\n\n";

// Step 4: Trigger consumption by calling consume_work_order_consumables
echo "=== STEP 4: Record Consumable Usage ===\n";
$result = consume_work_order_consumables($test_wo_id, $connection);
echo "[✓] consume_work_order_consumables returned: " . ($result ? "true" : "false") . "\n";

// Step 5: Verify usage record in database
echo "\n=== STEP 5: Verify Usage Record in Database ===\n";
$usage_record = $connection->query("
    SELECT * FROM consumable_usage 
    WHERE work_order_id = $test_wo_id 
    LIMIT 1
")->fetch(PDO::FETCH_ASSOC);

if ($usage_record) {
    echo "[✓] Usage record found:\n";
    echo "    ID: {$usage_record['id']}\n";
    echo "    Consumable ID: {$usage_record['consumable_id']}\n";
    echo "    Quantity Used: {$usage_record['quantity_used']}\n";
    echo "    Work Order ID: {$usage_record['work_order_id']}\n";
    echo "    Tenant ID: {$usage_record['tenant_id']}\n";
    echo "    Date: {$usage_record['usage_date']}\n";
    
    if ($usage_record['tenant_id'] == $tenant_id) {
        echo "\n[✓] CORRECT: Tenant ID matches current tenant!\n";
    } else {
        echo "\n[✗] ERROR: Tenant ID mismatch! Expected $tenant_id, got {$usage_record['tenant_id']}\n";
    }
} else {
    echo "[✗] No usage record found\n";
}

// Step 6: Verify it shows in get_consumable_usage()
echo "\n=== STEP 6: Verify get_consumable_usage() Finds It ===\n";
$usage_records = get_consumable_usage($connection, 50);
echo "get_consumable_usage() returned: " . count($usage_records) . " records\n";

$found = false;
foreach ($usage_records as $record) {
    if ($record['work_order_id'] == $test_wo_id) {
        echo "[✓] FOUND: {$record['consumable_name']} - Qty: {$record['quantity_used']} on WO #{$record['work_order_id']}\n";
        $found = true;
    }
}

if (!$found) {
    echo "[✗] Usage record NOT found in get_consumable_usage() results\n";
} else {
    echo "[✓] SUCCESS: Consumable usage is now visible!\n";
}

// Step 7: Verify stock was reduced
echo "\n=== STEP 7: Verify Stock Was Reduced ===\n";
$cons_after = $connection->query("SELECT current_stock FROM consumables WHERE id = $consumable_id")->fetch(PDO::FETCH_ASSOC);
$current_stock = $cons_after['current_stock'];
$expected_stock = $initial_stock - $qty_to_use;

echo "Initial stock: {$initial_stock}\n";
echo "Quantity used: {$qty_to_use}\n";
echo "Current stock: {$current_stock}\n";
echo "Expected stock: {$expected_stock}\n";

if ($current_stock == $expected_stock) {
    echo "[✓] Stock correctly reduced\n";
} else {
    echo "[✗] Stock reduction mismatch\n";
}

// Cleanup
echo "\n=== CLEANUP ===\n";
$connection->exec("DELETE FROM consumable_usage WHERE work_order_id = $test_wo_id");
$connection->exec("DELETE FROM work_order_consumables WHERE work_order_id = $test_wo_id");
$connection->exec("DELETE FROM work_orders WHERE wo_id = $test_wo_id");
// Restore stock
$connection->exec("UPDATE consumables SET current_stock = $initial_stock WHERE id = $consumable_id");
echo "[✓] Test data cleaned up\n\n";

?>
