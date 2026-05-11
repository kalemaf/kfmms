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

$title = "Supplier Management";
$action = isset($_GET['action']) ? $_GET['action'] : 'list';
$vendor_id = isset($_GET['id']) ? intval($_GET['id']) : null;

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_vendor'])) {
    $result = save_vendor($_POST, $connection);
    if ($result) {
        $_SESSION['success'] = "Supplier " . ($vendor_id ? "updated" : "created") . " successfully!";
        header("Location: vendor_management.php");
        exit;
    } else {
        $_SESSION['error'] = "Failed to save supplier.";
    }
}

// Load vendor for editing
$vendor = null;
if ($action === 'edit' && $vendor_id) {
    $vendor = get_vendor_details($vendor_id, $connection);
    if (!$vendor) {
        $_SESSION['error'] = "Supplier not found.";
        header("Location: vendor_management.php");
        exit;
    }
}

$vendors = get_vendors($connection, false);
$countries = get_country_list();
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
        .vendor-card { background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); border-left: 4px solid #667eea; margin-bottom: 20px; }
        .vendor-card h5 { font-weight: 700; color: #333; margin-bottom: 10px; }
        .vendor-info { font-size: 13px; color: #666; margin-bottom: 5px; }
        .rating-stars { color: #f39c12; margin-bottom: 10px; }
        .rating-stat { display: inline-block; margin-right: 15px; }
        .rating-stat .label { font-size: 11px; color: #999; text-transform: uppercase; }
        .rating-stat .value { font-size: 18px; font-weight: 700; color: #333; }
        .btn-primary { background: #667eea; border: none; }
        .btn-primary:hover { background: #5568d3; }
        .table { font-size: 13px; margin-bottom: 0; }
        .table thead { background: #f8f9fa; }
        .table th { font-weight: 700; color: #333; border-top: none; }
        .table-hover tbody tr:hover { background: #f8f9fa; }
        .status-badge { font-size: 11px; padding: 5px 10px; }
        .status-active { background: #d4edda; color: #155724; }
        .status-inactive { background: #f8d7da; color: #721c24; }
        .grid-2 { display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 15px; }
        .grid-3 { display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 15px; } 
        .form-label { font-weight: 600; color: #333; margin-bottom: 6px; }
        .alert { margin-bottom: 20px; }
        .action-buttons { display: flex; gap: 5px; }
        .action-buttons a { font-size: 13px; padding: 5px 10px; }
    </style>
</head>
<body>

<div class="container">
    
    <!-- Header -->
    <div class="header">
        <h1><i class="fas fa-users"></i> Supplier Management</h1>
        <p>Manage your supplier network with performance, terms and readiness tracking.</p>
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
    
    <?php if ($action === 'create' || $action === 'edit'): ?>
        
        <!-- Create/Edit Form -->
        <div class="form-container">
            <h2><?php echo $action === 'create' ? 'New Supplier' : 'Edit Supplier'; ?></h2>
            
            <form method="POST">
                <input type="hidden" name="save_vendor" value="1">
                <?php if ($vendor): ?>
                    <input type="hidden" name="id" value="<?php echo $vendor['id']; ?>">
                <?php endif; ?>
                
                <!-- Basic Information -->
                <div class="form-section">
                    <div class="form-section-title">Basic Information</div>
                    <div class="grid-2">
                        <div class="form-group">
                            <label class="form-label">Supplier Code *</label>
                            <input type="text" class="form-control" name="vendor_code" required
                                   value="<?php echo htmlspecialchars($vendor['vendor_code'] ?? ''); ?>">
                        </div>
                        <div class="form-group">
                            <label class="form-label">Supplier Name *</label>
                            <input type="text" class="form-control" name="vendor_name" required
                                   value="<?php echo htmlspecialchars($vendor['vendor_name'] ?? ''); ?>">
                        </div>
                        <div class="form-group">
                            <label class="form-label">Contact Person</label>
                            <input type="text" class="form-control" name="contact_person"
                                   value="<?php echo htmlspecialchars($vendor['contact_person'] ?? ''); ?>">
                        </div>
                        <div class="form-group">
                            <label class="form-label">Email</label>
                            <input type="email" class="form-control" name="email"
                                   value="<?php echo htmlspecialchars($vendor['email'] ?? ''); ?>">
                        </div>
                        <div class="form-group">
                            <label class="form-label">Phone</label>
                            <input type="tel" class="form-control" name="phone"
                                   value="<?php echo htmlspecialchars($vendor['phone'] ?? ''); ?>">
                        </div>
                        <div class="form-group">
                            <label class="form-label">Fax</label>
                            <input type="tel" class="form-control" name="fax"
                                   value="<?php echo htmlspecialchars($vendor['fax'] ?? ''); ?>">
                        </div>
                    </div>
                </div>
                
                <!-- Address Information -->
                <div class="form-section">
                    <div class="form-section-title">Address</div>
                    <div class="form-group">
                        <label class="form-label">Address</label>
                        <input type="text" class="form-control" name="address"
                               value="<?php echo htmlspecialchars($vendor['address'] ?? ''); ?>">
                    </div>
                    <div class="grid-3">
                        <div class="form-group">
                            <label class="form-label">City</label>
                            <input type="text" class="form-control" name="city"
                                   value="<?php echo htmlspecialchars($vendor['city'] ?? ''); ?>">
                        </div>
                        <div class="form-group">
                            <label class="form-label">State/Province</label>
                            <input type="text" class="form-control" name="state"
                                   value="<?php echo htmlspecialchars($vendor['state'] ?? ''); ?>">
                        </div>
                        <div class="form-group">
                            <label class="form-label">Postal Code</label>
                            <input type="text" class="form-control" name="postal_code"
                                   value="<?php echo htmlspecialchars($vendor['postal_code'] ?? ''); ?>">
                        </div>
                        <div class="form-group">
                            <label class="form-label">Country</label>
                            <input type="text" class="form-control" name="country"
                                   value="<?php echo htmlspecialchars($vendor['country'] ?? ''); ?>">
                        </div>
                    </div>
                </div>
                
                <!-- Business Terms -->
                <div class="form-section">
                    <div class="form-section-title">Business Terms</div>
                    <div class="grid-2">
                        <div class="form-group">
                            <label class="form-label">Payment Terms</label>
                            <select class="form-control" name="payment_terms">
                                <option value="">Select Terms</option>
                                <option value="Net 15" <?php echo ($vendor['payment_terms'] ?? '') === 'Net 15' ? 'selected' : ''; ?>>Net 15</option>
                                <option value="Net 30" <?php echo ($vendor['payment_terms'] ?? '') === 'Net 30' ? 'selected' : ''; ?>>Net 30</option>
                                <option value="Net 45" <?php echo ($vendor['payment_terms'] ?? '') === 'Net 45' ? 'selected' : ''; ?>>Net 45</option>
                                <option value="Net 60" <?php echo ($vendor['payment_terms'] ?? '') === 'Net 60' ? 'selected' : ''; ?>>Net 60</option>
                                <option value="Due on Receipt" <?php echo ($vendor['payment_terms'] ?? '') === 'Due on Receipt' ? 'selected' : ''; ?>>Due on Receipt</option>
                                <option value="2/10 Net 30" <?php echo ($vendor['payment_terms'] ?? '') === '2/10 Net 30' ? 'selected' : ''; ?>>2/10 Net 30</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Lead Time (Days)</label>
                            <input type="number" class="form-control" name="lead_time_days" min="1"
                                   value="<?php echo htmlspecialchars($vendor['lead_time_days'] ?? 7); ?>">
                        </div>
                    </div>
                </div>
                
                <!-- Performance & Status -->
                <div class="form-section">
                    <div class="form-section-title">Performance & Status</div>
                    <div class="grid-2">
                        <div class="form-group">
                            <label class="form-label">Overall Rating (1-5 Stars)</label>
                            <select class="form-control" name="rating">
                                <option value="1" <?php echo (intval($vendor['rating'] ?? 0) === 1) ? 'selected' : ''; ?>>1 Star - Poor</option>
                                <option value="2" <?php echo (intval($vendor['rating'] ?? 0) === 2) ? 'selected' : ''; ?>>2 Stars</option>
                                <option value="3" <?php echo (intval($vendor['rating'] ?? 0) === 3) ? 'selected' : ''; ?>>3 Stars - Average</option>
                                <option value="4" <?php echo (intval($vendor['rating'] ?? 0) === 4) ? 'selected' : ''; ?>>4 Stars - Good</option>
                                <option value="5" <?php echo (intval($vendor['rating'] ?? 5) === 5 && !isset($vendor)) ? 'selected' : (intval($vendor['rating'] ?? 0) === 5 ? 'selected' : ''); ?>>5 Stars - Excellent</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label class="form-label">
                                <input type="checkbox" name="is_active" value="1"
                                       <?php echo (!isset($vendor) || (isset($vendor['is_active']) && $vendor['is_active'])) ? 'checked' : ''; ?>>
                                Active Supplier
                            </label>
                        </div>
                    </div>
                </div>
                
                <!-- Notes -->
                <div class="form-section">
                    <div class="form-section-title">Additional Notes</div>
                    <div class="form-group">
                        <textarea class="form-control" name="notes" rows="5"><?php echo htmlspecialchars($vendor['notes'] ?? ''); ?></textarea>
                    </div>
                </div>
                
                <!-- Form Actions -->
                <div style="display: flex; gap: 10px; margin-top: 30px;">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save"></i> Save Supplier
                    </button>
                    <a href="vendor_management.php" class="btn btn-secondary">
                        <i class="fas fa-times"></i> Cancel
                    </a>
                </div>
            </form>
        </div>
        
    <?php else: ?>
        
        <!-- Vendors List -->
        <div class="list-container">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 25px;">
                <h3 style="margin: 0;">Suppliers (<?php echo count($vendors); ?>)</h3>
                <a href="vendor_management.php?action=create" class="btn btn-success">
                    <i class="fas fa-plus"></i> New Supplier
                </a>
            </div>
            
            <?php if (count($vendors) > 0): ?>
                <div class="grid-2">
                    <?php foreach ($vendors as $v): ?>
                        <div class="vendor-card">
                            <div style="display: flex; justify-content: space-between; align-items: start;">
                                <div style="flex: 1;">
                                    <h5>
                                        <i class="fas fa-industry"></i> <?php echo htmlspecialchars($v['vendor_name']); ?>
                                    </h5>
                                    <div class="vendor-info">
                                        <strong>Code:</strong> <?php echo htmlspecialchars($v['vendor_code']); ?>
                                    </div>
                                </div>
                                <span class="status-badge <?php echo $v['is_active'] ? 'status-active' : 'status-inactive'; ?>">
                                    <?php echo $v['is_active'] ? 'Active' : 'Inactive'; ?>
                                </span>
                            </div>
                            
                            <div class="rating-stars" style="margin-top: 10px;">
                                <?php echo str_repeat('★', intval($v['rating'] ?? 0)) . str_repeat('☆', 5 - intval($v['rating'] ?? 0)); ?>
                                <span style="margin-left: 5px; color: #666; font-size: 12px;"><?php echo number_format($v['rating'] ?? 0, 1); ?>/5</span>
                            </div>
                            
                            <div class="vendor-info">
                                <strong>Contact:</strong> <?php echo htmlspecialchars($v['contact_person'] ?? 'N/A'); ?>
                            </div>
                            <div class="vendor-info">
                                <strong>Phone:</strong> <?php echo htmlspecialchars($v['phone'] ?? 'N/A'); ?>
                            </div>
                            <div class="vendor-info">
                                <strong>Email:</strong> <?php echo htmlspecialchars($v['email'] ?? 'N/A'); ?>
                            </div>
                            <div class="vendor-info">
                                <strong>Lead Time:</strong> <?php echo $v['lead_time_days']; ?> days
                            </div>
                            <div class="vendor-info">
                                <strong>Payment Terms:</strong> <?php echo htmlspecialchars($v['payment_terms'] ?? 'N/A'); ?>
                            </div>
                            <div class="vendor-info" style="margin-bottom: 15px;">
                                <strong>Total Orders:</strong> <?php echo number_format($v['total_orders']); ?>
                            </div>
                            
                            <div style="display: flex; gap: 5px;">
                                <a href="vendor_management.php?action=edit&id=<?php echo $v['id']; ?>" class="btn btn-sm btn-info">
                                    <i class="fas fa-edit"></i> Edit
                                </a>
                                <a href="vendor_parts.php?vendor_id=<?php echo $v['id']; ?>" class="btn btn-sm btn-primary">
                                    <i class="fas fa-box"></i> Parts
                                </a>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="alert alert-info">
                    <i class="fas fa-info-circle"></i> No suppliers found. <a href="vendor_management.php?action=create">Create your first supplier</a>
                </div>
            <?php endif; ?>
        </div>
        
    <?php endif; ?>
    
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

</body>
</html>
