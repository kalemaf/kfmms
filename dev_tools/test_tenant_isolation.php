<?php
/**
 * Comprehensive Tenant Isolation Test
 * Tests that new company user arianna sees only their own data
 */

require_once 'config.inc.php';
require_once 'common.inc.php';

echo "<h1>Tenant Isolation Test Report</h1>";
echo "<p>Testing multi-tenant data isolation for company 10 (jjoin)</p>";

// Simulate arianna login
$_SESSION['user_id'] = 56;
$_SESSION['username'] = 'arianna';
$_SESSION['email'] = 'arianna@gmail.com';
$_SESSION['tenant_id'] = 10;  // jjoin company
$_SESSION['company_id'] = 10;
$_SESSION['role'] = 'supervisor';

echo "<h2>Session Set For: arianna (Company ID: 10)</h2>";
echo "<pre>";
print_r([
    'user_id' => $_SESSION['user_id'],
    'username' => $_SESSION['username'],
    'tenant_id' => $_SESSION['tenant_id'],
    'company_id' => $_SESSION['company_id'],
    'role' => $_SESSION['role']
]);
echo "</pre>";

echo "<h2>Data Visibility Tests</h2>";

$tests = [
    'Work Orders' => "SELECT COUNT(*) as cnt FROM work_orders",
    'Equipment' => "SELECT COUNT(*) as cnt FROM equipment",
    'Inventory' => "SELECT COUNT(*) as cnt FROM inventory",
    'Purchase Orders' => "SELECT COUNT(*) as cnt FROM purchase_orders",
    'Purchase Requests' => "SELECT COUNT(*) as cnt FROM purchase_requests",
    'PM Schedules' => "SELECT COUNT(*) as cnt FROM pm_schedules",
    'Consumables' => "SELECT COUNT(*) as cnt FROM consumables",
    'Vendors' => "SELECT COUNT(*) as cnt FROM vendors",
    'Warehouses' => "SELECT COUNT(*) as cnt FROM warehouses",
    'Equipment Spares' => "SELECT COUNT(*) as cnt FROM equipment_spares"
];

echo "<table border='1' cellpadding='10' style='width:100%; margin-top:20px;'>";
echo "<tr><th>Data Type</th><th>Count (With Filtering)</th><th>Raw Count (Unfiltered)</th><th>Status</th></tr>";

foreach ($tests as $label => $query) {
    // With filtering (as arianna would see)
    $filtered_result = safe_query_row($query);
    $filtered_count = $filtered_result['cnt'] ?? 0;
    
    // Without filtering (raw data)
    $raw_count = $connection->query($query)->fetch_assoc()['cnt'] ?? 0;
    
    $status = ($filtered_count == 0) ? '✓ ISOLATED' : '✗ LEAKED DATA';
    $style = ($filtered_count == 0) ? 'color: green;' : 'color: red;';
    
    echo "<tr>";
    echo "<td><strong>$label</strong></td>";
    echo "<td style='$style'><strong>$filtered_count</strong></td>";
    echo "<td>$raw_count</td>";
    echo "<td style='$style'><strong>$status</strong></td>";
    echo "</tr>";
}

echo "</table>";

echo "<h2>Detailed Data Check</h2>";

// Check what company owns the work orders
$wo_by_company = $connection->query("SELECT tenant_id, COUNT(*) as cnt FROM work_orders GROUP BY tenant_id")->fetchAll(PDO::FETCH_ASSOC);
echo "<h3>Work Orders by Company:</h3>";
echo "<pre>";
print_r($wo_by_company);
echo "</pre>";

// Check what company owns the equipment
$eq_by_company = $connection->query("SELECT tenant_id, COUNT(*) as cnt FROM equipment GROUP BY tenant_id")->fetchAll(PDO::FETCH_ASSOC);
echo "<h3>Equipment by Company:</h3>";
echo "<pre>";
print_r($eq_by_company);
echo "</pre>";

// Check what company owns purchase orders
$po_by_company = $connection->query("SELECT tenant_id, COUNT(*) as cnt FROM purchase_orders GROUP BY tenant_id")->fetchAll(PDO::FETCH_ASSOC);
echo "<h3>Purchase Orders by Company:</h3>";
echo "<pre>";
print_r($po_by_company);
echo "</pre>";

echo "<h2>Summary</h2>";
$all_isolated = true;
foreach ($tests as $label => $query) {
    $result = safe_query_row($query);
    if (($result['cnt'] ?? 0) > 0) {
        $all_isolated = false;
        echo "<p style='color:red;'>⚠ $label is NOT isolated - arianna can see data</p>";
    }
}

if ($all_isolated) {
    echo "<p style='color:green;'><strong>✓ SUCCESS: All data is properly isolated for company 10 (jjoin)</strong></p>";
    echo "<p>Arianna can only see empty tables - no data inheritance from other companies!</p>";
} else {
    echo "<p style='color:red;'><strong>✗ ISSUE: Some data is still visible across tenants</strong></p>";
}