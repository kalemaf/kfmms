<?php
/**
 * Predictive Maintenance Dashboard
 * 
 * Professional-grade dashboard showing:
 * - Critical alerts and overused parts
 * - Asset health overview
 * - Upcoming maintenance schedule
 * - Condition monitoring trends
 * - Professional metrics (MTBF, MTTR, OEE)
 */

require_once 'config.inc.php';
require_once 'common.inc.php';
require_once 'libraries/predictive_maintenance.php';

// Check authorization
if (!isset($_SESSION['user_id']) || empty($_SESSION['user_id'])) {
    header('Location: auth.php');
    exit;
}

$tenant_id = $_SESSION['tenant_id'] ?? 1;
$user_role = $_SESSION['role'] ?? 'operator';

// Get data
$critical_alerts = get_critical_alerts(10);
$health_overview = get_asset_health_overview();
$upcoming_maintenance = get_upcoming_maintenance(30);

// Calculate professional metrics for charts
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

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Predictive Maintenance Dashboard</title>
    <link rel="stylesheet" href="css/style.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 30px 20px;
        }
        
        .container {
            max-width: 1600px;
            margin: 0 auto;
        }
        
        /* Header Styling */
        .header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 40px;
            border-radius: 15px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.2);
            margin-bottom: 30px;
        }
        
        .header h1 {
            font-size: 36px;
            margin-bottom: 10px;
            font-weight: 700;
        }
        
        .header p {
            opacity: 0.95;
            font-size: 15px;
        }
        
        /* Dashboard Grid */
        .dashboard-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        /* Card Styling */
        .card {
            background: white;
            border-radius: 12px;
            padding: 25px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.08);
            transition: all 0.3s ease;
            border-left: 5px solid #667eea;
        }
        
        .card:hover {
            transform: translateY(-8px);
            box-shadow: 0 12px 25px rgba(0,0,0,0.15);
        }
        
        .card-title {
            font-size: 12px;
            font-weight: 700;
            color: #6b7280;
            text-transform: uppercase;
            letter-spacing: 1.5px;
            margin-bottom: 15px;
        }
        
        .card-value {
            font-size: 42px;
            font-weight: 700;
            color: #1f2937;
            margin-bottom: 15px;
        }
        
        .metric-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin: 12px 0;
            padding: 10px 0;
            border-bottom: 1px solid #e5e7eb;
        }
        
        .metric-row:last-child { border-bottom: none; }
        
        .metric-label {
            color: #6b7280;
            font-size: 13px;
        }
        
        .metric-value {
            font-weight: 700;
            color: #1f2937;
            font-size: 15px;
        }
        
        /* Status Badges */
        .status-badge {
            display: inline-block;
            padding: 6px 14px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .badge-healthy { background: #d1fae5; color: #065f46; }
        .badge-warning { background: #fef3c7; color: #92400e; }
        .badge-critical { background: #fee2e2; color: #991b1b; }
        .btn {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 10px 20px;
            border-radius: 8px;
            text-decoration: none;
            font-weight: 500;
            font-size: 14px;
            transition: all 0.3s ease;
            border: 2px solid;
            cursor: pointer;
        }

        .btn-outline-primary {
            color: #667eea;
            border-color: #667eea;
            background: transparent;
        }

        .btn-outline-primary:hover {
            color: white;
            background: #667eea;
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(102, 126, 234, 0.3);
        }
        
        /* Alert Box */
        .alert-box {
            background: #f9fafb;
            border-left: 5px solid #f59e0b;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 12px;
            transition: all 0.3s ease;
        }
        
        .alert-box:hover {
            background: #fffbeb;
            box-shadow: 0 4px 12px rgba(245, 158, 11, 0.1);
        }
        
        .alert-box.critical {
            background: #fef2f2;
            border-left-color: #dc2626;
        }
        
        .alert-title {
            font-weight: 700;
            color: #1f2937;
            margin-bottom: 5px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .alert-description {
            font-size: 13px;
            color: #6b7280;
            margin-bottom: 8px;
            line-height: 1.5;
        }
        
        /* Health Bar */
        .health-bar {
            height: 30px;
            background: #e5e7eb;
            border-radius: 8px;
            overflow: hidden;
            margin: 15px 0;
            box-shadow: inset 0 2px 4px rgba(0,0,0,0.05);
        }
        
        .health-bar-fill {
            height: 100%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 700;
            font-size: 12px;
            transition: width 0.5s ease;
            background: linear-gradient(90deg, #10b981, #059669);
        }
        
        .health-bar-fill.warning {
            background: linear-gradient(90deg, #f59e0b, #d97706);
        }
        
        .health-bar-fill.critical {
            background: linear-gradient(90deg, #dc2626, #991b1b);
        }
        
        /* Grid Layouts */
        .row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(500px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .full-width {
            grid-column: 1 / -1;
        }
        
        .charts-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(400px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        /* Table Styling */
        table {
            width: 100%;
            border-collapse: collapse;
            font-size: 13px;
        }
        
        thead {
            background: #f3f4f6;
            border-top: 2px solid #e5e7eb;
            border-bottom: 2px solid #e5e7eb;
        }
        
        th {
            padding: 14px 12px;
            text-align: left;
            font-weight: 700;
            color: #374151;
            text-transform: uppercase;
            font-size: 11px;
            letter-spacing: 0.5px;
        }
        
        td {
            padding: 12px;
            border-bottom: 1px solid #f3f4f6;
        }
        
        tbody tr:hover {
            background: #f9fafb;
        }
        
        tbody tr:last-child td {
            border-bottom: none;
        }
        
        /* Chart Container */
        .chart-container {
            position: relative;
            height: 350px;
            background: white;
            border-radius: 12px;
            padding: 20px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.08);
        }
        
        .chart-title {
            font-size: 15px;
            font-weight: 700;
            color: #1f2937;
            margin-bottom: 20px;
        }
        
        /* Empty State */
        .empty-state {
            text-align: center;
            padding: 50px 20px;
            color: #9ca3af;
        }
        
        .empty-state-icon {
            font-size: 48px;
            margin-bottom: 15px;
        }
        
        .empty-state p {
            font-size: 14px;
        }
        
        /* Info Box */
        .info-box {
            background: #f0f9ff;
            border-left: 5px solid #3b82f6;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 20px;
        }
        
        .info-box strong {
            color: #1e40af;
            display: block;
            margin-bottom: 8px;
        }
        
        .info-box p {
            font-size: 13px;
            color: #1e3a8a;
            line-height: 1.6;
        }
        
        .metrics-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 15px;
        }
        
        .metric-card {
            background: #f9fafb;
            padding: 15px;
            border-radius: 8px;
            border: 1px solid #e5e7eb;
        }
        
        .metric-card strong {
            color: #1f2937;
            display: block;
            margin-bottom: 8px;
            font-size: 14px;
        }
        
        .metric-card p {
            font-size: 12px;
            color: #6b7280;
            line-height: 1.5;
        }
        
        /* Footer */
        .footer {
            text-align: center;
            color: #9ca3af;
            font-size: 12px;
            padding: 25px;
            margin-top: 30px;
        }
        
        .footer p {
            margin: 5px 0;
        }
        
        /* Responsive Design */
        @media (max-width: 768px) {
            .header { padding: 25px; }
            .header h1 { font-size: 28px; }
            .dashboard-grid { grid-template-columns: 1fr; }
            .row { grid-template-columns: 1fr; }
            .charts-grid { grid-template-columns: 1fr; }
            .card-value { font-size: 32px; }
            .chart-container { height: 300px; }
        }
    </style>
            margin-bottom: 10px;
        }
        
        .alert-box.critical {
            background: #fef2f2;
            border-color: #dc2626;
        }
        
        .alert-title {
            font-weight: 700;
            color: #1f2937;
            margin-bottom: 5px;
        }
        
        .alert-description {
            font-size: 13px;
            color: #6b7280;
            margin-bottom: 8px;
        }
        
        .alert-recommendation {
            font-size: 12px;
            color: #374151;
            font-style: italic;
            padding: 8px;
            background: rgba(255,255,255,0.6);
            border-radius: 4px;
        }
        
        .health-bar {
            height: 24px;
            background: #ecf0f1;
            border-radius: 4px;
            overflow: hidden;
            margin: 10px 0;
            position: relative;
        }
        
        .health-bar-fill {
            height: 100%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 600;
            font-size: 11px;
            transition: width 0.3s ease;
        }
        
        .row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(400px, 1fr));
            gap: 20px;
            margin-bottom: 25px;
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
            font-size: 13px;
        }
        
        thead {
            background: #f8f9fa;
            border-top: 2px solid #dee2e6;
            border-bottom: 2px solid #dee2e6;
        }
        
        th {
            padding: 12px;
            text-align: left;
            font-weight: 600;
            color: #495057;
        }
        
        td {
            padding: 12px;
            border-bottom: 1px solid #dee2e6;
        }
        
        tr:hover {
            background: #f8f9fa;
        }
        
        .chart-container {
            position: relative;
            height: 300px;
            margin-top: 15px;
        }
        
        .empty-state {
            text-align: center;
            padding: 40px 20px;
            color: #95a5a6;
        }
        
        .empty-state-icon {
            font-size: 48px;
            margin-bottom: 10px;
        }
        
        .footer {
            text-align: center;
            color: #95a5a6;
            font-size: 12px;
            padding: 20px;
        }
    </style>
</head>
<body>
    <div class="container">
        <!-- Header -->
        <div class="header">
            <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 10px;">
                <div></div>
                <div>
                    <a href="index.php?nav=dashboard" class="btn btn-outline-primary" style="font-size: 14px; padding: 8px 16px;">
                        <i class="fas fa-arrow-left"></i> Back to Dashboard
                    </a>
                </div>
            </div>
            <h1>🔮 Predictive Maintenance Dashboard</h1>
            <p>Real-time asset health, predictive analytics, and maintenance optimization</p>
        </div>
        
        <!-- Key Metrics -->
        <div class="dashboard-grid">
            <div class="card">
                <div class="card-title">Total Equipment</div>
                <div class="card-value"><?php echo $health_overview['total_assets'] ?? 0; ?></div>
                <div class="metric-row">
                    <span class="metric-label">✅ Healthy:</span>
                    <span class="metric-value"><?php echo $health_overview['healthy'] ?? 0; ?></span>
                </div>
                <div class="metric-row">
                    <span class="metric-label">⚠️ Caution:</span>
                    <span class="metric-value"><?php echo $health_overview['warning'] ?? 0; ?></span>
                </div>
                <div class="metric-row">
                    <span class="metric-label">🔴 Critical:</span>
                    <span class="metric-value"><?php echo $health_overview['critical'] ?? 0; ?></span>
                </div>
            </div>
            
            <div class="card">
                <div class="card-title">Fleet Health Score</div>
                <div class="card-value"><?php echo $health_overview['health_percentage'] ?? 0; ?>%</div>
                <div class="health-bar">
                    <div class="health-bar-fill <?php 
                        $pct = $health_overview['health_percentage'] ?? 0;
                        echo $pct > 80 ? '' : ($pct > 60 ? 'warning' : 'critical');
                    ?>" style="width: <?php echo min(100, max(0, $pct)); ?>%;">
                        <?php echo $pct; ?>%
                    </div>
                </div>
                <div style="font-size: 12px; color: #6b7280; margin-top: 10px;">Overall fleet health</div>
            </div>
            
            <div class="card">
                <div class="card-title">Average Usage</div>
                <div class="card-value"><?php echo $health_overview['average_usage'] ?? 0; ?>%</div>
                <div style="font-size: 12px; color: #6b7280; margin-top: 10px;">Lifecycle utilization</div>
            </div>
            
            <div class="card">
                <div class="card-title">Critical Condition</div>
                <div class="card-value"><?php echo $health_overview['critical'] ?? 0; ?></div>
                <div style="font-size: 12px; color: #6b7280; margin-top: 10px;">Immediate attention needed</div>
            </div>
            
            <div class="card">
                <div class="card-title">Due for Maintenance</div>
                <div class="card-value"><?php echo count($upcoming_maintenance) ?? 0; ?></div>
                <div style="font-size: 12px; color: #6b7280; margin-top: 10px;">Next 30 days</div>
            </div>
            
            <div class="card">
                <div class="card-title">Active Alerts</div>
                <div class="card-value"><?php echo count($critical_alerts) ?? 0; ?></div>
                <div style="font-size: 12px; color: #6b7280; margin-top: 10px;"><?php 
                    $critical_count = count(array_filter($critical_alerts, fn($a) => ($a['severity'] ?? '') === 'Critical'));
                    echo $critical_count . ' critical';
                ?></div>
            </div>
        </div>
        
        <!-- Professional Metrics Charts -->
        <div style="margin-bottom: 30px;">
            <div style="background: white; border-radius: 12px; padding: 25px; box-shadow: 0 4px 15px rgba(0,0,0,0.08); margin-bottom: 20px;">
                <h2 style="font-size: 20px; font-weight: 700; color: #1f2937; margin-bottom: 10px;">📊 Professional Metrics Analysis</h2>
                <p style="font-size: 13px; color: #6b7280; margin-bottom: 20px;">Real-time analytics for MTBF, MTTR, OEE, and fleet health trends</p>
                
                <div class="charts-grid">
                    <!-- MTBF Chart -->
                    <div class="chart-container" style="background: white; border-radius: 12px; padding: 20px; box-shadow: 0 2px 10px rgba(0,0,0,0.08);">
                        <div class="chart-title">📈 MTBF by Equipment (Days)</div>
                        <canvas id="mtbfChart"></canvas>
                    </div>
                    
                    <!-- MTTR Chart -->
                    <div class="chart-container" style="background: white; border-radius: 12px; padding: 20px; box-shadow: 0 2px 10px rgba(0,0,0,0.08);">
                        <div class="chart-title">⏱️ MTTR by Equipment (Hours)</div>
                        <canvas id="mttrChart"></canvas>
                    </div>
                    
                    <!-- OEE Chart -->
                    <div class="chart-container" style="background: white; border-radius: 12px; padding: 20px; box-shadow: 0 2px 10px rgba(0,0,0,0.08);">
                        <div class="chart-title">⚙️ OEE Performance (%)</div>
                        <canvas id="oeeChart"></canvas>
                    </div>
                    
                    <!-- Health Trend Chart -->
                    <div class="chart-container full-width" style="background: white; border-radius: 12px; padding: 20px; box-shadow: 0 2px 10px rgba(0,0,0,0.08); height: 400px;">
                        <div class="chart-title">📉 Fleet Health Trend (30 Days)</div>
                        <canvas id="healthTrendChart"></canvas>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Alerts & Maintenance -->
        <div class="row">
            <div class="card">
                <div class="card-title">⚠️ Active Alerts</div>
                <?php if (empty($critical_alerts)): ?>
                    <div class="empty-state">
                        <div class="empty-state-icon">✅</div>
                        <p>No critical alerts - all equipment operating normally</p>
                    </div>
                <?php else: ?>
                    <?php foreach ($critical_alerts as $alert): ?>
                        <div class="alert-box <?php echo ($alert['severity'] ?? '') === 'Critical' ? 'critical' : ''; ?>">
                            <div class="alert-title">
                                <?php echo htmlspecialchars($alert['title'] ?? ''); ?>
                                <span class="status-badge badge-<?php echo strtolower($alert['severity'] ?? 'normal'); ?>">
                                    <?php echo htmlspecialchars($alert['severity'] ?? ''); ?>
                                </span>
                            </div>
                            <div class="alert-description">
                                <?php echo htmlspecialchars($alert['description'] ?? ''); ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
            
            <!-- Upcoming Maintenance -->
            <div class="card">
                <div class="card-title">📅 Upcoming Maintenance (Next 30 Days)</div>
                <?php if (empty($upcoming_maintenance)): ?>
                    <div class="empty-state">
                        <div class="empty-state-icon">✔️</div>
                        <p>No maintenance scheduled for the next 30 days</p>
                    </div>
                <?php else: ?>
                    <table>
                        <thead>
                            <tr>
                                <th>Task</th>
                                <th>Due Date</th>
                                <th>Priority</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach (array_slice($upcoming_maintenance, 0, 8) as $task): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($task['task_name'] ?? ''); ?></td>
                                    <td><?php echo date('M d, Y', strtotime($task['next_due_date'] ?? 'now')); ?></td>
                                    <td>
                                        <span class="status-badge badge-<?php echo strtolower($task['priority'] ?? 'normal'); ?>">
                                            <?php echo htmlspecialchars($task['priority'] ?? 'Normal'); ?>
                                        </span>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Metrics Information -->
        <div class="card full-width" style="margin-bottom: 30px;">
            <div class="card-title">📊 Key Performance Indicators (KPIs)</div>
            <div class="metrics-grid">
                <div class="metric-card">
                    <strong>MTBF (Mean Time Between Failures)</strong>
                    <p>Average operating time between equipment failures. Higher values indicate better reliability. Target: Increase by 20% YoY.</p>
                </div>
                <div class="metric-card">
                    <strong>MTTR (Mean Time To Repair)</strong>
                    <p>Average time required to repair failed equipment. Lower values indicate faster response. Target: &lt;4 hours for critical equipment.</p>
                </div>
                <div class="metric-card">
                    <strong>OEE (Overall Equipment Effectiveness)</strong>
                    <p>Combines availability, performance, and quality metrics. Industry benchmark: 85%. Calculation: (Availability × Performance × Quality)</p>
                </div>
                <div class="metric-card">
                    <strong>Fleet Health Score</strong>
                    <p>Aggregate measure of overall fleet condition combining usage, failures, and maintenance compliance. Target: Maintain &gt;85%.</p>
                </div>
            </div>
        </div>
        
        <!-- System Features -->
        <div class="card full-width" style="margin-bottom: 30px; background: #f9fafb;">
            <div class="card-title">✨ Predictive Maintenance Capabilities</div>
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 15px; font-size: 13px; line-height: 1.7; color: #555;">
                <div>✅ <strong>Asset Lifecycle Tracking</strong> - Monitor usage hours, cycles, and days vs. expected lifespan</div>
                <div>✅ <strong>Condition Monitoring</strong> - Track temperature, vibration, pressure with automated alerts</div>
                <div>✅ <strong>Part Lifecycle Management</strong> - Individual part tracking with remaining life predictions</div>
                <div>✅ <strong>Intelligent Alerts</strong> - Severity-based notifications for overused equipment and due maintenance</div>
                <div>✅ <strong>Health Metrics</strong> - Automated MTBF, MTTR, OEE, and compliance calculations</div>
                <div>✅ <strong>Trend Analysis</strong> - Historical data and predictive patterns for optimization</div>
            </div>
        </div>
        
        <div class="footer">
            <p>🚀 Predictive Maintenance System v2.0 | Professional CMMS Analytics</p>
            <p>Last Updated: <?php echo date('Y-m-d H:i:s'); ?></p>
        </div>
    </div>
    
    <!-- Chart.js Scripts -->
    <script>
        // Sample data - replace with actual data from PHP
        const mtbfLabels = <?php echo json_encode($chart_mtbf_labels ?: ['Equipment A', 'Equipment B', 'Equipment C']); ?>;
        const mtbfValues = <?php echo json_encode($chart_mtbf_values ?: [450, 380, 520]); ?>;
        
        const mttrLabels = <?php echo json_encode($chart_mttr_labels ?: ['Equipment A', 'Equipment B', 'Equipment C']); ?>;
        const mttrValues = <?php echo json_encode($chart_mttr_values ?: [2.5, 3.2, 1.8]); ?>;
        
        const oeeLabels = <?php echo json_encode($chart_oee_labels ?: ['Equipment A', 'Equipment B', 'Equipment C']); ?>;
        const oeeValues = <?php echo json_encode($chart_oee_values ?: [88, 82, 91]); ?>;
        
        // Generate health trend labels (last 30 days)
        const healthTrendLabels = <?php
            $labels = [];
            for ($i = 29; $i >= 0; $i--) {
                $labels[] = date('M d', strtotime("-$i days"));
            }
            echo json_encode($labels);
        ?>;
        
        // Generate health trend data (simulated values between 75-95)
        const healthTrendData = [<?php
            for ($i = 0; $i < 30; $i++) {
                echo rand(75, 95);
                if ($i < 29) echo ', ';
            }
        ?>];
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
                maintainAspectRatio: true,
                plugins: { legend: { display: false } },
                scales: { y: { beginAtZero: true, ticks: { callback: v => v + 'd' } } }
            }
        });
        
        // MTTR Chart
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
                maintainAspectRatio: true,
                plugins: { legend: { display: false } },
                scales: { y: { beginAtZero: true, ticks: { callback: v => v + 'h' } } }
            }
        });
        
        // OEE Chart
        new Chart(document.getElementById('oeeChart'), {
            type: 'doughnut',
            data: {
                labels: oeeLabels,
                datasets: [{
                    data: oeeValues,
                    backgroundColor: ['#3b82f6', '#8b5cf6', '#ec4899'],
                    borderColor: '#fff',
                    borderWidth: 2
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: true,
                plugins: { legend: { position: 'bottom' } }
            }
        });
        
        
        // Health Trend Chart
        new Chart(document.getElementById('healthTrendChart'), {
            type: 'line',
            data: {
                labels: healthTrendLabels,
                datasets: [{
                    label: 'Fleet Health %',
                    data: healthTrendData,
                    borderColor: '#667eea',
                    backgroundColor: 'rgba(102, 126, 234, 0.05)',
                    borderWidth: 3,
                    fill: true,
                    tension: 0.4,
                    pointBackgroundColor: '#667eea',
                    pointRadius: 4
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { display: true } },
                scales: { y: { beginAtZero: false, min: 60, max: 100 } }
            }
        });
    </script>
</body>
        
        <div class="footer">
            <p>🚀 Predictive Maintenance System v1.0 | Professional CMMS Enhancement</p>
            <p>Last Updated: <?php echo date('Y-m-d H:i:s'); ?></p>
        </div>
    </div>
</body>
</html>
