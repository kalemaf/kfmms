<?php
require_once("../config.inc.php");
session_save_path($session_save_path);
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (!isset($_SESSION['user'])) {
    header("Location: ../auth.php");
    exit;
}

require_once("../common.inc.php");
require_once("../libraries/inventory_manager.php");

$title = "Goods Receipt Management";
$action = isset($_GET['action']) ? $_GET['action'] : 'list';
$gr_id = isset($_GET['id']) ? intval($_GET['id']) : null;
$po_id = isset($_GET['po_id']) ? intval($_GET['po_id']) : null;

// Check session user_id exists
if (empty($_SESSION['user_id'])) {
    $_SESSION['error'] = "Session error: User ID not found. Please log in again.";
    header("Location: ../auth.php");
    exit;
}

// Handle GR creation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_gr'])) {
    $po_id = intval($_POST['po_id'] ?? 0);
    $warehouse_location_id = intval($_POST['warehouse_location_id'] ?? 0);
    $received_by_id = intval($_SESSION['user_id']);
    
    $gr_id = create_goods_receipt($po_id, $warehouse_location_id, $received_by_id, $connection);
    
    if ($gr_id) {
        // Add received items
        $items_json = $_POST['receipt_items_json'] ?? '[]';
        $items = json_decode($items_json, true) ?: [];
        
        foreach ($items as $item) {
            add_receipt_item(
                $gr_id,
                intval($item['po_item_id']),
                intval($item['quantity_received']),
                intval($item['part_id']),
                floatval($item['unit_cost']),
                $item['condition'] ?? 'good',
                $connection
            );
        }
        
        $_SESSION['success'] = "Goods Receipt created successfully!";
        header("Location: goods_receipt.php?action=view&id=$gr_id");
        exit;
    } else {
        $_SESSION['error'] = "Failed to create Goods Receipt. Please check: <br>
            - Ensure you have selected a warehouse location<br>
            - Ensure the warehouse location exists<br>
            - Ensure your user account is active<br>
            Check the error log for details.";
    }
}

// Handle GR completion
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['complete_gr'])) {
    $gr_id = intval($_POST['gr_id'] ?? 0);
    $quality_check_by = intval($_SESSION['user_id'] ?? 0);
    
    if (complete_goods_receipt($gr_id, $quality_check_by, $connection)) {
        $_SESSION['success'] = "Goods Receipt completed and inventory updated!";
        header("Location: goods_receipt.php?action=view&id=$gr_id");
        exit;
    } else {
        $_SESSION['error'] = "Failed to complete Goods Receipt.";
    }
}

// Load GR for viewing
$gr = null;
if ($gr_id && ($action === 'view')) {
    $query = "SELECT gr.*, p.username as received_by_name, qc.username as qc_by_name,
                     wl.location_code, w.warehouse_name, po.po_number
              FROM goods_receipts gr
              LEFT JOIN users p ON gr.received_by_id = p.user_id
              LEFT JOIN users qc ON gr.quality_check_by_id = qc.user_id
              LEFT JOIN warehouse_locations wl ON gr.warehouse_location_id = wl.id
              LEFT JOIN warehouses w ON wl.warehouse_id = w.id
              LEFT JOIN purchase_orders po ON gr.po_id = po.id
              WHERE gr.id = $gr_id";
    // Apply tenant filtering
    $query = apply_tenant_filter($query);
    $result = $connection->query($query);
    $gr = $result ? $result->fetch_assoc() : null;
    
    if ($gr) {
        // Get received items
        $items_query = "SELECT gri.*, po_item.quantity_ordered, pm.part_name, pm.part_code
                       FROM goods_receipt_items gri
                       LEFT JOIN purchase_order_items po_item ON gri.po_item_id = po_item.id
                       LEFT JOIN parts_master pm ON gri.part_id = pm.id
                       WHERE gri.gr_id = $gr_id";
        // Apply tenant filtering
        $items_query = apply_tenant_filter($items_query);
        $items_result = $connection->query($items_query);
        $gr['items'] = [];
        if ($items_result) {
            while ($item = $items_result->fetch_assoc()) {
                $gr['items'][] = $item;
            }
        }
    }
}

// Load PO for creation
$po = null;
if ($po_id && $action === 'create') {
    $po = get_purchase_order($po_id, $connection);
}

$warehouses = get_warehouses($connection);
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
        .form-container { background: white; padding: 30px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); margin-bottom: 25px; }
        .form-section { margin-bottom: 30px; }
        .form-section-title { font-weight: 700; color: #333; padding-bottom: 15px; border-bottom: 2px solid #667eea; margin-bottom: 15px; }
        .list-container { background: white; padding: 25px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        .btn-primary { background: #667eea; border: none; }
        .btn-primary:hover { background: #5568d3; }
        .table { font-size: 13px; }
        .table thead { background: #f8f9fa; }
        .table th { font-weight: 700; color: #333; border-top: none; }
        .status-badge { font-size: 11px; padding: 5px 10px; border-radius: 4px; }
        .status-pending { background: #fff3cd; color: #856404; }
        .status-passed { background: #d4edda; color: #155724; }
        .status-failed { background: #f8d7da; color: #721c24; }
        .grid-2 { display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 15px; }
        .form-label { font-weight: 600; color: #333; margin-bottom: 6px; }
        .alert { margin-bottom: 20px; }
        .gr-card { background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); margin-bottom: 20px; border-left: 4px solid #667eea; }
        .gr-card h5 { font-weight: 700; color: #333; margin-bottom: 10px; }
        .header h1, .form-container h2, .form-section-title, .list-container h3 { color: #c0392b !important; }
        .gr-info { font-size: 13px; color: #666; margin-bottom: 5px; }
        .receipt-item-row { background: #f8f9fa; padding: 15px; border-radius: 4px; margin-bottom: 10px; }
        .receipt-summary { background: #f8f9fa; padding: 20px; border-radius: 8px; margin-bottom: 20px; }
        .summary-row { display: flex; justify-content: space-between; margin-bottom: 10px; }
        .summary-row .label { font-weight: 600; color: #333; }
        .summary-row .value { color: #666; }
    </style>
</head>
<body>

<div class="container">
    
    <!-- Header -->
    <div class="header" style="display: flex; justify-content: space-between; align-items: center;">
        <div>
            <h1><i class="fas fa-inbox"></i> Goods Receipt Management</h1>
            <p>Receive and manage incoming goods from purchase orders</p>
        </div>
        <div>
            <a href="../index.php?nav=dashboard" class="btn btn-outline-primary" style="white-space: nowrap;">
                <i class="fas fa-home"></i> Back to Dashboard
            </a>
        </div>
    </div>
    
    <!-- Messages -->
    <?php if (isset($_SESSION['success'])): ?>
        <div class="alert alert-success alert-dismissible fade show">
            <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($_SESSION['success']); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php unset($_SESSION['success']); ?>
    <?php endif; ?>
    
    <?php if (isset($_SESSION['error'])): ?>
        <div class="alert alert-danger alert-dismissible fade show">
            <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($_SESSION['error']); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php unset($_SESSION['error']); ?>
    <?php endif; ?>
    
    <?php if ($action === 'create' && $po): ?>
        
        <!-- Create GR Form -->
        <div class="form-container">
            <h2>Create Goods Receipt for <?php echo htmlspecialchars($po['po_number']); ?></h2>
            
            <form method="POST" onsubmit="prepareSubmission(event)">
                <input type="hidden" name="create_gr" value="1">
                <input type="hidden" name="po_id" value="<?php echo $po['id']; ?>">
                <input type="hidden" id="receiptItemsJson" name="receipt_items_json">
                
                <!-- Receipt Information -->
                <div class="form-section">
                    <div class="form-section-title">Receipt Information</div>
                    <div class="grid-2">
                        <div class="form-group">
                            <label class="form-label">PO Number</label>
                            <input type="text" class="form-control" value="<?php echo htmlspecialchars($po['po_number']); ?>" readonly>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Vendor</label>
                            <input type="text" class="form-control" value="<?php echo htmlspecialchars($po['vendor_name']); ?>" readonly>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Warehouse Location *</label>
                            <select class="form-control" name="warehouse_location_id" required>
                                <option value="">Select Location...</option>
                                <?php
                                foreach ($warehouses as $w) {
                                    $locs = get_warehouse_locations($w['id'], $connection);
                                    foreach ($locs as $loc) {
                                        echo "<option value='" . $loc['id'] . "'>" . htmlspecialchars($w['warehouse_name'] . " - " . $loc['location_code']) . "</option>";
                                    }
                                }
                                ?>
                            </select>
                        </div>
                    </div>
                </div>
                
                <!-- Received Items -->
                <div class="form-section">
                    <div class="form-section-title">Received Items</div>
                    
                    <?php foreach ($po['items'] as $item): ?>
                        <div class="receipt-item-row">
                            <div style="display: grid; grid-template-columns: 1fr 1fr 1fr 1fr; gap: 15px; align-items: end;">
                                <div>
                                    <label class="form-label">Item: <?php echo htmlspecialchars($item['description']); ?></label>
                                </div>
                                <div>
                                    <label class="form-label">Quantity Ordered: <?php echo intval($item['quantity_ordered']); ?></label>
                                </div>
                                <div>
                                    <label class="form-label">Quantity Received *</label>
                                    <input type="number" class="form-control item-qty" min="0" max="<?php echo intval($item['quantity_ordered']); ?>" 
                                           value="<?php echo intval($item['quantity_ordered']); ?>" data-po-item="<?php echo $item['id']; ?>"
                                           data-part="<?php echo $item['part_id']; ?>" data-cost="<?php echo $item['unit_cost']; ?>">
                                </div>
                                <div>
                                    <label class="form-label">Condition</label>
                                    <select class="form-control item-condition" data-po-item="<?php echo $item['id']; ?>">
                                        <option value="good">Good</option>
                                        <option value="damaged">Damaged</option>
                                        <option value="defective">Defective</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
                
                <!-- Receipt Summary -->
                <div class="receipt-summary">
                    <div class="summary-row">
                        <span class="label">Total Items Ordered:</span>
                        <span class="value"><?php echo count($po['items']); ?></span>
                    </div>
                    <div class="summary-row">
                        <span class="label">PO Total:</span>
                        <span class="value">$<?php echo number_format($po['po_total'], 2); ?></span>
                    </div>
                </div>
                
                <!-- Form Actions -->
                <div style="display: flex; gap: 10px; margin-top: 30px;">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-check"></i> Create Goods Receipt
                    </button>
                    <a href="purchase_orders.php" class="btn btn-secondary">
                        <i class="fas fa-times"></i> Cancel
                    </a>
                </div>
            </form>
        </div>
        
    <?php elseif ($action === 'view' && $gr): ?>
        
        <!-- View GR -->
        <div class="form-container">
            <div style="display: flex; justify-content: space-between; align-items: start; margin-bottom: 25px;">
                <div>
                    <h2 style="margin-bottom: 5px;">Goods Receipt #<?php echo htmlspecialchars($gr['gr_number']); ?></h2>
                    <p style="color: #666; margin: 0;">PO: <?php echo htmlspecialchars($gr['po_number']); ?></p>
                </div>
                <span class="status-badge status-<?php echo str_replace(' ', '-', $gr['quality_check_status']); ?>" 
                      style="font-size: 14px; padding: 8px 12px;">
                    <?php echo ucfirst($gr['quality_check_status']); ?>
                </span>
            </div>
            
            <!-- GR Header Info -->
            <div class="grid-2" style="margin-bottom: 25px;">
                <div style="background: #f8f9fa; padding: 15px; border-radius: 4px;">
                    <div style="font-size: 12px; color: #999; text-transform: uppercase; font-weight: 600; margin-bottom: 5px;">Received By</div>
                    <div style="font-size: 16px; font-weight: 700; color: #333;"><?php echo htmlspecialchars($gr['received_by_name'] ?? 'Pending'); ?></div>
                </div>
                <div style="background: #f8f9fa; padding: 15px; border-radius: 4px;">
                    <div style="font-size: 12px; color: #999; text-transform: uppercase; font-weight: 600; margin-bottom: 5px;">Receipt Date</div>
                    <div style="font-size: 16px; font-weight: 700; color: #333;"><?php echo htmlspecialchars($gr['receipt_date'] ? date('M d, Y H:i', strtotime($gr['receipt_date'])) : 'Pending'); ?></div>
                </div>
                <div style="background: #f8f9fa; padding: 15px; border-radius: 4px;">
                    <div style="font-size: 12px; color: #999; text-transform: uppercase; font-weight: 600; margin-bottom: 5px;">Location</div>
                    <div style="font-size: 16px; font-weight: 700; color: #333;"><?php echo htmlspecialchars(trim(($gr['warehouse_name'] ?? '') . ' / ' . ($gr['location_code'] ?? '')) ?: 'Unknown'); ?></div>
                </div>
                <div style="background: #f8f9fa; padding: 15px; border-radius: 4px;">
                    <div style="font-size: 12px; color: #999; text-transform: uppercase; font-weight: 600; margin-bottom: 5px;">Quality Check</div>
                    <div style="font-size: 16px; font-weight: 700; color: #333;"><?php echo htmlspecialchars($gr['qc_by_name'] ?? 'Pending'); ?></div>
                </div>
            </div>
            
            <!-- Received Items -->
            <div class="form-section">
                <div class="form-section-title">Received Items</div>
                <div class="table-responsive">
                    <table class="table table-striped">
                        <thead>
                            <tr>
                                <th>Part Code</th>
                                <th>Part Name</th>
                                <th>Quantity Received</th>
                                <th>Quantity Accepted</th>
                                <th>Unit Cost</th>
                                <th>Receipt Cost</th>
                                <th>Condition</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($gr['items'] as $item): ?>
                                <tr>
                                    <td><strong><?php echo htmlspecialchars($item['part_code'] ?? 'N/A'); ?></strong></td>
                                    <td><?php echo htmlspecialchars($item['part_name'] ?? 'Unknown'); ?></td>
                                    <td><?php echo intval($item['quantity_received']); ?></td>
                                    <td><?php echo intval($item['quantity_accepted']); ?></td>
                                    <td>$<?php echo number_format($item['unit_cost'], 2); ?></td>
                                    <td>$<?php echo number_format($item['received_cost'], 2); ?></td>
                                    <td>
                                        <span class="status-badge status-<?php echo str_replace('/', '-', $item['received_condition']); ?>">
                                            <?php echo ucfirst($item['received_condition']); ?>
                                        </span>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            
            <!-- Actions -->
            <?php if (!$gr['is_complete']): ?>
                <form method="POST" style="display: inline;">
                    <input type="hidden" name="complete_gr" value="1">
                    <input type="hidden" name="gr_id" value="<?php echo $gr['id']; ?>">
                    <button type="submit" class="btn btn-success">
                        <i class="fas fa-check"></i> Complete & Update Inventory
                    </button>
                </form>
            <?php else: ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle"></i> This Goods Receipt has been completed and inventory has been updated.
                </div>
            <?php endif; ?>
            
            <div style="display: flex; gap: 10px; flex-wrap: wrap; margin-top: 15px;">
                <a href="goods_receipt.php" class="btn btn-secondary">
                    <i class="fas fa-arrow-left"></i> Back to List
                </a>
                <a href="../index.php" class="btn btn-outline-primary">
                    <i class="fas fa-home"></i> Back to Dashboard
                </a>
            </div>
        </div>
        
    <?php else: ?>
        
        <!-- GR List -->
        <div class="list-container">
            <h3 style="margin-bottom: 25px;">Goods Receipts</h3>
            
            <?php
            $query = "SELECT gr.*, po.po_number, v.vendor_name
                     FROM goods_receipts gr
                     LEFT JOIN purchase_orders po ON gr.po_id = po.id
                     LEFT JOIN vendors v ON po.vendor_id = v.id
                     ORDER BY gr.receipt_date DESC";
            // Apply tenant filtering
            $query = apply_tenant_filter($query);
            $result = $connection->query($query);
            $grs_list = [];
            if ($result) {
                while ($row = $result->fetch_assoc()) {
                    $grs_list[] = $row;
                }
            }
            ?>
            
            <?php if (count($grs_list) > 0): ?>
                <div class="table-responsive">
                    <table class="table table-striped table-hover">
                        <thead>
                            <tr>
                                <th>GR Number</th>
                                <th>Purchase Order</th>
                                <th>Vendor</th>
                                <th>Received</th>
                                <th>Status</th>
                                <th class="text-end">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($grs_list as $g): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($g['gr_number']); ?></td>
                                    <td><?php echo htmlspecialchars($g['po_number'] ?: 'N/A'); ?></td>
                                    <td><?php echo htmlspecialchars($g['vendor_name'] ?: 'N/A'); ?></td>
                                    <td><?php echo htmlspecialchars($g['receipt_date'] ? date('M d, Y', strtotime($g['receipt_date'])) : 'N/A'); ?></td>
                                    <td><?php echo htmlspecialchars($g['is_complete'] ? 'Completed' : ($g['quality_check_status'] ? ucfirst($g['quality_check_status']) : 'In Progress')); ?></td>
                                    <td class="text-end">
                                        <a href="goods_receipt.php?action=view&id=<?php echo $g['id']; ?>" class="btn btn-sm btn-primary">
                                            <i class="fas fa-eye"></i> View
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div class="alert alert-info">
                    <i class="fas fa-info-circle"></i> No goods receipts found yet.
                </div>
            <?php endif; ?>
        </div>
        
    <?php endif; ?>
    
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

<script>
function prepareSubmission(event) {
    const items = [];
    document.querySelectorAll('.item-qty').forEach(input => {
        const poItemId = parseInt(input.getAttribute('data-po-item'));
        const partId = parseInt(input.getAttribute('data-part'));
        const unitCost = parseFloat(input.getAttribute('data-cost'));
        const qty = parseInt(input.value) || 0;
        
        const conditionSelect = document.querySelector(`.item-condition[data-po-item="${poItemId}"]`);
        const condition = conditionSelect ? conditionSelect.value : 'good';
        
        items.push({
            po_item_id: poItemId,
            part_id: partId,
            quantity_received: qty,
            unit_cost: unitCost,
            condition: condition
        });
    });
    
    document.getElementById('receiptItemsJson').value = JSON.stringify(items);
}
</script>

</body>
</html>
