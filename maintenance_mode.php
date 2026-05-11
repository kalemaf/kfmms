<?php
/**
 * Maintenance Mode Handler
 * Toggle maintenance mode on/off and display maintenance landing page
 */

require_once 'config.inc.php';
if (session_status() === PHP_SESSION_NONE) {
    session_save_path($session_save_path);
    session_start();
}

$maintenance_file = __DIR__ . '/maintenance.flag';
$is_active = file_exists($maintenance_file);

// Helper to determine if current session belongs to admin or developer
function isAdminOrDeveloper() {
    $role = strtolower(trim($_SESSION['role'] ?? ''));
    $group = strtolower(trim($_SESSION['group'] ?? ''));
    $user = strtolower(trim($_SESSION['user'] ?? ''));
    $email = strtolower(trim($_SESSION['email'] ?? ''));
    $allowed_emails = ['kalemaf876@gmail.com'];

    return in_array($role, ['admin', 'developer'], true)
        || in_array($group, ['admin', 'developer'], true)
        || $user === 'developer'
        || in_array($email, $allowed_emails, true);
}

// Handle toggle request
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'toggle') {
    if (!isAdminOrDeveloper()) {
        $_SESSION['message'] = 'Access denied. Admin or Developer only.';
        $_SESSION['message_type'] = 'error';
        header('Location: admin_roles.php');
        exit;
    }

    if ($is_active) {
        // Disable maintenance mode
        unlink($maintenance_file);
        $_SESSION['message'] = 'Maintenance mode disabled. System back online.';
        $_SESSION['message_type'] = 'success';
        $is_active = false;
    } else {
        // Enable maintenance mode
        $maintenance_data = [
            'enabled_at' => date('Y-m-d H:i:s'),
            'enabled_by' => $_SESSION['user'] ?? 'Unknown',
            'message' => $_POST['message'] ?? 'System is currently under maintenance. Please check back soon.'
        ];
        file_put_contents($maintenance_file, json_encode($maintenance_data, JSON_PRETTY_PRINT));
        $_SESSION['message'] = 'Maintenance mode enabled. Users will see maintenance page.';
        $_SESSION['message_type'] = 'success';
        $is_active = true;
    }

    header('Location: admin_roles.php?tab=system');
    exit;
}

// If maintenance is active and user is not admin/developer, show maintenance page
if ($is_active && $connection) {
    if (!isAdminOrDeveloper()) {
        // Display maintenance page
        $maintenance_data = json_decode(file_get_contents($maintenance_file), true);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>System Maintenance</title>
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
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        
        .maintenance-container {
            background: white;
            border-radius: 20px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            max-width: 600px;
            width: 100%;
            padding: 60px 40px;
            text-align: center;
            animation: slideUp 0.5s ease-out;
        }
        
        @keyframes slideUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        .maintenance-icon {
            font-size: 64px;
            margin-bottom: 20px;
            animation: rotate 3s linear infinite;
        }
        
        @keyframes rotate {
            from { transform: rotate(0deg); }
            to { transform: rotate(360deg); }
        }
        
        h1 {
            color: #333;
            margin-bottom: 15px;
            font-size: 32px;
        }
        
        .message {
            color: #666;
            font-size: 18px;
            line-height: 1.6;
            margin-bottom: 30px;
        }
        
        .timeline {
            text-align: left;
            background: #f8f9fa;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 30px;
            border-left: 4px solid #667eea;
        }
        
        .timeline-item {
            margin: 10px 0;
            color: #666;
            font-size: 14px;
        }
        
        .timeline-label {
            color: #999;
            font-weight: 600;
            text-transform: uppercase;
            font-size: 12px;
        }
        
        .subscribe-section {
            background: #f0f4ff;
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 30px;
        }
        
        .subscribe-section p {
            color: #666;
            margin-bottom: 15px;
            font-size: 14px;
        }
        
        .email-input {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
            justify-content: center;
        }
        
        .email-input input {
            flex: 1;
            min-width: 200px;
            padding: 10px 15px;
            border: 1px solid #ddd;
            border-radius: 5px;
            font-size: 14px;
        }
        
        .email-input button {
            padding: 10px 20px;
            background: #667eea;
            color: white;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-weight: 600;
            transition: background 0.3s;
        }
        
        .email-input button:hover {
            background: #5568d3;
        }
        
        .status-indicator {
            display: inline-block;
            width: 12px;
            height: 12px;
            background: #ffc107;
            border-radius: 50%;
            margin-right: 8px;
            animation: pulse 2s infinite;
        }
        
        @keyframes pulse {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.5; }
        }
        
        .footer {
            color: #999;
            font-size: 13px;
            margin-top: 30px;
            padding-top: 20px;
            border-top: 1px solid #eee;
        }
        
        .login-link {
            color: #667eea;
            text-decoration: none;
            font-weight: 600;
        }
        
        .login-link:hover {
            text-decoration: underline;
        }
    </style>
</head>
<body>
    <div class="maintenance-container">
        <div class="maintenance-icon">🔧</div>
        
        <h1>Under Maintenance</h1>
        
        <div class="message">
            <span class="status-indicator"></span>
            <?php echo htmlspecialchars($maintenance_data['message'] ?? 'System is currently under maintenance. Please check back soon.'); ?>
        </div>
        
        <div class="timeline">
            <div class="timeline-item">
                <div class="timeline-label">Maintenance Started</div>
                <div><?php echo date('l, F j, Y \a\t g:i A', strtotime($maintenance_data['enabled_at'])); ?></div>
            </div>
        </div>
        
        <div class="subscribe-section">
            <p>💌 Get notified when we're back online</p>
            <div class="email-input">
                <input type="email" placeholder="Enter your email" id="email-notify">
                <button onclick="notifyMe()">Notify Me</button>
            </div>
        </div>
        
        <div class="footer">
            <p>Having issues? <a href="javascript:void(0)" onclick="document.getElementById('contact').style.display='block'" style="color: #667eea; cursor: pointer;">Contact Support</a></p>
            <p>If you are the developer or an administrator, <a href="auth.php?logout=1&redirect=login" class="login-link">log out and return to the login page</a> so you can re-enter your developer credentials.</p>
            <p style="margin-top: 10px; font-size: 12px;">Expected to be back: Coming soon</p>
        </div>
    </div>

    <script>
        function notifyMe() {
            const email = document.getElementById('email-notify').value;
            if (!email) {
                alert('Please enter your email');
                return;
            }
            
            if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) {
                alert('Please enter a valid email');
                return;
            }
            
            // Store in localStorage for demo purposes
            let notifications = JSON.parse(localStorage.getItem('maintenance_notifications') || '[]');
            if (!notifications.includes(email)) {
                notifications.push(email);
                localStorage.setItem('maintenance_notifications', JSON.stringify(notifications));
            }
            
            alert('✓ You will be notified when we\'re back online');
            document.getElementById('email-notify').value = '';
        }
    </script>
</body>
</html>
<?php
        exit;
    }
}
?>
