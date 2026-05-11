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

$user_role = strtolower($_SESSION['role'] ?? '');
$approver_roles = ['admin', 'maintenance manager', 'supervisor', 'manager'];

$title = "Purchase Order Management";
$action = isset($_GET['action']) ? $_GET['action'] : 'list';
$po_id = isset($_GET['id']) ? intval($_GET['id']) : null;

// Load PO for viewing/editing
$po = null;
if ($po_id && ($action === 'view' || $action === 'edit')) {
    $po = get_purchase_order($po_id, $connection);
    if (!$po) {
        $_SESSION['error'] = "Purchase Order not found.";
        header("Location: purchase_orders.php");
        exit;
    }

    if (!empty($po['notes'])) {
        if (empty($po['delivery_address']) && preg_match('/Delivery Address:\s*(.+)/i', $po['notes'], $matches)) {
            $po['delivery_address'] = trim($matches[1]);
        }
        if (empty($po['shipping_method']) && preg_match('/Shipping Method:\s*(.+)/i', $po['notes'], $matches)) {
            $po['shipping_method'] = trim($matches[1]);
        }
    }
}

// Handle PO creation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_po'])) {
    $vendor_id = intval($_POST['vendor_id'] ?? 0);
    $items_json = $_POST['po_items_json'] ?? '[]';
    $items = json_decode($items_json, true) ?: [];
    $required_by = $_POST['required_by_date'] ?? null;
    $expected_delivery_date = $_POST['expected_delivery_date'] ?? null;
    $work_order_ref = trim($_POST['work_order_ref'] ?? '');
    $project_code = trim($_POST['project_code'] ?? '');
    $cost_center = trim($_POST['cost_center'] ?? '');
    $asset_id = trim($_POST['asset_id'] ?? '');
    $delivery_address = trim($_POST['delivery_address'] ?? '');
    $shipping_method = trim($_POST['shipping_method'] ?? '');
    $notes = trim($_POST['notes'] ?? '');
    $ordered_by_id = intval($_SESSION['user'] ?? 0);
    $pr_id = intval($_POST['source_pr_id'] ?? 0);

    if ($vendor_id && count($items) > 0) {
        $metadata = [
            'expected_delivery_date' => $expected_delivery_date,
            'work_order_ref' => $work_order_ref,
            'project_code' => $project_code,
            'cost_center' => $cost_center,
            'asset_id' => $asset_id,
            'delivery_address' => $delivery_address,
            'shipping_method' => $shipping_method,
            'notes' => $notes,
        ];

        $po_id = create_purchase_order($vendor_id, $pr_id ?: null, $items, $ordered_by_id, $required_by, $metadata, $connection);
        if ($po_id) {
            $_SESSION['success'] = "Purchase Order created successfully!";
            header("Location: purchase_orders.php?action=view&id=$po_id");
            exit;
        } else {
            $_SESSION['error'] = "Failed to create Purchase Order.";
        }
    } else {
        $_SESSION['error'] = "Please select a vendor and add items.";
    }
}

// Handle PO approval
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['approve_po'])) {
    $po_id = intval($_POST['po_id'] ?? 0);
    $approved_by_id = $_SESSION['user'] ?? 'system';

    if (!in_array($user_role, $approver_roles, true)) {
        $_SESSION['error'] = "You are not authorized to approve Purchase Orders.";
        send_permission_request_notification(
            'Purchase order approval attempt',
            'User attempted to approve a purchase order without approval permissions.',
            [
                'user_id' => $_SESSION['user_id'] ?? 0,
                'username' => $_SESSION['user'] ?? '',
                'role' => $user_role,
                'purchase_order_id' => $po_id
            ]
        );
        header("Location: purchase_orders.php?action=view&id=$po_id");
        exit;
    }
    
    if (approve_purchase_order($po_id, $approved_by_id, $connection)) {
        $_SESSION['success'] = "Purchase Order approved!";
        header("Location: purchase_orders.php?action=view&id=$po_id");
        exit;
    } else {
        $_SESSION['error'] = "Failed to approve Purchase Order.";
    }
}

$vendors = get_vendors($connection, true);

// Get tenant_id for filtering
$tenant_id = (int)($_SESSION['tenant_id'] ?? 1);

// Get parts filtered by tenant_id
$parts_result = $connection->query("SELECT id, part_code, part_name, unit_cost, unit_of_measure FROM parts_master WHERE is_active=1 AND tenant_id = $tenant_id ORDER BY part_name");
$parts_list = [];
while ($row = $parts_result->fetch_assoc()) {
    $parts_list[] = $row;
}

// Get work orders filtered by tenant_id
$work_orders_list = query_to_array("SELECT wo_id, descriptive_text FROM work_orders WHERE tenant_id = $tenant_id ORDER BY submit_date DESC LIMIT 50");

$from_pr = intval($_GET['from_pr'] ?? 0);
$initial_vendor_id = '';
$initial_required_by = date('Y-m-d', strtotime('+7 days'));
$initial_expected_delivery_date = '';
$initial_work_order_ref = '';
$initial_project_code = '';
$initial_cost_center = '';
$initial_asset_id = '';
$initial_delivery_address = '';
$initial_shipping_method = '';
$initial_notes = '';
$initial_items = [];

if ($from_pr > 0) {
    $source_pr = get_purchase_request($from_pr, $connection);
    if ($source_pr) {
        $initial_required_by = $source_pr['required_by_date'] ?? $initial_required_by;
        $initial_work_order_ref = trim(preg_replace('/Request Type:.*/s','', $source_pr['notes'] ?? ''));
        $initial_project_code = '';
        $initial_cost_center = '';
        $initial_asset_id = '';
        $initial_delivery_address = '';
        $initial_shipping_method = '';
        $initial_notes = $source_pr['notes'] ?? '';
        foreach ($source_pr['items'] as $it) {
            $initial_items[] = [
                'part_id' => $it['part_id'],
                'description' => $it['part_name'] ?? $it['description'] ?? '',
                'quantity' => $it['quantity'],
                'unit_cost' => $it['estimated_unit_cost'] ?? 0,
                'unit_of_measure' => $it['unit_of_measure'] ?? 'EA'
            ];
            if (empty($initial_vendor_id) && !empty($it['vendor_id'])) {
                $initial_vendor_id = $it['vendor_id'];
            }
        }
    }
}

// Get equipment filtered by tenant_id
$equipment_list = [];
$asset_is_in_list = false;
$equipment_result = $connection->query("SELECT id, description FROM equipment WHERE tenant_id = $tenant_id ORDER BY description");
if ($equipment_result) {
    while ($eq = $equipment_result->fetch_assoc()) {
        $equipment_list[] = $eq;
        if ((string)$initial_asset_id === (string)$eq['id']) {
            $asset_is_in_list = true;
        }
    }
}
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
        .po-card { background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); margin-bottom: 20px; border-left: 4px solid #667eea; }
        .po-card h5 { font-weight: 700; color: #333; margin-bottom: 10px; }
        .po-info { font-size: 13px; color: #666; margin-bottom: 5px; }
        .btn-primary { background: #667eea; border: none; }
        .btn-primary:hover { background: #5568d3; }
        .table { font-size: 13px; }
        .table thead { background: #f8f9fa; }
        .table th { font-weight: 700; color: #333; border-top: none; }
        .status-badge { font-size: 11px; padding: 5px 10px; border-radius: 4px; }
        .status-draft { background: #d4d4d4; color: #333; }
        .status-submitted { background: #fff3cd; color: #856404; }
        .status-confirmed { background: #cce5ff; color: #004085; }
        .status-received { background: #d4edda; color: #155724; }
        .grid-2 { display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 15px; }
        .form-label { font-weight: 600; color: #333; margin-bottom: 6px; }
        .alert { margin-bottom: 20px; }
        .item-row { display: grid; grid-template-columns: 2fr 1fr 1fr 1fr auto; gap: 10px; margin-bottom: 10px; align-items: end; }
        .item-row input, .item-row select { padding: 6px 8px; border: 1px solid #ddd; border-radius: 4px; font-size: 12px; }
        .po-summary { background: #f8f9fa; padding: 20px; border-radius: 8px; margin-bottom: 20px; }
        .summary-row { display: flex; justify-content: space-between; margin-bottom: 10px; }
        .summary-row .label { font-weight: 600; color: #333; }
        .summary-row .value { color: #666; }
        .summary-row.total { border-top: 2px solid #ddd; padding-top: 10px; margin-top: 10px; }
        .summary-row.total .label { color: #667eea; font-weight: 700; }
        .summary-row.total .value { color: #667eea; font-weight: 700; font-size: 18px; }

        @media print {
            @page { size: A4 portrait; margin: 8mm; }
            html, body { background: white; color: #000; font-size: 9px !important; line-height: 1.1 !important; }
            .container { max-width: 100%; width: 100%; margin: 0; padding: 0; }
            .header, .btn, button, .btn-close, .alert, .form-control, .form-section-title, .po-summary, .receipt-summary { display: none !important; }
            .form-container, .list-container, .po-card, .po-summary, .receipt-summary { box-shadow: none !important; border: none !important; background: white !important; margin: 0 !important; padding: 5px !important; }
            .table { font-size: 8.5px !important; border-collapse: collapse !important; }
            .table th, .table td { padding: 2px 4px !important; border: 1px solid #ccc !important; }
            .table thead { background: #f8f9fa !important; }
            .table th { font-weight: 700 !important; }
            .grid-2 { grid-template-columns: 1fr 1fr !important; gap: 5px !important; }
            .po-info { font-size: 9px !important; margin-bottom: 3px !important; }
            .status-badge { font-size: 8px !important; padding: 2px 5px !important; }
            img { max-height: 60px !important; max-width: 100px !important; height: auto !important; }
            div[style*="background: #e8f4f8"] { background: #ffffff !important; border: 2px solid #667eea !important; padding: 10px !important; margin-bottom: 5px !important; }
            div[style*="📍 Delivery"] { font-size: 10px !important; font-weight: 700 !important; color: #667eea !important; }
            
            .item-row { gap: 4px !important; grid-template-columns: 1.5fr 0.7fr 0.6fr 0.6fr auto !important; }
            .item-row input, .item-row select { padding: 3px 4px !important; font-size: 8.5px !important; }
            .summary-row { display: flex !important; justify-content: space-between !important; font-size: 9px !important; margin-bottom: 2px !important; }
            .summary-row.total { border-top: 1px solid #ccc !important; padding-top: 5px !important; margin-top: 5px !important; }
            .summary-row.total .label, .summary-row.total .value { font-size: 10px !important; }
            .form-section { margin-bottom: 5px !important; }
            .page-break { page-break-inside: avoid !important; }
            .no-print { display: none !important; }
            body { zoom: 0.95; }
        }
    </style>
</head>
<body>

<div class="container">
    
    <!-- Header -->
    <div class="header">
        <h1><i class="fas fa-receipt"></i> Purchase Order Management</h1>
        <p>Create and manage purchase orders for vendors</p>
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
    
    <?php if ($action === 'create'): ?>
        
        <!-- Create PO Form -->
        <div class="form-container">
            <h2>Create Purchase Order</h2>
            
            <form method="POST" onsubmit="prepareSubmission(event)">
                <input type="hidden" name="create_po" value="1">
                <input type="hidden" id="poItemsJson" name="po_items_json">
                
                <!-- Vendor Selection -->
                <div class="form-section">
                    <div class="form-section-title">Vendor & Purchase Request Data</div>
                    <div class="grid-2">
                        <div class="form-group">
                            <label class="form-label">Vendor *</label>
                            <select class="form-control" id="vendorSelect" name="vendor_id" required onchange="updateVendorInfo()">
                                <option value="">Select Vendor...</option>
                                <?php foreach ($vendors as $v): ?>
                                    <option value="<?php echo $v['id']; ?>" <?php echo ($initial_vendor_id == $v['id'] ? 'selected' : ''); ?>><?php echo htmlspecialchars($v['vendor_name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Required by Date *</label>
                            <input type="date" class="form-control" name="required_by_date" required value="<?php echo htmlspecialchars($initial_required_by); ?>">
                        </div>
                    </div>
                    <div class="grid-2">
                        <div class="form-group">
                            <label class="form-label">Work Order Reference</label>
                            <select class="form-control" name="work_order_ref">
                                <option value="">Choose work order</option>
                                <?php foreach ($work_orders_list as $wo): ?>
                                    <option value="<?php echo htmlspecialchars($wo['wo_id']); ?>" <?php echo ($initial_work_order_ref == $wo['wo_id'] ? 'selected' : ''); ?>>
                                        <?php echo htmlspecialchars('WO #' . $wo['wo_id'] . ' — ' . $wo['descriptive_text']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Project Code</label>
                            <input type="text" class="form-control" name="project_code" value="<?php echo htmlspecialchars($initial_project_code); ?>" placeholder="CapEx-2026-01">
                        </div>
                        <div class="form-group">
                            <label class="form-label">Cost Center</label>
                            <input type="text" class="form-control" name="cost_center" value="<?php echo htmlspecialchars($initial_cost_center); ?>" placeholder="BUD-001">
                        </div>
                        <div class="form-group">
                            <label class="form-label">Asset / Equipment ID</label>
                            <select class="form-control" name="asset_id">
                                <option value="">Select equipment...</option>
                                <?php foreach ($equipment_list as $equip): ?>
                                    <option value="<?php echo $equip['id']; ?>" <?php echo ((string)$initial_asset_id === (string)$equip['id'] ? 'selected' : ''); ?>>
                                        <?php echo htmlspecialchars($equip['id'] . ' - ' . $equip['description']); ?>
                                    </option>
                                <?php endforeach; ?>
                                <?php if ($initial_asset_id !== '' && !$asset_is_in_list): ?>
                                    <option value="<?php echo htmlspecialchars($initial_asset_id); ?>" selected>Other: <?php echo htmlspecialchars($initial_asset_id); ?></option>
                                <?php endif; ?>
                            </select>
                        </div>
                    </div>
                    <div class="form-group" style="margin-top: 15px;">
                        <label class="form-label">Delivery Address</label>
                        <input type="text" class="form-control" name="delivery_address" value="<?php echo htmlspecialchars($initial_delivery_address); ?>" placeholder="Warehouse A, Factory 1">
                    </div>
                    <div class="grid-2">
                        <div class="form-group">
                            <label class="form-label">Expected Delivery Date</label>
                            <input type="date" class="form-control" name="expected_delivery_date" value="<?php echo htmlspecialchars($initial_expected_delivery_date); ?>">
                        </div>
                        <div class="form-group">
                            <label class="form-label">Shipping Method</label>
                            <input type="text" class="form-control" name="shipping_method" value="<?php echo htmlspecialchars($initial_shipping_method); ?>" placeholder="Standard / Express">
                        </div>
                    </div>
                    <input type="hidden" name="source_pr_id" value="<?php echo $from_pr ? intval($from_pr) : ''; ?>">
                    <div class="form-group" style="margin-top: 15px;">
                        <label class="form-label">Additional Notes</label>
                        <textarea class="form-control" name="notes" rows="3"><?php echo htmlspecialchars($initial_notes); ?></textarea>
                    </div>
                </div>
                    
                    <div id="vendorInfo" style="background: #f8f9fa; padding: 15px; border-radius: 4px; margin-top: 15px; display: none;">
                        <div class="row">
                            <div class="col-md-6">
                                <div><strong>Lead Time:</strong> <span id="vendorLeadTime">—</span> days</div>
                                <div><strong>Payment Terms:</strong> <span id="vendorPaymentTerms">—</span></div>
                            </div>
                            <div class="col-md-6">
                                <div><strong>Rating:</strong> <span id="vendorRating">—</span></div>
                                <div><strong>Total Orders:</strong> <span id="vendorTotalOrders">—</span></div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- PO Items -->
                <div class="form-section">
                    <div class="form-section-title">Purchase Order Items</div>
                    
                    <div id="itemsList" style="margin-bottom: 20px;"></div>
                    
                    <button type="button" class="btn btn-sm btn-secondary" onclick="addItemRow()">
                        <i class="fas fa-plus"></i> Add Item
                    </button>
                </div>
                
                <!-- Summary -->
                <div class="po-summary" id="poSummary" style="display: none;">
                    <div class="summary-row">
                        <span class="label">Subtotal:</span>
                        <span class="value">$<span id="subtotalAmount">0.00</span></span>
                    </div>
                    <div class="summary-row">
                        <span class="label">VAT (18%):</span>
                        <span class="value">$<span id="taxAmount">0.00</span></span>
                    </div>
                    <div class="summary-row total">
                        <span class="label">PO Total:</span>
                        <span class="value">$<span id="totalAmount">0.00</span></span>
                    </div>
                </div>
                
                <!-- Form Actions -->
                <div style="display: flex; gap: 10px; margin-top: 30px;">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save"></i> Create Purchase Order
                    </button>
                    <a href="purchase_orders.php" class="btn btn-secondary">
                        <i class="fas fa-times"></i> Cancel
                    </a>
                </div>
            </form>
        </div>
        
    <?php elseif ($action === 'view' && $po): ?>
        
        <!-- View PO -->
        <div class="form-container">
            <!-- Header with Logo and PO Title -->
            <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 30px; position: relative;">
                <div>
                    <h2 style="margin-bottom: 5px;">Purchase Order #<?php echo htmlspecialchars($po['po_number']); ?></h2>
                    <p style="color: #666; margin: 0;">Created: <?php echo date('M d, Y', strtotime($po['po_date'])); ?></p>
                </div>
                
                <!-- Company Branding (Logo + Slogan) - Upper Right Corner -->
                <div style="text-align: right; display: flex; flex-direction: column; align-items: flex-end; gap: 8px;">
                    <img src="../images/kimage.png" alt="KFMMS Logo" style="height: 80px; width: auto; max-width: 150px; object-fit: contain;">
                    <div style="font-size: 11px; color: #667eea; font-weight: 600; letter-spacing: 0.5px;">
                        Efficraft Technologies
                    </div>
                </div>
                
                <span class="status-badge status-<?php echo str_replace('_', '-', $po['status']); ?>" style="font-size: 14px; padding: 8px 12px; position: absolute; top: 0; right: 180px;">
                    <?php echo ucfirst(str_replace('_', ' ', $po['status'])); ?>
                </span>
            </div>
            
            <!-- Vendor, Shipping & PO Details -->
            <?php $discountAmount = floatval($po['discount_amount'] ?? $po['discount'] ?? 0); ?>
            <?php $vatAmount = floatval($po['tax_amount'] ?? 0); ?>
            <?php $shippingCost = floatval($po['shipping_cost'] ?? 0); ?>
            <?php $poTotal = floatval($po['po_total'] ?? $po['total_amount'] ?? 0); ?>
            
            <div class="grid-2" style="margin-bottom: 25px;">
                <div style="background: #f8f9fa; padding: 20px; border-radius: 8px;">
                    <div style="font-size: 13px; color: #667eea; text-transform: uppercase; font-weight: 700; margin-bottom: 10px;">Supplier Details</div>
                    <div style="font-size: 16px; font-weight: 700; color: #333; margin-bottom: 8px;"><?php echo htmlspecialchars($po['vendor_name']); ?></div>
                    <div style="font-size: 13px; color: #444; margin-bottom: 5px;"><strong>Contact:</strong> <?php echo htmlspecialchars($po['vendor_contact_person'] ?? 'N/A'); ?></div>
                    <div style="font-size: 13px; color: #444; margin-bottom: 5px;"><strong>Phone:</strong> <?php echo htmlspecialchars($po['vendor_phone'] ?? 'N/A'); ?></div>
                    <div style="font-size: 13px; color: #444; margin-bottom: 5px;"><strong>Email:</strong> <?php echo htmlspecialchars($po['vendor_email'] ?? 'N/A'); ?></div>
                    <div style="font-size: 13px; color: #444;"><strong>Address:</strong><br><?php echo nl2br(htmlspecialchars($po['vendor_address'] ?? 'Not provided')); ?></div>
                </div>
                <div style="background: #e8f4f8; padding: 20px; border-radius: 8px; border-left: 4px solid #667eea;">
                    <div style="font-size: 13px; color: #667eea; text-transform: uppercase; font-weight: 700; margin-bottom: 10px;">📍 Delivery Address</div>
                    <div style="font-size: 14px; font-weight: 700; color: #333; margin-bottom: 8px; line-height: 1.6;">
                        <?php echo nl2br(htmlspecialchars($po['delivery_address'] ?? 'Not specified')); ?>
                    </div>
                    <div style="border-top: 1px solid #ccc; padding-top: 10px; margin-top: 10px;">
                        <div style="font-size: 12px; color: #444; margin-bottom: 5px;"><strong>Shipping Method:</strong> <?php echo htmlspecialchars($po['shipping_method'] ?? 'Standard'); ?></div>
                        <div style="font-size: 12px; color: #444; margin-bottom: 5px;"><strong>Required By:</strong> <?php echo htmlspecialchars($po['required_by_date'] ? date('M d, Y', strtotime($po['required_by_date'])) : 'N/A'); ?></div>
                        <div style="font-size: 12px; color: #444;"><strong>Expected Delivery:</strong> <?php echo htmlspecialchars($po['expected_delivery_date'] ? date('M d, Y', strtotime($po['expected_delivery_date'])) : 'Not set'); ?></div>
                    </div>
                </div>
            </div>
            
            <div class="grid-2" style="margin-bottom: 25px;">
                <div style="background: #f8f9fa; padding: 20px; border-radius: 8px;">
                    <div style="font-size: 13px; color: #667eea; text-transform: uppercase; font-weight: 700; margin-bottom: 10px;">PO Summary</div>
                    <div style="font-size: 13px; color: #444; margin-bottom: 5px;"><strong>PO Number:</strong> <?php echo htmlspecialchars($po['po_number']); ?></div>
                    <div style="font-size: 13px; color: #444; margin-bottom: 5px;"><strong>Ordered By:</strong> <?php echo htmlspecialchars($po['ordered_by_name'] ?? 'System'); ?></div>
                    <div style="font-size: 13px; color: #444; margin-bottom: 5px;"><strong>Status:</strong> <?php echo ucfirst(str_replace('_', ' ', $po['status'])); ?></div>
                    <div style="font-size: 13px; color: #444;"><strong>Payment Terms:</strong> <?php echo htmlspecialchars($po['payment_terms'] ?? 'Not set'); ?></div>
                </div>
                <div style="background: #f8f9fa; padding: 20px; border-radius: 8px;">
                    <div style="font-size: 13px; color: #667eea; text-transform: uppercase; font-weight: 700; margin-bottom: 10px;">Financial Details</div>
                    <div style="font-size: 13px; color: #444; margin-bottom: 5px;"><strong>Subtotal:</strong> $<?php echo number_format(floatval($po['subtotal'] ?? 0), 2); ?></div>
                    <div style="font-size: 13px; color: #444; margin-bottom: 5px;"><strong>Discount:</strong> $<?php echo number_format($discountAmount, 2); ?></div>
                    <div style="font-size: 13px; color: #444; margin-bottom: 5px;"><strong>VAT:</strong> $<?php echo number_format($vatAmount, 2); ?></div>
                    <div style="font-size: 13px; color: #444; margin-bottom: 5px;"><strong>Shipping:</strong> $<?php echo number_format($shippingCost, 2); ?></div>
                    <div style="font-size: 13px; color: #333; font-weight: 700;"><strong>Total:</strong> $<?php echo number_format($poTotal, 2); ?></div>
                </div>
            </div>

            <!-- Items Table -->
            <div class="form-section">
                <div class="form-section-title">Ordered Items</div>
                <div class="table-responsive">
                    <table class="table table-striped">
                        <thead>
                            <tr>
                                <th>Item Description</th>
                                <th>Qty Ordered</th>
                                <th>Qty Received</th>
                                <th>Unit Cost</th>
                                <th>Line Total</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($po['items'] as $item): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($item['description'] ?? $item['part_name']); ?></td>
                                    <td><?php echo intval($item['quantity_ordered']); ?></td>
                                    <td><?php echo intval($item['quantity_received'] ?? 0); ?></td>
                                    <td>$<?php echo number_format($item['unit_cost'], 2); ?></td>
                                    <td>$<?php echo number_format($item['line_total'], 2); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            
            <!-- Summary -->
            <div class="po-summary">
                <div class="summary-row">
                    <span class="label">Subtotal:</span>
                    <span class="value">$<?php echo number_format(floatval($po['subtotal'] ?? 0), 2); ?></span>
                </div>
                <div class="summary-row">
                    <span class="label">Discount:</span>
                    <span class="value">$<?php echo number_format($discountAmount, 2); ?></span>
                </div>
                <div class="summary-row">
                    <span class="label">VAT:</span>
                    <span class="value">$<?php echo number_format($vatAmount, 2); ?></span>
                </div>
                <div class="summary-row">
                    <span class="label">Shipping:</span>
                    <span class="value">$<?php echo number_format($shippingCost, 2); ?></span>
                </div>
                <div class="summary-row total">
                    <span class="label">PO Total:</span>
                    <span class="value">$<?php echo number_format($poTotal, 2); ?></span>
                </div>
            </div>
            <?php if (!empty($po['notes'])): ?>
            <div class="form-section">
                <div class="form-section-title">Notes</div>
                <div style="background: #f8f9fa; padding: 20px; border-radius: 8px; color: #444; line-height: 1.6;">
                    <?php echo nl2br(htmlspecialchars($po['notes'])); ?>
                </div>
            </div>
            <?php endif; ?>
            
            <!-- Actions -->
            <?php if ($po['status'] === 'draft' || $po['status'] === 'submitted'): ?>
                <form method="POST" style="display: inline;">
                    <input type="hidden" name="approve_po" value="1">
                    <input type="hidden" name="po_id" value="<?php echo $po['id']; ?>">
                    <button type="submit" class="btn btn-success">
                        <i class="fas fa-check"></i> Approve PO
                    </button>
                </form>
            <?php endif; ?>
            
            <?php if ($po['status'] === 'confirmed'): ?>
                <a href="goods_receipt.php?action=create&po_id=<?php echo $po['id']; ?>" class="btn btn-primary">
                    <i class="fas fa-box"></i> Receive Goods
                </a>
            <?php endif; ?>

            <button type="button" class="btn btn-info" onclick="window.print();">
                <i class="fas fa-print"></i> Print PO
            </button>

            <?php if (!empty($po['vendor_email'])): ?>
                <a href="mailto:<?php echo rawurlencode($po['vendor_email']); ?>?subject=<?php echo rawurlencode('Purchase Order ' . $po['po_number']); ?>&body=<?php echo rawurlencode('Please find the purchase order attached or visit the system to view the order details.'); ?>" class="btn btn-warning">
                    <i class="fas fa-envelope"></i> Send to Vendor
                </a>
            <?php endif; ?>
            
            <a href="purchase_orders.php" class="btn btn-secondary">
                <i class="fas fa-arrow-left"></i> Back to List
            </a>
        </div>
        
    <?php else: ?>
        
        <!-- PO List -->
        <div class="list-container">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 25px;">
                <h3 style="margin: 0;">Purchase Orders</h3>
                <a href="purchase_orders.php?action=create" class="btn btn-success">
                    <i class="fas fa-plus"></i> New Purchase Order
                </a>
            </div>
            
            <?php
            $query = "SELECT po.*, v.vendor_name 
                     FROM purchase_orders po
                     LEFT JOIN vendors v ON po.vendor_id = v.id
                     ORDER BY po.po_date DESC";
            $result = safe_query_all($query);
            $pos_list = $result;
            ?>
            
            <?php if (count($pos_list) > 0): ?>
                <div class="grid-2">
                    <?php foreach ($pos_list as $p): ?>
                        <div class="po-card">
                            <div style="display: flex; justify-content: space-between; align-items: start;">
                                <div style="flex: 1;">
                                    <h5>
                                        <i class="fas fa-receipt"></i> <?php echo htmlspecialchars($p['po_number']); ?>
                                    </h5>
                                    <div class="po-info">
                                        <strong>Vendor:</strong> <?php echo htmlspecialchars($p['vendor_name']); ?>
                                    </div>
                                </div>
                                <span class="status-badge status-<?php echo str_replace('_', '-', $p['status']); ?>">
                                    <?php echo ucfirst(str_replace('_', ' ', $p['status'])); ?>
                                </span>
                            </div>
                            
                            <div class="po-info" style="margin-top: 10px;">
                                <strong>Amount:</strong> $<?php echo number_format($p['po_total'], 2); ?>
                            </div>
                            <div class="po-info">
                                <strong>Date:</strong> <?php echo date('M d, Y', strtotime($p['po_date'])); ?>
                            </div>
                            <div class="po-info" style="margin-bottom: 15px;">
                                <strong>Required by:</strong> <?php echo date('M d, Y', strtotime($p['required_by_date'])); ?>
                            </div>
                            
                            <a href="purchase_orders.php?action=view&id=<?php echo $p['id']; ?>" class="btn btn-sm btn-primary">
                                <i class="fas fa-eye"></i> View Details
                            </a>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="alert alert-info">
                    <i class="fas fa-info-circle"></i> No purchase orders found. <a href="purchase_orders.php?action=create">Create your first PO</a>
                </div>
            <?php endif; ?>
        </div>
        
    <?php endif; ?>
    
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

<script>
const vendorData = <?php echo json_encode(array_column($vendors, null, 'id')); ?>;
const partsList = <?php echo json_encode($parts_list); ?>;

function updateVendorInfo() {
    const vendorId = document.getElementById('vendorSelect').value;
    const vendorInfo = document.getElementById('vendorInfo');

    if (vendorId && vendorData[vendorId]) {
        const vendor = vendorData[vendorId];
        document.getElementById('vendorLeadTime').textContent = vendor.lead_time_days || 'N/A';
        document.getElementById('vendorPaymentTerms').textContent = vendor.payment_terms || 'N/A';
        document.getElementById('vendorRating').textContent = (vendor.rating || 0) + '★';
        document.getElementById('vendorTotalOrders').textContent = vendor.total_orders || 0;
        vendorInfo.style.display = 'block';
    } else {
        vendorInfo.style.display = 'none';
    }
}

function onPartChanged(rowId) {
    const row = document.getElementById(rowId);
    const partSelect = row.querySelector('.item-part');
    const selectedPartId = partSelect.value;
    const descriptionInput = row.querySelector('.item-description');
    const costInput = row.querySelector('.item-cost');
    const uomInput = row.querySelector('.item-uom');

    if (!selectedPartId) {
        descriptionInput.value = '';
        costInput.value = '0.00';
        if (uomInput) uomInput.value = 'EA';
        updatePOSummary();
        return;
    }

    const part = partsList.find(p => p.id == selectedPartId);
    if (part) {
        descriptionInput.value = `${part.part_code} - ${part.part_name}`;
        costInput.value = parseFloat(part.unit_cost || 0).toFixed(2);
        if (uomInput) uomInput.value = part.unit_of_measure || 'EA';
        const qtyInput = row.querySelector('.item-qty');
        const totalInput = row.querySelector('.item-total');
        const quantity = parseFloat(qtyInput.value) || 0;
        totalInput.value = '$' + (quantity * parseFloat(costInput.value || 0)).toFixed(2);
        updatePOSummary();
    }
}

function addItemRow(itemData = {}) {
    const itemsList = document.getElementById('itemsList');
    const rowId = 'item_' + Date.now();
    
    const selectedPartId = itemData.part_id || '';
    const description = itemData.description || '';
    const quantity = itemData.quantity || 1;
    const unitCost = parseFloat(itemData.unit_cost || 0).toFixed(2);
    const uom = itemData.unit_of_measure || 'EA';
    const total = parseFloat(quantity) * parseFloat(unitCost);

    const row = document.createElement('div');
    row.id = rowId;
    row.className = 'item-row';
    row.innerHTML = `
        <select class="form-control item-part" onchange="onPartChanged('${rowId}')">
            <option value="">Choose part or service</option>
            ${partsList.map(p => `<option value="${p.id}" data-unit-cost="${parseFloat(p.unit_cost)||0}" data-uom="${p.unit_of_measure || 'EA'}" ${p.id == selectedPartId ? 'selected' : ''}>${p.part_code} - ${p.part_name}</option>`).join('')}
        </select>
        <input type="text" placeholder="Item Description" class="item-description" value="${description}">
        <input type="number" placeholder="Qty" class="item-qty" min="1" value="${quantity}">
        <input type="text" placeholder="UoM" class="item-uom" value="${uom}">
        <input type="number" placeholder="Unit Cost" class="item-cost" min="0" step="0.01" value="${unitCost}">
        <input type="text" placeholder="Total" class="item-total" readonly value="${isNaN(total) ? '$0.00' : '$' + total.toFixed(2)}">
        <button type="button" class="btn btn-sm btn-danger" onclick="removeItemRow('${rowId}')">
            <i class="fas fa-trash"></i>
        </button>
    `;
    
    itemsList.appendChild(row);
    attachItemListeners(rowId);
    updatePOSummary();
}

function removeItemRow(rowId) {
    document.getElementById(rowId).remove();
    updatePOSummary();
}

function attachItemListeners(rowId) {
    const row = document.getElementById(rowId);
    const qtyInput = row.querySelector('.item-qty');
    const costInput = row.querySelector('.item-cost');
    const totalInput = row.querySelector('.item-total');
    
    [qtyInput, costInput].forEach(input => {
        input.addEventListener('change', () => {
            const total = (parseInt(qtyInput.value) || 0) * (parseFloat(costInput.value) || 0);
            totalInput.value = '$' + total.toFixed(2);
            updatePOSummary();
        });
    });
}

function updatePOSummary() {
    const items = document.querySelectorAll('.item-row');
    let subtotal = 0;
    
    items.forEach(row => {
        const qty = parseInt(row.querySelector('.item-qty').value) || 0;
        const cost = parseFloat(row.querySelector('.item-cost').value) || 0;
        subtotal += qty * cost;
    });
    
    const tax = subtotal * 0.18;
    const total = subtotal + tax;
    
    document.getElementById('subtotalAmount').textContent = subtotal.toFixed(2);
    document.getElementById('taxAmount').textContent = tax.toFixed(2);
    document.getElementById('totalAmount').textContent = total.toFixed(2);
    
    if (items.length > 0) {
        document.getElementById('poSummary').style.display = 'block';
    }
}

function prepareSubmission(event) {
    const items = [];
    document.querySelectorAll('.item-row').forEach(row => {
        const partSelect = row.querySelector('.item-part');
        const qty = parseInt(row.querySelector('.item-qty').value) || 0;
        const cost = parseFloat(row.querySelector('.item-cost').value) || 0;
        const uom = row.querySelector('.item-uom') ? row.querySelector('.item-uom').value : 'EA';

        items.push({
            part_id: partSelect ? parseInt(partSelect.value) || null : null,
            description: row.querySelector('.item-description').value,
            quantity: qty,
            unit_cost: cost,
            unit_of_measure: uom
        });
    });
    
    document.getElementById('poItemsJson').value = JSON.stringify(items);
}

// Initialize 
updateVendorInfo();

// Preload initial items when converting from Purchase Request
const initialItems = <?php echo json_encode($initial_items); ?>;
if (Array.isArray(initialItems) && initialItems.length > 0) {
    initialItems.forEach(item => addItemRow(item));
}
</script>

</body>
</html>
