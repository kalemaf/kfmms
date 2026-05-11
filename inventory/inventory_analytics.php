
<?php
// --- Standardized session handling ---
require_once("../config.inc.php");
session_save_path($session_save_path);
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (!empty($debug_mode)) {
    error_log("[DEBUG] inventory/inventory_analytics.php SID=" . session_id() . ", SESSION=" . json_encode($_SESSION));
}

if (!isset($_SESSION['user'])) {
    header("Location: ../auth.php");
    exit;
}

require_once("../common.inc.php");
require_once("../libraries/inventory_manager.php");

// Ensure all required tables exist
ensure_inventory_summary_table($connection);

$title = "Inventory Analytics & Reports";
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($title); ?> - CMMS</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">

    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { background: #f5f5f5; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; }
        .container { max-width: 1200px; margin: 20px auto; padding: 0 15px; }
        .header { background: white; padding: 25px; border-radius: 8px; margin-bottom: 25px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        .header h1 { color: #333; margin-bottom: 5px; font-weight: 700; }
        .stat-card { background: white; padding: 25px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); margin-bottom: 20px; border-left: 4px solid #667eea; }
        .stat-card h5 { font-weight: 600; color: #666; text-transform: uppercase; font-size: 12px; margin-bottom: 10px; }
        .stat-card .value { font-size: 32px; font-weight: 700; color: #333; }
        .stat-card .unit { font-size: 14px; color: #999; margin-left: 5px; }
        .chart-container { background: white; padding: 25px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); margin-bottom: 25px; }
        .table-container { background: white; padding: 25px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); margin-bottom: 25px; }
        .table { font-size: 13px; margin-bottom: 0; }
        .table thead { background: #f8f9fa; }
        .table th { font-weight: 700; color: #333; border-top: none; }
        .table-hover tbody tr:hover { background: #f8f9fa; }
        .badge-status { font-size: 11px; padding: 5px 10px; }
        .status-critical { background: #f8d7da; color: #721c24; }
        .status-low { background: #fff3cd; color: #856404; }
        .status-normal { background: #d4edda; color: #155724; }
        .status-overstock { background: #cce5ff; color: #004085; }
        .grid-2 { display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 20px; margin-bottom: 25px; }
        .grid-3 { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px; margin-bottom: 25px; }
        .alert { margin-bottom: 20px; }
        .tabs-nav { background: white; padding: 15px; border-radius: 8px; margin-bottom: 25px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        .tabs-nav a { display: inline-block; padding: 10px 20px; margin-right: 5px; text-decoration: none; color: #666; border-bottom: 3px solid transparent; }
        .tabs-nav a.active { color: #667eea; border-bottom-color: #667eea; font-weight: 600; }
    </style>
</head>
<body>

<div class="container">
    
    <!-- Header -->
    <div class="header">
        <h1><i class="fas fa-chart-bar"></i> Inventory Analytics & Reports</h1>
        <p>Real-time inventory insights and performance metrics</p>
    </div>
    
    <?php
    // Get stock status summary
    $status_summary = get_stock_status_summary($connection);
    
    // Get reorder parts
    $reorder_parts = get_reorder_parts($connection);
    
    // Get inventory value report
    $inventory_value = get_inventory_value_report($connection);
    
    $total_value = 0;
    $total_qty = 0;
    foreach ($inventory_value as $item) {
        $total_value += floatval($item['total_value']);
        $total_qty += intval($item['total_qty']);
    }
    ?>
    
    <!-- Key Metrics -->
    <div class="grid-3">
        <div class="stat-card">
            <h5><i class="fas fa-box"></i> Total Parts</h5>
            <div class="value"><?php echo intval($status_summary['total_parts'] ?? 0); ?></div>
        </div>
        <div class="stat-card" style="border-left-color: #27ae60;">
            <h5><i class="fas fa-check-circle"></i> Normal Stock</h5>
            <div class="value"><?php echo intval($status_summary['normal_count'] ?? 0); ?></div>
        </div>
        <div class="stat-card" style="border-left-color: #f39c12;">
            <h5><i class="fas fa-exclamation-triangle"></i> Low Stock</h5>
            <div class="value"><?php echo intval($status_summary['low_count'] ?? 0); ?></div>
        </div>
        <div class="stat-card" style="border-left-color: #e74c3c;">
            <h5><i class="fas fa-alert"></i> Critical Stock</h5>
            <div class="value"><?php echo intval($status_summary['critical_count'] ?? 0); ?></div>
        </div>
        <div class="stat-card" style="border-left-color: #3498db;">
            <h5><i class="fas fa-cubes"></i> Overstock</h5>
            <div class="value"><?php echo intval($status_summary['overstock_count'] ?? 0); ?></div>
        </div>
        <div class="stat-card" style="border-left-color: #9b59b6;">
            <h5><i class="fas fa-dollar-sign"></i> Total Inventory Value</h5>
            <div class="value">$<span class="unit" style="font-size: 20px; margin-left: 0;"><?php echo number_format($total_value, 0); ?></span></div>
        </div>
    </div>
    
    <!-- Stock Status Chart -->
    <div class="chart-container">
        <h5 style="font-weight: 700; margin-bottom: 20px;">
            <i class="fas fa-pie-chart"></i> Stock Status Distribution
        </h5>
        <canvas id="statusChart" style="max-height: 300px;"></canvas>
    </div>
    
    <!-- Reorder Alert -->
    <?php if (count($reorder_parts) > 0): ?>
        <div class="alert alert-warning">
            <i class="fas fa-exclamation-circle"></i> <strong><?php echo count($reorder_parts); ?> parts need reordering!</strong>
            Review the list below and create purchase requests for low-stock items.
        </div>
    <?php endif; ?>
    
    <!-- Reorder Parts Table -->
    <div class="table-container">
        <h5 style="font-weight: 700; margin-bottom: 20px;">
            <i class="fas fa-shopping-cart"></i> Parts Requiring Reorder
        </h5>
        
        <?php if (count($reorder_parts) > 0): ?>
            <div class="table-responsive">
                <table class="table table-striped table-hover">
                    <thead>
                        <tr>
                            <th>Part Code</th>
                            <th>Part Name</th>
                            <th>Current Stock</th>
                            <th>Reorder Point</th>
                            <th>Shortage</th>
                            <th>Unit Cost</th>
                            <th>Reorder Value</th>
                            <th>Lead Time</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($reorder_parts as $p): 
                            $shortage = max(0, intval($p['reorder_point']) - intval($p['current_stock'] ?? 0));
                            $reorder_value = $shortage * floatval($p['unit_cost'] ?? 0);
                        ?>
                            <tr>
                                <td><strong><?php echo htmlspecialchars($p['part_code']); ?></strong></td>
                                <td><?php echo htmlspecialchars($p['part_name']); ?></td>
                                <td><?php echo intval($p['current_stock']); ?></td>
                                <td><?php echo intval($p['reorder_point']); ?></td>
                                <td><span style="color: #e74c3c; font-weight: 700;"><?php echo $shortage; ?></span></td>
                                <td>$<?php echo number_format($p['unit_cost'], 2); ?></td>
                                <td>$<?php echo number_format($reorder_value, 2); ?></td>
                                <td><?php echo $p['lead_time_days']; ?> days</td>
                                <td>
                                    <a href="purchase_requests.php?action=create&part_id=<?php echo $p['id']; ?>" class="btn btn-sm btn-primary">
                                        <i class="fas fa-plus"></i> PR
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle"></i> All parts are above reorder point. No immediate reordering needed.
            </div>
        <?php endif; ?>
    </div>
    
    <!-- Inventory Value by Classification -->
    <div class="chart-container">
        <h5 style="font-weight: 700; margin-bottom: 20px;">
            <i class="fas fa-chart-line"></i> Inventory Value by Part
        </h5>
        
        <div class="table-responsive">
            <table class="table table-striped table-hover" style="font-size: 12px;">
                <thead>
                    <tr>
                        <th>Part Code</th>
                        <th>Part Name</th>
                        <th>On Hand</th>
                        <th>Unit Cost</th>
                        <th>Total Value</th>
                        <th>% of Total</th>
                        <th>Category</th>
                        <th>Criticality</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach (array_slice($inventory_value, 0, 20) as $item): 
                        $pct = $total_value > 0 ? (floatval($item['total_value']) / $total_value) * 100 : 0;
                    ?>
                        <tr>
                            <td><strong><?php echo htmlspecialchars($item['part_code']); ?></strong></td>
                            <td><?php echo htmlspecialchars(substr($item['part_name'], 0, 40)); ?></td>
                            <td><?php echo intval($item['total_qty']); ?></td>
                            <td>$<?php echo number_format($item['unit_cost'] ?? 0, 2); ?></td>
                            <td><strong>$<?php echo number_format($item['total_value'] ?? 0, 2); ?></strong></td>
                            <td>
                                <div style="width: 100%; background: #e9ecef; border-radius: 4px; overflow: hidden; height: 20px;">
                                    <div style="width: <?php echo min(100, $pct); ?>%; height: 100%; background: #667eea;"></div>
                                </div>
                                <?php echo round($pct, 1); ?>%
                            </td>
                            <td><?php echo htmlspecialchars($item['category'] ?? 'N/A'); ?></td>
                            <td>
                                <span class="badge-status status-<?php echo strtolower($item['criticality_level'] ?? 'normal'); ?>">
                                    <?php echo htmlspecialchars($item['criticality_level'] ?? 'N/A'); ?>
                                </span>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    
    <!-- ABC Classification Summary -->
    <div class="table-container">
        <h5 style="font-weight: 700; margin-bottom: 20px;">
            <i class="fas fa-th"></i> ABC Classification Summary
        </h5>
        
        <?php
        $abc_summary = [
            'A' => ['count' => 0, 'value' => 0, 'description' => 'High Value (80% of inventory value)'],
            'B' => ['count' => 0, 'value' => 0, 'description' => 'Medium Value (15% of inventory value)'],
            'C' => ['count' => 0, 'value' => 0, 'description' => 'Low Value (5% of inventory value)']
        ];
        
        foreach ($inventory_value as $item) {
            $class = $item['abc_classification'] ?? 'C';
            if (isset($abc_summary[$class])) {
                $abc_summary[$class]['count']++;
                $abc_summary[$class]['value'] += floatval($item['total_value']);
            }
        }
        ?>
        
        <div class="grid-3">
            <?php foreach ($abc_summary as $class => $data): 
                $pct = $total_value > 0 ? ($data['value'] / $total_value) * 100 : 0;
                $colors = ['A' => '#e74c3c', 'B' => '#f39c12', 'C' => '#3498db'];
            ?>
                <div style="background: #f8f9fa; padding: 20px; border-radius: 8px; border-top: 4px solid <?php echo $colors[$class]; ?>;">
                    <div style="font-size: 14px; font-weight: 700; color: #333; margin-bottom: 10px;">
                        Class <?php echo $class ?> - <?php echo $data['description']; ?>
                    </div>
                    <div style="font-size: 24px; font-weight: 700; color: <?php echo $colors[$class]; ?>;  margin-bottom: 5px;">
                        <?php echo $data['count']; ?> Parts
                    </div>
                    <div style="font-size: 14px; color: #666;">
                        $<?php echo number_format($data['value'], 0); ?> (<?php echo round($pct, 1); ?>%)
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
    
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>

<script>
// Status Distribution Chart - defer until DOM ready
document.addEventListener('DOMContentLoaded', function() {
    const statusChartElement = document.getElementById('statusChart');
    if (statusChartElement && typeof Chart !== 'undefined') {
        const ctx = statusChartElement.getContext('2d');
        const statusData = {
            normal: <?php echo intval($status_summary['normal_count'] ?? 0); ?>,
            low: <?php echo intval($status_summary['low_count'] ?? 0); ?>,
            critical: <?php echo intval($status_summary['critical_count'] ?? 0); ?>,
            overstock: <?php echo intval($status_summary['overstock_count'] ?? 0); ?>
        };

        new Chart(ctx, {
            type: 'doughnut',
            data: {
                labels: ['Normal', 'Low Stock', 'Critical', 'Overstock'],
                datasets: [{
                    data: [statusData.normal, statusData.low, statusData.critical, statusData.overstock],
                    backgroundColor: ['#27ae60', '#f39c12', '#e74c3c', '#3498db'],
                    borderColor: '#fff',
                    borderWidth: 2
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom',
                        labels: { padding: 15, font: { size: 12, weight: '600' }, usePointStyle: true }
                    }
                }
            }
        });
    }
});
</script>

</body>
</html>
