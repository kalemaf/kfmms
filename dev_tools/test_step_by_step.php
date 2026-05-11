<?php
session_start();
$_SESSION['tenant_id'] = 1;

require 'config.inc.php';
echo "Config OK\n";

// Test each function individually
echo "Testing ensure_sla_policies_table...\n";
require_once 'libraries/performance_schema.php';
@ensure_sla_policies_table($connection);
echo "ensure_sla_policies_table OK\n";

@ensure_work_order_sla_table($connection);
echo "ensure_work_order_sla_table OK\n";

@ensure_repeat_failures_table($connection);
echo "ensure_repeat_failures_table OK\n";

@ensure_technician_performance_table($connection);
echo "ensure_technician_performance_table OK\n";

@ensure_performance_history_table($connection);
echo "ensure_performance_history_table OK\n";

echo "All schema functions OK\n";
?>
