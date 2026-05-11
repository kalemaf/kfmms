<?php
/**
 * Developer Admin Panel for CMMS
 * Content only - to be included in index.php
 */

require_once 'config.inc.php';
require_once 'common.inc.php';

log_debug("[DEBUG] developer_admin.php accessed - SESSION: " . json_encode($_SESSION));

// Check if user has developer/admin access
$user_role = $_SESSION['role'] ?? '';
$user_email = $_SESSION['email'] ?? '';
$allowed_emails = ['kalemaf876@gmail.com'];

// DEBUG: Log the checks
log_debug("[DEBUG] user_role='$user_role', user_email='$user_email'");
log_debug("[DEBUG] in_array(\$user_role, ['developer', 'admin']) = " . (in_array($user_role, ['developer', 'admin']) ? 'TRUE' : 'FALSE'));
log_debug("[DEBUG] in_array(\$user_email, \$allowed_emails) = " . (in_array($user_email, $allowed_emails) ? 'TRUE' : 'FALSE'));

if (!in_array($user_role, ['developer', 'admin']) && !in_array($user_email, $allowed_emails)) {
    echo '<h2>Access Denied</h2><p>This area is restricted to developers and administrators only.</p>';
    echo '<p><a href="index.php">Return to Main Application</a></p>';
    return;
}

// Get system information
$system_info = [];
$system_info['php_version'] = phpversion();
$system_info['server_software'] = $_SERVER['SERVER_SOFTWARE'] ?? 'Unknown';
$system_info['database_version'] = 'Unknown';
$system_info['database_type'] = $db_type === 'sqlite' ? 'SQLite' : 'MySQL';

if ($connection) {
    try {
        if ($db_type === 'sqlite') {
            // SQLite: use PRAGMA sqlite_version()
            $result = $connection->query("PRAGMA sqlite_version()");
            if ($result) {
                $row = $result->fetch(PDO::FETCH_ASSOC);
                $system_info['database_version'] = $row ? $row['sqlite_version()'] ?? 'Unknown' : 'Unknown';
            }
        } else {
            // MySQL: use VERSION()
            $result = $connection->query("SELECT VERSION() as version");
            if ($result) {
                $row = $result->fetch(PDO::FETCH_ASSOC);
                $system_info['database_version'] = $row['version'] ?? 'Unknown';
            }
        }
    } catch (Exception $e) {
        error_log("[ERROR] Failed to get database version: " . $e->getMessage());
        $system_info['database_version'] = 'Error: ' . $e->getMessage();
    }
}

// Get table counts
$table_counts = [];
if ($connection) {
    try {
        if ($db_type === 'sqlite') {
            $result = $connection->query("SELECT name FROM sqlite_master WHERE type='table' ORDER BY name");
            if ($result) {
                while ($row = $result->fetch(PDO::FETCH_ASSOC)) {
                    $table_name = $row['name'];
                    $count_result = $connection->query("SELECT COUNT(*) as cnt FROM \"$table_name\"");
                    if ($count_result) {
                        $count_row = $count_result->fetch(PDO::FETCH_ASSOC);
                        $table_counts[$table_name] = $count_row['cnt'] ?? 0;
                    }
                }
            }
        } else {
            $result = $connection->query("SHOW TABLES");
            if ($result) {
                while ($row = $result->fetch(PDO::FETCH_ASSOC)) {
                    $table_name = reset($row);
                    $count_result = $connection->query("SELECT COUNT(*) as cnt FROM `$table_name`");
                    if ($count_result) {
                        $count_row = $count_result->fetch(PDO::FETCH_ASSOC);
                        $table_counts[$table_name] = $count_row['cnt'] ?? 0;
                    }
                }
            }
        }
    } catch (Exception $e) {
        error_log("[ERROR] Failed to get table counts: " . $e->getMessage());
    }
}
?>

<style>
    .dev-section { margin-bottom: 30px; border: 1px solid #ddd; padding: 15px; background-color: #f9f9f9; }
    .dev-section h3 { margin-top: 0; color: #d9534f; }
    .info-table { border-collapse: collapse; width: 100%; }
    .info-table th, .info-table td { border: 1px solid #ddd; padding: 8px; text-align: left; }
    .info-table th { background-color: #f0f0f0; }
    .warning { color: #d9534f; font-weight: bold; }
    .success { color: #5cb85c; font-weight: bold; }
</style>

<div style="background-color: #fff3cd; border: 1px solid #ffeaa7; padding: 15px; margin-bottom: 20px; border-radius: 5px;">
    <h3 style="color: #856404; margin-top: 0;">⚠️ Developer Access Only</h3>
    <p>This panel contains advanced system management tools. Use with caution - changes made here can affect system stability.</p>
</div>

<h2>Developer Administration Panel</h2>

<div class="dev-section">
    <h3>System Information</h3>
    <table class="info-table">
        <tr>
            <th>Component</th>
            <th>Version/Details</th>
        </tr>
        <tr>
            <td>PHP Version</td>
            <td><?php echo htmlspecialchars($system_info['php_version']); ?></td>
        </tr>
        <tr>
            <td>Web Server</td>
            <td><?php echo htmlspecialchars($system_info['server_software']); ?></td>
        </tr>
        <tr>
            <td>Database Type</td>
            <td><?php echo htmlspecialchars($system_info['database_type']); ?></td>
        </tr>
        <tr>
            <td>Database Version</td>
            <td><?php echo htmlspecialchars($system_info['database_version']); ?></td>
        </tr>
        <tr>
            <td>Database Connection</td>
            <td class="<?php echo $connection ? 'success' : 'warning'; ?>"><?php echo $connection ? 'Connected' : 'Failed'; ?></td>
        </tr>
        <tr>
            <td>Session Save Path</td>
            <td><?php echo htmlspecialchars(session_save_path()); ?></td>
        </tr>
    </table>
</div>

<div class="dev-section">
    <h3>Database Tables & Record Counts</h3>
    <?php if (empty($table_counts)): ?>
        <p class="warning">Unable to retrieve table information.</p>
    <?php else: ?>
        <table class="info-table">
            <tr>
                <th>Table Name</th>
                <th>Record Count</th>
            </tr>
            <?php foreach ($table_counts as $table => $count): ?>
            <tr>
                <td><?php echo htmlspecialchars($table); ?></td>
                <td><?php echo number_format($count); ?></td>
            </tr>
            <?php endforeach; ?>
        </table>
    <?php endif; ?>
</div>

<div class="dev-section">
    <h3>Developer Tools</h3>
    <ul>
        <li><strong>Database Management</strong>: Direct SQL queries, schema modifications, data exports</li>
        <li><strong>System Configuration</strong>: PHP settings, server configuration, environment variables</li>
        <li><strong>Debug Tools</strong>: Error logging, performance monitoring, cache management</li>
        <li><strong>Maintenance</strong>: Database optimization, cleanup scripts, backup tools</li>
    </ul>

    <p><em>Note: These tools are not yet implemented in the current version. Check the developer documentation for setup instructions.</em></p>
</div>

<div class="dev-section">
    <h3>Quick Actions</h3>
    <p>Common developer tasks:</p>
    <ul>
        <li><a href="test_db.php">Test Database Connection</a></li>
        <li><a href="php_error.log" target="_blank">View PHP Error Log</a></li>
        <li><a href="server.log" target="_blank">View Server Log</a></li>
        <li><a href="setup_database.php">Run Database Setup</a></li>
    </ul>
</div>

<p style="margin-top: 20px;">
    <a href="index.php?nav=admin">Back to Administration</a> |
    <a href="index.php">Back to Dashboard</a>
</p>