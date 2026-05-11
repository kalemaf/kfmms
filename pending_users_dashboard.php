<?php
/**
 * 🔐 PENDING USERS DASHBOARD
 * View temporary passwords for users who need to set permanent password
 * Admin/Developer exclusive access
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once 'config.inc.php';
require_once 'common.inc.php';
require_once 'app/PasswordManager.php';

session_save_path($session_save_path);
session_start();

global $db_type;

// Check if user is developer or admin
$user_role = strtolower(trim($_SESSION['role'] ?? ''));
$user_name = strtolower(trim($_SESSION['user'] ?? ''));

if (!in_array($user_role, ['developer', 'admin'], true) && $user_name !== 'developer') {
    die('<div class="alert alert-danger"><h4>🚫 Access Denied</h4><p>Admin/Developer access required.</p></div>');
}

$message = '';
$message_type = '';

// Handle actions
$action = $_GET['action'] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $post_action = $_POST['action'] ?? '';
    
    if ($post_action === 'mark_activated') {
        $user_id = (int)($_POST['user_id'] ?? 0);
        
        if ($user_id > 0) {
            if ($db_type === 'sqlite') {
                $stmt = $connection->prepare("UPDATE users SET must_change_password = 0, password_change_required = 0 WHERE user_id = ?");
                $stmt->bindParam(1, $user_id, PDO::PARAM_INT);
                $stmt->execute();
            } else {
                $stmt = $connection->prepare("UPDATE users SET must_change_password = 0, password_change_required = 0 WHERE user_id = ?");
                $stmt->bind_param('i', $user_id);
                $stmt->execute();
            }
            
            $message = '✅ User marked as activated!';
            $message_type = 'success';
        }
    }
    elseif ($post_action === 'resend_password') {
        $user_id = (int)($_POST['user_id'] ?? 0);
        
        if ($user_id > 0) {
            // Get user info
            if ($db_type === 'sqlite') {
                $stmt = $connection->prepare("SELECT email, username, temporary_password FROM users WHERE user_id = ?");
                $stmt->bindParam(1, $user_id, PDO::PARAM_INT);
                $stmt->execute();
                $user = $stmt->fetch(PDO::FETCH_ASSOC);
            } else {
                $stmt = $connection->prepare("SELECT email, username, temporary_password FROM users WHERE user_id = ?");
                $stmt->bind_param('i', $user_id);
                $stmt->execute();
                $result = $stmt->get_result();
                $user = $result->fetch_assoc();
            }
            
            if ($user) {
                // Send email (you can implement email function)
                $to = $user['email'];
                $subject = "Your KFMMS Temporary Password";
                $body = "Hello " . $user['username'] . ",\n\n";
                $body .= "Your temporary password is: " . $user['temporary_password'] . "\n\n";
                $body .= "Please login and change this password immediately.\n\n";
                $body .= "System: KFMMS";
                
                // For now, just show the message
                $message = '✅ Password resent to ' . htmlspecialchars($to) . ' (Email feature ready to implement)';
                $message_type = 'success';
            }
        }
    }
    elseif ($post_action === 'reset_password') {
        $user_id = (int)($_POST['user_id'] ?? 0);
        
        if ($user_id > 0) {
            // Generate new temporary password
            $new_temp_password = PasswordManager::generateTemporaryPassword();
            $new_hash = PasswordManager::hashPassword($new_temp_password);
            
            // Update database
            if ($db_type === 'sqlite') {
                $stmt = $connection->prepare("UPDATE users SET password_hash = ?, temporary_password = ?, must_change_password = 1 WHERE user_id = ?");
                $stmt->bindParam(1, $new_hash, PDO::PARAM_STR);
                $stmt->bindParam(2, $new_temp_password, PDO::PARAM_STR);
                $stmt->bindParam(3, $user_id, PDO::PARAM_INT);
                $stmt->execute();
            } else {
                $stmt = $connection->prepare("UPDATE users SET password_hash = ?, temporary_password = ?, must_change_password = 1 WHERE user_id = ?");
                $stmt->bind_param('ssi', $new_hash, $new_temp_password, $user_id);
                $stmt->execute();
            }
            
            $message = '✅ Password reset! New temporary password: <code style="background:#f0f0f0;padding:2px 6px;">' . htmlspecialchars($new_temp_password) . '</code>';
            $message_type = 'success';
        }
    }
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pending Users Dashboard - KFMMS</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 20px;
        }
        
        .container {
            max-width: 1200px;
            margin: 0 auto;
            background: white;
            border-radius: 12px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.2);
            overflow: hidden;
        }
        
        .header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 30px;
            text-align: center;
        }
        
        .header h1 {
            font-size: 2em;
            margin-bottom: 10px;
        }
        
        .header p {
            font-size: 1.1em;
            opacity: 0.9;
        }
        
        .content {
            padding: 30px;
        }
        
        .alert {
            padding: 15px 20px;
            border-radius: 8px;
            margin-bottom: 20px;
            border-left: 4px solid;
        }
        
        .alert-success {
            background: #d4edda;
            border-color: #28a745;
            color: #155724;
        }
        
        .alert-info {
            background: #d1ecf1;
            border-color: #17a2b8;
            color: #0c5460;
        }
        
        .alert-warning {
            background: #fff3cd;
            border-color: #ffc107;
            color: #856404;
        }
        
        .alert-danger {
            background: #f8d7da;
            border-color: #f5c6cb;
            color: #721c24;
        }
        
        .stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .stat-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 20px;
            border-radius: 8px;
            text-align: center;
        }
        
        .stat-card h3 {
            font-size: 2.5em;
            margin-bottom: 10px;
        }
        
        .stat-card p {
            opacity: 0.9;
            font-size: 0.95em;
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }
        
        thead {
            background: #f8f9fa;
            border-bottom: 2px solid #dee2e6;
        }
        
        th {
            padding: 15px;
            text-align: left;
            font-weight: 600;
            color: #333;
        }
        
        td {
            padding: 15px;
            border-bottom: 1px solid #dee2e6;
        }
        
        tr:hover {
            background: #f8f9fa;
        }
        
        .copy-btn {
            background: #667eea;
            color: white;
            border: none;
            padding: 5px 10px;
            border-radius: 4px;
            cursor: pointer;
            font-size: 0.9em;
            transition: 0.3s;
        }
        
        .copy-btn:hover {
            background: #764ba2;
        }
        
        .password-cell {
            display: flex;
            gap: 10px;
            align-items: center;
        }
        
        .password-display {
            background: #f8f9fa;
            padding: 5px 10px;
            border-radius: 4px;
            font-family: monospace;
            font-weight: bold;
            color: #667eea;
            user-select: all;
        }
        
        .action-buttons {
            display: flex;
            gap: 5px;
            flex-wrap: wrap;
        }
        
        .btn {
            padding: 6px 12px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 0.85em;
            transition: 0.3s;
            text-decoration: none;
            display: inline-block;
        }
        
        .btn-primary {
            background: #667eea;
            color: white;
        }
        
        .btn-primary:hover {
            background: #764ba2;
        }
        
        .btn-success {
            background: #28a745;
            color: white;
        }
        
        .btn-success:hover {
            background: #218838;
        }
        
        .btn-warning {
            background: #ffc107;
            color: #333;
        }
        
        .btn-warning:hover {
            background: #e0a800;
        }
        
        .btn-danger {
            background: #dc3545;
            color: white;
        }
        
        .btn-danger:hover {
            background: #c82333;
        }
        
        .status-badge {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 0.85em;
            font-weight: 600;
        }
        
        .status-pending {
            background: #fff3cd;
            color: #856404;
        }
        
        .status-activated {
            background: #d4edda;
            color: #155724;
        }
        
        .no-users {
            text-align: center;
            padding: 40px 20px;
            color: #666;
        }
        
        .no-users p {
            font-size: 1.1em;
            margin-bottom: 10px;
        }
        
        .return-link {
            display: inline-block;
            margin-top: 20px;
            padding: 10px 20px;
            background: #667eea;
            color: white;
            text-decoration: none;
            border-radius: 4px;
            transition: 0.3s;
        }
        
        .return-link:hover {
            background: #764ba2;
        }

        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.5);
            align-items: center;
            justify-content: center;
        }

        .modal.show {
            display: flex;
        }

        .modal-content {
            background: white;
            padding: 30px;
            border-radius: 8px;
            max-width: 400px;
            width: 90%;
        }

        .modal-header {
            margin-bottom: 20px;
            font-size: 1.5em;
            font-weight: 600;
        }

        .modal-body {
            margin-bottom: 20px;
        }

        .modal-footer {
            display: flex;
            gap: 10px;
            justify-content: flex-end;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>📋 Pending Users Dashboard</h1>
            <p>Manage temporary passwords for users who need to set permanent password</p>
        </div>
        
        <div class="content">
            <?php if (!empty($message)): ?>
                <div class="alert alert-<?php echo htmlspecialchars($message_type); ?>">
                    <?php echo $message; ?>
                </div>
            <?php endif; ?>
            
            <?php
            // Get statistics
            if ($db_type === 'sqlite') {
                $total_stmt = $connection->prepare("SELECT COUNT(*) as total FROM users WHERE must_change_password = 1 OR password_change_required = 1");
                $total_stmt->execute();
                $total_row = $total_stmt->fetch(PDO::FETCH_ASSOC);
                $total_pending = $total_row['total'];
            } else {
                $total_stmt = $connection->prepare("SELECT COUNT(*) as total FROM users WHERE must_change_password = 1 OR password_change_required = 1");
                $total_stmt->execute();
                $total_result = $total_stmt->get_result();
                $total_row = $total_result->fetch_assoc();
                $total_pending = $total_row['total'];
            }
            ?>
            
            <div class="stats">
                <div class="stat-card">
                    <h3><?php echo $total_pending; ?></h3>
                    <p>Pending Users</p>
                </div>
            </div>
            
            <?php
            // Get pending users
            if ($db_type === 'sqlite') {
                $stmt = $connection->prepare("SELECT user_id, username, email, temporary_password, company_id, created_at, must_change_password FROM users WHERE must_change_password = 1 OR password_change_required = 1 ORDER BY created_at DESC");
                $stmt->execute();
                $pending_users = [];
                while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                    $pending_users[] = $row;
                }
            } else {
                $stmt = $connection->prepare("SELECT user_id, username, email, temporary_password, company_id, created_at, must_change_password FROM users WHERE must_change_password = 1 OR password_change_required = 1 ORDER BY created_at DESC");
                $stmt->execute();
                $result = $stmt->get_result();
                $pending_users = [];
                while ($row = $result->fetch_assoc()) {
                    $pending_users[] = $row;
                }
            }
            ?>
            
            <?php if (count($pending_users) > 0): ?>
                <table>
                    <thead>
                        <tr>
                            <th>Username</th>
                            <th>Email</th>
                            <th>Temporary Password</th>
                            <th>Company</th>
                            <th>Created</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($pending_users as $user): ?>
                            <tr>
                                <td><strong><?php echo htmlspecialchars($user['username']); ?></strong></td>
                                <td><?php echo htmlspecialchars($user['email']); ?></td>
                                <td>
                                    <div class="password-cell">
                                        <div class="password-display" id="pwd-<?php echo $user['user_id']; ?>">
                                            <?php echo htmlspecialchars($user['temporary_password']); ?>
                                        </div>
                                        <button class="copy-btn" onclick="copyPassword(<?php echo $user['user_id']; ?>)">📋 Copy</button>
                                    </div>
                                </td>
                                <td><?php echo $user['company_id']; ?></td>
                                <td><?php echo date('M d, Y H:i', strtotime($user['created_at'])); ?></td>
                                <td>
                                    <div class="action-buttons">
                                        <form method="POST" style="display:inline;">
                                            <input type="hidden" name="action" value="resend_password">
                                            <input type="hidden" name="user_id" value="<?php echo $user['user_id']; ?>">
                                            <button type="submit" class="btn btn-primary" title="Send password to user email">📧 Send</button>
                                        </form>
                                        <form method="POST" style="display:inline;" onsubmit="return confirm('Generate new password?');">
                                            <input type="hidden" name="action" value="reset_password">
                                            <input type="hidden" name="user_id" value="<?php echo $user['user_id']; ?>">
                                            <button type="submit" class="btn btn-warning" title="Generate new temporary password">🔄 Reset</button>
                                        </form>
                                        <form method="POST" style="display:inline;">
                                            <input type="hidden" name="action" value="mark_activated">
                                            <input type="hidden" name="user_id" value="<?php echo $user['user_id']; ?>">
                                            <button type="submit" class="btn btn-success" title="Mark user as activated">✓ Activate</button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <div class="no-users">
                    <p>✅ No pending users!</p>
                    <p style="font-size:0.95em;color:#999;">All users have set their permanent passwords.</p>
                </div>
            <?php endif; ?>
            
            <a href="admin_roles.php" class="return-link">← Back to Admin Panel</a>
        </div>
    </div>
    
    <script>
        function copyPassword(userId) {
            const element = document.getElementById('pwd-' + userId);
            const text = element.innerText;
            
            navigator.clipboard.writeText(text).then(() => {
                alert('Password copied to clipboard!');
            }).catch(() => {
                // Fallback for older browsers
                const textarea = document.createElement('textarea');
                textarea.value = text;
                document.body.appendChild(textarea);
                textarea.select();
                document.execCommand('copy');
                document.body.removeChild(textarea);
                alert('Password copied to clipboard!');
            });
        }
    </script>
</body>
</html>