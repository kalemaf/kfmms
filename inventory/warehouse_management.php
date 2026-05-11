<?php
// Allow framing for development (localhost/127.0.0.1)
$is_localhost = isset($_SERVER['HTTP_HOST']) && preg_match('/^(localhost|127\.0\.0\.1)(:\d+)?$/', $_SERVER['HTTP_HOST']);
if ($is_localhost) {
    // In development, allow framing from localhost and remove X-Frame-Options to allow VS Code preview
    header('Access-Control-Allow-Origin: http://127.0.0.1:8000', false);
    header('Access-Control-Allow-Credentials: true', false);
} else {
    // In production, use DENY to prevent clickjacking
    header('X-Frame-Options: DENY', false);
}

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

$title = "Warehouse & Stock Management";
$action = isset($_GET['action']) ? $_GET['action'] : 'warehouses';
$warehouse_id = isset($_GET['warehouse_id']) ? intval($_GET['warehouse_id']) : null;
$part_id = isset($_GET['part_id']) ? intval($_GET['part_id']) : null;

// Handle warehouse creation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_warehouse'])) {
    try {
        $warehouse_code = $connection->real_escape_string($_POST['warehouse_code'] ?? '');
        $warehouse_name = $connection->real_escape_string($_POST['warehouse_name'] ?? '');
        $location = $connection->real_escape_string($_POST['location'] ?? '');
        $max_capacity = intval($_POST['max_capacity'] ?? 0);
        $tenant_id = (int)($_SESSION['tenant_id'] ?? 1);
        
        $query = "INSERT INTO warehouses (warehouse_code, warehouse_name, location, max_capacity, tenant_id) 
                 VALUES ('$warehouse_code', '$warehouse_name', '$location', $max_capacity, $tenant_id)";
        
        if ($connection->query($query)) {
            $_SESSION['success'] = "Warehouse created successfully!";
            header("Location: warehouse_management.php?action=warehouses");
            exit;
        } else {
            $error_msg = $connection->error ?? "Unknown database error";
            $_SESSION['error'] = "Failed to create warehouse: " . $error_msg;
            error_log("Warehouse creation error: " . $error_msg . " | Query: " . $query);
        }
    } catch (PDOException $e) {
        $_SESSION['error'] = "Database error: " . $e->getMessage();
        error_log("Warehouse creation PDO error: " . $e->getMessage());
    } catch (Exception $e) {
        $_SESSION['error'] = "Error creating warehouse: " . $e->getMessage();
        error_log("Warehouse creation error: " . $e->getMessage());
    }
}

// Handle location creation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_location'])) {
    try {
        $warehouse_id = intval($_POST['warehouse_id'] ?? 0);
        $location_code = $connection->real_escape_string($_POST['location_code'] ?? '');
        $location_name = $connection->real_escape_string($_POST['location_name'] ?? '');
        $zone = $connection->real_escape_string($_POST['zone'] ?? '');
        $aisle = $connection->real_escape_string($_POST['aisle'] ?? '');
        $rack = $connection->real_escape_string($_POST['rack'] ?? '');
        $bin = $connection->real_escape_string($_POST['bin'] ?? '');
        $max_capacity = intval($_POST['max_capacity'] ?? 0);
        $tenant_id = (int)($_SESSION['tenant_id'] ?? 1);
        
        if ($warehouse_id <= 0) {
            $_SESSION['error'] = "Invalid warehouse selected";
            error_log("Location creation error: Invalid warehouse_id=$warehouse_id");
        } else if (empty($location_code)) {
            $_SESSION['error'] = "Location code is required";
        } else {
            // Check if location already exists for this warehouse and tenant
            $check_query = "SELECT id FROM warehouse_locations 
                           WHERE warehouse_id = $warehouse_id 
                           AND location_code = '$location_code' 
                           AND tenant_id = $tenant_id LIMIT 1";
            
            $check_result = $connection->query($check_query);
            if ($check_result && $check_result->fetch(PDO::FETCH_ASSOC)) {
                $_SESSION['error'] = "A location with code '$location_code' already exists in this warehouse";
                error_log("Location creation error: Duplicate location_code '$location_code' for warehouse_id=$warehouse_id");
            } else {
                $query = "INSERT INTO warehouse_locations 
                         (warehouse_id, location_code, location_name, zone, aisle, rack, bin, max_capacity, tenant_id)
                         VALUES ($warehouse_id, '$location_code', '$location_name', '$zone', '$aisle', '$rack', '$bin', $max_capacity, $tenant_id)";
                
                if ($connection->query($query)) {
                    $_SESSION['success'] = "Location created successfully!";
                    header("Location: warehouse_management.php?action=locations&warehouse_id=$warehouse_id");
                    exit;
                } else {
                    $error_msg = $connection->error ?? "Unknown database error";
                    $_SESSION['error'] = "Failed to create location: " . $error_msg;
                    error_log("Location creation error: " . $error_msg . " | Query: " . $query);
                }
            }
        }
    } catch (PDOException $e) {
        // Handle UNIQUE constraint violations specifically
        if (strpos($e->getMessage(), 'UNIQUE') !== false) {
            $_SESSION['error'] = "A location with this code already exists in this warehouse";
        } else {
            $_SESSION['error'] = "Database error: " . $e->getMessage();
        }
        error_log("Location creation PDO error: " . $e->getMessage());
    } catch (Exception $e) {
        $_SESSION['error'] = "Error creating location: " . $e->getMessage();
        error_log("Location creation error: " . $e->getMessage());
    }
}

// Handle stock adjustment
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['adjust_stock'])) {
    $part_id = intval($_POST['part_id'] ?? 0);
    $warehouse_location_id = intval($_POST['warehouse_location_id'] ?? 0);
    $quantity_change = intval($_POST['quantity_change'] ?? 0);
    $reason = $connection->real_escape_string($_POST['reason'] ?? '');
    $user_id = $_SESSION['user'] ?? 'system';
    
    if (update_stock($part_id, $warehouse_location_id, $quantity_change, 'adjustment', 
                    null, $user_id, $reason, $connection)) {
        $_SESSION['success'] = "Stock adjusted successfully!";
    } else {
        $_SESSION['error'] = "Failed to adjust stock.";
    }
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
        .header { background: white; padding: 25px; border-radius: 8px; margin-bottom: 25px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 20px; }
        .header h1 { color: #333; margin-bottom: 5px; font-weight: 700; }
        .header p { margin-bottom: 0; color: #666; }
        .header > div:first-child { flex: 1; min-width: 300px; }
        .header > div:last-child { flex-shrink: 0; }
        .btn-outline-primary { border-color: #667eea; color: #667eea; }
        .tabs-nav { background: white; padding: 15px; border-radius: 8px; margin-bottom: 25px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        .tabs-nav a { display: inline-block; padding: 10px 20px; margin-right: 5px; text-decoration: none; color: #666; border-bottom: 3px solid transparent; }
        .tabs-nav a.active { color: #667eea; border-bottom-color: #667eea; font-weight: 600; }
        .form-container { background: white; padding: 30px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); margin-bottom: 25px; }
        .list-container { background: white; padding: 25px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); margin-bottom: 25px; }
        .table { font-size: 13px; }
        .table thead { background: #f8f9fa; }
        .table th { font-weight: 700; color: #333; border-top: none; }
        .table-hover tbody tr:hover { background: #f8f9fa; }
        .warehouse-card { background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); margin-bottom: 20px; border-left: 4px solid #667eea; }
        .warehouse-card h5 { font-weight: 700; color: #333; margin-bottom: 10px; }
        .warehouse-info { font-size: 13px; color: #666; margin-bottom: 5px; }
        .btn-primary { background: #667eea; border: none; }
        .btn-primary:hover { background: #5568d3; }
        .alert { margin-bottom: 20px; }
        .grid-2 { display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 15px; }
        .capacity-bar { height: 20px; background: #e9ecef; border-radius: 4px; overflow: hidden; }
        .capacity-fill { height: 100%; background: #667eea; transition: width 0.3s; }
        .form-label { font-weight: 600; color: #333; margin-bottom: 6px; }
    </style>
</head>
<body>

<div class="container">
    
    <!-- Header -->
    <div class="header" style="display: flex; justify-content: space-between; align-items: center;">
        <div>
            <h1><i class="fas fa-warehouse"></i> Warehouse & Stock Management</h1>
            <p>Multi-location inventory control and stock tracking</p>
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
    
    <!-- Navigation Tabs -->
    <div class="tabs-nav">
        <a href="warehouse_management.php?action=warehouses" class="<?php echo $action === 'warehouses' ? 'active' : ''; ?>">
            <i class="fas fa-building"></i> Warehouses
        </a>
        <a href="warehouse_management.php?action=locations" class="<?php echo $action === 'locations' ? 'active' : ''; ?>">
            <i class="fas fa-th"></i> Locations
        </a>
        <a href="warehouse_management.php?action=stock" class="<?php echo $action === 'stock' ? 'active' : ''; ?>">
            <i class="fas fa-boxes"></i> Stock Levels
        </a>
    </div>
    
    <?php if ($action === 'warehouses'): ?>
        
        <!-- Warehouses Management -->
        <div class="list-container">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 25px;">
                <h3 style="margin: 0;">Warehouses (<?php echo count($warehouses); ?>)</h3>
                <button class="btn btn-success" data-bs-toggle="modal" data-bs-target="#addWarehouseModal">
                    <i class="fas fa-plus"></i> New Warehouse
                </button>
            </div>
            
            <div class="grid-2">
                <?php foreach ($warehouses as $wh): ?>
                    <div class="warehouse-card">
                        <h5>
                            <i class="fas fa-warehouse"></i> <?php echo htmlspecialchars($wh['warehouse_name']); ?>
                        </h5>
                        <div class="warehouse-info">
                            <strong>Code:</strong> <?php echo htmlspecialchars($wh['warehouse_code']); ?>
                        </div>
                        <div class="warehouse-info">
                            <strong>Location:</strong> <?php echo htmlspecialchars($wh['location']); ?>
                        </div>
                        <div class="warehouse-info">
                            <strong>Locations:</strong> <?php echo $wh['location_count']; ?> zones
                        </div>
                        <div class="warehouse-info" style="margin-bottom: 15px;">
                            <strong>Capacity:</strong> <?php echo number_format($wh['current_usage']); ?> / <?php echo number_format($wh['max_capacity']); ?>
                            units
                        </div>
                        <div class="capacity-bar">
                            <div class="capacity-fill" style="width: <?php echo min(100, ($wh['current_usage'] / max(1, $wh['max_capacity'])) * 100); ?>%"></div>
                        </div>
                        <div style="margin-top: 15px;">
                            <a href="warehouse_management.php?action=locations&warehouse_id=<?php echo $wh['id']; ?>" class="btn btn-sm btn-primary">
                                <i class="fas fa-th"></i> Manage Zones
                            </a>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
        
        <!-- Add Warehouse Modal -->
        <div class="modal fade" id="addWarehouseModal" tabindex="-1">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">New Warehouse</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <form method="POST">
                        <div class="modal-body">
                            <div class="form-group" style="margin-bottom: 15px;">
                                <label class="form-label">Warehouse Code *</label>
                                <input type="text" class="form-control" name="warehouse_code" required placeholder="e.g., WH-001">
                            </div>
                            <div class="form-group" style="margin-bottom: 15px;">
                                <label class="form-label">Warehouse Name *</label>
                                <input type="text" class="form-control" name="warehouse_name" required placeholder="e.g., Main Warehouse">
                            </div>
                            <div class="form-group" style="margin-bottom: 15px;">
                                <label class="form-label">Location Address</label>
                                <input type="text" class="form-control" name="location" placeholder="Building address">
                            </div>
                            <div class="form-group">
                                <label class="form-label">Max Capacity (Units)</label>
                                <input type="number" class="form-control" name="max_capacity" min="1" value="1000">
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                            <button type="submit" name="add_warehouse" class="btn btn-primary">Create Warehouse</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        
    <?php elseif ($action === 'locations'): ?>
        
        <!-- Warehouse Locations -->
        <?php
        $isAllLocationsView = !$warehouse_id;
        $locations = [];
        $pageTitle = 'All Warehouse Locations';

        if ($warehouse_id) {
            $warehouse = array_filter($warehouses, fn($w) => $w['id'] == $warehouse_id);
            $warehouse = reset($warehouse);
            $locations = get_warehouse_locations($warehouse_id, $connection);
            $pageTitle = htmlspecialchars($warehouse['warehouse_name']) . ' - Zones & Locations';
        } else {
            $query = "SELECT wl.*, w.warehouse_name FROM warehouse_locations wl " .
                     "LEFT JOIN warehouses w ON wl.warehouse_id = w.id " .
                     "WHERE wl.is_active = 1 ORDER BY w.warehouse_name, wl.zone, wl.aisle, wl.rack, wl.bin";
            $query = apply_tenant_filter($query);
            $result = $connection->query($query);
            if ($result) {
                while ($row = $result->fetch_assoc()) {
                    $locations[] = $row;
                }
            }
        }
        ?>
        
        <div class="list-container">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 25px;">
                <div>
                    <h3 style="margin: 0;">
                        <?php if ($warehouse_id): ?>
                            <i class="fas fa-arrow-left" style="cursor: pointer; font-size: 18px;" onclick="history.back()"></i>
                        <?php endif; ?>
                        <?php echo $pageTitle; ?>
                    </h3>
                </div>
                <?php if ($warehouse_id): ?>
                    <button class="btn btn-success" data-bs-toggle="modal" data-bs-target="#addLocationModal">
                        <i class="fas fa-plus"></i> New Location
                    </button>
                <?php else: ?>
                    <a href="warehouse_management.php?action=warehouses" class="btn btn-primary">
                        <i class="fas fa-warehouse"></i> Select Warehouse
                    </a>
                <?php endif; ?>
            </div>
            
            <?php if (count($locations) > 0): ?>
                <div class="table-responsive">
                    <table class="table table-striped table-hover">
                        <thead>
                            <tr>
                                <?php if ($isAllLocationsView): ?>
                                    <th>Warehouse</th>
                                <?php endif; ?>
                                <th>Location Code</th>
                                <th>Location Name</th>
                                <th>Zone</th>
                                <th>Aisle/Rack/Bin</th>
                                <th>Capacity</th>
                                <th>Usage</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($locations as $loc): ?>
                                <tr>
                                    <?php if ($isAllLocationsView): ?>
                                        <td><?php echo htmlspecialchars($loc['warehouse_name'] ?? 'Unknown'); ?></td>
                                    <?php endif; ?>
                                    <td><strong><?php echo htmlspecialchars($loc['location_code']); ?></strong></td>
                                    <td><?php echo htmlspecialchars($loc['location_name']); ?></td>
                                    <td><?php echo htmlspecialchars($loc['zone']); ?></td>
                                    <td>
                                        A: <?php echo htmlspecialchars($loc['aisle']); ?> | 
                                        R: <?php echo htmlspecialchars($loc['rack']); ?> | 
                                        B: <?php echo htmlspecialchars($loc['bin']); ?>
                                    </td>
                                    <td><?php echo number_format($loc['max_capacity']); ?></td>
                                    <td><?php echo number_format($loc['current_usage']); ?></td>
                                    <td>
                                        <?php if ($loc['is_active']): ?>
                                            <span class="badge bg-success">Active</span>
                                        <?php else: ?>
                                            <span class="badge bg-danger">Inactive</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div class="alert alert-info">
                    <i class="fas fa-info-circle"></i> No locations created yet. Add your first location zone.
                </div>
            <?php endif; ?>
        </div>
        
        <?php if ($warehouse_id): ?>
            <!-- Add Location Modal -->
            <div class="modal fade" id="addLocationModal" tabindex="-1">
                <div class="modal-dialog">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title">New Location Zone</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                        </div>
                        <form method="POST">
                            <input type="hidden" name="warehouse_id" value="<?php echo $warehouse_id; ?>">
                            <div class="modal-body">
                                <div class="form-group" style="margin-bottom: 15px;">
                                    <label class="form-label">Location Code *</label>
                                    <input type="text" class="form-control" name="location_code" required placeholder="e.g., LOC-001">
                                </div>
                                <div class="form-group" style="margin-bottom: 15px;">
                                    <label class="form-label">Location Name</label>
                                    <input type="text" class="form-control" name="location_name" placeholder="e.g., Main Storage Zone">
                                </div>
                                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px; margin-bottom: 15px;">
                                    <div class="form-group">
                                        <label class="form-label">Zone</label>
                                        <input type="text" class="form-control" name="zone" placeholder="A, B, C">
                                    </div>
                                    <div class="form-group">
                                        <label class="form-label">Aisle</label>
                                        <input type="text" class="form-control" name="aisle" placeholder="01, 02, 03">
                                    </div>
                                </div>
                                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px; margin-bottom: 15px;">
                                    <div class="form-group">
                                        <label class="form-label">Rack</label>
                                        <input type="text" class="form-control" name="rack" placeholder="1, 2, 3">
                                    </div>
                                    <div class="form-group">
                                        <label class="form-label">Bin</label>
                                        <input type="text" class="form-control" name="bin" placeholder="A, B, C">
                                    </div>
                                </div>
                                <div class="form-group">
                                    <label class="form-label">Max Capacity (Units)</label>
                                    <input type="number" class="form-control" name="max_capacity" min="1" value="500">
                                </div>
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                <button type="submit" name="add_location" class="btn btn-primary">Create Location</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        <?php endif; ?>
        
    <?php elseif ($action === 'stock'): ?>
        
        <!-- Stock Levels -->
        <div class="list-container">
            <h3 style="margin-bottom: 20px;">
                <i class="fas fa-boxes"></i> Stock Levels Across All Locations
            </h3>
            
            <?php
            $query = "SELECT pm.id, pm.part_code, pm.part_name, pm.unit_cost,
                             SUM(sl.quantity_on_hand) as total_on_hand,
                             SUM(sl.quantity_reserved) as total_reserved,
                             SUM(sl.quantity_available) as total_available,
                             SUM(sl.quantity_on_order) as total_on_order
                      FROM parts_master pm
                      LEFT JOIN stock_locales sl ON pm.id = sl.part_id
                      WHERE pm.is_active = 1
                      GROUP BY pm.id
                      ORDER BY total_on_hand DESC";
            $query = apply_tenant_filter($query);
            $result = $connection->query($query);
            $stock_data = [];
            if ($result) {
                while ($row = $result->fetch_assoc()) {
                    $stock_data[] = $row;
                }
            }
            ?>
            
            <div class="table-responsive">
                <table class="table table-striped table-hover">
                    <thead>
                        <tr>
                            <th>Part Code</th>
                            <th>Part Name</th>
                            <th>On Hand</th>
                            <th>Reserved</th>
                            <th>Available</th>
                            <th>On Order</th>
                            <th>Unit Cost</th>
                            <th>Total Value</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($stock_data as $stock): ?>
                            <tr>
                                <td><strong><?php echo htmlspecialchars($stock['part_code']); ?></strong></td>
                                <td><?php echo htmlspecialchars($stock['part_name']); ?></td>
                                <td><?php echo intval($stock['total_on_hand'] ?? 0); ?></td>
                                <td><?php echo intval($stock['total_reserved'] ?? 0); ?></td>
                                <td><?php echo intval($stock['total_available'] ?? 0); ?></td>
                                <td><?php echo intval($stock['total_on_order'] ?? 0); ?></td>
                                <td>$<?php echo number_format($stock['unit_cost'] ?? 0, 2); ?></td>
                                <td>$<?php echo number_format((intval($stock['total_on_hand'] ?? 0) * floatval($stock['unit_cost'] ?? 0)), 2); ?></td>
                                <td>
                                    <a href="#" class="btn btn-sm btn-primary" data-bs-toggle="modal" 
                                       data-bs-target="#adjustStockModal"
                                       onclick="prepareAdjustment(<?php echo $stock['id']; ?>, '<?php echo htmlspecialchars($stock['part_code']); ?>')">
                                        <i class="fas fa-plus-minus"></i> Adjust
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        
        <!-- Adjust Stock Modal -->
        <div class="modal fade" id="adjustStockModal" tabindex="-1">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">Adjust Stock Quantity</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <form method="POST">
                        <div class="modal-body">
                            <div class="form-group" style="margin-bottom: 15px;">
                                <label class="form-label">Part Code</label>
                                <input type="text" class="form-control" id="partCodeDisplay" readonly>
                            </div>
                            <input type="hidden" id="partIdField" name="part_id">
                            <div class="form-group" style="margin-bottom: 15px;">
                                <label class="form-label">Warehouse Location *</label>
                                <select class="form-control" id="locationSelect" name="warehouse_location_id" required>
                                    <option value="">Select Location...</option>
                                    <?php
                                    $loc_query = "SELECT wl.id, w.warehouse_name, wl.location_code 
                                                 FROM warehouse_locations wl
                                                 JOIN warehouses w ON wl.warehouse_id = w.id
                                                 ORDER BY w.warehouse_name, wl.location_code";                                    $loc_query = apply_tenant_filter($loc_query);                                    $loc_result = $connection->query($loc_query);
                                    if ($loc_result) {
                                        while ($loc = $loc_result->fetch_assoc()) {
                                            echo "<option value='" . $loc['id'] . "'>" . htmlspecialchars($loc['warehouse_name'] . " - " . $loc['location_code']) . "</option>";
                                        }
                                    }
                                    ?>
                                </select>
                            </div>
                            <div class="form-group" style="margin-bottom: 15px;">
                                <label class="form-label">Quantity Change *</label>
                                <input type="number" class="form-control" name="quantity_change" required placeholder="Positive for add, negative for remove">
                            </div>
                            <div class="form-group">
                                <label class="form-label">Reason *</label>
                                <select class="form-control" name="reason" required>
                                    <option value="">Select reason...</option>
                                    <option value="Physical Count">Physical Count</option>
                                    <option value="Damaged">Damaged/Scrap</option>
                                    <option value="Lost">Lost/Theft">
                                    <option value="Return">Supplier Return</option>
                                    <option value="Manual Adjustment">Manual Adjustment</option>
                                </select>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                            <button type="submit" name="adjust_stock" class="btn btn-primary">Adjust Stock</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        
    <?php endif; ?>
    
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
function prepareAdjustment(partId, partCode) {
    document.getElementById('partIdField').value = partId;
    document.getElementById('partCodeDisplay').value = partCode;
}
</script>

</body>
</html>
