<?php
// Test tenant filtering for work_order.php query
ob_start();
include 'config.inc.php';
ob_end_clean();

echo "<h1>Testing Work Order Tenant Filtering</h1>";

// Simulate kalemat user (company_id = 11, tenant_id = 11)
$_SESSION['tenant_id'] = 11;
$_SESSION['user_id'] = 57;
$_SESSION['company_id'] = 11;

echo "<h2>Simulating: kalemat (tenant_id = 11)</h2>";

// Test query from work_order.php
$db_type = 'sqlite';
$cast_type = ($db_type === 'sqlite') ? 'TEXT' : 'CHAR';
$query = "SELECT wo.wo_id, wo.descriptive_text, wo.wo_status, wo.priority, wo.requestor, wo.submit_date, wo.equipment, wo.mechanic_id, wo.est_hours,
          COALESCE(e.description, wo.equipment) AS equipment_name,
          COALESCE(u.username, '') AS technician_name,
          wo.audit_item, wo.sla_due_date, wo.down_time_hours, wo.response_time, wo.resolution_time
          FROM work_orders wo
          LEFT JOIN equipment e ON wo.equipment = CAST(e.id AS {$cast_type})
          LEFT JOIN users u ON wo.mechanic_id = u.user_id
          ORDER BY wo.submit_date DESC LIMIT 200";

// Apply tenant filter
$filtered_query = apply_tenant_filter($query);
echo "<h3>Original Query:</h3>";
echo "<pre>" . htmlspecialchars($query) . "</pre>";
echo "<h3>Filtered Query:</h3>";
echo "<pre>" . htmlspecialchars($filtered_query) . "</pre>";

// Execute filtered query
$result = $connection->query($filtered_query);
$work_orders = $result->fetchAll(PDO::FETCH_ASSOC);

echo "<h3>Results for kalemat (tenant_id=11): " . count($work_orders) . " work orders</h3>";
if (count($work_orders) == 0) {
    echo "<p style='color:green'><strong>✓ SUCCESS: No work orders visible - tenant isolation working!</strong></p>";
} else {
    echo "<p style='color:red'><strong>✗ FAILED: Work orders visible!</strong></p>";
    print_r($work_orders);
}

// Now test for company 1 (hw)
$_SESSION['tenant_id'] = 1;
$_SESSION['user_id'] = 1;
$_SESSION['company_id'] = 1;

echo "<h2>Simulating: admin (tenant_id = 1)</h2>";
$filtered_query = apply_tenant_filter($query);
$result = $connection->query($filtered_query);
$work_orders = $result->fetchAll(PDO::FETCH_ASSOC);

echo "<p>Work orders visible: " . count($work_orders) . "</p>";
foreach ($work_orders as $wo) {
    echo "- WO #{$wo['wo_id']}: {$wo['descriptive_text']} ({$wo['wo_status']})<br>";
}