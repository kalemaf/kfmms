<?php
/**
 * generate_pm.php
 *
 * Run this script to generate work orders for due professional PM masters.
 * Usage: php generate_pm.php
 */

require_once 'config.inc.php';
require_once 'common.inc.php';
require_once 'libraries/inventory_manager.php';

if (!$connection) {
    echo "Database connection failed.\n";
    exit(1);
}

global $db_type;

function safe_fetch_assoc($result) {
    global $db_type;
    if (!$result) return null;
    if ($db_type === 'sqlite') {
        return $result->fetch(PDO::FETCH_ASSOC);
    } else {
        return $result->fetch_assoc();
    }
}

function get_last_insert_id($connection) {
    global $db_type;
    if ($db_type === 'sqlite') {
        return $connection->lastInsertId();
    } else {
        return $connection->insert_id;
    }
}

function reduceInventoryPartStock($part_id, $qty, $connection) {
    global $db_type;
    $part_id = intval($part_id);
    $qty = intval($qty);
    if ($part_id <= 0 || $qty <= 0) {
        return;
    }

    $remaining = $qty;
    $stockRes = $connection->query("SELECT id, quantity_on_hand FROM stock_locales WHERE part_id = $part_id AND quantity_on_hand > 0 ORDER BY quantity_on_hand DESC");
    if ($stockRes) {
        while ($remaining > 0 && ($stock = safe_fetch_assoc($stockRes))) {
            $available = intval($stock['quantity_on_hand']);
            $take = min($remaining, $available);
            $stock_id = intval($stock['id']);
            if ($db_type === 'sqlite') {
                $connection->query("UPDATE stock_locales SET quantity_on_hand = MAX(0, quantity_on_hand - $take), updated_at = CURRENT_TIMESTAMP WHERE id = $stock_id");
            } else {
                $connection->query("UPDATE stock_locales SET quantity_on_hand = GREATEST(0, quantity_on_hand - $take), updated_at = NOW() WHERE id = $stock_id");
            }
            $remaining -= $take;
        }
    }
    if ($db_type === 'sqlite') {
        $connection->query("UPDATE parts_master SET total_on_hand = COALESCE((SELECT SUM(quantity_on_hand) FROM stock_locales WHERE part_id = $part_id), 0) WHERE id = $part_id");
    } else {
        $connection->query("UPDATE parts_master SET total_on_hand = IFNULL((SELECT SUM(quantity_on_hand) FROM stock_locales WHERE part_id = $part_id), 0) WHERE id = $part_id");
    }
}

function consumeEquipmentSpare($spare_id, $qty, $wo_id, $connection) {
    global $db_type;
    $spare_id = intval($spare_id);
    $qty = intval($qty);
    $wo_id = intval($wo_id);
    if ($spare_id <= 0 || $qty <= 0 || $wo_id <= 0) {
        return;
    }
    if ($db_type === 'sqlite') {
        $connection->query("UPDATE equipment_spares SET quantity = MAX(0, quantity - $qty) WHERE id = $spare_id");
    } else {
        $connection->query("UPDATE equipment_spares SET quantity = GREATEST(0, quantity - $qty) WHERE id = $spare_id");
    }
    $connection->query("INSERT INTO work_order_spares (wo_id, spare_id, quantity_used) VALUES ($wo_id, $spare_id, $qty)");
}

$today = date('Y-m-d');
$hasPmMasters = table_exists('pm_masters');

if ($hasPmMasters) {
    $selectPmQuery = "SELECT * FROM pm_masters WHERE status='Active' AND next_due_date IS NOT NULL AND DATE(next_due_date) <= '$today'";
    $selectPm = safe_query_all($selectPmQuery);
    if (empty($selectPm)) {
        echo "No due PM masters found for today ($today).\n";
        exit(0);
    }

    $created = 0;
    foreach ($selectPm as $pm) {
        $assetLabel = $pm['asset_name'] ?: ($pm['asset_id'] ?: 'Unknown Asset');
        
        $pm_desc = $pm['description'] ?: 'Preventive maintenance task';
        $pm_desc_escaped = ($db_type === 'sqlite') ? str_replace("'", "''", $pm_desc) : $connection->real_escape_string($pm_desc);
        
        $pm_title = $pm['pm_title'] ?? '';
        $pm_title_escaped = ($db_type === 'sqlite') ? str_replace("'", "''", $pm_title) : $connection->real_escape_string($pm_title);
        
        $equipment_label = ($db_type === 'sqlite') ? str_replace("'", "''", $assetLabel) : $connection->real_escape_string($assetLabel);
        
        $plannedHours = max(0.0, floatval($pm['planned_labor_hours']));
        $tenantId = (int)($_SESSION['tenant_id'] ?? 1);

        $insertWo = "INSERT INTO work_orders (tenant_id, descriptive_text, audit_item, requestor, approval, equipment, description, action, mechanic_id, priority, submit_date, est_hours, act_hours, account, complete_date, coordinating_instructions, needed_date, wo_status, inspected_by, pm_id) VALUES ($tenantId, 'PM: $pm_title_escaped', 0, 'PM System', 'Approved', '$equipment_label', '$pm_desc_escaped', 'Auto-generated PM master #" . intval($pm['pm_id']) . "', 0, 1, '$today', $plannedHours, 0, '', NULL, 'Auto-generated PM', '$today', 'Approved', 'System', " . intval($pm['pm_id']) . ")";
        if (!$connection->query($insertWo)) {
            echo "Failed to create work order for PM master {$pm['pm_id']}\n";
            continue;
        }

        $woId = get_last_insert_id($connection);
        
        // Consume PM consumables and reduce stock
        if (function_exists('consume_pm_consumables')) {
            consume_pm_consumables(intval($pm['pm_id']), $woId, $connection);
        }
        
        // PM work order created. Spare and inventory consumption happens when the work order is completed.

        $nextDue = null;

        if (!empty($pm['frequency_type']) && in_array($pm['frequency_type'], ['Time-Based', 'Hybrid'])) {
            $interval = '+1 month';
            switch ($pm['time_frequency_unit']) {
                case 'Daily':
                    $interval = '+' . max(1, intval($pm['time_frequency_value'])) . ' days';
                    break;
                case 'Weekly':
                    $interval = '+' . max(1, intval($pm['time_frequency_value'])) . ' weeks';
                    break;
                case 'Monthly':
                    $interval = '+' . max(1, intval($pm['time_frequency_value'])) . ' months';
                    break;
                case 'Quarterly':
                    $interval = '+' . (3 * max(1, intval($pm['time_frequency_value']))) . ' months';
                    break;
                case 'Yearly':
                    $interval = '+' . max(1, intval($pm['time_frequency_value'])) . ' years';
                    break;
            }
            $nextDue = date('Y-m-d', strtotime($pm['next_due_date'] . ' ' . $interval));
        }

        if ($nextDue) {
            $updatePm = "UPDATE pm_masters SET last_completed_date='$today', completion_count = completion_count + 1, next_due_date='$nextDue', modified_date=" . ($db_type === 'sqlite' ? 'CURRENT_TIMESTAMP' : 'NOW()') . " WHERE pm_id=" . intval($pm['pm_id']);
        } else {
            $updatePm = "UPDATE pm_masters SET last_completed_date='$today', completion_count = completion_count + 1, modified_date=" . ($db_type === 'sqlite' ? 'CURRENT_TIMESTAMP' : 'NOW()') . " WHERE pm_id=" . intval($pm['pm_id']);
        }
        if (!$connection->query($updatePm)) {
            echo "Warning: failed to update PM master {$pm['pm_id']}\n";
        }

        $hasCreatedDate = false;
        if ($db_type === 'sqlite') {
            $createdDateCheck = $connection->query("PRAGMA table_info(pm_schedule_log)");
            if ($createdDateCheck) {
                while ($col = $createdDateCheck->fetch(PDO::FETCH_ASSOC)) {
                    if ($col['name'] === 'created_date') {
                        $hasCreatedDate = true;
                        break;
                    }
                }
            }
        } else {
            $createdDateCheck = $connection->query("SHOW COLUMNS FROM pm_schedule_log LIKE 'created_date'");
            if ($createdDateCheck && $createdDateCheck->num_rows > 0) {
                $hasCreatedDate = true;
            }
        }
        
        if (!$hasCreatedDate) {
            $alterResult = $connection->query("ALTER TABLE pm_schedule_log ADD COLUMN created_date DATETIME DEFAULT CURRENT_TIMESTAMP AFTER notes");
            if ($alterResult !== false) {
                $hasCreatedDate = true;
            }
        }

        $pm_next_due = ($db_type === 'sqlite') ? str_replace("'", "''", $pm['next_due_date']) : $connection->real_escape_string($pm['next_due_date']);
        
        if ($hasCreatedDate) {
            $logSql = "INSERT INTO pm_schedule_log (pm_id, wo_id, scheduled_date, due_date, completed_date, status, actual_labor_hours, actual_cost, notes, created_date) VALUES (" . intval($pm['pm_id']) . ", $woId, '$today', '$pm_next_due', '$today', 'Completed', $plannedHours, 0, 'Auto-generated PM work order', " . ($db_type === 'sqlite' ? 'CURRENT_TIMESTAMP' : 'NOW()') . ")";
        } else {
            $logSql = "INSERT INTO pm_schedule_log (pm_id, wo_id, scheduled_date, due_date, completed_date, status, actual_labor_hours, actual_cost, notes) VALUES (" . intval($pm['pm_id']) . ", $woId, '$today', '$pm_next_due', '$today', 'Completed', $plannedHours, 0, 'Auto-generated PM work order')";
        }
        if (!$connection->query($logSql)) {
            echo "Warning: failed to log schedule for PM master {$pm['pm_id']}\n";
        }

        echo "PM master {$pm['pm_id']} generated WO {$woId}." . ($nextDue ? " Next due: $nextDue" : '') . "\n";
        $created++;
    }

    if ($created === 0) {
        echo "No due PM masters found for today ($today).\n";
    } else {
        echo "Created $created work orders.\n";
    }
    exit(0);
}

// Legacy fallback
$frequencyMap = [
    'daily' => '+1 day',
    'weekly' => '+7 days',
    'monthly' => '+1 month',
];

$selectSchedules = "SELECT * FROM pm_schedules WHERE is_active=1 AND next_due_date <= '$today'";
$scheduleRows = safe_query_all($selectSchedules);
if (empty($scheduleRows)) {
    echo "No due PM schedules found for today ($today).\n";
    exit(0);
}

$created = 0;
foreach ($scheduleRows as $schedule) {
    $equipRes = $connection->query("SELECT * FROM equipment WHERE id=" . intval($schedule['equipment_id']) . " LIMIT 1");
    $equipment = $equipRes ? safe_fetch_assoc($equipRes) : null;

    $task_desc = $schedule['task_description'] ?? 'Preventive maintenance task';
    $description = ($db_type === 'sqlite') ? str_replace("'", "''", $task_desc) : $connection->real_escape_string($task_desc);
    
    $equip_desc = $equipment ? ($equipment['description'] ?? 'Unknown Equipment') : 'Unknown Equipment';
    $equipLabel = ($db_type === 'sqlite') ? str_replace("'", "''", $equip_desc) : $connection->real_escape_string($equip_desc);
    $tenantId = (int)($_SESSION['tenant_id'] ?? 1);

    $insertWo = "INSERT INTO work_orders
        (tenant_id, descriptive_text, audit_item, requestor, approval, equipment, description, action, mechanic_id,
         priority, submit_date, est_hours, act_hours, account, complete_date, coordinating_instructions,
         needed_date, wo_status, inspected_by)
        VALUES
        ($tenantId, 'PM: $equipLabel', 0, 'PM System', 'Approved', '$equipLabel', '$description', 'Auto-generated PM schedule #" . intval($schedule['id']) . "', 0,
         1, '$today', 0, 0, '', NULL, 'Auto-generated PM', '$today', 'Approved', 'System')";

    if (!$connection->query($insertWo)) {
        echo "Failed to create work order for schedule {$schedule['id']}\n";
        continue;
    }

    $woId = get_last_insert_id($connection);
    $freqType = $schedule['frequency_type'] ?: 'monthly';
    $interval = $frequencyMap[$freqType] ?? '+30 days';
    $nextDate = date('Y-m-d', strtotime($schedule['next_due_date'] . " $interval"));

    $updateSched = "UPDATE pm_schedules SET last_completed_date='$today', next_due_date='$nextDate', last_wo_id=$woId WHERE id=" . intval($schedule['id']);
    if (!$connection->query($updateSched)) {
        echo "Failed to update schedule {$schedule['id']}\n";
        continue;
    }

    echo "PM schedule {$schedule['id']} generated WO {$woId}. Next due: $nextDate\n";
    $created++;
}

if ($created === 0) {
    echo "No due PM schedules found for today ($today).\n";
} else {
    echo "Created $created work orders.\n";
}
exit(0);
