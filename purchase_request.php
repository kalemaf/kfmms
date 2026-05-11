<?php
/**
 * Purchase Requests Management for CMMS
 * Modern PR workflow page integrated into CMMS navigation.
 */

require_once 'config.inc.php';
require_once 'common.inc.php';
require_once 'libraries/inventory_manager.php';

$current_user = get_current_user_info();
$user_id = intval($current_user['id'] ?? 0);
$user_name = $current_user['username'] ?? 'Unknown';
$user_role = $_SESSION['role'] ?? '';

if ($user_id === 0) {
    echo '<div class="alert alert-warning">Please log in to access Purchase Requests.</div>';
    return;
}

$action = isset($_GET['action']) ? trim($_GET['action']) : 'list';
$pr_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
$message = '';
$error = '';
$created_pr_id = 0;

$approver_roles = ['admin', 'maintenance manager', 'supervisor', 'manager'];
$can_approve = in_array($user_role, $approver_roles, true);

$priority_options = [
    'low' => 'Low',
    'normal' => 'Normal',
    'high' => 'High',
    'critical' => 'Critical'
];

$request_types = [
    'Stock' => 'Stock',
    'Service' => 'Service',
    'Emergency' => 'Emergency',
    'CapEx' => 'CapEx',
    'OpEx' => 'OpEx'
];

$tenant_id = (int)($_SESSION['tenant_id'] ?? 1);

$parts_list = query_to_array("SELECT id, part_code, part_name, unit_cost, category, total_on_hand, reorder_point FROM parts_master WHERE tenant_id = $tenant_id ORDER BY part_name");
$vendors_list = query_to_array("SELECT id, vendor_name FROM vendors WHERE is_active = 1 AND tenant_id = $tenant_id ORDER BY vendor_name");
$work_orders_list = query_to_array("SELECT wo_id, descriptive_text FROM work_orders WHERE tenant_id = $tenant_id ORDER BY submit_date DESC LIMIT 50");
$sites_locations_list = query_to_array("SELECT id, full_location FROM sites_locations WHERE is_active = 1 AND tenant_id = $tenant_id ORDER BY full_location");
$warehouses_list = query_to_array("SELECT id, warehouse_name, warehouse_code FROM warehouses WHERE is_active = 1 AND tenant_id = $tenant_id ORDER BY warehouse_name");

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['form_action'])) {
    $form_action = trim($_POST['form_action']);

    if ($form_action === 'approve' && $pr_id > 0) {
        if (!$can_approve) {
            $error = 'You are not authorized to approve this purchase request.';
            send_permission_request_notification(
                'Purchase request approval attempt',
                'User attempted to approve a purchase request without sufficient approval permissions.',
                [
                    'user_id' => $user_id,
                    'username' => $user_name,
                    'role' => $user_role,
                    'purchase_request_id' => $pr_id
                ]
            );
        } else {
            $approval_notes = trim($_POST['approval_notes'] ?? '');
            if (approve_purchase_request($pr_id, $user_id, $approval_notes, $connection)) {
                $message = 'Purchase request approved successfully.';
                $action = 'view';
            } else {
                $error = 'Unable to approve the purchase request. Please try again.';
            }
        }
    }

    if (in_array($form_action, ['draft', 'submit'], true)) {
        $required_by_date = trim($_POST['required_by_date'] ?? '');
        $priority = trim($_POST['priority'] ?? 'normal');
        $status = $form_action === 'submit' ? 'submitted' : 'draft';
        $request_type = trim($_POST['request_type'] ?? 'Stock');
        $department = trim($_POST['department'] ?? '');
        $cost_center = trim($_POST['cost_center'] ?? '');
        $site_location_id = intval($_POST['site_location_id'] ?? 0);
        $warehouse_id = intval($_POST['warehouse_id'] ?? 0);
        $linked_work_order = trim($_POST['linked_work_order'] ?? '');
        $project_code = trim($_POST['project_code'] ?? '');
        $budget_code = trim($_POST['budget_code'] ?? '');
        $gl_account = trim($_POST['gl_account'] ?? '');
        $expense_type = trim($_POST['expense_type'] ?? 'OpEx');
        $justification = trim($_POST['justification'] ?? '');
        $notes_text = trim($_POST['notes'] ?? '');

        $items = json_decode($_POST['items'] ?? '[]', true);
        $clean_items = [];

        foreach ($items as $item) {
            $part_id = intval($item['part_id'] ?? 0);
            $quantity = intval($item['quantity'] ?? 0);
            $unit_cost = floatval($item['unit_cost'] ?? 0);
            $vendor_id = intval($item['vendor_id'] ?? 0);
            $description = trim($item['description'] ?? '');
            $uom = trim($item['unit_of_measure'] ?? 'EA');
            $required_date = trim($item['required_date'] ?? '');

            // For drafts, save all items even if incomplete
            // For submit, only save complete items
            if ($form_action === 'submit') {
                if ($part_id > 0 && $quantity > 0) {
                    $clean_items[] = [
                        'part_id' => $part_id,
                        'quantity' => $quantity,
                        'unit_cost' => $unit_cost,
                        'vendor_id' => $vendor_id,
                        'description' => $description,
                        'unit_of_measure' => $uom,
                        'required_date' => $required_date
                    ];
                }
            } else {
                // For drafts, save all items
                $clean_items[] = [
                    'part_id' => $part_id,
                    'quantity' => $quantity,
                    'unit_cost' => $unit_cost,
                    'vendor_id' => $vendor_id,
                    'description' => $description,
                    'unit_of_measure' => $uom,
                    'required_date' => $required_date
                ];
            }
        }

        if ($form_action === 'submit') {
            if (count($items) === 0) {
                $error = 'Please add at least one item line before submitting the purchase request.';
            } elseif (count($clean_items) === 0) {
                $error = 'Please complete at least one item with a selected part and quantity before submitting the purchase request.';
            } elseif (empty($required_by_date)) {
                $error = 'Required By Date is mandatory for submission.';
            } elseif (empty($justification)) {
                $error = 'Justification is required for all PR submissions.';
            }
        }

        if (empty($error)) {
            // Get site location name
            $site_location_name = '';
            if ($site_location_id > 0) {
                $row = db_query_row_params(
                    'SELECT full_location FROM sites_locations WHERE id = ?',
                    [$site_location_id]
                );
                if ($row) {
                    $site_location_name = $row['full_location'];
                }
            }
            
            // Get warehouse name
            $warehouse_name = '';
            if ($warehouse_id > 0) {
                $row = db_query_row_params(
                    'SELECT warehouse_name FROM warehouses WHERE id = ?',
                    [$warehouse_id]
                );
                if ($row) {
                    $warehouse_name = $row['warehouse_name'];
                }
            }

            $notes = "Request Type: {$request_type}\n" .
                     "Department: {$department}\n" .
                     "Cost Center: {$cost_center}\n" .
                     "Site / Location: {$site_location_name}\n" .
                     "Warehouse: {$warehouse_name}\n" .
                     "Linked WO: {$linked_work_order}\n" .
                     "Project Code: {$project_code}\n" .
                     "Budget Code: {$budget_code}\n" .
                     "GL Account: {$gl_account}\n" .
                     "Expense Type: {$expense_type}\n\n" .
                     "Justification:\n{$justification}\n\n" .
                     "Additional Notes:\n{$notes_text}";

            $created_pr_id = create_purchase_request(
                $user_id,
                $clean_items,
                $required_by_date,
                $priority,
                $status,
                $notes,
                $department,
                $cost_center,
                $site_location_id,
                $warehouse_id,
                $linked_work_order,
                $project_code,
                $budget_code,
                $gl_account,
                $expense_type,
                $justification,
                $connection
            );

            if ($created_pr_id) {
                $message = $status === 'submitted'
                    ? 'Purchase request submitted successfully and is pending approval.'
                    : 'Purchase request saved as draft successfully.';
                $action = 'view';
                $pr_id = $created_pr_id;
            } else {
                $error = 'Unable to save the purchase request. Please verify the data and try again.';
            }
        }
    }
}

function get_status_badge($status) {
    $map = [
        'draft' => 'secondary',
        'submitted' => 'info',
        'approved' => 'success',
        'rejected' => 'danger',
        'po_created' => 'warning',
        'closed' => 'dark'
    ];
    $status = strtolower($status);
    return $map[$status] ?? 'secondary';
}

if ($action === 'view' && $pr_id > 0) {
    $pr = get_purchase_request($pr_id, $connection);
}
?>

<style>
    .pr-header { background: #ffffff; border-radius: 14px; padding: 24px; margin-bottom: 24px; box-shadow: 0 18px 40px rgba(72,94,144,0.08); }
    .pr-card { background: #ffffff; border-radius: 14px; padding: 22px; box-shadow: 0 12px 28px rgba(58,77,120,0.08); border-left: 6px solid #5b7fff; }
    .badge-status { font-size: 0.78rem; padding: 0.6em 0.85em; border-radius: 999px; }
    .summary-pill { background: #f4f6ff; border-radius: 12px; padding: 12px 16px; color: #2f3f70; }
    .approval-step { display: inline-flex; align-items: center; gap: 10px; margin-right: 14px; margin-bottom: 12px; }
    .approval-step .step-dot { width: 12px; height: 12px; border-radius: 50%; display: inline-block; }
    .step-complete { background: #38c172; }
    .step-pending { background: #d1d5db; }
    .form-section-title { font-size: 14px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.08em; margin-bottom: 14px; color: #364154; }
    .item-row { margin-bottom: 18px; padding: 18px 18px 14px; border: 1px solid #e5e7eb; border-radius: 12px; background: #fbfbff; }
    .item-row .form-label { font-weight: 600; color: #333; }
    .item-row .item-meta { font-size: 12px; color: #6b7280; margin-top: 6px; }
    .form-note { font-size: 13px; color: #6b7280; margin-top: 6px; }
    .summary-box { background: #f5f7ff; border: 1px solid #e2e8f0; border-radius: 14px; padding: 18px; }
    .summary-box strong { color: #1f2937; }
    .btn-primary, .btn-secondary, .btn-success { min-width: 160px; }
</style>

<div class="pr-header">
    <div style="display:flex; flex-wrap:wrap; justify-content:space-between; align-items:center; gap:16px;">
        <div>
            <h2 style="margin-bottom:6px;">Purchase Request Management</h2>
            <p style="margin:0; color:#556073; font-size:0.95rem;">Create, track, and approve purchase requests with structured audit detail.</p>
        </div>
        <div style="text-align:right;">
            <div class="badge badge-status bg-primary" style="font-size:0.85rem;">PR Workflow</div>
            <div style="color:#7b8794; font-size:0.9rem; margin-top:8px;">Logged in as <?php echo htmlspecialchars($user_name); ?> (<?php echo htmlspecialchars($user_role); ?>)</div>
        </div>
    </div>
</div>

<?php if ($message): ?>
    <div class="alert alert-success"><?php echo htmlspecialchars($message); ?></div>
<?php endif; ?>
<?php if ($error): ?>
    <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
<?php endif; ?>

<?php if ($action === 'list'): ?>
    <div style="display:flex; justify-content:space-between; flex-wrap:wrap; gap:12px; margin-bottom:20px;">
        <div>
            <h3 style="margin-bottom:4px;">Open Purchase Requests</h3>
            <p style="margin:0; color:#5f6c80;">Review existing requests and launch a new PR.</p>
        </div>
        <a href="index.php?nav=purchase_requests&action=create" class="btn btn-primary">
            <i class="fas fa-plus"></i> New Purchase Request
        </a>
    </div>

    <?php
    $pr_list = [];
    $query = "SELECT pr.*, u.username AS requestor_name FROM purchase_requests pr LEFT JOIN users u ON pr.requestor_id = u.user_id ORDER BY pr.created_at DESC";
    $result = safe_query_all($query);
    $pr_list = $result;
    ?>

    <?php if (count($pr_list) === 0): ?>
        <div class="alert alert-info">No purchase requests have been created yet. Start by creating a new request.</div>
    <?php else: ?>
        <div class="row g-4">
            <?php foreach ($pr_list as $pr): ?>
                <div class="col-lg-4 col-md-6">
                    <div class="pr-card">
                        <div style="display:flex; justify-content:space-between; align-items:flex-start; gap:10px;">
                            <div>
                                <div style="font-size:0.9rem; color:#6b7280;">PR Number</div>
                                <div style="font-size:1.25rem; font-weight:700; margin-top:4px;"><?php echo htmlspecialchars($pr['pr_number']); ?></div>
                            </div>
                            <span class="badge bg-<?php echo get_status_badge($pr['status']); ?> text-capitalize"><?php echo htmlspecialchars($pr['status']); ?></span>
                        </div>
                        <div style="margin-top:16px; color:#4b5563; font-size:0.95rem;">
                            <div><strong>Requested By:</strong> <?php echo htmlspecialchars($pr['requestor_name'] ?: 'Unknown'); ?></div>
                            <div><strong>Required By:</strong> <?php echo htmlspecialchars(date('M d, Y', strtotime($pr['required_by_date'] ?? 'now'))); ?></div>
                            <div><strong>Total:</strong> $<?php echo number_format(floatval($pr['total_estimated_cost'] ?? 0), 2); ?></div>
                        </div>
                        <div style="margin-top:18px; display:flex; gap:10px; flex-wrap:wrap;">
                            <a href="index.php?nav=purchase_requests&action=view&id=<?php echo $pr['id']; ?>" class="btn btn-sm btn-outline-primary">View Details</a>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

<?php elseif ($action === 'create'): ?>
    <div class="pr-card" style="margin-bottom:24px;">
        <div style="display:flex; justify-content:space-between; flex-wrap:wrap; gap:16px;">
            <div>
                <h3 style="margin-bottom:4px;">New Purchase Request</h3>
                <p style="margin:0; color:#5f6c80;">Capture requester details, item lines, cost summary and submit for approval.</p>
                <?php if (empty($parts_list)): ?>
                    <div class="alert alert-warning mt-2" style="font-size:0.9rem;">
                        <strong>Warning:</strong> No active parts found in the system. You may need to add parts to the inventory before creating purchase requests.
                    </div>
                <?php endif; ?>
            </div>
            <a href="index.php?nav=purchase_requests" class="btn btn-secondary">Back to PR List</a>
        </div>
    </div>

    <form method="POST" id="pr_form" onsubmit="return buildItemsPayload();">
        <input type="hidden" name="form_action" id="form_action" value="draft">
        <input type="hidden" name="items" id="items_data" value="[]">

        <div class="pr-card form-section" style="margin-bottom:24px;">
            <div class="form-section-title">Header & Status</div>
            <div class="row g-3">
                <div class="col-md-3">
                    <label class="form-label">Request Type</label>
                    <select name="request_type" class="form-select">
                        <?php foreach ($request_types as $key => $label): ?>
                            <option value="<?php echo htmlspecialchars($key); ?>"><?php echo htmlspecialchars($label); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Priority</label>
                    <select name="priority" class="form-select">
                        <?php foreach ($priority_options as $value => $label): ?>
                            <option value="<?php echo htmlspecialchars($value); ?>"><?php echo htmlspecialchars($label); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Request Date</label>
                    <input type="text" class="form-control" value="<?php echo date('Y-m-d'); ?>" readonly>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Required By Date</label>
                    <input type="date" class="form-control" name="required_by_date" value="<?php echo date('Y-m-d', strtotime('+7 days')); ?>">
                </div>
            </div>
        </div>

        <div class="pr-card form-section" style="margin-bottom:24px;">
            <div class="form-section-title">Requester & Organizational Info</div>
            <div class="row g-3">
                <div class="col-md-4">
                    <label class="form-label">Requester</label>
                    <input type="text" class="form-control" value="<?php echo htmlspecialchars($user_name); ?>" readonly>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Department / Cost Center</label>
                    <input type="text" name="department" class="form-control" placeholder="Operations, Maintenance, etc.">
                </div>
                <div class="col-md-4">
                    <label class="form-label">Site / Location</label>
                    <select name="site_location_id" class="form-select" required>
                        <option value="">Choose site/location</option>
                        <?php foreach ($sites_locations_list as $location): ?>
                            <option value="<?php echo htmlspecialchars($location['id']); ?>"><?php echo htmlspecialchars($location['full_location']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Warehouse</label>
                    <select name="warehouse_id" class="form-select" required>
                        <option value="">Choose warehouse</option>
                        <?php foreach ($warehouses_list as $warehouse): ?>
                            <option value="<?php echo htmlspecialchars($warehouse['id']); ?>"><?php echo htmlspecialchars($warehouse['warehouse_name'] . ' (' . $warehouse['warehouse_code'] . ')'); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Linked Work Order</label>
                    <select name="linked_work_order" class="form-select">
                        <option value="">Choose work order</option>
                        <?php foreach ($work_orders_list as $wo): ?>
                            <option value="<?php echo htmlspecialchars($wo['wo_id']); ?>"><?php echo htmlspecialchars($wo['wo_id'] . ' — ' . $wo['descriptive_text']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Project Code</label>
                    <input type="text" name="project_code" class="form-control" placeholder="CapEx-2026-01">
                </div>
                <div class="col-md-4">
                    <label class="form-label">Budget / GL Code</label>
                    <input type="text" name="budget_code" class="form-control" placeholder="BUD-001 / 5001">
                </div>
            </div>
        </div>

        <div class="pr-card form-section" style="margin-bottom:24px;">
            <div class="form-section-title">Item / Service Details</div>
            <div id="items_container"></div>
            <button type="button" class="btn btn-outline-primary btn-sm mt-2" onclick="addItemRow();">
                <i class="fas fa-plus"></i> Add Item
            </button>
        </div>

        <div class="row g-4">
            <div class="col-lg-8">
                <div class="pr-card form-section">
                    <div class="form-section-title">Justification & Supporting Notes</div>
                    <div class="mb-3">
                        <label class="form-label">Justification</label>
                        <textarea name="justification" class="form-control" rows="5" placeholder="Why is this purchase needed? What happens if it is not approved?"></textarea>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Additional Notes</label>
                        <textarea name="notes" class="form-control" rows="3" placeholder="Supplier preferences, alternative items, compliance requirements."></textarea>
                    </div>
                </div>
            </div>
            <div class="col-lg-4">
                <div class="summary-box">
                    <h5 style="margin-bottom:14px;">Financial & Approval Snapshot</h5>
                    <div class="mb-3">
                        <label class="form-label">Expense Type</label>
                        <select name="expense_type" class="form-select">
                            <option value="CapEx">CapEx</option>
                            <option value="OpEx" selected>OpEx</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Estimated Total</label>
                        <div id="estimated_total_display" style="font-size:1.35rem; font-weight:700;">$0.00</div>
                    </div>
                    <div class="form-note">The system calculates the estimated value as item quantities are added.</div>
                    <div class="form-note" style="margin-top:14px;">Approval route is based on priority and amount. Submit this PR to trigger workflow assignment.</div>
                </div>
            </div>
        </div>

        <div class="mt-4" style="display:flex; gap:12px; flex-wrap:wrap;">
            <button type="button" class="btn btn-secondary" onclick="setFormAction('draft'); buildItemsPayload(); document.getElementById('pr_form').submit();">Save Draft</button>
            <button type="button" class="btn btn-primary" onclick="setFormAction('submit'); buildItemsPayload(); if (confirm('Submit this purchase request for approval?')) { document.getElementById('pr_form').submit(); }">Submit for Approval</button>
            <a href="index.php?nav=purchase_requests" class="btn btn-outline-secondary">Cancel</a>
        </div>
    </form>

    <script>
        const parts = <?php echo json_encode($parts_list); ?>;
        const vendors = <?php echo json_encode($vendors_list); ?>;
        const itemsContainer = document.getElementById('items_container');
        const itemsDataInput = document.getElementById('items_data');
        const estimatedTotalDisplay = document.getElementById('estimated_total_display');
        const formActionInput = document.getElementById('form_action');
        
        function setFormAction(action) {
            formActionInput.value = action;
        }

        function addItemRow() {
            const index = itemsContainer.children.length;
            const row = document.createElement('div');
            row.className = 'item-row';
            row.id = 'item-row-' + index;
            row.innerHTML = `
                <div class="row g-3 align-items-end">
                    <div class="col-md-4">
                        <label class="form-label">Item / Service</label>
                        <select class="form-select item-part" onchange="updateItemMetadata(${index});">
                            <option value="">Choose part or service</option>
                            ${parts.map(p => `<option value="${p.id}" data-unit-cost="${parseFloat(p.unit_cost)||0}" data-uom="EA" data-stock="${parseInt(p.total_on_hand)||0}" data-reorder="${parseInt(p.reorder_point)||0}">${p.part_code} — ${p.part_name}</option>`).join('')}
                        </select>
                        <div class="item-meta">Current stock: <span class="stock-value">0</span>, Reorder at: <span class="reorder-value">0</span></div>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">Qty</label>
                        <input type="number" class="form-control item-qty" value="1" min="1" onchange="refreshTotals();">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">UOM</label>
                        <input type="text" class="form-control item-uom" value="EA">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">Unit Cost</label>
                        <input type="number" class="form-control item-unit-cost" step="0.01" value="0.00" onchange="refreshTotals();">
                    </div>
                    <div class="col-md-2">
                        <button type="button" class="btn btn-danger w-100" onclick="removeItemRow(${index});">Remove</button>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Preferred Supplier</label>
                        <select class="form-select item-vendor">
                            <option value="">Choose vendor</option>
                            ${vendors.map(v => `<option value="${v.id}">${v.vendor_name}</option>`).join('')}
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Required Date</label>
                        <input type="date" class="form-control item-required-date" value="<?php echo date('Y-m-d', strtotime('+7 days')); ?>">
                    </div>
                </div>
            `;
            itemsContainer.appendChild(row);
            refreshTotals();
        }

        function updateItemMetadata(index) {
            const row = document.getElementById('item-row-' + index);
            const partSelect = row.querySelector('.item-part');
            const selected = partSelect.options[partSelect.selectedIndex];
            const unitCostInput = row.querySelector('.item-unit-cost');
            row.querySelector('.stock-value').textContent = selected.dataset.stock || '0';
            row.querySelector('.reorder-value').textContent = selected.dataset.reorder || '0';
            if (selected.dataset.unitCost) {
                unitCostInput.value = parseFloat(selected.dataset.unitCost).toFixed(2);
            }
            refreshTotals();
        }

        function removeItemRow(index) {
            const container = document.getElementById('items_container');
            if (container.children.length > 1) {
                const row = document.getElementById('item-row-' + index);
                if (row) {
                    row.remove();
                    refreshTotals();
                }
            } else {
                alert('At least one item line is required.');
            }
        }

        function refreshTotals() {
            let total = 0;
            document.querySelectorAll('.item-row').forEach(row => {
                const qty = parseFloat(row.querySelector('.item-qty').value) || 0;
                const cost = parseFloat(row.querySelector('.item-unit-cost').value) || 0;
                total += qty * cost;
            });
            estimatedTotalDisplay.textContent = '$' + total.toFixed(2);
        }

        function buildItemsPayload() {
            const items = [];
            document.querySelectorAll('.item-row').forEach(row => {
                const partId = parseInt(row.querySelector('.item-part').value) || 0;
                const qty = parseInt(row.querySelector('.item-qty').value) || 0;
                const unitCost = parseFloat(row.querySelector('.item-unit-cost').value) || 0;
                const vendorId = parseInt(row.querySelector('.item-vendor').value) || 0;
                const description = row.querySelector('.item-part').selectedOptions[0]?.text || '';
                const uom = row.querySelector('.item-uom').value || 'EA';
                const requiredDate = row.querySelector('.item-required-date').value || '';
                items.push({
                    part_id: partId,
                    quantity: qty,
                    unit_cost: unitCost,
                    vendor_id: vendorId,
                    description: description,
                    unit_of_measure: uom,
                    required_date: requiredDate
                });
            });
            itemsDataInput.value = JSON.stringify(items);
            return true;
        }

        addItemRow();
    </script>

<?php elseif ($action === 'view' && !empty($pr)): ?>
    <div class="pr-card" style="margin-bottom:24px;">
        <div style="display:flex; justify-content:space-between; flex-wrap:wrap; gap:16px;">
            <div>
                <div style="font-size:0.9rem; color:#6b7280;">PR Number</div>
                <h3 style="margin-top:6px; margin-bottom:4px;"><?php echo htmlspecialchars($pr['pr_number']); ?></h3>
                <div style="color:#6b7280;">Created by <?php echo htmlspecialchars($pr['user_name'] ?? $user_name); ?> on <?php echo htmlspecialchars(date('M d, Y', strtotime($pr['created_at'] ?? 'now'))); ?></div>
            </div>
            <div style="text-align:right;">
                <span class="badge bg-<?php echo get_status_badge($pr['status']); ?> text-capitalize" style="font-size:0.95rem; padding:0.8em 1em;"><?php echo htmlspecialchars($pr['status']); ?></span>
                <div style="margin-top:10px; color:#6b7280;">Required by <?php echo htmlspecialchars(date('M d, Y', strtotime($pr['required_by_date'] ?? 'now'))); ?></div>
                <div style="margin-top:12px;">
                    <a href="inventory/purchase_orders.php?action=create&from_pr=<?php echo intval($pr_id); ?>" class="btn btn-warning btn-sm" style="margin-top:8px;">Create PO from PR</a>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-4">
        <div class="col-lg-8">
            <div class="pr-card mb-4">
                <div class="row g-3">
                    <div class="col-md-6">
                        <strong>Priority</strong>
                        <div><?php echo htmlspecialchars(ucfirst($pr['priority'])); ?></div>
                    </div>
                    <div class="col-md-6">
                        <strong>Total Estimated Value</strong>
                        <div>$<?php echo number_format(floatval($pr['total_estimated_cost'] ?? 0), 2); ?></div>
                    </div>
                    <div class="col-md-6">
                        <strong>Approval Date</strong>
                        <div><?php echo htmlspecialchars($pr['approval_date'] ?? 'Pending'); ?></div>
                    </div>
                    <div class="col-md-6">
                        <strong>Approval Notes</strong>
                        <div><?php echo nl2br(htmlspecialchars($pr['approval_notes'] ?? 'None')); ?></div>
                    </div>
                </div>
            </div>

            <div class="pr-card mb-4">
                <h5 style="margin-bottom:16px;">Requested Items</h5>
                <div class="table-responsive">
                    <table class="table table-hover align-middle">
                        <thead>
                            <tr>
                                <th>Item</th>
                                <th>Qty</th>
                                <th>UOM</th>
                                <th>Unit Cost</th>
                                <th>Total</th>
                                <th>Vendor</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($pr['items'] as $item): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($item['part_code'] ?? $item['part_name'] ?? $item['description'] ?? 'Unknown'); ?></td>
                                <td><?php echo intval($item['quantity'] ?? $item['quantity_requested'] ?? 0); ?></td>
                                <td><?php echo htmlspecialchars($item['unit_of_measure'] ?? 'EA'); ?></td>
                                <td>$<?php echo number_format(floatval($item['estimated_unit_cost'] ?? 0), 2); ?></td>
                                <td>$<?php echo number_format(floatval($item['estimated_total'] ?? $item['estimated_total_cost'] ?? 0), 2); ?></td>
                                <td><?php echo htmlspecialchars($item['vendor_name'] ?? 'N/A'); ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="pr-card mb-4">
                <h5 style="margin-bottom:16px;">Justification & Audit Trail</h5>
                <div style="white-space:pre-wrap; color:#374151; line-height:1.6;"><?php echo htmlspecialchars($pr['notes'] ?? 'No additional details provided.'); ?></div>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="summary-box mb-4">
                <h5>Status Timeline</h5>
                <div class="approval-step"><span class="step-dot <?php echo in_array($pr['status'], ['draft','submitted','approved','rejected','po_created','closed']) ? 'step-complete' : 'step-pending'; ?>"></span> Draft created</div>
                <div class="approval-step"><span class="step-dot <?php echo in_array($pr['status'], ['submitted','approved','rejected','po_created','closed']) ? 'step-complete' : 'step-pending'; ?>"></span> Submitted</div>
                <div class="approval-step"><span class="step-dot <?php echo in_array($pr['status'], ['approved','po_created','closed']) ? 'step-complete' : 'step-pending'; ?>"></span> Approved</div>
                <div class="approval-step"><span class="step-dot <?php echo in_array($pr['status'], ['po_created','closed']) ? 'step-complete' : 'step-pending'; ?>"></span> PO Created</div>
            </div>

            <div class="pr-card mb-4">
                <h5>Attachments</h5>
                <p style="color:#4b5563; font-size:0.95rem; margin-bottom:0;">Upload support docs in the future. Attachments are not yet configured in this module.</p>
            </div>

            <?php if ($pr['status'] === 'draft' && $can_approve): ?>
                <div class="pr-card">
                    <form method="POST">
                        <input type="hidden" name="form_action" value="approve">
                        <div class="mb-3">
                            <label class="form-label">Approval Notes</label>
                            <textarea name="approval_notes" class="form-control" rows="3" placeholder="Optional approval comments."></textarea>
                        </div>
                        <button type="submit" class="btn btn-success w-100">Approve PR</button>
                    </form>
                </div>
            <?php endif; ?>

            <div class="d-grid mt-3">
                <a href="index.php?nav=purchase_requests" class="btn btn-outline-secondary">Back to Purchase Requests</a>
            </div>
        </div>
    </div>

<?php else: ?>
    <div class="alert alert-warning">Invalid PR action. Return to the <a href="index.php?nav=purchase_requests">Purchase Requests list</a>.</div>
<?php endif; ?>
