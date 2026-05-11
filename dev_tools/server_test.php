<?php
require_once 'config.inc.php';
require_once 'common.inc.php';
require_debug_page_access();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>KFMMS Server Test</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            max-width: 800px;
            margin: 0 auto;
            padding: 20px;
            background: #f0f0f0;
        }
        .test-section {
            background: white;
            padding: 20px;
            margin: 20px 0;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .success {
            color: #28a745;
            font-weight: bold;
        }
        .error {
            color: #dc3545;
            font-weight: bold;
        }
        .info {
            color: #007bff;
        }
    </style>
</head>
<body>
    <h1>KFMMS Development Server Test</h1>

    <div class="test-section">
        <h2>Server Information</h2>
        <p><strong>Server:</strong> <span class="info"><?php echo $_SERVER['SERVER_SOFTWARE'] ?? 'Unknown'; ?></span></p>
        <p><strong>Host:</strong> <span class="info"><?php echo $_SERVER['HTTP_HOST'] ?? 'Unknown'; ?></span></p>
        <p><strong>Port:</strong> <span class="info"><?php echo $_SERVER['SERVER_PORT'] ?? 'Unknown'; ?></span></p>
        <p><strong>PHP Version:</strong> <span class="info"><?php echo phpversion(); ?></span></p>
        <p><strong>Time:</strong> <span class="info"><?php echo date('Y-m-d H:i:s T'); ?></span></p>
        <p><strong>Request Method:</strong> <span class="info"><?php echo $_SERVER['REQUEST_METHOD']; ?></span></p>
    </div>

    <div class="test-section">
        <h2>Application Status</h2>
        <p><strong>Config Loaded:</strong>
            <?php if (file_exists('config.inc.php')): ?>
                <span class="success">✓ Yes</span>
            <?php else: ?>
                <span class="error">✗ No</span>
            <?php endif; ?>
        </p>

        <p><strong>Database Connection:</strong>
            <?php
            require_once 'config.inc.php';
            if ($db_available): ?>
                <span class="success">✓ Connected</span>
            <?php else: ?>
                <span class="error">✗ Failed (<?php echo $db_error; ?>)</span>
            <?php endif; ?>
        </p>

        <p><strong>Session Status:</strong>
            <?php if (session_status() === PHP_SESSION_ACTIVE): ?>
                <span class="success">✓ Active</span>
            <?php else: ?>
                <span class="error">✗ Inactive</span>
            <?php endif; ?>
        </p>
    </div>

    <div class="test-section">
        <h2>Navigation</h2>
        <ul>
            <li><a href="welcome.php">Welcome Page</a></li>
            <li><a href="auth.php">Login Page</a></li>
            <li><a href="license_gate.php">License Gate</a></li>
            <li><a href="index.php">Main Application</a></li>
            <li><a href="developer_license_generator.php">License Generator</a></li>
        </ul>
    </div>

    <div class="test-section">
        <h2>Debug Information</h2>
        <details>
            <summary>Click to show server variables</summary>
            <pre><?php
                $debug_vars = [
                    'HTTP_HOST' => $_SERVER['HTTP_HOST'] ?? 'N/A',
                    'HTTP_REFERER' => $_SERVER['HTTP_REFERER'] ?? 'N/A',
                    'HTTP_USER_AGENT' => $_SERVER['HTTP_USER_AGENT'] ?? 'N/A',
                    'REQUEST_URI' => $_SERVER['REQUEST_URI'],
                    'SCRIPT_NAME' => $_SERVER['SCRIPT_NAME'],
                    'REMOTE_ADDR' => $_SERVER['REMOTE_ADDR'] ?? 'N/A',
                    'HTTPS' => $_SERVER['HTTPS'] ?? 'off'
                ];
                echo json_encode($debug_vars, JSON_PRETTY_PRINT);
            ?></pre>
        </details>
    </div>

    <script>
        // Test if we're in a frame
        if (window.top !== window.self) {
            document.body.insertAdjacentHTML('afterbegin',
                '<div style="background: #fff3cd; border: 1px solid #ffeaa7; padding: 10px; margin-bottom: 20px; border-radius: 4px;">' +
                '<strong>⚠️ Frame Detected:</strong> This page is loaded in an iframe. ' +
                'Frame origin: ' + (window.top.location.origin || 'unknown') +
                '</div>');
        }
    </script>
</body>
</html>