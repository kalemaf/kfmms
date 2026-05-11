<?php
/**
 * Audit Logs for CMMS
 * Content only - to be included in index.php
 */

require_once 'config.inc.php';
require_once 'common.inc.php';

// Check if user has admin/manager access
$user_role = $_SESSION['role'] ?? '';
if (!in_array($user_role, ['admin', 'manager', 'maintenance manager', 'supervisor', 'developer'], true)) {
    echo '<h2>Access Denied</h2><p>You do not have permission to view audit logs.</p><p><a href="index.php">Return to Main Application</a></p>';
    return;
}

// Get audit logs data (limited to recent entries)
$audit_logs = [];
if ($connection) {
    try {
        // Try different possible audit log tables
        $tables_to_check = ['audit_logs', 'security_audit_log', 'compliance_audit_log'];
        $found = false;
        
        foreach ($tables_to_check as $table) {
            $table_exists = false;
            
            if ($db_type === 'sqlite') {
                // SQLite: Check if table exists
                $check = $connection->query("SELECT name FROM sqlite_master WHERE type='table' AND name='$table'");
                $table_exists = $check && $check->fetch(PDO::FETCH_ASSOC) !== false;
            } else {
                // MySQL: Use SHOW TABLES
                $check = $connection->query("SHOW TABLES LIKE '$table'");
                $table_exists = $check && $check->rowCount() > 0;
            }
            
            if ($table_exists) {
                $result = $connection->query("SELECT * FROM \"$table\" ORDER BY log_id DESC LIMIT 50");
                if ($result) {
                    while ($row = $result->fetch(PDO::FETCH_ASSOC)) {
                        $audit_logs[] = $row;
                    }
                    $found = true;
                    break; // Use the first table that exists and has data
                }
            }
        }
    } catch (Exception $e) {
        error_log("[ERROR] Failed to retrieve audit logs: " . $e->getMessage());
    }
}
?>

<h2>Audit Logs</h2>

<?php if (empty($audit_logs)): ?>
    <div style="background-color: #f9f9f9; border: 1px solid #ddd; padding: 20px; margin: 20px 0; border-radius: 5px;">
        <h3 style="color: #666; margin-top: 0;">No Audit Logs Found</h3>
        <p>The audit logging system may not be fully configured yet.</p>
        <p>Audit logs track system activities, user actions, and security events.</p>
        <p>To enable audit logging, please check the security implementation guides.</p>
    </div>
<?php else: ?>
    <p>Total Log Entries: <?php echo count($audit_logs); ?> (showing most recent)</p>

    <table border="1" cellpadding="5" cellspacing="0" style="border-collapse: collapse; width: 100%; margin-top: 20px;">
        <tr style="background-color: #f0f0f0;">
            <th>Timestamp</th>
            <th>User</th>
            <th>Action</th>
            <th>Details</th>
            <th>IP Address</th>
        </tr>
        <?php foreach ($audit_logs as $log): ?>
        <tr>
            <td><?php echo htmlspecialchars($log['timestamp'] ?? $log['TEXT'] ?? $log['created_at'] ?? ''); ?></td>
            <td><?php echo htmlspecialchars($log['username'] ?? $log['user_id'] ?? 'System'); ?></td>
            <td><?php echo htmlspecialchars($log['action'] ?? $log['event_type'] ?? ''); ?></td>
            <td><?php echo htmlspecialchars(substr($log['details'] ?? $log['description'] ?? '', 0, 100)); ?><?php echo (strlen($log['details'] ?? $log['description'] ?? '') > 100) ? '...' : ''; ?></td>
            <td><?php echo htmlspecialchars($log['ip_address'] ?? ''); ?></td>
        </tr>
        <?php endforeach; ?>
    </table>
<?php endif; ?>

<h3>Audit Log Information</h3>
<ul>
    <li><strong>Security Events</strong>: Login attempts, password changes, permission changes</li>
    <li><strong>User Actions</strong>: Work order updates, equipment changes, inventory modifications</li>
    <li><strong>System Events</strong>: Automated processes, scheduled tasks, system maintenance</li>
    <li><strong>Compliance</strong>: SOX, GDPR, and other regulatory compliance logging</li>
</ul>

<p style="margin-top: 20px;">
    <a href="index.php?nav=admin">Back to Administration</a> |
    <a href="index.php">Back to Dashboard</a>
</p>