<?php
echo "Starting...\n";
include 'config.inc.php';
include 'common.inc.php';
echo "Included config and common\n";
$_SESSION['tenant_id'] = 11;
echo "Session set\n";

// Test apply_tenant_filter with complex query
$query = "SELECT wo.wo_id, wo.descriptive_text FROM work_orders wo ORDER BY wo.submit_date DESC LIMIT 200";
echo "Query: $query\n";

try {
    echo "Calling apply_tenant_filter...\n";
    $filtered = apply_tenant_filter($query);
    echo "After apply_tenant_filter\n";
    echo "Filtered: $filtered\n";

    echo "Executing query...\n";
    $res = $connection->query($filtered);
    $rows = $res->fetchAll(PDO::FETCH_ASSOC);
    echo "Rows: " . count($rows) . "\n";

    if (count($rows) == 0) {
        echo "SUCCESS\n";
    }
} catch (Throwable $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
    echo "Trace: " . $e->getTraceAsString() . "\n";
}