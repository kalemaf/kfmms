<?php
require_once('../config.inc.php');
session_save_path($session_save_path);
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (!isset($_SESSION['user'])) {
    header('Location: ../auth.php');
    exit;
}

require_once('../common.inc.php');
require_once('../libraries/inventory_manager.php');

ensure_inventory_tables($connection);

$title = 'Consumables Management';
$action = $_GET['action'] ?? 'list';
$consumable_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if (empty($_SESSION['user_id'])) {
    $_SESSION['error'] = 'Session error: User ID not found. Please log in again.';
    header('Location: ../auth.php');
    exit;
}

// Create or update consumable
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_consumable'])) {
    $result = save_consumable_item($_POST, $connection);
    if ($result) {
        $_SESSION['success'] = 'Consumable item saved successfully.';
        header('Location: consumables.php');
        exit;
    }
    $_SESSION['error'] = 'Unable to save consumable item. Please verify the values and try again.';
}

// Record usage
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['record_usage'])) {
    $consumable_id = intval($_POST['consumable_id'] ?? 0);
    $quantity_used = floatval($_POST['quantity_used'] ?? 0);
    $work_order_id = intval($_POST['work_order_id'] ?? 0);
    $notes = trim($_POST['usage_notes'] ?? '');

    if (record_consumable_usage($consumable_id, $quantity_used, $work_order_id, $notes, $connection)) {
        $_SESSION['success'] = 'Consumable usage recorded and stock updated.';
    } else {
        $_SESSION['error'] = 'Failed to record consumable usage. Please check the consumable item and quantity.';
    }
    header('Location: consumables.php');
    exit;
}

$consumables = get_consumables($connection);
$usage_records = get_consumable_usage($connection, 50);
$category_options = get_consumable_categories();
$warehouses = get_warehouses($connection);
$warehouse_locations = get_all_warehouse_locations($connection);
$consumable = $consumable_id ? get_consumable($consumable_id, $connection) : null;

function render_alerts() {
    if (isset($_SESSION['success'])) {
        echo '<div class="alert alert-success alert-dismissible fade show" role="alert">'
            . '<i class="fas fa-check-circle"></i> ' . htmlspecialchars($_SESSION['success'])
            . '<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>'
            . '</div>';
        unset($_SESSION['success']);
    }
    if (isset($_SESSION['error'])) {
        echo '<div class="alert alert-danger alert-dismissible fade show" role="alert">'
            . '<i class="fas fa-exclamation-circle"></i> ' . htmlspecialchars($_SESSION['error'])
            . '<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>'
            . '</div>';
        unset($_SESSION['error']);
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
        body { background: #f4f7fb; color: #1f2937; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; }
        .page-shell { max-width: 1240px; margin: 30px auto; padding: 0 18px; }
        .page-header { padding: 24px 28px; margin-bottom: 24px; background: white; border-radius: 18px; border: 1px solid #e5e7eb; box-shadow: 0 10px 30px rgba(15,23,42,0.08); }
        .page-header h1 { margin: 0 0 6px; font-size: 28px; font-weight: 700; color: #111827; }
        .page-header p { margin: 0; color: #4b5563; font-size: 14px; }
        .card { border: none; border-radius: 18px; box-shadow: 0 10px 30px rgba(15,23,42,0.06); }
        .card-header { background: #eef2ff; border-radius: 18px 18px 0 0; border-bottom: none; }
        .form-label { font-weight: 600; color: #111827; }
        .form-control, .form-select { border-radius: 12px; border: 1px solid #d1d5db; }
        .table thead { background: #f8fafc; }
        .table th, .table td { vertical-align: middle; }
        .tag { display: inline-flex; align-items: center; gap: 6px; padding: 6px 10px; border-radius: 999px; font-size: 12px; font-weight: 600; }
        .tag-low { background: #fef2f2; color: #b91c1c; }
        .tag-normal { background: #ecfdf5; color: #166534; }
        .tag-out { background: #f8fafc; color: #475569; }
        .table-responsive { overflow-x: auto; }
        .btn-primary { background: #2563eb; border-color: #2563eb; }
        .btn-primary:hover { background: #1d4ed8; border-color: #1d4ed8; }
    </style>
</head>
<body>
<div class="page-shell">
    <div class="page-header">
        <div class="d-flex justify-content-between align-items-center">
            <div>
                <h1><i class="fas fa-box-open"></i> Consumables Management</h1>
                <p>Build and track consumable items from backend hierarchy to stock usage.</p>
            </div>
            <a href="../index.php?nav=dashboard" class="btn btn-outline-secondary">
                <i class="fas fa-arrow-left"></i> Back to Dashboard
            </a>
        </div>
    </div>

    <?php render_alerts(); ?>

    <div class="row g-4 mb-4">
        <div class="col-lg-7">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <div>
                        <h5 class="mb-0">Consumables Catalog</h5>
                        <small class="text-muted">Created and maintained in the backend database.</small>
                    </div>
                    <a href="consumables.php?action=add" class="btn btn-sm btn-primary"><i class="fas fa-plus"></i> Add Item</a>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Name</th>
                                    <th>Category</th>
                                    <th>Subcategory</th>
                                    <th>Location</th>
                                    <th>Stock</th>
                                    <th>Status</th>
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($consumables)): ?>
                                    <tr><td colspan="7" class="text-center text-muted py-4">No consumables defined yet.</td></tr>
                                <?php else: ?>
                                    <?php foreach ($consumables as $item): 
                                        // Get location info from warehouse_location_id if available
                                        $location_display = htmlspecialchars($item['location'] ?? '');
                                        if (!empty($item['warehouse_location_id']) && isset($warehouse_locations)) {
                                            foreach ($warehouse_locations as $loc) {
                                                if ($loc['id'] == $item['warehouse_location_id']) {
                                                    $location_display = htmlspecialchars($loc['warehouse_name'] ?? '') . ' - Z:' . htmlspecialchars($loc['zone'] ?? '-') . ' A:' . htmlspecialchars($loc['aisle'] ?? '-') . ' R:' . htmlspecialchars($loc['rack'] ?? '-');
                                                    break;
                                                }
                                            }
                                        }
                                    ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($item['name']); ?></td>
                                            <td><?php echo htmlspecialchars($item['category']); ?></td>
                                            <td><?php echo htmlspecialchars($item['subcategory']); ?></td>
                                            <td><?php echo $location_display; ?></td>
                                            <td><?php echo intval($item['current_stock']); ?></td>
                                            <td>
                                                <?php if (!$item['is_active']): ?>
                                                    <span class="tag tag-out">Inactive</span>
                                                <?php elseif (intval($item['current_stock']) <= 0): ?>
                                                    <span class="tag tag-out">Out of stock</span>
                                                <?php elseif (intval($item['current_stock']) <= intval($item['min_stock'])): ?>
                                                    <span class="tag tag-low">Low stock</span>
                                                <?php else: ?>
                                                    <span class="tag tag-normal">Normal</span>
                                                <?php endif; ?>
                                            </td>
                                            <td class="text-end">
                                                <a href="consumables.php?action=edit&id=<?php echo intval($item['id']); ?>" class="btn btn-sm btn-outline-secondary me-2">Edit</a>
                                                <a href="consumables.php?action=usage&id=<?php echo intval($item['id']); ?>" class="btn btn-sm btn-outline-primary">Usage</a>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-5">
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="mb-0"><?php echo ($action === 'usage' && $consumable) ? 'Record Consumable Usage' : (($action === 'edit' && $consumable) ? 'Edit Consumable' : 'Consumable Details'); ?></h5>
                </div>
                <div class="card-body">
                    <?php if ($action === 'usage' && $consumable): ?>
                        <form method="POST" onsubmit="this.querySelector('button[type=submit]').disabled = true;">
                            <input type="hidden" name="record_usage" value="1">
                            <input type="hidden" name="consumable_id" value="<?php echo intval($consumable['id']); ?>">

                            <div class="mb-3">
                                <label class="form-label">Consumable</label>
                                <input type="text" class="form-control" readonly value="<?php echo htmlspecialchars($consumable['name']); ?>">
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Quantity Used</label>
                                <input type="number" step="0.01" min="0.01" name="quantity_used" class="form-control" required>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Work Order ID</label>
                                <input type="number" min="0" name="work_order_id" class="form-control" placeholder="Optional">
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Usage Notes</label>
                                <textarea name="usage_notes" class="form-control" rows="3"></textarea>
                            </div>
                            <button type="submit" class="btn btn-primary w-100">Record Usage</button>
                        </form>
                    <?php else: ?>
                        <form method="POST">
                            <input type="hidden" name="save_consumable" value="1">
                            <?php if (!empty($consumable)): ?>
                                <input type="hidden" name="id" value="<?php echo intval($consumable['id']); ?>">
                            <?php endif; ?>

                            <div class="mb-3">
                                <label class="form-label">Name</label>
                                <input type="text" name="name" class="form-control" required value="<?php echo htmlspecialchars($consumable['name'] ?? ''); ?>">
                            </div>
                        <div class="row g-3 mb-3">
                            <div class="col-md-6">
                                <label class="form-label">Category</label>
                                <select name="category" class="form-select" required>
                                    <option value="">Select category</option>
                                    <?php foreach ($category_options as $category => $subcategories): ?>
                                        <option value="<?php echo htmlspecialchars($category); ?>" <?php echo (($consumable['category'] ?? '') === $category) ? 'selected' : ''; ?>><?php echo htmlspecialchars($category); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Subcategory</label>
                                <input type="text" name="subcategory" class="form-control" value="<?php echo htmlspecialchars($consumable['subcategory'] ?? ''); ?>">
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Location</label>
                            <select name="warehouse_location_id" class="form-select">
                                <option value="">Select warehouse location</option>
                                <?php if (!empty($warehouse_locations)): ?>
                                    <?php foreach ($warehouse_locations as $loc): ?>
                                        <option value="<?php echo intval($loc['id']); ?>" <?php echo (($consumable['warehouse_location_id'] ?? 0) == $loc['id']) ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($loc['warehouse_name'] ?? 'Warehouse'); ?> - Zone: <?php echo htmlspecialchars($loc['zone'] ?? '-'); ?>, Aisle: <?php echo htmlspecialchars($loc['aisle'] ?? '-'); ?>, Rack: <?php echo htmlspecialchars($loc['rack'] ?? '-'); ?>, Bin: <?php echo htmlspecialchars($loc['bin'] ?? '-'); ?>
                                        </option>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </select>
                            <small class="text-muted">Select warehouse location where this consumable is stored</small>
                        </div>
                        <div class="row g-3 mb-3">
                            <div class="col-md-6">
                                <label class="form-label">Current Stock</label>
                                <input type="number" name="current_stock" class="form-control" min="0" value="<?php echo intval($consumable['current_stock'] ?? 0); ?>">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Minimum Stock</label>
                                <input type="number" name="min_stock" class="form-control" min="0" value="<?php echo intval($consumable['min_stock'] ?? 0); ?>">
                            </div>
                        </div>
                        <div class="row g-3 mb-3">
                            <div class="col-md-6">
                                <label class="form-label">Unit</label>
                                <input type="text" name="unit" class="form-control" value="<?php echo htmlspecialchars($consumable['unit'] ?? 'pcs'); ?>">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Cost per Unit</label>
                                <input type="number" step="0.01" name="cost_per_unit" class="form-control" value="<?php echo htmlspecialchars($consumable['cost_per_unit'] ?? '0.00'); ?>">
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Supplier</label>
                            <input type="text" name="supplier" class="form-control" value="<?php echo htmlspecialchars($consumable['supplier'] ?? ''); ?>">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Description</label>
                            <textarea name="description" class="form-control" rows="3"><?php echo htmlspecialchars($consumable['description'] ?? ''); ?></textarea>
                        </div>
                        <button type="submit" class="btn btn-primary w-100">Save Consumable</button>
                    </form>
                    <?php endif; ?>
                </div>
            </div>

            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">Usage & Stock Alerts</h5>
                </div>
                <div class="card-body">
                    <p class="text-muted">Consumable stock is tracked centrally in the backend structure and used by work orders.</p>
                    <div class="mb-3">
                        <strong>Total items:</strong> <?php echo count($consumables); ?><br>
                        <strong>Recent usage records:</strong> <?php echo count($usage_records); ?>
                    </div>
                    <div class="list-group">
                        <?php foreach (array_slice($usage_records, 0, 5) as $usage): ?>
                            <div class="list-group-item">
                                <strong><?php echo htmlspecialchars($usage['consumable_name'] ?? 'Item'); ?></strong>
                                <div class="small text-muted">Used <?php echo htmlspecialchars($usage['quantity_used']); ?> on WO #<?php echo intval($usage['work_order_id']); ?> · <?php echo htmlspecialchars($usage['usage_date']); ?></div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-header">
            <h5 class="mb-0">Consumable Usage History</h5>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-striped">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Item</th>
                            <th>Quantity</th>
                            <th>Work Order</th>
                            <th>Notes</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($usage_records)): ?>
                            <tr><td colspan="5" class="text-center text-muted py-4">No usage records yet.</td></tr>
                        <?php else: ?>
                            <?php foreach ($usage_records as $usage): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($usage['usage_date']); ?></td>
                                    <td><?php echo htmlspecialchars($usage['consumable_name']); ?></td>
                                    <td><?php echo htmlspecialchars($usage['quantity_used']); ?></td>
                                    <td><?php echo intval($usage['work_order_id']); ?></td>
                                    <td><?php echo htmlspecialchars($usage['notes']); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
