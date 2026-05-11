<?php
/**
 * Debug: Check what lifecycle_analytics.php queries return for each tenant
 * Trace exact query results to identify data leakage source
 */

require_once 'config.inc.php';
require_once 'common.inc.php';

echo "\n╔════════════════════════════════════════════════════════════════╗\n";
echo "║         DEBUG: Lifecycle Analytics Query Results              ║\n";
echo "╚════════════════════════════════════════════════════════════════╝\n\n";

$from = date('Y-m-d', strtotime('-30 days'));
$to = date('Y-m-d');

function debug_tenant_queries($tenant_id, $company_name) {
    global $connection, $from, $to;
    
    $_SESSION['tenant_id'] = $tenant_id;
    $_SESSION['company_id'] = $tenant_id;
    
    echo "═══ $company_name (tenant_id=$tenant_id) ═══\n\n";
    
    // 1. Raw parts_master table
    echo "1. Parts Master (all columns):\n";
    $res = $connection->query(apply_tenant_filter("SELECT id, part_code, part_name, tenant_id FROM parts_master LIMIT 10"));
    if ($res) {
        $count = 0;
        while ($row = $res->fetch_assoc()) {
            echo "   - ID=" . $row['id'] . ", Code=" . $row['part_code'] . ", Name=" . $row['part_name'] . ", tenant_id=" . $row['tenant_id'] . "\n";
            $count++;
        }
        if ($count == 0) echo "   (empty - no parts)\n";
    }
    
    // 2. Work orders with parts
    echo "\n2. Work Orders (with wo_parts):\n";
    $res = $connection->query(apply_tenant_filter(
        "SELECT DISTINCT wo.wo_id, wo.submit_date, wo.equipment, wo.tenant_id, COUNT(wp.id) as part_count " .
        "FROM work_orders wo " .
        "LEFT JOIN wo_parts wp ON wo.wo_id = wp.wo_id " .
        "GROUP BY wo.wo_id " .
        "LIMIT 10"
    ));
    if ($res) {
        $count = 0;
        while ($row = $res->fetch_assoc()) {
            echo "   - WO_ID=" . $row['wo_id'] . ", Date=" . $row['submit_date'] . ", Parts=" . $row['part_count'] . ", tenant_id=" . $row['tenant_id'] . "\n";
            $count++;
        }
        if ($count == 0) echo "   (empty - no work orders)\n";
    }
    
    // 3. Fast-moving items
    echo "\n3. Fast-Moving Items (with consumption > 0):\n";
    $res = $connection->query(apply_tenant_filter(
        "SELECT pm.id, pm.part_code, pm.part_name, SUM(wp.quantity_required) as used_qty, pm.tenant_id " .
        "FROM parts_master pm " .
        "LEFT JOIN wo_parts wp ON wp.part_id = pm.id " .
        "LEFT JOIN work_orders wo ON wp.wo_id = wo.wo_id " .
        "WHERE pm.is_active = 1 AND wp.quantity_required > 0 " .
        "GROUP BY pm.id " .
        "LIMIT 10"
    ));
    if ($res) {
        $count = 0;
        while ($row = $res->fetch_assoc()) {
            echo "   - ID=" . $row['id'] . ", Code=" . $row['part_code'] . ", Name=" . $row['part_name'] . ", Used=" . $row['used_qty'] . ", tenant_id=" . $row['tenant_id'] . "\n";
            $count++;
        }
        if ($count == 0) echo "   (empty - no fast-moving items)\n";
    }
    
    // 4. Check for parts with NO tenant_id (orphaned)
    echo "\n4. Checking for parts with missing tenant_id:\n";
    $res = $connection->query(
        "SELECT COUNT(*) as cnt FROM parts_master WHERE tenant_id IS NULL OR tenant_id = 0"
    );
    if ($res && ($row = $res->fetch_assoc())) {
        echo "   Orphaned parts (NULL or 0 tenant_id): " . $row['cnt'] . "\n";
    }
    
    // 5. Check work_orders table for orphaned data
    echo "\n5. Checking for work_orders with missing tenant_id:\n";
    $res = $connection->query(
        "SELECT COUNT(*) as cnt FROM work_orders WHERE tenant_id IS NULL OR tenant_id = 0"
    );
    if ($res && ($row = $res->fetch_assoc())) {
        echo "   Orphaned work_orders (NULL or 0 tenant_id): " . $row['cnt'] . "\n";
    }
    
    echo "\n";
}

// Run debug for both tenants
debug_tenant_queries(1, "Tenant 1 (Admin)");
debug_tenant_queries(14, "Tenant 14 (Jimmy)");

?>
