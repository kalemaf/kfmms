<?php
// Test complex query from work_order.php
include 'config.inc.php';

echo "Testing work_order.php query...\n";

// Set session for kalemat (tenant_id = 11)
$_SESSION['tenant_id'] = 11;
$_SESSION['user_id'] = 57;

// Complex query from work_order.php
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

echo "Original query:\n$query\n\n";

// Apply tenant filter
$filtered = apply_tenant_filter($query);
echo "Filtered query:\n$filtered\n\n";

// Execute
$res = $connection->query($filtered);
$rows = $res->fetchAll(PDO::FETCH_ASSOC);
echo "Rows for kalemat (tenant_id=11): " . count($rows) . "\n";

if (count($rows) == 0) {
    echo "✓ SUCCESS: No work orders visible for new company!\n";
} else {
    echo "✗ FAILED: Work orders visible\n";
    print_r($rows);
}

// Now test for company 1
$_SESSION['tenant_id'] = 1;
$_SESSION['user_id'] = 1;

$filtered = apply_tenant_filter($query);
$res = $connection->query($filtered);
$rows = $res->fetchAll(PDO::FETCH_ASSOC);
echo "\nRows for company 1 (tenant_id=1): " . count($rows) . "\n";

foreach ($rows as $wo) {
    echo "- WO #{$wo['wo_id']}: {$wo['descriptive_text']} ({$wo['wo_status']})\n";
}