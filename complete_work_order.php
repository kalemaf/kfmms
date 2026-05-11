<?php
/**
 * Work Order Completion Confirmation Page
 * Allows users to select spares used before completing work order
 */

require_once 'config.inc.php';
require_once 'spare_integration_functions.php';
require_once 'common.inc.php';
require_once 'libraries/slaService.php';
require_once 'libraries/repeatFailureService.php';
if (file_exists(__DIR__ . '/libraries/predictive_maintenance.php')) {
    require_once __DIR__ . '/libraries/predictive_maintenance.php';
}
if (file_exists(__DIR__ . '/libraries/predictive_integration.php')) {
    require_once __DIR__ . '/libraries/predictive_integration.php';
}

$message = '';
$wo_id = isset($_GET['wo_id']) && is_numeric($_GET['wo_id']) ? (int)$_GET['wo_id'] : 0;
$statusFilter = trim($_GET['status'] ?? '');

// Get work order details
$workOrder = null;
$equipmentSpares = [];
$generalParts = [];

if ($connection && $wo_id > 0) {
        // Get work order details (filtered by tenant_id)
        $tenant_id = (int)($_SESSION['tenant_id'] ?? 1);
        $workOrder = safe_query_row("SELECT * FROM work_orders WHERE wo_id = {$wo_id} AND tenant_id = {$tenant_id} LIMIT 1");

        if ($workOrder) {
            // Get equipment spares (filtered by tenant_id)
            $equip_id = $workOrder['equipment'];
            if (is_numeric($equip_id)) {
                $equipmentSpares = query_to_array("SELECT id, part_name, part_number, quantity FROM equipment_spares WHERE equipment_id = {$equip_id} AND tenant_id = {$tenant_id} ORDER BY part_name");
            }

            // Get general parts inventory (filtered by tenant_id)
            $generalParts = query_to_array("SELECT id, part_name, part_number, total_on_hand as quantity FROM parts_master WHERE total_on_hand > 0 AND tenant_id = {$tenant_id} ORDER BY part_name LIMIT 20");
        }
    }
// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['confirm_complete']) && $workOrder) {
    try {
        global $app_env, $db_type;
        if (method_exists($connection, 'beginTransaction')) {
            $connection->beginTransaction();
        }

        // For SQLite, temporarily disable foreign key constraints
        if ($db_type === 'sqlite') {
            $connection->exec("PRAGMA foreign_keys=OFF");
        }

        $completeDate = trim($_POST['complete_date'] ?? date('Y-m-d'));
        if ($completeDate === '' || $completeDate === '0000-00-00') {
            $completeDate = date('Y-m-d');
        }

        // Update work order status
        $connection->query("UPDATE work_orders SET wo_status='Completed', complete_date='{$completeDate}', updated=NOW() WHERE wo_id={$wo_id}");

        // ✨ INTEGRATION: Record SLA completion
        if (function_exists('complete_work_order_sla')) {
            try {
                complete_work_order_sla($wo_id);
                error_log("[WO#$wo_id] SLA completion recorded");
            } catch (Exception $e) {
                error_log("[WO#$wo_id] SLA completion error: " . $e->getMessage());
            }
        }

        // ✨ INTEGRATION: Auto-detect repeat failures
        if ($workOrder && function_exists('auto_detect_repeat_failure')) {
            try {
                $asset_id = $workOrder['equipment'] ?? null;
                $failure_mode = $workOrder['failure_mode'] ?? 'General';
                if ($asset_id) {
                    auto_detect_repeat_failure($asset_id, $failure_mode, 30);
                    error_log("[WO#$wo_id] Repeat failure detection completed for asset $asset_id");
                }
            } catch (Exception $e) {
                error_log("[WO#$wo_id] Repeat failure detection error: " . $e->getMessage());
            }
        }

        // ✨ INTEGRATION: Update equipment lifecycle data from completed work order
        if (function_exists('update_equipment_from_workorder')) {
            try {
                update_equipment_from_workorder($wo_id);
                error_log("[WO#$wo_id] ✨ Equipment lifecycle updated and predictive alerts checked");
            } catch (Exception $e) {
                error_log("[WO#$wo_id] ⚠️ Predictive integration error: " . $e->getMessage());
                // Don't fail work order completion due to predictive maintenance issues
            }
        }

        // Process selected spares
        $selectedSpares = [];
        foreach ($_POST as $key => $value) {
            if (strpos($key, 'spare_qty_') === 0 && (int)$value > 0) {
                $spare_id = (int)substr($key, 10);
                $quantity = (int)$value;
                $selectedSpares[$spare_id] = $quantity;
            }
        }

        // Process selected general parts
        $selectedParts = [];
        foreach ($_POST as $key => $value) {
            if (strpos($key, 'part_qty_') === 0 && (int)$value > 0) {
                $part_id = (int)substr($key, 9);
                $quantity = (int)$value;
                $selectedParts[$part_id] = $quantity;
            }
        }

        // Record and reduce selected spares
        $tenant_id = (int)($_SESSION['tenant_id'] ?? 1);
        $spare_reduction_count = 0;
        $spare_error_count = 0;
        
        foreach ($selectedSpares as $spare_id => $quantity) {
            try {
                // Check for existing record to prevent duplicates
                $existing = $connection->query("
                    SELECT COUNT(*) as cnt FROM work_order_spares 
                    WHERE wo_id = {$wo_id} AND spare_id = {$spare_id} AND tenant_id = {$tenant_id}
                ")->fetch_assoc()['cnt'];
                
                if ($existing == 0) {
                    // Only insert if this spare hasn't been recorded yet
                    $connection->query("INSERT INTO work_order_spares (wo_id, spare_id, quantity_used, tenant_id) VALUES ({$wo_id}, {$spare_id}, {$quantity}, {$tenant_id})");
                    error_log("[WO#$wo_id] Recorded spare #$spare_id qty=$quantity tenant=$tenant_id");
                } else {
                    error_log("[WO#$wo_id] ⚠️ Duplicate spare record detected for spare #$spare_id - skipping INSERT");
                }
                
                // Always attempt to reduce inventory
                $reduction_result = @reduce_spare_inventory($spare_id, $quantity, $wo_id, $_SESSION['user_id'] ?? 0, 'Work Order #' . $wo_id, $connection);
                if ($reduction_result === false) {
                    error_log("[WO#$wo_id] ❌ ERROR: Failed to reduce spare #$spare_id qty=$quantity tenant=$tenant_id");
                    $spare_error_count++;
                } else {
                    error_log("[WO#$wo_id] ✅ SUCCESS: Reduced spare #$spare_id qty=$quantity");
                    $spare_reduction_count++;
                }
            } catch (Exception $e) {
                error_log("[WO#$wo_id] Exception reducing spare #$spare_id: " . $e->getMessage());
                $spare_error_count++;
            }
        }
        
        if ($spare_reduction_count > 0) {
            error_log("[WO#$wo_id] Spares reduction summary: $spare_reduction_count succeeded, $spare_error_count failed");
        }

        // Process general parts (similar to wo_parts logic)
        require_once 'libraries/inventory_manager.php';
        foreach ($selectedParts as $part_id => $quantity) {
            $connection->query("INSERT INTO wo_parts (wo_id, part_id, quantity_required, quantity_reserved, status, created_at, updated_at)
                               VALUES ({$wo_id}, {$part_id}, {$quantity}, 0, 'pending', NOW(), NOW())");

            if (issue_stock($wo_id, $part_id, $quantity, $_SESSION['user_id'] ?? 0, $connection)) {
                $connection->query("UPDATE wo_parts SET status='completed', quantity_issued={$quantity}, updated_at=NOW()
                                   WHERE wo_id={$wo_id} AND part_id={$part_id}");
            }
        }

        auto_reduce_spares($workOrder, $connection);

        if (function_exists('consume_work_order_consumables')) {
            consume_work_order_consumables($wo_id, $connection);
        }

        if (method_exists($connection, 'commit')) {
            $connection->commit();
        }

        // Re-enable foreign key constraints
        if ($db_type === 'sqlite') {
            $connection->exec("PRAGMA foreign_keys=ON");
        }

        // Rebuild analytics cache from completed work orders
        if (function_exists('rebuild_lifecycle_analytics')) {
            rebuild_lifecycle_analytics($connection);
        }

        $successMsg = 'Work order #' . $wo_id . ' completed successfully with spare parts tracking.';
        $basePath = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/\\');
        $workOrdersUrl = ($basePath === '' ? '' : $basePath) . '/index.php';
        header('Location: ' . $workOrdersUrl . '?nav=work_orders' . ($statusFilter !== '' ? '&status=' . urlencode($statusFilter) : '') . '&msg=' . urlencode($successMsg));
        exit;
    } catch (Throwable $e) {
        if (method_exists($connection, 'rollBack')) {
            $connection->rollBack();
        }
        // Re-enable foreign key constraints even on error
        if ($db_type === 'sqlite') {
            $connection->exec("PRAGMA foreign_keys=ON");
        }
        error_log('Complete work order failed: ' . $e->getMessage());
        $message = 'Unable to complete the work order. Please contact your system administrator.';
        if (!empty($app_env) && strtolower($app_env) !== 'production') {
            $message .= ' Error: ' . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8');
        }
    }
}

require_once 'title.php';
?>

<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <h1 class="page-title">Complete Work Order</h1>

            <?php if ($message): ?>
                <div class="alert alert-info"><?php echo htmlspecialchars($message); ?></div>
            <?php endif; ?>

            <?php if (!$workOrder): ?>
                <div class="alert alert-danger">Work order not found or already completed.</div>
                <a href="index.php?nav=work_orders" class="btn btn-primary">Back to Work Orders</a>
            <?php else: ?>
                <div class="card">
                    <div class="card-header">
                        <h5>Work Order #<?php echo $wo_id; ?> - Completion Confirmation</h5>
                    </div>
                    <div class="card-body">
                        <form method="post">
                            <!-- Work Order Summary -->
                            <div class="row mb-4">
                                <div class="col-md-6">
                                    <h6>Work Order Details</h6>
                                    <table class="table table-sm">
                                        <tr><td><strong>Description:</strong></td><td><?php echo htmlspecialchars($workOrder['description']); ?></td></tr>
                                        <tr><td><strong>Equipment:</strong></td><td><?php echo htmlspecialchars($workOrder['equipment']); ?></td></tr>
                                        <tr><td><strong>Requestor:</strong></td><td><?php echo htmlspecialchars($workOrder['requestor']); ?></td></tr>
                                        <tr><td><strong>Technician:</strong></td><td><?php echo htmlspecialchars($workOrder['mechanic_id'] ? 'ID: ' . $workOrder['mechanic_id'] : 'Not assigned'); ?></td></tr>
                                        <tr><td><strong>Priority:</strong></td><td><?php echo htmlspecialchars($workOrder['priority']); ?></td></tr>
                                        <tr><td><strong>Status:</strong></td><td><?php echo htmlspecialchars($workOrder['wo_status']); ?></td></tr>
                                    </table>
                                </div>
                                <div class="col-md-6">
                                    <h6>Action Taken</h6>
                                    <p><?php echo nl2br(htmlspecialchars($workOrder['action'] ?? 'No action recorded')); ?></p>

                                    <div class="form-group">
                                        <label for="complete_date">Completion Date:</label>
                                        <input type="date" class="form-control" id="complete_date" name="complete_date"
                                               value="<?php echo date('Y-m-d'); ?>" required>
                                    </div>
                                </div>
                            </div>

                            <!-- Spare Parts Selection -->
                            <div class="row">
                                <div class="col-12">
                                    <h6>Select Spare Parts Used (leave blank if none)</h6>
                                    <p class="text-muted">Select the quantity of each spare part that was used to complete this work order. Inventory will be automatically reduced.</p>

                                    <?php if (!empty($equipmentSpares)): ?>
                                        <div class="mb-4">
                                            <h6>Equipment-Specific Spares</h6>
                                            <div class="row">
                                                <?php foreach ($equipmentSpares as $spare): ?>
                                                    <div class="col-md-6 col-lg-4 mb-3">
                                                        <div class="card">
                                                            <div class="card-body">
                                                                <h6 class="card-title"><?php echo htmlspecialchars($spare['part_name']); ?></h6>
                                                                <p class="card-text small text-muted"><?php echo htmlspecialchars($spare['part_number']); ?></p>
                                                                <p class="card-text">Available: <?php echo $spare['quantity']; ?></p>
                                                                <div class="form-group">
                                                                    <label>Quantity Used:</label>
                                                                    <input type="number" class="form-control" name="spare_qty_<?php echo $spare['id']; ?>"
                                                                           min="0" max="<?php echo $spare['quantity']; ?>" placeholder="0">
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                <?php endforeach; ?>
                                            </div>
                                        </div>
                                    <?php endif; ?>

                                    <?php if (!empty($generalParts)): ?>
                                        <div class="mb-4">
                                            <h6>General Parts Inventory</h6>
                                            <div class="row">
                                                <?php foreach ($generalParts as $part): ?>
                                                    <div class="col-md-6 col-lg-4 mb-3">
                                                        <div class="card">
                                                            <div class="card-body">
                                                                <h6 class="card-title"><?php echo htmlspecialchars($part['part_name']); ?></h6>
                                                                <p class="card-text small text-muted"><?php echo htmlspecialchars($part['part_number']); ?></p>
                                                                <p class="card-text">Available: <?php echo $part['quantity']; ?></p>
                                                                <div class="form-group">
                                                                    <label>Quantity Used:</label>
                                                                    <input type="number" class="form-control" name="part_qty_<?php echo $part['id']; ?>"
                                                                           min="0" max="<?php echo $part['quantity']; ?>" placeholder="0">
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                <?php endforeach; ?>
                                            </div>
                                        </div>
                                    <?php endif; ?>

                                    <div class="alert alert-info">
                                        <strong>Note:</strong> The system will also automatically scan the work order description and action text for spare part mentions.
                                        Any spares detected will be automatically reduced in addition to your manual selections.
                                    </div>
                                </div>
                            </div>

                            <!-- Action Buttons -->
                            <div class="row mt-4">
                                <div class="col-12">
                                    <button type="submit" name="confirm_complete" class="btn btn-success btn-lg">
                                        <i class="fas fa-check"></i> Complete Work Order
                                    </button>
                                    <a href="index.php?nav=work_orders<?php echo $statusFilter !== '' ? '&status=' . urlencode($statusFilter) : ''; ?>"
                                       class="btn btn-secondary btn-lg ml-2">
                                        <i class="fas fa-times"></i> Cancel
                                    </a>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php require_once 'footer.php'; ?>