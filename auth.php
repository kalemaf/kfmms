<?php

/**
 * Simple Authentication Page for CMMS
 * Supports login and logout with optional DB user lookup.
 */

// Allow framing for development (localhost/127.0.0.1)
$is_localhost = isset($_SERVER['HTTP_HOST']) && preg_match('/^(localhost|127\.0\.0\.1)(:\d+)?$/', $_SERVER['HTTP_HOST']);
if ($is_localhost) {
    // In development, allow framing from localhost
    header('Access-Control-Allow-Origin: http://127.0.0.1:8000', false);
    header('Access-Control-Allow-Credentials: true', false);
} else {
    // In production, use DENY to prevent clickjacking
    header('X-Frame-Options: DENY', false);
}

// Suppress display of errors to prevent breaking page layout
error_reporting(E_ALL);
ini_set('display_errors', '0');  // Don't display errors on page
ini_set('log_errors', '1');      // Log errors to error_log

require_once 'config.inc.php';

session_save_path($session_save_path);
require_once 'common.inc.php';
require_once 'app/AuditLogger.php';

/**
 * Check and enforce login rate limiting
 * Blocks after 5 failed attempts for 15 minutes
 */
function check_login_rate_limit($username) {
    $rate_limit_file = sys_get_temp_dir() . '/cmms_login_' . md5($username) . '.txt';
    $max_attempts = 5;
    $lockout_duration = 900; // 15 minutes
    $now = time();
    
    if (file_exists($rate_limit_file)) {
        $data = json_decode(file_get_contents($rate_limit_file), true);
        if ($data['locked_until'] > $now) {
            return ['locked' => true, 'until' => $data['locked_until']];
        } else {
            // Lockout expired, reset
            @unlink($rate_limit_file);
            return ['locked' => false];
        }
    }
    return ['locked' => false];
}

/**
 * Record failed login attempt
 */
function record_failed_login($username) {
    $rate_limit_file = sys_get_temp_dir() . '/cmms_login_' . md5($username) . '.txt';
    $max_attempts = 5;
    $lockout_duration = 900; // 15 minutes
    $now = time();
    
    $data = ['attempts' => 1, 'first_attempt' => $now, 'locked_until' => 0];
    if (file_exists($rate_limit_file)) {
        $data = json_decode(file_get_contents($rate_limit_file), true);
        $data['attempts']++;
        if ($data['attempts'] >= $max_attempts) {
            $data['locked_until'] = $now + $lockout_duration;
        }
    }
    file_put_contents($rate_limit_file, json_encode($data));
}

/**
 * Clear failed login attempts on success
 */
function clear_login_attempts($username) {
    $rate_limit_file = sys_get_temp_dir() . '/cmms_login_' . md5($username) . '.txt';
    @unlink($rate_limit_file);
}

// Logout action
if (isset($_GET['logout']) && $_GET['logout']) {
    $logout_user = $_SESSION['user'] ?? 'unknown';
    $logout_user_id = $_SESSION['user_id'] ?? null;
    
    // Log logout event
    if (isset($connection) && is_object($connection)) {
        $audit = new AuditLogger($connection, $db_type);
        $audit->logLogout($logout_user_id, $logout_user);
    }
    
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $params['path'], $params['domain'], $params['secure'], $params['httponly']
        );
    }
    session_destroy();
    if (!empty($_GET['redirect']) && $_GET['redirect'] === 'login') {
        header('Location: auth.php');
    } else {
        header('Location: index.php');
    }
    exit;
}

// If already logged in, go to main page
if (!empty($_SESSION['user'])) {
    header('Location: index.php');
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = trim($_POST['password'] ?? '');
    $csrf_token = $_POST['csrf_token'] ?? '';
    $login_attempts = $_SESSION['login_attempts'] ?? 0;
    $login_lock_until = $_SESSION['login_lock_until'] ?? 0;
    $now = time();

    // Check rate limiting (blocks after 5 failed attempts for 15 minutes)
    $rate_limit = check_login_rate_limit($username);
    if ($rate_limit['locked']) {
        $waitMinutes = ceil(($rate_limit['until'] - $now) / 60);
        $error = "Too many failed login attempts. Try again in {$waitMinutes} minute(s).";
    } elseif ($login_lock_until > $now) {
        $waitMinutes = ceil(($login_lock_until - $now) / 60);
        $error = "Too many failed login attempts. Try again in {$waitMinutes} minute(s).";
    } elseif (!verify_csrf_token($csrf_token)) {
        $error = 'Invalid request. Please refresh the page and try again.';
    } elseif ($username === '' || $password === '') {
        $error = 'Please enter username and password.';
    } else {
        // Try database users table - search by email OR username
        if (isset($connection) && is_object($connection)) {
            // Search by email first, then by username
            $stmt = $connection->prepare("SELECT user_id, username, email, password_hash, role, is_active, is_locked, phone, country_code, company_id, password_change_required, must_change_password FROM users WHERE email = ? OR username = ? LIMIT 1");
            $stmt->bind_param('ss', $username, $username);
            $stmt->execute();
            $result = $stmt->get_result();

            if ($row = $result->fetch_assoc()) {
                // Check if user account is locked/inactive
                if (!$row['is_active'] || $row['is_locked']) {
                    $error = 'Account locked or inactive.';
                    // Log failed login - account locked
                    if (isset($connection) && is_object($connection)) {
                        $audit = new AuditLogger($connection, $db_type);
                        $audit->logLoginAttempt($username, false, $row['user_id'], 'Account locked or inactive');
                    }
                }
                // Check if user company is locked
                else if (!empty($row['company_id'])) {
                    $ctrl_stmt = $connection->prepare("SELECT system_locked, lock_reason FROM system_control WHERE company_id = ? LIMIT 1");
                    if ($ctrl_stmt) {
                        $ctrl_stmt->bind_param('i', $row['company_id']);
                        $ctrl_stmt->execute();
                        $ctrl_result = $ctrl_stmt->get_result();
                        if ($ctrl_row = $ctrl_result->fetch_assoc()) {
                            if ($ctrl_row['system_locked']) {
                                $error = 'System is locked for your organization. Reason: ' . ($ctrl_row['lock_reason'] ?: 'Administrative lock');
                                // Log failed login - organization locked
                                $audit = new AuditLogger($connection, $db_type);
                                $audit->logLoginAttempt($username, false, $row['user_id'], 'Organization system locked');
                                $ctrl_stmt->close();
                                $stmt->close();
                                session_write_close();
                            } else {
                                $ctrl_stmt->close();
                                $error = '';
                            }
                        } else {
                            $ctrl_stmt->close();
                            $error = '';
                        }
                    }
                }
                if ($error) {
                    // Account or company locked
                } else {
                    $valid = false;
                    if (!empty($row['password_hash']) && password_verify($password, $row['password_hash'])) {
                        $valid = true;
                    }

                    if ($valid) {
                        $updateLogin = $connection->prepare("UPDATE users SET last_login_at = " . get_current_timestamp_sql() . " WHERE user_id = ?");
                        if ($updateLogin) {
                            $updateLogin->bind_param('i', $row['user_id']);
                            $updateLogin->execute();
                            $updateLogin->close();
                        }

                        session_regenerate_id(true);
                        unset($_SESSION['login_attempts'], $_SESSION['login_lock_until']);
                        $_SESSION['user'] = $row['username'];
                        $_SESSION['user_id'] = $row['user_id'];
                        $_SESSION['email'] = $row['email'];
                        $_SESSION['phone'] = $row['phone'];
                        $_SESSION['country_code'] = $row['country_code'] ?? '+256';
                        $_SESSION['company_id'] = $row['company_id'] ?? 0;
                        $_SESSION['tenant_id'] = (int)($row['company_id'] ?? 0);
                        $_SESSION['role'] = strtolower($row['role']);
                        $_SESSION['group'] = strtolower($row['role']);
                        $_SESSION['permissions'] = [];
                        $_SESSION['password_change_required'] = !empty($row['password_change_required']) ? 1 : 0;
                        $_SESSION['must_change_password'] = !empty($row['must_change_password']) ? 1 : 0;
                        
                        // Log successful login
                        if (isset($connection) && is_object($connection)) {
                            $audit = new AuditLogger($connection, $db_type);
                            $audit->logLoginAttempt($row['username'], true, $row['user_id'], '');
                        }
                        
                        // Clear login rate limiting on success
                        clear_login_attempts($username);
                        
                        if (!empty($row['must_change_password'])) {
                            session_write_close(); // Ensure session is saved before redirect
                            header('Location: force_password_change.php');
                            exit;
                        }
                        
                        if (!empty($row['password_change_required'])) {
                            header('Location: change_password.php');
                            exit;
                        }
                        
                        $maintenance_file = __DIR__ . '/maintenance.flag';
                        $user_role = $_SESSION['role'];
                        $is_admin = ($user_role === 'admin' || $user_role === 'developer');
                        if (file_exists($maintenance_file) && !$is_admin) {
                            header('Location: maintenance_mode.php');
                        } else {
                            header('Location: index.php');
                        }
                        exit;
                    } else {
                        $login_attempts++;
                        $_SESSION['login_attempts'] = $login_attempts;
                        record_failed_login($username);  // Record failed attempt for rate limiting
                        if ($login_attempts >= 5) {
                            $_SESSION['login_lock_until'] = time() + 900;
                            $error = 'Too many failed login attempts. Please wait 15 minutes and try again.';
                        } else {
                            $error = 'Invalid username or password.';
                        }
                        // Log failed login - invalid password
                        if (isset($connection) && is_object($connection)) {
                            $audit = new AuditLogger($connection, $db_type);
                            $audit->logLoginAttempt($username, false, $row['user_id'] ?? null, 'Invalid password');
                        }
                    }
                }
            } else {
                $login_attempts++;
                $_SESSION['login_attempts'] = $login_attempts;
                record_failed_login($username);  // Record failed attempt for rate limiting
                if ($login_attempts >= 5) {
                    $_SESSION['login_lock_until'] = time() + 900;
                    $error = 'Too many failed login attempts. Please wait 15 minutes and try again.';
                } else {
                    $error = 'Invalid username or password.';
                }
                // Log failed login - user not found
                if (isset($connection) && is_object($connection)) {
                    $audit = new AuditLogger($connection, $db_type);
                    $audit->logLoginAttempt($username, false, null, 'User not found');
                }
            }

            $stmt->close();
        } else {
            $error = 'Unable to access authentication database.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>KFMMS - Login</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Playfair+Display:wght@400;600;700&display=swap" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        :root {
            --primary-gradient: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            --secondary-gradient: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
            --accent-gradient: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);
            --success-gradient: linear-gradient(135deg, #43e97b 0%, #38f9d7 100%);
            --warning-gradient: linear-gradient(135deg, #fa709a 0%, #fee140 100%);
            --glass-bg: rgba(255, 255, 255, 0.95);
            --glass-border: rgba(255, 255, 255, 0.2);
            --shadow-light: 0 8px 32px rgba(0, 0, 0, 0.1);
            --shadow-medium: 0 20px 60px rgba(0, 0, 0, 0.15);
            --shadow-heavy: 0 40px 100px rgba(0, 0, 0, 0.25);
        }

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
            background: 
                radial-gradient(circle at 20% 50%, rgba(102, 126, 234, 0.1) 0%, transparent 50%),
                radial-gradient(circle at 80% 20%, rgba(118, 75, 162, 0.1) 0%, transparent 50%),
                radial-gradient(circle at 40% 80%, rgba(240, 147, 251, 0.05) 0%, transparent 50%),
                linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            position: relative;
            overflow-x: hidden;
        }

        body::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: 
                url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100"><defs><pattern id="grain" width="100" height="100" patternUnits="userSpaceOnUse"><circle cx="25" cy="25" r="1" fill="rgba(0,0,0,0.03)"/><circle cx="75" cy="75" r="1" fill="rgba(0,0,0,0.03)"/><circle cx="50" cy="10" r="0.5" fill="rgba(0,0,0,0.02)"/><circle cx="10" cy="50" r="0.5" fill="rgba(0,0,0,0.02)"/><circle cx="90" cy="30" r="0.5" fill="rgba(0,0,0,0.02)"/></pattern></defs><rect width="100" height="100" fill="url(%23grain)"/></svg>');
            animation: grain 8s steps(10) infinite;
            pointer-events: none;
        }

        .login-wrapper {
            display: flex;
            background: var(--glass-bg);
            backdrop-filter: blur(20px);
            border: 1px solid var(--glass-border);
            border-radius: 24px;
            box-shadow: var(--shadow-heavy);
            overflow: hidden;
            max-width: 1000px;
            width: 90%;
            min-height: 600px;
            position: relative;
            z-index: 1;
        }

        .login-wrapper::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: linear-gradient(135deg, rgba(255,255,255,0.1) 0%, rgba(255,255,255,0.05) 100%);
            pointer-events: none;
        }

        .login-brand {
            flex: 1;
            background: var(--primary-gradient);
            padding: 60px 50px;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            color: white;
            text-align: center;
            position: relative;
            overflow: hidden;
        }

        .login-brand::before {
            content: '';
            position: absolute;
            top: -50%;
            left: -50%;
            width: 200%;
            height: 200%;
            background: 
                radial-gradient(circle at 30% 20%, rgba(255,255,255,0.1) 0%, transparent 50%),
                radial-gradient(circle at 70% 80%, rgba(255,255,255,0.1) 0%, transparent 50%);
            animation: float 15s ease-in-out infinite;
        }

        .login-brand img {
            max-width: 140px;
            margin-bottom: 30px;
            filter: drop-shadow(0 8px 16px rgba(0,0,0,0.2));
            animation: slideIn 0.8s ease-out;
            position: relative;
            z-index: 2;
        }

        .login-brand h1 {
            font-family: 'Playfair Display', serif;
            font-size: 36px;
            font-weight: 700;
            margin-bottom: 16px;
            letter-spacing: 1px;
            background: linear-gradient(135deg, #ffffff 0%, #f0f8ff 50%, #ffffff 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            text-shadow: 0 2px 20px rgba(0,0,0,0.3);
            position: relative;
            z-index: 2;
        }

        .login-brand p {
            font-size: 16px;
            opacity: 0.95;
            line-height: 1.6;
            max-width: 300px;
            position: relative;
            z-index: 2;
        }

        .login-brand .brand-subtitle {
            margin-top: 24px;
            font-size: 14px;
            opacity: 0.8;
            line-height: 1.5;
            position: relative;
            z-index: 2;
        }

        .login-brand .brand-subtitle::before {
            content: '';
            display: block;
            width: 40px;
            height: 2px;
            background: rgba(255,255,255,0.6);
            margin: 0 auto 16px;
            border-radius: 1px;
        }

        .website-link {
            margin-top: 16px;
            text-align: center;
        }

        .website-link a {
            color: rgba(255, 255, 255, 0.8);
            text-decoration: none;
            font-size: 14px;
            font-weight: 500;
            transition: all 0.3s ease;
            border-bottom: 1px solid transparent;
        }

        .website-link a:hover {
            color: #ffffff;
            border-bottom-color: rgba(255, 255, 255, 0.6);
            text-shadow: 0 0 10px rgba(255, 255, 255, 0.5);
        }

        .social-media-icons {
            margin-top: 20px;
            display: flex;
            justify-content: center;
            gap: 20px;
        }

        .social-icon {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 40px;
            height: 40px;
            border-radius: 50%;
            text-decoration: none;
            font-size: 18px;
            transition: all 0.3s ease;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.2);
        }

        .social-icon:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.3);
        }

        .social-icon span {
            color: white !important;
            font-size: 14px;
            font-weight: bold;
        }

        .social-icon.whatsapp {
            background: linear-gradient(135deg, #25d366, #128c7e);
        }

        .social-icon.whatsapp:hover {
            background: linear-gradient(135deg, #128c7e, #075e54);
        }

        .social-icon.facebook {
            background: linear-gradient(135deg, #1877f2, #42a5f5);
        }

        .social-icon.facebook:hover {
            background: linear-gradient(135deg, #42a5f5, #1976d2);
        }

        .social-icon.twitter {
            background: linear-gradient(135deg, #000000, #333333);
        }

        .social-icon.twitter:hover {
            background: linear-gradient(135deg, #333333, #000000);
        }

        .social-icon.instagram {
            background: linear-gradient(135deg, #f09433 0%, #e6683c 25%, #dc2743 50%, #cc2366 75%, #bc1888 100%);
        }

        .social-icon.instagram:hover {
            background: linear-gradient(135deg, #bc1888 0%, #cc2366 25%, #dc2743 50%, #e6683c 75%, #f09433 100%);
        }

        .social-icon.location {
            background: linear-gradient(135deg, #ff6b6b, #ee5a24);
        }

        .social-icon.location:hover {
            background: linear-gradient(135deg, #ee5a24, #ff4757);
        }

        .login-form-container {
            flex: 1;
            padding: 60px 50px;
            display: flex;
            flex-direction: column;
            justify-content: center;
            position: relative;
            z-index: 2;
        }

        .login-form-header {
            margin-bottom: 40px;
            text-align: center;
        }

        .login-form-header h2 {
            color: #1a202c;
            font-family: 'Playfair Display', serif;
            font-size: 32px;
            font-weight: 600;
            margin-bottom: 12px;
            background: linear-gradient(135deg, #2d3748 0%, #4a5568 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .login-form-header p {
            color: #718096;
            font-size: 16px;
            line-height: 1.5;
            font-weight: 400;
        }

        .form-group {
            margin-bottom: 28px;
            position: relative;
        }

        .form-group label {
            display: block;
            color: #2d3748;
            font-weight: 600;
            font-size: 14px;
            margin-bottom: 10px;
            text-transform: uppercase;
            letter-spacing: 0.8px;
            position: relative;
        }

        .form-group label::after {
            content: '';
            position: absolute;
            bottom: -4px;
            left: 0;
            width: 24px;
            height: 2px;
            background: var(--accent-gradient);
            border-radius: 1px;
        }

        .form-group input {
            width: 100%;
            padding: 16px 20px;
            border: 2px solid #e2e8f0;
            border-radius: 12px;
            font-size: 16px;
            font-family: inherit;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            background: rgba(255, 255, 255, 0.8);
            backdrop-filter: blur(10px);
            position: relative;
        }

        .form-group input:focus {
            outline: none;
            border-color: #667eea;
            background: white;
            box-shadow: 
                0 0 0 4px rgba(102, 126, 234, 0.1),
                0 8px 25px rgba(102, 126, 234, 0.15);
            transform: translateY(-2px);
        }

        .form-group input::placeholder {
            color: #a0aec0;
            font-weight: 400;
        }

        .form-group input:focus::placeholder {
            color: #cbd5e0;
        }

        .form-group .input-icon {
            position: absolute;
            right: 16px;
            top: 50%;
            transform: translateY(-50%);
            color: #a0aec0;
            font-size: 18px;
            transition: color 0.3s ease;
        }

        .form-group input:focus + .input-icon {
            color: #667eea;
        }

        .error-message {
            background: var(--warning-gradient);
            color: white;
            padding: 16px 20px;
            border-radius: 12px;
            margin-bottom: 24px;
            font-size: 14px;
            display: flex;
            align-items: flex-start;
            gap: 12px;
            box-shadow: 0 4px 15px rgba(245, 87, 108, 0.2);
            border: 1px solid rgba(255,255,255,0.2);
            backdrop-filter: blur(10px);
        }

        .error-message::before {
            content: "⚠";
            font-size: 20px;
            font-weight: bold;
            flex-shrink: 0;
        }

        .login-button {
            width: 100%;
            padding: 18px 24px;
            background: var(--primary-gradient);
            color: white;
            border: none;
            border-radius: 12px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            text-transform: uppercase;
            letter-spacing: 1px;
            margin-top: 16px;
            position: relative;
            overflow: hidden;
            box-shadow: 0 8px 25px rgba(102, 126, 234, 0.3);
        }

        .login-button::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255,255,255,0.2), transparent);
            transition: left 0.6s ease;
        }

        .login-button:hover {
            transform: translateY(-3px);
            box-shadow: 0 15px 35px rgba(102, 126, 234, 0.4);
        }

        .login-button:hover::before {
            left: 100%;
        }

        .login-button:active {
            transform: translateY(-1px);
        }

        .login-button i {
            margin-right: 8px;
        }

        @media (max-width: 768px) {
            .login-wrapper {
                flex-direction: column;
                width: 95%;
                min-height: auto;
            }

            .login-brand {
                padding: 40px 30px;
                flex: none;
                min-height: 300px;
            }

            .login-form-container {
                padding: 40px 30px;
            }

            .login-brand img {
                max-width: 100px;
            }

            .login-brand h1 {
                font-size: 28px;
            }

            .login-form-header h2 {
                font-size: 24px;
            }
        }

        @keyframes slideIn {
            from {
                opacity: 0;
                transform: translateY(-30px) scale(0.9);
            }
            to {
                opacity: 1;
                transform: translateY(0) scale(1);
            }
        }

        @keyframes float {
            0%, 100% { transform: translateY(0px) rotate(0deg); }
            33% { transform: translateY(-20px) rotate(2deg); }
            66% { transform: translateY(10px) rotate(-1deg); }
        }

        @keyframes grain {
            0%, 100% { transform: translate(0, 0); }
            10% { transform: translate(-5%, -10%); }
            20% { transform: translate(-15%, 5%); }
            30% { transform: translate(7%, -25%); }
            40% { transform: translate(-5%, 25%); }
            50% { transform: translate(-15%, 10%); }
            60% { transform: translate(15%, 0%); }
            70% { transform: translate(0%, 15%); }
            80% { transform: translate(3%, -10%); }
            90% { transform: translate(-10%, 5%); }
        }
    </style>
</head>
<body>
    <div class="login-wrapper">
        <div class="login-brand">
            <img src="images/kimage.png" alt="KFMMS Logo">
            <h1>KFMMS</h1>
            <p>Computerized Maintenance Management System</p>
            <div class="brand-subtitle">
                Streamline your equipment maintenance with advanced scheduling and tracking.
                <div class="website-link">
                    <a href="https://www.kfmms.com" target="_blank">www.kfmms.com</a>
                </div>
                <div class="social-media-icons">
                    <a href="#" class="social-icon whatsapp" title="WhatsApp"><span>WA</span></a>
                    <a href="#" class="social-icon facebook" title="Facebook"><span>FB</span></a>
                    <a href="#" class="social-icon twitter" title="X (Twitter)"><span>X</span></a>
                    <a href="#" class="social-icon instagram" title="Instagram"><span>IG</span></a>
                    <a href="#" class="social-icon location" title="Location"><span>📍</span></a>
                </div>
            </div>
        </div>

        <div class="login-form-container">
            <div class="login-form-header">
                <h2>Welcome Back</h2>
                <p>Enter your credentials to access the system</p>
            </div>

            <?php if ($error): ?>
                <div class="error-message">
                    <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>

            <form method="POST" action="auth.php">
                <div class="form-group">
                    <label for="username">Username or Email</label>
                    <input 
                        type="text" 
                        id="username" 
                        name="username" 
                        placeholder="Enter your username or email"
                        value="<?php echo htmlspecialchars($username ?? ''); ?>"
                        required
                        autofocus
                    >
                    <i class="fas fa-user input-icon"></i>
                </div>
                
                <div class="form-group">
                    <label for="password">Password</label>
                    <input 
                        type="password" 
                        id="password" 
                        name="password" 
                        placeholder="Enter your password"
                        required
                    >
                    <i class="fas fa-lock input-icon"></i>
                </div>

                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(generate_csrf_token()); ?>">

                <button type="submit" class="login-button">
                    <i class="fas fa-sign-in-alt"></i> Sign In
                </button>
            </form>
        </div>
    </div>
</body>
</html>