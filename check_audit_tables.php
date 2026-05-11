<?php
require_once 'config.inc.php';
echo 'Checking Audit Log Tables...' . PHP_EOL . PHP_EOL;

$tables = ['audit_logs', 'security_audit_log', 'compliance_audit_log'];

foreach ($tables as $table) {
    try {
        if ($db_type === 'sqlite') {
            $check = $connection->query("SELECT name FROM sqlite_master WHERE type='table' AND name='$table'");
            $exists = $check && $check->fetch(PDO::FETCH_ASSOC) !== false;
        } else {
            $check = $connection->query("SHOW TABLES LIKE '$table'");
            $exists = $check && $check->rowCount() > 0;
        }
        
        if ($exists) {
            $count = $connection->query("SELECT COUNT(*) as cnt FROM \"$table\"")->fetch(PDO::FETCH_ASSOC)['cnt'];
            echo "✓ Table: $table\n";
            echo "  Records: $count\n";
            
            if ($db_type === 'sqlite') {
                $pragma = $connection->query("PRAGMA table_info($table)")->fetchAll(PDO::FETCH_ASSOC);
                echo "  Columns: " . count($pragma) . "\n";
                foreach ($pragma as $col) {
                    echo "    - " . $col['name'] . " (" . $col['type'] . ")\n";
                }
            }
            echo PHP_EOL;
        } else {
            echo "✗ Table: $table (NOT FOUND)\n\n";
        }
    } catch (Exception $e) {
        echo "✗ Error checking $table: " . $e->getMessage() . "\n\n";
    }
}
?>
