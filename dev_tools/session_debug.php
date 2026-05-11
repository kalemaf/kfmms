<?php
require_once 'config.inc.php';
require_once 'common.inc.php';
require_debug_page_access();
session_save_path($session_save_path);
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$session_file = ini_get('session.save_path') ? rtrim(ini_get('session.save_path'), '\\/') . DIRECTORY_SEPARATOR . 'sess_' . session_id() : 'unknown';
$session_file_exists = $session_file !== 'unknown' && file_exists($session_file);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>KFMMS Session Debug</title>
    <style>
        body { font-family: Arial, sans-serif; background: #f5f7fb; color: #222; margin: 0; padding: 20px; }
        h1 { margin-top: 0; }
        .box { background: #fff; border: 1px solid #ddd; border-radius: 10px; padding: 20px; margin-bottom: 20px; }
        pre { white-space: pre-wrap; word-wrap: break-word; }
        .warning { color: #a94442; background: #f2dede; padding: 12px; border-radius: 8px; border: 1px solid #ebccd1; }
    </style>
</head>
<body>
    <h1>KFMMS Session Debug</h1>
    <div class="box">
        <h2>Request / Server Info</h2>
        <p><strong>URL:</strong> <?php echo htmlspecialchars((isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' ? 'https' : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? 'unknown') . ($_SERVER['REQUEST_URI'] ?? '')); ?></p>
        <p><strong>Host:</strong> <?php echo htmlspecialchars($_SERVER['HTTP_HOST'] ?? 'N/A'); ?></p>
        <p><strong>Request URI:</strong> <?php echo htmlspecialchars($_SERVER['REQUEST_URI'] ?? 'N/A'); ?></p>
        <p><strong>Request Method:</strong> <?php echo htmlspecialchars($_SERVER['REQUEST_METHOD'] ?? 'N/A'); ?></p>
        <p><strong>Session ID:</strong> <?php echo htmlspecialchars(session_id()); ?></p>
        <p><strong>Session Active:</strong> <?php echo session_status() === PHP_SESSION_ACTIVE ? 'Yes' : 'No'; ?></p>
        <p><strong>Session Save Path:</strong> <?php echo htmlspecialchars(ini_get('session.save_path')); ?></p>
        <p><strong>Session File:</strong> <?php echo htmlspecialchars($session_file); ?></p>
        <p><strong>Session File Exists:</strong> <?php echo $session_file_exists ? 'Yes' : 'No'; ?></p>
    </div>

    <div class="box">
        <h2>Cookies</h2>
        <pre><?php echo htmlspecialchars(json_encode($_COOKIE, JSON_PRETTY_PRINT)); ?></pre>
    </div>

    <div class="box">
        <h2>Session Data</h2>
        <pre><?php echo htmlspecialchars(json_encode($_SESSION, JSON_PRETTY_PRINT)); ?></pre>
    </div>

    <div class="box">
        <h2>Headers</h2>
        <pre><?php echo htmlspecialchars(json_encode(getallheaders(), JSON_PRETTY_PRINT)); ?></pre>
    </div>

    <div class="box warning" id="frame-warning" style="display:none;">
        <strong>Warning:</strong> This page is loaded inside a frame. Please open it in a normal browser tab to test the app correctly.
    </div>

    <script>
        if (window.top !== window.self) {
            document.getElementById('frame-warning').style.display = 'block';
        }
    </script>
</body>
</html>
