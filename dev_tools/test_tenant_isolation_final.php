<?php
require_once 'config.inc.php';
require_once 'common.inc.php';

echo "=== TENANT ISOLATION VERIFICATION TEST ===\n\n";

// Test 1: Admin user (tenant_id=1)
echo "TEST 1: Admin User (tenant_id=1)\n";
$_SESSION['tenant_id'] = 1;
$work_orders = $connection->query(apply_tenant_filter("SELECT COUNT(*) as cnt FROM work_orders"))->fetch(PDO::FETCH_ASSOC);
$parts = $connection->query(apply_tenant_filter("SELECT COUNT(*) as cnt FROM parts_master"))->fetch(PDO::FETCH_ASSOC);
echo "Work Orders: " . $work_orders['cnt'] . "\n";
echo "Parts: " . $parts['cnt'] . "\n\n";

// Test 2: Jimmy user (tenant_id=14)
echo "TEST 2: Jimmy User (tenant_id=14) - NEW COMPANY\n";
$_SESSION['tenant_id'] = 14;
$work_orders = $connection->query(apply_tenant_filter("SELECT COUNT(*) as cnt FROM work_orders"))->fetch(PDO::FETCH_ASSOC);
$parts = $connection->query(apply_tenant_filter("SELECT COUNT(*) as cnt FROM parts_master"))->fetch(PDO::FETCH_ASSOC);
echo "Work Orders: " . $work_orders['cnt'] . " (Expected: 0)\n";
echo "Parts: " . $parts['cnt'] . " (Expected: 0)\n\n";

if ($work_orders['cnt'] == 0 && $parts['cnt'] == 0) {
    echo "✓ PASS: No data leakage - Tenant isolation working!\n";
} else {
    echo "✗ FAIL: Data leakage detected!\n";
}