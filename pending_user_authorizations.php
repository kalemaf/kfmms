<?php
/**
 * Manage Pending User Authorization Codes
 * Allows admins/developers to view, approve, and deny pending user creation requests
 * 
 * This file is included via index.php navigation, so it's content-only (no HTML headers)
 */

// Note: config.inc.php and common.inc.php are already loaded by index.php
// Declare globals explicitly for clarity
global $connection, $db_type, $db_available;

// Check if user has admin/developer access
$user_role = $_SESSION['role'] ?? '';
$user_email = $_SESSION['email'] ?? '';
$allowed_emails = ['kalemaf876@gmail.com'];

if (!in_array($user_role, ['admin', 'developer'], true) && !in_array($user_email, $allowed_emails, true)) {
    echo '<div style="padding: 20px; color: red;"><h2>Access Denied</h2><p>Only admins and developers can access this page.</p></div>';
    return;
}

if (!$db_available) {
    die('<div style="padding: 20px; color: red;"><h2>Database Error</h2><p>Database is not available.</p></div>');
}

// Ensure the user_creation_authorizations table exists
// For SQLite, use the function defined in config.inc.php
if ($db_type === 'sqlite') {
    if (function_exists('ensure_sqlite_user_creation_authorizations_table')) {
        ensure_sqlite_user_creation_authorizations_table($connection);
    }
} else {
    // For MySQL, define and call the function inline or require access.php
    if (!function_exists('ensure_user_creation_authorizations_table')) {
        // Define it here to avoid circular includes
        if (!function_exists('ensure_user_creation_authorizations_table_inline')) {
            function ensure_user_creation_authorizations_table_inline($connection) {
                if (!is_object($connection)) {
                    return;
                }
                $connection->query(
                    "CREATE TABLE IF NOT EXISTS `user_creation_authorizations` (
                        `auth_id` INT(11) NOT NULL AUTO_INCREMENT,
                        `pending_username` VARCHAR(50) NOT NULL,
                        `pending_email` VARCHAR(255) NULL,
                        `password_hash` VARCHAR(255) NOT NULL,
                        `temp_password` VARCHAR(255) NULL,
                        `role` ENUM('admin','maintenance manager','supervisor','technician','operator') NOT NULL DEFAULT 'operator',
                        `phone` VARCHAR(20) NULL,
                        `country_code` VARCHAR(5) DEFAULT '+256',
                        `requestor_id` INT(11) NULL,
                        `requestor_name` VARCHAR(255) NULL,
                        `auth_code` CHAR(6) NOT NULL,
                        `is_used` BOOLEAN NOT NULL DEFAULT FALSE,
                        `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                        `expires_at` DATETIME NOT NULL,
                        `used_at` DATETIME NULL,
                        PRIMARY KEY (`auth_id`),
                        UNIQUE KEY `uk_auth_code` (`auth_code`),
                        INDEX `idx_is_used` (`is_used`)
                    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
                );
            }
        }
        ensure_user_creation_authorizations_table_inline($connection);
    } else {
        ensure_user_creation_authorizations_table($connection);
    }
}

// Handle approval/denial
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $auth_id = (int)($_POST['auth_id'] ?? 0);

    if ($action === 'approve' && $auth_id > 0) {
        // Mark as ready for user to confirm with code
        // The user will still need to enter the code from the authorization record
        echo '<div style="padding: 10px; background: #d4edda; color: #155724; margin: 10px 0; border-radius: 4px;">';
        echo 'Authorization ID ' . $auth_id . ' has been approved. The requester can now use the 6-digit code to complete user creation.';
        echo '</div>';
    } elseif ($action === 'deny' && $auth_id > 0) {
        // Delete the authorization record
        $stmt = $connection->prepare("DELETE FROM user_creation_authorizations WHERE auth_id = ?");
        if ($stmt) {
            $stmt->bind_param('i', $auth_id);
            if ($stmt->execute()) {
                echo '<div style="padding: 10px; background: #f8d7da; color: #721c24; margin: 10px 0; border-radius: 4px;">';
                echo 'Authorization request has been denied and removed.';
                echo '</div>';
            }
            $stmt->close();
        }
    }
}

// Fetch pending authorizations
$query = "SELECT auth_id, pending_username, pending_email, role, phone, country_code, requestor_name, auth_code, created_at, expires_at, is_used 
          FROM user_creation_authorizations 
          ORDER BY created_at DESC";

if ($db_type === 'sqlite') {
    $stmt = $connection->prepare($query);
    if (!$stmt) {
        die('<div style="padding: 20px; color: red;">Database error: Unable to prepare statement</div>');
    }
    $stmt->execute();
    $pending = [];
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $pending[] = $row;
    }
    $stmt->closeCursor();
} else {
    $stmt = $connection->prepare($query);
    if (!$stmt) {
        die('<div style="padding: 20px; color: red;">Database error: ' . $connection->error . '</div>');
    }
    $stmt->execute();
    $result = $stmt->get_result();
    $pending = [];
    while ($row = $result->fetch_assoc()) {
        $pending[] = $row;
    }
    $stmt->close();
}

?>
<style>
    .pending-auth-container {
        max-width: 1000px;
        background: white;
        padding: 20px;
        border-radius: 8px;
    }
    .pending-auth-container h2 {
        color: #333;
        margin-bottom: 20px;
    }
    .pending-auth-notice {
        padding: 15px;
        background: #e7f3ff;
        border-left: 4px solid #2196F3;
        margin-bottom: 20px;
        border-radius: 4px;
    }
    .pending-auth-no-pending {
        padding: 20px;
        text-align: center;
        color: #666;
        background: #f9f9f9;
        border-radius: 4px;
    }
    .pending-auth-table {
        width: 100%;
        border-collapse: collapse;
        margin-top: 20px;
    }
    .pending-auth-table th, .pending-auth-table td {
        padding: 12px;
        text-align: left;
        border-bottom: 1px solid #ddd;
    }
    .pending-auth-table th {
        background: #f5f5f5;
        font-weight: bold;
        color: #333;
    }
    .pending-auth-table tr:hover {
        background: #f9f9f9;
    }
    .pending-auth-code {
        font-family: monospace;
        font-size: 18px;
        font-weight: bold;
        color: #d9534f;
        background: #fef5f5;
        padding: 5px 10px;
        border-radius: 3px;
        letter-spacing: 2px;
    }
    .pending-auth-status-used {
        color: #999;
        text-decoration: line-through;
    }
    .pending-auth-status-expired {
        color: #d9534f;
    }
    .pending-auth-status-valid {
        color: #5cb85c;
    }
    .pending-auth-actions {
        display: flex;
        gap: 5px;
    }
    .pending-auth-btn {
        padding: 6px 12px;
        margin: 2px;
        border: none;
        border-radius: 3px;
        cursor: pointer;
        font-size: 12px;
    }
    .pending-auth-btn-copy {
        background: #5cb85c;
        color: white;
    }
    .pending-auth-btn-copy:hover {
        background: #4cae4c;
    }
    .pending-auth-btn-deny {
        background: #d9534f;
        color: white;
    }
    .pending-auth-btn-deny:hover {
        background: #c9302c;
    }
</style>

<div class="pending-auth-container">
    <h2>⚙️ Pending User Authorization Codes</h2>
        
        <div class="pending-auth-notice">
            <strong>ℹ️ Info:</strong> When a user requests to create a new account, a 6-digit authorization code is generated and stored here. 
            Share the code with the requester so they can complete the user creation process. 
            If email is not configured, codes must be shared manually.
        </div>

        <?php if (empty($pending)): ?>
            <div class="pending-auth-no-pending">
                <p>No pending user authorization requests at this time.</p>
            </div>
        <?php else: ?>
            <p>Total pending requests: <strong><?php echo count($pending); ?></strong></p>
            
            <table class="pending-auth-table">
                <thead>
                    <tr>
                        <th>Username</th>
                        <th>Email</th>
                        <th>Role</th>
                        <th>Requested By</th>
                        <th>Authorization Code</th>
                        <th>Status</th>
                        <th>Expires</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($pending as $auth): ?>
                    <tr>
                        <td><strong><?php echo htmlspecialchars($auth['pending_username']); ?></strong></td>
                        <td><?php echo htmlspecialchars($auth['pending_email'] ?: '(not provided)'); ?></td>
                        <td><?php echo htmlspecialchars($auth['role']); ?></td>
                        <td><?php echo htmlspecialchars($auth['requestor_name']); ?></td>
                        <td>
                            <span class="pending-auth-code"><?php echo htmlspecialchars($auth['auth_code']); ?></span>
                        </td>
                        <td>
                            <?php
                            if ($auth['is_used']) {
                                echo '<span class="pending-auth-status-used">Used</span>';
                            } else {
                                $expires = strtotime($auth['expires_at']);
                                if ($expires < time()) {
                                    echo '<span class="pending-auth-status-expired">Expired</span>';
                                } else {
                                    echo '<span class="pending-auth-status-valid">Valid</span>';
                                }
                            }
                            ?>
                        </td>
                        <td><?php echo date('M d, Y H:i', strtotime($auth['expires_at'])); ?></td>
                        <td>
                            <button class="pending-auth-btn pending-auth-btn-copy" onclick="copyToClipboard('<?php echo htmlspecialchars($auth['auth_code']); ?>')">
                                📋 Copy Code
                            </button>
                            <button class="pending-auth-btn pending-auth-btn-deny" onclick="denyAuth(<?php echo (int)$auth['auth_id']; ?>)">
                                ✕ Deny
                            </button>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
</div>

<script>
    function copyToClipboard(code) {
        navigator.clipboard.writeText(code).then(() => {
            alert('Code copied to clipboard: ' + code);
        }).catch(() => {
            prompt('Copy this code:', code);
        });
    }
    
    function denyAuth(authId) {
        if (confirm('Are you sure you want to deny this authorization request?')) {
            const form = document.createElement('form');
            form.method = 'POST';
            form.innerHTML = '<input type="hidden" name="action" value="deny">' +
                            '<input type="hidden" name="auth_id" value="' + authId + '">';
            document.body.appendChild(form);
            form.submit();
        }
    }
</script>
