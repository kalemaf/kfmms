
<?php
// --- Standardized session handling ---
require_once("../config.inc.php");
session_save_path($session_save_path);
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (!empty($debug_mode)) {
    error_log("[DEBUG] inventory/parts_master.php SID=" . session_id() . ", SESSION=" . json_encode($_SESSION));
}

if (!isset($_SESSION['user'])) {
    header("Location: ../auth.php");
    exit;
}

require_once("../common.inc.php");
require_once("../libraries/inventory_manager.php");

$title = "Parts Master Management";
$action = isset($_GET['action']) ? $_GET['action'] : 'list';
$part_id = isset($_GET['id']) ? intval($_GET['id']) : null;

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_part'])) {
    $result = save_part($_POST, $connection);
    if ($result) {
        // Handle equipment associations if provided
        $equipment_ids = isset($_POST['equipment_ids']) && is_array($_POST['equipment_ids']) 
            ? array_map('intval', $_POST['equipment_ids']) 
            : [];
        
        if (!empty($equipment_ids)) {
            save_part_equipment_associations($result, $equipment_ids, $connection);
        }
        
        $_SESSION['success'] = "Part " . ($part_id ? "updated" : "created") . " successfully!";
        header("Location: parts_master.php");
        exit;
    } else {
        $_SESSION['error'] = "Failed to save part.";
    }
}

// Load part for editing
$part = null;
$equipment_list = get_equipment_list($connection, true);
$part_equipment_spares = [];

if ($action === 'edit' && $part_id) {
    $part = get_part($part_id, $connection);
    if (!$part) {
        $_SESSION['error'] = "Part not found.";
        header("Location: parts_master.php");
        exit;
    }
    $part_equipment_spares = get_part_equipment_spares($part_id, $connection);
}

// Get parts list for display
$search = isset($_GET['search']) ? $_GET['search'] : '';
$category_filter = isset($_GET['category']) ? $_GET['category'] : '';
$criticality_filter = isset($_GET['criticality']) ? $_GET['criticality'] : '';

$filters = [];
if ($search) $filters['search'] = $search;
if ($category_filter) $filters['category'] = $category_filter;
if ($criticality_filter) $filters['criticality'] = $criticality_filter;

$parts_list = get_parts($connection, $filters);
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
        .header p { color: #666; margin-bottom: 0; }
        .form-container { background: white; padding: 30px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); margin-bottom: 25px; }
        .form-section { margin-bottom: 30px; }
        .form-section-title { font-weight: 700; color: #333; padding-bottom: 15px; border-bottom: 2px solid #667eea; margin-bottom: 15px; }
        .form-group { margin-bottom: 15px; }
        .form-label { font-weight: 600; color: #333; margin-bottom: 6px; }
        .btn-primary { background: #667eea; border: none; }
        .btn-primary:hover { background: #5568d3; }
        .btn-secondary { background: #6c757d; border: none; }
        .list-container { background: white; padding: 25px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        .table { font-size: 13px; margin-bottom: 0; }
        .table thead { background: #f8f9fa; }
        .table th { font-weight: 700; color: #333; border-top: none; }
        .table-hover tbody tr:hover { background: #f8f9fa; }
        .badge-criticality { font-size: 11px; padding: 5px 10px; }
        .criticality-critical { background: #e74c3c; color: white; }
        .criticality-high { background: #f39c12; color: white; }
        .criticality-medium { background: #3498db; color: white; }
        .criticality-low { background: #95a5a6; color: white; }
        .abc-badge { font-size: 12px; font-weight: 700; padding: 4px 8px; border-radius: 4px; }
        .abc-a { background: #e8f5e9; color: #2e7d32; }
        .abc-b { background: #fff3e0; color: #e65100; }
        .abc-c { background: #f3e5f5; color: #6a1b9a; }
        .grid-2 { display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 15px; }
        .filter-bar { background: white; padding: 20px; border-radius: 8px; margin-bottom: 25px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        .alert { margin-bottom: 20px; }
        .action-buttons { display: flex; gap: 5px; }
        .action-buttons a { font-size: 13px; padding: 5px 10px; }
        
        /* Equipment Compatibility Section Styles */
        .equipment-checkbox-list { 
            max-height: 300px; 
            overflow-y: auto; 
            border: 1px solid #ddd; 
            border-radius: 4px; 
            padding: 10px; 
            background: #f9f9f9;
        }
        .equipment-checkbox-item {
            margin-bottom: 10px;
            padding: 8px;
            border-radius: 4px;
            transition: background-color 0.2s;
        }
        .equipment-checkbox-item:hover {
            background-color: #f0f0f0;
        }
        .equipment-checkbox-item input[type="checkbox"]:checked + span {
            color: #667eea;
            font-weight: 600;
        }
        .equipment-info-section {
            background: #f0f4ff;
            border-left: 4px solid #667eea;
            padding: 12px;
            border-radius: 4px;
            margin-bottom: 15px;
        }
    </style>
</head>
<body>

<div class="container">
    
    <!-- Header -->
    <div class="header">
        <h1><i class="fas fa-box"></i> Parts Master Management</h1>
        <p>Manage your spare parts inventory and specifications</p>
    </div>
    
    <!-- Messages -->
    <?php if (isset($_SESSION['success'])): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($_SESSION['success']); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php unset($_SESSION['success']); ?>
    <?php endif; ?>
    
    <?php if (isset($_SESSION['error'])): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($_SESSION['error']); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php unset($_SESSION['error']); ?>
    <?php endif; ?>
    
    <?php if ($action === 'create' || $action === 'edit'): ?>
        
        <!-- Create/Edit Form -->
        <div class="form-container">
            <h2><?php echo $action === 'create' ? 'New Part' : 'Edit Part'; ?></h2>
            
            <form method="POST" action="">
                <input type="hidden" name="save_part" value="1">
                <?php if ($part): ?>
                    <input type="hidden" name="id" value="<?php echo $part['id']; ?>">
                <?php endif; ?>
                
                <!-- Basic Information -->
                <div class="form-section">
                    <div class="form-section-title">Basic Information</div>
                    <div class="grid-2">
                        <div class="form-group">
                            <label class="form-label">Part Code *</label>
                            <input type="text" class="form-control" name="part_code" required 
                                   value="<?php echo htmlspecialchars($part['part_code'] ?? ''); ?>">
                        </div>
                        <div class="form-group">
                            <label class="form-label">Part Number</label>
                            <input type="text" class="form-control" name="part_number"
                                   value="<?php echo htmlspecialchars($part['part_number'] ?? ''); ?>">
                        </div>
                        <div class="form-group">
                            <label class="form-label">Part Name *</label>
                            <input type="text" class="form-control" name="part_name" required
                                   value="<?php echo htmlspecialchars($part['part_name'] ?? ''); ?>">
                        </div>
                        <div class="form-group">
                            <label class="form-label">Category</label>
                            <select class="form-control" name="category">
                                <option value="">Select Category</option>
                                <option value="Mechanical" <?php echo ($part['category'] ?? '') === 'Mechanical' ? 'selected' : ''; ?>>Mechanical</option>
                                <option value="Electrical" <?php echo ($part['category'] ?? '') === 'Electrical' ? 'selected' : ''; ?>>Electrical</option>
                                <option value="Hydraulic" <?php echo ($part['category'] ?? '') === 'Hydraulic' ? 'selected' : ''; ?>>Hydraulic</option>
                                <option value="Other" <?php echo ($part['category'] ?? '') === 'Other' ? 'selected' : ''; ?>>Other</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Description</label>
                        <textarea class="form-control" name="description" rows="3"><?php echo htmlspecialchars($part['description'] ?? ''); ?></textarea>
                    </div>
                </div>
                
                <!-- Supply Information -->
                <div class="form-section">
                    <div class="form-section-title">Supply & Manufacturer Information</div>
                    <div class="grid-2">
                        <div class="form-group">
                            <label class="form-label">Manufacturer</label>
                            <input type="text" class="form-control" name="manufacturer"
                                   value="<?php echo htmlspecialchars($part['manufacturer'] ?? ''); ?>">
                        </div>
                        <div class="form-group">
                            <label class="form-label">OEM Part Number</label>
                            <input type="text" class="form-control" name="oem_part_number"
                                   value="<?php echo htmlspecialchars($part['oem_part_number'] ?? ''); ?>">
                        </div>
                        <div class="form-group">
                            <label class="form-label">Supplier Part Number</label>
                            <input type="text" class="form-control" name="supplier_part_number"
                                   value="<?php echo htmlspecialchars($part['supplier_part_number'] ?? ''); ?>">
                        </div>
                        <div class="form-group">
                            <label class="form-label">Unit of Measure</label>
                            <select class="form-control" name="unit_of_measure">
                                <option value="EA" <?php echo ($part['unit_of_measure'] ?? 'EA') === 'EA' ? 'selected' : ''; ?>>Each (EA)</option>
                                <option value="BOX" <?php echo ($part['unit_of_measure'] ?? '') === 'BOX' ? 'selected' : ''; ?>>Box</option>
                                <option value="KG" <?php echo ($part['unit_of_measure'] ?? '') === 'KG' ? 'selected' : ''; ?>>Kilogram (KG)</option>
                                <option value="L" <?php echo ($part['unit_of_measure'] ?? '') === 'L' ? 'selected' : ''; ?>>Liter (L)</option>
                                <option value="M" <?php echo ($part['unit_of_measure'] ?? '') === 'M' ? 'selected' : ''; ?>>Meter (M)</option>
                            </select>
                        </div>
                    </div>
                </div>
                
                <!-- Criticality & Classification -->
                <div class="form-section">
                    <div class="form-section-title">Criticality & Classification</div>
                    <div class="grid-2">
                        <div class="form-group">
                            <label class="form-label">Criticality Level</label>
                            <select class="form-control" name="criticality_level">
                                <option value="">Select Level</option>
                                <option value="Critical" <?php echo ($part['criticality_level'] ?? '') === 'Critical' ? 'selected' : ''; ?>>Critical</option>
                                <option value="High" <?php echo ($part['criticality_level'] ?? '') === 'High' ? 'selected' : ''; ?>>High</option>
                                <option value="Medium" <?php echo ($part['criticality_level'] ?? '') === 'Medium' ? 'selected' : ''; ?>>Medium</option>
                                <option value="Low" <?php echo ($part['criticality_level'] ?? '') === 'Low' ? 'selected' : ''; ?>>Low</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label class="form-label">ABC Classification</label>
                            <select class="form-control" name="abc_classification">
                                <option value="">Auto-calculated based on usage</option>
                                <option value="A" <?php echo ($part['abc_classification'] ?? '') === 'A' ? 'selected' : ''; ?>>A (High Value)</option>
                                <option value="B" <?php echo ($part['abc_classification'] ?? '') === 'B' ? 'selected' : ''; ?>>B (Medium Value)</option>
                                <option value="C" <?php echo ($part['abc_classification'] ?? '') === 'C' ? 'selected' : ''; ?>>C (Low Value)</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Unit Cost</label>
                            <div class="input-group">
                                <span class="input-group-text">$</span>
                                <input type="number" class="form-control" name="unit_cost" step="0.01" min="0"
                                       value="<?php echo htmlspecialchars($part['unit_cost'] ?? 0); ?>">
                            </div>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Lead Time (Days)</label>
                            <input type="number" class="form-control" name="lead_time_days" min="1"
                                   value="<?php echo htmlspecialchars($part['lead_time_days'] ?? 7); ?>">
                        </div>
                    </div>
                </div>
                
                <!-- Inventory Control -->
                <div class="form-section">
                    <div class="form-section-title">Inventory Control</div>
                    <div class="grid-2">
                        <div class="form-group">
                            <label class="form-label">Safety Stock Level</label>
                            <input type="number" class="form-control" name="safety_stock_level" min="0"
                                   value="<?php echo htmlspecialchars($part['safety_stock_level'] ?? 0); ?>">
                        </div>
                        <div class="form-group">
                            <label class="form-label">Minimum Quantity</label>
                            <input type="number" class="form-control" name="minimum_quantity" min="1"
                                   value="<?php echo htmlspecialchars($part['minimum_quantity'] ?? 1); ?>">
                        </div>
                        <div class="form-group">
                            <label class="form-label">Maximum Quantity</label>
                            <input type="number" class="form-control" name="maximum_quantity" min="1"
                                   value="<?php echo htmlspecialchars($part['maximum_quantity'] ?? 100); ?>">
                        </div>
                        <div class="form-group">
                            <label class="form-label">Reorder Point</label>
                            <input type="number" class="form-control" name="reorder_point" min="0"
                                   value="<?php echo htmlspecialchars($part['reorder_point'] ?? 0); ?>">
                        </div>
                    </div>
                </div>
                
                <!-- Equipment Compatibility Section -->
                <div class="form-section">
                    <div class="form-section-title"><i class="fas fa-sitemap"></i> Equipment Compatibility & Spares</div>
                    
                    <div class="equipment-info-section">
                        <i class="fas fa-info-circle"></i> <strong>Store Spares by Equipment:</strong><br>
                        Associate this part with equipment to automatically store it as a spare in the inventory system. This helps track and manage spares per equipment for easy assessment and monitoring.
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label"><i class="fas fa-tools"></i> Attach to Equipment</label>
                        <div class="equipment-checkbox-list">
                            <?php if (count($equipment_list) > 0): ?>
                                <?php 
                                $associated_eq_ids = [];
                                foreach ($part_equipment_spares as $spare) {
                                    $associated_eq_ids[] = $spare['equipment_id'];
                                }
                                ?>
                                <?php foreach ($equipment_list as $eq): ?>
                                    <div style="margin-bottom: 10px;">
                                        <label style="display: flex; align-items: center; gap: 8px; cursor: pointer; font-weight: normal;">
                                            <input type="checkbox" name="equipment_ids[]" value="<?php echo intval($eq['id']); ?>" 
                                                   <?php echo in_array($eq['id'], $associated_eq_ids) ? 'checked' : ''; ?>>
                                            <span style="flex: 1;">
                                                <strong><?php echo htmlspecialchars($eq['description'] ?? $eq['manufacturer'] . ' ' . $eq['model']); ?></strong>
                                                <?php if ($eq['manufacturer'] || $eq['model']): ?>
                                                    <br><small style="color: #666;">
                                                        <?php echo htmlspecialchars($eq['manufacturer']); ?> 
                                                        <?php echo htmlspecialchars($eq['model']); ?>
                                                        <?php if ($eq['serial_number']): ?>
                                                            (S/N: <?php echo htmlspecialchars($eq['serial_number']); ?>)
                                                        <?php endif; ?>
                                                    </small>
                                                <?php endif; ?>
                                            </span>
                                        </label>
                                    </div>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <p style="color: #999; text-align: center; padding: 20px;">
                                    <i class="fas fa-exclamation-triangle"></i> No equipment available. Please add equipment first.
                                </p>
                            <?php endif; ?>
                        </div>
                        <small style="color: #666; display: block; margin-top: 8px;">
                            <i class="fas fa-check-circle"></i> Select which equipment this spare part is compatible with. Selected equipment will automatically track this part in their spare inventory.
                        </small>
                    </div>
                    
                    <?php if (count($part_equipment_spares) > 0): ?>
                        <div style="margin-top: 20px; padding: 15px; background: #e8f5e9; border-radius: 4px;">
                            <h6 style="margin-bottom: 10px; color: #2e7d32;">
                                <i class="fas fa-link"></i> Currently Associated Equipment
                            </h6>
                            <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(250px, 1fr)); gap: 10px;">
                                <?php foreach ($part_equipment_spares as $spare): ?>
                                    <div style="padding: 10px; background: white; border-left: 3px solid #4caf50; border-radius: 4px;">
                                        <strong><?php echo htmlspecialchars($spare['description']); ?></strong><br>
                                        <small style="color: #666;">
                                            <?php echo htmlspecialchars($spare['manufacturer']); ?> <?php echo htmlspecialchars($spare['model']); ?>
                                            <?php if ($spare['serial_number']): ?>
                                                <br>S/N: <?php echo htmlspecialchars($spare['serial_number']); ?>
                                            <?php endif; ?>
                                        </small>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
                
                <!-- Additional Information -->
                <div class="form-section">
                    <div class="form-section-title">Additional Information</div>
                    <div class="grid-2">
                        <div class="form-group">
                            <label class="form-label">
                                <input type="checkbox" name="is_hazmat" value="1" 
                                       <?php echo (isset($part['is_hazmat']) && $part['is_hazmat']) ? 'checked' : ''; ?>>
                                Hazardous Material
                            </label>
                        </div>
                        <div class="form-group">
                            <label class="form-label">
                                <input type="checkbox" name="is_serialized" value="1"
                                       <?php echo (isset($part['is_serialized']) && $part['is_serialized']) ? 'checked' : ''; ?>>
                                Serialized (Track Serial Numbers)
                            </label>
                        </div>
                        <div class="form-group">
                            <label class="form-label">
                                <input type="checkbox" name="is_active" value="1" 
                                       <?php echo (!isset($part) || (isset($part['is_active']) && $part['is_active'])) ? 'checked' : ''; ?>>
                                Active
                            </label>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Warranty Period (Months)</label>
                        <input type="number" class="form-control" name="warranty_period_months" min="0"
                               value="<?php echo htmlspecialchars($part['warranty_period_months'] ?? 12); ?>">
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Notes</label>
                        <textarea class="form-control" name="notes" rows="4"><?php echo htmlspecialchars($part['notes'] ?? ''); ?></textarea>
                    </div>
                </div>
                
                <!-- Form Actions -->
                <div style="display: flex; gap: 10px; margin-top: 30px;">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save"></i> Save Part
                    </button>
                    <a href="parts_master.php" class="btn btn-secondary">
                        <i class="fas fa-times"></i> Cancel
                    </a>
                </div>
            </form>
        </div>
        
    <?php else: ?>
        
        <!-- Filter Bar -->
        <div class="filter-bar">
            <form method="GET" class="row g-3">
                <div class="col-md-4">
                    <input type="text" class="form-control" name="search" placeholder="Search parts..." 
                           value="<?php echo htmlspecialchars($search); ?>">
                </div>
                <div class="col-md-3">
                    <select class="form-control" name="category">
                        <option value="">All Categories</option>
                        <option value="Mechanical" <?php echo $category_filter === 'Mechanical' ? 'selected' : ''; ?>>Mechanical</option>
                        <option value="Electrical" <?php echo $category_filter === 'Electrical' ? 'selected' : ''; ?>>Electrical</option>
                        <option value="Hydraulic" <?php echo $category_filter === 'Hydraulic' ? 'selected' : ''; ?>>Hydraulic</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <select class="form-control" name="criticality">
                        <option value="">All Criticality Levels</option>
                        <option value="Critical" <?php echo $criticality_filter === 'Critical' ? 'selected' : ''; ?>>Critical</option>
                        <option value="High" <?php echo $criticality_filter === 'High' ? 'selected' : ''; ?>>High</option>
                        <option value="Medium" <?php echo $criticality_filter === 'Medium' ? 'selected' : ''; ?>>Medium</option>
                        <option value="Low" <?php echo $criticality_filter === 'Low' ? 'selected' : ''; ?>>Low</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <button type="submit" class="btn btn-primary w-100">
                        <i class="fas fa-filter"></i> Filter
                    </button>
                </div>
            </form>
        </div>
        
        <!-- Parts List -->
        <div class="list-container">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                <h3 style="margin: 0;">Parts Inventory (<?php echo count($parts_list); ?> parts)</h3>
                <a href="parts_master.php?action=create" class="btn btn-success">
                    <i class="fas fa-plus"></i> New Part
                </a>
            </div>
            
            <?php if (count($parts_list) > 0): ?>
                <div class="table-responsive">
                    <table class="table table-striped table-hover">
                        <thead>
                            <tr>
                                <th>Part Code</th>
                                <th>Part Name</th>
                                <th>Category</th>
                                <th>Criticality</th>
                                <th>ABC</th>
                                <th>Unit Cost</th>
                                <th>On Hand</th>
                                <th>Reorder Point</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($parts_list as $p): 
                                $stock = get_total_stock($p['id'], $connection);
                                $on_hand = intval($stock['total_on_hand'] ?? 0);
                            ?>
                                <tr>
                                    <td><strong><?php echo htmlspecialchars($p['part_code']); ?></strong></td>
                                    <td><?php echo htmlspecialchars($p['part_name']); ?></td>
                                    <td><?php echo htmlspecialchars($p['category'] ?? 'N/A'); ?></td>
                                    <td>
                                        <span class="badge-criticality criticality-<?php echo strtolower($p['criticality_level'] ?? 'low'); ?>">
                                            <?php echo htmlspecialchars($p['criticality_level'] ?? 'N/A'); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php if ($p['abc_classification']): ?>
                                            <span class="abc-badge abc-<?php echo strtolower($p['abc_classification']); ?>">
                                                <?php echo $p['abc_classification']; ?>
                                            </span>
                                        <?php else: ?>
                                            <span class="text-muted">—</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>$<?php echo number_format($p['unit_cost'] ?? 0, 2); ?></td>
                                    <td>
                                        <span style="<?php echo $on_hand <= intval($p['reorder_point'] ?? 0) ? 'color: #e74c3c; font-weight: 700;' : ''; ?>">
                                            <?php echo $on_hand; ?>
                                        </span>
                                    </td>
                                    <td><?php echo $p['reorder_point']; ?></td>
                                    <td>
                                        <div class="action-buttons">
                                            <a href="parts_master.php?action=edit&id=<?php echo $p['id']; ?>" class="btn btn-sm btn-info">
                                                <i class="fas fa-edit"></i> Edit
                                            </a>
                                            <a href="stock_management.php?part_id=<?php echo $p['id']; ?>" class="btn btn-sm btn-primary">
                                                <i class="fas fa-boxes"></i> Stock
                                            </a>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div class="alert alert-info">
                    <i class="fas fa-info-circle"></i> No parts found. <a href="parts_master.php?action=create">Create your first part</a>
                </div>
            <?php endif; ?>
        </div>
        
    <?php endif; ?>
    
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

</body>
</html>
