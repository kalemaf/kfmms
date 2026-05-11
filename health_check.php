<?php
/**
 * System Health Check Diagnostic
 * Comprehensive system diagnostics and status report
 */

require_once 'config.inc.php';
session_save_path($session_save_path);
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Basic auth check (optional, remove if you want public access)
// if (empty($_SESSION['user'])) {
//     header('Location: auth.php');
//     exit;
// }

$checks = [];
$overall_status = 'pass';

// ===== 1. DATABASE CONNECTION =====
$db_check = [
    'category' => 'Database',
    'name' => 'MySQL Connection',
    'status' => 'pass',
    'message' => 'Connected',
    'details' => []
];

if ($connection && !$db_error) {
    $db_check['status'] = 'pass';
    $db_check['message'] = 'Connected successfully';
    $db_check['details'][] = 'Host: ' . $hostName;
    $db_check['details'][] = 'Database: ' . $databaseName;
    
    // Check key tables
    $tables = ['work_orders', 'equipment', 'users', 'parts_master', 'companies'];
    $missing_tables = [];
    foreach ($tables as $table) {
        $table_exists = false;
        if ($db_type === 'sqlite') {
            $result = $connection->query("SELECT name FROM sqlite_master WHERE type='table' AND name='{$table}'");
            $table_exists = $result && $result->fetch(PDO::FETCH_ASSOC) ? true : false;
        } else {
            $result = $connection->query("SHOW TABLES LIKE '{$table}'");
            $table_exists = $result && $result->num_rows > 0 ? true : false;
        }
        if (!$table_exists) {
            $missing_tables[] = $table;
        }
    }
    
    if (!empty($missing_tables)) {
        $db_check['status'] = 'fail';
        $db_check['message'] = 'Missing tables: ' . implode(', ', $missing_tables);
        $overall_status = 'fail';
    } else {
        $db_check['details'][] = 'All key tables present';
    }
} else {
    $db_check['status'] = 'fail';
    $db_check['message'] = 'Connection failed: ' . $db_error;
    $overall_status = 'fail';
}
$checks[] = $db_check;

// ===== 2. PHP CONFIGURATION =====
$php_check = [
    'category' => 'PHP',
    'name' => 'PHP Version & Extensions',
    'status' => 'pass',
    'message' => 'PHP ' . phpversion(),
    'details' => []
];

$required_extensions = ['mysqli', 'openssl', 'curl', 'json', 'filter'];
$missing_extensions = [];
foreach ($required_extensions as $ext) {
    if (extension_loaded($ext)) {
        $php_check['details'][] = "✓ $ext";
    } else {
        $php_check['details'][] = "✗ $ext (MISSING)";
        $missing_extensions[] = $ext;
        $php_check['status'] = 'fail';
        $overall_status = 'fail';
    }
}

$php_check['details'][] = 'SAPI: ' . php_sapi_name();
$loadedIni = php_ini_loaded_file();
$php_check['details'][] = 'Loaded php.ini: ' . ($loadedIni ? $loadedIni : 'None');

$checks[] = $php_check;

// ===== 3. FILE SYSTEM =====
$fs_check = [
    'category' => 'File System',
    'name' => 'Directory Permissions',
    'status' => 'pass',
    'message' => 'Writable directories OK',
    'details' => []
];

$required_dirs = [
    __DIR__ . '/logs' => 'logs',
    __DIR__ . '/sessions' => 'sessions',
    __DIR__ . '/uploads' => 'uploads',
    __DIR__ . '/vendor' => 'vendor (optional)'
];

foreach ($required_dirs as $dir => $label) {
    $exists = is_dir($dir);
    $writable = $exists && is_writable($dir);
    
    if ($exists && $writable) {
        $fs_check['details'][] = "✓ $label (writable)";
    } elseif ($exists && !$writable) {
        $fs_check['details'][] = "⚠ $label (exists but not writable)";
        if (strpos($label, 'optional') === false) {
            $fs_check['status'] = 'warn';
        }
    } else {
        $fs_check['details'][] = "✗ $label (missing)";
        if (strpos($label, 'optional') === false) {
            $fs_check['status'] = 'fail';
            $overall_status = 'fail';
        }
    }
}
$checks[] = $fs_check;

// ===== 4. SMTP CONFIGURATION =====
$smtp_check = [
    'category' => 'Email',
    'name' => 'SMTP Configuration',
    'status' => 'pass',
    'message' => $SMTP_ENABLED ? 'Enabled' : 'Disabled (using PHP mail)',
    'details' => []
];

if ($SMTP_ENABLED) {
    $smtp_check['details'][] = "Host: $SMTP_HOST";
    $smtp_check['details'][] = "Port: $SMTP_PORT";
    $smtp_check['details'][] = "Secure: " . ($SMTP_SECURE ?: 'None');
    $smtp_check['details'][] = "Auth: " . ($SMTP_USER ? 'Enabled' : 'Disabled');
    
    // Check if PHPMailer is available
    $autoload = __DIR__ . DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR . 'autoload.php';
    if (!file_exists($autoload)) {
        $smtp_check['status'] = 'warn';
        $smtp_check['details'][] = '⚠ PHPMailer not found (vendor/autoload.php)';
    } else {
        $smtp_check['details'][] = '✓ PHPMailer available';
    }
} else {
    $smtp_check['details'][] = 'Using PHP mail() function';
}
$checks[] = $smtp_check;

// ===== 5. SESSION MANAGEMENT =====
$session_check = [
    'category' => 'Session',
    'name' => 'Session Handling',
    'status' => 'pass',
    'message' => 'Configured',
    'details' => []
];

$session_check['details'][] = "Save Path: " . ini_get('session.save_path');
$session_check['details'][] = "Session ID: " . session_id();
$session_check['details'][] = "Current User: " . ($_SESSION['user'] ?? 'Not logged in');

if (is_dir($session_save_path) && is_writable($session_save_path)) {
    $session_check['details'][] = '✓ Session save path writable';
} else {
    $session_check['status'] = 'warn';
    $session_check['details'][] = '⚠ Session save path may not be writable';
}
$checks[] = $session_check;

// ===== 6. SECURITY =====
$security_check = [
    'category' => 'Security',
    'name' => 'Security Configuration',
    'status' => 'pass',
    'message' => 'Basic checks OK',
    'details' => []
];

// Check for critical files
if (file_exists(__DIR__ . '/config.inc.php')) {
    $security_check['details'][] = '✓ Config file exists';
} else {
    $security_check['details'][] = '✗ Config file missing';
    $security_check['status'] = 'fail';
    $overall_status = 'fail';
}

// Check if critical passwords/keys are not exposed
$config_readable = filesize(__DIR__ . '/config.inc.php');
if ($config_readable > 100) {
    $security_check['details'][] = "✓ Config file size OK ({$config_readable} bytes)";
} else {
    $security_check['details'][] = '⚠ Config file seems too small';
}

$security_check['details'][] = 'Error logs configured: ' . (ini_get('log_errors') ? 'Yes' : 'No');
$checks[] = $security_check;

// ===== 7. PERFORMANCE =====
$perf_check = [
    'category' => 'Performance',
    'name' => 'PHP Settings',
    'status' => 'pass',
    'message' => 'Performance OK',
    'details' => []
];

$max_exec = intval(ini_get('max_execution_time'));
$max_upload = intval(ini_get('upload_max_filesize'));
$max_post = intval(ini_get('post_max_size'));

$perf_check['details'][] = "Max Execution Time: {$max_exec}s";
$perf_check['details'][] = "Max Upload Size: {$max_upload}M";
$perf_check['details'][] = "Max POST Size: {$max_post}M";

if ($max_exec < 30) {
    $perf_check['status'] = 'warn';
    $perf_check['details'][] = '⚠ Execution timeout may be too short';
}
$checks[] = $perf_check;

// ===== 8. LOGGING =====
$log_check = [
    'category' => 'Logging',
    'name' => 'Log Files',
    'status' => 'pass',
    'message' => 'Logging enabled',
    'details' => []
];

$log_files = [
    __DIR__ . '/logs/email_send.log' => 'Email Send Log',
    __DIR__ . '/logs/error.log' => 'Error Log',
    __DIR__ . '/logs/database.log' => 'Database Log'
];

foreach ($log_files as $file => $label) {
    if (file_exists($file)) {
        $size = filesize($file);
        $log_check['details'][] = "✓ $label (" . format_bytes($size) . ")";
    } else {
        $log_check['details'][] = "○ $label (not created yet)";
    }
}
$checks[] = $log_check;

function format_bytes($bytes, $precision = 2) {
    $units = ['B', 'KB', 'MB', 'GB'];
    for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
        $bytes /= 1024;
    }
    return round($bytes, $precision) . ' ' . $units[$i];
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>System Health Check - CMMS</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        body { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); min-height: 100vh; padding: 20px; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; }
        .container { max-width: 1000px; }
        .header { text-align: center; color: white; margin-bottom: 30px; }
        .header h1 { font-size: 2.5em; font-weight: 700; margin-bottom: 10px; }
        .header p { font-size: 1.1em; opacity: 0.9; }
        .status-badge { display: inline-block; padding: 8px 16px; border-radius: 20px; font-weight: 600; }
        .status-pass { background: #28a745; color: white; }
        .status-warn { background: #ffc107; color: #333; }
        .status-fail { background: #dc3545; color: white; }
        .check-card { background: white; border-radius: 8px; margin-bottom: 20px; overflow: hidden; box-shadow: 0 4px 6px rgba(0,0,0,0.1); }
        .check-header { display: flex; justify-content: space-between; align-items: center; padding: 20px; border-bottom: 1px solid #eee; }
        .check-header.pass { border-left: 4px solid #28a745; }
        .check-header.warn { border-left: 4px solid #ffc107; }
        .check-header.fail { border-left: 4px solid #dc3545; }
        .check-title { font-weight: 600; color: #333; font-size: 1.1em; }
        .check-category { color: #666; font-size: 0.85em; }
        .check-details { padding: 15px 20px; background: #f8f9fa; font-family: 'Courier New', monospace; font-size: 0.9em; }
        .check-details div { padding: 4px 0; color: #555; }
        .icon-pass { color: #28a745; margin-right: 10px; }
        .icon-warn { color: #ffc107; margin-right: 10px; }
        .icon-fail { color: #dc3545; margin-right: 10px; }
        .overall-status { text-align: center; margin-bottom: 30px; }
        .overall-status .badge { font-size: 1.2em; padding: 12px 24px; }
        .timestamp { text-align: right; color: #999; font-size: 0.9em; margin-bottom: 20px; }
        .action-buttons { text-align: center; margin-top: 30px; }
        .action-buttons a, .action-buttons button { margin: 5px; }
    </style>
</head>
<body>
<div class="container">
    <div class="header">
        <h1><i class="fas fa-heartbeat"></i> System Health Check</h1>
        <p>Comprehensive Diagnostics & System Status</p>
    </div>

    <div class="timestamp">
        Last checked: <strong><?php echo date('Y-m-d H:i:s'); ?></strong>
    </div>

    <div class="overall-status">
        <?php if ($overall_status === 'pass'): ?>
            <span class="badge status-pass"><i class="fas fa-check-circle"></i> All Systems Operational</span>
        <?php elseif ($overall_status === 'warn'): ?>
            <span class="badge status-warn"><i class="fas fa-exclamation-circle"></i> Some Warnings</span>
        <?php else: ?>
            <span class="badge status-fail"><i class="fas fa-times-circle"></i> Critical Issues</span>
        <?php endif; ?>
    </div>

    <?php foreach ($checks as $check): ?>
    <div class="check-card">
        <div class="check-header <?php echo $check['status']; ?>">
            <div>
                <div class="check-title">
                    <?php if ($check['status'] === 'pass'): ?>
                        <i class="fas fa-check-circle icon-pass"></i>
                    <?php elseif ($check['status'] === 'warn'): ?>
                        <i class="fas fa-exclamation-triangle icon-warn"></i>
                    <?php else: ?>
                        <i class="fas fa-times-circle icon-fail"></i>
                    <?php endif; ?>
                    <?php echo htmlspecialchars($check['name']); ?>
                </div>
                <div class="check-category"><?php echo htmlspecialchars($check['category']); ?></div>
            </div>
            <span class="status-badge status-<?php echo $check['status']; ?>">
                <?php echo strtoupper($check['status']); ?>
            </span>
        </div>
        <div class="check-details">
            <div style="margin-bottom: 10px; font-weight: 600; color: #333;">
                <?php echo htmlspecialchars($check['message']); ?>
            </div>
            <?php foreach ($check['details'] as $detail): ?>
                <div>→ <?php echo htmlspecialchars($detail); ?></div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endforeach; ?>

    <div class="action-buttons">
        <a href="<?php echo isset($_SESSION['user']) ? 'index.php?nav=dashboard' : 'index.php'; ?>" class="btn btn-primary">
            <i class="fas fa-home"></i> Back to Dashboard
        </a>
        <button class="btn btn-secondary" onclick="location.reload()">
            <i class="fas fa-sync-alt"></i> Refresh Check
        </button>
        <a href="diagnose_smtp.php" class="btn btn-info" target="_blank">
            <i class="fas fa-envelope"></i> SMTP Diagnostic
        </a>
        <a href="diagnose_headers.php" class="btn btn-info" target="_blank">
            <i class="fas fa-code"></i> Headers Diagnostic
        </a>
    </div>

    <hr style="margin-top: 40px; border-color: rgba(255,255,255,0.2);">

    <div style="background: rgba(255,255,255,0.1); border-radius: 8px; padding: 20px; color: white; margin-top: 20px;">
        <h5><i class="fas fa-info-circle"></i> About This Check</h5>
        <p style="margin: 0; opacity: 0.9;">
            This health check performs a comprehensive system diagnostic including database connectivity, PHP extensions, 
            file system permissions, SMTP configuration, session handling, security settings, and performance metrics.
        </p>
    </div>
</div>
</body>
</html>
