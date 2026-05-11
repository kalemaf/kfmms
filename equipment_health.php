<?php
/**
 * Equipment Health & Predictive Maintenance Details
 * Displays comprehensive health status and recommendations for a specific equipment
 */

require_once 'config.inc.php';
require_once 'common.inc.php';
require_once 'libraries/predictive_maintenance.php';
require_once 'libraries/predictive_integration.php';

$equipment_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if (!$equipment_id) {
    die('Equipment ID required');
}

// Verify access
$equipment = query_single_row("
    SELECT * FROM equipment WHERE id = " . $equipment_id . " AND tenant_id = " . (int)($_SESSION['tenant_id'] ?? 1)
);

if (!$equipment) {
    die('Equipment not found');
}

// Get comprehensive health data
$health = get_equipment_health_status($equipment_id);
$schedule = get_equipment_maintenance_schedule($equipment_id);
$lifecycle = db_query_row_params("
    SELECT * FROM asset_lifecycle
    WHERE equipment_id = ? AND tenant_id = ?",
    [$equipment_id, $_SESSION['tenant_id'] ?? 1]
);

// Get work order history
$workorders = db_query_all_params("
    SELECT * FROM work_orders
    WHERE equipment_id = (SELECT equipment_id FROM equipment WHERE equipment_id = ?)
    AND tenant_id = ?
    ORDER BY complete_date DESC
    LIMIT 20",
    [$equipment_id, $_SESSION['tenant_id'] ?? 1]
);

// Get condition monitoring data
$monitoring = db_query_all_params("
    SELECT * FROM condition_monitoring
    WHERE equipment_id = ?
    AND tenant_id = ?
    ORDER BY recorded_at DESC
    LIMIT 10",
    [$equipment_id, $_SESSION['tenant_id'] ?? 1]
);

?>
<!DOCTYPE html>
<html>
<head>
    <title>Equipment Health: <?php echo htmlspecialchars($equipment['equipment'] ?? 'Unknown'); ?></title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { 
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; 
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 30px 20px;
        }
        .container { 
            max-width: 1400px; 
            margin: 0 auto; 
            background: white; 
            padding: 40px; 
            border-radius: 12px; 
            box-shadow: 0 8px 32px rgba(0,0,0,0.15);
        }
        .back-link { 
            display: inline-flex;
            align-items: center;
            margin-bottom: 25px; 
            color: #667eea; 
            text-decoration: none;
            font-weight: 500;
            transition: all 0.3s ease;
        }
        .back-link:hover { 
            color: #764ba2;
            transform: translateX(-5px);
        }
        
        /* Header Styling */
        .header { 
            display: flex; 
            justify-content: space-between; 
            align-items: flex-start; 
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            padding: 30px;
            border-radius: 12px;
            color: white;
            margin-bottom: 30px;
            box-shadow: 0 4px 15px rgba(102, 126, 234, 0.2);
        }
        .header h1 { 
            margin: 0 0 8px 0;
            font-size: 32px;
            font-weight: 700;
        }
        .header p {
            opacity: 0.95;
            font-size: 15px;
        }
        .status-badge { 
            font-size: 16px; 
            font-weight: 600; 
            padding: 15px 25px; 
            border-radius: 8px;
            text-align: center;
            min-width: 150px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
            transition: transform 0.3s ease;
        }
        .status-badge:hover { transform: translateY(-2px); }
        .status-healthy { background: #10b981; color: white; }
        .status-caution { background: #f59e0b; color: white; }
        .status-warning { background: #ef4444; color: white; }
        .status-critical { background: #dc2626; color: white; }
        
        /* Grid Layout */
        .grid { 
            display: grid; 
            grid-template-columns: repeat(auto-fit, minmax(350px, 1fr)); 
            gap: 25px; 
            margin-bottom: 30px; 
        }
        
        /* Card Styling */
        .card { 
            border: none;
            padding: 25px; 
            border-radius: 12px; 
            background: white;
            box-shadow: 0 2px 12px rgba(0,0,0,0.08);
            transition: all 0.3s ease;
            border-left: 5px solid #667eea;
        }
        .card:hover {
            box-shadow: 0 8px 24px rgba(0,0,0,0.12);
            transform: translateY(-3px);
        }
        .card h3 { 
            margin: 0 0 20px 0; 
            color: #1f2937;
            font-size: 18px;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        /* Metrics */
        .metric { 
            display: flex; 
            justify-content: space-between; 
            align-items: center;
            padding: 12px 0; 
            border-bottom: 1px solid #e5e7eb;
            transition: background 0.2s ease;
        }
        .metric:hover { background: #f9fafb; padding: 12px 8px; border-radius: 4px; }
        .metric:last-child { border-bottom: none; }
        .metric-label { 
            font-weight: 500; 
            color: #6b7280;
            font-size: 14px;
        }
        .metric-value { 
            text-align: right;
            color: #1f2937;
            font-weight: 600;
            font-size: 15px;
        }
        
        /* Progress Bar */
        .progress-bar { 
            width: 100%; 
            height: 28px; 
            background: #e5e7eb; 
            border-radius: 8px; 
            overflow: hidden; 
            margin: 15px 0;
            box-shadow: inset 0 2px 4px rgba(0,0,0,0.06);
        }
        .progress-fill { 
            height: 100%; 
            display: flex; 
            align-items: center; 
            justify-content: center; 
            color: white; 
            font-weight: 600; 
            font-size: 12px;
            transition: width 0.4s ease;
        }
        .progress-green { background: linear-gradient(90deg, #10b981, #059669); }
        .progress-yellow { background: linear-gradient(90deg, #f59e0b, #d97706); }
        .progress-red { background: linear-gradient(90deg, #ef4444, #dc2626); }
        
        /* Recommendations */
        .recommendation { 
            background: #f0f9ff; 
            border-left: 5px solid #3b82f6; 
            padding: 15px; 
            margin: 12px 0; 
            border-radius: 6px;
            color: #1e40af;
            font-size: 14px;
            line-height: 1.6;
            transition: all 0.3s ease;
        }
        .recommendation:hover {
            background: #e0f2fe;
            box-shadow: 0 4px 12px rgba(59, 130, 246, 0.1);
        }
        .recommendation.urgent { 
            background: #fef2f2; 
            border-left-color: #dc2626;
            color: #7f1d1d;
        }
        
        /* Table Styling */
        table { 
            width: 100%; 
            border-collapse: collapse; 
            margin-top: 15px;
            font-size: 14px;
        }
        th { 
            background: #f3f4f6; 
            padding: 15px 12px; 
            text-align: left; 
            font-weight: 600;
            color: #374151;
            border-bottom: 2px solid #e5e7eb;
        }
        td { 
            padding: 14px 12px; 
            border-bottom: 1px solid #f3f4f6;
        }
        tr:hover { 
            background: #f9fafb;
        }
        tbody tr:last-child td {
            border-bottom: none;
        }
        
        /* Status Badges in Tables */
        .status-badge-small {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 6px;
            font-size: 12px;
            font-weight: 600;
            color: white;
            text-align: center;
            min-width: 80px;
        }
        
        /* Maintenance Schedule Item */
        .schedule-item {
            margin-bottom: 15px; 
            padding: 15px; 
            background: #f9fafb;
            border-left: 5px solid #e5e7eb;
            border-radius: 6px;
            transition: all 0.3s ease;
        }
        .schedule-item:hover {
            box-shadow: 0 4px 12px rgba(0,0,0,0.08);
            transform: translateX(5px);
        }
        .schedule-item strong {
            display: block;
            color: #1f2937;
            margin-bottom: 8px;
            font-size: 15px;
        }
        .schedule-item-meta {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-top: 10px;
            font-size: 13px;
        }
        
        /* Urgency Badges */
        .urgency-urgent { 
            background: #dc2626; 
            border-left-color: #dc2626;
        }
        .urgency-soon { 
            background: #f59e0b;
            border-left-color: #f59e0b;
        }
        .urgency-normal { 
            background: #10b981;
            border-left-color: #10b981;
        }
        
        /* Alerts */
        .alert { 
            padding: 15px 20px; 
            margin: 15px 0; 
            border-radius: 8px;
            border-left: 5px solid;
            font-size: 14px;
            line-height: 1.6;
        }
        .alert-success { 
            background: #f0fdf4; 
            color: #166534; 
            border-left-color: #10b981;
        }
        .alert-warning { 
            background: #fffbeb; 
            color: #92400e; 
            border-left-color: #f59e0b;
        }
        .alert-danger { 
            background: #fef2f2; 
            color: #7f1d1d; 
            border-left-color: #dc2626;
        }
        
        /* Responsive */
        @media (max-width: 768px) {
            .container { padding: 20px; }
            .header { 
                flex-direction: column;
                gap: 20px;
                padding: 20px;
            }
            .header h1 { font-size: 24px; }
            .grid { grid-template-columns: 1fr; }
            .card { padding: 20px; }
            table { font-size: 13px; }
            th, td { padding: 10px 8px; }
        }
    </style>
</head>
<body>
<div class="container">
    <a href="equipment.php" class="back-link">← Back to Equipment</a>
    
    <div class="header">
        <div>
            <h1><?php echo htmlspecialchars($equipment['equipment'] ?? 'Equipment'); ?></h1>
            <p style="margin: 5px 0; color: #666;">
                <?php echo htmlspecialchars($equipment['location'] ?? 'Unknown Location'); ?> | 
                <?php echo htmlspecialchars($equipment['manufacturer'] ?? 'Unknown Manufacturer'); ?> 
                <?php echo htmlspecialchars($equipment['model'] ?? ''); ?>
            </p>
        </div>
        <div class="status-badge status-<?php 
            echo strtolower(str_replace(' ', '-', $health['status'] ?? 'unknown')); 
        ?>">
            <?php echo htmlspecialchars($health['status'] ?? 'Unknown'); ?><br>
            <small><?php echo $health['health_percentage'] ?? 0; ?>% Used</small>
        </div>
    </div>

    <!-- Health Overview -->
    <div class="grid">
        <div class="card">
            <h3>📊 Equipment Lifecycle</h3>
            <div class="metric">
                <span class="metric-label">Health Status:</span>
                <span class="metric-value"><strong><?php echo htmlspecialchars($health['status'] ?? 'Unknown'); ?></strong></span>
            </div>
            <div class="metric">
                <span class="metric-label">Usage Level:</span>
                <span class="metric-value"><?php echo $health['health_percentage'] ?? 0; ?>%</span>
            </div>
            <div class="progress-bar">
                <div class="progress-fill progress-<?php 
                    $pct = $health['health_percentage'] ?? 0;
                    echo $pct > 80 ? 'red' : ($pct > 60 ? 'yellow' : 'green');
                ?>" style="width: <?php echo min(100, max(0, $health['health_percentage'] ?? 0)); ?>%">
                    <?php echo $health['health_percentage'] ?? 0; ?>%
                </div>
            </div>
            <div class="metric">
                <span class="metric-label">Installation Date:</span>
                <span class="metric-value"><?php echo $lifecycle['installation_date'] ?? 'Unknown'; ?></span>
            </div>
            <div class="metric">
                <span class="metric-label">Last Service:</span>
                <span class="metric-value"><?php echo $lifecycle['last_service_date'] ?? 'Never'; ?></span>
            </div>
        </div>

        <div class="card">
            <h3>⚙️ Performance Metrics</h3>
            <div class="metric">
                <span class="metric-label">MTBF (Days):</span>
                <span class="metric-value"><?php echo $health['mtbf_days'] ?? 'N/A'; ?> days</span>
            </div>
            <div class="metric">
                <span class="metric-label">Recent Failures (30d):</span>
                <span class="metric-value"><?php echo $health['recent_failures_30days'] ?? 0; ?> times</span>
            </div>
            <div class="metric">
                <span class="metric-label">Active Alerts:</span>
                <span class="metric-value">
                    <strong style="color: <?php echo $health['active_alerts'] > 0 ? '#dc3545' : '#28a745'; ?>">
                        <?php echo $health['active_alerts'] ?? 0; ?>
                    </strong>
                </span>
            </div>
            <div class="metric">
                <span class="metric-label">Remaining Lifecycle:</span>
                <span class="metric-value"><?php echo $health['remaining_lifecycle_percentage'] ?? 0; ?>%</span>
            </div>
            <div class="metric">
                <span class="metric-label">Current Status:</span>
                <span class="metric-value"><?php echo htmlspecialchars($equipment['status'] ?? 'Unknown'); ?></span>
            </div>
        </div>
    </div>

    <!-- Recommendations -->
    <?php if (!empty($health['maintenance_recommendations'])): ?>
    <div class="card">
        <h3>💡 Maintenance Recommendations</h3>
        <?php foreach ($health['maintenance_recommendations'] as $rec): ?>
            <div class="recommendation <?php echo strpos($rec, 'URGENT') !== false ? 'urgent' : ''; ?>">
                <?php echo htmlspecialchars($rec); ?>
            </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <!-- Maintenance Schedule -->
    <?php if (!empty($schedule)): ?>
    <div class="card">
        <h3>📅 Recommended Maintenance Schedule</h3>
        <?php foreach ($schedule as $item): ?>
            <div class="schedule-item urgency-<?php echo strtolower($item['urgency'] ?? 'normal'); ?>">
                <strong><?php echo htmlspecialchars($item['type']); ?></strong>
                <div class="schedule-item-meta">
                    <span style="font-size: 13px; color: #6b7280;">
                        <?php 
                        if (isset($item['remaining_weeks'])) {
                            echo "Remaining: " . $item['remaining_weeks'] . " weeks";
                        } elseif (isset($item['remaining_days'])) {
                            echo "Remaining: " . $item['remaining_days'] . " days";
                        } elseif (isset($item['remaining_hours'])) {
                            echo "Remaining: " . round($item['remaining_hours']) . " hours";
                        } elseif (isset($item['remaining_cycles'])) {
                            echo "Remaining: " . $item['remaining_cycles'] . " cycles";
                        }
                        ?>
                    </span>
                    <span class="status-badge-small" style="background: <?php 
                        echo $item['urgency'] === 'Urgent' ? '#dc2626' : ($item['urgency'] === 'Soon' ? '#f59e0b' : '#10b981'); 
                    ?>;">
                        <?php echo htmlspecialchars($item['urgency']); ?>
                    </span>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <!-- Condition Monitoring -->
    <?php if (!empty($monitoring)): ?>
    <div class="card">
        <h3>📈 Condition Monitoring History</h3>
        <table>
            <thead>
                <tr>
                    <th>Date</th>
                    <th>Parameter</th>
                    <th>Value</th>
                    <th>Status</th>
                    <th>Notes</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($monitoring as $record): ?>
                <tr>
                    <td><?php echo htmlspecialchars(substr($record['recorded_at'], 0, 10)); ?></td>
                    <td><?php echo htmlspecialchars($record['parameter_type'] ?? ''); ?></td>
                    <td><?php echo htmlspecialchars($record['measured_value'] ?? 0) . ' ' . htmlspecialchars($record['unit'] ?? ''); ?></td>
                    <td>
                        <span class="status-badge-small" style="background:
                        <?php echo $record['status'] === 'Critical' ? '#dc2626' : ($record['status'] === 'Warning' ? '#f59e0b' : '#10b981'); ?>;">
                            <?php echo htmlspecialchars($record['status'] ?? ''); ?>
                        </span>
                    </td>
                    <td><?php echo htmlspecialchars($record['notes'] ?? ''); ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>

    <!-- Work Order History -->
    <?php if (!empty($workorders)): ?>
    <div class="card">
        <h3>🔧 Recent Work Order History (Last 20)</h3>
        <table>
            <thead>
                <tr>
                    <th>WO#</th>
                    <th>Date</th>
                    <th>Status</th>
                    <th>Description</th>
                    <th>Hours</th>
                    <th>Cost</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($workorders as $wo): ?>
                <tr>
                    <td><?php echo htmlspecialchars($wo['wo_id'] ?? ''); ?></td>
                    <td><?php echo htmlspecialchars(substr($wo['complete_date'] ?? $wo['work_open_date'], 0, 10)); ?></td>
                    <td>
                        <span class="status-badge-small" style="background:
                        <?php echo $wo['wo_status'] === 'Completed' || $wo['wo_status'] === 'Closed' ? '#10b981' : '#f59e0b'; ?>;">
                            <?php echo htmlspecialchars($wo['wo_status'] ?? ''); ?>
                        </span>
                    </td>
                    <td><?php echo htmlspecialchars(substr($wo['description'] ?? '', 0, 50)); ?></td>
                    <td><?php echo htmlspecialchars($wo['hours_worked'] ?? 0); ?>h</td>
                    <td style="font-weight: 600; color: #10b981;">$<?php echo number_format($wo['cost'] ?? 0, 2); ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>

</div>
</body>
</html>
