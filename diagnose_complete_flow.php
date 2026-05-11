<?php
/**
 * Diagnostic: Complete Work Order Flow
 * 
 * Tests:
 * 1. Finding a recent WO with spares
 * 2. Simulating an edit form load
 * 3. Simulating a form submission without changing spares
 * 4. Verifying spares are preserved
 */

require_once 'config.inc.php';
require_once 'common.inc.php';

// Set test tenant
$_SESSION['tenant_id'] = 1;
$tenant_id = 1;

echo "\n╔════════════════════════════════════════════════════════════════════════╗\n";
echo "║    DIAGNOSTIC: Complete Work Order Update Flow                        ║\n";
echo "╚════════════════════════════════════════════════════════════════════════╝\n\n";

// Find WO with spares
$result = $connection->query("
    SELECT wo_id, descriptive_text, equipment 
    FROM work_orders 
    WHERE tenant_id = $tenant_id 
      AND wo_id IN (SELECT DISTINCT wo_id FROM work_order_spares WHERE tenant_id = $tenant_id)
    ORDER BY wo_id DESC LIMIT 1
");

if (!$result || !($wo_row = $result->fetch(PDO::FETCH_ASSOC))) {
    echo "[!] No work orders with spares found for tenant $tenant_id\n";
    exit(0);
}

$wo_id = $wo_row['wo_id'];
$equipment_id = $wo_row['equipment'];
echo "[→] Testing with WO #{$wo_id}: {$wo_row['descriptive_text']}\n";
echo "[→] Equipment: {$equipment_id}\n\n";

// ===== STEP 1: Fetch spares from API (simulating form load) =====
echo "=== STEP 1: Simulate API Call (what loadSpares() does) ===\n";
echo "[→] GET api_spares.php?equipment_id={$equipment_id}\n";

$spares_query = "SELECT id, part_name, part_number, quantity, 'spare' as type FROM equipment_spares WHERE equipment_id={$equipment_id} ORDER BY part_name";
$spares_query = apply_tenant_filter($spares_query);
echo "    Query: {$spares_query}\n";

$q = $connection->query($spares_query);
if (!$q) {
    echo "    [✗] Query failed\n";
} else {
    $items = [];
    while ($row = $q->fetch(PDO::FETCH_ASSOC)) {
        $items[] = $row;
        echo "    [{$row['id']}] {$row['part_name']} ({$row['part_number']}) - Qty: {$row['quantity']}\n";
    }
    echo "    Total: " . count($items) . " items returned\n";
}

// ===== STEP 2: Get currently linked spares (edit mode) =====
echo "\n=== STEP 2: Fetch Currently Linked Spares (for edit mode form) ===\n";
$usedRes = $connection->query("
    SELECT spare_id, quantity_used 
    FROM work_order_spares 
    WHERE wo_id=$wo_id AND tenant_id=$tenant_id
");

$usedSpares = [];
while ($row = $usedRes->fetch(PDO::FETCH_ASSOC)) {
    $spare_id = (int)$row['spare_id'];
    $qty = (int)$row['quantity_used'];
    $usedSpares[$spare_id] = $qty;
    echo "  [JavaScript receives] sparesUsed[{$spare_id}] = {$qty}\n";
}
echo "Total: " . count($usedSpares) . " spares already linked\n";

// ===== STEP 3: Simulate form submission (POST) =====
echo "\n=== STEP 3: Simulate Form Submission (POST data) ===\n";
echo "[→] User leaves spares unchanged, clicking 'Update Work Order'\n";

// Simulate form inputs
$_POST['descriptive_text'] = 'test update';
$_POST['equipment'] = $equipment_id;
foreach ($usedSpares as $spare_id => $qty) {
    $_POST["spares_{$spare_id}"] = (string)$qty;
    echo "  POST[spares_{$spare_id}] = {$qty}\n";
}

// Extract spares from POST (like work_order.php does)
$selectedSpares = [];
foreach ($_POST as $key => $val) {
    if (strpos($key, 'spares_') === 0) {
        $spare_id = (int)substr($key, 7);
        $qty = (int)$val;
        if ($spare_id > 0 && $qty > 0) {
            $selectedSpares[$spare_id] = $qty;
        }
    }
}
echo "Collected " . json_encode($selectedSpares) . " array\n";

// ===== STEP 4: Process deletion and reinsertion =====
echo "\n=== STEP 4: Delete and Re-insert (work_order.php update logic) ===\n";

// Delete with tenant filter
$delete_count = $connection->exec("DELETE FROM work_order_spares WHERE wo_id=$wo_id AND tenant_id=$tenant_id");
echo "[✓] Deleted $delete_count spare records\n";

// Re-insert
foreach ($selectedSpares as $spare_id => $qty) {
    $sql = "INSERT INTO work_order_spares (wo_id, spare_id, quantity_used, tenant_id) VALUES ($wo_id, $spare_id, $qty, $tenant_id)";
    try {
        $connection->exec($sql);
        echo "[✓] Inserted spare {$spare_id} qty={$qty}\n";
    } catch (Exception $e) {
        echo "[✗] Failed to insert spare {$spare_id}: " . $e->getMessage() . "\n";
    }
}

// ===== STEP 5: Verify =====
echo "\n=== STEP 5: Verify Spares After Update ===\n";
$result = $connection->query("
    SELECT wos.spare_id, wos.quantity_used, wos.tenant_id,
           es.part_name
    FROM work_order_spares wos
    LEFT JOIN equipment_spares es ON wos.spare_id = es.id
    WHERE wos.wo_id = $wo_id AND wos.tenant_id = $tenant_id
");

$final_spares = [];
while ($row = $result->fetch(PDO::FETCH_ASSOC)) {
    echo "  [{$row['spare_id']}] {$row['part_name']} qty={$row['quantity_used']} tenant={$row['tenant_id']}\n";
    $final_spares[(int)$row['spare_id']] = (int)$row['quantity_used'];
}

// Compare
echo "\n=== COMPARISON ===\n";
if (count($usedSpares) === count($final_spares)) {
    $all_match = true;
    foreach ($usedSpares as $spare_id => $qty) {
        if (!isset($final_spares[$spare_id]) || $final_spares[$spare_id] != $qty) {
            echo "[✗] Spare {$spare_id}: Expected {$qty}, got " . ($final_spares[$spare_id] ?? 'MISSING') . "\n";
            $all_match = false;
        }
    }
    if ($all_match) {
        echo "[✓] SUCCESS: All spares matched!\n";
    }
} else {
    echo "[✗] FAILURE: Spare count mismatch\n";
    echo "    Before: " . count($usedSpares) . "\n";
    echo "    After: " . count($final_spares) . "\n";
}

echo "\n";
?>
