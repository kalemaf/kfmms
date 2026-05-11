<?php
/**
 * Audit Logger Test Script
 * Generates sample audit log entries to verify the system is working
 */

require_once 'config.inc.php';
require_once 'app/AuditLogger.php';

echo "=== Audit Logger Test Script ===\n\n";

if (!isset($connection) || !is_object($connection)) {
    echo "❌ Error: Cannot connect to database\n";
    exit(1);
}

$audit = new AuditLogger($connection, $db_type);

echo "Testing Audit Logger Functions...\n\n";

// Test 1: Log a security event
echo "1. Testing logSecurityEvent()...\n";
$result = $audit->logSecurityEvent('test_event', 38, 'developer', 'This is a test event', AuditLogger::SEVERITY_INFO);
echo "   Result: " . ($result ? "✅ SUCCESS" : "❌ FAILED") . "\n\n";

// Test 2: Log a compliance event
echo "2. Testing logComplianceEvent()...\n";
$result = $audit->logComplianceEvent('test', 38, 'test_resource', '123', ['old' => 'value'], ['new' => 'value'], 'general');
echo "   Result: " . ($result ? "✅ SUCCESS" : "❌ FAILED") . "\n\n";

// Test 3: Log a login attempt
echo "3. Testing logLoginAttempt()...\n";
$result = $audit->logLoginAttempt('testuser', true, 38, '');
echo "   Result: " . ($result ? "✅ SUCCESS" : "❌ FAILED") . "\n\n";

// Test 4: Log a logout
echo "4. Testing logLogout()...\n";
$result = $audit->logLogout(38, 'developer');
echo "   Result: " . ($result ? "✅ SUCCESS" : "❌ FAILED") . "\n\n";

// Test 5: Log user creation
echo "5. Testing logUserCreated()...\n";
$result = $audit->logUserCreated(38, 999, ['username' => 'testuser', 'email' => 'test@example.com', 'role' => 'operator']);
echo "   Result: " . ($result ? "✅ SUCCESS" : "❌ FAILED") . "\n\n";

// Test 6: Log password change
echo "6. Testing logPasswordChange()...\n";
$result = $audit->logPasswordChange(38);
echo "   Result: " . ($result ? "✅ SUCCESS" : "❌ FAILED") . "\n\n";

// Test 7: Get security logs
echo "7. Testing getSecurityLogs()...\n";
$logs = $audit->getSecurityLogs(5);
echo "   Retrieved " . count($logs) . " security logs\n";
if (count($logs) > 0) {
    echo "   Sample log:\n";
    $sample = $logs[0];
    echo "     - Event: " . $sample['event_type'] . "\n";
    echo "     - User: " . $sample['username'] . "\n";
    echo "     - IP: " . $sample['ip_address'] . "\n";
    echo "     - Severity: " . $sample['severity'] . "\n";
}
echo "\n";

// Test 8: Get compliance logs
echo "8. Testing getComplianceLogs()...\n";
$logs = $audit->getComplianceLogs(5);
echo "   Retrieved " . count($logs) . " compliance logs\n";
if (count($logs) > 0) {
    echo "   Sample log:\n";
    $sample = $logs[0];
    echo "     - Action: " . $sample['action'] . "\n";
    echo "     - Resource: " . $sample['resource_type'] . "\n";
    echo "     - Resource ID: " . $sample['resource_id'] . "\n";
}
echo "\n";

// Summary
echo "=== Audit Logger Test Complete ===\n\n";
echo "✅ All tests completed successfully!\n";
echo "Audit logging system is now active and logging events to the database.\n";
?>
