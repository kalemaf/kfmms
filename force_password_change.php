<?php
/**
 * Force Password Change on First Login
 * Users with temporary passwords are redirected here on first login
 */

require_once 'config.inc.php';
session_save_path($session_save_path);
session_start();

// Require login
if (empty($_SESSION['user_id'])) {
    header('Location: auth.php');
    exit;
}

require_once 'common.inc.php';
require_once 'app/PasswordManager.php';
require_once 'app/AuditLogger.php';

$message = '';
$message_type = '';
$user_id = (int)$_SESSION['user_id'];
$username = $_SESSION['user'] ?? '';

// Handle password change submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['change_password'])) {
    $new_password = $_POST['new_password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    
    // Validate inputs
    if (empty($new_password) || empty($confirm_password)) {
        $message = '❌ Both password fields are required.';
        $message_type = 'danger';
    } elseif ($new_password !== $confirm_password) {
        $message = '❌ Passwords do not match.';
        $message_type = 'danger';
    } else {
        // Validate password strength
        $validation = PasswordManager::validatePassword($new_password);
        if (!$validation['valid']) {
            $message = '❌ ' . implode(' ', $validation['errors']);
            $message_type = 'danger';
        } else {
            // Hash and update password
            $password_hash = PasswordManager::hashPassword($new_password);
            
            try {
                if ($db_type === 'sqlite') {
                    $stmt = $connection->prepare(
                        "UPDATE users SET password_hash = ?, must_change_password = 0, password_changed_at = CURRENT_TIMESTAMP WHERE user_id = ?"
                    );
                    $stmt->bindParam(1, $password_hash, PDO::PARAM_STR);
                    $stmt->bindParam(2, $user_id, PDO::PARAM_INT);
                    $success = $stmt->execute();
                    $stmt->closeCursor();
                } else {
                    $stmt = $connection->prepare(
                        "UPDATE users SET password_hash = ?, must_change_password = 0, password_changed_at = NOW() WHERE user_id = ?"
                    );
                    $stmt->bind_param('si', $password_hash, $user_id);
                    $success = $stmt->execute();
                    $stmt->close();
                }
                
                if ($success) {
                    // Log password change
                    $audit = new AuditLogger($connection, $db_type);
                    $audit->logPasswordChange($user_id);
                    
                    $_SESSION['must_change_password'] = 0;
                    $message = '✅ Password changed successfully! Redirecting...';
                    $message_type = 'success';
                    header('Refresh: 2; url=index.php');
                } else {
                    $message = '❌ Failed to update password. Please try again.';
                    $message_type = 'danger';
                }
            } catch (Exception $e) {
                $message = '❌ Error: ' . $e->getMessage();
                $message_type = 'danger';
            }
        }
    }
}

// Check if user still has must_change_password flag
if (empty($_SESSION['must_change_password'])) {
    header('Location: index.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Set New Password - KFMMS</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            min-height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 20px;
        }

        .container {
            display: flex;
            width: 100%;
            max-width: 1000px;
            background: white;
            border-radius: 12px;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.2);
            overflow: hidden;
            min-height: 600px;
        }

        .left-panel {
            flex: 1;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            padding: 60px 40px;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            color: white;
            text-align: center;
        }

        .left-panel img {
            width: 140px;
            height: 140px;
            margin-bottom: 30px;
            animation: slideDown 0.6s ease-out;
            object-fit: contain;
            background: rgba(255, 255, 255, 0.1);
            padding: 12px;
            border-radius: 12px;
            box-shadow: 0 8px 24px rgba(0, 0, 0, 0.2);
            transition: transform 0.3s ease;
        }

        .left-panel img:hover {
            transform: scale(1.05);
        }

        .left-panel h1 {
            font-size: 32px;
            font-weight: 700;
            margin-bottom: 10px;
            letter-spacing: 1px;
        }

        .left-panel p {
            font-size: 14px;
            line-height: 1.6;
            opacity: 0.95;
            margin-bottom: 20px;
        }

        .right-panel {
            flex: 1;
            padding: 60px 50px;
            display: flex;
            flex-direction: column;
            justify-content: center;
            overflow-y: auto;
        }

        .welcome-box {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 24px;
            border-radius: 8px;
            margin-bottom: 24px;
            text-align: center;
        }

        .welcome-box .icon {
            font-size: 40px;
            margin-bottom: 12px;
        }

        .welcome-box h2 {
            font-size: 22px;
            font-weight: 700;
            margin-bottom: 8px;
        }

        .welcome-box .subtitle {
            font-size: 14px;
            opacity: 0.95;
            margin-bottom: 12px;
        }

        .welcome-box .username {
            font-size: 16px;
            font-weight: 600;
            opacity: 1;
        }

        .info-notice {
            background: linear-gradient(135deg, #e3f2fd 0%, #f3e5f5 100%);
            border-left: 4px solid #667eea;
            padding: 16px;
            border-radius: 6px;
            margin-bottom: 24px;
            font-size: 13px;
            color: #1565c0;
            line-height: 1.6;
        }

        .info-notice strong {
            display: block;
            color: #333;
            margin-bottom: 6px;
            font-weight: 600;
        }

        .alert {
            padding: 16px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-size: 14px;
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .alert-danger {
            background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
            color: white;
        }

        .alert-success {
            background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);
            color: white;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            color: #333;
            font-weight: 600;
            font-size: 13px;
            margin-bottom: 8px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .form-group input {
            width: 100%;
            padding: 12px 14px;
            border: 2px solid #e0e0e0;
            border-radius: 6px;
            font-size: 14px;
            transition: all 0.3s ease;
            background-color: #f9f9f9;
        }

        .form-group input:focus {
            outline: none;
            border-color: #667eea;
            background-color: white;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }

        .form-group input::placeholder {
            color: #999;
        }

        .requirements-box {
            background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
            padding: 16px;
            border-radius: 8px;
            margin-bottom: 24px;
        }

        .requirements-box h4 {
            color: #333;
            font-size: 13px;
            font-weight: 600;
            margin-bottom: 12px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .requirement {
            display: flex;
            align-items: center;
            margin-bottom: 8px;
            font-size: 12px;
            color: #555;
            padding: 4px 0;
        }

        .requirement-icon {
            width: 20px;
            height: 20px;
            margin-right: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            color: #ccc;
            font-size: 14px;
            transition: all 0.3s ease;
        }

        .requirement.met .requirement-icon {
            color: #28a745;
        }

        .submit-btn {
            width: 100%;
            padding: 14px 16px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            border-radius: 6px;
            font-size: 14px;
            font-weight: 700;
            cursor: pointer;
            transition: all 0.3s ease;
            text-transform: uppercase;
            letter-spacing: 1px;
            margin-top: 10px;
        }

        .submit-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(102, 126, 234, 0.3);
        }

        .submit-btn:active {
            transform: translateY(0);
        }

        @keyframes slideDown {
            from {
                opacity: 0;
                transform: translateY(-20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        @media (max-width: 768px) {
            .container {
                flex-direction: column;
                min-height: auto;
            }

            .left-panel {
                padding: 40px 30px;
                min-height: 280px;
            }

            .right-panel {
                padding: 40px 30px;
            }

            .left-panel img {
                width: 110px;
                height: 110px;
            }

            .left-panel h1 {
                font-size: 24px;
            }

            .left-panel p {
                font-size: 13px;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <!-- LEFT PANEL: BRANDING -->
        <div class="left-panel">
            <img src="images/kimage.png" alt="KFMMS Logo">
            <h1>KFMMS</h1>
            <p>Computerized Maintenance Management System</p>
            <p style="margin-top: 20px; font-size: 13px; opacity: 0.85;">Secure your account with a strong password to continue.</p>
        </div>

        <!-- RIGHT PANEL: FORM -->
        <div class="right-panel">
            <!-- WELCOME BOX -->
            <div class="welcome-box">
                <div class="icon">🔒</div>
                <h2>Change Your Password</h2>
                <div class="subtitle">First-Time Login - Set Your Permanent Password</div>
                <div class="username">Welcome, <?php echo htmlspecialchars($username); ?>!</div>
            </div>

            <!-- INFO NOTICE -->
            <div class="info-notice">
                <strong>ℹ️ Important Notice:</strong>
                You must set a new password before you can access the system. This temporary password was generated for your security and must be changed now.
            </div>

            <!-- ALERTS -->
            <?php if (!empty($message)): ?>
                <div class="alert alert-<?php echo htmlspecialchars($message_type); ?>">
                    <?php echo htmlspecialchars($message); ?>
                </div>
            <?php endif; ?>

            <!-- FORM -->
            <form method="POST" action="force_password_change.php">
                <div class="form-group">
                    <label for="new_password">New Password</label>
                    <input 
                        type="password" 
                        id="new_password" 
                        name="new_password" 
                        placeholder="Enter a new password"
                        required
                        autofocus
                    >
                </div>

                <div class="form-group">
                    <label for="confirm_password">Confirm Password</label>
                    <input 
                        type="password" 
                        id="confirm_password" 
                        name="confirm_password" 
                        placeholder="Re-enter your password"
                        required
                    >
                </div>

                <!-- PASSWORD REQUIREMENTS -->
                <div class="requirements-box">
                    <h4>Password Requirements:</h4>
                    <div class="requirement" id="req-length">
                        <span class="requirement-icon">○</span>
                        <span>At least 8 characters</span>
                    </div>
                    <div class="requirement" id="req-upper">
                        <span class="requirement-icon">○</span>
                        <span>At least one uppercase letter (A-Z)</span>
                    </div>
                    <div class="requirement" id="req-lower">
                        <span class="requirement-icon">○</span>
                        <span>At least one lowercase letter (a-z)</span>
                    </div>
                    <div class="requirement" id="req-number">
                        <span class="requirement-icon">○</span>
                        <span>At least one number (0-9)</span>
                    </div>
                    <div class="requirement" id="req-special">
                        <span class="requirement-icon">○</span>
                        <span>At least one special character (!@#$%^&*)</span>
                    </div>
                </div>

                <input type="hidden" name="change_password" value="1">
                <button type="submit" class="submit-btn">Update Password</button>
            </form>
        </div>
    </div>

    <script>
        const passwordInput = document.getElementById('new_password');
        const requirements = {
            'req-length': (pwd) => pwd.length >= 8,
            'req-upper': (pwd) => /[A-Z]/.test(pwd),
            'req-lower': (pwd) => /[a-z]/.test(pwd),
            'req-number': (pwd) => /[0-9]/.test(pwd),
            'req-special': (pwd) => /[!@#$%^&*]/.test(pwd)
        };

        passwordInput.addEventListener('input', function() {
            Object.entries(requirements).forEach(([id, test]) => {
                const elem = document.getElementById(id);
                const icon = elem.querySelector('.requirement-icon');
                
                if (test(this.value)) {
                    elem.classList.add('met');
                    icon.textContent = '✓';
                } else {
                    elem.classList.remove('met');
                    icon.textContent = '○';
                }
            });
        });
    </script>
</body>
</html>
