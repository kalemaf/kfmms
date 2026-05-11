<?php
require_once 'config.inc.php';
require_once 'common.inc.php';

echo "Debugging Audit Logs Display Issue...\n\n";

$audit_logs = [];
if ($connection) {
    // Try different possible audit log tables
    $tables_to_check = ['audit_logs', 'security_audit_log', 'compliance_audit_log'];
    $found_table = false;
    
    foreach ($tables_to_check as $table) {
        echo "Checking table: $table\n";
        
        try {
            if ($db_type === 'sqlite') {
                // SQLite: Check if table exists
                $check = $connection->query("SELECT name FROM sqlite_master WHERE type='table' AND name='$table'");
                if ($check) {
                    $exists = $check->fetch(PDO::FETCH_ASSOC) !== false;
                    echo "  Table exists: " . ($exists ? "YES" : "NO") . "\n";
                    
                    if ($exists) {
                        // Get count
                        $count_result = $connection->query("SELECT COUNT(*) as cnt FROM \"$table\"");
                        if ($count_result) {
                            $count_row = $count_result->fetch(PDO::FETCH_ASSOC);
                            $count = $count_row['cnt'] ?? 0;
                            echo "  Records in table: $count\n";
                            
                            if ($count > 0) {
                                // Get data
                                $result = $connection->query("SELECT * FROM \"$table\" ORDER BY log_id DESC LIMIT 50");
                                if ($result) {
                                    $data = [];
                                    while ($row = $result->fetch(PDO::FETCH_ASSOC)) {
                                        $data[] = $row;
                                    }
                                    $audit_logs = array_merge($audit_logs, $data);
                                    $found_table = true;
                                    echo "  Successfully retrieved " . count($data) . " records\n";
                                }
                            }
                        }
                    }
                }
            } else {
                // MySQL: Use SHOW TABLES
                $check = $connection->query("SHOW TABLES LIKE '$table'");
                if ($check && $check->rowCount() > 0) {
                    echo "  Table exists: YES\n";
                    
                    $result = $connection->query("SELECT * FROM `$table` ORDER BY log_id DESC LIMIT 50");
                    if ($result) {
                        $data = [];
                        while ($row = $result->fetch_assoc()) {
                            $data[] = $row;
                        }
                        $audit_logs = array_merge($audit_logs, $data);
                        $found_table = true;
                        echo "  Successfully retrieved " . count($data) . " records\n";
                    }
                }
            }
        } catch (Exception $e) {
            echo "  Error: " . $e->getMessage() . "\n";
        }
        
        echo "\n";
    }
}

echo "SUMMARY:\n";
echo "Total audit logs found: " . count($audit_logs) . "\n";
echo "Found table: " . ($found_table ? "YES" : "NO") . "\n\n";

if (count($audit_logs) > 0) {
    echo "Sample entries:\n";
    foreach (array_slice($audit_logs, 0, 3) as $log) {
        echo "  - " . json_encode($log) . "\n";
    }
}
?>
