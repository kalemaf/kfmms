<?php
/**
 * Artisan Performance Dashboard - Manager Only
 * 
 * Displays:
 * - Team performance overview
 * - Individual artisan scores
 * - SLA compliance metrics
 * - Repeat failure tracking
 * - Performance trends
 * 
 * Access: Managers and supervisors only
 */

require_once 'config.inc.php';
require_once 'common.inc.php';
require_once 'libraries/slaService.php';
require_once 'libraries/performanceService.php';
require_once 'libraries/repeatFailureService.php';
require_once 'libraries/performance_schema.php';

// Check authentication and authorization
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

// Only managers and supervisors can access
$user_role = $_SESSION['role'] ?? 'operator';
if (!in_array($user_role, ['manager', 'maintenance manager', 'supervisor', 'admin'])) {
    die('Access Denied: This dashboard is for managers only. Your role: ' . $user_role);
}

// Initialize tables if needed
initialize_performance_monitoring_tables($connection);

$tenant_id = $_SESSION['tenant_id'] ?? 1;

// Get filter parameters
$filter_period = $_GET['period'] ?? 'monthly'; // daily, weekly, monthly, yearly
$filter_artisan = $_GET['artisan'] ?? null;
$filter_sort = $_GET['sort'] ?? 'overall_score'; // overall_score, response_sla_percentage, completion_sla_percentage

// Determine period dates
$today = date('Y-m-d');
switch ($filter_period) {
    case 'daily':
        $period_start = $today;
        $period_end = $today;
        $period_label = 'Today';
        break;
    case 'weekly':
        $period_start = date('Y-m-d', strtotime('monday this week'));
        $period_end = date('Y-m-d', strtotime('sunday this week'));
        $period_label = 'This Week';
        break;
    case 'monthly':
        $period_start = date('Y-m-01');
        $period_end = date('Y-m-t');
        $period_label = 'This Month';
        break;
    case 'yearly':
        $period_start = date('Y-01-01');
        $period_end = date('Y-12-31');
        $period_label = 'This Year';
        break;
    default:
        $period_start = date('Y-m-01');
        $period_end = date('Y-m-t');
        $period_label = 'This Month';
}

// Get team performance
$team_performance = get_team_performance_summary($filter_sort, $period_start, $period_end);

// Get artisan details for single view
$selected_artisan = null;
$artisan_detail = null;
$artisan_trend = null;
$artisan_repeat_failures = null;

if ($filter_artisan) {
    foreach ($team_performance as $artisan) {
        if ($artisan['artisan_id'] == $filter_artisan) {
            $selected_artisan = $artisan;
            break;
        }
    }
    
    if ($selected_artisan) {
        $artisan_trend = get_performance_trend($filter_artisan, 6);
        $artisan_repeat_failures = get_artisan_repeat_failures($filter_artisan, $period_start, $period_end);
    }
}

// Get chronic problem assets
$chronic_assets = get_chronic_failure_assets(3);

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Artisan Performance Dashboard - Manager</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.1.3/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        body {
            background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
            min-height: 100vh;
            padding: 20px 0;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }

        .dashboard-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 30px;
            border-radius: 12px;
            margin-bottom: 30px;
            box-shadow: 0 8px 24px rgba(102, 126, 234, 0.3);
        }

        .dashboard-title {
            font-size: 28px;
            font-weight: 700;
            margin-bottom: 10px;
        }

        .dashboard-subtitle {
            font-size: 14px;
            opacity: 0.9;
        }

        .filter-section {
            background: white;
            padding: 20px;
            border-radius: 12px;
            margin-bottom: 20px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
        }

        .kpi-card {
            background: white;
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.07);
            border-left: 4px solid;
            transition: all 0.3s ease;
        }

        .kpi-card:hover {
            box-shadow: 0 8px 16px rgba(0, 0, 0, 0.1);
            transform: translateY(-2px);
        }

        .kpi-card.excellent { border-left-color: #10b981; }
        .kpi-card.good { border-left-color: #3b82f6; }
        .kpi-card.satisfactory { border-left-color: #f59e0b; }
        .kpi-card.poor { border-left-color: #ef4444; }

        .kpi-value {
            font-size: 32px;
            font-weight: 700;
            color: #667eea;
            margin-bottom: 8px;
        }

        .kpi-label {
            font-size: 13px;
            color: #718096;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            font-weight: 600;
        }

        .performance-table {
            background: white;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.07);
        }

        .performance-table thead {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            font-weight: 600;
        }

        .performance-table tbody tr:hover {
            background: #f8f9fa;
        }

        .score-badge {
            display: inline-block;
            padding: 8px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 700;
            text-transform: uppercase;
        }

        .score-excellent { background: #d1fae5; color: #065f46; }
        .score-good { background: #dbeafe; color: #1e3a8a; }
        .score-satisfactory { background: #fed7aa; color: #92400e; }
        .score-poor { background: #fee2e2; color: #7f1d1d; }

        .metric-progress {
            height: 6px;
            background: #e2e8f0;
            border-radius: 3px;
            overflow: hidden;
            margin-top: 8px;
        }

        .metric-progress-bar {
            height: 100%;
            background: linear-gradient(90deg, #667eea 0%, #764ba2 100%);
            transition: width 0.3s ease;
        }

        .chart-container {
            position: relative;
            height: 350px;
            background: white;
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.07);
        }

        .trend-chart-title {
            font-size: 16px;
            font-weight: 700;
            margin-bottom: 20px;
            color: #2d3748;
        }

        .alert-section {
            background: #fff5f5;
            border-left: 4px solid #ef4444;
            padding: 16px;
            border-radius: 8px;
            margin-bottom: 20px;
        }

        .alert-section-title {
            color: #742a2a;
            font-weight: 700;
            margin-bottom: 12px;
        }

        .repeat-failure-item {
            background: white;
            padding: 12px;
            margin-bottom: 8px;
            border-left: 3px solid #ef4444;
            border-radius: 6px;
        }

        @media (max-width: 1024px) {
            .dashboard-title { font-size: 24px; }
        }

        @media (max-width: 768px) {
            .dashboard-header { padding: 20px; }
            .dashboard-title { font-size: 20px; }
        }
    </style>
</head>
<body>
    <div class="container-fluid">
        <!-- Header -->
        <div class="dashboard-header">
            <div class="dashboard-title">
                <i class="fas fa-chart-line"></i> Artisan Performance Dashboard
            </div>
            <div class="dashboard-subtitle">Monitor SLA compliance and artisan performance</div>
        </div>

        <!-- Filters -->
        <div class="filter-section">
            <form class="row g-3" method="GET">
                <div class="col-md-3">
                    <label class="form-label">Period</label>
                    <select class="form-select" name="period" onchange="this.form.submit()">
                        <option value="daily" <?= $filter_period === 'daily' ? 'selected' : '' ?>>Today</option>
                        <option value="weekly" <?= $filter_period === 'weekly' ? 'selected' : '' ?>>This Week</option>
                        <option value="monthly" <?= $filter_period === 'monthly' ? 'selected' : '' ?>>This Month</option>
                        <option value="yearly" <?= $filter_period === 'yearly' ? 'selected' : '' ?>>This Year</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Sort By</label>
                    <select class="form-select" name="sort" onchange="this.form.submit()">
                        <option value="overall_score" <?= $filter_sort === 'overall_score' ? 'selected' : '' ?>>Overall Score</option>
                        <option value="response_sla_percentage" <?= $filter_sort === 'response_sla_percentage' ? 'selected' : '' ?>>Response SLA %</option>
                        <option value="completion_sla_percentage" <?= $filter_sort === 'completion_sla_percentage' ? 'selected' : '' ?>>Completion SLA %</option>
                        <option value="first_time_fix_percentage" <?= $filter_sort === 'first_time_fix_percentage' ? 'selected' : '' ?>>First-Time Fix %</option>
                        <option value="total_completed" <?= $filter_sort === 'total_completed' ? 'selected' : '' ?>>Completed Tasks</option>
                    </select>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Period Range</label>
                    <p class="text-muted mb-0">
                        <i class="far fa-calendar"></i>
                        <?= $period_start ?> to <?= $period_end ?> (<?= $period_label ?>)
                    </p>
                </div>
            </form>
        </div>

        <!-- Team Summary Stats -->
        <div class="row mb-4">
            <div class="col-md-3">
                <div class="kpi-card">
                    <div class="kpi-value"><?= count($team_performance) ?></div>
                    <div class="kpi-label">Total Artisans</div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="kpi-card">
                    <div class="kpi-value">
                        <?php
                        $total_assigned = array_sum(array_column($team_performance, 'total_assigned'));
                        echo $total_assigned;
                        ?>
                    </div>
                    <div class="kpi-label">Total Assigned Tasks</div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="kpi-card">
                    <div class="kpi-value">
                        <?php
                        $total_completed = array_sum(array_column($team_performance, 'total_completed'));
                        $completion_rate = $total_assigned > 0 ? round(($total_completed / $total_assigned) * 100, 1) : 0;
                        echo $completion_rate . '%';
                        ?>
                    </div>
                    <div class="kpi-label">Team Completion Rate</div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="kpi-card">
                    <div class="kpi-value">
                        <?php
                        $avg_score = count($team_performance) > 0 
                            ? round(array_sum(array_column($team_performance, 'overall_score')) / count($team_performance), 1)
                            : 0;
                        echo $avg_score;
                        ?>
                    </div>
                    <div class="kpi-label">Average Team Score</div>
                </div>
            </div>
        </div>

        <!-- Main Content Row -->
        <div class="row">
            <!-- Team Performance Table -->
            <div class="col-lg-<?= $selected_artisan ? '6' : '12' ?>">
                <div class="performance-table">
                    <table class="table table-hover mb-0">
                        <thead>
                            <tr>
                                <th>Artisan</th>
                                <th class="text-center">Assigned</th>
                                <th class="text-center">Completed</th>
                                <th class="text-center">Response SLA</th>
                                <th class="text-center">Completion SLA</th>
                                <th class="text-center">Score</th>
                                <th class="text-center">Rating</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($team_performance as $artisan): 
                                $rating_class = strtolower(str_replace(' ', '-', $artisan['rating']));
                            ?>
                            <tr onclick="window.location='?artisan=<?= $artisan['artisan_id'] ?>&period=<?= $filter_period ?>&sort=<?= urlencode($filter_sort) ?>';" style="cursor: pointer;">
                                <td>
                                    <strong><?= htmlspecialchars($artisan['username']) ?></strong>
                                    <br>
                                    <small class="text-muted">Artisan</small>
                                </td>
                                <td class="text-center"><?= $artisan['total_assigned'] ?></td>
                                <td class="text-center"><?= $artisan['total_completed'] ?></td>
                                <td class="text-center">
                                    <div><?= $artisan['response_sla_percentage'] ?>%</div>
                                    <div class="metric-progress" style="width: 100px; margin: auto;">
                                        <div class="metric-progress-bar" style="width: <?= $artisan['response_sla_percentage'] ?>%"></div>
                                    </div>
                                </td>
                                <td class="text-center">
                                    <div><?= $artisan['completion_sla_percentage'] ?>%</div>
                                    <div class="metric-progress" style="width: 100px; margin: auto;">
                                        <div class="metric-progress-bar" style="width: <?= $artisan['completion_sla_percentage'] ?>%"></div>
                                    </div>
                                </td>
                                <td class="text-center">
                                    <strong style="font-size: 16px;"><?= $artisan['overall_score'] ?></strong>
                                </td>
                                <td class="text-center">
                                    <span class="score-badge score-<?= $rating_class ?>">
                                        <?= $artisan['rating'] ?>
                                    </span>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Individual Artisan Detail -->
            <?php if ($selected_artisan): ?>
            <div class="col-lg-6">
                <div style="background: white; border-radius: 12px; padding: 20px; box-shadow: 0 4px 6px rgba(0, 0, 0, 0.07);">
                    <h5 class="mb-4">
                        <i class="fas fa-user-tie"></i>
                        <?= htmlspecialchars($selected_artisan['username']) ?> - Detailed View
                    </h5>

                    <!-- Detail Cards -->
                    <div class="row mb-3">
                        <div class="col-6">
                            <div class="kpi-card">
                                <div class="kpi-value" style="font-size: 24px;"><?= $selected_artisan['first_time_fix_percentage'] ?>%</div>
                                <div class="kpi-label">First-Time Fix Rate</div>
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="kpi-card">
                                <div class="kpi-value" style="font-size: 24px;"><?= $selected_artisan['mttr_hours'] ?></div>
                                <div class="kpi-label">Avg Repair Time (hrs)</div>
                            </div>
                        </div>
                    </div>

                    <div class="row mb-3">
                        <div class="col-6">
                            <div class="kpi-card">
                                <div class="kpi-value" style="font-size: 24px;"><?= $selected_artisan['repeat_failure_count'] ?></div>
                                <div class="kpi-label">Repeat Failures</div>
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="kpi-card">
                                <div class="kpi-value" style="font-size: 24px;"><?= $selected_artisan['total_overdue'] ?? 0 ?></div>
                                <div class="kpi-label">Overdue Tasks</div>
                            </div>
                        </div>
                    </div>

                    <!-- Repeat Failures List -->
                    <?php if (!empty($artisan_repeat_failures)): ?>
                    <div class="alert-section mt-4">
                        <div class="alert-section-title">
                            <i class="fas fa-exclamation-triangle"></i>
                            Recent Repeat Failures (<?= count($artisan_repeat_failures) ?>)
                        </div>
                        <?php foreach ($artisan_repeat_failures as $failure): ?>
                        <div class="repeat-failure-item">
                            <small>
                                <strong><?= htmlspecialchars($failure['failure_category']) ?></strong><br>
                                WO #<?= $failure['original_work_order_id'] ?> → #<?= $failure['repeat_work_order_id'] ?><br>
                                <?= $failure['days_between_failures'] ?> days between failures
                            </small>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
            <?php endif; ?>
        </div>

        <!-- Chronic Problems Section -->
        <?php if (!empty($chronic_assets)): ?>
        <div class="mt-4">
            <div style="background: white; border-radius: 12px; padding: 20px; box-shadow: 0 4px 6px rgba(0, 0, 0, 0.07);">
                <h5 class="mb-4">
                    <i class="fas fa-exclamation-circle text-danger"></i>
                    Chronic Problem Assets (Multiple Repeat Failures)
                </h5>
                <div class="table-responsive">
                    <table class="table table-sm">
                        <thead>
                            <tr>
                                <th>Asset ID</th>
                                <th>Total Repeats</th>
                                <th>Artisans Affected</th>
                                <th>Avg Days Between</th>
                                <th>Min Days Between</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($chronic_assets as $asset): ?>
                            <tr>
                                <td><strong><?= $asset['asset_id'] ?></strong></td>
                                <td><span class="badge bg-danger"><?= $asset['repeat_count'] ?></span></td>
                                <td><?= $asset['technician_count'] ?></td>
                                <td><?= round($asset['avg_days_between_failures'], 1) ?> days</td>
                                <td><?= $asset['min_days_between_failures'] ?> days</td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.1.3/js/bootstrap.bundle.min.js"></script>
</body>
</html>
