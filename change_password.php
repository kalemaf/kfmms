<?php
require_once __DIR__ . '/common.inc.php';
require_once __DIR__ . '/config.inc.php';

// Check if user is logged in
if (!isset($_SESSION['user_id']) || empty($_SESSION['user_id'])) {
    header('Location: auth.php');
    exit;
}

// Check if password change is required
if (empty($_SESSION['password_change_required'])) {
    // User does not need to change password, redirect to dashboard
    header('Location: dashboard.php');
    exit;
}

$user_id = $_SESSION['user_id'];
$username = $_SESSION['username'] ?? '';
$message = '';
$error = '';

// Process password change form
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $current_password = $_POST['current_password'] ?? '';
    $new_password = $_POST['new_password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    
    // Validate inputs
    if (empty($current_password)) {
        $error = 'Current password is required';
    } elseif (empty($new_password)) {
        $error = 'New password is required';
    } elseif (empty($confirm_password)) {
        $error = 'Password confirmation is required';
    } elseif ($new_password !== $confirm_password) {
        $error = 'New passwords do not match';
    } elseif (strlen($new_password) < 8) {
        $error = 'New password must be at least 8 characters long';
    } elseif (!preg_match('/[A-Z]/', $new_password) || !preg_match('/[0-9]/', $new_password)) {
        $error = 'Password must contain at least one uppercase letter and one number';
    } else {
        // Verify current password
        try {
            $stmt = $connection->prepare("SELECT password_hash FROM users WHERE id = ? LIMIT 1");
            if (!$stmt) {
                $error = 'Database error: ' . $connection->error;
            } else {
                $stmt->bind_param('i', $user_id);
                $stmt->execute();
                $result = $stmt->get_result();
                
                if ($result->num_rows === 0) {
                    $error = 'User not found';
                } else {
                    $row = $result->fetch_assoc();
                    $stored_hash = $row['password_hash'];
                    
                    // Verify password
                    if (password_verify($current_password, $stored_hash)) {
                        // Current password is correct, update to new password
                        $new_hash = password_hash($new_password, PASSWORD_DEFAULT);
                        $updateStmt = $connection->prepare(
                            "UPDATE users SET password_hash = ?, password_change_required = 0 WHERE id = ?"
                        );
                        
                        if (!$updateStmt) {
                            $error = 'Database error: ' . $connection->error;
                        } else {
                            $updateStmt->bind_param('si', $new_hash, $user_id);
                            if ($updateStmt->execute()) {
                                $_SESSION['password_change_required'] = 0;
                                $message = 'Password changed successfully!';
                                // Redirect to dashboard after 2 seconds
                                header('Refresh: 2; URL=dashboard.php');
                            } else {
                                $error = 'Failed to update password: ' . $updateStmt->error;
                            }
                            $updateStmt->close();
                        }
                    } else {
                        $error = 'Current password is incorrect';
                    }
                }
                $stmt->close();
            }
        } catch (Exception $e) {
            $error = 'Error: ' . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Change Password - CMMS</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        .password-change-container {
            background: white;
            border-radius: 10px;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.2);
            padding: 40px;
            max-width: 450px;
            width: 100%;
        }
        .password-change-container h2 {
            color: #333;
            margin-bottom: 10px;
            font-weight: 600;
            text-align: center;
        }
        .subtitle {
            text-align: center;
            color: #666;
            margin-bottom: 30px;
            font-size: 14px;
        }
        .form-group {
            margin-bottom: 20px;
        }
        .form-group label {
            font-weight: 500;
            color: #333;
            margin-bottom: 8px;
            font-size: 14px;
        }
        .form-control {
            border-radius: 5px;
            border: 1px solid #ddd;
            padding: 10px 12px;
            font-size: 14px;
            transition: all 0.3s;
        }
        .form-control:focus {
            border-color: #667eea;
            box-shadow: 0 0 0 0.2rem rgba(102, 126, 234, 0.25);
        }
        .password-strength {
            margin-top: 10px;
            padding: 10px;
            border-radius: 5px;
            font-size: 13px;
            display: none;
        }
        .password-strength.weak {
            display: block;
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        .password-strength.fair {
            display: block;
            background-color: #fff3cd;
            color: #856404;
            border: 1px solid #ffeaa7;
        }
        .password-strength.good {
            display: block;
            background-color: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        .btn-change {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border: none;
            color: white;
            padding: 10px 20px;
            border-radius: 5px;
            font-weight: 600;
            width: 100%;
            transition: all 0.3s;
            margin-top: 10px;
        }
        .btn-change:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 20px rgba(102, 126, 234, 0.4);
            color: white;
        }
        .alert {
            border-radius: 5px;
            margin-bottom: 20px;
            font-size: 14px;
        }
        .requirements {
            background-color: #f8f9fa;
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 20px;
            font-size: 13px;
            border-left: 4px solid #667eea;
        }
        .requirements h6 {
            margin-bottom: 10px;
            font-weight: 600;
            color: #333;
        }
        .requirements ul {
            margin: 0;
            padding-left: 20px;
        }
        .requirements li {
            color: #666;
            margin-bottom: 5px;
        }
        .requirements li.done {
            color: #28a745;
        }
        .fa-check {
            margin-right: 5px;
        }
    </style>
</head>
<body>
    <div class="password-change-container">
        <h2><i class="fas fa-lock"></i> Change Password</h2>
        <p class="subtitle">First Login - You must change your temporary password</p>

        <?php if (!empty($message)): ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($message); ?>
            </div>
        <?php endif; ?>

        <?php if (!empty($error)): ?>
            <div class="alert alert-danger">
                <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>

        <div class="requirements">
            <h6>Password Requirements:</h6>
            <ul>
                <li>At least 8 characters long</li>
                <li>At least one uppercase letter (A-Z)</li>
                <li>At least one number (0-9)</li>
            </ul>
        </div>

        <form method="POST" action="change_password.php">
            <div class="form-group">
                <label for="current_password">Current Password (Temporary)</label>
                <input 
                    type="password" 
                    class="form-control" 
                    id="current_password" 
                    name="current_password" 
                    required 
                    placeholder="Enter your temporary password"
                >
            </div>

            <div class="form-group">
                <label for="new_password">New Password</label>
                <input 
                    type="password" 
                    class="form-control" 
                    id="new_password" 
                    name="new_password" 
                    required 
                    placeholder="Enter your new password"
                    onkeyup="checkPasswordStrength()"
                >
                <div id="strengthIndicator" class="password-strength"></div>
            </div>

            <div class="form-group">
                <label for="confirm_password">Confirm New Password</label>
                <input 
                    type="password" 
                    class="form-control" 
                    id="confirm_password" 
                    name="confirm_password" 
                    required 
                    placeholder="Re-enter your new password"
                >
            </div>

            <button type="submit" class="btn-change">
                <i class="fas fa-check"></i> Update Password
            </button>
        </form>

        <div style="margin-top: 20px; text-align: center; color: #999; font-size: 12px;">
            <p><i class="fas fa-info-circle"></i> You must change your password to continue</p>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function checkPasswordStrength() {
            const password = document.getElementById('new_password').value;
            const indicator = document.getElementById('strengthIndicator');
            
            if (!password) {
                indicator.className = 'password-strength';
                return;
            }
            
            let strength = 0;
            if (password.length >= 8) strength++;
            if (/[A-Z]/.test(password)) strength++;
            if (/[0-9]/.test(password)) strength++;
            if (/[!@#$%^&*]/.test(password)) strength++;
            
            if (strength < 2) {
                indicator.className = 'password-strength weak';
                indicator.innerHTML = '<i class="fas fa-exclamation-triangle"></i> Weak password';
            } else if (strength < 3) {
                indicator.className = 'password-strength fair';
                indicator.innerHTML = '<i class="fas fa-exclamation-circle"></i> Fair password - add more characters or special characters';
            } else {
                indicator.className = 'password-strength good';
                indicator.innerHTML = '<i class="fas fa-check-circle"></i> Strong password';
            }
        }

        // Auto-redirect if password changed successfully
        document.addEventListener('DOMContentLoaded', function() {
            const successAlert = document.querySelector('.alert-success');
            if (successAlert) {
                console.log('Password changed successfully, redirecting to dashboard...');
            }
        });
    </script>
</body>
</html>
