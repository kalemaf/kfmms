<?php
require_once 'config.inc.php';
if (session_status() === PHP_SESSION_NONE) {
    session_save_path($session_save_path);
    session_start();
}
if (empty($_SESSION['user'])) {
    header('Location: auth.php');
    exit;
}

require_once 'common.inc.php';
require_once 'libraries/inventory_manager.php';

$action = isset($_GET['action']) ? $_GET['action'] : 'list';
$vendor_id = isset($_GET['id']) ? intval($_GET['id']) : null;
$_SESSION['nav'] = 'vendors';
$standalone = basename($_SERVER['PHP_SELF']) === 'vendors.php';

if ($standalone) {
    require_once 'title.php';
}

$message = '';
$error = '';
$vendor = null;

// Enable error reporting for debugging vendor save issues
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_vendor'])) {
    if (!$connection) {
        $error = 'Database connection error. Please contact system administrator.';
        error_log("[VENDOR ERROR] No database connection available");
    } else {
        $result = save_vendor($_POST, $connection);
        if ($result) {
            $message = 'Supplier ' . (!empty($vendor_id) ? 'updated' : 'created') . ' successfully.';
            $action = 'list';
            // Refresh vendor list
            $vendors = get_vendors($connection, false);
        } else {
            $error = 'Unable to save supplier. Please verify all required fields are filled correctly.';
            $action = isset($_POST['id']) && intval($_POST['id']) > 0 ? 'edit' : 'create';
            if (!empty($_POST['id'])) {
                $vendor_id = intval($_POST['id']);
            }
            // Log for debugging
            error_log("[VENDOR SAVE] POST data: " . json_encode($_POST));
        }
    }
}

if ($action === 'edit' && $vendor_id) {
    $vendor = get_vendor_details($vendor_id, $connection);
    if (!$vendor) {
        $error = 'Supplier not found.';
        $action = 'list';
    }
}

$vendors = get_vendors($connection, false);
$countries = get_country_list();
?>

<?php if ($standalone): ?>
<?php endif; ?>

<div class="page-header" style="margin-top: 20px; margin-bottom: 20px; padding: 20px; background: white; border-radius: 10px; box-shadow: 0 2px 12px rgba(0,0,0,0.05);">
    <div class="d-flex justify-content-between align-items-start flex-column flex-md-row gap-3">
        <div>
            <h1 class="h3">Supplier Management</h1>
            <p class="text-muted mb-0">Manage approved suppliers, terms, lead times and vendor performance at a glance.</p>
        </div>
        <div>
            <a href="vendors.php?action=create" class="btn btn-success">
                <i class="fas fa-plus"></i> New Supplier
            </a>
        </div>
    </div>
</div>

<style>
    .supplier-table {
        border-collapse: separate;
        border-spacing: 0 0.5rem;
        background: transparent;
    }
    .supplier-table thead {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    }
    .supplier-table th {
        color: #fff;
        letter-spacing: 0.08em;
        text-transform: uppercase;
        font-size: 0.78rem;
        border: 0;
        padding: 1rem 0.75rem;
        vertical-align: bottom;
    }
    .supplier-table tbody tr {
        background: #ffffff;
        box-shadow: 0 2px 10px rgba(102, 126, 234, 0.06);
        border-radius: 0.75rem;
    }
    .supplier-table tbody tr td {
        vertical-align: middle;
        font-size: 0.92rem;
        padding: 1rem 0.75rem;
        border-top: 0;
        border-bottom: 1px solid rgba(102, 126, 234, 0.08);
    }
    .supplier-table tbody tr:last-child td {
        border-bottom: none;
    }
    .supplier-table tbody tr:hover {
        background: #eef2ff;
    }
    .supplier-table .text-muted.small {
        font-size: 0.78rem;
        color: #6c757d;
    }
    .supplier-table .badge {
        font-size: 0.78rem;
        padding: 0.45em 0.75em;
        border-radius: 999px;
    }
    .supplier-table .badge.bg-success {
        background-color: #10b981 !important;
    }
    .supplier-table .badge.bg-secondary {
        background-color: #6b7280 !important;
    }
    .supplier-table .badge.bg-primary {
        background-color: #3b82f6 !important;
    }
    .supplier-table .badge.bg-warning {
        background-color: #f59e0b !important;
    }
    .supplier-table td:first-child {
        border-top-left-radius: 0.75rem;
        border-bottom-left-radius: 0.75rem;
    }
    .supplier-table td:last-child {
        border-top-right-radius: 0.75rem;
        border-bottom-right-radius: 0.75rem;
    }
</style>

<?php if ($message): ?>
    <div class="alert alert-success alert-dismissible fade show" role="alert">
        <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($message); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
<?php endif; ?>

<?php if ($error): ?>
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
        <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
<?php endif; ?>

<?php if ($action === 'create' || $action === 'edit'): ?>
    <div class="card mb-4">
        <div class="card-body">
            <h2 class="h5 mb-4"><?php echo $action === 'create' ? 'Add New Supplier' : 'Edit Supplier'; ?></h2>
            <form method="POST">
                <input type="hidden" name="save_vendor" value="1">
                <?php if ($vendor): ?>
                    <input type="hidden" name="id" value="<?php echo intval($vendor['id']); ?>">
                <?php endif; ?>

                <div class="row g-3">
                    <?php if ($action === 'edit' && $vendor): ?>
                        <div class="col-md-6">
                            <label class="form-label">Supplier Code</label>
                            <input type="text" name="vendor_code" class="form-control" readonly value="<?php echo htmlspecialchars($vendor['vendor_code'] ?? ''); ?>">
                            <small class="text-muted">Auto-generated</small>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Supplier Name *</label>
                            <input type="text" name="vendor_name" class="form-control" required value="<?php echo htmlspecialchars($vendor['vendor_name'] ?? ''); ?>">
                        </div>
                    <?php else: ?>
                        <input type="hidden" name="vendor_code" value="">
                        <div class="col-md-6">
                            <label class="form-label">Supplier Name *</label>
                            <input type="text" name="vendor_name" class="form-control" required value="<?php echo htmlspecialchars($vendor['vendor_name'] ?? ''); ?>">
                        </div>
                    <?php endif; ?>
                    <div class="col-md-6">
                        <label class="form-label">Contact Person</label>
                        <input type="text" name="contact_person" class="form-control" value="<?php echo htmlspecialchars($vendor['contact_person'] ?? ''); ?>">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Email</label>
                        <input type="email" name="email" class="form-control" value="<?php echo htmlspecialchars($vendor['email'] ?? ''); ?>">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Phone</label>
                        <input type="tel" name="phone" class="form-control" value="<?php echo htmlspecialchars($vendor['phone'] ?? ''); ?>">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Fax</label>
                        <input type="text" name="fax" class="form-control" value="<?php echo htmlspecialchars($vendor['fax'] ?? ''); ?>">
                    </div>
                    <div class="col-12">
                        <label class="form-label">Address</label>
                        <input type="text" name="address" class="form-control" value="<?php echo htmlspecialchars($vendor['address'] ?? ''); ?>">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">City</label>
                        <input type="text" name="city" class="form-control" value="<?php echo htmlspecialchars($vendor['city'] ?? ''); ?>">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">State/Province</label>
                        <input type="text" name="state" class="form-control" value="<?php echo htmlspecialchars($vendor['state'] ?? ''); ?>">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Postal Code</label>
                        <input type="text" name="postal_code" class="form-control" value="<?php echo htmlspecialchars($vendor['postal_code'] ?? ''); ?>">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Country</label>
                        <select name="country" class="form-select">
                            <option value="">Select Country</option>
                            <?php foreach ($countries as $country): ?>
                                <option value="<?php echo htmlspecialchars($country); ?>" <?php echo (isset($vendor['country']) && $vendor['country'] === $country) ? 'selected' : ''; ?>><?php echo htmlspecialchars($country); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Payment Terms</label>
                        <select name="payment_terms" class="form-select">
                            <option value="">Select Terms</option>
                            <?php foreach (['Net 15', 'Net 30', 'Net 45', 'Net 60', 'Due on Receipt', '2/10 Net 30'] as $term): ?>
                                <option value="<?php echo htmlspecialchars($term); ?>" <?php echo (isset($vendor['payment_terms']) && $vendor['payment_terms'] === $term) ? 'selected' : ''; ?>><?php echo htmlspecialchars($term); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Lead Time (Days)</label>
                        <input type="number" name="lead_time_days" min="1" class="form-control" value="<?php echo htmlspecialchars($vendor['lead_time_days'] ?? 7); ?>">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Rating</label>
                        <select name="rating" class="form-select">
                            <?php for ($i = 1; $i <= 5; $i++): ?>
                                <option value="<?php echo $i; ?>" <?php echo (intval($vendor['rating'] ?? 5) === $i) ? 'selected' : ''; ?>><?php echo $i; ?> Star<?php echo $i > 1 ? 's' : ''; ?></option>
                            <?php endfor; ?>
                        </select>
                    </div>
                    <div class="col-12">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="is_active" name="is_active" value="1" <?php echo (!isset($vendor['is_active']) || $vendor['is_active']) ? 'checked' : ''; ?>>
                            <label class="form-check-label" for="is_active">Active Supplier</label>
                        </div>
                    </div>
                    <div class="col-12">
                        <label class="form-label">Notes</label>
                        <textarea name="notes" class="form-control" rows="4"><?php echo htmlspecialchars($vendor['notes'] ?? ''); ?></textarea>
                    </div>
                </div>

                <div class="mt-4 d-flex gap-2">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save"></i> Save Supplier
                    </button>
                    <a href="vendors.php" class="btn btn-secondary">
                        <i class="fas fa-times"></i> Cancel
                    </a>
                </div>
            </form>
        </div>
    </div>
<?php else: ?>
    <?php if (count($vendors) > 0): ?>
        <div class="table-responsive mb-4">
            <table class="table table-hover align-middle supplier-table">
                <thead class="table-light">
                    <tr>
                        <th>Supplier</th>
                        <th>Code</th>
                        <th>Contact</th>
                        <th>Phone / Email</th>
                        <th>Lead Time</th>
                        <th>Payment Terms</th>
                        <th>Rating</th>
                        <th>Status</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($vendors as $v): ?>
                        <tr>
                            <td>
                                <div class="fw-semibold"><?php echo htmlspecialchars($v['vendor_name']); ?></div>
                                <div class="text-muted small"><?php echo htmlspecialchars($v['address'] ?? ''); ?></div>
                            </td>
                            <td><?php echo htmlspecialchars($v['vendor_code'] ?: '—'); ?></td>
                            <td><?php echo htmlspecialchars($v['contact_person'] ?: '—'); ?></td>
                            <td>
                                <div><?php echo htmlspecialchars($v['phone'] ?: '—'); ?></div>
                                <div class="text-muted small"><?php echo htmlspecialchars($v['email'] ?: '—'); ?></div>
                            </td>
                            <td><?php echo intval($v['lead_time_days'] ?? 0); ?> days</td>
                            <td><?php echo htmlspecialchars($v['payment_terms'] ?: '—'); ?></td>
                            <td>
                                <span class="text-warning"><?php echo str_repeat('★', intval($v['rating'] ?? 0)); ?></span><?php echo str_repeat('☆', 5 - intval($v['rating'] ?? 0)); ?>
                                <div class="text-muted small"><?php echo number_format($v['rating'] ?? 0, 1); ?>/5</div>
                            </td>
                            <td>
                                <span class="badge <?php echo $v['is_active'] ? 'bg-success' : 'bg-secondary'; ?>"><?php echo $v['is_active'] ? 'Active' : 'Inactive'; ?></span>
                            </td>
                            <td class="text-end">
                                <a href="vendors.php?action=edit&id=<?php echo intval($v['id']); ?>" class="btn btn-sm btn-outline-primary me-2">
                                    <i class="fas fa-edit"></i>
                                </a>
                                <a href="inventory/vendor_parts.php?vendor_id=<?php echo intval($v['id']); ?>" class="btn btn-sm btn-outline-secondary">
                                    <i class="fas fa-box"></i>
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php else: ?>
        <div class="alert alert-info">
            <i class="fas fa-info-circle"></i> No suppliers found. <a href="vendors.php?action=create">Create your first supplier</a>.
        </div>
    <?php endif; ?>
<?php endif; ?>

<?php if ($standalone): ?>
    </div>
</body>
</html>
<?php endif; ?>
