<?php
/**
 * Spare Parts Lifecycle Analytics for CMMS
 * Content only - to be included in index.php
 */

require_once 'config.inc.php';
require_once 'common.inc.php';

if (basename($_SERVER['PHP_SELF']) === 'lifecycle_analytics.php') {
    header('Location: index.php?nav=lifecycle');
    exit;
}

$date_range = $_GET['date_range'] ?? 'last_30d';
$asset_id = isset($_GET['asset_id']) ? intval($_GET['asset_id']) : 0;
$category = trim($_GET['category'] ?? '');
$location_id = isset($_GET['location_id']) ? intval($_GET['location_id']) : 0;
$search = trim($_GET['search'] ?? '');
$custom_from = trim($_GET['date_from'] ?? '');
$custom_to = trim($_GET['date_to'] ?? '');

$today = new DateTime('today');
$from = (clone $today)->modify('-29 days')->format('Y-m-d');
$to = $today->format('Y-m-d');
if ($date_range === 'last_7d') {
    $from = (clone $today)->modify('-6 days')->format('Y-m-d');
} elseif ($date_range === 'this_month') {
    $from = $today->format('Y-m-01');
} elseif ($date_range === 'this_year') {
    $from = $today->format('Y-01-01');
} elseif ($date_range === 'custom') {
    $fromDate = DateTime::createFromFormat('Y-m-d', $custom_from);
    $toDate = DateTime::createFromFormat('Y-m-d', $custom_to);
    if ($fromDate && $toDate && $fromDate <= $toDate) {
        $from = $fromDate->format('Y-m-d');
        $to = $toDate->format('Y-m-d');
    }
}

$intervalDays = max(1, intval((new DateTime($to))->diff(new DateTime($from))->days) + 1);

$assetList = [];
// Apply tenant filtering
$assetRes = $connection->query(apply_tenant_filter("SELECT id, description FROM equipment ORDER BY description"));
if ($assetRes) {
    while ($row = $assetRes->fetch_assoc()) {
        $assetList[] = $row;
    }
}

$categoryList = [];
// Apply tenant filtering
$catRes = $connection->query(apply_tenant_filter("SELECT DISTINCT category FROM parts_master WHERE is_active = 1 AND category <> '' ORDER BY category"));
if ($catRes) {
    while ($row = $catRes->fetch_assoc()) {
        $categoryList[] = $row['category'];
    }
}

$locationList = [];
// Apply tenant filtering
$locRes = $connection->query(apply_tenant_filter("SELECT wl.id, CONCAT(IFNULL(w.warehouse_name,''), ' / ', wl.location_code) AS label FROM warehouse_locations wl LEFT JOIN warehouses w ON wl.warehouse_id = w.id ORDER BY label"));
if ($locRes) {
    while ($row = $locRes->fetch_assoc()) {
        $locationList[] = $row;
    }
}

$filterParts = ["pm.is_active = 1"];
$filterOrders = ["wo.submit_date BETWEEN '{$connection->real_escape_string($from)}' AND '{$connection->real_escape_string($to)}'", "wo.wo_status NOT IN ('Canceled','Rejected')"];
if ($search !== '') {
    $term = $connection->real_escape_string($search);
    $filterParts[] = "(pm.part_name LIKE '%{$term}%' OR pm.part_code LIKE '%{$term}%' OR pm.part_number LIKE '%{$term}%')";
}
if ($category !== '') {
    $filterParts[] = "pm.category = '" . $connection->real_escape_string($category) . "'";
}
if ($location_id) {
    $filterParts[] = "EXISTS (SELECT 1 FROM stock_locales sl2 WHERE sl2.part_id = pm.id AND sl2.warehouse_location_id = {$location_id})";
}
if ($asset_id) {
    $filterOrders[] = "wo.equipment = '" . $connection->real_escape_string((string)$asset_id) . "'";
}

$partsWhereSql = implode(' AND ', $filterParts);
$ordersWhereSql = implode(' AND ', $filterOrders);

$totalUsedRes = $connection->query(apply_tenant_filter(
    "SELECT COALESCE(SUM(wp.quantity_required),0) AS total_used, COALESCE(SUM(wp.quantity_required * COALESCE(pm.unit_cost,0)),0) AS total_cost_used " .
    "FROM wo_parts wp " .
    "JOIN work_orders wo ON wp.wo_id = wo.wo_id " .
    "JOIN parts_master pm ON wp.part_id = pm.id " .
    "WHERE {$ordersWhereSql} AND {$partsWhereSql}"
));
$totalUsed = 0;
$totalCostUsed = 0.0;
if ($totalUsedRes && ($row = $totalUsedRes->fetch_assoc())) {
    $totalUsed = intval($row['total_used']);
    $totalCostUsed = floatval($row['total_cost_used']);
}

$totalInventoryRes = $connection->query(apply_tenant_filter(
    "SELECT COALESCE(SUM(pm.total_on_hand * COALESCE(pm.unit_cost,0)),0) AS total_value, COALESCE(AVG(pm.total_on_hand),0) AS avg_inventory " .
    "FROM parts_master pm " .
    "WHERE {$partsWhereSql}"
));
$totalInventoryValue = 0.0;
$avgInventory = 0.0;
if ($totalInventoryRes && ($row = $totalInventoryRes->fetch_assoc())) {
    $totalInventoryValue = floatval($row['total_value']);
    $avgInventory = floatval($row['avg_inventory']);
}

$stockTurnover = $avgInventory > 0 ? round($totalUsed / $avgInventory, 2) : 0.0;
$avgConsumptionRate = round($totalUsed / $intervalDays, 2);

$fastMoving = [];
// Apply tenant filtering
$fastMovingRes = $connection->query(apply_tenant_filter(
    "SELECT pm.part_code, pm.part_name, COALESCE(SUM(wp.quantity_required),0) AS used_qty " .
    "FROM parts_master pm " .
    "LEFT JOIN wo_parts wp ON wp.part_id = pm.id " .
    "LEFT JOIN work_orders wo ON wp.wo_id = wo.wo_id AND {$ordersWhereSql} " .
    "WHERE {$partsWhereSql} " .
    "GROUP BY pm.id " .
    "HAVING used_qty > 0 " .
    "ORDER BY used_qty DESC LIMIT 10"
));
if ($fastMovingRes) {
    while ($row = $fastMovingRes->fetch_assoc()) {
        $fastMoving[] = $row;
    }
}
$fastMovingCount = count($fastMoving);

$deadStockCount = 0;
// Apply tenant filtering
$deadStockRes = $connection->query(apply_tenant_filter(
    "SELECT COALESCE(SUM(wp.quantity_required),0) AS used_qty, MAX(wo.submit_date) AS last_used " .
    "FROM parts_master pm " .
    "LEFT JOIN wo_parts wp ON wp.part_id = pm.id " .
    "LEFT JOIN work_orders wo ON wp.wo_id = wo.wo_id " .
    "WHERE {$partsWhereSql} " .
    "GROUP BY pm.id"
));
if ($deadStockRes) {
    while ($row = $deadStockRes->fetch_assoc()) {
        $usedQty = intval($row['used_qty']);
        $lastUsed = $row['last_used'];
        if ($usedQty === 0 || $lastUsed === null || strtotime($lastUsed) < strtotime('-90 days')) {
            $deadStockCount++;
        }
    }
}

$trendData = [];
// Apply tenant filtering
$trendRes = $connection->query(apply_tenant_filter(
    "SELECT DATE_FORMAT(wo.submit_date, '%Y-%m-%d') AS period, COALESCE(SUM(wp.quantity_required),0) AS qty " .
    "FROM wo_parts wp " .
    "JOIN work_orders wo ON wp.wo_id = wo.wo_id " .
    "JOIN parts_master pm ON wp.part_id = pm.id " .
    "WHERE {$ordersWhereSql} AND {$partsWhereSql} " .
    "GROUP BY period ORDER BY period"
));
if ($trendRes) {
    while ($row = $trendRes->fetch_assoc()) {
        $trendData[$row['period']] = intval($row['qty']);
    }
}

$categoryData = [];
// Apply tenant filtering
$categoryRes = $connection->query(apply_tenant_filter(
    "SELECT COALESCE(pm.category,'Uncategorized') AS category, COALESCE(SUM(wp.quantity_required),0) AS qty " .
    "FROM parts_master pm " .
    "LEFT JOIN wo_parts wp ON wp.part_id = pm.id " .
    "LEFT JOIN work_orders wo ON wp.wo_id = wo.wo_id AND {$ordersWhereSql} " .
    "WHERE {$partsWhereSql} " .
    "GROUP BY pm.category ORDER BY qty DESC LIMIT 12"
));
if ($categoryRes) {
    while ($row = $categoryRes->fetch_assoc()) {
        $categoryData[$row['category']] = intval($row['qty']);
    }
}

$assetUsage = [];
// Apply tenant filtering
$assetResUsage = $connection->query(apply_tenant_filter(
    "SELECT COALESCE(e.description, wo.equipment) AS asset, COALESCE(SUM(wp.quantity_required),0) AS qty " .
    "FROM wo_parts wp " .
    "JOIN work_orders wo ON wp.wo_id = wo.wo_id " .
    "LEFT JOIN equipment e ON wo.equipment = CAST(e.id AS CHAR) " .
    "JOIN parts_master pm ON wp.part_id = pm.id " .
    "WHERE {$ordersWhereSql} AND {$partsWhereSql} " .
    "GROUP BY asset ORDER BY qty DESC LIMIT 10"
));
if ($assetResUsage) {
    while ($row = $assetResUsage->fetch_assoc()) {
        $assetUsage[] = ['asset' => $row['asset'], 'qty' => intval($row['qty'])];
    }
}

$monthlySpending = [];
// Apply tenant filtering
$spendingRes = $connection->query(apply_tenant_filter(
    "SELECT DATE_FORMAT(wo.submit_date, '%Y-%m') AS month_label, COALESCE(SUM(wp.quantity_required * COALESCE(pm.unit_cost,0)),0) AS cost " .
    "FROM wo_parts wp " .
    "JOIN work_orders wo ON wp.wo_id = wo.wo_id " .
    "JOIN parts_master pm ON wp.part_id = pm.id " .
    "WHERE {$ordersWhereSql} AND {$partsWhereSql} " .
    "GROUP BY month_label ORDER BY month_label"
));
if ($spendingRes) {
    while ($row = $spendingRes->fetch_assoc()) {
        $monthlySpending[$row['month_label']] = floatval($row['cost']);
    }
}

$topMoving = [];
// Apply tenant filtering
$topRes = $connection->query(apply_tenant_filter(
    "SELECT pm.part_code, pm.part_name, COALESCE(SUM(wp.quantity_required),0) AS quantity_used, MAX(wo.submit_date) AS last_used, COALESCE(pm.total_on_hand,0) AS stock_remaining, pm.reorder_point " .
    "FROM parts_master pm " .
    "LEFT JOIN wo_parts wp ON wp.part_id = pm.id " .
    "LEFT JOIN work_orders wo ON wp.wo_id = wo.wo_id AND {$ordersWhereSql} " .
    "WHERE {$partsWhereSql} " .
    "GROUP BY pm.id ORDER BY quantity_used DESC LIMIT 10"
));
if ($topRes) {
    while ($row = $topRes->fetch_assoc()) {
        $topMoving[] = $row;
    }
}

$detailRows = [];
$detailRes = $connection->query(apply_tenant_filter(
    "SELECT pm.part_code, pm.part_name, pm.category, COALESCE(pm.total_on_hand,0) AS closing_stock, pm.reorder_point, pm.minimum_quantity, pm.safety_stock_level, pm.unit_cost, pm.supplier_part_number, pm.manufacturer, " .
    "COALESCE(SUM(wp.quantity_required),0) AS quantity_used, MAX(wo.submit_date) AS last_used, COALESCE(SUM(sl.quantity_on_hand),0) AS stock_on_hand " .
    "FROM parts_master pm " .
    "LEFT JOIN stock_locales sl ON sl.part_id = pm.id " .
    "LEFT JOIN wo_parts wp ON wp.part_id = pm.id " .
    "LEFT JOIN work_orders wo ON wp.wo_id = wo.wo_id AND {$ordersWhereSql} " .
    "WHERE {$partsWhereSql} " .
    "GROUP BY pm.id ORDER BY pm.part_name ASC"
));
if ($detailRes) {
    while ($row = $detailRes->fetch_assoc()) {
        $row['opening_stock'] = intval($row['stock_on_hand']) + intval($row['quantity_used']);
        $row['consumption_rate'] = $intervalDays > 0 ? round(intval($row['quantity_used']) / $intervalDays, 2) : 0;
        $detailRows[] = $row;
    }
}

$insights = [];
$previousFrom = (new DateTime($from))->modify('-' . $intervalDays . ' days')->format('Y-m-d');
$previousTo = (new DateTime($from))->modify('-1 day')->format('Y-m-d');
$prevUsedRes = $connection->query(apply_tenant_filter(
    "SELECT COALESCE(SUM(wp.quantity_required),0) AS prev_used " .
    "FROM wo_parts wp " .
    "JOIN work_orders wo ON wp.wo_id = wo.wo_id " .
    "JOIN parts_master pm ON wp.part_id = pm.id " .
    "WHERE wo.submit_date BETWEEN '{$connection->real_escape_string($previousFrom)}' AND '{$connection->real_escape_string($previousTo)}' " .
    "AND wo.wo_status NOT IN ('Canceled','Rejected') AND {$partsWhereSql}"
));
$prevUsed = 0;
if ($prevUsedRes && ($row = $prevUsedRes->fetch_assoc())) {
    $prevUsed = intval($row['prev_used']);
}

if ($prevUsed > 0 && $totalUsed > $prevUsed) {
    $pct = round((($totalUsed - $prevUsed) / $prevUsed) * 100, 1);
    $insights[] = "Parts consumption is up {$pct}% compared with the previous {$intervalDays}-day period.";
} elseif ($prevUsed > 0) {
    $pct = round((($prevUsed - $totalUsed) / $prevUsed) * 100, 1);
    $insights[] = "Parts consumption is down {$pct}% compared with the previous {$intervalDays}-day period.";
}

if (!empty($detailRows)) {
    usort($detailRows, function ($a, $b) {
        return intval($b['quantity_used']) <=> intval($a['quantity_used']);
    });
    $highest = $detailRows[0];
    if (floatval($highest['consumption_rate']) > 0 && intval($highest['stock_on_hand']) > 0) {
        $rate = max(1, floatval($highest['consumption_rate']));
        $daysRemaining = intval(floor(intval($highest['stock_on_hand']) / $rate));
        $reorderDate = (new DateTime())->modify("+{$daysRemaining} days")->format('Y-m-d');
        $insights[] = "{$highest['part_name']} is expected to hit reorder trigger in {$daysRemaining} days (approx. {$reorderDate}).";
    }
}

$trendLabels = array_keys($trendData);
$trendValues = array_values($trendData);
$categoryLabels = array_keys($categoryData);
$categoryValues = array_values($categoryData);
$assetLabels = array_column($assetUsage, 'asset');
$assetValues = array_column($assetUsage, 'qty');
$spendingLabels = array_keys($monthlySpending);
$spendingValues = array_values($monthlySpending);

$kpiCards = [
    [ 'label' => 'Total Spare Parts Consumed', 'value' => number_format($totalUsed), 'color' => 'success' ],
    [ 'label' => 'Total Inventory Value', 'value' => 'UGX '.number_format($totalInventoryValue, 2), 'color' => 'primary' ],
    [ 'label' => 'Fast-Moving Items', 'value' => number_format($fastMovingCount), 'color' => 'info' ],
    [ 'label' => 'Slow / Dead Stock Items', 'value' => number_format($deadStockCount), 'color' => $deadStockCount > 10 ? 'danger' : 'warning' ],
    [ 'label' => 'Stock Turnover Rate', 'value' => number_format($stockTurnover, 2), 'color' => 'secondary' ],
    [ 'label' => 'Avg Consumption Rate', 'value' => number_format($avgConsumptionRate, 2).' / day', 'color' => 'success' ],
];
?>

<style>
    .lifecycle-page { width: 100%; }
    .filter-panel { background: white; padding: 20px; border-radius: 18px; border: 1px solid #e2e8f0; margin-bottom: 24px; }
    .metric-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(180px,1fr)); gap: 16px; margin-bottom: 24px; }
    .metric-card { background: #fff; border-radius: 16px; padding: 20px; border: 1px solid #e2e8f0; }
    .metric-card h3 { font-size: 1rem; margin-bottom: 10px; color: #102a43; }
    .metric-card .metric-value { font-size: 2rem; font-weight: 700; margin-bottom: 6px; }
    .metric-card .metric-sub { color: #64748b; font-size: 0.9rem; }
    .chart-card { background: white; border-radius: 18px; padding: 20px; border: 1px solid #e2e8f0; margin-bottom: 24px; }
    .chart-title { font-size: 1rem; font-weight: 700; margin-bottom: 16px; }
    .insights-box { background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 16px; padding: 20px; }
    .insight-item { margin-bottom: 12px; }
    .insight-item strong { display: block; margin-bottom: 6px; font-size: 0.95rem; }
    .lifecycle-step { display: flex; gap: 14px; padding: 18px; border-radius: 16px; background: #fff; border: 1px solid #e2e8f0; margin-bottom: 16px; }
    .lifecycle-step-icon { width: 46px; height: 46px; display: grid; place-items: center; border-radius: 50%; background: #e0e7ff; color: #3730a3; font-weight: 700; }
    .lifecycle-step-body h5 { margin: 0 0 6px; font-size: 1rem; }
    .lifecycle-step-body p { margin: 0; color: #475569; font-size: 0.95rem; }
</style>

<div class="lifecycle-page">
    <div class="analytics-header">
        <h2>Spare Parts Lifecycle Analytics</h2>
        <p>Monitor supplier performance, maintenance quality, and equipment reliability</p>
    </div>

    <div style="background-color: #f9f9f9; border: 1px solid #ddd; padding: 20px; margin: 20px 0; border-radius: 5px;">
        <h3 style="color: #666; margin-top: 0;">System Status: Not Configured</h3>
        <p>The Spare Parts Lifecycle Analytics system requires additional database tables and configuration.</p>
        <p>To enable this feature, please run the lifecycle setup scripts or contact your system administrator.</p>
    </div>

    <table border="1" cellpadding="10" cellspacing="0" style="border-collapse: collapse; width: 100%; margin-bottom: 20px;">
        <tr style="background-color: #f0f0f0;">
            <th style="text-align: center;">Suppliers Tracked</th>
            <th style="text-align: center;">Technicians Monitored</th>
            <th style="text-align: center;">Equipment Locations</th>
            <th style="text-align: center;">Active Alerts</th>
        </tr>
        <tr>
            <td style="text-align: center;">
                <div class="metric-value"><?php echo $total_suppliers; ?></div>
                <div class="metric-label">Suppliers</div>
            </td>
            <td style="text-align: center;">
                <div class="metric-value"><?php echo $total_technicians; ?></div>
                <div class="metric-label">Technicians</div>
            </td>
            <td style="text-align: center;">
                <div class="metric-value"><?php echo $total_equipment; ?></div>
                <div class="metric-label">Locations</div>
            </td>
            <td style="text-align: center;">
                <div class="metric-value <?php echo $total_alerts > 10 ? 'status-danger' : ($total_alerts > 5 ? 'status-warning' : 'status-good'); ?>">
                    <?php echo $total_alerts; ?>
                </div>
                <div class="metric-label">Alerts</div>
            </td>
        </tr>
    </table>

    <h3>Setup Required</h3>
    <p>The lifecycle analytics feature requires the following components to be installed:</p>
    <ul>
        <li>Spare parts lifecycle database tables</li>
        <li>Supplier performance tracking</li>
        <li>Maintenance quality metrics</li>
        <li>Equipment reliability monitoring</li>
        <li>Alert management system</li>
    </ul>

    <p>Please check the following setup files for installation instructions:</p>
    <ul>
        <li><code>spare_parts_lifecycle_schema.sql</code></li>
        <li><code>setup_lifecycle_system.php</code></li>
        <li><code>LIFECYCLE_README.md</code></li>
    </ul>
</div>