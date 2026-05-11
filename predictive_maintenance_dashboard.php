<?php
/**
 * Predictive Maintenance Integration Dashboard
 * Central hub for all predictive maintenance data across equipment, work orders, and inventory
 */

require_once 'config.inc.php';
require_once 'common.inc.php';
require_once 'libraries/predictive_maintenance.php';
require_once 'libraries/predictive_integration.php';

// Get comprehensive metrics
$metrics = get_equipment_dashboard_metrics();
$health_overview = get_asset_health_overview();
$critical_alerts = get_critical_alerts(100);
$upcoming_maintenance = get_upcoming_maintenance(30);

// Get equipment details with health status
$equipment_list = [];
try {
    $stmt = $connection->prepare("
        SELECT e.id, e.description, e.manufacturer, e.model, e.status,
               al.expected_lifecycle_hours, al.current_runtime_hours,
               al.expected_lifecycle_cycles, al.current_cycles
        FROM equipment e
        LEFT JOIN asset_lifecycle al ON e.id = al.equipment_id
        WHERE e.tenant_id = ?
        ORDER BY e.description
    ");
    $stmt->execute([$_SESSION['tenant_id'] ?? 1]);
    $equipment_list = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    error_log("Error loading equipment list: " . $e->getMessage());
    $equipment_list = [];
}

// Get recommended maintenance by equipment
$recommendations = [];
foreach ($equipment_list as $equip) {
    $health = get_equipment_health_status($equip['id']);
    if ($health && $health['urgent_action_needed']) {
        $recommendations[] = [
            'equipment' => $equip,
            'health' => $health,
            'priority' => $health['status'] === 'Critical' ? 'URGENT' : 'HIGH'
        ];
    }
}

// Sort by priority
usort($recommendations, function($a, $b) {
    return strcmp($a['priority'], $b['priority']);
});

// Get professional metrics for charts
$metrics_data = get_equipment_metrics_for_analysis();
$mtbf_data = $metrics_data['mtbf_by_equipment'] ?? [];
$mttr_data = $metrics_data['mttr_by_equipment'] ?? [];
$oee_data = $metrics_data['oee_by_equipment'] ?? [];
$health_trend = $metrics_data['health_trend_30days'] ?? [];

// Chart data preparation
$chart_mtbf_labels = array_column($mtbf_data, 'equipment_name');
$chart_mtbf_values = array_column($mtbf_data, 'mtbf_days');
$chart_mttr_labels = array_column($mttr_data, 'equipment_name');
$chart_mttr_values = array_column($mttr_data, 'mttr_hours');
$chart_oee_labels = array_column($oee_data, 'equipment_name');
$chart_oee_values = array_column($oee_data, 'oee_percent');
$chart_health_labels = array_column($health_trend, 'date');
$chart_health_values = array_column($health_trend, 'health_score');

?>
<!DOCTYPE html>
<html>
<head>
    <title>Predictive Maintenance Dashboard</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        
        body {
            background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', 'Roboto', 'Oxygen', 'Ubuntu', 'Cantarell', sans-serif;
            font-size: 14px;
            color: #2d3748;
            min-height: 100vh;
            padding: 30px 20px;
            line-height: 1.6;
        }
        
        .container {
            max-width: 1400px;
            margin: 0 auto;
        }
        
        h1, h5 {
            font-weight: 700;
            color: #1a202c;
            letter-spacing: -0.5px;
        }
        
        h1 {
            font-size: 32px;
            margin-bottom: 8px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }
        
        h5 {
            font-size: 18px;
            color: #2d3748;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        /* Card Styling */
        .card {
            border: none;
            background: white;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.07);
            margin-bottom: 28px;
            border-radius: 12px;
            overflow: hidden;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }
        
        .card:hover {
            box-shadow: 0 12px 24px rgba(0, 0, 0, 0.12);
            transform: translateY(-2px);
        }
        
        .card-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            padding: 24px;
            font-size: 16px;
            font-weight: 600;
            letter-spacing: -0.3px;
        }
        
        .card-body {
            padding: 28px;
        }
        
        /* Metric Cards Grid */
        .row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .col-md-3 {
            min-width: 0;
        }
        
        .metric-card {
            text-align: center;
            padding: 32px 24px;
            background: white;
            border-radius: 12px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.07);
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            position: relative;
            overflow: hidden;
        }
        
        .metric-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        }
        
        .metric-card:hover {
            box-shadow: 0 12px 24px rgba(0, 0, 0, 0.12);
            transform: translateY(-4px);
        }
        
        .metric-value {
            font-size: 42px;
            font-weight: 800;
            color: #667eea;
            line-height: 1;
            margin: 16px 0 12px 0;
            display: block;
        }
        
        .metric-label {
            font-size: 13px;
            color: #718096;
            font-weight: 500;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        /* Status Badges */
        .status-badge {
            display: inline-block;
            padding: 8px 16px;
            border-radius: 24px;
            font-size: 12px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.3px;
            transition: all 0.2s ease;
        }
        
        .status-critical {
            background: linear-gradient(135deg, #fed7d7 0%, #fc8181 100%);
            color: #742a2a;
        }
        
        .status-warning {
            background: linear-gradient(135deg, #feebc8 0%, #fbd38d 100%);
            color: #7c2d12;
        }
        
        .status-healthy {
            background: linear-gradient(135deg, #c6f6d5 0%, #9ae6b4 100%);
            color: #22543d;
        }
        
        /* Equipment Row */
        .equipment-row {
            padding: 20px;
            border: 1px solid #e2e8f0;
            margin-bottom: 14px;
            border-radius: 10px;
            background: white;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            border-left: 4px solid #e2e8f0;
        }
        
        .equipment-row:hover {
            background: #f8f9fa;
            border-left-color: #667eea;
            box-shadow: 0 4px 12px rgba(102, 126, 234, 0.15);
            transform: translateX(4px);
        }
        
        /* Progress Bar */
        .progress {
            background-color: #edf2f7;
            border-radius: 8px;
            height: 6px;
            overflow: hidden;
            box-shadow: inset 0 1px 3px rgba(0, 0, 0, 0.1);
        }
        
        .progress-bar {
            background: linear-gradient(90deg, #667eea 0%, #764ba2 100%);
            transition: width 0.6s cubic-bezier(0.4, 0, 0.2, 1);
            box-shadow: 0 0 10px rgba(102, 126, 234, 0.3);
        }
        
        .progress-bar-animation {
            animation: pulse 2s cubic-bezier(0.4, 0, 0.6, 1) infinite;
        }
        
        @keyframes pulse {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.8; }
        }
        
        /* Alert Box */
        .alert-box {
            padding: 16px 20px;
            margin-bottom: 12px;
            border-left: 4px solid;
            border-radius: 8px;
            transition: all 0.2s ease;
            background-size: 0 0;
        }
        
        .alert-box:hover {
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08);
        }
        
        .alert-critical {
            background: linear-gradient(135deg, #fed7d7 0%, #feebc8 50%);
            border-left-color: #dc3545;
            color: #742a2a;
        }
        
        .alert-warning {
            background: linear-gradient(135deg, #feebc8 0%, #fff5e6 50%);
            border-left-color: #ff9800;
            color: #7c2d12;
        }
        
        .alert-info {
            background: linear-gradient(135deg, #bee3f8 0%, #e0f2fe 50%);
            border-left-color: #2196F3;
            color: #002d5c;
        }
        
        /* Chart Container */
        .chart-container {
            position: relative;
            height: 360px;
            margin-top: 16px;
            background: white;
            border-radius: 12px;
            padding: 24px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.07);
            border: 1px solid #e2e8f0;
            transition: all 0.3s ease;
        }
        
        .chart-container:hover {
            box-shadow: 0 8px 16px rgba(0, 0, 0, 0.1);
        }
        
        /* Charts Grid */
        .charts-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(480px, 1fr));
            gap: 24px;
            margin-bottom: 28px;
        }
        
        .chart-title {
            font-size: 14px;
            font-weight: 700;
            color: #2d3748;
            margin-bottom: 16px;
            display: flex;
            align-items: center;
            gap: 8px;
            letter-spacing: -0.3px;
            text-transform: capitalize;
        }
        
        /* Description Text */
        .card-body > p {
            color: #718096;
            font-size: 14px;
            margin-bottom: 20px;
            line-height: 1.6;
        }
        
        /* Buttons */
        .btn {
            font-weight: 600;
            padding: 8px 16px;
            border-radius: 8px;
            font-size: 13px;
            transition: all 0.2s ease;
            text-transform: uppercase;
            letter-spacing: 0.3px;
            border: none;
            cursor: pointer;
        }
        
        .btn-outline-primary {
            color: #667eea;
            background: transparent;
            border: 1px solid #cbd5e0;
        }
        
        .btn-outline-primary:hover {
            background: #667eea;
            color: white;
            border-color: #667eea;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(102, 126, 234, 0.3);
        }
        
        .btn-outline-success {
            color: #28a745;
            background: transparent;
            border: 1px solid #cbd5e0;
        }
        
        .btn-outline-success:hover {
            background: #28a745;
            color: white;
            border-color: #28a745;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(40, 167, 69, 0.3);
        }
        
        .btn-secondary {
            background: #718096;
            color: white;
            border: none;
        }
        
        .btn-secondary:hover {
            background: #4a5568;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.2);
        }
        
        /* Page Header Section */
        .page-header {
            margin-bottom: 32px;
        }
        
        .page-header p {
            font-size: 15px;
            color: #718096;
            margin: 0;
        }
        
        /* Responsive */
        @media (max-width: 1024px) {
            .charts-grid { grid-template-columns: 1fr; }
            h1 { font-size: 28px; }
        }
        
        @media (max-width: 768px) {
            body { padding: 16px; }
            .container { max-width: 100%; }
            h1 { font-size: 24px; }
            .card-body { padding: 20px; }
            .metric-card { padding: 24px 16px; }
            .row { grid-template-columns: 1fr; }
            .chart-container { height: 300px; }
        }
    </style>
</head>
<body>
<div class="container">
    <div class="page-header" style="margin-bottom: 36px;">
        <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 20px;">
            <div></div>
            <div>
                <a href="index.php?nav=dashboard" class="btn btn-outline-primary" style="font-size: 14px; padding: 8px 16px;">
                    <i class="fas fa-arrow-left"></i> Back to Dashboard
                </a>
            </div>
        </div>
    </div>
    
    <!-- Key Metrics -->
    <div class="row mb-4">
        <div class="col-md-3">
            <div class="card metric-card">
                <div class="metric-value" style="color: #667eea;"><?php echo $metrics['total_equipment']; ?></div>
                <div class="metric-label">Total Equipment</div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card metric-card">
                <div class="metric-value" style="color: #dc3545;"><?php echo $metrics['critical_health']; ?></div>
                <div class="metric-label">Critical Condition</div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card metric-card">
                <div class="metric-value" style="color: #ff9800;"><?php echo $metrics['due_for_maintenance']; ?></div>
                <div class="metric-label">Due for Maintenance</div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card metric-card">
                <div class="metric-value" style="color: #f44336;"><?php echo $metrics['active_predictive_alerts']; ?></div>
                <div class="metric-label">Active Alerts</div>
            </div>
        </div>
    </div>

    <!-- Fleet Health Summary -->
    <div class="row mb-4">
        <div class="col-md-6">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">📊 Fleet Health Overview</h5>
                </div>
                <div class="card-body">
                    <div class="metric mb-2">
                        <strong>Fleet Health Score:</strong>
                        <span style="float: right; font-size: 24px; font-weight: bold; color: #28a745;">
                            <?php echo $health_overview['health_percentage'] ?? 100; ?>%
                        </span>
                    </div>
                    <div class="progress" style="height: 25px;">
                        <div class="progress-bar bg-success" role="progressbar" 
                             style="width: <?php echo min(100, $health_overview['health_percentage'] ?? 100); ?>%"
                             aria-valuenow="<?php echo $health_overview['health_percentage'] ?? 100; ?>" 
                             aria-valuemin="0" aria-valuemax="100">
                            <?php echo $health_overview['health_percentage'] ?? 100; ?>%
                        </div>
                    </div>
                    <div style="margin-top: 15px; display: grid; grid-template-columns: 1fr 1fr; gap: 15px;">
                        <div>
                            <div style="font-size: 24px; font-weight: bold; color: #28a745;">
                                <?php echo $health_overview['healthy'] ?? 0; ?>
                            </div>
                            <small>Healthy Equipment</small>
                        </div>
                        <div>
                            <div style="font-size: 24px; font-weight: bold; color: #dc3545;">
                                <?php echo $health_overview['critical'] ?? 0; ?>
                            </div>
                            <small>Critical Equipment</small>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-md-6">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">⚠️ Active Alerts</h5>
                </div>
                <div class="card-body" style="max-height: 300px; overflow-y: auto;">
                    <?php if (empty($critical_alerts)): ?>
                        <div class="alert alert-success mb-0">
                            ✅ No critical alerts - all equipment operating normally
                        </div>
                    <?php else: ?>
                        <?php foreach ($critical_alerts as $alert): ?>
                            <div class="alert-box alert-<?php echo strtolower($alert['severity']); ?>">
                                <strong><?php echo htmlspecialchars($alert['title']); ?></strong>
                                <div style="font-size: 12px; margin-top: 3px;">
                                    Severity: <?php echo htmlspecialchars($alert['severity']); ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Upcoming Maintenance -->
    <div class="card mb-4">
        <div class="card-header">
            <h5 class="mb-0">📅 Upcoming Maintenance (Next 30 Days)</h5>
        </div>
        <div class="card-body">
            <?php if (empty($upcoming_maintenance)): ?>
                <div class="alert alert-info mb-0">
                    No maintenance scheduled for the next 30 days
                </div>
            <?php else: ?>
                <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(300px, 1fr)); gap: 15px;">
                    <?php foreach (array_slice($upcoming_maintenance, 0, 6) as $maintenance): ?>
                        <div style="padding: 15px; border: 1px solid #ddd; border-radius: 5px; background: #fafafa;">
                            <strong><?php echo htmlspecialchars($maintenance['task_name'] ?? 'Maintenance Task'); ?></strong>
                            <div style="font-size: 12px; color: #666; margin-top: 5px;">
                                📅 Due: <?php echo htmlspecialchars($maintenance['next_due_date'] ?? 'TBD'); ?>
                            </div>
                            <div style="margin-top: 10px;">
                                <span class="status-badge status-<?php echo strtolower($maintenance['priority'] ?? 'normal'); ?>">
                                    <?php echo htmlspecialchars($maintenance['priority'] ?? 'Normal'); ?> Priority
                                </span>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Equipment Status Overview -->
    <div class="card mb-4">
        <div class="card-header">
            <h5 class="mb-0">📊 Professional Metrics Analysis</h5>
        </div>
        <div class="card-body">
            <p style="color: #666; margin-bottom: 20px;">Real-time analytics for MTBF, MTTR, OEE, and fleet health trends</p>
            <div class="charts-grid">
                <div class="chart-container">
                    <div class="chart-title">📈 MTBF by Equipment (Days)</div>
                    <canvas id="mtbfChart"></canvas>
                </div>
                <div class="chart-container">
                    <div class="chart-title">⏱️ MTTR by Equipment (Hours)</div>
                    <canvas id="mttrChart"></canvas>
                </div>
                <div class="chart-container">
                    <div class="chart-title">⚙️ OEE Performance (%)</div>
                    <canvas id="oeeChart"></canvas>
                </div>
                <div class="chart-container" style="grid-column: 1 / -1;">
                    <div class="chart-title">📉 Fleet Health Trend (30 Days)</div>
                    <canvas id="healthTrendChart"></canvas>
                </div>
            </div>
        </div>
    </div>

    <!-- Equipment Status Overview -->
    <div class="card mb-4">
        <div class="card-header">
            <h5 class="mb-0">🏭 Equipment Status Overview</h5>
        </div>
        <div class="card-body" style="max-height: 600px; overflow-y: auto;">
            <?php if (empty($equipment_list)): ?>
                <div class="alert alert-info mb-0">
                    No equipment registered in the system
                </div>
            <?php else: ?>
                <?php foreach ($equipment_list as $equip): ?>
                    <?php 
                    $eq_health = get_equipment_health_status($equip['id']);
                    $status_class = 'status-healthy';
                    if ($eq_health['status'] === 'Critical') {
                        $status_class = 'status-critical';
                    } elseif ($eq_health['status'] === 'Warning') {
                        $status_class = 'status-warning';
                    }
                    ?>
                    <div class="equipment-row">
                        <div style="display: flex; justify-content: space-between; align-items: center;">
                            <div>
                                <strong><?php echo htmlspecialchars($equip['description'] ?? 'Unknown'); ?></strong>
                                <div style="font-size: 12px; color: #666;">
                                    <?php echo htmlspecialchars($equip['status'] ?? 'Unknown'); ?> • 
                                    <?php echo htmlspecialchars($equip['manufacturer'] ?? ''); ?>
                                </div>
                            </div>
                            <div style="text-align: right;">
                                <span class="status-badge <?php echo $status_class; ?>">
                                    <?php echo htmlspecialchars($eq_health['status'] ?? 'Unknown'); ?>
                                </span>
                                <div style="font-size: 18px; font-weight: bold; color: #667eea; margin-top: 5px;">
                                    <?php echo $eq_health['health_percentage'] ?? 0; ?>%
                                </div>
                            </div>
                        </div>
                        <div class="progress mt-2" style="height: 8px;">
                            <div class="progress-bar <?php 
                                echo $eq_health['health_percentage'] > 80 ? 'bg-danger' : 
                                     ($eq_health['health_percentage'] > 60 ? 'bg-warning' : 'bg-success');
                            ?>" role="progressbar" 
                            style="width: <?php echo min(100, $eq_health['health_percentage'] ?? 0); ?>%" 
                            aria-valuenow="<?php echo $eq_health['health_percentage'] ?? 0; ?>" 
                            aria-valuemin="0" aria-valuemax="100"></div>
                        </div>
                        <?php if (!empty($eq_health['maintenance_recommendations'])): ?>
                            <div style="font-size: 12px; color: #666; margin-top: 8px;">
                                <strong>Recommendations:</strong><br>
                                <?php foreach (array_slice($eq_health['maintenance_recommendations'], 0, 2) as $rec): ?>
                                    • <?php echo htmlspecialchars(substr($rec, 0, 50)) . (strlen($rec) > 50 ? '...' : ''); ?><br>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                        <div style="margin-top: 10px;">
                            <a href="equipment_health.php?id=<?php echo $equip['id']; ?>" target="_blank" 
                               class="btn btn-sm btn-outline-primary">View Details</a>
                            <a href="work_order.php?equipment=<?php echo urlencode($equip['id']); ?>" 
                               class="btn btn-sm btn-outline-success">Create Work Order</a>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>

<script>
    // Chart data from PHP backend
    const mtbfLabels = <?php echo json_encode($chart_mtbf_labels ?: []); ?>;
    const mtbfValues = <?php echo json_encode($chart_mtbf_values ?: []); ?>;
    
    const mttrLabels = <?php echo json_encode($chart_mttr_labels ?: []); ?>;
    const mttrValues = <?php echo json_encode($chart_mttr_values ?: []); ?>;
    
    const oeeLabels = <?php echo json_encode($chart_oee_labels ?: []); ?>;
    const oeeValues = <?php echo json_encode($chart_oee_values ?: []); ?>;
    
    const healthLabels = <?php echo json_encode($chart_health_labels ?: []); ?>;
    const healthValues = <?php echo json_encode($chart_health_values ?: []); ?>;
    
    // MTBF Chart
    if (mtbfLabels.length > 0) {
        new Chart(document.getElementById('mtbfChart'), {
            type: 'bar',
            data: {
                labels: mtbfLabels,
                datasets: [{
                    label: 'MTBF (Days)',
                    data: mtbfValues,
                    backgroundColor: '#10b981',
                    borderColor: '#059669',
                    borderWidth: 2,
                    borderRadius: 6
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { display: false } },
                scales: { y: { beginAtZero: true, ticks: { callback: v => v + 'd' } } }
            }
        });
    }
    
    // MTTR Chart
    if (mttrLabels.length > 0) {
        new Chart(document.getElementById('mttrChart'), {
            type: 'bar',
            data: {
                labels: mttrLabels,
                datasets: [{
                    label: 'MTTR (Hours)',
                    data: mttrValues,
                    backgroundColor: '#f59e0b',
                    borderColor: '#d97706',
                    borderWidth: 2,
                    borderRadius: 6
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { display: false } },
                scales: { y: { beginAtZero: true, ticks: { callback: v => v + 'h' } } }
            }
        });
    }
    
    // OEE Chart
    if (oeeLabels.length > 0) {
        new Chart(document.getElementById('oeeChart'), {
            type: 'doughnut',
            data: {
                labels: oeeLabels,
                datasets: [{
                    data: oeeValues,
                    backgroundColor: ['#3b82f6', '#8b5cf6', '#ec4899', '#f59e0b', '#10b981', '#06b6d4', '#f97316', '#6366f1', '#d946ef', '#14b8a6'],
                    borderColor: '#fff',
                    borderWidth: 2
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { position: 'bottom', labels: { padding: 15, font: { size: 12 } } } }
            }
        });
    }
    
    // Health Trend Chart
    if (healthLabels.length > 0) {
        new Chart(document.getElementById('healthTrendChart'), {
            type: 'line',
            data: {
                labels: healthLabels,
                datasets: [{
                    label: 'Fleet Health %',
                    data: healthValues,
                    borderColor: '#667eea',
                    backgroundColor: 'rgba(102, 126, 234, 0.05)',
                    borderWidth: 3,
                    fill: true,
                    tension: 0.4,
                    pointBackgroundColor: '#667eea',
                    pointRadius: 4,
                    pointBorderColor: '#fff',
                    pointBorderWidth: 2
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { display: true } },
                scales: { y: { beginAtZero: false, min: 60, max: 100 } }
            }
        });
    }
</script>
</body>
</html>
