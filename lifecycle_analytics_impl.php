<?php
/**
 * Spare Parts Lifecycle Analytics for CMMS
 */

require_once 'config.inc.php';
require_once 'common.inc.php';
require_once 'libraries/inventory_manager.php';

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
$assetRes = $connection->query(apply_tenant_filter("SELECT id, description FROM equipment ORDER BY description"));
if ($assetRes) {
    while ($row = $assetRes->fetch_assoc()) {
        $assetList[] = $row;
    }
}

$categoryList = [];
$catRes = $connection->query(apply_tenant_filter("SELECT DISTINCT category FROM parts_master WHERE is_active = 1 AND category <> '' ORDER BY category"));
if ($catRes) {
    while ($row = $catRes->fetch_assoc()) {
        $categoryList[] = $row['category'];
    }
}

$locationList = [];
$locRes = $connection->query(apply_tenant_filter("SELECT wl.id, CONCAT(IFNULL(w.warehouse_name,''), ' / ', wl.location_code) AS label FROM warehouse_locations wl LEFT JOIN warehouses w ON wl.warehouse_id = w.id ORDER BY label"));
if ($locRes) {
    while ($row = $locRes->fetch_assoc()) {
        $locationList[] = $row;
    }
}

// Add tenant_id filtering to base filter arrays
$tenant_id = isset($_SESSION['tenant_id']) ? (int)$_SESSION['tenant_id'] : 0;
$tenantFilterClause = $tenant_id > 0 ? " AND pm.tenant_id = {$tenant_id}" : "";
$tenantFilterWOClause = $tenant_id > 0 ? " AND wo.tenant_id = {$tenant_id}" : "";

$filterParts = ["pm.is_active = 1" . $tenantFilterClause];
$filterOrders = ["wo.submit_date BETWEEN '{$connection->real_escape_string($from)}' AND '{$connection->real_escape_string($to)}'" . $tenantFilterWOClause, "wo.wo_status NOT IN ('Canceled','Rejected')"];
if ($search !== '') {
    $term = $connection->real_escape_string($search);
    $filterParts[] = "(pm.part_name LIKE '%{$term}%' OR pm.part_code LIKE '%{$term}%' OR pm.part_number LIKE '%{$term}%')";
}
if ($category !== '') {
    $filterParts[] = "pm.category = '" . $connection->real_escape_string($category) . "'";
}
if ($location_id) {
    $sl_tenant_filter = $tenant_id > 0 ? " AND sl2.tenant_id = {$tenant_id}" : "";
    $filterParts[] = "EXISTS (SELECT 1 FROM stock_locales sl2 WHERE sl2.part_id = pm.id AND sl2.warehouse_location_id = {$location_id}{$sl_tenant_filter})";
}
if ($asset_id) {
    $filterOrders[] = "wo.equipment = '" . $connection->real_escape_string((string)$asset_id) . "'";
}

$partsWhereSql = implode(' AND ', $filterParts);
$ordersWhereSql = implode(' AND ', $filterOrders);

// Add tenant_id filtering to subquery WHERE clauses
$tenantFilter = $tenant_id > 0 ? " AND pm.tenant_id = {$tenant_id} AND es.tenant_id = {$tenant_id}" : "";
$tenantFilterWO = $tenant_id > 0 ? " AND wo.tenant_id = {$tenant_id} AND wos.tenant_id = {$tenant_id}" : "";
$tenantFilterConsumable = $tenant_id > 0 ? " AND c.tenant_id = {$tenant_id}" : "";
$tenantFilterConsumableUsage = $tenant_id > 0 ? " AND cu.tenant_id = {$tenant_id}" : "";

$usageUnionSql = "
        SELECT pm.id AS part_id, pm.part_code, pm.part_name, pm.category, pm.unit_cost,
               wos.quantity_used AS qty,
               wos.quantity_used * COALESCE(pm.unit_cost, 0) AS cost,
               wo.submit_date,
               e.description AS equipment
        FROM equipment_spares es
        JOIN work_order_spares wos ON es.id = wos.spare_id
        JOIN work_orders wo ON wos.wo_id = wo.wo_id
        JOIN parts_master pm ON es.part_id = pm.id
        LEFT JOIN equipment e ON wo.equipment = CAST(e.id AS CHAR)
        WHERE pm.is_active = 1 AND wo.submit_date BETWEEN '{$from}' AND '{$to}'{$tenantFilter}{$tenantFilterWO}
        
        UNION ALL
        
        SELECT c.id AS part_id, c.id AS part_code, c.name AS part_name, c.category AS category, 0 AS unit_cost,
               cu.quantity_used AS qty,
               0 AS cost,
               cu.usage_date AS submit_date,
               '' AS equipment
        FROM consumable_usage cu
        JOIN consumables c ON cu.consumable_id = c.id
        WHERE cu.usage_date BETWEEN '{$from}' AND '{$to}'{$tenantFilterConsumable}{$tenantFilterConsumableUsage}
    ";

$totalUsedRes = $connection->query(apply_tenant_filter(
    "SELECT COALESCE(SUM(qty),0) AS total_used, COALESCE(SUM(cost),0) AS total_cost_used " .
    "FROM ({$usageUnionSql}) AS usage_all"
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

$linkedSparesCount = 0;
$linkedSparesRes = $connection->query(apply_tenant_filter("SELECT COUNT(*) AS count FROM equipment_spares WHERE part_id IS NOT NULL"));
if ($linkedSparesRes && ($row = $linkedSparesRes->fetch_assoc())) {
    $linkedSparesCount = intval($row['count']);
}

$fastMoving = [];
$fastMovingRes = $connection->query(apply_tenant_filter(
    "SELECT part_code, part_name, COALESCE(SUM(qty),0) AS used_qty, MAX(submit_date) AS last_used " .
    "FROM ({$usageUnionSql}) AS usage_all " .
    "GROUP BY part_id ORDER BY used_qty DESC LIMIT 10"
));
if ($fastMovingRes) {
    while ($row = $fastMovingRes->fetch_assoc()) {
        $fastMoving[] = $row;
    }
}
$fastMovingCount = count($fastMoving);

$deadStockCount = 0;
$deadStockRes = $connection->query(apply_tenant_filter(
    "SELECT pm.id, COALESCE(SUM(usage_all.qty),0) AS used_qty, MAX(usage_all.submit_date) AS last_used " .
    "FROM parts_master pm " .
    "LEFT JOIN ({$usageUnionSql}) AS usage_all ON usage_all.part_id = pm.id " .
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
$trendRes = $connection->query(apply_tenant_filter(
    "SELECT DATE_FORMAT(submit_date, '%Y-%m-%d') AS period, COALESCE(SUM(qty),0) AS qty " .
    "FROM ({$usageUnionSql}) AS usage_all " .
    "GROUP BY period ORDER BY period"
));
if ($trendRes) {
    while ($row = $trendRes->fetch_assoc()) {
        $trendData[$row['period']] = intval($row['qty']);
    }
}

$categoryData = [];
$categoryRes = $connection->query(apply_tenant_filter(
    "SELECT COALESCE(category,'Uncategorized') AS category, COALESCE(SUM(qty),0) AS qty " .
    "FROM ({$usageUnionSql}) AS usage_all " .
    "GROUP BY category ORDER BY qty DESC LIMIT 12"
));
if ($categoryRes) {
    while ($row = $categoryRes->fetch_assoc()) {
        $categoryData[$row['category']] = intval($row['qty']);
    }
}

$assetUsage = [];
$assetResUsage = $connection->query(apply_tenant_filter(
    "SELECT COALESCE(e.description, usage_all.equipment) AS asset, COALESCE(SUM(usage_all.qty),0) AS qty " .
    "FROM ({$usageUnionSql}) AS usage_all " .
    "LEFT JOIN equipment e ON usage_all.equipment = CAST(e.id AS CHAR) " .
    "GROUP BY asset ORDER BY qty DESC LIMIT 10"
));
if ($assetResUsage) {
    while ($row = $assetResUsage->fetch_assoc()) {
        $assetUsage[] = ['asset' => $row['asset'], 'qty' => intval($row['qty'])];
    }
}

$monthlySpending = [];
$spendingRes = $connection->query(apply_tenant_filter(
    "SELECT DATE_FORMAT(submit_date, '%Y-%m') AS month_label, COALESCE(SUM(cost),0) AS cost " .
    "FROM ({$usageUnionSql}) AS usage_all " .
    "GROUP BY month_label ORDER BY month_label"
));
if ($spendingRes) {
    while ($row = $spendingRes->fetch_assoc()) {
        $monthlySpending[$row['month_label']] = floatval($row['cost']);
    }
}

$topMoving = [];
$topRes = $connection->query(apply_tenant_filter(
    "SELECT pm.part_code, pm.part_name, COALESCE(SUM(qty),0) AS quantity_used, MAX(submit_date) AS last_used, COALESCE(pm.total_on_hand,0) AS stock_remaining, pm.reorder_point " .
    "FROM parts_master pm " .
    "LEFT JOIN ({$usageUnionSql}) AS usage_all ON usage_all.part_id = pm.id " .
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
    "COALESCE(part_usage.quantity_used,0) AS quantity_used, part_usage.last_used, COALESCE(SUM(sl.quantity_on_hand),0) AS stock_on_hand " .
    "FROM parts_master pm " .
    "LEFT JOIN stock_locales sl ON sl.part_id = pm.id " .
    "LEFT JOIN (" .
        "SELECT part_id, COALESCE(SUM(qty),0) AS quantity_used, MAX(submit_date) AS last_used " .
        "FROM ({$usageUnionSql}) AS usage_all " .
        "GROUP BY part_id" .
    ") part_usage ON part_usage.part_id = pm.id " .
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

$understockCount = 0;
$understockRes = $connection->query(apply_tenant_filter(
    "SELECT COUNT(*) AS count FROM parts_master pm WHERE {$partsWhereSql} AND COALESCE(pm.total_on_hand,0) <= COALESCE(pm.reorder_point,0) AND pm.reorder_point > 0"
));
if ($understockRes && ($row = $understockRes->fetch_assoc())) {
    $understockCount = intval($row['count']);
}

$insights = [];
$previousFrom = (new DateTime($from))->modify('-' . $intervalDays . ' days')->format('Y-m-d');
$previousTo = (new DateTime($from))->modify('-1 day')->format('Y-m-d');
$prevUsedRes = $connection->query(apply_tenant_filter(
    "SELECT COALESCE(SUM(qty),0) AS prev_used " .
    "FROM ({$usageUnionSql}) AS usage_all " .
    "WHERE submit_date BETWEEN '{$connection->real_escape_string($previousFrom)}' AND '{$connection->real_escape_string($previousTo)}'"
));
$prevUsed = 0;
if ($prevUsedRes && ($row = $prevUsedRes->fetch_assoc())) {
    $prevUsed = intval($row['prev_used']);
}

if ($understockCount > 0) {
    $insights[] = "There are {$understockCount} parts currently at or below reorder level.";
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
    [ 'label' => 'Linked Equipment Spares', 'value' => number_format($linkedSparesCount), 'color' => 'info' ],
    [ 'label' => 'Fast-Moving Items', 'value' => number_format($fastMovingCount), 'color' => 'info' ],
    [ 'label' => 'Slow / Dead Stock Items', 'value' => number_format($deadStockCount), 'color' => $deadStockCount > 10 ? 'danger' : 'warning' ],
    [ 'label' => 'Stock Turnover Rate', 'value' => number_format($stockTurnover, 2), 'color' => 'secondary' ],
    [ 'label' => 'Avg Consumption Rate', 'value' => number_format($avgConsumptionRate, 2).' / day', 'color' => 'success' ],
];

if (isset($_GET['export_csv']) && intval($_GET['export_csv']) === 1) {
    while (ob_get_level() > 0) {
        ob_end_clean();
    }

    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="spare_parts_lifecycle_' . date('Ymd_His') . '.csv"');
    echo "\xEF\xBB\xBF";

    $output = fopen('php://output', 'w');
    @fputcsv($output, [
        'Part Code', 'Part Name', 'Category', 'Opening Stock', 'Quantity Used', 'Closing Stock', 'Consumption/Day', 'Last Used', 'Reorder Level', 'Supplier / Manufacturer'
    ], ',', '"', '\\');

    foreach ($detailRows as $row) {
        @fputcsv($output, [
            $row['part_code'] ?? '',
            $row['part_name'] ?? '',
            $row['category'] ?? '',
            intval($row['opening_stock']),
            intval($row['quantity_used']),
            intval($row['stock_on_hand']),
            number_format($row['consumption_rate'], 2, '.', ''),
            $row['last_used'] ?? '',
            intval($row['reorder_point']),
            ($row['supplier_part_number'] ?? $row['manufacturer'] ?? 'Unknown'),
        ], ',', '"', '\\');
    }

    fclose($output);
    exit;
}
?>

<style>
    .lifecycle-page { width: 100%; }
    .filter-panel { background: white; padding: 15px; border-radius: 12px; border: 1px solid #e2e8f0; margin-bottom: 18px; }
    .metric-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(160px,1fr)); gap: 12px; margin-bottom: 18px; }
    .metric-card { background: #fff; border-radius: 12px; padding: 15px; border: 1px solid #e2e8f0; }
    .metric-card h3 { font-size: 0.9rem; margin-bottom: 8px; color: #102a43; }
    .metric-card .metric-value { font-size: 1.6rem; font-weight: 700; margin-bottom: 4px; }
    .metric-card .metric-sub { color: #64748b; font-size: 0.8rem; }
    .chart-card { background: white; border-radius: 12px; padding: 15px; border: 1px solid #e2e8f0; margin-bottom: 18px; }
    .chart-title { font-size: 0.9rem; font-weight: 700; margin-bottom: 12px; }
    .insights-box { background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 12px; padding: 15px; }
    .insight-item { margin-bottom: 10px; }
    .insight-item strong { display: block; margin-bottom: 4px; font-size: 0.9rem; }
    .lifecycle-step { display: flex; gap: 12px; padding: 14px; border-radius: 12px; background: #fff; border: 1px solid #e2e8f0; margin-bottom: 12px; }
    .lifecycle-step-icon { width: 40px; height: 40px; display: grid; place-items: center; border-radius: 50%; background: #e0e7ff; color: #3730a3; font-weight: 700; }
    .lifecycle-step-body h5 { margin: 0 0 4px; font-size: 0.9rem; }
    .lifecycle-step-body p { margin: 0; color: #475569; font-size: 0.85rem; }
</style>

<div class="lifecycle-page">
    <div class="page-header">
        <h1 class="page-title">Spare Parts Lifecycle Analytics</h1>
        <p class="page-subtitle">Track lifecycle, usage, stock health, cost, and reorder triggers across inventory and equipment spares.</p>
    </div>

    <form method="get" class="filter-panel">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <div><strong>Analytics Control Panel</strong></div>
            <div><a href="index.php?nav=lifecycle&export_csv=1&date_range=<?php echo urlencode($date_range); ?>&asset_id=<?php echo urlencode($asset_id); ?>&category=<?php echo urlencode($category); ?>&location_id=<?php echo urlencode($location_id); ?>&search=<?php echo urlencode($search); ?>&date_from=<?php echo urlencode($custom_from); ?>&date_to=<?php echo urlencode($custom_to); ?>" class="btn btn-sm btn-outline-primary">Export CSV</a></div>
        </div>
        <div class="row gy-3">
            <div class="col-md-2">
                <label class="form-label">Date Range</label>
                <select name="date_range" class="form-select" onchange="document.getElementById('dateFrom').disabled=this.value!=='custom'; document.getElementById('dateTo').disabled=this.value!=='custom';">
                    <option value="last_7d"<?php echo $date_range==='last_7d'?' selected':''; ?>>Last 7 days</option>
                    <option value="last_30d"<?php echo $date_range==='last_30d'?' selected':''; ?>>Last 30 days</option>
                    <option value="this_month"<?php echo $date_range==='this_month'?' selected':''; ?>>This month</option>
                    <option value="this_year"<?php echo $date_range==='this_year'?' selected':''; ?>>This year</option>
                    <option value="custom"<?php echo $date_range==='custom'?' selected':''; ?>>Custom range</option>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label">From</label>
                <input id="dateFrom" type="date" name="date_from" class="form-control" value="<?php echo htmlspecialchars($custom_from ?: $from); ?>"<?php echo $date_range==='custom'?'':' disabled'; ?>>
            </div>
            <div class="col-md-2">
                <label class="form-label">To</label>
                <input id="dateTo" type="date" name="date_to" class="form-control" value="<?php echo htmlspecialchars($custom_to ?: $to); ?>"<?php echo $date_range==='custom'?'':' disabled'; ?>>
            </div>
            <div class="col-md-2">
                <label class="form-label">Asset / Machine</label>
                <select name="asset_id" class="form-select">
                    <option value="">All assets</option>
                    <?php foreach ($assetList as $asset): ?>
                        <option value="<?php echo intval($asset['id']); ?>"<?php echo $asset_id===intval($asset['id'])?' selected':''; ?>><?php echo htmlspecialchars($asset['description']); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label">Spare Category</label>
                <select name="category" class="form-select">
                    <option value="">All categories</option>
                    <?php foreach ($categoryList as $cat): ?>
                        <option value="<?php echo htmlspecialchars($cat); ?>"<?php echo $category===$cat?' selected':''; ?>><?php echo htmlspecialchars($cat); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label">Store / Location</label>
                <select name="location_id" class="form-select">
                    <option value="">All locations</option>
                    <?php foreach ($locationList as $loc): ?>
                        <option value="<?php echo intval($loc['id']); ?>"<?php echo $location_id===intval($loc['id'])?' selected':''; ?>><?php echo htmlspecialchars($loc['label']); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
        <div class="row gy-3 mt-3">
            <div class="col-md-10">
                <label class="form-label">Search by part name or code</label>
                <input type="search" name="search" class="form-control" placeholder="e.g. bearing 6205 or PART-123" value="<?php echo htmlspecialchars($search); ?>">
            </div>
            <div class="col-md-2 d-flex align-items-end">
                <button type="submit" class="btn btn-primary w-100">Apply filters</button>
            </div>
        </div>
    </form>

    <div class="metric-grid">
        <?php foreach ($kpiCards as $card): ?>
            <div class="metric-card">
                <h3><?php echo htmlspecialchars($card['label']); ?></h3>
                <div class="metric-value text-<?php echo htmlspecialchars($card['color']); ?>"><?php echo htmlspecialchars($card['value']); ?></div>
            </div>
        <?php endforeach; ?>
    </div>

    <div class="chart-card">
        <div class="chart-title">Consumption Trend Analysis</div>
        <canvas id="consumptionTrend"></canvas>
    </div>

    <div class="row">
        <div class="col-lg-6">
            <div class="chart-card">
                <div class="chart-title">Consumption by Category</div>
                <canvas id="categoryBreakdown"></canvas>
            </div>
        </div>
        <div class="col-lg-6">
            <div class="chart-card">
                <div class="chart-title">Usage by Asset / Machine</div>
                <canvas id="assetUsage"></canvas>
            </div>
        </div>
    </div>

    <div class="chart-card">
        <div class="chart-title">Cost & Value Analytics</div>
        <canvas id="monthlySpending"></canvas>
    </div>

    <div class="row">
        <div class="col-lg-8">
            <div class="chart-card">
                <div class="chart-title">Fast-Moving Spare Parts</div>
                <div class="table-responsive">
                    <table class="table table-sm table-striped">
                        <thead>
                            <tr>
                                <th>Part Code</th>
                                <th>Part Name</th>
                                <th>Used</th>
                                <th>Last Used</th>
                                <th>Stock Remaining</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($topMoving)): ?>
                                <tr><td colspan="5">No spare usage data found.</td></tr>
                            <?php else: ?>
                                <?php foreach ($topMoving as $item): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($item['part_code'] ?? ''); ?></td>
                                        <td><?php echo htmlspecialchars($item['part_name'] ?? ''); ?></td>
                                        <td><?php echo intval($item['quantity_used']); ?></td>
                                        <td><?php echo htmlspecialchars($item['last_used'] ?: 'Never'); ?></td>
                                        <td><?php echo intval($item['stock_remaining']); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <div class="col-lg-4">
            <div class="insights-box">
                <h4>Alerts & Insights</h4>
                <?php if (empty($insights)): ?>
                    <p>No major insights detected yet. Adjust the filters to surface more recommendations.</p>
                <?php else: ?>
                    <?php foreach ($insights as $insight): ?>
                        <div class="insight-item"><strong>Insight</strong><?php echo htmlspecialchars($insight); ?></div>
                    <?php endforeach; ?>
                <?php endif; ?>
                <div class="mt-3">
                    <span class="badge bg-danger me-1">Overstock risk</span>
                    <span class="badge bg-warning me-1">Understock risk</span>
                    <span class="badge bg-info">Consumption spike</span>
                </div>
            </div>
        </div>
    </div>

    <div class="chart-card">
        <div class="chart-title">Stock Level Monitoring</div>
        <div class="table-responsive">
            <table class="table table-sm table-hover">
                <thead>
                    <tr>
                        <th>Part Code</th>
                        <th>Part Name</th>
                        <th>Current Stock</th>
                        <th>Reorder Point</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($detailRows as $row): ?>
                        <?php
                            $current = intval($row['stock_on_hand']);
                            $reorder = intval($row['reorder_point']);
                            if ($reorder > 0 && $current <= $reorder) {
                                $statusLabel = 'Critical';
                                $statusClass = 'danger';
                            } elseif ($reorder > 0 && $current <= $reorder * 1.2) {
                                $statusLabel = 'Warning';
                                $statusClass = 'warning';
                            } else {
                                $statusLabel = 'Healthy';
                                $statusClass = 'success';
                            }
                        ?>
                        <tr>
                            <td><?php echo htmlspecialchars($row['part_code']); ?></td>
                            <td><?php echo htmlspecialchars($row['part_name']); ?></td>
                            <td><?php echo $current; ?></td>
                            <td><?php echo $reorder; ?></td>
                            <td><span class="badge bg-<?php echo $statusClass; ?>"><?php echo $statusLabel; ?></span></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <div class="chart-card">
        <div class="chart-title">Detailed Spare Part Table</div>
        <div class="table-responsive">
            <table class="table table-bordered table-hover">
                <thead>
                    <tr>
                        <th>Part Code</th>
                        <th>Part Name</th>
                        <th>Category</th>
                        <th>Opening Stock</th>
                        <th>Quantity Used</th>
                        <th>Closing Stock</th>
                        <th>Consumption/Day</th>
                        <th>Last Used</th>
                        <th>Reorder Level</th>
                        <th>Supplier</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($detailRows)): ?>
                        <tr><td colspan="10">No spare data found for the selected filters.</td></tr>
                    <?php else: ?>
                        <?php foreach ($detailRows as $row): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($row['part_code'] ?? ''); ?></td>
                                <td><?php echo htmlspecialchars($row['part_name'] ?? ''); ?></td>
                                <td><?php echo htmlspecialchars($row['category'] ?? ''); ?></td>
                                <td><?php echo intval($row['opening_stock']); ?></td>
                                <td><?php echo intval($row['quantity_used']); ?></td>
                                <td><?php echo intval($row['stock_on_hand']); ?></td>
                                <td><?php echo number_format($row['consumption_rate'], 2); ?></td>
                                <td><?php echo htmlspecialchars($row['last_used'] ?: 'Never'); ?></td>
                                <td><?php echo intval($row['reorder_point']); ?></td>
                                <td><?php echo htmlspecialchars(($row['supplier_part_number'] ?? $row['manufacturer']) ?: 'Unknown'); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <div class="chart-card">
        <div class="chart-title">Spare Part Lifecycle Flow</div>
        <div class="row">
            <div class="col-md-4">
                <div class="lifecycle-step"><div class="lifecycle-step-icon">1</div><div class="lifecycle-step-body"><h5>Procurement</h5><p>Supplier and purchase activity aggregated per part.</p></div></div>
            </div>
            <div class="col-md-4">
                <div class="lifecycle-step"><div class="lifecycle-step-icon">2</div><div class="lifecycle-step-body"><h5>Stock Entry</h5><p>Current stock recorded from warehouse locations.</p></div></div>
            </div>
            <div class="col-md-4">
                <div class="lifecycle-step"><div class="lifecycle-step-icon">3</div><div class="lifecycle-step-body"><h5>Storage</h5><p>Stock held across locations and safety stock bands.</p></div></div>
            </div>
            <div class="col-md-4">
                <div class="lifecycle-step"><div class="lifecycle-step-icon">4</div><div class="lifecycle-step-body"><h5>Usage</h5><p>Issued parts tied to work orders for root-cause analytics.</p></div></div>
            </div>
            <div class="col-md-4">
                <div class="lifecycle-step"><div class="lifecycle-step-icon">5</div><div class="lifecycle-step-body"><h5>Reorder Trigger</h5><p>Reorder thresholds and understock alerts for each part.</p></div></div>
            </div>
        </div>
    </div>
</div>

<script>
    const trendCtx = document.getElementById('consumptionTrend').getContext('2d');
    const categoryCtx = document.getElementById('categoryBreakdown').getContext('2d');
    const assetCtx = document.getElementById('assetUsage').getContext('2d');
    const spendingCtx = document.getElementById('monthlySpending').getContext('2d');

    const trendLabels = <?php echo json_encode(array_values($trendLabels)); ?>;
    const trendValues = <?php echo json_encode(array_values($trendValues)); ?>;
    const categoryLabels = <?php echo json_encode(array_values($categoryLabels)); ?>;
    const categoryValues = <?php echo json_encode(array_values($categoryValues)); ?>;
    const assetLabels = <?php echo json_encode(array_values($assetLabels)); ?>;
    const assetValues = <?php echo json_encode(array_values($assetValues)); ?>;
    const spendingLabels = <?php echo json_encode(array_values($spendingLabels)); ?>;
    const spendingValues = <?php echo json_encode(array_values($spendingValues)); ?>;

    new Chart(trendCtx, {
        type: 'line',
        data: {
            labels: trendLabels,
            datasets: [{ label: 'Parts Consumed', data: trendValues, borderColor: '#0d6efd', backgroundColor: 'rgba(13,110,253,0.15)', fill: true, tension: 0.3, pointRadius: 3 }]
        },
        options: { responsive: true, plugins: { legend: { display: false } }, scales: { y: { beginAtZero: true } } }
    });

    new Chart(categoryCtx, {
        type: 'bar',
        data: {
            labels: categoryLabels,
            datasets: [{ label: 'Consumption', data: categoryValues, backgroundColor: '#198754' }]
        },
        options: { responsive: true, plugins: { legend: { display: false } }, scales: { y: { beginAtZero: true } } }
    });

    new Chart(assetCtx, {
        type: 'bar',
        data: {
            labels: assetLabels,
            datasets: [{ label: 'Used', data: assetValues, backgroundColor: '#0dcaf0' }]
        },
        options: { responsive: true, plugins: { legend: { display: false } }, scales: { y: { beginAtZero: true } } }
    });

    new Chart(spendingCtx, {
        type: 'line',
        data: {
            labels: spendingLabels,
            datasets: [{ label: 'Spending', data: spendingValues, borderColor: '#dc3545', backgroundColor: 'rgba(220,53,69,0.15)', fill: true, tension: 0.3, pointRadius: 3 }]
        },
        options: { responsive: true, plugins: { legend: { display: false } }, scales: { y: { beginAtZero: true } } }
    });
</script>
