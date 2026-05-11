<?php
session_start();
require 'config.inc.php';
echo "Config loaded\n";

// Try to call each function one by one
@ensure_sla_policies_table($connection);
echo "1. ensure_sla_policies_table OK\n";

@ensure_work_order_sla_table($connection);
echo "2. ensure_work_order_sla_table OK\n";

@ensure_repeat_failures_table($connection);
echo "3. ensure_repeat_failures_table OK\n";

@ensure_technician_performance_table($connection);
echo "4. ensure_technician_performance_table OK\n";

@ensure_performance_history_table($connection);
echo "5. ensure_performance_history_table OK\n";

echo "Done!\n";
?>
