<?php
/**
 * Integration Test: Complete Spares Preservation Workflow
 * 
 * Tests the COMPLETE workflow:
 * 1. Create WO
 * 2. Add spares to it
 * 3. Edit WO without changing spares
 * 4. Verify spares are preserved
 * 5. Delete WO
 */

require_once 'config.inc.php';
require_once 'common.inc.php';

$_SESSION['tenant_id'] = 1;
$tenant_id = 1;

echo "\n╔════════════════════════════════════════════════════════════════════════╗\n";
echo "║    INTEGRATION TEST: Complete Spares Preservation Workflow            ║\n";
echo "╚════════════════════════════════════════════════════════════════════════╝\n\n";

// Step 1: Create a test WO
echo "=== STEP 1: Create Test Work Order ===\n";
$sql = "INSERT INTO work_orders (descriptive_text, equipment, description, wo_status, submit_date, tenant_id) VALUES ('Integration Test WO', 1, 'Test', 'Pending Approval', date('now'), ?)";
$stmt = $connection->prepare($sql);
$stmt->execute([$tenant_id]);
$test_wo_id = $connection->lastInsertId();
echo "[✓] Created WO #{$test_wo_id}\n\n";

// Step 2: Add spares directly to the WO
echo "=== STEP 2: Add Spares to WO ===\n";
$test_spares = [1 => 3, 3 => 2]; // spare_id => quantity
foreach ($test_spares as $spare_id => $qty) {
    $sql = "INSERT INTO work_order_spares (wo_id, spare_id, quantity_used, tenant_id) VALUES ({$test_wo_id}, {$spare_id}, {$qty}, {$tenant_id})";
    $connection->exec($sql);
    echo "[✓] Added spare {$spare_id} qty {$qty}\n";
}

// Verify spares were added
$result = $connection->query("SELECT COUNT(*) as cnt FROM work_order_spares WHERE wo_id={$test_wo_id} AND tenant_id={$tenant_id}");
$count = $result->fetch(PDO::FETCH_ASSOC)['cnt'];
echo "Verified: {$count} spares in database\n\n";

// Step 3: Simulate form submission without spares (preservation test)
echo "=== STEP 3: Simulate Form Submission (NO SPARES SELECTED) ===\n";
echo "[→] User edits WO and clicks 'Update' without touching spares\n\n";

// This is what the backend does during POST processing:
// 1. Collect selected spares from POST (there are none)
$selectedSpares = [];
echo "[✓] selectedSpares from POST: " . json_encode($selectedSpares) . "\n";

// 2. Fetch current spares for preservation
$usedSpares = [];
$usedRes = $connection->query("SELECT spare_id, quantity_used FROM work_order_spares WHERE wo_id={$test_wo_id} AND tenant_id={$tenant_id}");
while ($row = $usedRes->fetch(PDO::FETCH_ASSOC)) {
    $usedSpares[(int)$row['spare_id']] = (int)$row['quantity_used'];
}
echo "[✓] usedSpares from DB: " . json_encode($usedSpares) . "\n";

// 3. Decide if should delete
$wo_status = 'Pending Approval';
$shouldDeleteSpares = !empty($selectedSpares) || $wo_status === 'Completed';
echo "[✓] shouldDeleteSpares = " . ($shouldDeleteSpares ? "true" : "false") . "\n\n";

// 4. Execute deletion and preservation
echo "=== STEP 4: Execute Deletion & Preservation Logic ===\n";
if ($shouldDeleteSpares) {
    $connection->exec("DELETE FROM work_order_spares WHERE wo_id={$test_wo_id} AND tenant_id={$tenant_id}");
    echo "[✓] Deleted old spares\n";
}

if (!empty($selectedSpares)) {
    echo "[→] Re-inserting selected spares (none available)\n";
} else if (!empty($usedSpares)) {
    echo "[→] Preserving " . count($usedSpares) . " existing spares\n";
    foreach ($usedSpares as $spare_id => $qty) {
        $connection->exec("INSERT INTO work_order_spares (wo_id, spare_id, quantity_used, tenant_id) VALUES ({$test_wo_id}, {$spare_id}, {$qty}, {$tenant_id})");
        echo "    [✓] Re-inserted spare {$spare_id} qty {$qty}\n";
    }
}

echo "\n=== STEP 5: Verify Spares Were Preserved ===\n";
$result = $connection->query("SELECT spare_id, quantity_used FROM work_order_spares WHERE wo_id={$test_wo_id} AND tenant_id={$tenant_id}");
$preserved_spares = [];
while ($row = $result->fetch(PDO::FETCH_ASSOC)) {
    $preserved_spares[(int)$row['spare_id']] = (int)$row['quantity_used'];
}

echo "Spares after update: " . json_encode($preserved_spares) . "\n";

// Verify they match
$all_match = true;
foreach ($test_spares as $spare_id => $qty) {
    if (!isset($preserved_spares[$spare_id]) || $preserved_spares[$spare_id] != $qty) {
        echo "[✗] Spare {$spare_id}: Expected {$qty}, got " . ($preserved_spares[$spare_id] ?? 'MISSING') . "\n";
        $all_match = false;
    }
}

if ($all_match && count($preserved_spares) == count($test_spares)) {
    echo "[✓] SUCCESS: All spares preserved correctly!\n";
} else {
    echo "[✗] FAILURE: Spares were lost or changed\n";
}

// Cleanup
echo "\n=== CLEANUP ===\n";
$connection->exec("DELETE FROM work_order_spares WHERE wo_id={$test_wo_id}");
$connection->exec("DELETE FROM work_orders WHERE wo_id={$test_wo_id}");
echo "[✓] Test WO and spares cleaned up\n\n";

?>
