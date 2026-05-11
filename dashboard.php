<?php
/**
 * Professional CMMS Dashboard
 * Data-driven executive overview and maintenance action center.
 */

require_once 'config.inc.php';
require_once 'common.inc.php';
if (file_exists(__DIR__ . '/libraries/metrics.php')) {
    require_once __DIR__ . '/libraries/metrics.php';
}
// Load predictive maintenance module
$predictive_loaded = false;
if (file_exists(__DIR__ . '/libraries/predictive_maintenance.php')) {
    require_once __DIR__ . '/libraries/predictive_maintenance.php';
    $predictive_loaded = function_exists('get_asset_health_overview');
}

if (!function_exists('column_exists_dashboard')) {
    function column_exists_dashboard($table, $column)
    {
        global $connection, $db_type;
        if (!$connection) {
            return false;
        }

        if ($db_type === 'sqlite') {
            // SQLite version
            $stmt = $connection->query("PRAGMA table_info('$table')");
            if ($stmt) {
                foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
                    if ($row['name'] === $column) {
                        return true;
                    }
                }
            }
            return false;
        } else {
            // MySQL version
            $result = $connection->query("SHOW COLUMNS FROM `$table` LIKE '$column'");
            return $result && $result->fetch(PDO::FETCH_ASSOC) !== false;
        }
    }
}

$today = date('Y-m-d');
$firstOfMonth = date('Y-m-01');

$currentUserRole = $_SESSION['role'] ?? '';
$approverRoles = ['maintenance manager', 'supervisor', 'manager', 'admin', 'developer'];

$summary = [
    'total_work_orders' => 0,
    'open_work_orders' => 0,
    'in_progress_work_orders' => 0,
    'completed_work_orders' => 0,
    'overdue_work_orders' => 0,
    'pending_request_approvals' => 0,
    'equipment_count' => 0,
    'critical_assets' => 0,
    'inventory_value' => 0,
    'low_stock_parts' => 0,
    'pm_compliance' => null,
    'maintenance_cost_month' => 0,
    'equipment_downtime_hours' => 0,
    'scheduled_pm_due' => 0,
    // Predictive Maintenance metrics
    'fleet_health_score' => 100,
    'critical_predictive_alerts' => 0,
    'upcoming_predictive_maintenance' => 0,
];

$status_counts = [];
$priority_counts = [];
$equipment_status_counts = [];
$work_order_trend = [];
$recent_work_orders = [];
$top_critical_assets = [];
$assets_by_location = [];
$low_stock_parts = [];
$notifications = [];
$report_links = [
    ['label' => 'SLA Compliance', 'href' => 'reports/sla_compliance.php', 'icon' => 'fas fa-file-alt'],
    ['label' => 'Inventory Analytics', 'href' => 'index.php?nav=inventory', 'icon' => 'fas fa-boxes'],
    ['label' => 'PM Management', 'href' => 'index.php?nav=pm', 'icon' => 'fas fa-tools'],
    ['label' => 'Lifecycle Analytics', 'href' => 'index.php?nav=lifecycle', 'icon' => 'fas fa-recycle'],
];

if ($connection) {
    $row = query_single_row(apply_tenant_filter("SELECT COUNT(*) AS total FROM work_orders"));
    $summary['total_work_orders'] = intval($row['total'] ?? 0);

    $statusResult = query_to_array(apply_tenant_filter("SELECT wo_status AS status, COUNT(*) AS cnt FROM work_orders GROUP BY wo_status"));
    foreach ($statusResult as $row) {
        $status = $row['status'] ?: 'Unknown';
        $count = intval($row['cnt']);
        $status_counts[$status] = $count;
        if (in_array($status, ['New', 'Pending Approval', 'Assigned', 'Approved', 'In Progress', 'Open'], true)) {
            $summary['open_work_orders'] += $count;
        }
        if (in_array($status, ['Completed', 'Closed'], true)) {
            $summary['completed_work_orders'] += $count;
        }
        if ($status === 'In Progress') {
            $summary['in_progress_work_orders'] += $count;
        }
    }

    $row = query_single_row(apply_tenant_filter("SELECT COUNT(*) AS total FROM work_orders WHERE wo_status NOT IN ('Completed','Closed','Rejected','Canceled') AND needed_date IS NOT NULL AND DATE(needed_date) < '$today'"));
    $summary['overdue_work_orders'] = intval($row['total'] ?? 0);

    $trendLabels = [];
    $trendBuckets = [];
    for ($i = 5; $i >= 0; $i--) {
        $month = new DateTime("first day of -{$i} month");
        $key = $month->format('Y-m');
        $trendLabels[] = $month->format('M Y');
        $trendBuckets[$key] = 0;
    }
    // Calculate date 5 months ago
    $fiveMonthsAgo = date('Y-m-d', strtotime('-5 months', strtotime($today)));
    $trendQuery = apply_tenant_filter("SELECT strftime('%Y-%m', submit_date) AS ym, COUNT(*) AS cnt FROM work_orders WHERE submit_date >= '$fiveMonthsAgo' GROUP BY ym ORDER BY ym");
    $trendResult = query_to_array($trendQuery);
    foreach ($trendResult as $row) {
        if (isset($trendBuckets[$row['ym']])) {
            $trendBuckets[$row['ym']] = intval($row['cnt']);
        }
    }
    $work_order_trend = array_values($trendBuckets);

    $priorityResult = query_to_array(apply_tenant_filter("SELECT CASE WHEN priority IS NULL OR priority = '' THEN 'Unspecified' ELSE priority END AS priority, COUNT(*) AS cnt FROM work_orders GROUP BY priority ORDER BY cnt DESC"));
    foreach ($priorityResult as $row) {
        $priority_counts[$row['priority']] = intval($row['cnt']);
    }

    if (table_exists('mechanics')) {
        $recentQuery = apply_tenant_filter("SELECT wo.wo_id, wo.descriptive_text, wo.equipment, wo.wo_status, wo.priority, wo.needed_date, wo.submit_date, wo.act_hours, wo.complete_date, (m.fname || ' ' || m.lname) AS mechanic_name FROM work_orders wo LEFT JOIN mechanics m ON wo.mechanic_id = m.id ORDER BY wo.submit_date DESC LIMIT 10");
    } else {
        $recentQuery = apply_tenant_filter("SELECT wo_id, descriptive_text, equipment, wo_status, priority, needed_date, submit_date, act_hours, complete_date, mechanic_id FROM work_orders ORDER BY submit_date DESC LIMIT 10");
    }
    $recentResult = query_to_array($recentQuery);
    foreach ($recentResult as $row) {
        $recent_work_orders[] = $row;
    }

    if (table_exists('equipment')) {
        $row = query_single_row(apply_tenant_filter("SELECT COUNT(*) AS total FROM equipment"));
        $summary['equipment_count'] = intval($row['total'] ?? 0);

        $equipStatusResult = query_to_array(apply_tenant_filter("SELECT status, COUNT(*) AS cnt FROM equipment GROUP BY status"));
        foreach ($equipStatusResult as $row) {
            $equipment_status_counts[$row['status'] ?: 'Unknown'] = intval($row['cnt']);
            if (in_array($row['status'], ['Down', 'Critical', 'Needs Repair', 'Out of Service', 'Failure'], true)) {
                $summary['critical_assets'] += intval($row['cnt']);
            }
        }

        $locationResult = query_to_array(apply_tenant_filter("SELECT location, COUNT(*) AS cnt FROM equipment GROUP BY location ORDER BY cnt DESC LIMIT 6"));
        foreach ($locationResult as $row) {
            $assets_by_location[] = $row;
        }
    }

    if (table_exists('parts_master')) {
        $row = query_single_row(apply_tenant_filter("SELECT COUNT(*) AS total, COALESCE(SUM(unit_cost * total_on_hand),0) AS value FROM parts_master WHERE is_active = 1"));
        $summary['inventory_value'] = floatval($row['value'] ?? 0);
        $lowStockRow = query_single_row(apply_tenant_filter("SELECT COUNT(*) AS total FROM parts_master WHERE is_active = 1 AND total_on_hand <= reorder_point"));
        $summary['low_stock_parts'] = intval($lowStockRow['total'] ?? 0);

        $lowPartsResult = query_to_array(apply_tenant_filter("SELECT part_name, part_code, total_on_hand, reorder_point, unit_cost FROM parts_master WHERE is_active = 1 AND total_on_hand <= reorder_point ORDER BY total_on_hand ASC LIMIT 5"));
        foreach ($lowPartsResult as $row) {
            $low_stock_parts[] = $row;
        }
    }

    if (table_exists('pm_schedule_log')) {
        $pmTotalRow = query_single_row("SELECT COUNT(*) AS total FROM pm_schedule_log");
        $pmTotal = intval($pmTotalRow['total'] ?? 0);

        $thirtyDaysAgo = date('Y-m-d', strtotime('-30 days', strtotime($today)));
        $pmComplete30Row = query_single_row("SELECT COUNT(*) AS total FROM pm_schedule_log WHERE status IN ('Completed','Closed') AND due_date >= '$thirtyDaysAgo'");
        $pmComplete30 = intval($pmComplete30Row['total'] ?? 0);

        $pmDue30Row = query_single_row("SELECT COUNT(*) AS total FROM pm_schedule_log WHERE due_date >= '$thirtyDaysAgo'");
        $pmDue30 = intval($pmDue30Row['total'] ?? 0);

        $summary['pm_compliance'] = $pmDue30 > 0 ? round(($pmComplete30 / $pmDue30) * 100, 1) : ($pmTotal > 0 ? round(($pmComplete30 / $pmTotal) * 100, 1) : null);

        $pmDueRow = query_single_row(apply_tenant_filter("SELECT COUNT(*) AS total FROM pm_masters WHERE status = 'Active' AND next_due_date IS NOT NULL AND DATE(next_due_date) <= '$today'"));
        $summary['scheduled_pm_due'] = intval($pmDueRow['total'] ?? 0);
    }

    if (column_exists_dashboard('work_orders', 'act_hours')) {
        $row = query_single_row(apply_tenant_filter("SELECT COALESCE(SUM(act_hours),0) AS hours FROM work_orders WHERE complete_date >= '$firstOfMonth'"));
        $workHours = floatval($row['hours'] ?? 0);
        $summary['maintenance_cost_month'] = round($workHours * 75, 2);
    }

    if (column_exists_dashboard('work_orders', 'down_time_hours')) {
        $row = query_single_row(apply_tenant_filter("SELECT COALESCE(SUM(down_time_hours),0) AS downtime FROM work_orders WHERE complete_date >= '$firstOfMonth'"));
        $summary['equipment_downtime_hours'] = floatval($row['downtime'] ?? 0);
    }

    $topAssetsResult = query_to_array(apply_tenant_filter("SELECT equipment AS asset, COUNT(*) AS open_count FROM work_orders WHERE wo_status NOT IN ('Completed','Closed','Rejected','Canceled') GROUP BY equipment ORDER BY open_count DESC LIMIT 5"));
    foreach ($topAssetsResult as $row) {
        $top_critical_assets[] = $row;
    }

    if ($summary['overdue_work_orders'] > 0) {
        $notifications[] = ['type' => 'Overdue Work Orders', 'text' => $summary['overdue_work_orders'] . ' open work orders are overdue.', 'icon' => 'fas fa-exclamation-triangle', 'class' => 'danger'];
    }
    if ($summary['low_stock_parts'] > 0) {
        $notifications[] = ['type' => 'Low Inventory', 'text' => $summary['low_stock_parts'] . ' parts below reorder point.', 'icon' => 'fas fa-box-open', 'class' => 'warning'];
    }
    if ($summary['scheduled_pm_due'] > 0) {
        $notifications[] = ['type' => 'PM Due Today', 'text' => $summary['scheduled_pm_due'] . ' preventive maintenance schedules require attention.', 'icon' => 'fas fa-tools', 'class' => 'info'];
    }

    // Load Predictive Maintenance metrics
    if ($predictive_loaded && table_exists('asset_lifecycle')) {
        try {
            $health_overview = get_asset_health_overview();
            $summary['fleet_health_score'] = intval($health_overview['health_percentage'] ?? 100);
            
            $critical_alerts = get_critical_alerts(100);
            $summary['critical_predictive_alerts'] = count($critical_alerts);
            
            $upcoming = get_upcoming_maintenance(30);
            $summary['upcoming_predictive_maintenance'] = count($upcoming);
            
            // Add critical predictive alerts to notifications
            if ($summary['critical_predictive_alerts'] > 0) {
                $notifications[] = [
                    'type' => 'Predictive Alerts',
                    'text' => $summary['critical_predictive_alerts'] . ' predictive maintenance alerts require attention.',
                    'icon' => 'fas fa-robot',
                    'class' => 'warning',
                    'href' => 'predictive_dashboard.php'
                ];
            }
        } catch (Exception $e) {
            // Predictive maintenance data not available
        }
    }

    if (table_exists('work_order_requests') && in_array($currentUserRole, $approverRoles, true)) {
        $pendingRow = query_single_row(apply_tenant_filter("SELECT COUNT(*) AS total FROM work_order_requests WHERE status = 'Pending Approval'"));
        $summary['pending_request_approvals'] = intval($pendingRow['total'] ?? 0);
        if ($summary['pending_request_approvals'] > 0) {
            $notifications[] = [
                'type' => 'Pending Request Approvals',
                'text' => $summary['pending_request_approvals'] . ' work order request(s) are awaiting approval.',
                'icon' => 'fas fa-user-check',
                'class' => 'warning',
                'href' => 'index.php?nav=work_requests'
            ];
        }
    }
}

$trendLabels = [];
for ($i = 5; $i >= 0; $i--) {
    $month = new DateTime("first day of -{$i} month");
    $trendLabels[] = $month->format('M Y');
}

function render_status_badge($status)
{
    $map = [
        'Completed' => 'success',
        'Closed' => 'secondary',
        'In Progress' => 'info',
        'Assigned' => 'primary',
        'Approved' => 'success',
        'Pending Approval' => 'warning',
        'Rejected' => 'danger',
        'Canceled' => 'danger',
        'New' => 'secondary',
        'Open' => 'primary',
    ];
    $class = $map[$status] ?? 'secondary';
    return '<span class="badge bg-' . $class . '">' . htmlspecialchars($status) . '</span>';
}
?>

<div class="page-header">
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-start gap-3">
        <div>
            <h1 class="page-title"><i class="fas fa-chart-line me-2"></i>Operations Dashboard</h1>
            <p class="page-subtitle">A unified, data-driven command center for work orders, assets, inventory, maintenance and reliability performance.</p>
        </div>
        <div class="d-flex gap-2 flex-wrap">
            <a href="work_order.php?action=create" class="btn btn-primary"><i class="fas fa-plus me-2"></i>Create Work Order</a>
            <a href="index.php?nav=pm" class="btn btn-outline-primary"><i class="fas fa-tools me-2"></i>PM Planner</a>
            <a href="index.php?nav=inventory" class="btn btn-outline-secondary"><i class="fas fa-boxes me-2"></i>Inventory</a>
        </div>
    </div>
</div>

<div class="row gy-4">
    <div class="col-12">
        <div class="card p-3 shadow-sm border-0">
            <div class="row align-items-center g-3">
                <div class="col-lg-8">
                    <div class="input-group">
                        <span class="input-group-text bg-white border-end-0"><i class="fas fa-search"></i></span>
                        <input id="dashboardSearch" type="search" class="form-control border-start-0" placeholder="Search assets, work orders, inventory..." aria-label="Search dashboard">
                        <button class="btn btn-secondary" type="button" onclick="dashboardSearch()"><i class="fas fa-arrow-right"></i></button>
                    </div>
                </div>
                <div class="col-lg-4 d-flex gap-2 justify-content-lg-end">
                    <div class="alert alert-info mb-0 py-2 px-3 d-flex align-items-center gap-2">
                        <i class="fas fa-bell"></i> Notifications
                    </div>
                    <div class="alert alert-secondary mb-0 py-2 px-3 d-flex align-items-center gap-2">
                        <i class="fas fa-user"></i> <?php echo htmlspecialchars($_SESSION['user'] ?? 'Unknown'); ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row row-cols-1 row-cols-md-2 row-cols-xl-5 g-4 mb-4">
    <div class="col">
        <div class="card shadow-sm border-0 h-100">
            <div class="card-body">
                <div class="d-flex align-items-center justify-content-between mb-3">
                    <div>
                        <h6 class="text-uppercase text-muted mb-1">Total Work Orders</h6>
                        <h2 class="mb-0"><?php echo number_format($summary['total_work_orders']); ?></h2>
                    </div>
                    <div class="badge bg-primary py-2 px-3">Live</div>
                </div>
                <p class="text-muted mb-0">Current total work order backlog across the system.</p>
            </div>
        </div>
    </div>
    <div class="col">
        <div class="card shadow-sm border-0 h-100">
            <div class="card-body">
                <div class="d-flex align-items-center justify-content-between mb-3">
                    <div>
                        <h6 class="text-uppercase text-muted mb-1">Open / In Progress</h6>
                        <h2 class="mb-0"><?php echo number_format($summary['open_work_orders'] + $summary['in_progress_work_orders']); ?></h2>
                    </div>
                    <div class="badge bg-warning text-dark py-2 px-3">Action</div>
                </div>
                <p class="text-muted mb-0">Work orders requiring technician attention.</p>
            </div>
        </div>
    </div>
    <div class="col">
        <div class="card shadow-sm border-0 h-100">
            <div class="card-body">
                <div class="d-flex align-items-center justify-content-between mb-3">
                    <div>
                        <h6 class="text-uppercase text-muted mb-1">Completed</h6>
                        <h2 class="mb-0"><?php echo number_format($summary['completed_work_orders']); ?></h2>
                    </div>
                    <div class="badge bg-success py-2 px-3">Stable</div>
                </div>
                <p class="text-muted mb-0">Work orders finished and closed.</p>
            </div>
        </div>
    </div>
    <div class="col">
        <div class="card shadow-sm border-0 h-100">
            <div class="card-body">
                <div class="d-flex align-items-center justify-content-between mb-3">
                    <div>
                        <h6 class="text-uppercase text-muted mb-1">PM Compliance</h6>
                        <h2 class="mb-0"><?php echo $summary['pm_compliance'] !== null ? number_format($summary['pm_compliance'], 1) . '%': 'N/A'; ?></h2>
                    </div>
                    <div class="badge bg-info text-dark py-2 px-3">Health</div>
                </div>
                <p class="text-muted mb-0">Preventive maintenance completion vs due schedules.</p>
            </div>
        </div>
    </div>
    <div class="col">
        <div class="card shadow-sm border-0 h-100">
            <div class="card-body">
                <div class="d-flex align-items-center justify-content-between mb-3">
                    <div>
                        <h6 class="text-uppercase text-muted mb-1">Pending Approvals</h6>
                        <h2 class="mb-0"><?php echo number_format($summary['pending_request_approvals']); ?></h2>
                    </div>
                    <div class="badge bg-warning text-dark py-2 px-3">Review</div>
                </div>
                <p class="text-muted mb-0">Requests awaiting manager or supervisor approval.</p>
            </div>
        </div>
    </div>
</div>

<div class="row g-4">
    <div class="col-xl-8">
        <div class="row g-4 mb-4">
            <div class="col-lg-6">
                <div class="card shadow-sm border-0 h-100">
                    <div class="card-body">
                        <div class="d-flex align-items-center justify-content-between mb-3">
                            <div>
                                <h6 class="text-uppercase text-muted mb-1">Maintenance Cost (This Month)</h6>
                                <h3 class="mb-0">$<?php echo number_format($summary['maintenance_cost_month'], 2); ?></h3>
                            </div>
                            <span class="text-success"><i class="fas fa-arrow-up"></i></span>
                        </div>
                        <p class="text-muted mb-0">Estimated labor-based maintenance spend in the current month.</p>
                    </div>
                </div>
            </div>
            <div class="col-lg-6">
                <div class="card shadow-sm border-0 h-100">
                    <div class="card-body">
                        <div class="d-flex align-items-center justify-content-between mb-3">
                            <div>
                                <h6 class="text-uppercase text-muted mb-1">Equipment Downtime</h6>
                                <h3 class="mb-0"><?php echo number_format($summary['equipment_downtime_hours'], 1); ?> hrs</h3>
                            </div>
                            <span class="text-danger"><i class="fas fa-clock"></i></span>
                        </div>
                        <p class="text-muted mb-0">Total recorded downtime for the month.</p>
                    </div>
                </div>
            </div>
        </div>

        <div class="row g-4 mb-4">
            <div class="col-lg-6">
                <div class="card shadow-sm border-0 h-100">
                    <div class="card-header border-0 bg-white">
                        <h5 class="card-title mb-0"><i class="fas fa-chart-line me-2"></i>Work Order Trend</h5>
                    </div>
                    <div class="card-body">
                        <canvas id="workOrderTrendChart" height="220"></canvas>
                    </div>
                </div>
            </div>
            <div class="col-lg-6">
                <div class="card shadow-sm border-0 h-100">
                    <div class="card-header border-0 bg-white">
                        <h5 class="card-title mb-0"><i class="fas fa-tasks me-2"></i>Work Order Status</h5>
                    </div>
                    <div class="card-body">
                        <canvas id="statusDistributionChart" height="220"></canvas>
                    </div>
                </div>
            </div>
        </div>

        <div class="card shadow-sm border-0 mb-4">
            <div class="card-header bg-white border-0">
                <h5 class="card-title mb-0"><i class="fas fa-clipboard-list me-2"></i>Recent Work Orders</h5>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>WO#</th>
                                <th>Asset</th>
                                <th>Technician</th>
                                <th>Priority</th>
                                <th>Status</th>
                                <th>Due</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($recent_work_orders)): ?>
                                <tr><td colspan="6" class="text-center py-4">No recent work orders found.</td></tr>
                            <?php else: ?>
                                <?php foreach ($recent_work_orders as $wo): ?>
                                    <tr>
                                        <td><a href="work_order.php?wo_id=<?php echo intval($wo['wo_id']); ?>">#<?php echo intval($wo['wo_id']); ?></a></td>
                                        <td><?php echo htmlspecialchars($wo['equipment'] ?? 'Unassigned'); ?></td>
                                        <td><?php echo htmlspecialchars($wo['mechanic_name'] ?? ($wo['mechanic_id'] ?? '—')); ?></td>
                                        <td><?php echo htmlspecialchars($wo['priority'] ?? 'Standard'); ?></td>
                                        <td><?php echo render_status_badge($wo['wo_status'] ?? 'Unknown'); ?></td>
                                        <td><?php echo !empty($wo['needed_date']) ? date('M j', strtotime($wo['needed_date'])) : '<span class="text-muted">TBD</span>'; ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <div class="col-xl-4">
        <div class="card shadow-sm border-0 mb-4">
            <div class="card-header bg-white border-0">
                <h5 class="card-title mb-0"><i class="fas fa-cogs me-2"></i>Asset Snapshot</h5>
            </div>
            <div class="card-body">
                <div class="mb-3">
                    <h6 class="mb-1">Total Assets</h6>
                    <strong><?php echo number_format($summary['equipment_count']); ?></strong>
                </div>
                <div class="mb-3">
                    <h6 class="mb-1">Critical Assets</h6>
                    <strong><?php echo number_format($summary['critical_assets']); ?></strong>
                </div>
                <div class="mb-3">
                    <h6 class="mb-1">Assets Due for Maintenance</h6>
                    <strong><?php echo number_format($summary['scheduled_pm_due']); ?></strong>
                </div>
                <div class="mb-0">
                    <h6 class="mb-1">Asset Health Score</h6>
                    <?php
                        $health = 100;
                        if ($summary['equipment_count'] > 0) {
                            $health = max(0, 100 - ($summary['critical_assets'] / $summary['equipment_count'] * 100));
                        }
                    ?>
                    <div class="progress" style="height: 12px;">
                        <div class="progress-bar bg-success" role="progressbar" style="width: <?php echo round($health); ?>%;" aria-valuenow="<?php echo round($health); ?>" aria-valuemin="0" aria-valuemax="100"></div>
                    </div>
                    <small class="text-muted"><?php echo round($health); ?>% healthy</small>
                </div>
            </div>
        </div>

        <?php if ($predictive_loaded && table_exists('asset_lifecycle')): ?>
        <div class="card shadow-sm border-0 mb-4">
            <div class="card-header bg-white border-0">
                <h5 class="card-title mb-0"><i class="fas fa-robot me-2"></i>Fleet Health & Predictive</h5>
            </div>
            <div class="card-body">
                <div class="mb-3">
                    <h6 class="mb-1">Fleet Health Score</h6>
                    <strong><?php echo number_format($summary['fleet_health_score']); ?>%</strong>
                    <div class="progress" style="height: 12px;">
                        <?php 
                            $health_color = 'success';
                            if ($summary['fleet_health_score'] < 50) $health_color = 'danger';
                            elseif ($summary['fleet_health_score'] < 75) $health_color = 'warning';
                        ?>
                        <div class="progress-bar bg-<?php echo $health_color; ?>" role="progressbar" style="width: <?php echo $summary['fleet_health_score']; ?>%;" aria-valuenow="<?php echo $summary['fleet_health_score']; ?>" aria-valuemin="0" aria-valuemax="100"></div>
                    </div>
                </div>
                <div class="mb-3">
                    <h6 class="mb-1">Critical Predictive Alerts</h6>
                    <strong><?php echo number_format($summary['critical_predictive_alerts']); ?></strong>
                </div>
                <div class="mb-3">
                    <h6 class="mb-1">Upcoming Maintenance (30 days)</h6>
                    <strong><?php echo number_format($summary['upcoming_predictive_maintenance']); ?></strong>
                </div>
                <a href="predictive_maintenance_dashboard.php" class="btn btn-sm btn-outline-primary w-100">View Predictive Dashboard</a>
            </div>
        </div>
        <?php endif; ?>

        <div class="card shadow-sm border-0 mb-4">
            <div class="card-header bg-white border-0">
                <h5 class="card-title mb-0"><i class="fas fa-boxes me-2"></i>Inventory Snapshot</h5>
            </div>
            <div class="card-body">
                <div class="mb-3">
                    <h6 class="mb-1">Inventory Value</h6>
                    <strong>$<?php echo number_format($summary['inventory_value'], 2); ?></strong>
                </div>
                <div class="mb-3">
                    <h6 class="mb-1">Low Stock Alerts</h6>
                    <strong><?php echo number_format($summary['low_stock_parts']); ?></strong>
                </div>
                <a href="index.php?nav=inventory" class="btn btn-sm btn-outline-primary">Review Parts Master</a>
            </div>
        </div>

        <div class="card shadow-sm border-0 mb-4">
            <div class="card-header bg-white border-0">
                <h5 class="card-title mb-0"><i class="fas fa-bell me-2"></i>Alerts & Notifications</h5>
            </div>
            <div class="card-body">
                <?php if (empty($notifications)): ?>
                    <div class="alert alert-success mb-0">No critical alerts. System operating within expected parameters.</div>
                <?php else: ?>
                    <?php foreach ($notifications as $alert): ?>
                        <div class="alert alert-<?php echo htmlspecialchars($alert['class']); ?> d-flex align-items-start gap-2 justify-content-between" role="alert">
                            <div class="d-flex gap-2 align-items-start">
                                <i class="<?php echo htmlspecialchars($alert['icon']); ?> fa-fw mt-1"></i>
                                <div>
                                    <strong><?php echo htmlspecialchars($alert['type']); ?></strong>
                                    <div><?php echo htmlspecialchars($alert['text']); ?></div>
                                </div>
                            </div>
                            <?php if (!empty($alert['href'])): ?>
                                <a href="<?php echo htmlspecialchars($alert['href']); ?>" class="btn btn-sm btn-outline-light">Review</a>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>

        <div class="card shadow-sm border-0">
            <div class="card-header bg-white border-0">
                <h5 class="card-title mb-0"><i class="fas fa-comments me-2"></i>Collaboration</h5>
            </div>
            <div class="card-body">
                <p class="text-muted">Team chat and work order collaboration are available through the work order notes system. Use the assigned technician and comment sections for quick updates.</p>
                <a href="index.php?nav=work_orders" class="btn btn-sm btn-primary"><i class="fas fa-comment-alt me-2"></i>Open Work Order Conversation</a>
            </div>
        </div>
    </div>
</div>

<div class="row g-4 mb-4">
    <div class="col-lg-6">
        <div class="card shadow-sm border-0">
            <div class="card-header bg-white border-0">
                <h5 class="card-title mb-0"><i class="fas fa-map-marker-alt me-2"></i>Assets by Location</h5>
            </div>
            <div class="card-body">
                <?php if (empty($assets_by_location)): ?>
                    <p class="text-muted mb-0">No location data available.</p>
                <?php else: ?>
                    <ul class="list-group list-group-flush">
                        <?php foreach ($assets_by_location as $location): ?>
                            <li class="list-group-item d-flex justify-content-between align-items-center px-0 py-2">
                                <?php echo htmlspecialchars($location['location'] ?: 'Unspecified'); ?>
                                <span class="badge bg-primary rounded-pill"><?php echo intval($location['cnt']); ?></span>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <div class="col-lg-6">
        <div class="card shadow-sm border-0">
            <div class="card-header bg-white border-0">
                <h5 class="card-title mb-0"><i class="fas fa-exclamation-circle me-2"></i>Critical Asset Workload</h5>
            </div>
            <div class="card-body">
                <?php if (empty($top_critical_assets)): ?>
                    <p class="text-muted mb-0">No critical asset workload data available.</p>
                <?php else: ?>
                    <ul class="list-group list-group-flush">
                        <?php foreach ($top_critical_assets as $asset): ?>
                            <li class="list-group-item d-flex justify-content-between align-items-center px-0 py-2">
                                <?php echo htmlspecialchars($asset['asset'] ?: 'Unassigned'); ?>
                                <span class="badge bg-danger rounded-pill"><?php echo intval($asset['open_count']); ?></span>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<div class="row g-4 mb-4">
    <div class="col-lg-6">
        <div class="card shadow-sm border-0">
            <div class="card-header bg-white border-0">
                <h5 class="card-title mb-0"><i class="fas fa-list-alt me-2"></i>Low Stock Parts</h5>
            </div>
            <div class="card-body p-0">
                <?php if (empty($low_stock_parts)): ?>
                    <div class="p-4 text-center text-muted">Inventory levels are healthy.</div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-sm mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Part</th>
                                    <th>On Hand</th>
                                    <th>Reorder</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($low_stock_parts as $part): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($part['part_name'] ?? $part['part_code'] ?? 'Unknown'); ?></td>
                                    <td><?php echo intval($part['total_on_hand']); ?></td>
                                    <td><?php echo intval($part['reorder_point']); ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="col-lg-6">
        <div class="card shadow-sm border-0">
            <div class="card-header bg-white border-0">
                <h5 class="card-title mb-0"><i class="fas fa-file-export me-2"></i>Reports & Exports</h5>
            </div>
            <div class="card-body">
                <p class="text-muted">Quick access to the most important reports and exports for performance review.</p>
                <div class="d-grid gap-2">
                    <?php foreach ($report_links as $link): ?>
                        <a href="<?php echo htmlspecialchars($link['href']); ?>" class="btn btn-outline-secondary btn-sm"><i class="<?php echo htmlspecialchars($link['icon']); ?> me-2"></i><?php echo htmlspecialchars($link['label']); ?></a>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<?php
// Determine WhatsApp number to use
$whatsapp_number = '256754974499'; // Default to developer
$whatsapp_title = 'Chat with Developer on WhatsApp';

if (!empty($_SESSION['phone']) && !empty($_SESSION['country_code'])) {
    // Format user's phone number for WhatsApp (remove any formatting)
    $user_phone = preg_replace('/[^0-9]/', '', $_SESSION['phone']);
    $country_code = preg_replace('/[^0-9]/', '', $_SESSION['country_code']);
    if ($user_phone) {
        $whatsapp_number = $country_code . $user_phone;
        $whatsapp_title = 'Chat on WhatsApp';
    }
}
?>

<!-- WhatsApp Chat Widget -->
<div class="whatsapp-chat-widget">
    <a href="https://wa.me/<?php echo $whatsapp_number; ?>?text=Hello%20from%20CMMS%20Dashboard" target="_blank" rel="noopener noreferrer" title="<?php echo htmlspecialchars($whatsapp_title); ?>">
        <svg width="28" height="28" viewBox="0 0 448 512" aria-hidden="true" focusable="false" xmlns="http://www.w3.org/2000/svg">
            <path fill="currentColor" d="M380.9 97.1C339 55.2 285.5 32 228.8 32c-67.6 0-131 26.3-178.7 74-47.8 47.8-74 111.2-74 178.7 0 31.4 8.2 62 23.8 89l-25.2 92.3 95-25.1c25.9 14.1 54.8 21.6 84.8 21.6h.1c67.6 0 131-26.3 178.7-74 47.8-47.8 74-111.3 74-178.7 0-56.7-23.2-111.1-65.1-153zm-152 328.1c-28.1 0-55.6-7.5-79.8-21.7l-5.7-3.4-56.3 14.9 14.9-54.8-3.6-6c-15.1-24.6-23-52.8-23-81.8 0-84.3 68.6-152.8 152.8-152.8 40.8 0 79.1 15.9 107.8 44.8 28.7 28.8 44.6 67.4 44.6 108.4 0 84.3-68.6 152.8-152.8 152.8zm84.5-114.1c-4.6-2.3-27.1-13.4-31.3-14.9-4.2-1.5-7.3-2.3-10.4 2.3-2.9 4.6-11.2 14.9-13.7 18-2.5 3.1-5 3.5-9.3 1.2-4.3-2.3-18.1-6.7-34.4-21.3-12.7-11.3-21.3-25.2-23.8-29.5-2.5-4.3-.3-6.6 1.9-8.8 1.9-1.9 4.2-5 6.3-7.5 2.1-2.5 2.8-4.3 4.2-7.3 1.4-3 0.7-5.6-.3-7.9-1-2.3-10.4-25.1-14.3-34.4-3.8-9-7.7-7.8-10.4-7.9-2.6-.1-5.6-.1-8.6-.1-3 0-7.9 1.1-12 5.6-4.1 4.6-15.6 15.2-15.6 37.1 0 21.9 15.9 43 18.1 45.8 2.3 2.8 31.3 47.6 75.8 66.7 10.6 4.6 18.9 7.3 25.4 9.3 10.7 3.4 20.4 2.9 28.1 1.8 8.6-1.2 27.1-11.1 31-21.8 3.9-10.7 3.9-19.8 2.7-21.8-1.2-2-4.5-3.3-9.1-5.6z"/>
        </svg>
    </a>
</div>

<style>
    .page-header { margin-bottom: 1.5rem; }
    .page-title { font-size: 2rem; margin-bottom: 0.3rem; }
    .page-subtitle { color: #6c757d; }
    .card { border-radius: 1rem; }
    .card-header { border-radius: 1rem 1rem 0 0; }

    /* WhatsApp Chat Widget Styling */
    .whatsapp-chat-widget {
        position: fixed;
        bottom: 30px;
        right: 30px;
        z-index: 1000;
    }
    .whatsapp-chat-widget a {
        display: flex;
        align-items: center;
        justify-content: center;
        width: 60px;
        height: 60px;
        background-color: #25d366;
        color: white;
        border-radius: 50%;
        font-size: 32px;
        text-decoration: none;
        box-shadow: 0 4px 12px rgba(0,0,0,0.15);
        transition: all 0.3s ease;
    }
    .whatsapp-chat-widget a i {
        color: white;
        font-size: 28px;
        line-height: 1;
    }
    .whatsapp-chat-widget a:hover {
        background-color: #20ba5a;
        box-shadow: 0 6px 16px rgba(37,211,102,0.4);
        transform: scale(1.1);
    }
    .whatsapp-chat-widget a:active {
        transform: scale(0.95);
    }
</style>

<script>
function dashboardSearch() {
    const query = document.getElementById('dashboardSearch').value.trim();
    if (!query) return;
    window.location.href = 'work_order.php?search=' + encodeURIComponent(query);
}

const workOrderTrendCtx = document.getElementById('workOrderTrendChart')?.getContext('2d');
const statusDistributionCtx = document.getElementById('statusDistributionChart')?.getContext('2d');

if (workOrderTrendCtx) {
    new Chart(workOrderTrendCtx, {
        type: 'line',
        data: {
            labels: <?php echo json_encode($trendLabels); ?>,
            datasets: [{
                label: 'Work Orders',
                data: <?php echo json_encode($work_order_trend); ?>,
                borderColor: '#4e73df',
                backgroundColor: 'rgba(78, 115, 223, 0.15)',
                fill: true,
                tension: 0.35,
                pointRadius: 4
            }]
        },
        options: {
            responsive: true,
            plugins: { legend: { display: false } },
            scales: {
                x: { grid: { display: false } },
                y: { beginAtZero: true, ticks: { precision: 0 } }
            }
        }
    });
}

if (statusDistributionCtx) {
    const statusLabels = <?php echo json_encode(array_keys($status_counts)); ?>;
    const statusData = <?php echo json_encode(array_values($status_counts)); ?>;
    new Chart(statusDistributionCtx, {
        type: 'doughnut',
        data: {
            labels: statusLabels,
            datasets: [{
                data: statusData,
                backgroundColor: ['#1cc88a', '#36b9cc', '#f6c23e', '#e74a3b', '#858796', '#5a5c69'],
                borderWidth: 1
            }]
        },
        options: {
            responsive: true,
            plugins: { legend: { position: 'bottom' } }
        }
    });
}
</script>
