<?php
require_once __DIR__ . '/config.inc.php';

$c = $GLOBALS['c'];

echo "Creating sample parts for tenant_id=1...\n\n";

$parts_to_add = [
    ['code' => 'PART-001', 'name' => 'Standard Bolt M10', 'category' => 'Fasteners', 'cost' => 2.50],
    ['code' => 'PART-002', 'name' => 'Bearing 6208', 'category' => 'Bearings', 'cost' => 45.00],
    ['code' => 'PART-003', 'name' => 'Oil Seal', 'category' => 'Seals', 'cost' => 15.75],
    ['code' => 'PART-004', 'name' => 'Drive Belt', 'category' => 'Belts', 'cost' => 35.00],
    ['code' => 'PART-005', 'name' => 'Motor Coupling', 'category' => 'Couplings', 'cost' => 125.00],
];

$count = 0;
foreach ($parts_to_add as $part) {
    $code = $c->quote($part['code']);
    $name = $c->quote($part['name']);
    $category = $c->quote($part['category']);
    $cost = $part['cost'];
    
    // Check if part already exists
    $check = $c->query("SELECT id FROM parts_master WHERE part_code = $code AND tenant_id = 1");
    $exists = $check && $check->fetch();
    
    if (!$exists) {
        $query = "INSERT INTO parts_master (part_code, part_name, category, unit_cost, total_on_hand, reorder_point, is_active, tenant_id, created_at, updated_at)
                  VALUES ($code, $name, $category, $cost, 0, 0, 1, 1, datetime('now'), datetime('now'))";
        
        if ($c->query($query)) {
            echo "[✓] Created: {$part['code']} - {$part['name']}\n";
            $count++;
        } else {
            echo "[✗] Failed: {$part['code']}\n";
        }
    } else {
        echo "[~] Already exists: {$part['code']}\n";
    }
}

echo "\n$count new parts created for tenant_id=1\n\n";

// Verify
$stmt = $c->query('SELECT COUNT(*) as cnt FROM parts_master WHERE tenant_id = 1 AND is_active = 1');
$result = $stmt->fetch(PDO::FETCH_ASSOC);
echo "Total active parts for tenant_id=1: " . $result['cnt'] . "\n";

?>
