<?php
// Check WO #8 and work_order_requests structure

$db = new SQLite3('database/maintenix.db');

echo "=== WORK ORDER #8 DETAILS ===\n\n";

$result = $db->querySingle('SELECT * FROM work_orders WHERE wo_id = 8', SQLITE3_ASSOC);
if ($result) {
    echo "WO #8 Complete Record:\n";
    echo str_repeat("-", 80) . "\n";
    foreach ($result as $key => $value) {
        echo sprintf("%-20s: %s\n", $key, $value);
    }
}

// Check work_order_requests columns
echo "\n\n=== WORK_ORDER_REQUESTS TABLE SCHEMA ===\n";
echo str_repeat("-", 80) . "\n";
$result = $db->query('PRAGMA table_info(work_order_requests)');
if ($result) {
    $found_tenant_id = false;
    while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
        echo sprintf("%-15s | %-10s | Default: %s\n", $row['name'], $row['type'], $row['dflt_value'] ?? 'NULL');
        if ($row['name'] === 'tenant_id') {
            $found_tenant_id = true;
        }
    }
    echo str_repeat("-", 80) . "\n";
    if (!$found_tenant_id) {
        echo "\n⚠️  ALERT: tenant_id column MISSING from work_order_requests!\n";
    } else {
        echo "\n✓ tenant_id column EXISTS in work_order_requests\n";
    }
}

// Check work_order_requests data
echo "\n\n=== WORK_ORDER_REQUESTS DATA ===\n";
echo str_repeat("-", 80) . "\n";
$result = $db->query('SELECT wor_id, request_name, tenant_id FROM work_order_requests ORDER BY wor_id');
if ($result) {
    while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
        echo sprintf("WOR #%-3d | %-30s | Tenant: %s\n", $row['wor_id'], substr($row['request_name'], 0, 28), $row['tenant_id'] ?? 'NULL');
    }
}

// Check if there's a wo_request_date or similar column that might show when WO #8 was created
echo "\n\n=== CHECKING CREATION/MODIFICATION TIMESTAMPS ===\n";
echo str_repeat("-", 80) . "\n";
$result = $db->query('PRAGMA table_info(work_orders)');
$timestamp_cols = [];
if ($result) {
    while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
        if (strpos(strtolower($row['name']), 'date') !== false || 
            strpos(strtolower($row['name']), 'time') !== false ||
            strpos(strtolower($row['name']), 'created') !== false ||
            strpos(strtolower($row['name']), 'updated') !== false) {
            $timestamp_cols[] = $row['name'];
        }
    }
}

if ($timestamp_cols) {
    echo "Timestamp columns found: " . implode(', ', $timestamp_cols) . "\n";
    $cols_query = implode(', ', $timestamp_cols);
    $result = $db->querySingle("SELECT wo_id, $cols_query FROM work_orders WHERE wo_id = 8", SQLITE3_ASSOC);
    if ($result) {
        foreach ($result as $key => $value) {
            echo "  $key: $value\n";
        }
    }
}

$db->close();
?>
