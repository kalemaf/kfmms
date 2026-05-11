<?php
require_once 'config.inc.php';

echo "Checking audit log table columns...\n\n";

if ($db_type === 'sqlite') {
    // Get security_audit_log columns
    $result = $connection->query("PRAGMA table_info(security_audit_log)");
    $columns = $result->fetchAll(PDO::FETCH_ASSOC);
    
    echo "security_audit_log columns:\n";
    foreach ($columns as $col) {
        echo "  - " . $col['name'] . " (" . $col['type'] . ") NOT NULL: " . ($col['notnull'] ? 'YES' : 'NO') . "\n";
    }
    
    echo "\ncompliance_audit_log columns:\n";
    $result = $connection->query("PRAGMA table_info(compliance_audit_log)");
    $columns = $result->fetchAll(PDO::FETCH_ASSOC);
    foreach ($columns as $col) {
        echo "  - " . $col['name'] . " (" . $col['type'] . ") NOT NULL: " . ($col['notnull'] ? 'YES' : 'NO') . "\n";
    }
} else {
    echo "MySQL database detected\n";
    $result = $connection->query("DESCRIBE security_audit_log");
    echo "security_audit_log columns:\n";
    while ($col = $result->fetch_assoc()) {
        echo "  - " . $col['Field'] . " (" . $col['Type'] . ") NULL: " . ($col['Null'] === 'YES' ? 'YES' : 'NO') . "\n";
    }
}

// Try inserting a test record and show error
echo "\n\nTrying to insert test record...\n";
try {
    $sql = "INSERT INTO security_audit_log (event_type, user_id, username, ip_address, user_agent, details, severity, timestamp) 
            VALUES ('test', 38, 'developer', '127.0.0.1', 'Test Agent', 'Test details', 'info', ?)";
    $stmt = $connection->prepare($sql);
    $timestamp = date('Y-m-d H:i:s');
    $stmt->bindParam(1, $timestamp);
    $result = $stmt->execute();
    echo "Result: " . ($result ? "SUCCESS" : "FAILED") . "\n";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>
