<?php
// Debug: Check tenant filtering step by step
include 'config.inc.php';

echo "Step 1: Database connected\n";

// Set session for kalemat (tenant_id = 11)
$_SESSION['tenant_id'] = 11;
$_SESSION['user_id'] = 57;

echo "Step 2: Session set - tenant_id = " . $_SESSION['tenant_id'] . "\n";

// Test tenant_id() function
$tid = 0;
if (isset($_SESSION['tenant_id']) && $_SESSION['tenant_id'] > 0) {
    $tid = (int)$_SESSION['tenant_id'];
    echo "Step 3: tenant_id from session: $tid\n";
} else {
    echo "Step 3: tenant_id NOT SET in session\n";
}

// Test apply_tenant_filter manually
$query = "SELECT * FROM work_orders";
echo "Step 4: Original query: $query\n";

$tenant_id = $tid;
$filtered = preg_replace('/^SELECT\s+(.*?)\s+FROM\s+work_orders/i', 
    'SELECT $1 FROM work_orders WHERE tenant_id = ' . $tenant_id, 
    $query);

if ($filtered === $query) {
    // Manual filter
    $filtered = $query . " WHERE tenant_id = " . $tenant_id;
}

echo "Step 5: Filtered query: $filtered\n";

// Execute
try {
    $res = $connection->query($filtered);
    $rows = $res->fetchAll(PDO::FETCH_ASSOC);
    echo "Step 6: Rows returned: " . count($rows) . "\n";
    
    if (count($rows) == 0) {
        echo "SUCCESS: Tenant isolation working!\n";
    } else {
        echo "FAILED: Data visible\n";
        print_r($rows);
    }
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
}