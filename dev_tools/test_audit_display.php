<?php
require_once 'config.inc.php';
require_once 'common.inc.php';

echo "Testing Audit Logs Display Logic...\n\n";

$user_role = 'supervisor'; // Simulate supervisor role
echo "User role: $user_role\n";
echo "Has access: " . (in_array($user_role, ['admin', 'manager', 'maintenance manager', 'supervisor', 'developer']) ? "YES" : "NO") . "\n\n";

$audit_logs = [];
if ($connection) {
    try {
        $tables_to_check = ['audit_logs', 'security_audit_log', 'compliance_audit_log'];
        $found = false;
        
        foreach ($tables_to_check as $table) {
            $table_exists = false;
            
            if ($db_type === 'sqlite') {
                $check = $connection->query("SELECT name FROM sqlite_master WHERE type='table' AND name='$table'");
                $table_exists = $check && $check->fetch(PDO::FETCH_ASSOC) !== false;
            } else {
                $check = $connection->query("SHOW TABLES LIKE '$table'");
                $table_exists = $check && $check->rowCount() > 0;
            }
            
            if ($table_exists) {
                echo "Found table: $table\n";
                $result = $connection->query("SELECT * FROM \"$table\" ORDER BY log_id DESC LIMIT 50");
                if ($result) {
                    while ($row = $result->fetch(PDO::FETCH_ASSOC)) {
                        $audit_logs[] = $row;
                    }
                    $found = true;
                    break;
                }
            }
        }
    } catch (Exception $e) {
        echo "Error: " . $e->getMessage() . "\n";
    }
}

echo "Total logs retrieved: " . count($audit_logs) . "\n\n";

if (empty($audit_logs)) {
    echo "❌ Display message: No Audit Logs Found\n";
} else {
    echo "✅ Display message: Table with " . count($audit_logs) . " entries\n\n";
    echo "Sample table rows:\n";
    foreach (array_slice($audit_logs, 0, 3) as $log) {
        $timestamp = $log['timestamp'] ?? $log['TEXT'] ?? $log['created_at'] ?? 'N/A';
        $username = $log['username'] ?? $log['user_id'] ?? 'System';
        $action = $log['action'] ?? $log['event_type'] ?? 'N/A';
        $details = substr($log['details'] ?? $log['description'] ?? '', 0, 100);
        $ip = $log['ip_address'] ?? 'N/A';
        
        echo "  | " . str_pad($timestamp, 20) . " | " . str_pad($username, 15) . " | " . str_pad($action, 20) . " | " . str_pad(substr($details, 0, 30), 30) . " | $ip\n";
    }
}
?>
