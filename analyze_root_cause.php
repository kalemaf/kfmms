<?php
/**
 * Root Cause Analysis: Spares Disappearing
 *  
 * This script analyzes:
 * 1. Are spares being populated in the form correctly?
 * 2. Is the preservation logic being triggered?
 * 3. Are there any tenant_id mismatches?
 */

require_once 'config.inc.php';
require_once 'common.inc.php';

$_SESSION['tenant_id'] = 1;
$tenant_id = 1;

echo "\n╔════════════════════════════════════════════════════════════════════════╗\n";
echo "║    ROOT CAUSE ANALYSIS: Why Spares Disappear                          ║\n";
echo "╚════════════════════════════════════════════════════════════════════════╝\n\n";

echo "=== THEORY 1: Form Doesn't Populate Spares Because JavaScript Fails ===\n";

// Find a WO with spares
$result = $connection->query("
    SELECT wo.wo_id, wo.equipment, wo.wo_status
    FROM work_orders wo
    WHERE wo.tenant_id = $tenant_id
    LIMIT 1
");
$wo = $result->fetch(PDO::FETCH_ASSOC);
if (!$wo) {
    echo "[!] No work orders found\n";
    exit(0);
}

$wo_id = $wo['wo_id'];
$equipment_id = $wo['equipment'];

// Add test spares if none exist
$test_spare_id = 1;
$test_qty = 5;
$existing = $connection->query("SELECT COUNT(*) as cnt FROM work_order_spares WHERE wo_id=$wo_id")->fetch(PDO::FETCH_ASSOC);
if ($existing['cnt'] == 0) {
    // Add a test spare
    $connection->exec("INSERT INTO work_order_spares (wo_id, spare_id, quantity_used, tenant_id) VALUES ($wo_id, $test_spare_id, $test_qty, $tenant_id)");
    echo "[+] Added test spare to WO for testing\n";
}

echo "[→] Testing WO #$wo_id with equipment $equipment_id\n";
echo "[→] Current status: {$wo['wo_status']}\n\n";

// Check spare inventory
$spare_check = $connection->query("
    SELECT es.id, es.part_name, es.quantity, es.tenant_id
    FROM equipment_spares es
    WHERE es.equipment_id = $equipment_id AND es.tenant_id = $tenant_id
    LIMIT 1
");

if ($spare = $spare_check->fetch(PDO::FETCH_ASSOC)) {
    echo "[✓] Equipment has spares: ID={$spare['id']}, Name={$spare['part_name']}, Qty={$spare['quantity']}\n";
} else {
    echo "[!] Equipment has NO spares or tenant mismatch\n";
}

echo "\n=== THEORY 2: User Submits Form Without Spares Selected ===\n";
echo "If user doesn't interact with spare inputs, no spares_ POST fields are sent\n";

// Simulate form submission without spares selected
$_POST = [
    'descriptive_text' => 'Test Update',
    'equipment' => $equipment_id,
    'wo_status' => 'In Progress',
    'wo_id' => $wo_id
];

echo "[→] Simulated POST data (NO SPARES):\n";
foreach ($_POST as $k => $v) {
    echo "    POST['$k'] = '$v'\n";
}

// Extract spares like work_order.php does
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

echo "[→] Extracted selectedSpares: " . json_encode($selectedSpares) . "\n";
echo "    empty(\$selectedSpares) = " . (empty($selectedSpares) ? "TRUE" : "FALSE") . "\n\n";

// Check what would happen with the logic
$wo_status = $_POST['wo_status'];
$shouldDeleteSpares = !empty($selectedSpares) || $wo_status === 'Completed';
echo "=== DECISION POINT ===\n";
echo "shouldDeleteSpares = !empty(\$selectedSpares) || \$wo_status === 'Completed'\n";
echo "                   = " . (!empty($selectedSpares) ? "true" : "false") . " || " . ($wo_status === 'Completed' ? "true" : "false") . "\n";
echo "                   = " . ($shouldDeleteSpares ? "TRUE" : "FALSE") . "\n\n";

if (!$shouldDeleteSpares) {
    echo "[✓] GOOD: Spares would NOT be deleted\n";
} else {
    echo "[!] PROBLEM: Spares WOULD be deleted\n";
    
    // Now check if they would be re-inserted
    echo "\n=== RE-INSERTION CHECK ===\n";
    
    // Get current used spares
    $usedRes = $connection->query("
        SELECT spare_id, quantity_used 
        FROM work_order_spares 
        WHERE wo_id=$wo_id AND tenant_id=$tenant_id
    ");
    $usedSpares = [];
    while ($row = $usedRes->fetch(PDO::FETCH_ASSOC)) {
        $usedSpares[(int)$row['spare_id']] = (int)$row['quantity_used'];
    }
    
    echo "Current usedSpares in DB: " . json_encode($usedSpares) . "\n";
    echo "empty(\$usedSpares) = " . (empty($usedSpares) ? "TRUE" : "FALSE") . "\n";
    
    echo "\nLogic checks:\n";
    echo "  if (!empty(\$selectedSpares)) → " . (!empty($selectedSpares) ? "TRUE" : "FALSE") . "\n";
    echo "    → Would re-insert " . count($selectedSpares) . " spares\n";
    echo "  elseif (isset(\$usedSpares) && !empty(\$usedSpares)) → " . (isset($usedSpares) && !empty($usedSpares) ? "TRUE" : "FALSE") . "\n";
    echo "    → Would re-insert " . count($usedSpares) . " spares (PRESERVATION)\n";
    
    if (empty($selectedSpares) && (empty($usedSpares) || !isset($usedSpares))) {
        echo "\n[✗] PROBLEM FOUND: Both selectedSpares and usedSpares are empty!\n";
        echo "    Spares would be DELETED with no re-insertion!\n";
    } elseif (empty($selectedSpares) && !empty($usedSpares)) {
        echo "\n[✓] OK: Preservation logic should trigger\n";
        echo "    Would re-insert " . count($usedSpares) . " preserved spares\n";
    }
}

echo "\n=== THEORY 3: Tenant ID Mismatch ===\n";

// Check for tenant_id mismatches
$mismatch = $connection->query("
    SELECT COUNT(*) as cnt
    FROM work_order_spares wos
    WHERE wos.wo_id = $wo_id AND wos.tenant_id != $tenant_id
")->fetch(PDO::FETCH_ASSOC);

if ($mismatch['cnt'] > 0) {
    echo "[!] WARNING: Found " . $mismatch['cnt'] . " spare records with WRONG tenant_id!\n";
} else {
    echo "[✓] No tenant_id mismatches\n";
}

// Check equipment_spares tenant_id
$eq_tenant_check = $connection->query("
    SELECT COUNT(*) as cnt
    FROM equipment_spares es
    WHERE es.equipment_id = $equipment_id AND es.tenant_id != $tenant_id
")->fetch(PDO::FETCH_ASSOC);

if ($eq_tenant_check['cnt'] > 0) {
    echo "[!] WARNING: Found " . $eq_tenant_check['cnt'] . " equipment spares with WRONG tenant_id!\n";
} else {
    echo "[✓] Equipment spares all have correct tenant_id\n";
}

echo "\n";
?>
