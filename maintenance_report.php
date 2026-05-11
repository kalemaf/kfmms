<?php
/**
 * Monthly Maintenance Report
 * Shows spares used, costs, MTBF per equipment, linked to work orders.
 * Printable report.
 */

require_once 'config.inc.php';
require_once 'common.inc.php';
require_once 'spare_integration_functions.php';
require_once 'libraries/metrics.php';

// Auto-detect spares used based on work order descriptions
function auto_detect_spares($wo, $connection) {
    $detected_spares = [];
    $text_to_analyze = strtolower($wo['description'] . ' ' . $wo['descriptive_text'] . ' ' . ($wo['action'] ?? ''));
    
    // Get equipment spares for this equipment
    $equip_id = $wo['equipment'];
    if (is_numeric($equip_id)) {
        $tenant_id = (int)($_SESSION['tenant_id'] ?? 1);
        $spares_query = "SELECT part_name, part_number FROM equipment_spares WHERE equipment_id = " . intval($equip_id) . " AND tenant_id = {$tenant_id}";
    } else {
        // Try to match by description
        $tenant_id = (int)($_SESSION['tenant_id'] ?? 1);
        $spares_query = "SELECT es.part_name, es.part_number FROM equipment_spares es 
                        JOIN equipment e ON es.equipment_id = e.id 
                        WHERE e.description = '" . $connection->real_escape_string($equip_id) . "' AND es.tenant_id = {$tenant_id}";
    }
    $spares_result = $connection->query($spares_query);
    $existing_spares = [];
    if ($spares_result) {
        while ($spare = $spares_result->fetch_assoc()) {
            $existing_spares[] = $spare;
            $spare_name = strtolower($spare['part_name']);
            $part_number = strtolower($spare['part_number']);
            
            // Check if spare is mentioned in work order text
            if (strpos($text_to_analyze, $spare_name) !== false || 
                strpos($text_to_analyze, $part_number) !== false) {
                $detected_spares[$spare['part_name']] = ($detected_spares[$spare['part_name']] ?? 0) + 1;
            }
        }
    }
    
    // Common spare part keywords that map to likely used parts
    $keyword_mappings = [
        'bolt' => 'Bolt',
        'bearing' => 'Bearing', 
        'shaft' => 'Shaft',
        'gear' => 'Gear',
        'seal' => 'Seal',
        'bush' => 'Bush',
        'chain' => 'Chain',
        'cam' => 'Cam',
        'filter' => 'Filter',
        'pump' => 'Pump',
        'motor' => 'Motor',
        'valve' => 'Valve',
        'hose' => 'Hose',
        'belt' => 'Belt'
    ];
    
    foreach ($keyword_mappings as $keyword => $spare_name) {
        if (strpos($text_to_analyze, $keyword) !== false) {
            // Check if this spare exists for the equipment
            $exists = false;
            foreach ($existing_spares as $spare) {
                if (strtolower($spare['part_name']) === strtolower($spare_name)) {
                    $exists = true;
                    break;
                }
            }
            if ($exists || !empty($detected_spares)) {
                $detected_spares[$spare_name] = ($detected_spares[$spare_name] ?? 0) + 1;
            }
        }
    }
    
    return $detected_spares;
}

// Retroactively apply spare reductions for completed work orders
function apply_retroactive_spare_reductions($start_date, $end_date, $connection) {
    $query = "SELECT wo.*, e.id as equip_id FROM work_orders wo 
              LEFT JOIN equipment e ON (wo.equipment = CAST(e.id AS CHAR) OR wo.equipment = e.description)
              WHERE wo.submit_date BETWEEN '$start_date' AND '$end_date' 
              AND wo.wo_status = 'Completed'";
    // Apply tenant filtering
    $query = apply_tenant_filter($query);
    $result = $connection->query($query);
    if ($result) {
        while ($wo = $result->fetch_assoc()) {
            // Check if spares already recorded
            $check_query = "SELECT COUNT(*) as count FROM work_order_spares WHERE wo_id = " . intval($wo['wo_id']);
            $check_query = apply_tenant_filter($check_query);
            $check_result = $connection->query($check_query);
            $already_recorded = false;
            
            if ($check_result && $check_result->fetch_assoc()['count'] > 0) {
                $already_recorded = true;
            }
            
            if (!$already_recorded) {
                $detected_spares = auto_detect_spares($wo, $connection);
                
                foreach ($detected_spares as $spare_name => $quantity) {
                    // Find the spare in equipment_spares
                    $spare_query = "SELECT id FROM equipment_spares 
                                   WHERE equipment_id = " . intval($wo['equip_id']) . " 
                                   AND part_name = '" . $connection->real_escape_string($spare_name) . "'";
                    // Apply tenant filtering
                    $spare_query = apply_tenant_filter($spare_query);
                    $spare_result = $connection->query($spare_query);
                    
                    if ($spare_result && $spare_result->num_rows > 0) {
                        $spare_id = $spare_result->fetch_assoc()['id'];
                        
                        // Reduce quantity in equipment_spares
                        $update_query = "UPDATE equipment_spares SET quantity = GREATEST(0, quantity - $quantity) WHERE id = $spare_id";
                        $update_query = apply_tenant_filter($update_query);
                        $connection->query($update_query);
                        
                        // Record the usage
                        $insert_query = "INSERT INTO work_order_spares (wo_id, spare_id, quantity_used, tenant_id) 
                                        VALUES (" . intval($wo['wo_id']) . ", $spare_id, $quantity, {$_SESSION['tenant_id']})";
                        $connection->query($insert_query);
                    }
                }
            }
        }
    }
}

function get_equipment_failure_count($equipment_id, $start_date, $end_date, $connection) {
    $equipment_id = intval($equipment_id);
    if (!function_exists('metric_table_exists') || !metric_table_exists('failures')) {
        return 0;
    }

    $sql = "SELECT COUNT(*) AS count FROM failures 
            WHERE equipment_id = $equipment_id 
            AND DATE(failure_datetime) BETWEEN '" . $connection->real_escape_string($start_date) . "' AND '" . $connection->real_escape_string($end_date) . "'";
    // Apply tenant filtering
    $sql = apply_tenant_filter($sql);
    $result = $connection->query($sql);
    if ($result && ($row = $result->fetch_assoc())) {
        return intval($row['count']);
    }
    return 0;
}

function count_workorder_failures($work_orders) {
    $failures = 0;
    foreach ($work_orders as $wo) {
        $status = strtolower($wo['maintenance_type'] ?? '');
        $description = strtolower($wo['description'] ?? '');
        $descriptive = strtolower($wo['descriptive_text'] ?? '');
        if ($status === 'corrective' || strpos($description, 'fail') !== false || strpos($descriptive, 'fail') !== false) {
            $failures++;
        }
    }
    return $failures;
}

function calculate_mtbf_from_workorders($work_orders) {
    $timestamps = [];
    foreach ($work_orders as $wo) {
        if ($wo['wo_status'] !== 'Completed' && empty($wo['complete_date'])) {
            continue;
        }

        $date = $wo['complete_date'] ?: $wo['submit_date'];
        $ts = strtotime($date);
        if ($ts) {
            $timestamps[] = $ts;
        }
    }

    sort($timestamps);
    if (count($timestamps) < 2) {
        return null;
    }

    $intervals = [];
    for ($i = 1; $i < count($timestamps); $i++) {
        $intervals[] = $timestamps[$i] - $timestamps[$i - 1];
    }
    if (empty($intervals)) {
        return null;
    }

    return round(array_sum($intervals) / count($intervals) / (60 * 60 * 24), 1);
}

// Get month/year from GET, default to current
$month = isset($_GET['month']) ? intval($_GET['month']) : date('m');
$year = isset($_GET['year']) ? intval($_GET['year']) : date('Y');

// Validate
if ($month < 1 || $month > 12) $month = date('m');
if ($year < 2000 || $year > date('Y') + 1) $year = date('Y');

$start_date = sprintf('%04d-%02d-01', $year, $month);
$end_date = date('Y-m-t', strtotime($start_date)); // Last day of month

$report_data = [];

if ($connection) {
    // Apply retroactive spare reductions if requested
    if (isset($_GET['apply_retroactive'])) {
        apply_retroactive_spare_reductions($start_date, $end_date, $connection);
        $message = "Retroactive spare reductions applied for $month/$year";
    }
    
    // Get all work orders in the period with equipment info
    $wo_sql = "SELECT wo.*, e.description as equip_name, e.id as equip_id
               FROM work_orders wo
               LEFT JOIN equipment e ON (wo.equipment = CAST(e.id AS CHAR) OR wo.equipment = e.description)
               WHERE wo.submit_date BETWEEN '$start_date' AND '$end_date'
               ORDER BY e.description, wo.submit_date";
    // Apply tenant filtering
    $wo_sql = apply_tenant_filter($wo_sql);
    $wo_res = $connection->query($wo_sql);

    if ($wo_res) {
        $equipment_data = [];
        
        while ($wo = $wo_res->fetch_assoc()) {
            $equip_id = $wo['equip_id'] ?: 'unknown_' . $wo['equipment'];
            $equip_name = $wo['equip_name'] ?: 'Unknown Equipment';
            
            if (!isset($equipment_data[$equip_id])) {
                $equipment_data[$equip_id] = [
                    'id' => $equip_id,
                    'name' => $equip_name,
                    'work_orders' => [],
                    'total_material_cost' => 0,
                    'total_labor_cost' => 0,
                    'total_cost' => 0,
                    'spares_used' => [],
                    'failure_dates' => [],
                    'failure_count' => 0,
                    'mtbf_days' => null
                ];
            }
            
            $equipment_data[$equip_id]['work_orders'][] = $wo;
            
            // Accumulate costs
            $equipment_data[$equip_id]['total_material_cost'] += floatval($wo['material_cost'] ?? 0);
            $equipment_data[$equip_id]['total_labor_cost'] += floatval($wo['labor_cost'] ?? 0);
            $equipment_data[$equip_id]['total_cost'] += floatval($wo['total_cost'] ?? 0);
            
            // Add spare and consumable costs to total material cost
            $spare_cost = 0;
            $consumable_cost = 0;
            
            // Get spare part costs
            $tenant_id = (int)($_SESSION['tenant_id'] ?? 1);
            $spare_detail_query = "SELECT COALESCE(SUM(wos.quantity_used * COALESCE(pm.unit_cost, 0)), 0) as total_cost
                                  FROM work_order_spares wos
                                  LEFT JOIN equipment_spares es ON wos.spare_id = es.id AND es.tenant_id = {$tenant_id}
                                  LEFT JOIN parts_master pm ON es.part_id = pm.id AND pm.tenant_id = {$tenant_id}
                                  WHERE wos.wo_id = " . intval($wo['wo_id']) . " AND wos.tenant_id = {$tenant_id}";
            // No need for apply_tenant_filter since we've added explicit tenant_id checks
            $spare_detail_result = $connection->query($spare_detail_query);
            if ($spare_detail_result) {
                $spare_detail_row = $spare_detail_result->fetch_assoc();
                $spare_cost = floatval($spare_detail_row['total_cost'] ?? 0);
            }
            
            // Get consumable costs
            $consumable_detail_query = "SELECT COALESCE(SUM(woc.quantity_required * woc.unit_cost), 0) as total_cost
                                       FROM work_order_consumables woc
                                       WHERE woc.work_order_id = " . intval($wo['wo_id']);
            // Apply tenant filtering
            $consumable_detail_query = apply_tenant_filter($consumable_detail_query);
            $consumable_detail_result = $connection->query($consumable_detail_query);
            if ($consumable_detail_result) {
                $consumable_detail_row = $consumable_detail_result->fetch_assoc();
                $consumable_cost = floatval($consumable_detail_row['total_cost'] ?? 0);
            }
            
            $total_material_cost = $spare_cost + $consumable_cost;
            $equipment_data[$equip_id]['total_material_cost'] += $total_material_cost;
            $equipment_data[$equip_id]['total_cost'] += $total_material_cost;
            
            // Query actual spares used from work_order_spares table with costs
            $tenant_id = (int)($_SESSION['tenant_id'] ?? 1);
            $spares_query = "SELECT wos.quantity_used, COALESCE(es.part_name, 'Unknown Spare') as part_name, pm.unit_cost,
                                   (wos.quantity_used * COALESCE(pm.unit_cost, 0)) as total_cost
                            FROM work_order_spares wos
                            LEFT JOIN equipment_spares es ON wos.spare_id = es.id AND es.tenant_id = {$tenant_id}
                            LEFT JOIN parts_master pm ON es.part_id = pm.id AND pm.tenant_id = {$tenant_id}
                            WHERE wos.wo_id = " . intval($wo['wo_id']) . " AND wos.tenant_id = {$tenant_id}";
            // No need for apply_tenant_filter since we've added explicit tenant_id checks
            $spares_result = $connection->query($spares_query);
            if ($spares_result) {
                while ($spare_row = $spares_result->fetch_assoc()) {
                    $spare_name = $spare_row['part_name'];
                    $qty = intval($spare_row['quantity_used']);
                    $cost = floatval($spare_row['total_cost'] ?? 0);
                    if (!isset($equipment_data[$equip_id]['spares_used'][$spare_name])) {
                        $equipment_data[$equip_id]['spares_used'][$spare_name] = [
                            'qty' => 0,
                            'cost' => 0
                        ];
                    }
                    $equipment_data[$equip_id]['spares_used'][$spare_name]['qty'] += $qty;
                    $equipment_data[$equip_id]['spares_used'][$spare_name]['cost'] += $cost;
                }
            }
            
            // Auto-detect spares from work order descriptions if no spares recorded
            if (empty($equipment_data[$equip_id]['spares_used']) && $wo['wo_status'] === 'Completed') {
                $detected_spares = auto_detect_spares($wo, $connection);
                foreach ($detected_spares as $spare_name => $quantity) {
                    if (!isset($equipment_data[$equip_id]['spares_used'][$spare_name])) {
                        $equipment_data[$equip_id]['spares_used'][$spare_name] = 0;
                    }
                    $equipment_data[$equip_id]['spares_used'][$spare_name] += $quantity;
                }
            }
        }
        
        // Calculate MTBF and failure counts from either the failures history or completed work orders
        foreach ($equipment_data as $equip_id => &$data) {
            $use_failure_table = function_exists('metric_table_exists') && metric_table_exists('failures');

            if (is_numeric($data['id']) && intval($data['id']) > 0 && $use_failure_table) {
                $data['failure_count'] = get_equipment_failure_count(intval($data['id']), $start_date, $end_date, $connection);
                $mtbf_seconds = calculate_mtbf(intval($data['id']));
                $data['mtbf_days'] = $mtbf_seconds ? round($mtbf_seconds / (60*60*24), 1) : null;
            }

            // Fallback to work order based MTBF when failure table isn't available or when no failure events were found.
            if (empty($data['mtbf_days'])) {
                $workorder_mtbf = calculate_mtbf_from_workorders($data['work_orders']);
                if ($workorder_mtbf !== null) {
                    $data['mtbf_days'] = $workorder_mtbf;
                }
            }

            if (empty($data['failure_count'])) {
                $data['failure_count'] = count_workorder_failures($data['work_orders']);
            }
        }
        
        // Ensure no duplicate equipment entries and dedup work orders, sort by equipment name
        $unique_equipment = [];
        foreach ($equipment_data as $equip_id => $data) {
            $key = $data['id'] . '_' . $data['name']; // Create unique key
            if (!isset($unique_equipment[$key])) {
                $unique_equipment[$key] = $data;
            } else {
                // Merge work orders if duplicate equipment found (avoid duplicating WOs)
                $wo_ids_seen = [];
                foreach ($unique_equipment[$key]['work_orders'] as $existing_wo) {
                    $wo_ids_seen[$existing_wo['wo_id']] = true;
                }
                foreach ($data['work_orders'] as $new_wo) {
                    if (!isset($wo_ids_seen[$new_wo['wo_id']])) {
                        $unique_equipment[$key]['work_orders'][] = $new_wo;
                        $wo_ids_seen[$new_wo['wo_id']] = true;
                    }
                }
                $unique_equipment[$key]['total_material_cost'] += $data['total_material_cost'];
                $unique_equipment[$key]['total_labor_cost'] += $data['total_labor_cost'];
                $unique_equipment[$key]['total_cost'] += $data['total_cost'];
                // Merge spares_used
                foreach ($data['spares_used'] as $spare => $info) {
                    if (isset($unique_equipment[$key]['spares_used'][$spare])) {
                        if (is_array($info)) {
                            $unique_equipment[$key]['spares_used'][$spare]['qty'] += $info['qty'];
                            $unique_equipment[$key]['spares_used'][$spare]['cost'] += $info['cost'];
                        } else {
                            $unique_equipment[$key]['spares_used'][$spare] += $info;
                        }
                    } else {
                        $unique_equipment[$key]['spares_used'][$spare] = $info;
                    }
                }
                // Merge failure dates
                $unique_equipment[$key]['failure_dates'] = array_unique(array_merge($unique_equipment[$key]['failure_dates'], $data['failure_dates']));
            }
        }
        
        $report_data = array_values($unique_equipment);
    }
}

// Month navigation
$prev_month = $month - 1;
$prev_year = $year;
if ($prev_month < 1) {
    $prev_month = 12;
    $prev_year--;
}
$next_month = $month + 1;
$next_year = $year;
if ($next_month > 12) {
    $next_month = 1;
    $next_year++;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Monthly Maintenance Report - <?php echo date('F Y', strtotime("$year-$month-01")); ?></title>
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 20px;
            color: #333;
        }
        .header {
            text-align: center;
            margin-bottom: 30px;
            border-bottom: 2px solid #333;
            padding-bottom: 10px;
        }
        .header h1 {
            margin: 0;
            font-size: 24px;
        }
        .header p {
            margin: 5px 0;
            color: #666;
        }
        .navigation {
            text-align: center;
            margin-bottom: 20px;
        }
        .navigation a {
            margin: 0 10px;
            text-decoration: none;
            color: #007bff;
        }
        .equipment-section {
            margin-bottom: 40px;
            page-break-inside: avoid;
        }
        .equipment-header {
            background: #f8f9fa;
            padding: 10px;
            border-left: 4px solid #007bff;
            margin-bottom: 15px;
        }
        .equipment-header h2 {
            margin: 0;
            font-size: 18px;
        }
        .metrics {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-bottom: 20px;
        }
        .metric {
            background: #e9ecef;
            padding: 10px;
            border-radius: 5px;
        }
        .metric strong {
            display: block;
            font-size: 18px;
            color: #007bff;
        }
        .metric span {
            font-size: 12px;
            color: #666;
        }
        .spares-list {
            margin-bottom: 20px;
        }
        .spares-list h3 {
            margin-bottom: 10px;
            font-size: 14px;
        }
        .spares-list ul {
            list-style: none;
            padding: 0;
        }
        .spares-list li {
            padding: 5px 0;
            border-bottom: 1px solid #eee;
        }
        .work-orders {
            margin-bottom: 20px;
        }
        .work-orders h3 {
            margin-bottom: 10px;
            font-size: 14px;
        }
        .work-orders table {
            width: 100%;
            border-collapse: collapse;
            font-size: 12px;
        }
        .work-orders th, .work-orders td {
            border: 1px solid #ddd;
            padding: 8px;
            text-align: left;
        }
        .work-orders th {
            background: #f8f9fa;
        }
        @media print {
            body {
                margin: 0;
            }
            .navigation {
                display: none;
            }
            .equipment-section {
                page-break-after: always;
            }
            .equipment-section:last-child {
                page-break-after: avoid;
            }
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>Monthly Maintenance Report</h1>
        <p><?php echo date('F Y', strtotime("$year-$month-01")); ?></p>
        <p>Report generated on <?php echo date('Y-m-d H:i:s'); ?></p>
        <?php if (isset($message)): ?>
            <p style="color: green; font-weight: bold;"><?php echo htmlspecialchars($message); ?></p>
        <?php endif; ?>
        <p><strong>Report Overview:</strong> This report shows maintenance activities, costs, and performance metrics for each piece of equipment during the selected month. It includes spare parts usage, labor and material costs, and Mean Time Between Failures (MTBF) calculations.</p>
    </div>

    <div class="navigation">
        <a href="?month=<?php echo $prev_month; ?>&year=<?php echo $prev_year; ?>">&larr; Previous Month</a>
        <a href="?month=<?php echo date('m'); ?>&year=<?php echo date('Y'); ?>">Current Month</a>
        <a href="?month=<?php echo $next_month; ?>&year=<?php echo $next_year; ?>">Next Month &rarr;</a>
        <?php if (!isset($_GET['apply_retroactive'])): ?>
            <a href="?month=<?php echo $month; ?>&year=<?php echo $year; ?>&apply_retroactive=1" style="background: #28a745; color: white; padding: 5px 10px; text-decoration: none; border-radius: 3px; margin-left: 20px;">Apply Spare Reductions</a>
        <?php endif; ?>
    </div>

    <?php if (empty($report_data)): ?>
        <p>No maintenance data found for this month.</p>
    <?php else: ?>
        <?php foreach ($report_data as $data): ?>
            <div class="equipment-section">
                <div class="equipment-header">
                    <h2><?php echo htmlspecialchars($data['name']); ?> (ID: <?php echo $data['id']; ?>)</h2>
                    <p><strong>Equipment Summary:</strong> <?php echo count($data['work_orders']); ?> work orders, <?php echo $data['failure_count']; ?> failures recorded</p>
                </div>

                <div class="metrics">
                    <div class="metric">
                        <strong>$<?php echo number_format($data['total_material_cost'], 2); ?></strong>
                        <span>Material Cost - Parts & Supplies</span>
                    </div>
                    <div class="metric">
                        <strong>$<?php echo number_format($data['total_labor_cost'], 2); ?></strong>
                        <span>Labor Cost - Technician Hours</span>
                    </div>
                    <div class="metric">
                        <strong>$<?php echo number_format($data['total_cost'], 2); ?></strong>
                        <span>Total Cost - Combined Expenses</span>
                    </div>
                    <div class="metric">
                        <strong><?php echo $data['mtbf_days'] ? $data['mtbf_days'] . ' days' : 'N/A'; ?></strong>
                        <span>MTBF - Mean Time Between Failures (<?php echo $data['failure_count']; ?> failures)</span>
                    </div>
                </div>

                <?php if (!empty($data['spares_used'])): ?>
                    <div class="spares-list">
                        <h3>Spare Parts & Materials Used (with quantities and costs):</h3>
                        <p><em>This section shows all parts, supplies, and materials consumed during maintenance work on this equipment.</em></p>
                        <table style="width: 100%; border-collapse: collapse; margin-top: 10px;">
                            <thead>
                                <tr style="background-color: #f0f0f0; border-bottom: 2px solid #333;">
                                    <th style="text-align: left; padding: 8px; border: 1px solid #ddd;">Part Name</th>
                                    <th style="text-align: center; padding: 8px; border: 1px solid #ddd;">Quantity</th>
                                    <th style="text-align: right; padding: 8px; border: 1px solid #ddd;">Cost</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php 
                                $total_spare_cost = 0;
                                foreach ($data['spares_used'] as $spare => $info): 
                                    if (is_array($info)) {
                                        $qty = $info['qty'];
                                        $cost = $info['cost'];
                                    } else {
                                        $qty = $info;
                                        $cost = 0;
                                    }
                                    $total_spare_cost += $cost;
                                ?>
                                <tr style="border-bottom: 1px solid #ddd;">
                                    <td style="padding: 8px; border: 1px solid #ddd;"><?php echo htmlspecialchars($spare); ?></td>
                                    <td style="padding: 8px; border: 1px solid #ddd; text-align: center;"><?php echo $qty; ?></td>
                                    <td style="padding: 8px; border: 1px solid #ddd; text-align: right;">$<?php echo number_format($cost, 2); ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                            <tfoot>
                                <tr style="background-color: #f9f9f9; border-top: 2px solid #333; font-weight: bold;">
                                    <td style="padding: 8px; border: 1px solid #ddd;">Total Spare Cost</td>
                                    <td style="padding: 8px; border: 1px solid #ddd;"></td>
                                    <td style="padding: 8px; border: 1px solid #ddd; text-align: right;">$<?php echo number_format($total_spare_cost, 2); ?></td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="spares-list">
                        <h3>Spare Parts & Materials Used:</h3>
                        <p><em>No spare parts or materials were recorded for this equipment during this period.</em></p>
                    </div>
                <?php endif; ?>

                <div class="work-orders">
                    <h3>Related Work Orders (<?php echo count($data['work_orders']); ?> total):</h3>
                    <p><em>Detailed list of all maintenance work performed on this equipment, including dates, types of work, and associated costs.</em></p>
                    <table>
                        <thead>
                            <tr>
                                <th>WO ID</th>
                                <th>Submit Date</th>
                                <th>Complete Date</th>
                                <th>Work Type</th>
                                <th>Description</th>
                                <th>Material Cost</th>
                                <th>Labor Cost</th>
                                <th>Total Cost</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            $wo_ids_displayed = [];
                            foreach ($data['work_orders'] as $wo): 
                                // Skip duplicate WO IDs in display
                                if (isset($wo_ids_displayed[$wo['wo_id']])) {
                                    continue;
                                }
                                $wo_ids_displayed[$wo['wo_id']] = true;
                                
                                // Calculate actual material cost from spares and consumables
                                $material_cost = 0;
                                $spare_query = "SELECT COALESCE(SUM(wos.quantity_used * COALESCE(pm.unit_cost, 0)), 0) as cost
                                              FROM work_order_spares wos
                                              JOIN equipment_spares es ON wos.spare_id = es.id
                                              LEFT JOIN parts_master pm ON es.part_id = pm.id
                                              WHERE wos.wo_id = " . intval($wo['wo_id']);
                                $spare_res = $connection->query($spare_query);
                                if ($spare_res) {
                                    $spare_row = $spare_res->fetch_assoc();
                                    $material_cost += floatval($spare_row['cost'] ?? 0);
                                }
                                
                                $consumable_query = "SELECT COALESCE(SUM(woc.quantity_required * woc.unit_cost), 0) as cost
                                                   FROM work_order_consumables woc
                                                   WHERE woc.work_order_id = " . intval($wo['wo_id']);
                                $cons_res = $connection->query($consumable_query);
                                if ($cons_res) {
                                    $cons_row = $cons_res->fetch_assoc();
                                    $material_cost += floatval($cons_row['cost'] ?? 0);
                                }
                            ?>
                                <tr>
                                    <td><?php echo $wo['wo_id']; ?></td>
                                    <td><?php echo $wo['submit_date']; ?></td>
                                    <td><?php echo $wo['complete_date'] ?: 'Pending'; ?></td>
                                    <td><?php echo htmlspecialchars($wo['maintenance_type'] ?: 'N/A'); ?></td>
                                    <td><?php echo htmlspecialchars(substr($wo['descriptive_text'] ?: $wo['description'], 0, 50)); ?><?php echo strlen($wo['descriptive_text'] ?: $wo['description']) > 50 ? '...' : ''; ?></td>
                                    <td>$<?php echo number_format($material_cost, 2); ?></td>
                                    <td>$<?php echo number_format(floatval($wo['labor_cost'] ?? 0), 2); ?></td>
                                    <td>$<?php echo number_format($material_cost + floatval($wo['labor_cost'] ?? 0), 2); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
</body>
</html>