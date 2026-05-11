<?php
require_once __DIR__ . '/config.inc.php';

$c = $GLOBALS['c'];

// Check parts data
$stmt = $c->query('SELECT COUNT(*) as total FROM parts_master');
$all = $stmt->fetch(PDO::FETCH_ASSOC);

$stmt = $c->query('SELECT COUNT(*) as active FROM parts_master WHERE is_active = 1');
$active = $stmt->fetch(PDO::FETCH_ASSOC);

$stmt = $c->query('SELECT COUNT(*) as t1 FROM parts_master WHERE tenant_id = 1');
$t1 = $stmt->fetch(PDO::FETCH_ASSOC);

echo "Parts Database Status:\n";
echo "  Total parts: " . $all['total'] . "\n";
echo "  Active parts: " . $active['active'] . "\n";
echo "  Parts for tenant_id=1: " . $t1['t1'] . "\n\n";

if ($all['total'] > 0) {
    echo "Sample parts:\n";
    $stmt = $c->query('SELECT id, part_code, part_name, is_active, tenant_id FROM parts_master LIMIT 5');
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        echo "  - {$row['part_code']}: {$row['part_name']} (active={$row['is_active']}, tenant={$row['tenant_id']})\n";
    }
}
?>
