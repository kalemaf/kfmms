<?php
/**
 * Work Orders Management for CMMS
 * Enhanced with technicians, spares (with auto-reduction), and all maintenix fields
 */

require_once 'config.inc.php';
require_once 'spare_integration_functions.php';
require_once 'common.inc.php';
require_once 'libraries/inventory_manager.php';
require_once 'libraries/slaService.php';
require_once 'libraries/artisanService.php';
if (file_exists(__DIR__ . '/libraries/predictive_maintenance.php')) {
    require_once __DIR__ . '/libraries/predictive_maintenance.php';
}
if (file_exists(__DIR__ . '/libraries/predictive_integration.php')) {
    require_once __DIR__ . '/libraries/predictive_integration.php';
}

// Helper function to get last inserted ID (database-agnostic)
function get_last_insert_id($connection) {
    global $db_type;
    if ($db_type === 'sqlite') {
        return $connection->lastInsertId();
    } else {
        return $connection->insert_id;
    }
}

$message = '';
$editing = null;
$statusFilter = trim($_GET['status'] ?? '');
$selectedEquipmentId = null;
$isCompleted = false;
$currentUserRole = $_SESSION['role'] ?? '';
$isTechnician = ($currentUserRole === 'technician');
$blockPost = false;
if ($isTechnician && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $blockPost = true;
    $message = 'Technicians are not allowed to create or edit work orders; they may only view and complete them.';
}

if (isset($_GET['msg'])) {
    $message = trim($_GET['msg']);
}

if ($connection) {
    // Fetch technicians list with artisan details (active users with role technician, filtered by tenant_id)
    $technicians = [];
    $artisans_list = [];
    $edit_id = isset($_GET['edit']) && is_numeric($_GET['edit']) ? (int)$_GET['edit'] : null;
    $tenant_id = (int)($_SESSION['tenant_id'] ?? 1);
    
    // Get all technicians
    $techRes = safe_query_all("SELECT user_id, username FROM users WHERE is_active=1 AND role='technician' AND tenant_id = " . $tenant_id . " ORDER BY username");
    foreach ($techRes as $row) {
        $technicians[] = $row;
    }
    
    // Get artisan profiles with skills and performance scores
    try {
        $artisanService = new ArtisanService($pdo, $tenant_id);
        if ($edit_id) {
            $artisans_list = $artisanService->get_available_artisans_for_work_order($edit_id);
        } else {
            $artisans_list = $artisanService->get_all_artisans(['is_active' => 1]);
        }
    } catch (Exception $e) {
        error_log("Error loading artisans: " . $e->getMessage());
    }

    // Fetch equipment list (filtered by tenant_id)
    $equipmentList = [];
    $equipRes = safe_query_all("SELECT id, description FROM equipment WHERE tenant_id = " . $tenant_id . " ORDER BY description");
    foreach ($equipRes as $row) {
        $equipmentList[] = $row;
    }

    // Fetch consumables list for work orders (filtered by tenant_id)
    $consumablesList = [];
    $consumablesRes = safe_query_all("SELECT id, name, category, subcategory, unit, current_stock, cost_per_unit FROM consumables WHERE is_active = 1 AND tenant_id = " . (int)($_SESSION['tenant_id'] ?? 1) . " ORDER BY category, subcategory, name");
    foreach ($consumablesRes as $row) {
        $consumablesList[] = $row;
    }

    /**
 * Auto-detect and reduce spares based on work order content
 */

// Quick complete action from work order list
    // NOTE: This redirect is now handled in index.php BEFORE title.php outputs HTML
    // This prevents "headers already sent" errors

    // CSV import for work orders
    if (!$blockPost && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['csv_import']) && $_FILES['csv_import']['error'] === UPLOAD_ERR_OK) {
        $filePath = $_FILES['csv_import']['tmp_name'];
        if (($handle = fopen($filePath, 'r')) !== false) {
            $header = fgetcsv($handle);
            $imported = 0;
            while (($data = fgetcsv($handle)) !== false) {
                $row = array_combine($header, $data);
                if (!$row) { continue; }
                $descriptive_text = sanitize_input(trim($row['descriptive_text'] ?? ''));
                if ($descriptive_text === '') { continue; }
                $requestor = sanitize_input(trim($row['requestor'] ?? ''));
                $equipment = sanitize_input(trim($row['equipment'] ?? ''));
                $description = sanitize_input(trim($row['description'] ?? ''));
                $priority = (int)($row['priority'] ?? 1);
                $wo_status = sanitize_input(trim($row['wo_status'] ?? 'Pending Approval'));
                $submit_date = trim($row['submit_date'] ?? date('Y-m-d'));
                if ($submit_date === '' || $submit_date === '0000-00-00') { $submit_date = date('Y-m-d'); }
                $mechanic_id = isset($row['mechanic_id']) && is_numeric($row['mechanic_id']) ? (int)$row['mechanic_id'] : null;
                $est_hours = isset($row['est_hours']) && is_numeric($row['est_hours']) ? (int)$row['est_hours'] : null;
                $needed_date = trim($row['needed_date'] ?? '');
                $needed_date = ($needed_date === '' || $needed_date === '0000-00-00') ? null : $needed_date;
                $now = date('Y-m-d H:i:s');
                if (db_execute_params(
                    'INSERT INTO work_orders (descriptive_text, requestor, equipment, description, priority, wo_status, submit_date, mechanic_id, est_hours, needed_date, updated, tenant_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
                    [$descriptive_text, $requestor, $equipment, $description, $priority, $wo_status, $submit_date, $mechanic_id, $est_hours, $needed_date, $now, $_SESSION['tenant_id'] ?? 1]
                )) {
                    $imported++;
                }
            }
            fclose($handle);
            $message = "Imported {$imported} work order(s) from CSV.";
        }
    }

    // Add/update work order (full form submission)
    if (!$blockPost && $_SERVER['REQUEST_METHOD'] === 'POST' && empty($_FILES['csv_import']['name']) && !empty($_POST['descriptive_text'])) {
        log_debug("[WO] Form submission started");
        
        // DEBUG: Capture spare/part inputs
        $debug_spares = [];
        $debug_parts = [];
        foreach ($_POST as $key => $val) {
            if (strpos($key, 'spares_') === 0) $debug_spares[$key] = $val;
            if (strpos($key, 'part_') === 0) $debug_parts[$key] = $val;
        }
        log_debug("[WO] DEBUG - POST spares: " . json_encode($debug_spares));
        log_debug("[WO] DEBUG - POST parts: " . json_encode($debug_parts));
        
        try {
            $tenant_id = (int)($_SESSION['tenant_id'] ?? 1);
            $wo_id = isset($_POST['wo_id']) && is_numeric($_POST['wo_id']) ? (int)$_POST['wo_id'] : null;
            log_debug("[WO] Processing WO ID: {$wo_id}");
            $descriptive_text = sanitize_input(trim($_POST['descriptive_text']));
            $requestor = sanitize_input(trim($_POST['requestor'] ?? ''));
            $equipment = sanitize_input(trim($_POST['equipment'] ?? ''));
            $description = sanitize_input(trim($_POST['description'] ?? ''));
            $priority = (int)($_POST['priority'] ?? 1);
            $wo_status = sanitize_input(trim($_POST['wo_status'] ?? 'Pending Approval'));
            $submit_date = trim($_POST['submit_date'] ?? date('Y-m-d'));
            if ($submit_date === '' || $submit_date === '0000-00-00') { $submit_date = date('Y-m-d'); }

        // Collect selected consumables from form (consumables_<consumable_id> = quantity)
        $selectedConsumables = [];
        $consumableNotes = [];
        foreach ($_POST as $key => $value) {
            if (strpos($key, 'consumables_') === 0) {
                $consumable_id = (int)substr($key, strlen('consumables_'));
                $qty = floatval($value);
                if ($consumable_id > 0 && $qty > 0) {
                    $selectedConsumables[$consumable_id] = $qty;
                }
            } elseif (strpos($key, 'consumable_notes_') === 0) {
                $consumable_id = (int)substr($key, strlen('consumable_notes_'));
                $consumableNotes[$consumable_id] = trim($value);
            }
        }

        $mechanic_id = isset($_POST['mechanic_id']) && is_numeric($_POST['mechanic_id']) ? (int)$_POST['mechanic_id'] : null;
        $est_hours = isset($_POST['est_hours']) && is_numeric($_POST['est_hours']) ? (int)$_POST['est_hours'] : null;
        $act_hours = isset($_POST['act_hours']) && is_numeric($_POST['act_hours']) ? (int)$_POST['act_hours'] : null;
        $account = sanitize_input(trim($_POST['account'] ?? ''));
        
        // Auto-populate complete_date when work order is marked as Completed or Closed
        $complete_date = trim($_POST['complete_date'] ?? '');
        if (($wo_status === 'Completed' || $wo_status === 'Closed') && ($complete_date === '' || $complete_date === '0000-00-00')) {
            $complete_date = "'" . date('Y-m-d H:i:s') . "'"; // Auto-set to NOW() if not provided
        } elseif ($complete_date === '' || $complete_date === '0000-00-00') { 
            $complete_date = 'NULL'; 
        } else { 
            $complete_date = "'{$complete_date}'"; 
        }
        
        $coordinating_instructions = sanitize_input(trim($_POST['coordinating_instructions'] ?? ''));
        $needed_date = trim($_POST['needed_date'] ?? '');
        if ($needed_date === '' || $needed_date === '0000-00-00') { $needed_date = 'NULL'; } else { $needed_date = "'{$needed_date}'"; }
        $inspected_by = sanitize_input(trim($_POST['inspected_by'] ?? ''));
        $approval = sanitize_input(trim($_POST['approval'] ?? ''));
        $action = sanitize_input(trim($_POST['action'] ?? ''));

        $approvalRestricted = false;
        $canApprove = in_array($currentUserRole, ['admin', 'maintenance manager', 'supervisor'], true);
        if ($wo_status === 'Approved' && !$canApprove) {
            $wo_status = 'Pending Approval';
            $approval = '';
            $approvalRestricted = true;
            send_permission_request_notification(
                'Work order approval validation failed',
                'User attempted to approve a work order without approval rights.',
                [
                    'user_id' => $_SESSION['user_id'] ?? 0,
                    'username' => $_SESSION['user'] ?? '',
                    'role' => $currentUserRole,
                    'wo_id' => $wo_id
                ]
            );
        }
        $audit_item = (int)($_POST['audit_item'] ?? 0);
        
        // Auto-populate sla_due_date if not provided (submit_date + 2 days)
        $sla_due_date = trim($_POST['sla_due_date'] ?? '');
        if ($sla_due_date === '' || $sla_due_date === '0000-00-00') {
            $sla_due_date = "'" . date('Y-m-d', strtotime($submit_date . ' +2 days')) . "'"; // Auto-set to submit_date + 2 days
        } else { 
            $sla_due_date = "'{$sla_due_date}'"; 
        }

        $complete_date_value = ($complete_date === 'NULL') ? null : trim($complete_date, "'");
        $needed_date_value = ($needed_date === 'NULL') ? null : trim($needed_date, "'");
        $maintenance_type = trim($_POST['maintenance_type'] ?? '');
        if ($maintenance_type === '') {
            $maintenance_type = 'NULL';
        } else {
            $maintenance_type = "'" . sanitize_input($maintenance_type) . "'";
        }
        $maintenance_type_value = ($maintenance_type === 'NULL') ? null : trim($maintenance_type, "'");
        $failure_mode = trim($_POST['failure_mode'] ?? '');
        if ($failure_mode === '') {
            $failure_mode = 'NULL';
        } else {
            $failure_mode = "'" . sanitize_input($failure_mode) . "'";
        }
        $failure_mode_value = ($failure_mode === 'NULL') ? null : trim($failure_mode, "'");
        $sla_due_date_value = ($sla_due_date === 'NULL') ? null : trim($sla_due_date, "'");
        $failure_mode = trim($_POST['failure_mode'] ?? '');
        if ($failure_mode === '') {
            $failure_mode = 'NULL';
        } else {
            $failure_mode = "'" . sanitize_input($failure_mode) . "'";
        }
        $down_time_hours = (float)($_POST['down_time_hours'] ?? 0);
        $response_time = (float)($_POST['response_time'] ?? 0);
        $resolution_time = (float)($_POST['resolution_time'] ?? 0);

        // Collect selected spares from form (spares_<spare_id> = quantity)
        $selectedSpares = [];
        foreach ($_POST as $key => $val) {
            if (strpos($key, 'spares_') === 0) {
                $spare_id = (int)substr($key, 7);
                $qty = (int)$val;
                if ($spare_id > 0 && $qty > 0) {
                    $selectedSpares[$spare_id] = $qty;
                }
            }
        }

        // For edit mode: fetch current spares from DB for preservation logic
        $usedSpares = [];
        if ($wo_id) {
            $tenant_id_for_fetch = (int)($_SESSION['tenant_id'] ?? 1);
            $usedRes = safe_query_all("SELECT spare_id, quantity_used FROM work_order_spares WHERE wo_id=" . (int)$wo_id . " AND tenant_id=" . $tenant_id_for_fetch);
            foreach ($usedRes as $row) {
                $usedSpares[(int)$row['spare_id']] = (int)$row['quantity_used'];
            }
            log_debug("[WO] DEBUG - Fetched usedSpares for WO {$wo_id}: " . json_encode($usedSpares));
        }

        // Collect required parts for work order (part_<part_id> = qty)
        $selectedParts = [];
        foreach ($_POST as $key => $val) {
            if (strpos($key, 'part_') === 0) {
                $part_id = (int)substr($key, 5);
                $qty = (int)$val;
                if ($part_id > 0 && $qty > 0) {
                    $selectedParts[$part_id] = $qty;
                }
            }
        }

        /**
         * Persist work order parts and manage inventory reservation/issue.
         */
        function process_wo_parts($wo_id, $selectedParts, $wo_status) {
            global $connection;
            if (!$wo_id || empty($selectedParts)) {
                return;
            }

            $now = date('Y-m-d H:i:s');

            // Clear any previous part linkages for updates
            db_execute_params('DELETE FROM wo_parts WHERE wo_id = ?', [$wo_id]);

            foreach ($selectedParts as $part_id => $qty_required) {
                $part_id = intval($part_id);
                $qty_required = intval($qty_required);
                $quantity_reserved = 0;
                $status = 'pending';

                // Insert the part requirement record
                db_execute_params(
                    'INSERT INTO wo_parts (wo_id, part_id, quantity_required, quantity_reserved, status, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?)',
                    [$wo_id, $part_id, $qty_required, $quantity_reserved, $status, $now, $now]
                );
                $partRecordId = get_last_insert_id($connection);

                // Reserve or issue based on WO status
                if (in_array($wo_status, ['Assigned', 'Approved', 'Completed'])) {
                    $reserved = reserve_stock($wo_id, $part_id, $qty_required, $connection);
                    if ($reserved) {
                        $quantity_reserved = $qty_required;
                        $status = $wo_status === 'Completed' ? 'issued' : 'reserved';
                        db_execute_params(
                            'UPDATE wo_parts SET quantity_reserved = ?, status = ?, updated_at = ? WHERE id = ?',
                            [$quantity_reserved, $status, $now, $partRecordId]
                        );
                    } else {
                        $status = 'shortage';
                        db_execute_params(
                            'UPDATE wo_parts SET status = ?, updated_at = ? WHERE id = ?',
                            [$status, $now, $partRecordId]
                        );
                    }
                }

                if ($wo_status === 'Completed') {
                    // If already reserved, issue the stock.
                    if ($quantity_reserved > 0) {
                        issue_stock($wo_id, $part_id, $qty_required, $_SESSION['user_id'] ?? 0, $connection);
                        db_execute_params(
                            'UPDATE wo_parts SET status = ?, updated_at = ? WHERE id = ?',
                            ['completed', $now, $partRecordId]
                        );
                    }
                }

                if (in_array($wo_status, ['Suspended', 'Rejected', 'Canceled'])) {
                    // Revert any existing reservations if work order not active
                    return_stock($wo_id, $part_id, $qty_required, $_SESSION['user_id'] ?? 0, $connection);
                    db_execute_params(
                        'UPDATE wo_parts SET status = ?, updated_at = ? WHERE id = ?',
                        ['returned', $now, $partRecordId]
                    );
                }
            }
        }

        if ($wo_id) {
            // For edits, first clear old spare records
            log_debug("[WO] UPDATE mode - clearing old records for WO {$wo_id}");
            
            // DEBUG: Log current state before deletion
            $before_delete = db_query_row_params(
                'SELECT COUNT(*) as cnt FROM work_order_spares WHERE wo_id = ? AND tenant_id = ?',
                [$wo_id, $tenant_id]
            );
            log_debug("[WO] DEBUG - Before delete: " . ($before_delete['cnt'] ?? 0) . " spare records");
            
            // Disable foreign key constraints temporarily for SQLite
            if ($db_type === 'sqlite') {
                $connection->exec("PRAGMA foreign_keys=OFF");
            }
            
            // Only delete consumables/spares if user is providing new selections
            // Otherwise preserve existing records
            $tenant_id = (int)($_SESSION['tenant_id'] ?? 1);
            $shouldDeleteConsumables = !empty($selectedConsumables) || (isset($_POST['consumables_table_body']) && !empty($_POST['consumables_table_body']));
            $shouldDeleteSpares = !empty($selectedSpares) || $wo_status === 'Completed';
            
            log_debug("[WO] DEBUG - shouldDeleteSpares={$shouldDeleteSpares}, selectedSpares=" . json_encode($selectedSpares) . ", wo_status={$wo_status}");
            
            if ($shouldDeleteConsumables) {
                db_execute_params(
                    'DELETE FROM work_order_consumables WHERE work_order_id = ? AND tenant_id = ?',
                    [$wo_id, $tenant_id]
                );
            }
            if ($shouldDeleteSpares) {
                db_execute_params(
                    'DELETE FROM work_order_spares WHERE wo_id = ? AND tenant_id = ?',
                    [$wo_id, $tenant_id]
                );
                log_debug("[WO] DEBUG - Deleted spares");
            }
            if ($db_type === 'sqlite') {
                $connection->exec("PRAGMA foreign_keys=ON");
            }

            $complete_date_value = $complete_date === 'NULL' ? null : trim($complete_date, "'");
            $needed_date_value = $needed_date === 'NULL' ? null : trim($needed_date, "'");
            $maintenance_type_value = $maintenance_type === 'NULL' ? null : trim($maintenance_type, "'");
            $failure_mode_value = $failure_mode === 'NULL' ? null : trim($failure_mode, "'");
            $sla_due_date_value = $sla_due_date === 'NULL' ? null : trim($sla_due_date, "'");
            $now = date('Y-m-d H:i:s');

            $sql = 'UPDATE work_orders SET 
                    descriptive_text = ?, 
                    requestor = ?, 
                    equipment = ?, 
                    description = ?, 
                    priority = ?, 
                    wo_status = ?, 
                    submit_date = ?, 
                    mechanic_id = ?, 
                    est_hours = ?, 
                    act_hours = ?, 
                    account = ?, 
                    complete_date = ?, 
                    coordinating_instructions = ?, 
                    needed_date = ?, 
                    inspected_by = ?, 
                    approval = ?, 
                    action = ?, 
                    maintenance_type = ?, 
                    failure_mode = ?, 
                    audit_item = ?, 
                    sla_due_date = ?, 
                    down_time_hours = ?, 
                    response_time = ?, 
                    resolution_time = ?, 
                    updated = ? 
                    WHERE wo_id = ?';
            error_log("[WO] Executing UPDATE query");
            if (db_execute_params($sql, [
                $descriptive_text,
                $requestor,
                $equipment,
                $description,
                $priority,
                $wo_status,
                $submit_date,
                $mechanic_id,
                $est_hours,
                $act_hours,
                $account,
                $complete_date_value,
                $coordinating_instructions,
                $needed_date_value,
                $inspected_by,
                $approval,
                $action,
                $maintenance_type_value,
                $failure_mode_value,
                $audit_item,
                $sla_due_date_value,
                $down_time_hours,
                $response_time,
                $resolution_time,
                $now,
                $wo_id
            ])) {
                error_log("[WO] UPDATE successful");
                
                if ($mechanic_id) {
                    try {
                        $artisanService->assign_work_order_to_artisan($wo_id, $mechanic_id, $est_hours);
                        error_log("[WO#$wo_id] Artisan assignment recorded for technician {$mechanic_id}");
                    } catch (Exception $e) {
                        error_log("[WO#$wo_id] Artisan assignment error: " . $e->getMessage());
                    }
                }
                
                // Re-insert selected consumables for this work order (for ALL updates, not just Completed)
                if (!empty($selectedConsumables)) {
                    foreach ($selectedConsumables as $consumable_id => $qty) {
                        $consumable_cost = 0;
                        foreach ($consumablesList as $c) {
                            if ($c['id'] == $consumable_id) {
                                $consumable_cost = $c['cost_per_unit'];
                                break;
                            }
                        }
                        $notes = $consumableNotes[$consumable_id] ?? '';
                        if (function_exists('add_consumable_to_work_order')) {
                            add_consumable_to_work_order($wo_id, $consumable_id, $qty, $connection, $consumable_cost, $notes);
                        }
                    }
                } else if (isset($usedConsumables) && !empty($usedConsumables)) {
                    // Preserve existing consumables if none were selected in form
                    log_debug("[WO] Preserving existing consumables during update");
                    foreach ($usedConsumables as $consumable_id => $cons) {
                        if (function_exists('add_consumable_to_work_order')) {
                            add_consumable_to_work_order($wo_id, $cons['consumable_id'], $cons['quantity_required'], $connection, $cons['unit_cost'], $cons['notes']);
                        }
                    }
                }
                
                // Update spare inventory if status is Completed or In Use
                // NOTE: Spare inventory reduction for Completed status is now handled exclusively
                // by complete_work_order.php to prevent duplicate reductions. This code only
                // records/tracks spares for all statuses.
                if (!empty($selectedSpares) || (isset($usedSpares) && !empty($usedSpares))) {
                    // Determine which spares to use
                    $sparesToRecord = !empty($selectedSpares) ? $selectedSpares : $usedSpares;
                    
                    foreach ($sparesToRecord as $spare_id => $qty) {
                        // Record spare usage (include tenant_id)
                        db_execute_params(
                            'INSERT INTO work_order_spares (wo_id, spare_id, quantity_used, tenant_id) VALUES (?, ?, ?, ?)',
                            [$wo_id, $spare_id, $qty, $tenant_id]
                        );
                    }
                    log_debug("[WO] DEBUG - Recorded " . count($sparesToRecord) . " spares (no inventory reduction)");
                }
                
                // WARNING: DO NOT reduce inventory or auto-detect spares here!
                // That is handled exclusively by complete_work_order.php when work order
                // is formally completed through that dedicated interface.
                // Save and reserve / issue selected parts
                log_debug("[WO] Processing parts");
                process_wo_parts($wo_id, $selectedParts, $wo_status);

                $message = 'Work order updated successfully.';
                if (!empty($approvalRestricted)) {
                    $message .= ' Only supervisors, maintenance managers, or admins may approve work requests.';
                }
            } else {
                error_log("[WO] UPDATE query failed");
                if (method_exists($connection, 'errorInfo')) {
                    $error = $connection->errorInfo();
                    error_log("[WO] Database error: " . json_encode($error));
                }
                $message = 'Update failed: Database error occurred';
            }
        } else {
            error_log("[WO] CREATE mode - creating new work order");
            $now = date('Y-m-d H:i:s');
            $created_values = [
                null,
                sanitize_input($descriptive_text),
                sanitize_input($requestor),
                sanitize_input($equipment),
                sanitize_input($description),
                $priority,
                sanitize_input($wo_status),
                sanitize_input($submit_date),
                $mechanic_id,
                $est_hours,
                $act_hours,
                sanitize_input($account),
                $complete_date_value,
                sanitize_input($coordinating_instructions),
                $needed_date_value,
                sanitize_input($inspected_by),
                sanitize_input($approval),
                sanitize_input($action),
                $maintenance_type_value,
                $failure_mode_value,
                $audit_item,
                $sla_due_date_value,
                $down_time_hours,
                $response_time,
                $resolution_time,
                $now,
                $_SESSION['tenant_id'] ?? 1
            ];

            $sql = 'INSERT INTO work_orders 
                    (pm_id, descriptive_text, requestor, equipment, description, priority, wo_status, submit_date, mechanic_id, est_hours, act_hours, account, complete_date, coordinating_instructions, needed_date, inspected_by, approval, action, maintenance_type, failure_mode, audit_item, sla_due_date, down_time_hours, response_time, resolution_time, updated, tenant_id) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)';
            if (db_execute_params($sql, $created_values)) {
                $newWoId = get_last_insert_id($connection);
                
                // ✨ INTEGRATION: Create SLA tracking for this work order
                if (function_exists('create_work_order_sla') && $mechanic_id) {
                    try {
                        create_work_order_sla($newWoId, $mechanic_id, $priority);
                        error_log("[WO#$newWoId] SLA tracking created");
                    } catch (Exception $e) {
                        error_log("[WO#$newWoId] SLA creation error: " . $e->getMessage());
                    }
                }

                if ($mechanic_id) {
                    try {
                        $artisanService->assign_work_order_to_artisan($newWoId, $mechanic_id, $est_hours);
                        error_log("[WO#$newWoId] Artisan assignment recorded for technician {$mechanic_id}");
                    } catch (Exception $e) {
                        error_log("[WO#$newWoId] Artisan assignment error: " . $e->getMessage());
                    }
                }
                
                // Auto-reduce spares if status triggers consumption
                if ($wo_status === 'Completed') {
                    foreach ($selectedSpares as $spare_id => $qty) {
                        // Record spare usage (include tenant_id)
                        db_execute_params(
                            'INSERT INTO work_order_spares (wo_id, spare_id, quantity_used, tenant_id) VALUES (?, ?, ?, ?)',
                            [$newWoId, $spare_id, $qty, $tenant_id]
                        );
                        // Reduce inventory from both equipment_spares and general stock
                        reduce_spare_inventory($spare_id, $qty, $newWoId, $_SESSION['user_id'] ?? 0, 'Work Order #' . $newWoId, $connection);
                    }
                    
                    // Insert selected consumables for this work order
                    foreach ($selectedConsumables as $consumable_id => $qty) {
                        $consumable_cost = 0;
                        foreach ($consumablesList as $c) {
                            if ($c['id'] == $consumable_id) {
                                $consumable_cost = $c['cost_per_unit'];
                                break;
                            }
                        }
                        if (function_exists('add_consumable_to_work_order')) {
                            add_consumable_to_work_order($newWoId, $consumable_id, $qty, $connection, $consumable_cost, '');
                        }
                    }
                    
                    // Auto-detect and reduce spares based on work order content
                    $wo_data = [
                        'wo_id' => $newWoId,
                        'equipment' => $equipment,
                        'description' => $description,
                        'descriptive_text' => $descriptive_text,
                        'action' => $action,
                        'wo_status' => $wo_status
                    ];
                    auto_reduce_spares($wo_data, $connection);
                    
                    // Consume all consumables linked to this work order
                    if (function_exists('consume_work_order_consumables')) {
                        consume_work_order_consumables($newWoId, $connection);
                    }
                    
                    // Rebuild analytics cache after work order completion
                    if (function_exists('rebuild_lifecycle_analytics')) {
                        rebuild_lifecycle_analytics($connection);
                    }
                }
                // Save and reserve / issue selected parts
                process_wo_parts($newWoId, $selectedParts, $wo_status);

                $message = 'Work order created successfully.';
                if (!empty($approvalRestricted)) {
                    $message .= ' Only supervisors, maintenance managers, or admins may approve work requests.';
                }
            } else {
                error_log("[WO] CREATE query failed");
                if (method_exists($connection, 'errorInfo')) {
                    $error = $connection->errorInfo();
                    error_log("[WO] Database error: " . json_encode($error));
                }
                $message = 'Create failed: Database error occurred';
            }
        }
        } catch (Exception $e) {
            error_log("[WO] EXCEPTION during form processing: " . $e->getMessage());
            error_log("[WO] Stack trace: " . $e->getTraceAsString());
            $message = 'Error processing work order: ' . $e->getMessage();
        }
    }

    // Edit mode - fetch existing spares, parts, and consumables for this WO
    $usedSpares = [];
    $usedParts = [];
    $usedConsumables = [];
    $nextWoId = null;
    
    if (isset($_GET['edit']) && is_numeric($_GET['edit'])) {
        $edit_id = (int)$_GET['edit'];
        $tenant_id = (int)($_SESSION['tenant_id'] ?? 1);
        $result_edit = safe_query_row("SELECT * FROM work_orders WHERE wo_id=" . (int)$edit_id . " AND tenant_id=" . $tenant_id . " LIMIT 1");
        if ($result_edit) {
            $editing = $result_edit;
            $selectedEquipmentId = $editing['equipment'] ?? null;
            $isCompleted = isset($editing['wo_status']) && $editing['wo_status'] === 'Completed';
            // Fetch already-used spares for this WO (with tenant filtering)
            $tenant_id = (int)($_SESSION['tenant_id'] ?? 1);
            $usedRes = safe_query_all("SELECT spare_id, quantity_used FROM work_order_spares WHERE wo_id=" . (int)$edit_id . " AND tenant_id=" . $tenant_id);
            foreach ($usedRes as $row) {
                $usedSpares[(int)$row['spare_id']] = (int)$row['quantity_used'];
            }
            // Fetch already-used parts for this WO (with tenant filtering)
            $partsRes = safe_query_all("SELECT part_id, quantity_required FROM wo_parts WHERE wo_id=" . (int)$edit_id . " AND tenant_id=" . $tenant_id);
            foreach ($partsRes as $row) {
                $usedParts[(int)$row['part_id']] = (int)$row['quantity_required'];
            }
            // Fetch already-linked consumables for this WO (with tenant filtering)
            $consumablesRes = safe_query_all("SELECT wc.id, c.id as consumable_id, c.name, wc.quantity_required, wc.unit_cost, wc.notes FROM work_order_consumables wc JOIN consumables c ON wc.consumable_id = c.id WHERE wc.work_order_id=" . (int)$edit_id . " AND wc.tenant_id=" . $tenant_id . " ORDER BY c.name");
            foreach ($consumablesRes as $row) {
                $usedConsumables[(int)$row['consumable_id']] = $row;
            }
        }
    } else {
        // For new WO, calculate the next ID as MAX(wo_id) + 1
        $result_next = safe_query_row("SELECT MAX(wo_id) as max_id FROM work_orders");
        if ($result_next) {
            $nextWoId = (int)($result_next['max_id'] ?? 0) + 1;
        } else {
            $nextWoId = 1; // Fallback if no work orders exist
        }
    }

    // Fetch spares for selected equipment
    $spares = [];
    if ($selectedEquipmentId) {
        // Use parameterized queries for all database operations
        $tenant_id = (int)($_SESSION['tenant_id'] ?? 1);
        $stmt = $connection->prepare("SELECT id, part_name, part_number, quantity FROM equipment_spares WHERE equipment_id = ? AND tenant_id = ? ORDER BY part_name");
        $stmt->bindParam(1, $selectedEquipmentId, PDO::PARAM_INT);
        $stmt->bindParam(2, $tenant_id, PDO::PARAM_INT);
        try {
            $stmt->execute();
            $sparesRes = $stmt->fetchAll(PDO::FETCH_ASSOC);
            foreach ($sparesRes as $row) {
                $spares[] = $row;
            }
        } catch (Exception $e) {
            error_log("Equipment spares query failed: " . $e->getMessage());
            throw $e;
        }
    }

    // Fetch work orders list
    $statusFilter = trim($_GET['status'] ?? '');
    
    // Build database-agnostic query for work orders list
    $cast_type = ($db_type === 'sqlite') ? 'TEXT' : 'CHAR';
    $query = "SELECT wo.wo_id, wo.descriptive_text, wo.wo_status, wo.priority, wo.requestor, wo.submit_date, wo.equipment, wo.mechanic_id, wo.est_hours,
              COALESCE(e.description, wo.equipment) AS equipment_name,
              COALESCE(u.username, '') AS technician_name,
              wo.audit_item, wo.sla_due_date, wo.down_time_hours, wo.response_time, wo.resolution_time
              FROM work_orders wo
              LEFT JOIN equipment e ON wo.equipment = CAST(e.id AS {$cast_type})
              LEFT JOIN users u ON wo.mechanic_id = u.user_id";
    $query .= " WHERE wo.tenant_id = " . $tenant_id;
    if ($statusFilter !== '' && in_array($statusFilter, ['Pending Approval','Assigned','Approved','Suspended','Completed','Rejected','Hot Job'], true)) {
        $query .= " AND wo.wo_status='" . str_replace("'", "''", $statusFilter) . "'";
    }
    $query .= " ORDER BY wo.submit_date DESC LIMIT 200";

    $work_orders = [];
    $result = safe_query_all($query);
    foreach ($result as $row) {
        $work_orders[] = $row;
    }
}
?>

<h2>Work Orders Management</h2>

<?php if ($message): ?>
    <p style="color: green;"><?php echo htmlspecialchars($message); ?></p>
<?php endif; ?>

<form method="get" style="margin-bottom: 12px; padding: 8px; border: 1px solid #ccc;">
    <strong>Filter by status:</strong>
    <select name="status">
        <option value=""<?php echo $statusFilter === '' ? ' selected' : ''; ?>>All</option>
        <?php foreach (['Pending Approval','Assigned','Approved','Suspended','Completed','Rejected','Hot Job'] as $status): ?>
            <option value="<?php echo htmlspecialchars($status); ?>"<?php echo $statusFilter === $status ? ' selected' : ''; ?>><?php echo htmlspecialchars($status); ?></option>
        <?php endforeach; ?>
    </select>
    <button type="submit" style="margin-left:8px;">Apply Filter</button>
</form>

<?php if (!$isTechnician): ?>
<form method="post" enctype="multipart/form-data" style="margin-bottom: 12px; padding: 8px; border: 1px dashed #999;">
    <strong>Import work orders CSV</strong> (headers: descriptive_text,requestor,equipment,description,priority,wo_status,submit_date,mechanic_id,est_hours,needed_date)
    <input type="file" name="csv_import" accept=".csv" required style="margin-left:8px;" />
    <button type="submit">Import CSV</button>
</form>
<?php else: ?>
<p style="margin-bottom: 12px; padding: 12px; border: 1px dashed #999; background: #f8f9fa; color: #555;">Technicians may only view and complete work orders. CSV import is disabled.</p>
<?php endif; ?>

<?php
$editMode = ($editing !== null);
$editTitle = $editMode ? 'Edit Work Order #'.(int)$editing['wo_id'] : 'Create New Work Order';
$standardFailureModes = [
    'Mechanical Failure',
    'Electrical Failure',
    'Hydraulic Failure',
    'Pneumatic Failure',
    'Wear / Tear',
    'Control System Fault',
    'Leakage',
    'Overheating',
    'Software / PLC',
    'Other'
];
$maintenanceTypes = [
    'Corrective',
    'Preventive',
    'Predictive',
    'Inspection',
    'Lubrication',
    'Other'
];
$vals = $editMode ? $editing : [
    'descriptive_text' => '',
    'requestor' => '',
    'equipment' => '',
    'description' => '',
    'action' => '',
    'mechanic_id' => '',
    'priority' => 1,
    'submit_date' => date('Y-m-d'),
    'est_hours' => '',
    'act_hours' => '',
    'account' => '',
    'complete_date' => '',
    'coordinating_instructions' => '',
    'needed_date' => '',
    'wo_status' => 'Pending Approval',
    'inspected_by' => '',
    'approval' => '',
    'audit_item' => 0,
    'sla_due_date' => '',
    'down_time_hours' => '',
    'response_time' => '',
    'resolution_time' => '',
    'failure_mode' => '',
    'maintenance_type' => ''
];
?>

<?php if (!$isTechnician || $editMode): ?>
<form method="post" enctype="multipart/form-data" style="margin-bottom: 20px; padding: 12px; border: 2px solid #666; background-color: #f9f9f9;" id="woForm">
        <?php if (!empty($isCompleted)): ?>
            <div style="margin-bottom:16px; padding:12px; border:1px solid #c3e6cb; background:#d4edda; color:#155724; border-radius:5px;">
                <strong>This work order has been completed and is no longer editable.</strong>
            </div>
        <?php endif; ?>
        <fieldset <?php echo (!empty($isCompleted) || $isTechnician) ? 'disabled' : ''; ?>>
    <h3><?php echo $editTitle; ?></h3>
    <table style="width: 100%; border-collapse: collapse;">
        <tr><th colspan="3" style="background-color: #e9ecef; padding: 10px; text-align: left;"><h5>Basic Information</h5></th></tr>
    <script>
        function loadSpares(equipmentId) {
            const sparesDiv = document.getElementById('sparesContainer');
            if (!equipmentId) {
                sparesDiv.innerHTML = '<p style="color: #999;">Select equipment to see available spares</p>';
                return;
            }
            fetch('api_spares.php?equipment_id=' + equipmentId)
                .then(r => {
                    if (!r.ok) {
                        throw new Error('Network response was not ok: ' + r.status);
                    }
                    return r.json();
                })
                .then(items => {
                    // Check if API returned an error object
                    if (items.error) {
                        sparesDiv.innerHTML = '<p style="color: red;">Error loading spares: ' + items.error + '</p>';
                        console.error('api_spares.php error:', items.error);
                        return;
                    }
                    
                    if (!items || items.length === 0) {
                        sparesDiv.innerHTML = '<p style="color: #999;">No spares or parts available for this equipment</p>';
                        return;
                    }
                    
                    let html = '<p><strong>Equipment Spares & Parts (Select & Enter Qty to Use):</strong></p>';
                    
                    // Group by type
                    const spares = items.filter(item => item.type === 'spare');
                    const parts = items.filter(item => item.type === 'part');
                    
                    if (spares.length > 0) {
                        html += '<h6>Equipment-Specific Spares:</h6>';
                        spares.forEach(s => {
                            const usedQty = sparesUsed[s.id] || 0;
                            html += `<div class="spare-item" style="margin-bottom: 8px; padding: 8px; border: 1px solid #ddd; border-radius: 3px;">
                                <label style="display: block; margin-bottom: 6px;">
                                    <strong>${s.part_name}</strong> (${s.part_number}) - Available: ${s.quantity}
                                </label>
                                <label>Qty to Use: <input type="number" name="spares_${s.id}" min="0" max="${s.quantity}" value="${usedQty}" placeholder="0" id="spare_qty_${s.id}" style="width: 80px;"></label>
                            </div>`;
                        });
                    }
                    
                    if (parts.length > 0) {
                        html += '<h6>General Parts Inventory:</h6>';
                        parts.forEach(p => {
                            const usedQty = partsUsed[p.id] || 0;
                            html += `<div class="spare-item" style="margin-bottom: 8px; padding: 8px; border: 1px solid #ddd; border-radius: 3px;">
                                <label style="display: block; margin-bottom: 6px;">
                                    <strong>${p.part_name}</strong> (${p.part_number}) - Available: ${p.quantity}
                                </label>
                                <label>Qty to Use: <input type="number" name="part_${p.id}" min="0" max="${p.quantity}" value="${usedQty}" placeholder="0" id="part_qty_${p.id}" style="width: 80px;"></label>
                            </div>`;
                        });
                    }
                    
                    sparesDiv.innerHTML = html;
                    // No more checkbox listeners needed - inputs are always visible
                })
                .catch(e => sparesDiv.innerHTML = '<p style="color: red;">Error loading spares: ' + e.message + '</p>');
        }
        
        const sparesUsed = <?php echo json_encode($usedSpares); ?>;
        const partsUsed = <?php echo json_encode($usedParts ?? []); ?>;
        
        document.addEventListener('DOMContentLoaded', function() {
            const equipSelect = document.querySelector('select[name="equipment"]');
            if (equipSelect) {
                equipSelect.addEventListener('change', function() {
                    loadSpares(this.value);
                });
                // Initial load if equipment already selected
                if (equipSelect.value) loadSpares(equipSelect.value);
            }
        });
    </script>
    <h3 style="margin-top: 0;"><?php echo htmlspecialchars($editTitle); ?></h3>
    <?php if ($editMode): ?>
        <input type="hidden" name="wo_id" value="<?php echo (int)$editing['wo_id']; ?>">
        <p style="padding: 12px 15px; background-color: #e7f3ff; border-left: 4px solid #007bff; color: #004085; border-radius: 4px; margin-bottom: 20px;"><strong>Work Order ID:</strong> <span style="font-size: 16px; font-weight: bold;">WO #<?php echo (int)$editing['wo_id']; ?></span></p>
    <?php elseif ($nextWoId): ?>
        <p style="padding: 12px 15px; background-color: #d4edda; border-left: 4px solid #28a745; color: #155724; border-radius: 4px; margin-bottom: 20px;"><strong>New Work Order ID:</strong> <span style="font-size: 18px; font-weight: bold; color: #28a745;">WO #<?php echo $nextWoId; ?></span> <span style="font-size: 12px; color: #666;">(will be assigned upon creation)</span></p>
    <?php endif; ?>

    <style>
        .wo-form-container { max-width: 900px; margin: 0 auto; }
        .wo-form-section { margin-bottom: 20px; padding: 15px; background: white; border: 1px solid #dee2e6; border-radius: 5px; }
        .wo-form-section h5 { color: #007bff; margin: 0 0 15px 0; padding-bottom: 10px; border-bottom: 2px solid #007bff; }
        .wo-form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 15px; margin-bottom: 12px; }
        .wo-form-row.full { grid-template-columns: 1fr; }
        .wo-form-row.three { grid-template-columns: 1fr 1fr 1fr; }
        .wo-form-group label { display: block; margin-bottom: 5px; font-weight: 600; color: #333; font-size: 13px; }
        .wo-form-group input, 
        .wo-form-group select, 
        .wo-form-group textarea { width: 100%; padding: 8px; border: 1px solid #ced4da; border-radius: 4px; font-family: Arial, sans-serif; font-size: 13px; }
        .wo-form-group input:focus, 
        .wo-form-group select:focus, 
        .wo-form-group textarea:focus { outline: none; border-color: #007bff; box-shadow: 0 0 0 3px rgba(0,123,255,0.25); }
        .wo-form-group textarea { resize: vertical; }
        .wo-checkbox-group { margin-top: 8px; }
        .wo-checkbox-group label { display: flex; align-items: center; font-weight: normal; margin: 0; }
        .wo-checkbox-group input[type="checkbox"] { width: auto; margin-right: 8px; }
        .wo-form-buttons { margin-top: 20px; padding-top: 15px; border-top: 1px solid #dee2e6; }
        .wo-form-buttons button, 
        .wo-form-buttons a { margin-right: 10px; padding: 10px 20px; font-weight: 600; border: none; border-radius: 4px; cursor: pointer; text-decoration: none; display: inline-block; }
        .wo-form-buttons button { background-color: #28a745; color: white; }
        .wo-form-buttons button:hover { background-color: #218838; }
        .wo-form-buttons a { background-color: #6c757d; color: white; }
        .wo-form-buttons a:hover { background-color: #5a6268; }
        .spares-section { margin-top: 15px; padding: 12px; background: #f0f8ff; border: 1px solid #b3d9ff; border-radius: 4px; }
        .spare-item { margin: 8px 0; padding: 8px; background: white; border: 1px solid #ddd; border-radius: 3px; }
        .spare-item label { display: flex; align-items: center; margin: 0; }
        .spare-item input[type="checkbox"] { width: auto; margin-right: 10px; }
    </style>

    <div class="wo-form-container">
        <div class="wo-form-section">
            <h5>Basic Information</h5>
            <div class="wo-form-row">
                <div class="wo-form-group">
                    <label>Work Order Title *</label>
                    <input type="text" name="descriptive_text" required value="<?php echo htmlspecialchars($vals['descriptive_text']); ?>" placeholder="Brief description of the work order">
                </div>
                <div class="wo-form-group">
                    <label>Requestor *</label>
                    <input type="text" name="requestor" required value="<?php echo htmlspecialchars($vals['requestor']); ?>" placeholder="Name of person requesting work">
                </div>
            </div>
            <div class="wo-form-row full">
                <div class="wo-form-group">
                    <label>Description *</label>
                    <textarea name="description" rows="4" placeholder="Detailed description of the work to be done"><?php echo htmlspecialchars($vals['description']); ?></textarea>
                </div>
            </div>
        </div>

        <div class="wo-form-section">
            <h5>Equipment & Assignment</h5>
            <div class="wo-form-row">
                <div class="wo-form-group">
                    <label>Equipment *</label>
                    <select name="equipment" required>
                        <option value="">-- Select Equipment --</option>
                        <?php foreach ($equipmentList as $e): ?>
                            <option value="<?php echo (int)$e['id']; ?>" <?php echo (isset($vals['equipment']) && $vals['equipment'] == $e['id']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($e['description']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="wo-form-group">
                    <label>Assigned Artisan (with Skills & Performance)</label>
                    <select name="mechanic_id">
                        <option value="">-- Unassigned --</option>
                        <?php 
                        // First show artisans with profile and skills info
                        foreach ($artisans_list as $artisan): 
                            $display_name = htmlspecialchars($artisan['first_name'] . ' ' . $artisan['last_name']);
                            $skills = $artisan['skill_count'] > 0 ? " ({$artisan['skill_count']} skills)" : '';
                            $perf_score = isset($artisan['performance_score']) ? $artisan['performance_score'] : 0;
                            $perf = " - Perf: " . number_format($perf_score, 0) . "%";
                            $label = $display_name . $skills . $perf;
                        ?>
                            <option value="<?php echo (int)$artisan['artisan_id']; ?>" <?php echo (isset($vals['mechanic_id']) && $vals['mechanic_id'] == $artisan['artisan_id']) ? 'selected' : ''; ?>>
                                <?php echo $label; ?>
                            </option>
                        <?php endforeach; ?>
                        
                        <?php if (!empty($technicians)): ?>
                            <optgroup label="--- Other Technicians (No Profile) ---">
                        <?php foreach ($technicians as $tech): 
                            // Skip if already listed as artisan
                            $is_artisan = false;
                            foreach ($artisans_list as $a) {
                                if ($a['artisan_id'] == $tech['user_id']) {
                                    $is_artisan = true;
                                    break;
                                }
                            }
                            if ($is_artisan) continue;
                        ?>
                            <option value="<?php echo (int)$tech['user_id']; ?>" <?php echo (isset($vals['mechanic_id']) && $vals['mechanic_id'] == $tech['user_id']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($tech['username']); ?> (No profile)
                            </option>
                        <?php endforeach; ?>
                            </optgroup>
                        <?php endif; ?>
                    </select>
                </div>
            </div>
            <div class="wo-form-row">
                <div class="wo-form-group">
                    <label>Failure Mode</label>
                    <select name="failure_mode">
                        <option value="">-- Select Failure Mode --</option>
                        <?php foreach ($standardFailureModes as $mode): ?>
                            <option value="<?php echo htmlspecialchars($mode); ?>" <?php echo (isset($vals['failure_mode']) && $vals['failure_mode'] === $mode) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($mode); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="wo-form-group">
                    <label>Maintenance Type</label>
                    <select name="maintenance_type">
                        <option value="">-- Select Maintenance Type --</option>
                        <?php foreach ($maintenanceTypes as $type): ?>
                            <option value="<?php echo htmlspecialchars($type); ?>" <?php echo (isset($vals['maintenance_type']) && $vals['maintenance_type'] === $type) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($type); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
        </div>

        <div class="wo-form-section">
            <h5>Dates & Scheduling</h5>
            <div class="wo-form-row three">
                <div class="wo-form-group">
                    <label>Submit Date *</label>
                    <input type="date" name="submit_date" required value="<?php echo htmlspecialchars($vals['submit_date']); ?>">
                </div>
                <div class="wo-form-group">
                    <label>Needed Date</label>
                    <input type="date" name="needed_date" value="<?php echo htmlspecialchars($vals['needed_date'] ?? ''); ?>">
                </div>
                <div class="wo-form-group">
                    <label>Completion Date</label>
                    <input type="date" name="complete_date" value="<?php echo htmlspecialchars($vals['complete_date'] ?? ''); ?>">
                </div>
            </div>
            <div class="wo-form-row three">
                <div class="wo-form-group">
                    <label>Est. Hours</label>
                    <input type="number" name="est_hours" min="0" placeholder="0" value="<?php echo htmlspecialchars($vals['est_hours'] ?? ''); ?>">
                </div>
                <div class="wo-form-group">
                    <label>Actual Hours</label>
                    <input type="number" name="act_hours" min="0" placeholder="0" value="<?php echo htmlspecialchars($vals['act_hours'] ?? ''); ?>">
                </div>
                <div class="wo-form-group">
                    <label>Account Code</label>
                    <input type="text" name="account" value="<?php echo htmlspecialchars($vals['account'] ?? ''); ?>">
                </div>
            </div>
        </div>

        <div class="wo-form-section">
            <h5>Status & Priority</h5>
            <div class="wo-form-row">
                <div class="wo-form-group">
                    <label>Priority (1-5)</label>
                    <input type="number" name="priority" min="1" max="5" value="<?php echo (int)($vals['priority'] ?? 1); ?>">
                </div>
                <div class="wo-form-group">
                    <label>Status</label>
                    <select name="wo_status">
                        <?php foreach (['Pending Approval','Assigned','Approved','Suspended','Completed','Rejected','Hot Job'] as $status): ?>
                            <option value="<?php echo htmlspecialchars($status); ?>" <?php echo (isset($vals['wo_status']) && $vals['wo_status'] === $status) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($status); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
        </div>

        <div class="wo-form-section">
            <h5>SLA & Metrics</h5>
            <div class="wo-form-row">
                <div class="wo-form-group">
                    <label>SLA Due Date</label>
                    <input type="date" name="sla_due_date" value="<?php echo htmlspecialchars($vals['sla_due_date'] ?? ''); ?>">
                </div>
                <div class="wo-form-group">
                    <label>Down Time Hours</label>
                    <input type="number" name="down_time_hours" min="0" step="0.01" placeholder="0.0" value="<?php echo htmlspecialchars($vals['down_time_hours'] ?? ''); ?>">
                </div>
            </div>
            <div class="wo-form-row">
                <div class="wo-form-group">
                    <label>Response Time (hours)</label>
                    <input type="number" name="response_time" min="0" step="0.01" placeholder="0.0" value="<?php echo htmlspecialchars($vals['response_time'] ?? ''); ?>">
                </div>
                <div class="wo-form-group">
                    <label>Resolution Time (hours)</label>
                    <input type="number" name="resolution_time" min="0" step="0.01" placeholder="0.0" value="<?php echo htmlspecialchars($vals['resolution_time'] ?? ''); ?>">
                </div>
            </div>
            <div class="wo-form-row">
                <div class="wo-form-group wo-checkbox-group">
                    <label>
                        <input type="checkbox" name="audit_item" value="1" <?php echo (isset($vals['audit_item']) && $vals['audit_item']) ? 'checked' : ''; ?>>
                        This is an Audit Item
                    </label>
                </div>
            </div>
        </div>

        <div class="wo-form-section">
            <h5>Approvals & Inspection</h5>
            <div class="wo-form-row">
                <div class="wo-form-group">
                    <label>Inspected By</label>
                    <input type="text" name="inspected_by" value="<?php echo htmlspecialchars($vals['inspected_by'] ?? ''); ?>" placeholder="Inspector name">
                </div>
                <div class="wo-form-group">
                    <label>Approved By</label>
                    <input type="text" name="approval" value="<?php echo htmlspecialchars($vals['approval'] ?? ''); ?>" placeholder="Approver name">
                </div>
            </div>
        </div>

        <div class="wo-form-section">
            <h5>Work Completion</h5>
            <div class="wo-form-row full">
                <div class="wo-form-group">
                    <label>Coordinating Instructions</label>
                    <textarea name="coordinating_instructions" rows="3" placeholder="Any special instructions or coordination needed"><?php echo htmlspecialchars($vals['coordinating_instructions'] ?? ''); ?></textarea>
                </div>
            </div>
            <div class="wo-form-row full">
                <div class="wo-form-group">
                    <label>Action Taken</label>
                    <textarea name="action" rows="3" placeholder="Description of actions taken to complete this work order"><?php echo htmlspecialchars($vals['action'] ?? ''); ?></textarea>
                </div>
            </div>
        </div>

        <div class="wo-form-section">
            <h5>Equipment Spares</h5>
            <div id="sparesContainer" class="spares-section">
                <p style="color: #999; margin: 0;">Select equipment above to see available spares</p>
            </div>
        </div>

        <div class="wo-form-section">
            <h5>Consumables</h5>
            <div id="consumablesContainer">
                <div style="margin-bottom: 15px;">
                    <label style="display: block; margin-bottom: 8px; font-weight: 600; color: #333;">Select Consumable:</label>
                    <div style="display: grid; grid-template-columns: 1fr 1fr 1fr auto; gap: 10px; margin-bottom: 15px;">
                        <select id="consumableSelect" style="padding: 8px; border: 1px solid #ced4da; border-radius: 4px;">
                            <option value="">-- Select a Consumable --</option>
                            <?php foreach ($consumablesList as $cons): ?>
                                <option value="<?php echo (int)$cons['id']; ?>" data-cost="<?php echo round((float)$cons['cost_per_unit'], 2); ?>" data-available="<?php echo (int)$cons['current_stock']; ?>">
                                    <?php echo htmlspecialchars($cons['name']); ?> (<?php echo (int)$cons['current_stock']; ?> available)
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <input type="number" id="consumableQty" min="1" step="0.1" placeholder="Qty" style="padding: 8px; border: 1px solid #ced4da; border-radius: 4px;">
                        <input type="text" id="consumableCost" placeholder="Unit Cost" readonly style="padding: 8px; border: 1px solid #ced4da; border-radius: 4px; background-color: #f5f5f5;">
                        <button type="button" onclick="addConsumableRow()" style="padding: 8px 15px; background-color: #007bff; color: white; border: none; border-radius: 4px; cursor: pointer; font-weight: 600;">Add</button>
                    </div>
                </div>

                <div id="consumablesTableContainer">
                    <table id="consumablesTable" style="width: 100%; border-collapse: collapse; background: white; border: 1px solid #dee2e6; border-radius: 4px; margin-top: 10px;">
                        <thead>
                            <tr style="background-color: #f8f9fa; border-bottom: 1px solid #dee2e6;">
                                <th style="padding: 10px; text-align: left; font-weight: 600; color: #333;">Consumable Name</th>
                                <th style="padding: 10px; text-align: center; font-weight: 600; color: #333; width: 100px;">Quantity</th>
                                <th style="padding: 10px; text-align: center; font-weight: 600; color: #333; width: 120px;">Unit Cost</th>
                                <th style="padding: 10px; text-align: center; font-weight: 600; color: #333; width: 80px;">Notes</th>
                                <th style="padding: 10px; text-align: center; font-weight: 600; color: #333; width: 50px;">Action</th>
                            </tr>
                        </thead>
                        <tbody id="consumablesTableBody">
                            <?php if (!empty($usedConsumables)): ?>
                                <?php foreach ($usedConsumables as $cons): ?>
                                    <tr style="border-bottom: 1px solid #f0f0f0;">
                                        <td style="padding: 10px;"><?php echo htmlspecialchars($cons['name']); ?></td>
                                        <td style="padding: 10px; text-align: center;">
                                            <input type="number" name="consumables_<?php echo (int)$cons['consumable_id']; ?>" value="<?php echo round((float)$cons['quantity_required'], 2); ?>" min="0.1" step="0.1" style="width: 70px; padding: 5px; border: 1px solid #ced4da; border-radius: 3px; text-align: center;">
                                        </td>
                                        <td style="padding: 10px; text-align: center;">
                                            <input type="text" value="<?php echo htmlspecialchars($cons['unit_cost']); ?>" readonly style="width: 100px; padding: 5px; border: 1px solid #ced4da; border-radius: 3px; background-color: #f5f5f5; text-align: center;">
                                        </td>
                                        <td style="padding: 10px; text-align: center;">
                                            <input type="text" name="consumable_notes_<?php echo (int)$cons['consumable_id']; ?>" value="<?php echo htmlspecialchars($cons['notes'] ?? ''); ?>" placeholder="Notes" style="width: 100%; padding: 5px; border: 1px solid #ced4da; border-radius: 3px; font-size: 12px;">
                                        </td>
                                        <td style="padding: 10px; text-align: center;">
                                            <button type="button" onclick="removeConsumableRow(this)" style="padding: 5px 10px; background-color: #dc3545; color: white; border: none; border-radius: 3px; cursor: pointer; font-size: 12px;">Remove</button>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
                <?php if (empty($usedConsumables)): ?>
                    <p id="noConsumablesMsg" style="color: #999; margin-top: 10px; text-align: center;">No consumables selected yet. Add consumables above.</p>
                <?php else: ?>
                    <p id="noConsumablesMsg" style="color: #999; margin-top: 10px; text-align: center; display: none;">No consumables selected yet. Add consumables above.</p>
                <?php endif; ?>
            </div>
        </div>

        <script>
            let consumablesData = <?php echo json_encode($consumablesList); ?>;
            
            function populateConsumableCost() {
                const select = document.getElementById('consumableSelect');
                const selectedOption = select.options[select.selectedIndex];
                const costField = document.getElementById('consumableCost');
                
                if (select.value) {
                    const cost = selectedOption.getAttribute('data-cost') || '0';
                    costField.value = cost;
                } else {
                    costField.value = '';
                }
            }
            
            function addConsumableRow() {
                const select = document.getElementById('consumableSelect');
                const qtyField = document.getElementById('consumableQty');
                const costField = document.getElementById('consumableCost');
                
                if (!select.value || !qtyField.value) {
                    alert('Please select a consumable and enter a quantity.');
                    return;
                }
                
                const consumableId = parseInt(select.value);
                const selectedOption = select.options[select.selectedIndex];
                const consumableName = selectedOption.textContent.split(' (')[0];
                const qty = parseFloat(qtyField.value);
                const cost = parseFloat(costField.value) || 0;
                
                // Check if already in table
                const existingRow = document.querySelector(`input[name="consumables_${consumableId}"]`);
                if (existingRow) {
                    alert('This consumable is already selected. You can modify its quantity directly in the table.');
                    return;
                }
                
                // Add to table
                const tbody = document.getElementById('consumablesTableBody');
                const row = document.createElement('tr');
                row.style.borderBottom = '1px solid #f0f0f0';
                row.innerHTML = `
                    <td style="padding: 10px;">${consumableName}</td>
                    <td style="padding: 10px; text-align: center;">
                        <input type="number" name="consumables_${consumableId}" value="${qty}" min="0.1" step="0.1" style="width: 70px; padding: 5px; border: 1px solid #ced4da; border-radius: 3px; text-align: center;">
                    </td>
                    <td style="padding: 10px; text-align: center;">
                        <input type="text" value="${cost.toFixed(2)}" readonly style="width: 100px; padding: 5px; border: 1px solid #ced4da; border-radius: 3px; background-color: #f5f5f5; text-align: center;">
                    </td>
                    <td style="padding: 10px; text-align: center;">
                        <input type="text" name="consumable_notes_${consumableId}" placeholder="Notes" style="width: 100%; padding: 5px; border: 1px solid #ced4da; border-radius: 3px; font-size: 12px;">
                    </td>
                    <td style="padding: 10px; text-align: center;">
                        <button type="button" onclick="removeConsumableRow(this)" style="padding: 5px 10px; background-color: #dc3545; color: white; border: none; border-radius: 3px; cursor: pointer; font-size: 12px;">Remove</button>
                    </td>
                `;
                tbody.appendChild(row);
                
                // Hide no consumables message if visible
                document.getElementById('noConsumablesMsg').style.display = 'none';
                
                // Reset form fields
                select.value = '';
                qtyField.value = '';
                costField.value = '';
                select.focus();
            }
            
            function removeConsumableRow(button) {
                button.closest('tr').remove();
                
                // Show no consumables message if table is empty
                const tbody = document.getElementById('consumablesTableBody');
                if (tbody.querySelectorAll('tr').length === 0) {
                    document.getElementById('noConsumablesMsg').style.display = 'block';
                }
            }
            
            document.addEventListener('DOMContentLoaded', function() {
                const consumableSelect = document.getElementById('consumableSelect');
                if (consumableSelect) {
                    consumableSelect.addEventListener('change', populateConsumableCost);
                }
            });
        </script>

        <?php if (empty($isCompleted)): ?>
            <div class="wo-form-buttons">
                <button type="submit">
                    <?php echo $editMode ? '✎ Update Work Order' : '✚ Create Work Order'; ?>
                </button>
                <?php if ($editMode): ?>
                    <a href="index.php?nav=work_orders&status=<?php echo urlencode($statusFilter); ?>">Cancel</a>
                <?php endif; ?>
            </div>
        <?php else: ?>
            <div class="wo-form-buttons">
                <a href="index.php?nav=work_orders&status=<?php echo urlencode($statusFilter); ?>">Back to Work Orders</a>
            </div>
        <?php endif; ?>
        </fieldset>
    </div>
</form>
<?php endif; ?>

<?php if (empty($work_orders)): ?>
    <p>No work orders found in the database.</p>
<?php else: ?>
    <p style="font-weight: bold;">Recent Work Orders: <?php echo count($work_orders); ?></p>
    <table border="1" cellpadding="6" cellspacing="0" style="border-collapse: collapse; width: 100%; margin-top: 12px; font-size: 13px;">
        <tr style="background-color: #f0f0f0;">
            <th>WO ID</th>
            <th>Title</th>
            <th>Equipment</th>
            <th>Technician</th>
            <th>Status</th>
            <th>Priority</th>
            <th>EST Hrs</th>
            <th>Requestor</th>
            <th>Created</th>
            <th>Action</th>
        </tr>
        <?php foreach ($work_orders as $wo): ?>
            <tr style="border-bottom: 1px solid #ddd;">
                <td><a href="view_work_order.php?wo_id=<?php echo (int)$wo['wo_id']; ?>" style="color: blue; text-decoration: underline;">WO #<?php echo (int)$wo['wo_id']; ?></a></td>
                <td><?php echo htmlspecialchars(substr($wo['descriptive_text'], 0, 30)); ?></td>
                <td><?php echo htmlspecialchars(substr($wo['equipment_name'] ?? '', 0, 20)); ?></td>
                <td><?php echo htmlspecialchars($wo['technician_name'] ?? 'Unassigned'); ?></td>
                <td><?php echo htmlspecialchars($wo['wo_status']); ?></td>
                <td><?php echo (int)($wo['priority'] ?? ''); ?></td>
                <td><?php echo htmlspecialchars($wo['est_hours'] ?? '--'); ?></td>
                <td><?php echo htmlspecialchars($wo['requestor'] ?? ''); ?></td>
                <td><?php echo htmlspecialchars($wo['submit_date'] ?? ''); ?></td>
                <td>
                    <?php if ($wo['wo_status'] !== 'Completed'): ?>
                        <?php if (!$isTechnician): ?>
                            <a href="index.php?nav=work_orders&edit=<?php echo (int)$wo['wo_id']; ?>&status=<?php echo urlencode($statusFilter); ?>">Edit</a>
                            &nbsp;|&nbsp;
                        <?php endif; ?>
                        <a href="index.php?nav=work_orders&complete=<?php echo (int)$wo['wo_id']; ?>&status=<?php echo urlencode($statusFilter); ?>" title="Mark Completed" style="color: green; text-decoration: none; font-weight: 600;">
                            <i class="fas fa-check-circle" aria-hidden="true"></i> Complete
                        </a>
                    <?php else: ?>
                        <span title="Completed"><i class="fas fa-lock" style="color:#6c757d;"></i> Completed</span>
                    <?php endif; ?>
                </td>
            </tr>
        <?php endforeach; ?>
    </table>
<?php endif; ?>

<p style="margin-top: 20px;">
    <a href="index.php?nav=dashboard">Back to Dashboard</a>
</p>