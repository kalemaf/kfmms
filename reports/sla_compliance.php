<?php
/**
 * SLA Compliance Report for CMMS
 * Content only - can be included into index.php or opened directly from /reports.
 */

require_once __DIR__ . '/../config.inc.php';
require_once __DIR__ . '/../common.inc.php';

$sla_data = [];
$summary = [
    'total' => 0,
    'on_track' => 0,
    'breached' => 0,
    'completed' => 0,
    'avg_response' => null,
    'avg_resolution' => null,
    'pm_orders' => 0,
    'pm_due_30' => 0,
    'pm_overdue' => 0,
    'pm_compliance' => null,
];

if ($connection) {
    $has_mechanics = table_exists('mechanics');

    $summaryQuery = "
        SELECT
            COUNT(*) AS total,
            SUM(CASE
                WHEN wo_status IN ('Completed','Closed')
                     AND complete_date IS NOT NULL
                     AND complete_date <= COALESCE(sla_due_date, DATE_ADD(submit_date, INTERVAL 1 DAY))
                THEN 1 ELSE 0 END) AS completed,
            SUM(CASE
                WHEN wo_status NOT IN ('Completed','Closed','Rejected','Canceled')
                     AND sla_due_date IS NOT NULL
                     AND NOW() <= sla_due_date
                THEN 1 ELSE 0 END) AS on_track,
            SUM(CASE
                WHEN (wo_status NOT IN ('Completed','Closed','Rejected','Canceled')
                        AND sla_due_date IS NOT NULL
                        AND NOW() > sla_due_date)
                     OR (wo_status IN ('Completed','Closed')
                        AND complete_date IS NOT NULL
                        AND sla_due_date IS NOT NULL
                        AND complete_date > sla_due_date)
                THEN 1 ELSE 0 END) AS breached,
            AVG(response_time) AS avg_response,
            AVG(resolution_time) AS avg_resolution
        FROM work_orders
    ";
    // Apply tenant filtering
    $statsResult = $connection->query(apply_tenant_filter($summaryQuery));
    if ($statsResult) {
        $stats = $statsResult->fetch_assoc();
        $summary['total'] = intval($stats['total'] ?? 0);
        $summary['completed'] = intval($stats['completed'] ?? 0);
        $summary['on_track'] = intval($stats['on_track'] ?? 0);
        $summary['breached'] = intval($stats['breached'] ?? 0);
        $summary['avg_response'] = $stats['avg_response'] !== null ? round(floatval($stats['avg_response']), 1) : null;
        $summary['avg_resolution'] = $stats['avg_resolution'] !== null ? round(floatval($stats['avg_resolution']), 1) : null;
    }

    $pmOrders = $connection->query(apply_tenant_filter("SELECT COUNT(*) AS total FROM work_orders WHERE pm_id > 0"));
    if ($pmOrders) {
        $summary['pm_orders'] = intval($pmOrders->fetch_assoc()['total'] ?? 0);
    }

    if (table_exists('pm_schedule_log')) {
        $pmDue = $connection->query(apply_tenant_filter("SELECT COUNT(*) AS total FROM pm_schedule_log WHERE due_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 30 DAY)"));
        $summary['pm_due_30'] = $pmDue ? intval($pmDue->fetch_assoc()['total'] ?? 0) : 0;

        $pmOverdue = $connection->query(apply_tenant_filter("SELECT COUNT(*) AS total FROM pm_schedule_log WHERE status NOT IN ('Completed','Closed') AND due_date < CURDATE()"));
        $summary['pm_overdue'] = $pmOverdue ? intval($pmOverdue->fetch_assoc()['total'] ?? 0) : 0;

        $pmComplete = $connection->query(apply_tenant_filter("SELECT COUNT(*) AS total FROM pm_schedule_log WHERE status IN ('Completed','Closed') AND due_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 30 DAY)"));
        $completedDue = $pmComplete ? intval($pmComplete->fetch_assoc()['total'] ?? 0) : 0;
        if ($summary['pm_due_30'] > 0) {
            $summary['pm_compliance'] = round(($completedDue / $summary['pm_due_30']) * 100, 1);
        }
    }

    $mechanicSelect = $has_mechanics ? "CONCAT(m.fname, ' ', m.lname) AS technician," : "IFNULL(wo.mechanic_id, 'Unassigned') AS technician,";
    $mechanicJoin = $has_mechanics ? "LEFT JOIN mechanics m ON wo.mechanic_id = m.id" : "";

    $workOrderQuery = "
        SELECT
            wo.wo_id,
            wo.equipment,
            wo.description,
            wo.priority,
            wo.wo_status,
            wo.mechanic_id,
            $mechanicSelect
            COALESCE(wo.sla_due_date, DATE_ADD(wo.submit_date, INTERVAL 1 DAY)) AS sla_due_date,
            wo.submit_date,
            wo.complete_date,
            wo.pm_id,
            wo.response_time,
            wo.resolution_time
        FROM work_orders wo
        $mechanicJoin
        ORDER BY wo.submit_date DESC
        LIMIT 200
    ";
    // Apply tenant filtering
    $result = $connection->query(apply_tenant_filter($workOrderQuery));
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $row['sla_status'] = 'On Track';
            $dueDate = $row['sla_due_date'];
            $status = $row['wo_status'];
            $completeDate = $row['complete_date'];
            $referenceDue = $dueDate ?: date('Y-m-d', strtotime($row['submit_date'] . ' +1 day'));

            if (in_array($status, ['Completed', 'Closed'], true) && !empty($completeDate)) {
                $row['sla_status'] = (strtotime($completeDate) <= strtotime($referenceDue)) ? 'Completed' : 'Breached';
            } elseif (!in_array($status, ['Completed', 'Closed', 'Rejected', 'Canceled'], true)) {
                $row['sla_status'] = (time() <= strtotime($referenceDue)) ? 'On Track' : 'Breached';
            } else {
                $row['sla_status'] = 'Breached';
            }

            $sla_data[] = $row;
        }
    }
}

$sla_compliance_rate = $summary['total'] > 0 ? round((($summary['on_track'] + $summary['completed']) / $summary['total']) * 100, 1) : 0;
?>

<style>
    .sla-report {font-family: Inter, system-ui, -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif; color: #1f2a37; line-height: 1.6;}
    .sla-report h2 {margin-bottom: 0.5rem; color: #102a43;}
    .sla-report h3 {margin-top: 2rem; margin-bottom: 0.75rem; color: #243b53;}
    .sla-report .summary-grid {display: grid; grid-template-columns: repeat(auto-fit, minmax(190px, 1fr)); gap: 16px; margin-bottom: 24px;}
    .sla-report .metric-card {background: linear-gradient(180deg, #ffffff 0%, #f7fbff 100%); border: 1px solid #d9e6f4; border-radius: 18px; padding: 20px; box-shadow: 0 10px 30px rgba(15, 34, 61, 0.08);}
    .sla-report .metric-title {font-size: .78rem; color: #52606d; text-transform: uppercase; letter-spacing: .12em; margin-bottom: 12px;}
    .sla-report .metric-value {font-size: 2rem; font-weight: 800; color: #102a43;}
    .sla-report .metric-note {font-size: .95rem; color: #627d98; margin-top: 10px;}
    .sla-report .report-layout {display: grid; grid-template-columns: 1.75fr 1fr; gap: 22px; align-items: start; margin-bottom: 24px;}
    .sla-report .chart-card {display: flex; flex-direction: column; justify-content: space-between;}
    .sla-report .chart-title {font-size: .95rem; color: #334e68; text-transform: uppercase; letter-spacing: .12em; margin-bottom: 14px;}
    .sla-report table {width: 100%; border-collapse: collapse; background: #ffffff; margin-bottom: 24px;}
    .sla-report th, .sla-report td {padding: 14px 12px; border: 1px solid #e2e8f0;}
    .sla-report th {background: #f5f8fc; color: #344050; text-align: left;}
    .sla-report td {vertical-align: middle; color: #32415a;}
    .sla-report tbody tr:hover {background: #f7fbff;}
    .sla-report .badge {display: inline-flex; align-items: center; justify-content: center; padding: 6px 12px; border-radius: 999px; font-size: .82rem; font-weight: 700;}
    .sla-report .badge-success {background: #e6ffed; color: #1b7d3a;}
    .sla-report .badge-warning {background: #fff7d6; color: #9f6b00;}
    .sla-report .badge-danger {background: #ffe4e6; color: #b42318;}
    .sla-report .section-panel {background: #f8fbff; border: 1px solid #dfe9f4; border-radius: 16px; padding: 22px; margin-bottom: 24px;}
    .sla-report .section-panel p {margin: 0 0 0.85rem; color: #4a5d73;}
    .sla-report .report-footer {margin-top: 24px; padding: 18px; background: #f4f7fb; border: 1px solid #dde4ee; border-radius: 16px;}
    .sla-report a.link-button {display: inline-block; padding: 12px 18px; background: #1d6edb; color: #ffffff; border-radius: 12px; text-decoration: none; font-weight: 700;}
    @media (max-width: 992px) {.sla-report .report-layout {grid-template-columns: 1fr;}}
</style>

<div class="sla-report">
    <h2>SLA Compliance Report</h2>
    <div class="summary-grid">
        <div class="metric-card">
            <div class="metric-title">Total Work Orders</div>
            <div class="metric-value"><?php echo number_format($summary['total']); ?></div>
            <div class="metric-note">Total records considered for SLA analysis.</div>
        </div>
        <div class="metric-card">
            <div class="metric-title">On Track</div>
            <div class="metric-value"><?php echo number_format($summary['on_track']); ?></div>
            <div class="metric-note">Open work orders still within SLA window.</div>
        </div>
        <div class="metric-card">
            <div class="metric-title">Breached</div>
            <div class="metric-value"><?php echo number_format($summary['breached']); ?></div>
            <div class="metric-note">Work orders past SLA target or completed late.</div>
        </div>
        <div class="metric-card">
            <div class="metric-title">SLA Compliance</div>
            <div class="metric-value"><?php echo $sla_compliance_rate; ?>%</div>
            <div class="metric-note">Percentage of work orders meeting SLA expectations.</div>
        </div>
    </div>

    <div class="report-layout">
        <div>
            <div class="summary-grid">
                <div class="metric-card">
                    <div class="metric-title">Avg. Response Time</div>
                    <div class="metric-value"><?php echo $summary['avg_response'] !== null ? number_format($summary['avg_response'], 1) . ' hrs' : 'N/A'; ?></div>
                    <div class="metric-note">Average logged technician response time.</div>
                </div>
                <div class="metric-card">
                    <div class="metric-title">Avg. Resolution Time</div>
                    <div class="metric-value"><?php echo $summary['avg_resolution'] !== null ? number_format($summary['avg_resolution'], 1) . ' hrs' : 'N/A'; ?></div>
                    <div class="metric-note">Average time taken to complete work orders.</div>
                </div>
                <div class="metric-card">
                    <div class="metric-title">PM-linked Orders</div>
                    <div class="metric-value"><?php echo number_format($summary['pm_orders']); ?></div>
                    <div class="metric-note">Work orders generated from preventive maintenance.</div>
                </div>
                <div class="metric-card">
                    <div class="metric-title">PM Due Next 30 Days</div>
                    <div class="metric-value"><?php echo number_format($summary['pm_due_30']); ?></div>
                    <div class="metric-note">Upcoming PM schedule events that need planning.</div>
                </div>
            </div>
        </div>
        <aside class="metric-card chart-card">
            <div class="chart-title">SLA Status Distribution</div>
            <div style="flex:1; display:flex; align-items:center; justify-content:center; min-height:280px; position:relative;">
                <canvas id="slaStatusChart" style="max-width:100%;"></canvas>
                <div id="slaChartPlaceholder" style="position:absolute; inset:0; display:flex; align-items:center; justify-content:center; color:#627d98; font-size:0.95rem;">
                    Loading chart...
                </div>
            </div>
        </aside>
    </div>

    <div class="sla-report section-panel">
        <p><strong>What this report shows</strong></p>
        <p>Work order SLA health is evaluated against the SLA due date. Completed work orders are validated against the due deadline, and open work orders are flagged as breached once the current date passes the SLA due date.</p>
        <p>Technician assignments and preventive maintenance linkage are now surfaced to help operations teams prioritize response and maintenance execution.</p>
    </div>

    <h3>Work Order SLA Status</h3>
    <?php if (empty($sla_data)): ?>
        <p>No work order data available for SLA analysis.</p>
    <?php else: ?>
        <table>
            <thead>
                <tr>
                    <th>WO</th>
                    <th>Asset</th>
                    <th>Technician</th>
                    <th>Priority</th>
                    <th>Status</th>
                    <th>Submit Date</th>
                    <th>SLA Due</th>
                    <th>PM Link</th>
                    <th>SLA Status</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($sla_data as $wo): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($wo['wo_id']); ?></td>
                        <td><?php echo htmlspecialchars($wo['equipment'] ?: 'Unassigned'); ?></td>
                        <td><?php echo htmlspecialchars($wo['technician'] ?: 'Unassigned'); ?></td>
                        <td><?php echo htmlspecialchars($wo['priority'] !== null ? $wo['priority'] : 'Standard'); ?></td>
                        <td><?php echo htmlspecialchars($wo['wo_status']); ?></td>
                        <td><?php echo htmlspecialchars($wo['submit_date']); ?></td>
                        <td><?php echo htmlspecialchars($wo['sla_due_date']); ?></td>
                        <td><?php echo !empty($wo['pm_id']) ? 'PM#' . intval($wo['pm_id']) : '—'; ?></td>
                        <td>
                            <?php
                                $status = $wo['sla_status'];
                                $badgeClass = 'badge-warning';
                                if ($status === 'Completed') {
                                    $badgeClass = 'badge-success';
                                } elseif ($status === 'Breached') {
                                    $badgeClass = 'badge-danger';
                                }
                            ?>
                            <span class="badge <?php echo $badgeClass; ?>"><?php echo htmlspecialchars($status); ?></span>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>

    <div class="report-footer">
        <strong>Note:</strong> This report is optimized for SLA and PM analysis. Use the preventative maintenance module and technician assignments to reduce breaches and improve response performance.
    </div>

    <p style="margin-top: 18px;"><a class="link-button" href="../index.php">Return to Dashboard</a></p>
</div>

<script>
(function() {
    const totals = [<?php echo $summary['on_track']; ?>, <?php echo $summary['completed']; ?>, <?php echo $summary['breached']; ?>];
    const chartElement = document.getElementById('slaStatusChart');
    const placeholder = document.getElementById('slaChartPlaceholder');

    function renderChart() {
        if (!chartElement) {
            return;
        }

        const hasData = totals.some(v => v > 0);
        if (!hasData) {
            if (placeholder) {
                placeholder.textContent = 'No SLA status data available yet.';
            }
            return;
        }

        if (placeholder) {
            placeholder.style.display = 'none';
        }

        new Chart(chartElement.getContext('2d'), {
            type: 'doughnut',
            data: {
                labels: ['On Track', 'Completed', 'Breached'],
                datasets: [{
                    data: totals,
                    backgroundColor: ['#34a853', '#1a73e8', '#ea4335'],
                    borderColor: ['#ffffff', '#ffffff', '#ffffff'],
                    borderWidth: 2,
                    hoverOffset: 10
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom',
                        labels: {
                            boxWidth: 12,
                            padding: 14,
                            color: '#334e68'
                        }
                    }
                }
            }
        });
    }

    if (typeof Chart !== 'undefined') {
        renderChart();
        return;
    }

    const script = document.createElement('script');
    script.src = 'https://cdn.jsdelivr.net/npm/chart.js';
    script.onload = renderChart;
    document.head.appendChild(script);
})();
</script>

