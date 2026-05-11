<?php
/**
 * Test: Form Load in Edit Mode
 * 
 * Simulates what happens when edit form loads:
 * 1. Check if equipment value is pre-selected
 * 2. Check if JavaScript would call loadSpares
 * 3. Verify API returns spares correctly
 */

require_once 'config.inc.php';
require_once 'common.inc.php';

$_SESSION['tenant_id'] = 1;
$tenant_id = 1;

echo "\n╔════════════════════════════════════════════════════════════════════════╗\n";
echo "║    TEST: Edit Form Load - JavaScript Trigger Check                   ║\n";
echo "╚════════════════════════════════════════════════════════════════════════╝\n\n";

// Find WO with spares
$result = $connection->query("
    SELECT wo.wo_id, wo.descriptive_text, wo.equipment
    FROM work_orders wo
    WHERE wo.tenant_id = $tenant_id 
      AND wo.wo_id IN (SELECT DISTINCT wo_id FROM work_order_spares WHERE tenant_id = $tenant_id)
    ORDER BY wo.wo_id DESC LIMIT 1
");

if (!$result || !($wo_row = $result->fetch(PDO::FETCH_ASSOC))) {
    echo "[!] No WO with spares found\n";
    exit(0);
}

$editing = $wo_row;
$wo_id = $editing['wo_id'];
$equipment_id = $editing['equipment'];

echo "[→] WO #{$wo_id}: {$editing['descriptive_text']}\n";
echo "[→] Equipment ID: {$equipment_id}\n\n";

// Simulate what would be in $vals during edit mode
echo "=== CHECKING FORM WOULD PRE-SELECT EQUIPMENT ===\n";
echo "In edit mode:\n";
echo "  isset(\$vals['equipment']) = " . (isset($editing['equipment']) ? "true" : "false") . "\n";
echo "  \$vals['equipment'] = {$editing['equipment']}\n\n";

// Get all equipment for the dropdown
$equipmentList = [];
$equipRes = $connection->query("SELECT id, description FROM equipment ORDER BY description");
while ($e = $equipRes->fetch(PDO::FETCH_ASSOC)) {
    $equipmentList[] = $e;
}

echo "=== SIMULATED HTML FORM OUTPUT ===\n";
echo "<select name=\"equipment\" required>\n";
echo "    <option value=\"\">-- Select Equipment --</option>\n";
foreach ($equipmentList as $e) {
    $selected = (isset($editing['equipment']) && $editing['equipment'] == $e['id']) ? ' selected' : '';
    echo "    <option value=\"" . (int)$e['id'] . "\"{$selected}>" . htmlspecialchars($e['description']) . "</option>\n";
}
echo "</select>\n\n";

// Check JavaScript variables
echo "=== JAVASCRIPT VARIABLES (from PHP json_encode) ===\n";
$usedRes = $connection->query("SELECT spare_id, quantity_used FROM work_order_spares WHERE wo_id=$wo_id AND tenant_id=$tenant_id");
$usedSpares = [];
while ($row = $usedRes->fetch(PDO::FETCH_ASSOC)) {
    $usedSpares[(int)$row['spare_id']] = (int)$row['quantity_used'];
}
echo "const sparesUsed = " . json_encode($usedSpares) . ";\n";

// Simulate JavaScript execution
echo "\n=== SIMULATED JAVASCRIPT EXECUTION ===\n";
echo "On DOMContentLoaded:\n";
echo "  const equipSelect = document.querySelector('select[name=\"equipment\"]');\n";
echo "  equipSelect.value = \"$equipment_id\";\n";
echo "  if (equipSelect.value) loadSpares(equipSelect.value);\n";
echo "  // Would call: loadSpares(" . $equipment_id . ")\n\n";

// Test API call
echo "=== API CALL: api_spares.php?equipment_id={$equipment_id} ===\n";
$spares_query = "SELECT id, part_name, part_number, quantity, 'spare' as type FROM equipment_spares WHERE equipment_id={$equipment_id}";
$spares_query = apply_tenant_filter($spares_query);
echo "Query: $spares_query\n";

$items = [];
$q = $connection->query($spares_query);
if (!$q) {
    echo "[✗] Query failed: " . $connection->errorInfo()[2] . "\n";
} else {
    while ($row = $q->fetch(PDO::FETCH_ASSOC)) {
        $items[] = $row;
    }
    echo "[✓] Returned " . count($items) . " items\n";
    
    if (count($items) > 0) {
        echo "    Spares would be rendered:\n";
        foreach ($items as $s) {
            $usedQty = $usedSpares[$s['id']] ?? 0;
            echo "    <input type=\"number\" name=\"spares_" . $s['id'] . "\" value=\"{$usedQty}\" ...>\n";
        }
    } else {
        echo "    [!] No spares returned - form would show 'No spares available'\n";
    }
}

// Now check if there's an issue fetching fresh WO data
echo "\n=== CHECKING: Does refetching WO in edit mode return the same equipment? ===\n";
$edit_check = $connection->query("SELECT * FROM work_orders WHERE wo_id=$wo_id AND tenant_id=$tenant_id LIMIT 1");
if (!$edit_check || !($row = $edit_check->fetch(PDO::FETCH_ASSOC))) {
    echo "[✗] Could not fetch WO for edit\n";
} else {
    echo "[✓] WO fetched\n";
    echo "    equipment = {$row['equipment']}\n";
    echo "    equipment_id match = " . ($row['equipment'] == $equipment_id ? "YES" : "NO") . "\n";
}

echo "\n";
?>
