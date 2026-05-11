<?php
/**
 * Analytics Dashboard for CMMS
 * Advanced visual insights for work orders, equipment performance, and maintenance mix.
 */

require_once 'config.inc.php';
require_once 'common.inc.php';

$months = [];
$workOrderTrend = [];
$completedPending = ['Completed' => 0, 'Pending' => 0];
$priorityBreakdown = [];
$departmentBreakdown = [];
$assetTypeBreakdown = [];
$criticalAssets = [];
$equipmentStatus = ['Running' => 0, 'Idle' => 0, 'Down' => 0, 'Other' => 0];
$downtimeHistory = [];
$failureRanking = [];
$failureModeBreakdown = [];
$maintenanceType = ['Preventive' => 0, 'Corrective' => 0, 'Predictive' => 0];

$today = new DateTime();
for ($i = 5; $i >= 0; $i--) {
    $month = (clone $today)->modify("first day of -{$i} month");
    $key = $month->format('Y-m');
    $months[] = $month->format('M Y');
    $workOrderTrend[$key] = 0;
    $downtimeHistory[$key] = 0;
}

if ($connection) {
    $trendSql = apply_tenant_filter("SELECT DATE_FORMAT(submit_date, '%Y-%m') AS ym, COUNT(*) AS cnt FROM work_orders WHERE submit_date >= DATE_SUB(CURDATE(), INTERVAL 5 MONTH) GROUP BY ym ORDER BY ym");
    $trendRows = query_to_array($trendSql);
    foreach ($trendRows as $row) {
        if (array_key_exists($row['ym'], $workOrderTrend)) {
            $workOrderTrend[$row['ym']] = intval($row['cnt']);
        }
    }

    $statusRow = query_single_row(apply_tenant_filter("SELECT SUM(CASE WHEN wo_status IN ('Completed','Closed') THEN 1 ELSE 0 END) AS completed, SUM(CASE WHEN wo_status NOT IN ('Completed','Closed','Rejected','Canceled') THEN 1 ELSE 0 END) AS pending FROM work_orders"));
    if ($statusRow) {
        $completedPending['Completed'] = intval($statusRow['completed']);
        $completedPending['Pending'] = intval($statusRow['pending']);
    }

    $priorityRows = query_to_array(apply_tenant_filter("SELECT COALESCE(priority, '0') AS priority, COUNT(*) AS cnt FROM work_orders GROUP BY priority ORDER BY CAST(priority AS UNSIGNED) ASC"));
    foreach ($priorityRows as $row) {
        $label = 'Priority ' . $row['priority'];
        if ($row['priority'] === '1') {
            $label = 'Normal';
        } elseif ($row['priority'] === '2') {
            $label = 'High';
        } elseif ($row['priority'] === '3') {
            $label = 'Urgent';
        }
        $priorityBreakdown[$label] = intval($row['cnt']);
    }

    $deptRows = query_to_array(apply_tenant_filter("SELECT COALESCE(NULLIF(requestor, ''), 'Unknown') AS requestor, COUNT(*) AS cnt FROM work_orders GROUP BY requestor ORDER BY cnt DESC LIMIT 8"));
    foreach ($deptRows as $row) {
        $departmentBreakdown[$row['requestor']] = intval($row['cnt']);
    }

    $assetRows = query_to_array(apply_tenant_filter("SELECT COALESCE(e.manufacturer, 'Unspecified') AS asset_type, COUNT(*) AS cnt FROM work_orders wo LEFT JOIN equipment e ON wo.equipment = CAST(e.id AS CHAR) GROUP BY asset_type ORDER BY cnt DESC LIMIT 6"));
    foreach ($assetRows as $row) {
        $assetTypeBreakdown[$row['asset_type']] = intval($row['cnt']);
    }

    $equipRows = query_to_array(apply_tenant_filter("SELECT status, COUNT(*) AS cnt FROM equipment GROUP BY status"));
    $mapping = [
        'running' => 'Running',
        'operational' => 'Running',
        'active' => 'Running',
        'idle' => 'Idle',
        'standby' => 'Idle',
        'waiting' => 'Idle',
        'down' => 'Down',
        'out of service' => 'Down',
        'critical' => 'Down',
        'needs repair' => 'Down',
        'fault' => 'Down',
    ];
    foreach ($equipRows as $row) {
        $statusKey = strtolower(trim($row['status']));
        $category = $mapping[$statusKey] ?? 'Other';
        $equipmentStatus[$category] += intval($row['cnt']);
    }

    $criticalRows = query_to_array(apply_tenant_filter("SELECT wo.equipment, COALESCE(e.description, wo.equipment) AS asset, COUNT(*) AS cnt FROM work_orders wo LEFT JOIN equipment e ON wo.equipment = CAST(e.id AS CHAR) WHERE wo.wo_status NOT IN ('Completed','Closed','Rejected','Canceled') GROUP BY wo.equipment ORDER BY cnt DESC LIMIT 10"));
    foreach ($criticalRows as $row) {
        $criticalAssets[] = $row;
    }

    $downtimeRows = query_to_array(apply_tenant_filter("SELECT DATE_FORMAT(submit_date, '%Y-%m') AS ym, SUM(COALESCE(down_time_hours, 0)) AS total FROM work_orders WHERE submit_date >= DATE_SUB(CURDATE(), INTERVAL 5 MONTH) GROUP BY ym ORDER BY ym"));
    foreach ($downtimeRows as $row) {
        if (array_key_exists($row['ym'], $downtimeHistory)) {
            $downtimeHistory[$row['ym']] = floatval($row['total']);
        }
    }

    $failureRows = query_to_array(apply_tenant_filter("SELECT wo.equipment, COALESCE(e.description, wo.equipment) AS asset, COUNT(*) AS cnt FROM work_orders wo LEFT JOIN equipment e ON wo.equipment = CAST(e.id AS CHAR) WHERE wo.wo_status IN ('Completed','Closed','Rejected','Hot Job') GROUP BY wo.equipment ORDER BY cnt DESC LIMIT 10"));
    foreach ($failureRows as $row) {
        $failureRanking[] = $row;
    }

    $maintenanceType = [];
    $maintenanceRows = query_to_array(apply_tenant_filter("SELECT COALESCE(NULLIF(maintenance_type, ''), 'Unspecified') AS maintenance_type, COUNT(*) AS cnt FROM work_orders GROUP BY maintenance_type ORDER BY cnt DESC"));
    foreach ($maintenanceRows as $row) {
        $maintenanceType[$row['maintenance_type']] = intval($row['cnt']);
    }

    $failureModeRows = query_to_array(apply_tenant_filter("SELECT COALESCE(NULLIF(failure_mode, ''), 'Unspecified') AS failure_mode, COUNT(*) AS cnt FROM work_orders GROUP BY failure_mode ORDER BY cnt DESC LIMIT 10"));
    foreach ($failureModeRows as $row) {
        $failureModeBreakdown[$row['failure_mode']] = intval($row['cnt']);
    }
}

$chartMonths = json_encode(array_values($months));
$trendValues = json_encode(array_values($workOrderTrend));
$completedPendingValues = json_encode(array_values($completedPending));
$priorityLabels = json_encode(array_keys($priorityBreakdown));
$priorityValues = json_encode(array_values($priorityBreakdown));
$departmentLabels = json_encode(array_keys($departmentBreakdown));
$departmentValues = json_encode(array_values($departmentBreakdown));
$assetLabels = json_encode(array_keys($assetTypeBreakdown));
$assetValues = json_encode(array_values($assetTypeBreakdown));
$downtimeValues = json_encode(array_values($downtimeHistory));
$failureModeLabels = json_encode(array_keys($failureModeBreakdown));
$failureModeValues = json_encode(array_values($failureModeBreakdown));
$maintenanceLabels = json_encode(array_keys($maintenanceType));
$maintenanceValues = json_encode(array_values($maintenanceType));
$equipmentStatusValues = json_encode(array_values($equipmentStatus));
$equipmentStatusLabels = json_encode(array_keys($equipmentStatus));
?>

<style>
    .analytics-page {font-family: Inter, system-ui, -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif; color: #1f2a37;}
    .analytics-header {display: flex; flex-wrap: wrap; justify-content: space-between; gap: 16px; margin-bottom: 24px;}
    .analytics-header h1 {margin: 0; font-size: 2rem;}
    .analytics-header p {margin: 0; color: #52606d; max-width: 600px;}
    .analytics-grid {display: grid; gap: 24px;}
    .analytics-row {display: grid; gap: 24px; grid-template-columns: 1fr 1fr;}
    .analytics-card {background: #ffffff; border: 1px solid #e2e8f0; border-radius: 18px; padding: 22px; box-shadow: 0 16px 40px rgba(15, 34, 61, 0.06);}
    .card-title {font-size: 1rem; font-weight: 700; color: #102a43; margin-bottom: 12px;}
    .metric-grid {display: grid; gap: 14px; grid-template-columns: repeat(auto-fit, minmax(140px, 1fr)); margin-top: 16px;}
    .metric-tile {background: #f8fbff; border: 1px solid #dfe9f4; border-radius: 14px; padding: 16px;}
    .metric-tile strong {display: block; font-size: 1.65rem; color: #102a43;}
    .metric-tile span {color: #627d98; font-size: 0.95rem;}
    .status-list {list-style: none; padding: 0; margin: 0;}
    .status-list li {display: flex; justify-content: space-between; align-items: center; padding: 10px 0; border-bottom: 1px solid #eef2f7;}
    .status-chip {padding: 4px 10px; border-radius: 999px; font-size: 0.85rem; font-weight: 700;}
    .status-running {background: #e9f7ef; color: #107a43;}
    .status-idle {background: #fff6dd; color: #8a6d00;}
    .status-down {background: #fde8e8; color: #912b2b;}
    .status-other {background: #e8f1ff; color: #1d4ed8;}
    .asset-table, .failure-table {width: 100%; border-collapse: collapse;}
    .asset-table th, .asset-table td, .failure-table th, .failure-table td {padding: 12px 10px; border: 1px solid #e2e8f0;}
    .asset-table th, .failure-table th {background: #f5f8fc; text-align: left;}
    .chart-wrapper {position: relative; height: 320px;}
    .analytics-footer {margin-top: 24px; display: flex; justify-content: flex-end;}
    .analytics-footer a {text-decoration: none; color: #1d4ed8; font-weight: 600;}
    @media (max-width: 1100px) {.analytics-row {grid-template-columns: 1fr;}}
</style>

<div class="analytics-page">
    <div class="analytics-header">
        <div>
            <h1>Main Analytics</h1>
            <p>Advanced visual insights for work order trends, equipment performance, and maintenance type distribution across your CMMS.</p>
        </div>
    </div>

    <div class="analytics-grid">
        <div class="analytics-card">
            <div class="card-title">A. Work Order Trends</div>
            <div class="metric-grid">
                <div class="metric-tile"><strong><?php echo $completedPending['Completed']; ?></strong><span>Completed Work Orders</span></div>
                <div class="metric-tile"><strong><?php echo $completedPending['Pending']; ?></strong><span>Pending Work Orders</span></div>
                <div class="metric-tile"><strong><?php echo array_sum($priorityBreakdown); ?></strong><span>Total Work Orders</span></div>
                <div class="metric-tile"><strong><?php echo array_sum($departmentBreakdown); ?></strong><span>Active Departments</span></div>
            </div>

            <div class="analytics-row" style="margin-top: 24px;">
                <div class="analytics-card" style="padding: 18px;">
                    <div class="card-title">Work Orders Over Time</div>
                    <div class="chart-wrapper"><canvas id="trendChart"></canvas></div>
                </div>
                <div class="analytics-card" style="padding: 18px;">
                    <div class="card-title">Completed vs Pending</div>
                    <div class="chart-wrapper"><canvas id="statusBarChart"></canvas></div>
                </div>
            </div>

            <div class="analytics-row" style="margin-top: 24px;">
                <div class="analytics-card" style="padding: 18px;">
                    <div class="card-title">By Priority</div>
                    <div class="chart-wrapper"><canvas id="priorityChart"></canvas></div>
                </div>
                <div class="analytics-card" style="padding: 18px;">
                    <div class="card-title">By Department</div>
                    <div class="chart-wrapper"><canvas id="departmentChart"></canvas></div>
                </div>
            </div>

            <div class="analytics-row" style="margin-top: 24px;">
                <div class="analytics-card" style="padding: 18px;">
                    <div class="card-title">By Asset Type</div>
                    <div class="chart-wrapper"><canvas id="assetTypeChart"></canvas></div>
                </div>
            </div>
        </div>

        <div class="analytics-card">
            <div class="card-title">B. Equipment Performance Monitoring</div>
            <ul class="status-list">
                <li><span>Running</span><span class="status-chip status-running"><?php echo $equipmentStatus['Running']; ?> 🟢</span></li>
                <li><span>Idle</span><span class="status-chip status-idle"><?php echo $equipmentStatus['Idle']; ?> 🟡</span></li>
                <li><span>Down</span><span class="status-chip status-down"><?php echo $equipmentStatus['Down']; ?> 🔴</span></li>
                <li><span>Other</span><span class="status-chip status-other"><?php echo $equipmentStatus['Other']; ?> ⚪</span></li>
            </ul>

            <div style="margin-top: 24px;">
                <div class="card-title">Top 10 Critical Assets</div>
                <?php if (empty($criticalAssets)): ?>
                    <p>No critical assets found.</p>
                <?php else: ?>
                    <table class="asset-table">
                        <thead>
                            <tr><th>Asset</th><th>Open Work Orders</th></tr>
                        </thead>
                        <tbody>
                            <?php foreach ($criticalAssets as $asset): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($asset['asset']); ?></td>
                                    <td><?php echo intval($asset['cnt']); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>

            <div class="analytics-card" style="margin-top: 24px; padding: 18px;">
                <div class="card-title">Downtime History</div>
                <div class="chart-wrapper"><canvas id="downtimeChart"></canvas></div>
            </div>

            <div style="margin-top: 24px;">
                <div class="card-title">Failure Frequency Ranking</div>
                <?php if (empty($failureRanking)): ?>
                    <p>No failure records available.</p>
                <?php else: ?>
                    <table class="failure-table">
                        <thead><tr><th>Asset</th><th>Failure Count</th></tr></thead>
                        <tbody>
                            <?php foreach ($failureRanking as $fail): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($fail['asset']); ?></td>
                                    <td><?php echo intval($fail['cnt']); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
        </div>

        <div class="analytics-card">
            <div class="card-title">C. Maintenance Type Distribution</div>
            <div class="chart-wrapper"><canvas id="maintenanceTypeChart"></canvas></div>
            <div style="margin-top: 18px;">
                <table class="asset-table">
                    <thead><tr><th>Maintenance Type</th><th>Count</th></tr></thead>
                    <tbody>
                        <?php foreach ($maintenanceType as $type => $count): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($type); ?></td>
                                <td><?php echo intval($count); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="analytics-card">
            <div class="card-title">D. Failure Mode Distribution</div>
            <div class="chart-wrapper"><canvas id="failureModeChart"></canvas></div>
            <div style="margin-top: 18px;">
                <table class="asset-table">
                    <thead><tr><th>Failure Mode</th><th>Count</th></tr></thead>
                    <tbody>
                        <?php if (empty($failureModeBreakdown)): ?>
                            <tr><td colspan="2" style="text-align:center; color:#6b7280;">No failure mode data available.</td></tr>
                        <?php else: ?>
                            <?php foreach ($failureModeBreakdown as $mode => $count): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($mode); ?></td>
                                    <td><?php echo intval($count); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="analytics-footer">
        <a href="index.php?nav=dashboard">← Back to Dashboard</a>
    </div>
</div>

<script>
(function() {
    function createChart(ctx, type, data, options) {
        if (!ctx) return null;
        return new Chart(ctx, { type: type, data: data, options: options });
    }

    const trendCtx = document.getElementById('trendChart')?.getContext('2d');
    const statusCtx = document.getElementById('statusBarChart')?.getContext('2d');
    const priorityCtx = document.getElementById('priorityChart')?.getContext('2d');
    const departmentCtx = document.getElementById('departmentChart')?.getContext('2d');
    const assetCtx = document.getElementById('assetTypeChart')?.getContext('2d');
    const downtimeCtx = document.getElementById('downtimeChart')?.getContext('2d');
    const maintenanceCtx = document.getElementById('maintenanceTypeChart')?.getContext('2d');
    const failureModeCtx = document.getElementById('failureModeChart')?.getContext('2d');

    const months = <?php echo $chartMonths; ?>;
    const trendValues = <?php echo $trendValues; ?>;
    const downtimeValues = <?php echo $downtimeValues; ?>;
    const completedPending = <?php echo $completedPendingValues; ?>;
    const priorityLabels = <?php echo $priorityLabels; ?>;
    const priorityValues = <?php echo $priorityValues; ?>;
    const departmentLabels = <?php echo $departmentLabels; ?>;
    const departmentValues = <?php echo $departmentValues; ?>;
    const assetLabels = <?php echo $assetLabels; ?>;
    const assetValues = <?php echo $assetValues; ?>;
    const failureModeLabels = <?php echo $failureModeLabels; ?>;
    const failureModeValues = <?php echo $failureModeValues; ?>;
    const maintenanceLabels = <?php echo $maintenanceLabels; ?>;
    const maintenanceValues = <?php echo $maintenanceValues; ?>;

    createChart(trendCtx, 'line', {
        labels: months,
        datasets: [{
            label: 'Work Orders',
            data: trendValues,
            borderColor: '#2563eb',
            backgroundColor: 'rgba(37, 99, 235, 0.15)',
            fill: true,
            tension: 0.35,
            pointRadius: 4
        }]
    }, { responsive: true, plugins: { legend: { display: false } }, scales: { y: { beginAtZero: true } } });

    createChart(statusCtx, 'bar', {
        labels: ['Completed', 'Pending'],
        datasets: [{
            label: 'Orders',
            data: completedPending,
            backgroundColor: ['#16a34a', '#f59e0b']
        }]
    }, { responsive: true, plugins: { legend: { display: false } }, scales: { y: { beginAtZero: true } } });

    createChart(priorityCtx, 'doughnut', {
        labels: priorityLabels,
        datasets: [{
            data: priorityValues,
            backgroundColor: ['#4f46e5', '#f97316', '#dc2626', '#0ea5e9']
        }]
    }, { responsive: true, plugins: { legend: { position: 'bottom' } } });

    createChart(departmentCtx, 'doughnut', {
        labels: departmentLabels,
        datasets: [{
            data: departmentValues,
            backgroundColor: ['#22c55e', '#38bdf8', '#fbbf24', '#f87171', '#a855f7', '#22d3ee', '#fb7185', '#4ade80']
        }]
    }, { responsive: true, plugins: { legend: { position: 'bottom' } } });

    createChart(assetCtx, 'doughnut', {
        labels: assetLabels,
        datasets: [{
            data: assetValues,
            backgroundColor: ['#9333ea', '#10b981', '#fb7185', '#38bdf8', '#fbbf24', '#64748b']
        }]
    }, { responsive: true, plugins: { legend: { position: 'bottom' } } });

    createChart(downtimeCtx, 'line', {
        labels: months,
        datasets: [{
            label: 'Downtime (hrs)',
            data: downtimeValues,
            borderColor: '#dc2626',
            backgroundColor: 'rgba(220, 38, 38, 0.15)',
            fill: true,
            tension: 0.35,
            pointRadius: 4
        }]
    }, { responsive: true, plugins: { legend: { display: false } }, scales: { y: { beginAtZero: true } } });

    createChart(failureModeCtx, 'pie', {
        labels: failureModeLabels,
        datasets: [{
            data: failureModeValues,
            backgroundColor: ['#4f46e5', '#f97316', '#dc2626', '#0ea5e9', '#22c55e', '#f59e0b', '#10b981', '#64748b', '#8b5cf6', '#f43f5e']
        }]
    }, { responsive: true, plugins: { legend: { position: 'bottom' } } });

    createChart(maintenanceCtx, 'pie', {
        labels: maintenanceLabels,
        datasets: [{
            data: maintenanceValues,
            backgroundColor: ['#2563eb', '#f59e0b', '#10b981']
        }]
    }, { responsive: true, plugins: { legend: { position: 'bottom' } } });
})();
</script>