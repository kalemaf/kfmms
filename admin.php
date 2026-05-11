<?php
/**
 * Admin Panel - Main administrative interface
 * Content only - to be included in index.php
 */

require_once 'config.inc.php';
require_once 'common.inc.php';

// Check if user has admin/manager access
$user_role = $_SESSION['role'] ?? '';
if (!in_array($user_role, ['admin', 'manager', 'maintenance manager', 'supervisor', 'developer'], true)) {
    echo '<h2>Access Denied</h2><p>You do not have permission to access the admin panel.</p><p><a href="index.php">Return to Main Application</a></p>';
    return;
}
?>

<style>
    .admin-container { max-width: 1200px; margin: 0 auto; padding: 20px; }
    .admin-header { text-align: center; margin-bottom: 30px; }
    .admin-section { margin-bottom: 30px; }
    .admin-section h3 { background-color: #f0f0f0; padding: 10px; margin-bottom: 15px; }
    .admin-links { display: table; width: 100%; }
    .admin-link { display: table-cell; width: 33%; padding: 10px; text-align: center; vertical-align: top; }
    .admin-link a { display: inline-block; padding: 15px 20px; background-color: #e8e8e8; border: 1px solid #ccc; text-decoration: none; color: #333; border-radius: 5px; }
    .admin-link a:hover { background-color: #d0d0d0; }
</style>

<div class="admin-container">
    <div class="admin-header">
        <h2>Administration Panel</h2>
        <p>Manage users, system settings, and administrative functions</p>
        <p><strong>Logged in as:</strong> <?php echo htmlspecialchars($_SESSION['user'] ?? 'Unknown'); ?> (<?php echo htmlspecialchars($user_role); ?>)</p>
    </div>

    <div class="admin-section">
        <h3>User & Access Management</h3>
        <div class="admin-links">
            <div class="admin-link">
                <a href="index.php?nav=users">👥 User Management<br><small>Manage user accounts, roles, and permissions</small></a>
            </div>
            <div class="admin-link">
                <a href="index.php?nav=admin_roles">🔐 Role Management<br><small>Configure roles and assign permissions</small></a>
            </div>
            <div class="admin-link">
                <a href="index.php?nav=audit">📊 Audit Logs<br><small>View system activity and security events</small></a>
            </div>
        </div>
    </div>

    <div class="admin-section">
        <h3>System Monitoring & Analytics</h3>
        <div class="admin-links">
            <div class="admin-link">
                <a href="index.php?nav=analytics">📈 Analytics Dashboard<br><small>View system analytics and reports</small></a>
            </div>
            <div class="admin-link">
                <a href="index.php?nav=maintenance_report">📋 Monthly Maintenance Report<br><small>Equipment maintenance costs, spares, and MTBF</small></a>
            </div>
            <div class="admin-link">
                <a href="index.php?nav=lifecycle">🔄 Spare Parts Lifecycle<br><small>Monitor spare parts performance and lifecycle</small></a>
            </div>
            <div class="admin-link">
                <a href="index.php?nav=health_check">🏥 System Health<br><small>Check system status and performance</small></a>
            </div>
        </div>
    </div>

    <?php if (in_array($user_role, ['developer', 'admin']) || in_array($_SESSION['email'] ?? '', ['kalemaf876@gmail.com'])): ?>
    <div class="admin-section">
        <h3>Developer Tools</h3>
        <div class="admin-links">
            <div class="admin-link">
                <a href="index.php?nav=developer" style="background-color: #fff3cd; border-color: #ffeaa7;">⚙️ Developer Admin<br><small>Advanced system management (developers only)</small></a>
            </div>
            <div class="admin-link">
                <a href="developer_license_generator.php">🔑 License Generator<br><small>Generate licenses for companies (developers)</small></a>
            </div>
            <div class="admin-link">
                <a href="index.php?nav=pending_user_authorizations">📟 Pending User Auth Codes<br><small>View and manage user creation authorization codes</small></a>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <div style="text-align: center; margin-top: 30px; padding-top: 20px; border-top: 1px solid #ccc;">
        <a href="index.php" style="padding: 10px 20px; background-color: #e8e8e8; border: 1px solid #ccc; text-decoration: none; color: #333; border-radius: 5px;">← Return to Main Application</a>
    </div>
</div>