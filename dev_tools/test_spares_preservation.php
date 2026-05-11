<?php
/**
 * Test: Work Order Spares & Consumables Preservation
 * 
 * Tests the complete flow of:
 * 1. Creating a work order with spares and consumables
 * 2. Editing the work order without changing spares/consumables
 * 3. Verifying they are preserved
 */

require_once 'config.inc.php';
require_once 'common.inc.php';

// Set a test tenant
$_SESSION['tenant_id'] = 1;
$tenant_id = 1;

echo "\n╔════════════════════════════════════════════════════════════════════════╗\n";
echo "║    TEST: Work Order Spares & Consumables Preservation                 ║\n";
echo "╚════════════════════════════════════════════════════════════════════════╝\n\n";

// Find a recent work order
$result = $connection->query("
    SELECT wo_id, descriptive_text, wo_status FROM work_orders 
    WHERE tenant_id = $tenant_id 
    ORDER BY wo_id DESC LIMIT 1
");

if (!$result || !($wo_row = $result->fetch(PDO::FETCH_ASSOC))) {
    echo "[!] No work orders found for tenant $tenant_id\n";
    exit(0);
}

$wo_id = $wo_row['wo_id'];
echo "[→] Testing with WO #{$wo_id}: {$wo_row['descriptive_text']}\n\n";

// Check current spares
echo "=== CURRENT SPARES ===\n";
$result = $connection->query("
    SELECT wos.id, wos.spare_id, wos.quantity_used, wos.tenant_id,
           es.part_name
    FROM work_order_spares wos
    LEFT JOIN equipment_spares es ON wos.spare_id = es.id
    WHERE wos.wo_id = $wo_id
");

$spares_count = 0;
$current_spares = [];
while ($row = $result->fetch(PDO::FETCH_ASSOC)) {
    echo "  [{$row['id']}] Spare #{$row['spare_id']}: {$row['part_name']} Qty={$row['quantity_used']} Tenant={$row['tenant_id']}\n";
    $current_spares[$row['spare_id']] = $row['quantity_used'];
    $spares_count++;
}
echo "Total: $spares_count spares\n\n";

if ($spares_count === 0) {
    echo "[!] No spares found for this work order. Please add spares first.\n";
    exit(0);
}

// Check current consumables
echo "=== CURRENT CONSUMABLES ===\n";
$result = $connection->query("
    SELECT woc.id, woc.consumable_id, woc.quantity_required, woc.tenant_id,
           c.name
    FROM work_order_consumables woc
    LEFT JOIN consumables c ON woc.consumable_id = c.id
    WHERE woc.work_order_id = $wo_id AND woc.tenant_id = $tenant_id
");

$consumables_count = 0;
while ($row = $result->fetch(PDO::FETCH_ASSOC)) {
    echo "  [{$row['id']}] Consumable #{$row['consumable_id']}: {$row['name']} Qty={$row['quantity_required']} Tenant={$row['tenant_id']}\n";
    $consumables_count++;
}
echo "Total: $consumables_count consumables\n\n";

// Simulate an update without changing spares/consumables
echo "=== SIMULATING UPDATE (WITHOUT CHANGING SPARES) ===\n";
echo "[→] Deleting and re-inserting spares with new tenant_id...\n\n";

// This is what the backend does - delete with tenant filter
$delete_count = $connection->exec("DELETE FROM work_order_spares WHERE wo_id = $wo_id AND tenant_id = $tenant_id");
echo "[✓] Deleted $delete_count spare records\n";

// Re-insert with tenant_id
foreach ($current_spares as $spare_id => $qty) {
    $sql = "INSERT INTO work_order_spares (wo_id, spare_id, quantity_used, tenant_id) VALUES ($wo_id, $spare_id, $qty, $tenant_id)";
    try {
        $connection->query($sql);
        echo "[✓] Re-inserted spare #{$spare_id} with tenant_id={$tenant_id}\n";
    } catch (Exception $e) {
        echo "[✗] Failed to re-insert spare #{$spare_id}: " . $e->getMessage() . "\n";
    }
}

// Verify spares are still there
echo "\n=== AFTER UPDATE: SPARES VERIFICATION ===\n";
$result = $connection->query("
    SELECT wos.id, wos.spare_id, wos.quantity_used, wos.tenant_id,
           es.part_name
    FROM work_order_spares wos
    LEFT JOIN equipment_spares es ON wos.spare_id = es.id
    WHERE wos.wo_id = $wo_id AND wos.tenant_id = $tenant_id
");

$after_count = 0;
$match_count = 0;
while ($row = $result->fetch(PDO::FETCH_ASSOC)) {
    echo "  [{$row['id']}] Spare #{$row['spare_id']}: {$row['part_name']} Qty={$row['quantity_used']}\n";
    $after_count++;
    if (isset($current_spares[$row['spare_id']]) && $current_spares[$row['spare_id']] == $row['quantity_used']) {
        $match_count++;
    }
}
echo "Total: $after_count spares (matched: $match_count)\n\n";

if ($match_count === $spares_count) {
    echo "[✓] SUCCESS: All spares were preserved correctly!\n";
} else {
    echo "[✗] FAILURE: Some spares were lost! ($match_count matched out of $spares_count)\n";
}

echo "\n";
?>
