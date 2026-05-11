<?php
/**
 * Purchase Request Management
 * Handles creation, editing, approval, and management of Purchase Requests (PRs)
 */

include('../config.inc.php');
include_once('../flash.php');
include_once('../libraries/inventory_manager.php');
session_save_path($session_save_path);
session_start();

// Access control
if (!isset($_SESSION['user'])) {
    header('Location: ../index.php');
    exit;
}

$c = $connection;
$user = $_SESSION['user'];
$group = $_SESSION['group'] ?? 'user';
$action = isset($_GET['action']) ? trim($_GET['action']) : 'list';
$pr_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

// Handle Create PR
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_POST['action'] == 'create') {
    try {
        $items = isset($_POST['items']) ? json_decode($_POST['items'], true) : [];
        
        $pr_data = [
            'requestor_id' => 0,
            'request_date' => date('Y-m-d H:i:s'),
            'required_by_date' => $_POST['required_by_date'],
            'status' => 'draft',
            'notes' => $_POST['notes'] ?? ''
        ];
        
        $new_pr = create_purchase_request(
            $pr_data['requestor_id'],
            $items,
            $pr_data['required_by_date'],
            'normal',
            $pr_data['status'],
            $pr_data['notes'],
            $connection
        );
        
        if ($new_pr) {
            header("Location: purchase_requests.php?action=view&id=" . intval($new_pr));
            exit;
        }
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}

// Handle Approve PR
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_POST['action'] == 'approve') {
    try {
        $approved_by_id = 0;  // Default to 0 if no user ID available
        approve_purchase_request($pr_id, $approved_by_id, '', $connection);
        header("Location: purchase_requests.php?action=view&id=" . $pr_id);
        exit;
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}

// Get parts for dropdown
$parts_result = safe_query_all("SELECT id, part_code, part_name, unit_cost, category FROM parts_master WHERE is_active = 1 ORDER BY part_name");
$parts_list = $parts_result;

// Get vendors for dropdown
$vendors_result = safe_query_all("SELECT id, vendor_code, vendor_name FROM vendors WHERE is_active = 1 ORDER BY vendor_name");
$vendors_list = $vendors_result;
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
        .form-section { margin-bottom: 25px; }
        .form-section h5 { font-weight: 700; color: #333; margin-bottom: 15px; font-size: 14px; text-transform: uppercase; border-bottom: 2px solid #667eea; padding-bottom: 10px; }
        .table-container { background: white; padding: 25px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); margin-bottom: 25px; }
        .table { font-size: 13px; margin-bottom: 0; }
        .table thead { background: #f8f9fa; }
        .table th { font-weight: 700; color: #333; border-top: none; }
        .table-hover tbody tr:hover { background: #f8f9fa; }
        .card-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); gap: 20px; margin-bottom: 25px; }
        .pr-card { background: white; border-radius: 8px; padding: 20px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); border-left: 4px solid #667eea; }
        .pr-card:hover { box-shadow: 0 4px 8px rgba(0,0,0,0.15); }
        .pr-card .pr-number { font-weight: 700; color: #333; font-size: 16px; margin-bottom: 10px; }
        .pr-card .pr-date { font-size: 12px; color: #999; }
        .badge-status { font-size: 11px; padding: 5px 10px; }
        .item-row { background: #f8f9fa; border-radius: 6px; padding: 12px; margin-bottom: 10px; }
        .item-remove-btn { cursor: pointer; color: #e74c3c; font-weight: 600; }
        .form-label { font-weight: 600; color: #333; font-size: 13px; }
        .btn-add-item { margin-top: 10px; }
        .alert { margin-bottom: 20px; }
    </style>
</head>
<body>

<div class="container">
    
    <?php if ($action === 'list'):  ?>
        <!-- LIST VIEW -->
        <div class="header">
            <h1><i class="fas fa-list"></i> Purchase Requests</h1>
            <p>Manage all purchase requests and approvals</p>
        </div>
        
        <a href="purchase_requests.php?action=create" class="btn btn-primary mb-4">
            <i class="fas fa-plus"></i> Create New PR
        </a>
        
        <?php
        // Get all PRs
        $pr_result = $connection->query("
            SELECT pr.* 
            FROM purchase_requests pr
            ORDER BY pr.created_at DESC
        ");
        $prs = [];
        while ($row = $pr_result->fetch_assoc()) {
            $prs[] = $row;
        }
        
        if (count($prs) > 0):
        ?>
            <div class="card-grid">
                <?php foreach ($prs as $pr): 
                    $statusColors = [
                        'draft' => 'secondary',
                        'submitted' => 'info',
                        'approved' => 'success',
                        'rejected' => 'danger',
                        'po_created' => 'warning'
                    ];
                    $statusColor = $statusColors[$pr['status']] ?? 'secondary';
                ?>
                    <div class="pr-card">
                        <div class="pr-number">PR-<?php echo str_pad($pr['id'], 5, '0', STR_PAD_LEFT); ?></div>
                        <div class="pr-date"><i class="fas fa-calendar"></i> <?php echo date('M d, Y', strtotime($pr['created_at'])); ?></div>
                        <div style="margin: 10px 0;">
                            <span class="badge bg-<?php echo $statusColor; ?>"><?php echo ucfirst($pr['status']); ?></span>
                        </div>
                        <div style="font-size: 12px; color: #666; margin: 10px 0;">
                            <strong>Requested By:</strong> <?php echo htmlspecialchars($pr['user_name'] ?? 'N/A'); ?><br>
                            <strong>Required By:</strong> <?php echo date('M d, Y', strtotime($pr['required_by_date'])); ?>
                        </div>
                        <div style="margin-top: 15px;">
                            <a href="purchase_requests.php?action=view&id=<?php echo $pr['id']; ?>" class="btn btn-sm btn-primary w-100">
                                <i class="fas fa-eye"></i> View Details
                            </a>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="alert alert-info">
                <i class="fas fa-info-circle"></i> No purchase requests yet. Create one to get started.
            </div>
        <?php endif; ?>
    
    <?php elseif ($action === 'create'): ?>
        <!-- CREATE VIEW -->
        <div class="header">
            <h1><i class="fas fa-plus"></i> Create Purchase Request</h1>
            <p>Create a new purchase request</p>
        </div>
        
        <form class="form-container" method="POST" onsubmit="return validatePRForm();">
            <input type="hidden" name="action" value="create">
            <input type="hidden" id="items_json" name="items" value="[]">
            
            <div class="form-section">
                <h5>Request Information</h5>
                <div class="row">
                    <div class="col-md-6">
                        <label class="form-label">Required By Date *</label>
                        <input type="date" name="required_by_date" id="required_by" class="form-control" required 
                               value="<?php echo date('Y-m-d', strtotime('+7 days')); ?>">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Notes</label>
                        <input type="text" name="notes" class="form-control" placeholder="Additional notes...">
                    </div>
                </div>
            </div>
            
            <div class="form-section">
                <h5>Items to Request</h5>
                
                <div id="items_container"></div>
                
                <button type="button" class="btn btn-secondary btn-add-item" onclick="addItemRow();">
                    <i class="fas fa-plus"></i> Add Item
                </button>
            </div>
            
            <div class="form-section">
                <button type="submit" class="btn btn-primary btn-lg">
                    <i class="fas fa-save"></i> Create Purchase Request
                </button>
                <a href="purchase_requests.php" class="btn btn-secondary btn-lg">
                    <i class="fas fa-times"></i> Cancel
                </a>
            </div>
        </form>
        
        <script>
        const partsData = <?php echo json_encode($parts_list); ?>;
            const vendorsData = <?php echo json_encode($vendors_list); ?>;
        
        function addItemRow() {
            const container = document.getElementById('items_container');
            const rowNum = container.children.length;
            
            let partOptions = '<option value="">-- Select Part --</option>';
            partsData.forEach(p => {
                partOptions += `<option value="${p.id}" data-cost="${p.unit_cost}">${p.part_code} - ${p.part_name}</option>`;
            });
            
                let vendorOptions = '<option value="">-- Select Vendor --</option>';
                vendorsData.forEach(v => {
                    vendorOptions += `<option value="${v.id}">${v.vendor_name}</option>`;
                });
            
            const html = `
                <div class="item-row" id="item-${rowNum}">
                    <div class="row">
                        <div class="col-md-5">
                            <label class="form-label">Part</label>
                            <select class="form-control part-select" onchange="updateItemCost(${rowNum})">
                                ${partOptions}
                            </select>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">Quantity</label>
                            <input type="number" class="form-control item-qty" min="1" value="1">
                        </div>
                            <div class="col-md-2">
                            <label class="form-label">Unit Cost</label>
                            <input type="text" class="form-control item-cost" readonly>
                        </div>
                            <div class="col-md-2">
                                <label class="form-label">Vendor</label>
                                <select class="form-control vendor-select">
                                    ${vendorOptions}
                                </select>
                            </div>
                        <div class="col-md-2">
                            <label class="form-label">&nbsp;</label>
                            <a onclick="removeItemRow(${rowNum})" class="btn btn-danger btn-sm w-100">
                                <i class="fas fa-trash"></i>
                            </a>
                        </div>
                    </div>
                </div>
            `;
            
            container.insertAdjacentHTML('beforeend', html);
        }
        
        function updateItemCost(rowNum) {
            const row = document.getElementById('item-' + rowNum);
            const select = row.querySelector('.part-select');
            const costInput = row.querySelector('.item-cost');
            const option = select.options[select.selectedIndex];
            costInput.value = option.dataset.cost || '';
        }
        
        function removeItemRow(rowNum) {
            const row = document.getElementById('item-' + rowNum);
            if (row) row.remove();
        }
        
        function validatePRForm() {
            const items = [];
            document.querySelectorAll('.item-row').forEach(row => {
                const partId = row.querySelector('.part-select').value;
                const qty = parseInt(row.querySelector('.item-qty').value);
                    const vendorId = row.querySelector('.vendor-select').value;
                
                    if (partId && qty > 0 && vendorId) {
                        items.push({ part_id: partId, quantity: qty, vendor_id: vendorId });
                    } else if (partId && qty > 0 && !vendorId) {
                        alert('Please select a vendor for all items');
                        return false;
                }
            });
            
            if (items.length === 0) {
                alert('Please add at least one item to the PR');
                return false;
            }
            
            document.getElementById('items_json').value = JSON.stringify(items);
            return true;
        }
        
        // Add one empty row by default
        addItemRow();
        </script>
    
    <?php elseif ($action === 'view' && $pr_id > 0): ?>
        <!-- VIEW/EDIT PR -->
        <?php
        $pr = get_purchase_request($pr_id, $connection);
        if (!$pr):
            echo '<div class="alert alert-danger">Purchase Request not found</div>';
        else:
        ?>
        <div class="header">
            <div style="display: flex; justify-content: space-between; align-items: center;">
                <h1><i class="fas fa-file"></i> PR-<?php echo str_pad($pr['id'], 5, '0', STR_PAD_LEFT); ?></h1>
                <span class="badge bg-<?php echo ($pr['status'] == 'approved') ? 'success' : (($pr['status'] == 'draft') ? 'secondary' : 'info'); ?>" style="font-size: 14px; padding: 8px 15px;">
                    <?php echo ucfirst($pr['status']); ?>
                </span>
            </div>
            <p>Created on <?php echo date('M d, Y H:i', strtotime($pr['created_at'])); ?></p>
        </div>
        
        <div class="form-container">
            <div class="row mb-4">
                <div class="col-md-3">
                    <label class="form-label">PR Number</label>
                    <input type="text" class="form-control" value="PR-<?php echo str_pad($pr['id'], 5, '0', STR_PAD_LEFT); ?>" readonly>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Requested By</label>
                    <input type="text" class="form-control" value="<?php echo htmlspecialchars($pr['user_name'] ?? 'N/A'); ?>" readonly>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Created Date</label>
                    <input type="text" class="form-control" value="<?php echo date('M d, Y', strtotime($pr['created_at'])); ?>" readonly>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Required By</label>
                    <input type="text" class="form-control" value="<?php echo date('M d, Y', strtotime($pr['required_by_date'])); ?>" readonly>
                </div>
            </div>
            
            <?php if ($pr['notes']): ?>
            <div class="mb-4">
                <label class="form-label">Notes</label>
                <p style="color: #666; padding: 10px; background: #f8f9fa; border-radius: 4px;">
                    <?php echo htmlspecialchars($pr['notes']); ?>
                </p>
            </div>
            <?php endif; ?>
        </div>
        
        <!-- Items Table -->
        <div class="table-container">
            <h5 style="font-weight: 700; margin-bottom: 20px;">
                <i class="fas fa-list"></i> Requested Items
            </h5>
            <div class="table-responsive">
                <table class="table table-striped table-hover">
                    <thead>
                        <tr>
                            <th>Part Code</th>
                            <th>Part Name</th>
                            <th>Qty</th>
                            <th>UOM</th>
                            <th>Unit Cost</th>
                            <th>Total</th>
                            <th>Category</th>
                            <th>Vendor</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($pr['items'] as $item): ?>
                            <tr>
                                <td><strong><?php echo htmlspecialchars($item['part_code']); ?></strong></td>
                                <td><?php echo htmlspecialchars($item['part_name']); ?></td>
                                <td><?php echo intval($item['quantity']); ?></td>
                                <td><?php echo htmlspecialchars($item['unit_of_measure'] ?? 'EA'); ?></td>
                                <td>$<?php echo number_format(floatval($item['estimated_unit_cost'] ?? 0), 2); ?></td>
                                <td>$<?php echo number_format(floatval($item['estimated_total'] ?? ($item['quantity'] * ($item['estimated_unit_cost'] ?? 0))), 2); ?></td>
                                <td><?php echo htmlspecialchars($item['category'] ?? ''); ?></td>
                                <td><?php echo htmlspecialchars($item['vendor_name'] ?? '-'); ?></td>
                                <td>
                                    <span class="badge bg-<?php echo ((intval($item['quantity_ordered'] ?? 0) >= intval($item['quantity'])) ? 'success' : 'warning'); ?>">
                                        <?php echo intval($item['quantity_ordered'] ?? 0); ?>/<?php echo intval($item['quantity']); ?> ordered
                                    </span>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        
        <?php if ($pr['status'] == 'draft'): ?>
        <!-- Actions -->
        <div class="form-container">
            <form method="POST" style="display: inline;">
                <input type="hidden" name="action" value="approve">
                <button type="submit" class="btn btn-success btn-lg">
                    <i class="fas fa-check"></i> Approve PR
                </button>
            </form>
            <a href="purchase_requests.php" class="btn btn-secondary btn-lg">
                <i class="fas fa-arrow-left"></i> Back
            </a>
        </div>
        <?php else: ?>
        <div class="form-container">
            <a href="purchase_requests.php" class="btn btn-secondary btn-lg">
                <i class="fas fa-arrow-left"></i> Back to List
            </a>
        </div>
        <?php endif; ?>
        
        <?php endif; ?>
    
    <?php else: ?>
        <div class="alert alert-warning">
            <i class="fas fa-exclamation-triangle"></i> Invalid action or purchase request not found.
        </div>
        <a href="purchase_requests.php" class="btn btn-primary">Back to List</a>
    <?php endif; ?>
    
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

</body>
</html>
