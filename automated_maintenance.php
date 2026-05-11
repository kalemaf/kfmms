<?php
/**
 * Professional Preventive Maintenance (PM) System
 * Modern PM master records, task plans, parts, and scheduled work order generation.
 */

require_once 'config.inc.php';
require_once 'common.inc.php';

if (!function_exists('column_exists_pm')) {
    function column_exists_pm($table, $column)
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

$pm_message = '';
$pm_alert_type = 'success';

function safePost($key, $default = '') {
    return isset($_POST[$key]) ? sanitize_input(trim($_POST[$key])) : $default;
}

function calculateNextDueDate($startDate, $unit, $value) {
    if (!$startDate || !$unit || $value <= 0) {
        return null;
    }
    $dt = DateTime::createFromFormat('Y-m-d', $startDate);
    if (!$dt) {
        return null;
    }
    switch ($unit) {
        case 'Daily':
            $dt->modify("+{$value} days");
            break;
        case 'Weekly':
            $dt->modify("+{$value} weeks");
            break;
        case 'Monthly':
            $dt->modify("+{$value} months");
            break;
        case 'Quarterly':
            $dt->modify("+" . ($value * 3) . " months");
            break;
        case 'Yearly':
            $dt->modify("+{$value} years");
            break;
        default:
            $dt->modify("+{$value} days");
            break;
    }
    return $dt->format('Y-m-d');
}

$has_pm_tables = false;
$inventory_parts = [];
$spare_parts = [];
$consumables = [];
if ($connection) {
    $has_pm_tables = table_exists('pm_masters');

    if ($has_pm_tables) {
        if (!column_exists_pm('pm_required_parts', 'inventory_part_id')) {
            $connection->query("ALTER TABLE pm_required_parts ADD COLUMN inventory_part_id INT NULL AFTER part_name");
        }
        if (!column_exists_pm('pm_required_parts', 'equipment_spare_id')) {
            $connection->query("ALTER TABLE pm_required_parts ADD COLUMN equipment_spare_id INT NULL AFTER inventory_part_id");
        }

        // Load consumables for dropdown (filtered by tenant_id)
        try {
            $tenant_id = (int)($_SESSION['tenant_id'] ?? 1);
            $consumablesRes = $connection->query("SELECT id, name, category, subcategory, unit, current_stock, cost_per_unit FROM consumables WHERE is_active = 1 AND tenant_id = $tenant_id ORDER BY category, subcategory, name");
            if ($consumablesRes) {
                while ($row = $consumablesRes->fetch(PDO::FETCH_ASSOC)) {
                    $consumables[] = $row;
                }
            }
        } catch (Exception $e) {
            // consumables table may not exist, skip
            $consumables = [];
        }

        $tenant_id = (int)($_SESSION['tenant_id'] ?? 1);
        $partsRes = $connection->query("SELECT id, part_name, unit_cost FROM parts_master WHERE is_active = 1 AND tenant_id = $tenant_id ORDER BY part_name");
        if ($partsRes) {
            while ($row = $partsRes->fetch(PDO::FETCH_ASSOC)) {
                $inventory_parts[] = $row;
            }
        }

        try {
            $tenant_id = (int)($_SESSION['tenant_id'] ?? 1);
            $spareRes = $connection->query("SELECT id, part_name, part_number, quantity FROM equipment_spares WHERE tenant_id = {$tenant_id} ORDER BY part_name");
            if ($spareRes) {
                while ($row = $spareRes->fetch(PDO::FETCH_ASSOC)) {
                    $spare_parts[] = $row;
                }
            }
        } catch (Exception $e) {
            // equipment_spares table may not exist, skip it
            $spare_parts = [];
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $has_pm_tables) {
    if ($_POST['action'] === 'create_pm_master') {
        $equipment_id = intval($_POST['equipment_id'] ?? 0);
        $asset_id = '';
        $asset_name = '';
        if ($equipment_id) {
            $asset_id = (string)$equipment_id;
            $equipResult = $connection->query("SELECT description FROM equipment WHERE id = $equipment_id LIMIT 1");
            if ($equipResult && $equipRow = $equipResult->fetch(PDO::FETCH_ASSOC)) {
                $asset_name = sanitize_input($equipRow['description']);
            }
        } else {
            $asset_id = sanitize_input(trim($_POST['asset_id'] ?? ''));
            $asset_name = sanitize_input(trim($_POST['asset_name'] ?? ''));
        }

        $pm_title = safePost('pm_title');
        $description = safePost('description');
        $maintenance_type = safePost('maintenance_type', 'Preventive');
        $status = safePost('status', 'Active');
        $frequency_type = safePost('frequency_type', 'Time-Based');
        $time_frequency_unit = safePost('time_frequency_unit', 'Monthly');
        $time_frequency_value = max(1, intval($_POST['time_frequency_value'] ?? 30));
        $grace_period_days = max(0, intval($_POST['grace_period_days'] ?? 3));
        $meter_type = safePost('meter_type');
        $meter_trigger = isset($_POST['meter_trigger_threshold']) && $_POST['meter_trigger_threshold'] !== '' ? floatval($_POST['meter_trigger_threshold']) : null;
        $start_date = !empty($_POST['start_date']) ? sanitize_input($_POST['start_date']) : null;
        $next_due_date = !empty($_POST['next_due_date']) ? sanitize_input($_POST['next_due_date']) : null;
        if (empty($next_due_date) && $start_date && in_array($frequency_type, ['Time-Based', 'Hybrid'])) {
            $next_due_date = calculateNextDueDate($start_date, $time_frequency_unit, $time_frequency_value);
        }
        $planned_labor_hours = max(0.0, floatval($_POST['planned_labor_hours'] ?? 0));
        $required_technician_skill = safePost('required_technician_skill');
        $estimated_cost = max(0.0, floatval($_POST['estimated_cost'] ?? 0));
        
        // Extract time from datetime inputs or use defaults
        $start_time = '08:00:00';
        $next_due_time = '08:00:00';
        if (!empty($_POST['start_time'])) {
            $start_time = sanitize_input($_POST['start_time']);
        }
        if (!empty($_POST['next_due_time'])) {
            $next_due_time = sanitize_input($_POST['next_due_time']);
        }
        
        $created_by = sanitize_input($_SESSION['user'] ?? 'System');

        if (!$pm_title) {
            $pm_message = 'PM Title is required.';
            $pm_alert_type = 'danger';
        } else {
            $sql = "INSERT INTO pm_masters (asset_id, asset_name, pm_title, description, maintenance_type, status, frequency_type, time_frequency_unit, time_frequency_value, grace_period_days, meter_type, meter_trigger_threshold, start_date, start_time, next_due_date, next_due_time, planned_labor_hours, required_technician_skill, estimated_cost, created_by, created_date, modified_date) VALUES ('" . $asset_id . "', '" . $asset_name . "', '" . $pm_title . "', '" . $description . "', '" . $maintenance_type . "', '" . $status . "', '" . $frequency_type . "', '" . $time_frequency_unit . "', $time_frequency_value, $grace_period_days, " . ($meter_type ? "'" . $meter_type . "'" : 'NULL') . ", " . ($meter_trigger !== null ? $meter_trigger : 'NULL') . ", " . ($start_date ? "'" . $start_date . "'" : 'NULL') . ", '" . $start_time . "', " . ($next_due_date ? "'" . $next_due_date . "'" : 'NULL') . ", '" . $next_due_time . "', $planned_labor_hours, '" . $required_technician_skill . "', $estimated_cost, '" . $created_by . "', " . get_current_timestamp_sql() . ", " . get_current_timestamp_sql() . ")";

            if ($connection->query($sql)) {
                $pm_id = $connection->lastInsertId();

                if (!empty($_POST['task_description']) && is_array($_POST['task_description'])) {
                    foreach ($_POST['task_description'] as $idx => $taskDesc) {
                        $taskDesc = trim($taskDesc);
                        if ($taskDesc === '') {
                            continue;
                        }
                        $seq = intval($_POST['task_sequence'][$idx] ?? ($idx + 1));
                        $est_hours = max(0.0, floatval($_POST['estimated_labor_hours'][$idx] ?? 0));
                        $skill = sanitize_input(trim($_POST['required_skill'][$idx] ?? ''));
                        $tools = sanitize_input(trim($_POST['required_tools'][$idx] ?? ''));
                        $safety = sanitize_input(trim($_POST['safety_instructions'][$idx] ?? ''));
                        $insp_type = sanitize_input(trim($_POST['inspection_type'][$idx] ?? 'None'));
                        $insp_min = $_POST['inspection_min'][$idx] !== '' ? floatval($_POST['inspection_min'][$idx]) : null;
                        $insp_max = $_POST['inspection_max'][$idx] !== '' ? floatval($_POST['inspection_max'][$idx]) : null;
                        $insp_unit = sanitize_input(trim($_POST['inspection_unit'][$idx] ?? ''));

                        $taskSql = "INSERT INTO pm_tasks (pm_id, task_sequence, task_description, estimated_labor_hours, required_skill, required_tools, safety_instructions, inspection_type, inspection_min_value, inspection_max_value, inspection_unit) VALUES ($pm_id, $seq, '" . sanitize_input($taskDesc) . "', $est_hours, '" . $skill . "', '" . $tools . "', '" . $safety . "', '" . $insp_type . "', " . ($insp_min !== null ? $insp_min : 'NULL') . ", " . ($insp_max !== null ? $insp_max : 'NULL') . ", '" . $insp_unit . "')";
                        $connection->query($taskSql);
                    }
                }

                if (!empty($_POST['part_name']) && is_array($_POST['part_name'])) {
                    foreach ($_POST['part_name'] as $idx => $partName) {
                        $partName = trim($partName);
                        if ($partName === '') {
                            continue;
                        }
                        $qty = max(1, intval($_POST['quantity'][$idx] ?? 1));
                        $unit_cost = max(0.0, floatval($_POST['unit_cost'][$idx] ?? 0));
                        $inventory_part_id = isset($_POST['inventory_part_id'][$idx]) && $_POST['inventory_part_id'][$idx] !== '' ? intval($_POST['inventory_part_id'][$idx]) : null;
                        $equipment_spare_id = isset($_POST['equipment_spare_id'][$idx]) && $_POST['equipment_spare_id'][$idx] !== '' ? intval($_POST['equipment_spare_id'][$idx]) : null;
                        $inventorySql = $inventory_part_id ? $inventory_part_id : 'NULL';
                        $spareSql = $equipment_spare_id ? $equipment_spare_id : 'NULL';
                        $total = $qty * $unit_cost;
                        $sqlPart = "INSERT INTO pm_required_parts (pm_id, part_name, inventory_part_id, equipment_spare_id, quantity, unit_cost, total_cost) VALUES ($pm_id, '" . sanitize_input($partName) . "', $inventorySql, $spareSql, $qty, $unit_cost, $total)";
                        $connection->query($sqlPart);
                    }
                }

                // Process consumables for this PM
                if (!empty($_POST['consumable_id']) && is_array($_POST['consumable_id'])) {
                    foreach ($_POST['consumable_id'] as $idx => $consumable_id) {
                        $consumable_id = intval($consumable_id);
                        if ($consumable_id <= 0) {
                            continue;
                        }
                        $qty_required = max(0.01, floatval($_POST['consumable_quantity'][$idx] ?? 1));
                        $cost_per_unit = max(0.0, floatval($_POST['consumable_unit_cost'][$idx] ?? 0));
                        $notes = sanitize_input(trim($_POST['consumable_notes'][$idx] ?? ''));
                        
                        $sqlConsumable = "INSERT INTO pm_consumables (pm_id, consumable_id, quantity_required, unit_cost, notes, created_at) VALUES ($pm_id, $consumable_id, $qty_required, $cost_per_unit, '" . $notes . "', " . get_current_timestamp_sql() . ")";
                        $connection->query($sqlConsumable);
                    }
                }

                $pm_message = 'Professional PM record created successfully.';
                $pm_alert_type = 'success';
            } else {
                $pm_message = 'Failed to create PM record: ' . implode(' ', $connection->errorInfo());
                $pm_alert_type = 'danger';
            }
        }
    } elseif ($_POST['action'] === 'run_pm_generator') {
        ob_start();
        include __DIR__ . '/generate_pm.php';
        $pm_message = trim(ob_get_clean());
        $pm_alert_type = strpos($pm_message, 'Failed') === false ? 'success' : 'danger';
    }
}

$pm_masters = [];
$task_counts = [];
$part_counts = [];
$equipment_options = [];
if ($connection && $has_pm_tables) {
        $pm_masters = query_to_array("SELECT * FROM pm_masters ORDER BY COALESCE(next_due_date, '9999-12-31') ASC, pm_title ASC");

        $taskRows = query_to_array("SELECT pm_id, COUNT(*) AS cnt FROM pm_tasks GROUP BY pm_id");
        foreach ($taskRows as $row) {
            $task_counts[$row['pm_id']] = intval($row['cnt']);
        }

        $partRows = query_to_array("SELECT pm_id, COUNT(*) AS cnt FROM pm_required_parts GROUP BY pm_id");
        foreach ($partRows as $row) {
            $part_counts[$row['pm_id']] = intval($row['cnt']);
        }

        $latest_schedule_status = [];
        if (table_exists('pm_schedule_log')) {
            $latestLogRows = query_to_array("SELECT psl.pm_id, psl.status FROM pm_schedule_log psl JOIN (SELECT pm_id, MAX(pm_log_id) AS latest_log_id FROM pm_schedule_log GROUP BY pm_id) latest ON latest.pm_id = psl.pm_id AND latest.latest_log_id = psl.pm_log_id");
            foreach ($latestLogRows as $row) {
                $latest_schedule_status[$row['pm_id']] = $row['status'];
            }
        }

        try {
            $equipment_options = query_to_array("SELECT id, description FROM equipment ORDER BY description");
        } catch (Exception $e) {
            // equipment table may not exist, provide manual entry option
            $equipment_options = [];
        }
    }
$partSelectionOptions = '<option value="">Manual / Custom</option>';
if (!empty($inventory_parts)) {
    $partSelectionOptions .= '<optgroup label="Inventory Parts">';
    foreach ($inventory_parts as $part) {
        $partSelectionOptions .= '<option value="inventory:' . intval($part['id']) . '" data-unit-cost="' . number_format(floatval($part['unit_cost']), 2, '.', '') . '">' . htmlspecialchars($part['part_name']) . '</option>';
    }
    $partSelectionOptions .= '</optgroup>';
}
if (!empty($spare_parts)) {
    $partSelectionOptions .= '<optgroup label="Equipment Spares">';
    foreach ($spare_parts as $spare) {
        $label = htmlspecialchars($spare['part_name']);
        if (!empty($spare['equipment_desc'])) {
            $label .= ' (' . htmlspecialchars($spare['equipment_desc']) . ')';
        }
        $partSelectionOptions .= '<option value="spare:' . intval($spare['id']) . '" data-unit-cost="0.00">' . $label . '</option>';
    }
    $partSelectionOptions .= '</optgroup>';
}
?>

<div class="container-fluid">
    <div class="row mb-3">
        <div class="col-12">
            <h2><i class="fas fa-tools me-2"></i>Professional Preventive Maintenance</h2>
            <p class="text-muted">Define PM masters, schedule rules, task plans, parts, and generate work orders automatically.</p>
        </div>
    </div>

    <?php if (!empty($pm_message)): ?>
    <div class="row">
        <div class="col-12">
            <div class="alert alert-<?php echo $pm_alert_type; ?>" role="alert">
                <?php echo htmlspecialchars($pm_message); ?>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <?php if (!$has_pm_tables): ?>
    <div class="row">
        <div class="col-12">
            <div class="alert alert-warning">
                The professional PM schema is not installed. Please run <code>php migrations/add_pm_professional_structure.php</code> first.
            </div>
        </div>
    </div>
    <?php else: ?>

    <div class="row">
        <div class="col-lg-8">
            <div class="card mb-4">
                <div class="card-header">
                    <h5><i class="fas fa-calendar-alt me-2"></i>PM Master Records</h5>
                </div>
                <div class="card-body">
                    <?php if (empty($pm_masters)): ?>
                        <p class="text-muted">No professional PM masters exist yet. Use the form to create one.</p>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover table-sm align-middle">
                                <thead class="table-light">
                                    <tr>
                                        <th>PM Name</th>
                                        <th>Asset</th>
                                        <th>Type</th>
                                        <th>Frequency</th>
                                        <th>Next Due</th>
                                        <th>Tasks</th>
                                        <th>Parts</th>
                                        <th>Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($pm_masters as $pm): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($pm['pm_title']); ?></td>
                                        <td><?php echo htmlspecialchars($pm['asset_name'] ?: $pm['asset_id'] ?: 'Unassigned'); ?></td>
                                        <td><?php echo htmlspecialchars($pm['maintenance_type']); ?></td>
                                        <td><?php echo htmlspecialchars($pm['frequency_type']); ?> / <?php echo intval($pm['time_frequency_value']) . ' ' . htmlspecialchars($pm['time_frequency_unit']); ?></td>
                                        <td><?php echo $pm['next_due_date'] ? date('M d, Y', strtotime($pm['next_due_date'])) : '<span class="text-muted">Not scheduled</span>'; ?></td>
                                        <td><?php echo intval($task_counts[$pm['pm_id']] ?? 0); ?></td>
                                        <td><?php echo intval($part_counts[$pm['pm_id']] ?? 0); ?></td>
                                        <td>
                                            <?php
                                                $displayStatus = $pm['status'];
                                                $badgeClass = $pm['status'] === 'Active' ? 'bg-success' : 'bg-secondary';
                                                if ($pm['status'] === 'Active' && !empty($latest_schedule_status[$pm['pm_id']])) {
                                                    $displayStatus = $latest_schedule_status[$pm['pm_id']];
                                                    switch ($displayStatus) {
                                                        case 'Completed':
                                                            $badgeClass = 'bg-primary';
                                                            break;
                                                        case 'Pending':
                                                            $badgeClass = 'bg-warning text-dark';
                                                            break;
                                                        case 'In Progress':
                                                            $badgeClass = 'bg-info text-dark';
                                                            break;
                                                        default:
                                                            $badgeClass = 'bg-success';
                                                            break;
                                                    }
                                                }
                                            ?>
                                            <span class="badge <?php echo $badgeClass; ?>"><?php echo htmlspecialchars($displayStatus); ?></span>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="card mb-4">
                <div class="card-header">
                    <h5><i class="fas fa-info-circle me-2"></i>PM Operations</h5>
                </div>
                <div class="card-body">
                    <p class="mb-2"><strong>Professional PM supports:</strong></p>
                    <ul class="ps-3">
                        <li>Asset-linked PM masters</li>
                        <li>Time / meter / hybrid scheduling</li>
                        <li>Structured task plans</li>
                        <li>Spare parts planning</li>
                        <li>Work order generation</li>
                    </ul>
                    <form method="post" class="mt-3">
                        <input type="hidden" name="action" value="run_pm_generator">
                        <button type="submit" class="btn btn-success w-100">
                            <i class="fas fa-play me-2"></i>Generate Due PM Work Orders
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h5><i class="fas fa-plus me-2"></i>Create Professional PM</h5>
                </div>
                <div class="card-body">
                    <form method="post" id="pmMasterForm">
                        <input type="hidden" name="action" value="create_pm_master">

                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label">PM Name</label>
                                <input type="text" class="form-control" name="pm_title" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Maintenance Type</label>
                                <select class="form-select" name="maintenance_type">
                                    <option value="Preventive">Preventive</option>
                                    <option value="Inspection">Inspection</option>
                                    <option value="Lubrication">Lubrication</option>
                                    <option value="Predictive">Predictive</option>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Equipment / Asset</label>
                                <select class="form-select" name="equipment_id" id="equipmentIdSelect">
                                    <option value="">Select Equipment</option>
                                    <?php foreach ($equipment_options as $equip): ?>
                                    <option value="<?php echo $equip['id']; ?>"><?php echo htmlspecialchars($equip['description']); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Alternative Asset Name</label>
                                <input type="text" class="form-control" name="asset_name" placeholder="Use if not linked to equipment">
                            </div>
                            <div class="col-12">
                                <label class="form-label">Description</label>
                                <textarea class="form-control" name="description" rows="2"></textarea>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Status</label>
                                <select class="form-select" name="status">
                                    <option value="Active">Active</option>
                                    <option value="Inactive">Inactive</option>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Schedule Mode</label>
                                <select class="form-select" name="frequency_type" id="frequencyTypeSelect">
                                    <option value="Time-Based">Time-Based</option>
                                    <option value="Meter-Based">Meter-Based</option>
                                    <option value="Hybrid">Hybrid</option>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Grace Period (days)</label>
                                <input type="number" class="form-control" name="grace_period_days" value="3" min="0">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Time Unit</label>
                                <select class="form-select" name="time_frequency_unit">
                                    <option value="Daily">Daily</option>
                                    <option value="Weekly">Weekly</option>
                                    <option value="Monthly" selected>Monthly</option>
                                    <option value="Quarterly">Quarterly</option>
                                    <option value="Yearly">Yearly</option>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Time Value</label>
                                <input type="number" class="form-control" name="time_frequency_value" value="1" min="1">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Meter Type</label>
                                <input type="text" class="form-control" name="meter_type" placeholder="e.g. Running Hours">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Meter Threshold</label>
                                <input type="number" class="form-control" step="0.01" name="meter_trigger_threshold" placeholder="e.g. 500">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Start Date</label>
                                <input type="date" class="form-control" name="start_date" value="<?php echo date('Y-m-d'); ?>">
                            </div>
                            <div class="col-md-2">
                                <label class="form-label">Time</label>
                                <input type="time" class="form-control" name="start_time" value="08:00">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Next Due Date</label>
                                <input type="date" class="form-control" id="nextDueDate" name="next_due_date" readonly value="<?php echo date('Y-m-d', strtotime('+30 days')); ?>">
                            </div>
                            <div class="col-md-2">
                                <label class="form-label">Time</label>
                                <input type="time" class="form-control" name="next_due_time" value="08:00" readonly>
                            </div>
                            <div class="col-md-2">
                                <label class="form-label">Labor Hrs</label>
                                <input type="number" class="form-control" step="0.25" name="planned_labor_hours" value="1">
                            </div>
                            <div class="col-md-2">
                                <label class="form-label">Est. Cost</label>
                                <input type="number" class="form-control" step="0.01" name="estimated_cost" value="0.00">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Required Technician Skill</label>
                                <input type="text" class="form-control" name="required_technician_skill" placeholder="e.g. Electrician">
                            </div>
                        </div>

                        <hr>
                        <h6>Task Plan</h6>
                        <div id="tasksContainer">
                            <div class="row g-3 task-row mb-3">
                                <div class="col-md-1">
                                    <label class="form-label">Seq</label>
                                    <input type="number" class="form-control" name="task_sequence[]" value="1" min="1">
                                </div>
                                <div class="col-md-5">
                                    <label class="form-label">Task Description</label>
                                    <input type="text" class="form-control" name="task_description[]" placeholder="Task step">
                                </div>
                                <div class="col-md-2">
                                    <label class="form-label">Est Hrs</label>
                                    <input type="number" class="form-control" name="estimated_labor_hours[]" step="0.25" value="0">
                                </div>
                                <div class="col-md-2">
                                    <label class="form-label">Skill</label>
                                    <input type="text" class="form-control" name="required_skill[]" placeholder="Skill">
                                </div>
                                <div class="col-md-2">
                                    <label class="form-label">Tools</label>
                                    <input type="text" class="form-control" name="required_tools[]" placeholder="Tools">
                                </div>
                                <div class="col-md-12">
                                    <label class="form-label">Safety / Inspection</label>
                                    <input type="text" class="form-control mb-2" name="safety_instructions[]" placeholder="Safety or LOTO instructions">
                                    <div class="row g-2">
                                        <div class="col-md-3">
                                            <select class="form-select" name="inspection_type[]">
                                                <option value="None">None</option>
                                                <option value="Pass/Fail">Pass/Fail</option>
                                                <option value="Measurement">Measurement</option>
                                                <option value="Visual">Visual</option>
                                                <option value="Reading">Reading</option>
                                            </select>
                                        </div>
                                        <div class="col-md-3">
                                            <input type="number" class="form-control" step="0.01" name="inspection_min[]" placeholder="Min">
                                        </div>
                                        <div class="col-md-3">
                                            <input type="number" class="form-control" step="0.01" name="inspection_max[]" placeholder="Max">
                                        </div>
                                        <div class="col-md-3">
                                            <input type="text" class="form-control" name="inspection_unit[]" placeholder="Unit">
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <button type="button" class="btn btn-outline-secondary btn-sm mb-3" onclick="addTaskRow()">
                            <i class="fas fa-plus me-1"></i>Add Task Step
                        </button>

                        <hr>
                        <h6>Required Parts</h6>
                        <div id="partsContainer">
                            <div class="row g-3 part-row mb-3">
                                <div class="col-md-5">
                                    <label class="form-label">Select Part</label>
                                    <select class="form-select part-selection" name="part_selection[]">
                                        <?php echo $partSelectionOptions; ?>
                                    </select>
                                    <input type="text" class="form-control mt-2 part-name-input" name="part_name[]" placeholder="Part or material">
                                    <input type="hidden" name="inventory_part_id[]" class="inventory-part-id" value="">
                                    <input type="hidden" name="equipment_spare_id[]" class="equipment-spare-id" value="">
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label">Quantity</label>
                                    <input type="number" class="form-control" name="quantity[]" value="1" min="1">
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">Unit Cost</label>
                                    <input type="number" class="form-control part-unit-cost" step="0.01" name="unit_cost[]" value="0.00">
                                </div>
                            </div>
                        </div>
                        <button type="button" class="btn btn-outline-secondary btn-sm mb-3" onclick="addPartRow()">
                            <i class="fas fa-plus me-1"></i>Add Required Part
                        </button>

                        <hr>
                        <h6>Required Consumables</h6>
                        <div id="consumablesContainer">
                            <div class="row g-3 consumable-row mb-3">
                                <div class="col-md-5">
                                    <label class="form-label">Select Consumable</label>
                                    <select class="form-select consumable-selection" name="consumable_id[]">
                                        <option value="">-- Select a Consumable --</option>
                                        <?php foreach ($consumables as $c): ?>
                                            <option value="<?php echo intval($c['id']); ?>" data-cost="<?php echo floatval($c['cost_per_unit']); ?>">
                                                <?php echo htmlspecialchars($c['category'] . ' > ' . $c['subcategory'] . ' > ' . $c['name']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-md-2">
                                    <label class="form-label">Quantity</label>
                                    <input type="number" class="form-control consumable-qty" name="consumable_quantity[]" value="1" min="0.01" step="0.01">
                                </div>
                                <div class="col-md-2">
                                    <label class="form-label">Unit Cost</label>
                                    <input type="number" class="form-control consumable-cost" name="consumable_unit_cost[]" value="0.00" min="0" step="0.01" readonly>
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label">Notes (Optional)</label>
                                    <input type="text" class="form-control" name="consumable_notes[]" placeholder="e.g., Brand, special instructions">
                                </div>
                            </div>
                        </div>
                        <button type="button" class="btn btn-outline-secondary btn-sm mb-3" onclick="addConsumableRow()">
                            <i class="fas fa-plus me-1"></i>Add Consumable
                        </button>

                        <div class="text-end">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-save me-2"></i>Create Professional PM
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <?php endif; ?>
</div>

<script>
function addTaskRow() {
    const container = document.getElementById('tasksContainer');
    const index = container.querySelectorAll('.task-row').length + 1;
    const row = document.createElement('div');
    row.className = 'row g-3 task-row mb-3';
    row.innerHTML = `
        <div class="col-md-1">
            <label class="form-label">Seq</label>
            <input type="number" class="form-control" name="task_sequence[]" value="${index}" min="1">
        </div>
        <div class="col-md-5">
            <label class="form-label">Task Description</label>
            <input type="text" class="form-control" name="task_description[]" placeholder="Task step">
        </div>
        <div class="col-md-2">
            <label class="form-label">Est Hrs</label>
            <input type="number" class="form-control" name="estimated_labor_hours[]" step="0.25" value="0">
        </div>
        <div class="col-md-2">
            <label class="form-label">Skill</label>
            <input type="text" class="form-control" name="required_skill[]" placeholder="Skill">
        </div>
        <div class="col-md-2">
            <label class="form-label">Tools</label>
            <input type="text" class="form-control" name="required_tools[]" placeholder="Tools">
        </div>
        <div class="col-md-12">
            <label class="form-label">Safety / Inspection</label>
            <input type="text" class="form-control mb-2" name="safety_instructions[]" placeholder="Safety or LOTO instructions">
            <div class="row g-2">
                <div class="col-md-3">
                    <select class="form-select" name="inspection_type[]">
                        <option value="None">None</option>
                        <option value="Pass/Fail">Pass/Fail</option>
                        <option value="Measurement">Measurement</option>
                        <option value="Visual">Visual</option>
                        <option value="Reading">Reading</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <input type="number" class="form-control" step="0.01" name="inspection_min[]" placeholder="Min">
                </div>
                <div class="col-md-3">
                    <input type="number" class="form-control" step="0.01" name="inspection_max[]" placeholder="Max">
                </div>
                <div class="col-md-3">
                    <input type="text" class="form-control" name="inspection_unit[]" placeholder="Unit">
                </div>
            </div>
        </div>
    `;
    container.appendChild(row);
}

const partSelectionOptionsHtml = <?php echo json_encode($partSelectionOptions); ?>;
const inventoryParts = <?php echo json_encode($inventory_parts); ?>;
const spareParts = <?php echo json_encode($spare_parts); ?>;
const consumablesData = <?php echo json_encode($consumables); ?>;

function bindPartRowEvents(row) {
    const select = row.querySelector('.part-selection');
    if (!select) {
        return;
    }
    select.addEventListener('change', function() {
        updatePartRowFromSelection(select);
    });
}

function updatePartRowFromSelection(selectEl) {
    const row = selectEl.closest('.part-row');
    if (!row) {
        return;
    }
    const nameInput = row.querySelector('.part-name-input');
    const unitCostInput = row.querySelector('.part-unit-cost');
    const inventoryIdInput = row.querySelector('.inventory-part-id');
    const spareIdInput = row.querySelector('.equipment-spare-id');
    const selectedValue = selectEl.value;

    if (!selectedValue) {
        if (inventoryIdInput) {
            inventoryIdInput.value = '';
        }
        if (spareIdInput) {
            spareIdInput.value = '';
        }
        if (nameInput) {
            nameInput.readOnly = false;
            nameInput.value = '';
        }
        if (unitCostInput) {
            unitCostInput.value = '0.00';
        }
        return;
    }

    const [source, id] = selectedValue.split(':');
    if (source === 'inventory') {
        const part = inventoryParts.find(p => String(p.id) === id);
        if (part) {
            if (nameInput) {
                nameInput.value = part.part_name;
                nameInput.readOnly = true;
            }
            if (unitCostInput) {
                unitCostInput.value = Number(part.unit_cost || 0).toFixed(2);
            }
            if (inventoryIdInput) {
                inventoryIdInput.value = id;
            }
            if (spareIdInput) {
                spareIdInput.value = '';
            }
        }
    } else if (source === 'spare') {
        const part = spareParts.find(p => String(p.id) === id);
        if (part) {
            if (nameInput) {
                nameInput.value = part.part_name;
                nameInput.readOnly = true;
            }
            if (unitCostInput) {
                unitCostInput.value = '0.00';
            }
            if (inventoryIdInput) {
                inventoryIdInput.value = '';
            }
            if (spareIdInput) {
                spareIdInput.value = id;
            }
        }
    }
}

function addPartRow() {
    const container = document.getElementById('partsContainer');
    const row = document.createElement('div');
    row.className = 'row g-3 part-row mb-3';
    row.innerHTML = `
        <div class="col-md-5">
            <label class="form-label">Select Part</label>
            <select class="form-select part-selection" name="part_selection[]">
                ${partSelectionOptionsHtml}
            </select>
            <input type="text" class="form-control mt-2 part-name-input" name="part_name[]" placeholder="Part or material">
            <input type="hidden" name="inventory_part_id[]" class="inventory-part-id" value="">
            <input type="hidden" name="equipment_spare_id[]" class="equipment-spare-id" value="">
        </div>
        <div class="col-md-3">
            <label class="form-label">Quantity</label>
            <input type="number" class="form-control" name="quantity[]" value="1" min="1">
        </div>
        <div class="col-md-4">
            <label class="form-label">Unit Cost</label>
            <input type="number" class="form-control part-unit-cost" step="0.01" name="unit_cost[]" value="0.00">
        </div>
    `;
    container.appendChild(row);
    bindPartRowEvents(row);
}

function updateNextDueDateField() {
    var start = document.querySelector('[name="start_date"]');
    var unit = document.querySelector('[name="time_frequency_unit"]');
    var value = document.querySelector('[name="time_frequency_value"]');
    var nextDue = document.getElementById('nextDueDate');
    var nextDueTime = document.querySelector('[name="next_due_time"]');
    if (!start || !unit || !value || !nextDue) {
        return;
    }
    var startDate = start.value;
    var interval = parseInt(value.value, 10);
    if (!startDate || isNaN(interval) || interval < 1) {
        return;
    }
    var date = new Date(startDate);
    switch (unit.value) {
        case 'Daily':
            date.setDate(date.getDate() + interval);
            break;
        case 'Weekly':
            date.setDate(date.getDate() + (7 * interval));
            break;
        case 'Monthly':
            date.setMonth(date.getMonth() + interval);
            break;
        case 'Quarterly':
            date.setMonth(date.getMonth() + (3 * interval));
            break;
        case 'Yearly':
            date.setFullYear(date.getFullYear() + interval);
            break;
        default:
            date.setDate(date.getDate() + interval);
            break;
    }
    var yyyy = date.getFullYear();
    var mm = String(date.getMonth() + 1).padStart(2, '0');
    var dd = String(date.getDate()).padStart(2, '0');
    nextDue.value = yyyy + '-' + mm + '-' + dd;
    
    // Copy start time to next due time if start time is set
    if (nextDueTime) {
        var startTime = document.querySelector('[name="start_time"]');
        if (startTime && startTime.value) {
            nextDueTime.value = startTime.value;
        }
    }
}

function addConsumableRow() {
    const container = document.getElementById('consumablesContainer');
    const row = document.createElement('div');
    row.className = 'row g-3 consumable-row mb-3';
    
    let consumableOptions = '<option value="">-- Select a Consumable --</option>';
    consumablesData.forEach(function(c) {
        consumableOptions += '<option value="' + c.id + '" data-cost="' + c.cost_per_unit + '">' +
            c.category + ' > ' + c.subcategory + ' > ' + c.name + '</option>';
    });
    
    row.innerHTML = `
        <div class="col-md-5">
            <label class="form-label">Select Consumable</label>
            <select class="form-select consumable-selection" name="consumable_id[]">
                ${consumableOptions}
            </select>
        </div>
        <div class="col-md-2">
            <label class="form-label">Quantity</label>
            <input type="number" class="form-control consumable-qty" name="consumable_quantity[]" value="1" min="0.01" step="0.01">
        </div>
        <div class="col-md-2">
            <label class="form-label">Unit Cost</label>
            <input type="number" class="form-control consumable-cost" name="consumable_unit_cost[]" value="0.00" min="0" step="0.01" readonly>
        </div>
        <div class="col-md-3">
            <label class="form-label">Notes (Optional)</label>
            <input type="text" class="form-control" name="consumable_notes[]" placeholder="e.g., Brand, special instructions">
        </div>
    `;
    container.appendChild(row);
    bindConsumableRowEvents(row);
}

function bindConsumableRowEvents(row) {
    const select = row.querySelector('.consumable-selection');
    if (!select) {
        return;
    }
    select.addEventListener('change', function() {
        const costInput = row.querySelector('.consumable-cost');
        const selectedOption = this.options[this.selectedIndex];
        if (selectedOption && selectedOption.value) {
            const cost = selectedOption.getAttribute('data-cost') || '0.00';
            costInput.value = parseFloat(cost).toFixed(2);
        } else {
            costInput.value = '0.00';
        }
    });
}

document.addEventListener('DOMContentLoaded', function() {
    updateNextDueDateField();
    var inputs = document.querySelectorAll('[name="start_date"], [name="time_frequency_unit"], [name="time_frequency_value"], [name="start_time"]');
    inputs.forEach(function(el) {
        el.addEventListener('change', updateNextDueDateField);
        el.addEventListener('input', updateNextDueDateField);
    });

    document.querySelectorAll('.part-row').forEach(function(row) {
        bindPartRowEvents(row);
    });
    
    document.querySelectorAll('.consumable-row').forEach(function(row) {
        bindConsumableRowEvents(row);
    });
});
</script>
