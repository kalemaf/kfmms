<?php
error_reporting(0);
ini_set('display_errors', '0');

// Don't load the library - just check if functions exist
$library_loaded = false;

$status = array();
$status['timestamp'] = date('Y-m-d H:i:s');
$status['files'] = array();
$status['database'] = array();
$status['functions'] = array();
$status['overall'] = 'CHECKING...';

$files_to_check = array(
    'libraries/predictive_maintenance.php',
    'predictive_dashboard.php',
    'api_condition_monitoring.php',
    'setup_predictive_maintenance.php',
);

foreach ($files_to_check as $file) {
    $exists = file_exists($file);
    $size = $exists ? filesize($file) : 0;
    $status['files'][$file] = array('exists' => $exists, 'size_kb' => round($size / 1024, 1));
}

$tables_to_check = array('asset_lifecycle', 'condition_monitoring', 'maintenance_schedule', 'part_lifecycle', 'asset_health_metrics', 'predictive_alerts');

// Database file location - matches config.inc.php default
$db_file = 'database/maintenix.db';
if (!file_exists($db_file)) {
    $db_file = 'database/cmms.db';  // Fallback
}
if (file_exists($db_file)) {
    try {
        // Use the same connection logic as config.inc.php
        $db_path = 'sqlite:' . realpath($db_file);
        $sqlite = new PDO($db_path);
        $sqlite->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        
        foreach ($tables_to_check as $table) {
            try {
                $result = $sqlite->query("SELECT COUNT(*) as cnt FROM sqlite_master WHERE type='table' AND name='" . $table . "'");
                $row = $result->fetch(PDO::FETCH_ASSOC);
                
                if ($row && $row['cnt'] > 0) {
                    // Table exists, count rows
                    $count_result = $sqlite->query("SELECT COUNT(*) as cnt FROM " . $table);
                    $count_row = $count_result->fetch(PDO::FETCH_ASSOC);
                    $status['database'][$table] = array('exists' => true, 'row_count' => (int)$count_row['cnt']);
                } else {
                    $status['database'][$table] = array('exists' => false);
                }
            } catch (Exception $e) {
                $status['database'][$table] = array('exists' => false);
            }
        }
        $sqlite = null;
    } catch (Exception $e) {
        foreach ($tables_to_check as $table) {
            $status['database'][$table] = array('exists' => false);
        }
    }
} else {
    foreach ($tables_to_check as $table) {
        $status['database'][$table] = array('exists' => false);
    }
}

$functions_to_check = array('create_predictive_maintenance_tables', 'calculate_remaining_lifecycle', 'calculate_usage_percentage', 'get_health_status', 'create_predictive_alert', 'check_all_assets_for_alerts', 'get_critical_alerts', 'get_asset_health_overview', 'get_upcoming_maintenance', 'calculate_mtbf', 'calculate_mttr', 'calculate_oee', 'get_equipment_condition_trend');

foreach ($functions_to_check as $func) {
    $status['functions'][$func] = function_exists($func);
}

$files_existing = 0;
$tables_existing = 0;
$functions_count = 0;

foreach ($status['files'] as $f) {
    if ($f['exists']) $files_existing++;
}

foreach ($status['database'] as $t) {
    if (isset($t['exists']) && $t['exists']) $tables_existing++;
}

foreach ($status['functions'] as $func_exists) {
    if ($func_exists) $functions_count++;
}

$all_files_exist = ($files_existing === 4);
$functions_loaded = ($functions_count > 10);
$tables_exist = ($tables_existing > 0);

if ($all_files_exist && $tables_existing === 6) {
    $status['overall'] = 'ACTIVE & INITIALIZED';
} elseif ($all_files_exist && $tables_exist) {
    $status['overall'] = 'PARTIALLY_INITIALIZED';
} elseif ($all_files_exist && $library_loaded) {
    $status['overall'] = 'INSTALLED';
} elseif ($all_files_exist) {
    $status['overall'] = 'FILES_PRESENT';
} else {
    $status['overall'] = 'NOT_INSTALLED';
}

// ============ HTML OUTPUT ============
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Predictive Maintenance Status</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Segoe UI', sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 20px;
        }
        .container {
            max-width: 1000px;
            margin: 0 auto;
            background: white;
            border-radius: 8px;
            padding: 30px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.3);
        }
        h1 { color: #333; margin-bottom: 10px; }
        .timestamp { color: #999; font-size: 12px; margin-bottom: 20px; }
        
        .status-badge {
            display: inline-block;
            padding: 8px 16px;
            border-radius: 4px;
            font-weight: bold;
            margin-bottom: 20px;
            font-size: 14px;
        }
        .status-active { background: #27AE60; color: white; }
        .status-installed { background: #3498DB; color: white; }
        .status-inactive { background: #E74C3C; color: white; }
        
        .section {
            margin: 30px 0;
            padding: 20px;
            background: #f8f9fa;
            border-left: 4px solid #667eea;
            border-radius: 4px;
        }
        .section h2 {
            color: #333;
            font-size: 16px;
            margin-bottom: 15px;
            display: flex;
            align-items: center;
        }
        .section h2 .icon {
            margin-right: 10px;
            font-size: 20px;
        }
        
        .item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 10px 0;
            border-bottom: 1px solid #ddd;
            font-size: 13px;
        }
        .item:last-child { border-bottom: none; }
        .item-label { flex: 1; }
        .item-status {
            padding: 4px 12px;
            border-radius: 3px;
            font-size: 11px;
            font-weight: bold;
        }
        .status-ok { background: #d4edda; color: #155724; }
        .status-error { background: #f8d7da; color: #721c24; }
        .status-info { background: #d1ecf1; color: #0c5460; }
        
        .details {
            font-size: 11px;
            color: #666;
            margin-left: auto;
            margin-right: 10px;
        }
        
        .next-steps {
            background: #e7f3ff;
            border-left: 4px solid #2196F3;
            padding: 15px;
            margin: 20px 0;
            border-radius: 4px;
        }
        .next-steps h3 {
            color: #1976D2;
            font-size: 14px;
            margin-bottom: 10px;
        }
        .next-steps ul {
            margin-left: 20px;
            font-size: 13px;
        }
        .next-steps li {
            margin: 8px 0;
            color: #333;
        }
        
        code {
            background: #f4f4f4;
            padding: 2px 6px;
            border-radius: 3px;
            font-family: 'Courier New', monospace;
            font-size: 12px;
        }
        
        .summary-table {
            width: 100%;
            margin: 15px 0;
            border-collapse: collapse;
            font-size: 13px;
        }
        .summary-table th, .summary-table td {
            padding: 10px;
            text-align: left;
            border: 1px solid #ddd;
        }
        .summary-table th {
            background: #f0f0f0;
            font-weight: bold;
        }
        .summary-table tr:hover { background: #f9f9f9; }
    </style>
</head>
<body>
    <div class="container">
        <h1>🔍 Predictive Maintenance - Implementation Status</h1>
        <p class="timestamp">Checked: <?php echo htmlspecialchars($status['timestamp']); ?></p>
        
        <!-- STATUS BADGE -->
        <?php
        $badge_class = 'status-inactive';
        if ($status['overall'] === 'ACTIVE & INITIALIZED') {
            $badge_class = 'status-active';
        } elseif ($status['overall'] === 'INSTALLED') {
            $badge_class = 'status-installed';
        }
        ?>
        <div class="status-badge <?php echo $badge_class; ?>">
            Status: <?php echo htmlspecialchars($status['overall']); ?>
        </div>
        
        <!-- FILES SECTION -->
        <div class="section">
            <h2><span class="icon">📁</span> Core Files (4 Required)</h2>
            <table class="summary-table">
                <thead>
                    <tr>
                        <th>File</th>
                        <th>Status</th>
                        <th>Size</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($status['files'] as $file => $info): ?>
                    <tr>
                        <td><code><?php echo htmlspecialchars($file); ?></code></td>
                        <td>
                            <span class="item-status <?php echo $info['exists'] ? 'status-ok' : 'status-error'; ?>">
                                <?php echo $info['exists'] ? '✅ OK' : '❌ MISSING'; ?>
                            </span>
                        </td>
                        <td><?php echo $info['exists'] ? htmlspecialchars($info['size_kb'] . ' KB') : '-'; ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        
        <!-- DATABASE SECTION -->
        <div class="section">
            <h2><span class="icon">🗄️</span> Database Tables (6 Required)</h2>
            <table class="summary-table">
                <thead>
                    <tr>
                        <th>Table Name</th>
                        <th>Status</th>
                        <th>Row Count</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($status['database'] as $table => $info): ?>
                    <tr>
                        <td><code><?php echo htmlspecialchars($table); ?></code></td>
                        <td>
                            <span class="item-status <?php echo $info['exists'] ? 'status-ok' : 'status-error'; ?>">
                                <?php echo $info['exists'] ? '✅ EXISTS' : '❌ MISSING'; ?>
                            </span>
                        </td>
                        <td><?php echo $info['exists'] ? $info['row_count'] : '-'; ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        
        <!-- FUNCTIONS SECTION -->
        <div class="section">
            <h2><span class="icon">⚙️</span> Core Functions (13 Required)</h2>
            <div style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 10px;">
                <?php foreach ($status['functions'] as $func => $exists): ?>
                <div class="item">
                    <span class="item-label"><code><?php echo htmlspecialchars($func); ?></code></span>
                    <span class="item-status <?php echo $exists ? 'status-ok' : 'status-error'; ?>">
                        <?php echo $exists ? '✅' : '❌'; ?>
                    </span>
                </div>
                <?php endforeach; ?>
            </div>
            <p style="margin-top: 15px; font-size: 12px; color: #666;">
                Loaded: <strong><?php echo $functions_count; ?>/<?php echo count($functions_to_check); ?></strong> functions
            </p>
        </div>
        
        <!-- NEXT STEPS -->
        <?php if ($status['overall'] !== 'ACTIVE & INITIALIZED'): ?>
        <div class="next-steps">
            <h3>🚀 Next Steps</h3>
            <ul>
                <li><strong>Step 1:</strong> Run initialization script: <code>php setup_predictive_maintenance.php</code></li>
                <li><strong>Step 2:</strong> Refresh this page to see updated status</li>
                <li><strong>Step 3:</strong> Access dashboard: <code>predictive_dashboard.php</code></li>
                <li><strong>Step 4:</strong> Read guide: <code>PREDICTIVE_MAINTENANCE_GUIDE.md</code></li>
            </ul>
        </div>
        <?php else: ?>
        <div class="next-steps">
            <h3>✅ System Ready!</h3>
            <ul>
                <li><strong>Dashboard:</strong> <a href="predictive_dashboard.php">predictive_dashboard.php</a></li>
                <li><strong>API Endpoint:</strong> <a href="api_condition_monitoring.php">api_condition_monitoring.php</a></li>
                <li><strong>Documentation:</strong> Read PREDICTIVE_MAINTENANCE_GUIDE.md</li>
                <li><strong>Test:</strong> Submit condition data via API</li>
            </ul>
        </div>
        <?php endif; ?>
        
        <!-- QUICK LINKS -->
        <div class="section" style="border-left-color: #27AE60;">
            <h2><span class="icon">🔗</span> Quick Links</h2>
            <div style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 10px;">
                <a href="predictive_dashboard.php" style="padding: 10px; background: #667eea; color: white; text-decoration: none; border-radius: 4px; text-align: center; font-size: 13px;">
                    📊 Dashboard
                </a>
                <a href="setup_predictive_maintenance.php" style="padding: 10px; background: #3498DB; color: white; text-decoration: none; border-radius: 4px; text-align: center; font-size: 13px;">
                    ⚙️ Setup
                </a>
                <a href="api_condition_monitoring.php" style="padding: 10px; background: #27AE60; color: white; text-decoration: none; border-radius: 4px; text-align: center; font-size: 13px;">
                    🔌 API
                </a>
                <a href="PREDICTIVE_MAINTENANCE_GUIDE.md" style="padding: 10px; background: #E67E22; color: white; text-decoration: none; border-radius: 4px; text-align: center; font-size: 13px;">
                    📚 Guide
                </a>
            </div>
        </div>
        
        <!-- DEBUG INFO -->
        <div class="section" style="background: #f4f4f4; font-size: 11px;">
            <h3 style="color: #666; margin-bottom: 10px;">Debug Info</h3>
            <p>Library Loaded: <strong><?php echo $library_loaded ? 'YES' : 'NO'; ?></strong></p>
            <p>Files OK: <strong><?php echo $files_existing; ?>/4</strong></p>
            <p>Tables OK: <strong><?php echo $tables_existing; ?>/6</strong></p>
            <p>Functions OK: <strong><?php echo $functions_count; ?>/13</strong></p>
        </div>
    </div>
</body>
</html>
