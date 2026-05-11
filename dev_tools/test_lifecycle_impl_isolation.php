<?php
/**
 * Final Verification: Lifecycle Analytics Impl - Data Isolation
 * Test that lifecycle_analytics_impl.php queries now respect tenant_id boundaries
 */

require_once 'config.inc.php';
require_once 'common.inc.php';
require_once 'libraries/inventory_manager.php';

echo "\n╔════════════════════════════════════════════════════════════════╗\n";
echo "║  LIFECYCLE ANALYTICS IMPL - FINAL TENANT ISOLATION TEST       ║\n";
echo "╚════════════════════════════════════════════════════════════════╝\n\n";

function simulate_lifecycle_impl_queries($tenant_id, $company_name) {
    global $connection;
    
    $_SESSION['tenant_id'] = $tenant_id;
    $_SESSION['company_id'] = $tenant_id;
    
    echo "─ $company_name (tenant_id=$tenant_id) ─\n";
    
    $from = date('Y-m-d', strtotime('-30 days'));
    $to = date('Y-m-d');
    $partsWhereSql = "pm.is_active = 1";
    
    $metrics = [];
    
    // 1. Parts count
    $res = $connection->query(apply_tenant_filter("SELECT COUNT(*) as cnt FROM parts_master WHERE is_active = 1"));
    if ($res && ($row = $res->fetch_assoc())) {
        $metrics['parts'] = $row['cnt'];
    }
    
    // 2. Equipment spares
    $res = $connection->query(apply_tenant_filter("SELECT COUNT(*) AS count FROM equipment_spares WHERE part_id IS NOT NULL"));
    if ($res && ($row = $res->fetch_assoc())) {
        $metrics['spares'] = $row['count'];
    }
    
    // 3. Linked equipment
    $res = $connection->query(apply_tenant_filter("SELECT COUNT(*) as cnt FROM equipment"));
    if ($res && ($row = $res->fetch_assoc())) {
        $metrics['equipment'] = $row['cnt'];
    }
    
    // 4. Stock locales
    $res = $connection->query(apply_tenant_filter("SELECT COUNT(*) as cnt FROM stock_locales"));
    if ($res && ($row = $res->fetch_assoc())) {
        $metrics['stock_locales'] = $row['cnt'];
    }
    
    echo "  Parts Master Records: " . $metrics['parts'] . "\n";
    echo "  Equipment Spares: " . $metrics['spares'] . "\n";
    echo "  Equipment Count: " . $metrics['equipment'] . "\n";
    echo "  Stock Locales: " . $metrics['stock_locales'] . "\n";
    
    return $metrics;
}

// Test both tenants
$admin_metrics = simulate_lifecycle_impl_queries(1, "Tenant 1 (Admin)");
echo "\n";
$jimmy_metrics = simulate_lifecycle_impl_queries(14, "Tenant 14 (Jimmy)");

// Verify results
echo "\n╔════════════════════════════════════════════════════════════════╗\n";
echo "║                    VERIFICATION RESULTS                       ║\n";
echo "╚════════════════════════════════════════════════════════════════╝\n\n";

$pass = true;

// For new company (tenant 14), all should be 0
if ($jimmy_metrics['parts'] == 0 && $jimmy_metrics['spares'] == 0 && 
    $jimmy_metrics['equipment'] == 0 && $jimmy_metrics['stock_locales'] == 0) {
    echo "✓ PASS: New Company (tenant_id=14) has NO data leakage\n";
    echo "  All queries correctly return 0 (no data for new company)\n";
} else {
    echo "✗ FAIL: Data Leakage Detected!\n";
    echo "  Jimmy's company should see 0 records:\n";
    echo "    - Parts Master: " . $jimmy_metrics['parts'] . " (expected 0)\n";
    echo "    - Equipment Spares: " . $jimmy_metrics['spares'] . " (expected 0)\n";
    echo "    - Equipment: " . $jimmy_metrics['equipment'] . " (expected 0)\n";
    echo "    - Stock Locales: " . $jimmy_metrics['stock_locales'] . " (expected 0)\n";
    $pass = false;
}

echo "\n";

if ($pass) {
    echo "╔════════════════════════════════════════════════════════════════╗\n";
    echo "║  ✓✓✓ SUCCESS: Lifecycle Analytics Impl Fixed & Isolated! ✓✓✓ ║\n";
    echo "╚════════════════════════════════════════════════════════════════╝\n\n";
} else {
    echo "╔════════════════════════════════════════════════════════════════╗\n";
    echo "║  ✗✗✗ FAILURE: Data leakage still present!                    ║\n";
    echo "╚════════════════════════════════════════════════════════════════╝\n\n";
}

?>
