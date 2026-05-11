<?php
/**
 * Predictive Maintenance Integration Status Report
 * Shows the complete integration of predictive maintenance across the entire application
 */

// Set default tenant for CLI runs
if (PHP_SAPI === 'cli' && empty($_SESSION['tenant_id'])) {
    $_SESSION['tenant_id'] = 1;
}

require_once 'config.inc.php';
require_once 'common.inc.php';
require_once 'libraries/predictive_maintenance.php';
require_once 'libraries/predictive_integration.php';

// Verify all components are loaded
$status = [
    'database_tables' => [],
    'functions_loaded' => [],
    'integration_points' => []
];

// Check database tables
$tables_to_check = [
    'asset_lifecycle' => 'Asset Lifecycle Tracking',
    'condition_monitoring' => 'Condition Monitoring',
    'maintenance_schedule' => 'Maintenance Scheduling',
    'part_lifecycle' => 'Part Lifecycle Tracking',
    'asset_health_metrics' => 'Health Metrics',
    'predictive_alerts' => 'Predictive Alerts'
];

foreach ($tables_to_check as $table => $label) {
    $exists = table_exists($table);
    $rows = 0;
    
    if ($exists) {
        try {
            $result = query_single_value("SELECT COUNT(*) FROM $table WHERE tenant_id = ?", [$_SESSION['tenant_id'] ?? 1]);
            $rows = $result ?? 0;
        } catch (Exception $e) {
            $rows = 0;
        }
    }
    
    $status['database_tables'][$label] = [
        'table' => $table,
        'exists' => $exists,
        'rows' => $rows
    ];
}

// Check functions
$functions_to_check = [
    'create_predictive_maintenance_tables' => 'Initialize Predictive Tables',
    'calculate_remaining_lifecycle' => 'Calculate Lifecycle %',
    'calculate_usage_percentage' => 'Calculate Usage %',
    'get_health_status' => 'Get Health Status',
    'create_predictive_alert' => 'Create Alerts',
    'check_all_assets_for_alerts' => 'Check Asset Alerts',
    'get_asset_health_overview' => 'Get Fleet Health Overview',
    'get_critical_alerts' => 'Get Critical Alerts',
    'get_upcoming_maintenance' => 'Get Upcoming Maintenance',
    'update_equipment_from_workorder' => 'Update Equipment from WO',
    'get_equipment_health_status' => 'Get Equipment Health',
    'calculate_equipment_mtbf' => 'Calculate MTBF',
    'get_workorder_priority_recommendation' => 'Recommend WO Priority',
    'get_equipment_maintenance_schedule' => 'Get Maintenance Schedule',
    'equipment_health_badge' => 'Equipment Health Badge HTML'
];

foreach ($functions_to_check as $func => $label) {
    $status['functions_loaded'][$label] = [
        'function' => $func,
        'loaded' => function_exists($func)
    ];
}

// Check integration points
$status['integration_points'] = [
    [
        'name' => 'Equipment Module',
        'file' => 'equipment.php',
        'status' => file_exists(__DIR__ . '/equipment.php'),
        'features' => ['Health Status Display', 'Lifecycle Initialization', 'Health Details Link']
    ],
    [
        'name' => 'Work Order Module',
        'file' => 'work_order.php',
        'status' => file_exists(__DIR__ . '/work_order.php'),
        'features' => ['Predictive Library Loading', 'Integration Hooks']
    ],
    [
        'name' => 'Work Order Completion',
        'file' => 'complete_work_order.php',
        'status' => file_exists(__DIR__ . '/complete_work_order.php'),
        'features' => ['Equipment Lifecycle Update', 'MTTR Calculation', 'Alert Generation']
    ],
    [
        'name' => 'Equipment Health Details',
        'file' => 'equipment_health.php',
        'status' => file_exists(__DIR__ . '/equipment_health.php'),
        'features' => ['Comprehensive Health Display', 'Condition Monitoring', 'Work Order History', 'Maintenance Schedule']
    ],
    [
        'name' => 'Predictive Dashboard',
        'file' => 'predictive_maintenance_dashboard.php',
        'status' => file_exists(__DIR__ . '/predictive_maintenance_dashboard.php'),
        'features' => ['Fleet Overview', 'Equipment Status', 'Alerts', 'Upcoming Maintenance']
    ],
    [
        'name' => 'Work Order Requests',
        'file' => 'work_order_requests.php',
        'status' => file_exists(__DIR__ . '/work_order_requests.php'),
        'features' => ['Predictive Library Integration']
    ],
    [
        'name' => 'Operations Dashboard',
        'file' => 'dashboard.php',
        'status' => file_exists(__DIR__ . '/dashboard.php'),
        'features' => ['Fleet Health Card', 'Critical Alerts', 'Link to Predictive Dashboard']
    ]
];

// Get sample data
$equipment = [];
$alerts = [];
$upcoming = [];
$metrics = ['total_equipment' => 0, 'critical_health' => 0, 'due_for_maintenance' => 0, 'active_predictive_alerts' => 0];

try {
    $equipment = query_to_array("SELECT * FROM equipment WHERE tenant_id = ? LIMIT 5", [$_SESSION['tenant_id'] ?? 1]) ?? [];
} catch (Exception $e) {}

try {
    $alerts = get_critical_alerts(10) ?? [];
} catch (Exception $e) {}

try {
    $upcoming = get_upcoming_maintenance(30) ?? [];
} catch (Exception $e) {}

try {
    $metrics = get_equipment_dashboard_metrics() ?? $metrics;
} catch (Exception $e) {}

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
        .status-warning { color: #ff9800; font-weight: bold; }
        table { font-size: 14px; }
        .badge-ok { background: #d4edda; color: #155724; }
        .badge-error { background: #f8d7da; color: #721c24; }
        h2 { border-bottom: 2px solid #667eea; padding-bottom: 10px; margin-top: 30px; }
    </style>
</head>
<body>
<div class="container">
    <h1 class="mb-4">🔧 Predictive Maintenance Integration Status Report</h1>

    <!-- Overall Status -->
    <div class="row mb-4">
        <div class="col-md-4">
            <div class="card text-center">
                <div class="card-body">
                    <h3><?php echo array_sum(array_map(fn($t) => $t['exists'] ? 1 : 0, $status['database_tables'])); ?>/<?php echo count($status['database_tables']); ?></h3>
                    <p>Database Tables Initialized</p>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card text-center">
                <div class="card-body">
                    <h3><?php echo array_sum(array_map(fn($f) => $f['loaded'] ? 1 : 0, $status['functions_loaded'])); ?>/<?php echo count($status['functions_loaded']); ?></h3>
                    <p>Functions Loaded</p>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card text-center">
                <div class="card-body">
                    <h3><?php echo array_sum(array_map(fn($i) => $i['status'] ? 1 : 0, $status['integration_points'])); ?>/<?php echo count($status['integration_points']); ?></h3>
                    <p>Integration Points Active</p>
                </div>
            </div>
        </div>
    </div>

    <!-- Database Tables Status -->
    <h2>📊 Database Tables</h2>
    <table class="table table-striped">
        <thead>
            <tr>
                <th>Table</th>
                <th>Status</th>
                <th>Row Count</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($status['database_tables'] as $label => $info): ?>
            <tr>
                <td><?php echo $label; ?></td>
                <td>
                    <span class="<?php echo $info['exists'] ? 'status-ok' : 'status-error'; ?>">
                        <?php echo $info['exists'] ? '✅ Exists' : '❌ Missing'; ?>
                    </span>
                </td>
                <td><?php echo $info['rows']; ?> records</td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

    <!-- Functions Status -->
    <h2>⚙️ Core Functions</h2>
    <table class="table table-striped">
        <thead>
            <tr>
                <th>Function</th>
                <th>Status</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($status['functions_loaded'] as $label => $info): ?>
            <tr>
                <td><code><?php echo $info['function']; ?></code></td>
                <td>
                    <span class="badge <?php echo $info['loaded'] ? 'badge-ok' : 'badge-error'; ?>">
                        <?php echo $info['loaded'] ? '✅ Loaded' : '❌ Missing'; ?>
                    </span>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

    <!-- Integration Points -->
    <h2>🔗 Integration Points</h2>
    <table class="table table-striped">
        <thead>
            <tr>
                <th>Module</th>
                <th>File</th>
                <th>Status</th>
                <th>Features</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($status['integration_points'] as $point): ?>
            <tr>
                <td><strong><?php echo $point['name']; ?></strong></td>
                <td><code><?php echo $point['file']; ?></code></td>
                <td>
                    <span class="badge <?php echo $point['status'] ? 'badge-ok' : 'badge-error'; ?>">
                        <?php echo $point['status'] ? '✅ Available' : '❌ Missing'; ?>
                    </span>
                </td>
                <td>
                    <?php foreach ($point['features'] as $feature): ?>
                        <small>• <?php echo $feature; ?></small><br>
                    <?php endforeach; ?>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

    <!-- Live Data Samples -->
    <h2>📈 Live Data Samples</h2>

    <div class="row mb-3">
        <div class="col-md-6">
            <div class="card">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0">📊 Equipment Metrics</h5>
                </div>
                <div class="card-body">
                    <div><strong>Total Equipment:</strong> <?php echo $metrics['total_equipment']; ?></div>
                    <div><strong>Critical Condition:</strong> <?php echo $metrics['critical_health']; ?></div>
                    <div><strong>Due for Maintenance:</strong> <?php echo $metrics['due_for_maintenance']; ?></div>
                    <div><strong>Active Alerts:</strong> <?php echo $metrics['active_predictive_alerts']; ?></div>
                </div>
            </div>
        </div>

        <div class="col-md-6">
            <div class="card">
                <div class="card-header bg-warning text-white">
                    <h5 class="mb-0">⚠️ Critical Alerts (<?php echo count($alerts); ?>)</h5>
                </div>
                <div class="card-body" style="max-height: 250px; overflow-y: auto;">
                    <?php if (empty($alerts)): ?>
                        <div class="alert alert-success mb-0">No critical alerts</div>
                    <?php else: ?>
                        <?php foreach (array_slice($alerts, 0, 5) as $alert): ?>
                            <div style="padding: 8px 0; border-bottom: 1px solid #eee;">
                                <strong><?php echo htmlspecialchars(substr($alert['title'], 0, 40)); ?></strong>
                                <div style="font-size: 12px; color: #666;">
                                    Severity: <?php echo htmlspecialchars($alert['severity']); ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-md-12">
            <div class="card">
                <div class="card-header bg-info text-white">
                    <h5 class="mb-0">📅 Upcoming Maintenance (<?php echo count($upcoming); ?> scheduled)</h5>
                </div>
                <div class="card-body">
                    <?php if (empty($upcoming)): ?>
                        <div class="alert alert-info mb-0">No maintenance scheduled</div>
                    <?php else: ?>
                        <ul>
                        <?php foreach (array_slice($upcoming, 0, 5) as $item): ?>
                            <li>
                                <strong><?php echo htmlspecialchars($item['task_name'] ?? 'Task'); ?></strong>
                                - Due: <?php echo htmlspecialchars($item['next_due_date'] ?? 'TBD'); ?>
                                (Priority: <?php echo htmlspecialchars($item['priority'] ?? 'Normal'); ?>)
                            </li>
                        <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Action Links -->
    <h2>🚀 Quick Actions</h2>
    <div class="row">
        <div class="col-md-3">
            <a href="dashboard.php" class="btn btn-primary w-100 mb-2">
                <i class="fas fa-tachometer-alt"></i> Main Dashboard
            </a>
        </div>
        <div class="col-md-3">
            <a href="predictive_maintenance_dashboard.php" class="btn btn-success w-100 mb-2">
                <i class="fas fa-chart-line"></i> Predictive Dashboard
            </a>
        </div>
        <div class="col-md-3">
            <a href="equipment.php" class="btn btn-info w-100 mb-2">
                <i class="fas fa-cog"></i> Equipment List
            </a>
        </div>
        <div class="col-md-3">
            <a href="work_order.php" class="btn btn-warning w-100 mb-2">
                <i class="fas fa-tasks"></i> Work Orders
            </a>
        </div>
    </div>

</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
