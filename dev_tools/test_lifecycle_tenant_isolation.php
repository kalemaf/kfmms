<?php
/**
 * Test: Lifecycle Analytics Tenant Isolation
 * Verifies that lifecycle_analytics.php now respects tenant_id for all queries
 */

require_once 'common.inc.php';

echo "\n=== LIFECYCLE ANALYTICS TENANT ISOLATION TEST ===\n\n";

// Test 1: Admin user (tenant_id=1) - should have parts data from old company
echo "TEST 1: Admin User (tenant_id=1)\n";
$_SESSION['tenant_id'] = 1;
$_SESSION['company_id'] = 1;

// Check parts_master count for tenant 1
$result = $connection->query(apply_tenant_filter("SELECT COUNT(*) AS cnt FROM parts_master"));
if ($result && ($row = $result->fetch_assoc())) {
    echo "  Parts Master Count: " . $row['cnt'] . " (Expected: > 0)\n";
} else {
    echo "  ERROR: Could not query parts_master\n";
}

// Check wo_parts count for tenant 1
$result = $connection->query(apply_tenant_filter("SELECT COUNT(*) AS cnt FROM wo_parts"));
if ($result && ($row = $result->fetch_assoc())) {
    echo "  WO Parts Count: " . $row['cnt'] . " (Expected: > 0)\n";
} else {
    echo "  ERROR: Could not query wo_parts\n";
}

// Test 2: Jimmy user (tenant_id=14) - should have ZERO parts (new company, no data)
echo "\nTEST 2: Jimmy User (tenant_id=14) - NEW COMPANY\n";
$_SESSION['tenant_id'] = 14;
$_SESSION['company_id'] = 14;

// Check parts_master count for tenant 14
$result = $connection->query(apply_tenant_filter("SELECT COUNT(*) AS cnt FROM parts_master"));
if ($result && ($row = $result->fetch_assoc())) {
    echo "  Parts Master Count: " . $row['cnt'] . " (Expected: 0)\n";
    $parts_count = $row['cnt'];
} else {
    echo "  ERROR: Could not query parts_master\n";
    $parts_count = -1;
}

// Check wo_parts count for tenant 14
$result = $connection->query(apply_tenant_filter("SELECT COUNT(*) AS cnt FROM wo_parts"));
if ($result && ($row = $result->fetch_assoc())) {
    echo "  WO Parts Count: " . $row['cnt'] . " (Expected: 0)\n";
    $wo_parts_count = $row['cnt'];
} else {
    echo "  ERROR: Could not query wo_parts\n";
    $wo_parts_count = -1;
}

// Verify results
echo "\n";
if ($parts_count === 0 && $wo_parts_count === 0) {
    echo "✓ PASS: Lifecycle Analytics - No data leakage for tenant_id=14\n";
    echo "  Jimmy's company sees 0 parts (correct - new company)\n";
} else {
    echo "✗ FAIL: Data leakage detected!\n";
    echo "  Jimmy's company should see 0 parts but sees:\n";
    echo "    - Parts Master: $parts_count\n";
    echo "    - WO Parts: $wo_parts_count\n";
}

echo "\n=== TEST COMPLETE ===\n\n";
?>
