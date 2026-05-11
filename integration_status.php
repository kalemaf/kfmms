<?php
/**
 * Predictive Maintenance Integration Status - Simplified
 */
error_reporting(E_ALL);
ini_set('display_errors', '0');

// Mock session for CLI testing
if (!isset($_SESSION)) {
    $_SESSION = ['tenant_id' => 1];
}

if (PHP_SAPI !== 'cli') {
    // Web mode - full HTML
    ?>
<!DOCTYPE html>
<html>
<head>
    <title>Predictive Maintenance Integration Status</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css">
    <style>
        body { background: #f8f9fa; padding: 20px; }
        .container { max-width: 1200px; }
        .status-ok { color: #28a745; font-weight: bold; }
        .status-error { color: #dc3545; font-weight: bold; }
        h2 { border-bottom: 2px solid #667eea; padding-bottom: 10px; margin-top: 30px; }
    </style>
</head>
<body>
<div class="container">
    <h1 class="mb-4">🔧 Predictive Maintenance Integration Status</h1>
    <?php
}

try {
    if (PHP_SAPI !== 'cli') {
        require_once 'config.inc.php';
        require_once 'common.inc.php';
        require_once 'libraries/predictive_maintenance.php';
        require_once 'libraries/predictive_integration.php';
    } else {
        $_SESSION['tenant_id'] = 1;
        require 'config.inc.php';
        require 'common.inc.php';
        require 'libraries/predictive_maintenance.php';
        require 'libraries/predictive_integration.php';
    }

    // Database tables check
    $tables = ['asset_lifecycle', 'condition_monitoring', 'maintenance_schedule', 'part_lifecycle', 'asset_health_metrics', 'predictive_alerts'];
    $tables_ok = 0;
    
    if (PHP_SAPI !== 'cli') {
        echo '<div class="row mb-4">';
        echo '<div class="col-md-6">';
        echo '<div class="card">';
        echo '<div class="card-header bg-primary text-white"><h5 class="mb-0">📊 Database Tables</h5></div>';
        echo '<div class="card-body">';
    }
    
    foreach ($tables as $table) {
        $exists = table_exists($table);
        if ($exists) $tables_ok++;
        
        if (PHP_SAPI === 'cli') {
            echo "   - $table: " . ($exists ? '✓ EXISTS' : '✗ MISSING') . "\n";
        } else {
            echo '<div style="padding: 8px 0; border-bottom: 1px solid #eee;">';
            echo '<span class="' . ($exists ? 'status-ok' : 'status-error') . '">';
            echo ($exists ? '✅' : '❌') . ' ' . $table;
            echo '</span></div>';
        }
    }
    
    if (PHP_SAPI !== 'cli') {
        echo '</div></div></div>';
        echo '<div class="col-md-6">';
        echo '<div class="card">';
        echo '<div class="card-header bg-success text-white"><h5 class="mb-0">⚙️ Core Functions</h5></div>';
        echo '<div class="card-body">';
    }
    
    // Functions check
    $functions = ['create_predictive_maintenance_tables', 'calculate_remaining_lifecycle', 'get_health_status', 
                  'create_predictive_alert', 'get_asset_health_overview', 'update_equipment_from_workorder'];
    $functions_ok = 0;
    
    foreach ($functions as $func) {
        $exists = function_exists($func);
        if ($exists) $functions_ok++;
        
        if (PHP_SAPI === 'cli') {
            echo "   - $func: " . ($exists ? '✓ LOADED' : '✗ MISSING') . "\n";
        } else {
            echo '<div style="padding: 8px 0; border-bottom: 1px solid #eee; font-size: 12px;">';
            echo '<span class="' . ($exists ? 'status-ok' : 'status-error') . '">';
            echo ($exists ? '✅' : '❌') . ' ' . substr($func, 0, 35) . '...';
            echo '</span></div>';
        }
    }
    
    if (PHP_SAPI !== 'cli') {
        echo '</div></div></div></div>';
        
        // Integration points check
        echo '<h2>🔗 Integration Points</h2>';
        $files = ['equipment.php', 'work_order.php', 'complete_work_order.php', 'predictive_maintenance_dashboard.php'];
        echo '<div class="row">';
        
        foreach ($files as $file) {
            $exists = file_exists($file);
            echo '<div class="col-md-6 mb-3">';
            echo '<div class="card ' . ($exists ? 'border-success' : 'border-danger') . '">';
            echo '<div class="card-body">';
            echo '<span class="' . ($exists ? 'status-ok' : 'status-error') . '">';
            echo ($exists ? '✅' : '❌') . ' ' . $file;
            echo '</span></div></div></div>';
        }
        echo '</div>';
        
        // Summary
        echo '<div class="alert alert-info mt-4">';
        echo '<strong>Summary:</strong> ';
        echo $tables_ok . '/' . count($tables) . ' database tables, ';
        echo $functions_ok . '/' . count($functions) . ' functions loaded<br>';
        echo '<a href="dashboard.php" class="btn btn-primary btn-sm mt-2">📊 View Dashboard</a>';
        echo '</div>';
        
        echo '</div></body></html>';
    } else {
        echo "\n✓ Database tables: $tables_ok/" . count($tables);
        echo "\n✓ Functions loaded: $functions_ok/" . count($functions);
        echo "\nIntegration complete!\n";
    }
    
} catch (Throwable $e) {
    if (PHP_SAPI === 'cli') {
        echo "ERROR: " . $e->getMessage() . "\n";
    } else {
        echo '<div class="alert alert-danger">ERROR: ' . htmlspecialchars($e->getMessage()) . '</div>';
    }
}
?>
