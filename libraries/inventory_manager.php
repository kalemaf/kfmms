<?php
/**
 * Inventory Management Helper Library
 * 
 * Handles all inventory operations:
 * - Stock management
 * - Work order parts integration  
 * - Purchase request/order workflow
 * - Goods receipt processing
 * - Vendor management
 * - Inventory transactions & auditing
 */

// ============================================================================
// PARTS MASTER FUNCTIONS
// ============================================================================

/**
 * Create or update a part in the parts master
 */
function save_part($data, $connection) {
    $part_id = isset($data['id']) && $data['id'] > 0 ? intval($data['id']) : null;
    
    $fields = [
        'part_code', 'part_number', 'part_name', 'description', 'category',
        'sub_category', 'criticality_level', 'abc_classification', 'manufacturer',
        'oem_part_number', 'supplier_part_number', 'unit_of_measure', 'unit_cost',
        'warranty_period_months', 'safety_stock_level', 'minimum_quantity',
        'maximum_quantity', 'reorder_point', 'lead_time_days', 'is_hazmat',
        'is_serialized', 'is_active', 'asset_compatibility', 'notes'
    ];
    
    // Build values array
    $values = [];
    foreach ($fields as $field) {
        if ($field === 'is_hazmat' || $field === 'is_serialized') {
            // Always set to 0 or 1
            $val = isset($data[$field]) && $data[$field] ? 1 : 0;
            $values[$field] = $connection->real_escape_string($val);
        } else {
            $values[$field] = $connection->real_escape_string($data[$field] ?? '');
        }
    }
    
    if ($part_id) {
        // Update existing part
        $tenant_id = function_exists('tenant_id') ? tenant_id() : 0;
        $set_clause = implode(', ', array_map(fn($f) => "`$f` = '{$values[$f]}'", $fields));
        $query = "UPDATE parts_master SET $set_clause, updated_at = NOW(), tenant_id = $tenant_id WHERE id = $part_id";
        $result = $connection->query($query);
        return $result ? $part_id : false;
    } else {
        // Insert new part
        $tenant_id = function_exists('tenant_id') ? tenant_id() : 0;
        $cols = '`' . implode('`, `', $fields) . '`, `tenant_id`';
        $vals = "'" . implode("', '", $values) . "', $tenant_id";
        $query = "INSERT INTO parts_master ($cols) VALUES ($vals)";
        $result = $connection->query($query);
        return $result ? $connection->insert_id : false;
    }
}

/**
 * Get part details
 */
function get_part($part_id, $connection) {
    global $db_type;
    $part_id = intval($part_id);
    $query = "SELECT * FROM parts_master WHERE id = $part_id";
    // Apply tenant filtering
    $query = apply_tenant_filter($query);
    $result = $connection->query($query);
    if (!$result) return null;
    return ($db_type === 'sqlite') ? $result->fetch(PDO::FETCH_ASSOC) : $result->fetch_assoc();
}

/**
 * Get all parts with optional filters
 */
function get_parts($connection, $filters = []) {
    global $db_type;
    $where = "WHERE is_active = 1";
    
    if (!empty($filters['category'])) {
        $cat = ($db_type === 'sqlite') ? str_replace("'", "''", $filters['category']) : $connection->real_escape_string($filters['category']);
        $where .= " AND category = '$cat'";
    }
    
    if (!empty($filters['criticality'])) {
        $crit = ($db_type === 'sqlite') ? str_replace("'", "''", $filters['criticality']) : $connection->real_escape_string($filters['criticality']);
        $where .= " AND criticality_level = '$crit'";
    }
    
    if (!empty($filters['search'])) {
        $search = ($db_type === 'sqlite') ? str_replace("'", "''", $filters['search']) : $connection->real_escape_string($filters['search']);
        $where .= " AND (part_code LIKE '%$search%' OR part_name LIKE '%$search%')";
    }
    
    $query = "SELECT * FROM parts_master $where ORDER BY part_name ASC";
    // Apply tenant filtering
    $query = apply_tenant_filter($query);
    $result = $connection->query($query);
    
    $parts = [];
    if ($result) {
        while ($row = ($db_type === 'sqlite') ? $result->fetch(PDO::FETCH_ASSOC) : $result->fetch_assoc()) {
            $parts[] = $row;
        }
    }
    return $parts;
}

// ============================================================================
// CONSUMABLES & MATERIALS MANAGEMENT
// ============================================================================

function get_consumable_categories() {
    return [
        'Fasteners' => ['Bolts', 'Nuts', 'Washers', 'Studs'],
        'Lubricants' => ['Grease', 'Oil'],
        'Electrical' => ['Fuses', 'Cables'],
        'Production materials' => ['Sand', 'Cement', 'Chemicals']
    ];
}

function get_consumables($connection) {
    global $db_type;
    $query = "SELECT * FROM consumables WHERE is_active = 1 ORDER BY category, subcategory, name";
    // Apply tenant filtering
    $query = apply_tenant_filter($query);
    $result = $connection->query($query);
    $consumables = [];
    if ($result) {
        while ($row = ($db_type === 'sqlite') ? $result->fetch(PDO::FETCH_ASSOC) : $result->fetch_assoc()) {
            $consumables[] = $row;
        }
    }
    return $consumables;
}

function get_consumable($consumable_id, $connection) {
    global $db_type;
    $consumable_id = intval($consumable_id);
    $query = "SELECT * FROM consumables WHERE id = $consumable_id LIMIT 1";
    // Apply tenant filtering
    $query = apply_tenant_filter($query);
    $result = $connection->query($query);
    
    if (!$result) {
        return null;
    }
    
    if ($db_type === 'sqlite') {
        return $result->fetch(PDO::FETCH_ASSOC);
    } else {
        return $result->fetch_assoc();
    }
}

function save_consumable_item($data, $connection) {
    global $db_type;
    $id = isset($data['id']) ? intval($data['id']) : 0;
    $tenant_id = function_exists('tenant_id') ? tenant_id() : 0;

    $timestamp_func = ($db_type === 'sqlite') ? 'CURRENT_TIMESTAMP' : 'NOW()';
    $name = $connection->real_escape_string(trim($data['name'] ?? ''));
    $category = $connection->real_escape_string(trim($data['category'] ?? ''));
    $subcategory = $connection->real_escape_string(trim($data['subcategory'] ?? ''));
    $unit = $connection->real_escape_string(trim($data['unit'] ?? 'pcs'));
    $location = $connection->real_escape_string(trim($data['location'] ?? ''));
    $warehouse_location_id = intval($data['warehouse_location_id'] ?? 0);
    $supplier = $connection->real_escape_string(trim($data['supplier'] ?? ''));
    $description = $connection->real_escape_string(trim($data['description'] ?? ''));
    $current_stock = intval($data['current_stock'] ?? 0);
    $min_stock = intval($data['min_stock'] ?? 0);
    $cost_per_unit = floatval($data['cost_per_unit'] ?? 0);
    $is_active = isset($data['is_active']) && $data['is_active'] ? 1 : 1;

    if ($id > 0) {
        $query = "UPDATE consumables SET
            name = '$name',
            category = '$category',
            subcategory = '$subcategory',
            unit = '$unit',
            location = '$location',
            warehouse_location_id = " . ($warehouse_location_id > 0 ? $warehouse_location_id : 'NULL') . ",
            supplier = '$supplier',
            description = '$description',
            current_stock = $current_stock,
            min_stock = $min_stock,
            cost_per_unit = $cost_per_unit,
            is_active = $is_active,
            tenant_id = $tenant_id,
            last_updated = $timestamp_func
            WHERE id = $id";
        return $connection->query($query) ? $id : false;
    }

    $query = "INSERT INTO consumables (
            name, category, subcategory, unit, location, warehouse_location_id, supplier,
            description, min_stock, current_stock, cost_per_unit,
            last_updated, created_at, is_active, tenant_id
        ) VALUES (
            '$name', '$category', '$subcategory', '$unit', '$location', " . ($warehouse_location_id > 0 ? $warehouse_location_id : 'NULL') . ", '$supplier',
            '$description', $min_stock, $current_stock, $cost_per_unit,
            $timestamp_func, $timestamp_func, $is_active, $tenant_id
        )";

    $result = $connection->query($query);
    if ($result) {
        return ($db_type === 'sqlite') ? $connection->lastInsertId() : $connection->insert_id;
    }
    return false;
}

function record_consumable_usage($consumable_id, $quantity_used, $work_order_id, $notes, $connection) {
    global $db_type;
    $consumable_id = intval($consumable_id);
    $quantity_used = floatval($quantity_used);
    $work_order_id = intval($work_order_id);
    $notes = trim($notes ?? '');
    
    // Escape notes properly based on database type
    if ($db_type === 'sqlite') {
        $notes = str_replace("'", "''", $notes);
    } else {
        $notes = $connection->real_escape_string($notes);
    }
    
    $timestamp_func = ($db_type === 'sqlite') ? 'CURRENT_TIMESTAMP' : 'NOW()';

    if ($consumable_id <= 0 || $quantity_used <= 0) {
        return false;
    }

    $consumable = get_consumable($consumable_id, $connection);
    if (!$consumable) {
        return false;
    }

    $new_stock = max(0, intval($consumable['current_stock'] ?? 0) - $quantity_used);

    // Prevent rapid duplicate usage records when the user submits the same form twice.
    if ($db_type === 'sqlite') {
        $duplicate_check = "SELECT COUNT(*) AS count FROM consumable_usage
            WHERE consumable_id = $consumable_id
              AND quantity_used = $quantity_used
              AND work_order_id = $work_order_id
              AND tenant_id = " . (int)($_SESSION['tenant_id'] ?? 1) . "
              AND notes = '$notes'
              AND usage_date >= datetime('now', '-10 seconds')";
    } else {
        $duplicate_check = "SELECT COUNT(*) AS count FROM consumable_usage
            WHERE consumable_id = $consumable_id
              AND quantity_used = $quantity_used
              AND work_order_id = $work_order_id
              AND tenant_id = " . (int)($_SESSION['tenant_id'] ?? 1) . "
              AND notes = '$notes'
              AND usage_date >= DATE_SUB(NOW(), INTERVAL 10 SECOND)";
    }

    $dup_result = $connection->query($duplicate_check);
    if ($dup_result) {
        if ($db_type === 'sqlite') {
            $dup_row = $dup_result->fetch(PDO::FETCH_ASSOC);
        } else {
            $dup_row = $dup_result->fetch_assoc();
        }
        if (intval($dup_row['count'] ?? 0) > 0) {
            return false;
        }
    }

    // Get tenant_id for multi-tenant support
    $tenant_id = (int)($_SESSION['tenant_id'] ?? 1);
    
    $usage_query = "INSERT INTO consumable_usage (
            consumable_id, quantity_used, work_order_id, usage_date, notes, created_at, tenant_id
        ) VALUES (
            $consumable_id, $quantity_used, $work_order_id, $timestamp_func, '$notes', $timestamp_func, $tenant_id
        )";

    $update_query = "UPDATE consumables SET current_stock = $new_stock, last_updated = $timestamp_func WHERE id = $consumable_id";

    $result = $connection->query($usage_query);
    if ($result) {
        $connection->query($update_query);
        return true;
    }
    return false;
}

function get_consumable_usage($connection, $limit = 100) {
    global $db_type;
    $query = "SELECT cu.*, c.name AS consumable_name FROM consumable_usage cu
              LEFT JOIN consumables c ON cu.consumable_id = c.id
              ORDER BY cu.usage_date DESC LIMIT $limit";
    // Apply tenant filtering
    $query = apply_tenant_filter($query);
    $result = $connection->query($query);
    $usage = [];
    if ($result) {
        while ($row = ($db_type === 'sqlite') ? $result->fetch(PDO::FETCH_ASSOC) : $result->fetch_assoc()) {
            $usage[] = $row;
        }
    }
    return $usage;
}

/**
 * Record consumable usage for a PM (Professional Maintenance)
 * Called when a PM work order is generated or completed
 * Automatically reduces stock for all consumables required by the PM
 */
function consume_pm_consumables($pm_id, $work_order_id, $connection) {
    global $db_type;
    
    $pm_id = intval($pm_id);
    $work_order_id = intval($work_order_id);
    
    if ($pm_id <= 0 || $work_order_id <= 0) {
        return false;
    }
    
    // Get all consumables required for this PM
    $query = "SELECT pc.*, c.name FROM pm_consumables pc
              LEFT JOIN consumables c ON pc.consumable_id = c.id
              WHERE pc.pm_id = $pm_id";
    
    $result = $connection->query($query);
    if (!$result) {
        return false;
    }
    
    $consumed_any = false;
    
    if ($db_type === 'sqlite') {
        while ($row = $result->fetch(PDO::FETCH_ASSOC)) {
            $consumable_id = intval($row['consumable_id']);
            $qty_required = floatval($row['quantity_required']);
            $notes = 'PM #' . $pm_id . ' - Work Order #' . $work_order_id;
            
            // Record the consumption
            if (record_consumable_usage($consumable_id, $qty_required, $work_order_id, $notes, $connection)) {
                $consumed_any = true;
            }
        }
    } else {
        // MySQL
        while ($row = $result->fetch_assoc()) {
            $consumable_id = intval($row['consumable_id']);
            $qty_required = floatval($row['quantity_required']);
            $notes = 'PM #' . $pm_id . ' - Work Order #' . $work_order_id;
            
            // Record the consumption
            if (record_consumable_usage($consumable_id, $qty_required, $work_order_id, $notes, $connection)) {
                $consumed_any = true;
            }
        }
    }
    
    return $consumed_any;
}

/**
 * Add a consumable requirement to a work order
 * Called when setting up consumable needs for a work order
 */
function add_consumable_to_work_order($work_order_id, $consumable_id, $quantity, $connection, $unit_cost = 0, $notes = '') {
    global $db_type;
    
    $work_order_id = intval($work_order_id);
    $consumable_id = intval($consumable_id);
    $quantity = floatval($quantity);
    $unit_cost = floatval($unit_cost);
    $tenant_id = (int)($_SESSION['tenant_id'] ?? 1);
    
    if ($work_order_id <= 0 || $consumable_id <= 0 || $quantity <= 0) {
        return false;
    }
    
    $notes = sanitize_input($notes);
    
    if ($db_type === 'sqlite') {
        $sql = "INSERT INTO work_order_consumables (work_order_id, consumable_id, quantity_required, unit_cost, notes, tenant_id)
                VALUES ($work_order_id, $consumable_id, $quantity, $unit_cost, '$notes', $tenant_id)";
    } else {
        $sql = "INSERT INTO work_order_consumables (work_order_id, consumable_id, quantity_required, unit_cost, notes, tenant_id)
                VALUES ($work_order_id, $consumable_id, $quantity, $unit_cost, '$notes', $tenant_id)";
    }
    
    return $connection->query($sql) ? true : false;
}

/**
 * Get all consumables required for a work order
 */
function get_work_order_consumables($work_order_id, $connection) {
    global $db_type;
    
    $work_order_id = intval($work_order_id);
    
    if ($db_type === 'sqlite') {
        $query = "SELECT woc.*, c.name, c.unit, c.category, c.current_stock
                  FROM work_order_consumables woc
                  LEFT JOIN consumables c ON woc.consumable_id = c.id
                  WHERE woc.work_order_id = $work_order_id
                  ORDER BY woc.created_at ASC";
    } else {
        $query = "SELECT woc.*, c.name, c.unit, c.category, c.current_stock
                  FROM work_order_consumables woc
                  LEFT JOIN consumables c ON woc.consumable_id = c.id
                  WHERE woc.work_order_id = $work_order_id
                  ORDER BY woc.created_at ASC";
    }
    
    $result = $connection->query($query);
    $consumables = [];
    
    if ($result) {
        if ($db_type === 'sqlite') {
            while ($row = $result->fetch(PDO::FETCH_ASSOC)) {
                $consumables[] = $row;
            }
        } else {
            while ($row = $result->fetch_assoc()) {
                $consumables[] = $row;
            }
        }
    }
    
    return $consumables;
}

/**
 * Consume all consumables required for a work order
 * Called when work order is marked as completed
 * Automatically reduces stock and marks consumables as consumed
 */
function consume_work_order_consumables($work_order_id, $connection) {
    global $db_type;
    
    $work_order_id = intval($work_order_id);
    
    if ($work_order_id <= 0) {
        return false;
    }
    
    try {
        // Get all unconsumed consumables for this work order (with tenant filtering)
        $tenant_id = (int)($_SESSION['tenant_id'] ?? 1);
        $query = "SELECT woc.*, c.name FROM work_order_consumables woc
                  LEFT JOIN consumables c ON woc.consumable_id = c.id
                  WHERE woc.work_order_id = $work_order_id AND woc.is_consumed = 0 AND woc.tenant_id = $tenant_id";
        
        $result = $connection->query($query);
        if (!$result) {
            error_log("Query failed in consume_work_order_consumables: " . $query);
            return false;
        }
        
        $consumed_count = 0;
        $timestamp_func = ($db_type === 'sqlite') ? "datetime('now')" : "NOW()";
        
        if ($db_type === 'sqlite') {
            while ($row = $result->fetch(PDO::FETCH_ASSOC)) {
                $woc_id = intval($row['id']);
                $consumable_id = intval($row['consumable_id']);
                $qty_required = floatval($row['quantity_required']);
                $notes = 'Work Order #' . $work_order_id;
                
                // Record the consumption in consumable_usage table
                if (function_exists('record_consumable_usage') && record_consumable_usage($consumable_id, $qty_required, $work_order_id, $notes, $connection)) {
                    // Mark this item as consumed in work_order_consumables
                    $updateSQL = "UPDATE work_order_consumables 
                                  SET is_consumed = 1, quantity_used = $qty_required, consumed_at = $timestamp_func
                                  WHERE id = $woc_id";
                    if ($connection->query($updateSQL)) {
                        $consumed_count++;
                    }
                }
            }
        } else {
            while ($row = $result->fetch_assoc()) {
                $woc_id = intval($row['id']);
                $consumable_id = intval($row['consumable_id']);
                $qty_required = floatval($row['quantity_required']);
                $notes = 'Work Order #' . $work_order_id;
                
                // Record the consumption in consumable_usage table
                if (function_exists('record_consumable_usage') && record_consumable_usage($consumable_id, $qty_required, $work_order_id, $notes, $connection)) {
                    // Mark this item as consumed in work_order_consumables
                    $updateSQL = "UPDATE work_order_consumables 
                                  SET is_consumed = 1, quantity_used = $qty_required, consumed_at = $timestamp_func
                                  WHERE id = $woc_id";
                    if ($connection->query($updateSQL)) {
                        $consumed_count++;
                    }
                }
            }
        }
        
        return $consumed_count > 0;
    } catch (Exception $e) {
        error_log("Exception in consume_work_order_consumables: " . $e->getMessage());
        return false;
    }
}


// ============================================================================
// WAREHOUSE & STOCK MANAGEMENT FUNCTIONS
// ============================================================================

/**
 * Get all warehouses
 */
function get_warehouses($connection) {
    global $db_type;
    $query = "SELECT w.*, COUNT(wl.id) as location_count FROM warehouses w
              LEFT JOIN warehouse_locations wl ON w.id = wl.warehouse_id
              WHERE w.is_active = 1
              GROUP BY w.id
              ORDER BY w.warehouse_name";
    // Apply tenant filtering
    $query = apply_tenant_filter($query);
    $result = $connection->query($query);

    $warehouses = [];
    if ($result) {
        while ($row = ($db_type === 'sqlite') ? $result->fetch(PDO::FETCH_ASSOC) : $result->fetch_assoc()) {
            $warehouses[] = $row;
        }
    }
    return $warehouses;
}

/**
 * Get warehouse locations
 */
function get_warehouse_locations($warehouse_id, $connection) {
    global $db_type;
    $warehouse_id = intval($warehouse_id);
    $query = "SELECT * FROM warehouse_locations 
              WHERE warehouse_id = $warehouse_id AND is_active = 1
              ORDER BY zone, aisle, rack, bin";
    // Apply tenant filtering
    $query = apply_tenant_filter($query);
    $result = $connection->query($query);
    
    $locations = [];
    if ($result) {
        while ($row = ($db_type === 'sqlite') ? $result->fetch(PDO::FETCH_ASSOC) : $result->fetch_assoc()) {
            $locations[] = $row;
        }
    }
    return $locations;
}

/**
 * Get ALL warehouse locations across all warehouses for the current tenant
 */
function get_all_warehouse_locations($connection) {
    global $db_type;
    $query = "SELECT wl.*, w.warehouse_name FROM warehouse_locations wl
              LEFT JOIN warehouses w ON wl.warehouse_id = w.id
              WHERE wl.is_active = 1
              ORDER BY w.warehouse_name, wl.zone, wl.aisle, wl.rack, wl.bin";
    // Apply tenant filtering
    $query = apply_tenant_filter($query);
    $result = $connection->query($query);
    
    $locations = [];
    if ($result) {
        while ($row = ($db_type === 'sqlite') ? $result->fetch(PDO::FETCH_ASSOC) : $result->fetch_assoc()) {
            $locations[] = $row;
        }
    }
    return $locations;
}

/**
 * Get current stock at a location
 */
function get_stock_at_location($part_id, $warehouse_location_id, $connection) {
    $part_id = intval($part_id);
    $location_id = intval($warehouse_location_id);
    
    $query = "SELECT * FROM stock_locales 
              WHERE part_id = $part_id AND warehouse_location_id = $location_id";
    // Apply tenant filtering
    $query = apply_tenant_filter($query);
    $result = $connection->query($query);
    if ($result instanceof PDOStatement) {
        $row = $result->fetch(PDO::FETCH_ASSOC);
        return $row !== false ? $row : null;
    }
    return $result ? $result->fetch_assoc() : null;
}

/**
 * Get total stock for a part across all locations
 */
function get_total_stock($part_id, $connection) {
    $part_id = intval($part_id);
    $query = "SELECT 
                SUM(quantity_on_hand) as total_on_hand,
                SUM(quantity_reserved) as total_reserved,
                SUM(quantity_available) as total_available,
                SUM(quantity_on_order) as total_on_order
              FROM stock_locales WHERE part_id = $part_id";
    // Apply tenant filtering
    $query = apply_tenant_filter($query);
    $result = $connection->query($query);
    if ($result instanceof PDOStatement) {
        $row = $result->fetch(PDO::FETCH_ASSOC);
        return $row !== false ? $row : null;
    }
    return $result ? $result->fetch_assoc() : null;
}

/**
 * Update stock at a location
 */
function update_stock($part_id, $warehouse_location_id, $quantity_change, 
                      $transaction_type, $reference_id = null, $user_id = null, 
                      $reason = '', $connection = null) {
    global $c;
    $connection = $connection ?? $c;
    
    $part_id = intval($part_id);
    $location_id = intval($warehouse_location_id);
    $qty = intval($quantity_change);
    $user_id = intval($user_id ?? 0);
    
    // Start transaction
    $connection->beginTransaction();
    
    try {
        // Get current stock BEFORE update
        $stock = get_stock_at_location($part_id, $location_id, $connection);
        $quantity_before = intval($stock['quantity_on_hand'] ?? 0);
        $quantity_reserved_before = intval($stock['quantity_reserved'] ?? 0);
        
        // Calculate new quantities
        $new_qty = max(0, $quantity_before + $qty);
        $new_reserved = $quantity_reserved_before;
        $new_available = max(0, $new_qty - $new_reserved);
        
        // Check if record exists
        $check_query = "SELECT id FROM stock_locales WHERE part_id = $part_id AND warehouse_location_id = $location_id AND tenant_id = " . tenant_id();
        $check = $connection->query($check_query);
        $exists = $check && (($check instanceof PDOStatement) ? $check->rowCount() > 0 : $check->num_rows > 0);
        
        if ($exists) {
            // Update existing record
            $update_query = "UPDATE stock_locales 
                           SET quantity_on_hand = $new_qty,
                               quantity_reserved = $new_reserved,
                               last_received_date = CURRENT_TIMESTAMP,
                               updated_at = CURRENT_TIMESTAMP
                           WHERE part_id = $part_id AND warehouse_location_id = $location_id AND tenant_id = " . tenant_id();
            
            if (!$connection->query($update_query)) {
                $error_msg = ($connection instanceof PDO) ? json_encode($connection->errorInfo()) : $connection->error;
                throw new Exception('Failed to update stock locale: ' . $error_msg);
            }
        } else {
            // Insert new record
            $insert_query = "INSERT INTO stock_locales 
                           (part_id, warehouse_location_id, quantity_on_hand, quantity_reserved, tenant_id, last_received_date, updated_at)
                           VALUES ($part_id, $location_id, $new_qty, $new_reserved, " . tenant_id() . ", CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)";
            
            if (!$connection->query($insert_query)) {
                $error_msg = ($connection instanceof PDO) ? json_encode($connection->errorInfo()) : $connection->error;
                throw new Exception('Failed to insert stock locale: ' . $error_msg);
            }
        }
        
        // Record transaction ONCE per call
        $quantity_after = $new_qty;
        $resulting_balance = $new_available;

        $trans_query = "INSERT INTO inventory_transactions 
                      (part_id, transaction_type, reference_type, reference_id, warehouse_location_id, quantity_change, quantity_before, quantity_changed, quantity_after, resulting_balance, reason, notes, user_id, tenant_id)
                      VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        
        if ($connection instanceof PDO) {
            $stmt = $connection->prepare($trans_query);
            $stmt->execute([
                $part_id,
                $transaction_type,
                $transaction_type,
                ($reference_id ? intval($reference_id) : 0),
                $location_id,
                $qty,
                $quantity_before,
                $qty,
                $quantity_after,
                $resulting_balance,
                $reason,
                $reason,
                ($user_id ? $user_id : null),
                tenant_id()
            ]);
        } else {
            if (!$connection->query($trans_query)) {
                throw new Exception('Failed to insert transaction: ' . $connection->error);
            }
        }

        // Update parts master total
        $new_total = 0;
        $stock_query = "SELECT COALESCE(SUM(quantity_on_hand), 0) as total FROM stock_locales WHERE part_id = $part_id AND tenant_id = " . tenant_id();
        $stock_result = $connection->query($stock_query);
        if ($stock_result instanceof PDOStatement) {
            $row = $stock_result->fetch(PDO::FETCH_ASSOC);
            $new_total = intval($row['total'] ?? 0);
        } elseif ($stock_result) {
            $row = $stock_result->fetch_assoc();
            $new_total = intval($row['total'] ?? 0);
        }
        
        $update_query = "UPDATE parts_master SET total_on_hand = $new_total WHERE id = $part_id AND tenant_id = " . tenant_id();
        if (!$connection->query($update_query)) {
            $error_msg = ($connection instanceof PDO) ? json_encode($connection->errorInfo()) : $connection->error;
            throw new Exception('Failed to update parts_master: ' . $error_msg);
        }
        
        $connection->commit();
        return true;
    } catch (Exception $e) {
        $connection->rollback();
        error_log("Stock update error: " . $e->getMessage());
        return false;
    }
}

/**
 * Reserve stock for a work order
 */
function reserve_stock($wo_id, $part_id, $quantity, $connection = null) {
    global $c;
    $connection = $connection ?? $c;
    
    $wo_id = intval($wo_id);
    $part_id = intval($part_id);
    $qty = intval($quantity);
    
    // Get available stock from default location (first available)
    $query = "SELECT sl.* FROM stock_locales sl
              WHERE sl.part_id = $part_id 
              AND sl.quantity_available >= $qty
              ORDER BY sl.quantity_available DESC LIMIT 1";
    $result = $connection->query($query);
    
    if ($result && $result->num_rows > 0) {
        $stock = $result->fetch_assoc();

        // Update stock locale
        $new_reserved = intval($stock['quantity_reserved']) + $qty;
        $update = "UPDATE stock_locales 
                  SET quantity_reserved = $new_reserved,
                      updated_at = NOW()
                  WHERE id = " . intval($stock['id']);
        $connection->query($update);

        // Record transaction
        $quantity_before = intval($stock['quantity_on_hand']);
        $quantity_after = intval($stock['quantity_on_hand']);
        $resulting_balance = intval($new_reserved);

        $query = "INSERT INTO inventory_transactions 
                 (part_id, transaction_type, reference_type, reference_id, warehouse_location_id, quantity_change, quantity_before, quantity_changed, quantity_after, resulting_balance, reason, notes, user_id, created_at)
                 VALUES (" . intval($part_id) . ", 'reserve', 'work_order', $wo_id, " . intval($stock['warehouse_location_id']) . ", $qty, $quantity_before, $qty, $quantity_after, $resulting_balance, 'WO stock reservation', 'WO stock reservation', " . intval($_SESSION['user_id'] ?? 0) . ", NOW())";
        $connection->query($query);

        // Update or insert WO parts record
        $query = "SELECT * FROM wo_parts WHERE wo_id = $wo_id AND part_id = $part_id";
        $result = $connection->query($query);

        if ($result && $result->num_rows > 0) {
            $update = "UPDATE wo_parts 
                      SET quantity_reserved = $qty, status = 'reserved', updated_at = NOW()
                      WHERE wo_id = $wo_id AND part_id = $part_id";
        } else {
            $update = "INSERT INTO wo_parts 
                      (wo_id, part_id, quantity_required, quantity_reserved, status, created_at, updated_at)
                      VALUES ($wo_id, $part_id, $qty, $qty, 'reserved', NOW(), NOW())";
        }

        $result = $connection->query($update);

        // Re-sync total stock in parts_master
        $stock_total = $connection->query("SELECT SUM(quantity_on_hand) AS total FROM stock_locales WHERE part_id = $part_id");
        if ($stock_total && ($row = $stock_total->fetch_assoc())) {
            $connection->query("UPDATE parts_master SET total_on_hand = " . intval($row['total']) . " WHERE id = $part_id");
        }

        return $result ? true : false;
    }
    
    return false;
}

/**
 * Issue stock from a work order
 */
function issue_stock($wo_id, $part_id, $quantity, $user_id, $connection = null) {
    global $c;
    $connection = $connection ?? $c;
    
    $wo_id = intval($wo_id);
    $part_id = intval($part_id);
    $qty = intval($quantity);
    $user_id = intval($user_id);
    
    // Get reserved stock
    $query = "SELECT sl.* FROM stock_locales sl
              WHERE sl.part_id = $part_id AND sl.quantity_reserved >= $qty
              ORDER BY sl.quantity_reserved DESC LIMIT 1";
    $result = $connection->query($query);
    
    if ($result && $result->num_rows > 0) {
        $stock = $result->fetch_assoc();
        
        $connection->beginTransaction();
        try {
            // Update stock
            $new_on_hand = max(0, intval($stock['quantity_on_hand']) - $qty);
            $new_reserved = max(0, intval($stock['quantity_reserved']) - $qty);
            $new_available = max(0, $new_on_hand - $new_reserved);
            
            $update = "UPDATE stock_locales 
                      SET quantity_on_hand = $new_on_hand,
                          quantity_reserved = $new_reserved,
                          quantity_issued = quantity_issued + $qty,
                          last_issued_date = NOW(),
                          updated_at = NOW()
                      WHERE id = " . intval($stock['id']);
            $connection->query($update);
            
            // Log transaction
            $quantity_before = intval($stock['quantity_on_hand']);
            $quantity_after = intval($new_on_hand);
            $resulting_balance = intval($new_reserved);

            $query = "INSERT INTO inventory_transactions 
                     (part_id, transaction_type, reference_type, reference_id, warehouse_location_id, quantity_change, quantity_before, quantity_changed, quantity_after, resulting_balance, reason, notes, user_id, created_at)
                     VALUES ($part_id, 'issued', 'work_order', $wo_id, " . intval($stock['warehouse_location_id']) . ", -$qty, $quantity_before, -$qty, $quantity_after, $resulting_balance, 'WO stock issue', 'WO stock issue', " . intval($user_id) . ", NOW())";
            $connection->query($query);
            
            // Update WO parts
            $update = "UPDATE wo_parts 
                      SET quantity_issued = $qty, status = 'issued', updated_at = NOW()
                      WHERE wo_id = $wo_id AND part_id = $part_id";
            $connection->query($update);
            
            // Re-sync total stock in parts_master
            $stock_total = $connection->query("SELECT SUM(quantity_on_hand) AS total FROM stock_locales WHERE part_id = $part_id");
            if ($stock_total && ($row = $stock_total->fetch_assoc())) {
                $connection->query("UPDATE parts_master SET total_on_hand = " . intval($row['total']) . " WHERE id = $part_id");
            }
            
            $connection->commit();
            return true;
        } catch (Exception $e) {
            $connection->rollback();
            error_log("Issue stock error: " . $e->getMessage());
            return false;
        }
    }
    
    return false;
}

/**
 * Return reserved stock for a work order part
 */
function return_stock($wo_id, $part_id, $quantity, $user_id, $connection = null) {
    global $c;
    $connection = $connection ?? $c;

    $wo_id = intval($wo_id);
    $part_id = intval($part_id);
    $qty = intval($quantity);
    $user_id = intval($user_id);

    $query = "SELECT sl.* FROM stock_locales sl
              JOIN wo_parts wp ON wp.part_id = sl.part_id
              WHERE wp.wo_id = $wo_id AND wp.part_id = $part_id AND sl.quantity_reserved >= $qty
              ORDER BY sl.quantity_reserved DESC LIMIT 1";
    $result = $connection->query($query);

    if ($result && $result->num_rows > 0) {
        $stock = $result->fetch_assoc();

        $connection->beginTransaction();
        try {
            $new_reserved = max(0, intval($stock['quantity_reserved']) - $qty);
            $new_on_hand = max(0, intval($stock['quantity_on_hand']) + $qty);

            $update = "UPDATE stock_locales SET quantity_reserved = $new_reserved, quantity_on_hand = $new_on_hand, updated_at = NOW() WHERE id = " . intval($stock['id']);
            $connection->query($update);

            $update = "UPDATE wo_parts SET quantity_reserved = GREATEST(0, quantity_reserved - $qty), status = 'returned' WHERE wo_id = $wo_id AND part_id = $part_id";
            $connection->query($update);

            $quantity_before = intval($stock['quantity_on_hand']);
            $quantity_after = intval($new_on_hand);
            $resulting_balance = intval($new_reserved);

            $query = "INSERT INTO inventory_transactions
                     (part_id, transaction_type, reference_type, reference_id, warehouse_location_id, quantity_change, quantity_before, quantity_changed, quantity_after, resulting_balance, reason, notes, user_id, created_at)
                     VALUES ($part_id, 'return', 'work_order', $wo_id, " . intval($stock['warehouse_location_id']) . ", $qty, $quantity_before, $qty, $quantity_after, $resulting_balance, 'Work Order Return', 'Work Order Return', $user_id, NOW())";
            $connection->query($query);

            // Update parts_master total_on_hand
            $stock_result = $connection->query("SELECT SUM(quantity_on_hand) as total FROM stock_locales WHERE part_id = $part_id");
            if ($stock_result && ($row = $stock_result->fetch_assoc())) {
                $new_total = intval($row['total']);
                $connection->query("UPDATE parts_master SET total_on_hand = $new_total WHERE id = $part_id");
            }

            $connection->commit();
            return true;
        } catch (Exception $e) {
            $connection->rollback();
            error_log("Return stock error: " . $e->getMessage());
            return false;
        }
    }

    return false;
}

// ============================================================================
// VENDOR MANAGEMENT FUNCTIONS
// ============================================================================

/**
 * Create or update vendor
 */
function save_vendor($data, $connection) {
    global $db_type;
    
    // Validate required fields
    if (empty($data['vendor_name'])) {
        error_log("[VENDOR SAVE] Validation failed: vendor_name is required");
        return false;
    }
    
    $vendor_id = isset($data['id']) && $data['id'] > 0 ? intval($data['id']) : null;
    $tenant_id = (int)($_SESSION['tenant_id'] ?? 1);
    
    $fields = [
        'vendor_code', 'vendor_name', 'contact_person', 'email', 'phone', 'fax',
        'address', 'city', 'state', 'postal_code', 'country', 'payment_terms',
        'lead_time_days', 'rating', 'is_active', 'notes'
    ];
    
    $values = [];
    foreach ($fields as $field) {
        if (method_exists($connection, 'real_escape_string')) {
            // MySQL
            $values[$field] = $connection->real_escape_string($data[$field] ?? '');
        } else {
            // SQLite PDO wrapper - escape single quotes
            $values[$field] = str_replace("'", "''", $data[$field] ?? '');
        }
    }
    
    // Auto-generate supplier code if not provided
    if (empty($values['vendor_code'])) {
        try {
            // Generate unique vendor code with timestamp and random to ensure uniqueness
            $timestamp = time();
            $random = mt_rand(100, 999);
            $values['vendor_code'] = 'SUP-' . $timestamp . '-' . $random;
            
            // Keep trying until we find a unique code
            $max_attempts = 5;
            $attempt = 0;
            while ($attempt < $max_attempts) {
                try {
                    if ($db_type === 'sqlite') {
                        $check = $connection->query("SELECT vendor_code FROM vendors WHERE vendor_code = '{$values['vendor_code']}'");
                        $exists = $check && $check->fetch(PDO::FETCH_ASSOC);
                    } else {
                        $check = $connection->query("SELECT vendor_code FROM vendors WHERE vendor_code = '{$values['vendor_code']}'");
                        $exists = $check && $check->fetch_assoc();
                    }
                    
                    if (!$exists) {
                        break;  // Found unique code
                    }
                } catch (Exception $e) {
                    // Ignore check errors and proceed
                    break;
                }
                
                // Try again with different random
                $random = mt_rand(100, 999);
                $values['vendor_code'] = 'SUP-' . $timestamp . '-' . $random;
                $attempt++;
            }
            
            error_log("[VENDOR SAVE] Generated vendor code: {$values['vendor_code']}");
        } catch (Exception $e) {
            error_log("[VENDOR SAVE ERROR] Failed to generate vendor code: " . $e->getMessage());
            // Use timestamp+random as fallback
            $values['vendor_code'] = 'SUP-' . time() . '-' . mt_rand(100, 999);
        }
    }
    
    // Handle checkbox for is_active
    $values['is_active'] = isset($data['is_active']) && $data['is_active'] == 1 ? 1 : 0;
    
    // Handle rating - default to 5 if not provided or empty
    if (empty($values['rating'])) {
        $values['rating'] = 5;
    } else {
        $values['rating'] = floatval($values['rating']);
    }
    
    // Handle lead_time_days - ensure it's an integer
    if (empty($values['lead_time_days'])) {
        $values['lead_time_days'] = 7;  // default
    } else {
        $values['lead_time_days'] = intval($values['lead_time_days']);
    }
    
    // Check if tenant_id column exists
    $has_tenant_column = false;
    try {
        if ($db_type === 'sqlite') {
            $check = $connection->query("PRAGMA table_info('vendors')");
            $columns = [];
            while ($row = $check->fetch(PDO::FETCH_ASSOC)) {
                $columns[] = $row['name'];
            }
            $has_tenant_column = in_array('tenant_id', $columns);
        } else {
            $check = $connection->query("SHOW COLUMNS FROM vendors LIKE 'tenant_id'");
            $has_tenant_column = $check && $check->fetch() !== false;
        }
    } catch (Exception $e) {
        error_log("[VENDOR SAVE] Could not check for tenant_id column: " . $e->getMessage());
    }
    
    if ($vendor_id) {
        // Update existing vendor
        $set_parts = [];
        foreach ($fields as $f) {
            $set_parts[] = "{$f} = '{$values[$f]}'";
        }
        $set_parts[] = "updated_at = CURRENT_TIMESTAMP";
        $where = "id = $vendor_id";
        if ($has_tenant_column) {
            $where .= " AND tenant_id = $tenant_id";
        }
        $query = "UPDATE vendors SET " . implode(', ', $set_parts) . " WHERE $where";
        
        if (!$connection->query($query)) {
            $error_msg = method_exists($connection, 'error') ? $connection->error : 'Unknown error';
            error_log("[VENDOR SAVE ERROR] UPDATE failed: $error_msg");
            error_log("[VENDOR SAVE ERROR] Query: $query");
            return false;
        }
        return $vendor_id;
    } else {
        // Create new vendor
        $col_names = $fields;
        if ($has_tenant_column) {
            $col_names[] = 'tenant_id';
        }
        $col_list = implode(', ', $col_names);
        
        $val_list = [];
        foreach ($fields as $field) {
            $val_list[] = "'{$values[$field]}'";
        }
        if ($has_tenant_column) {
            $val_list[] = $tenant_id;
        }
        $vals = implode(', ', $val_list);
        
        $query = "INSERT INTO vendors ($col_list) VALUES ($vals)";
        
        try {
            if (!$connection->query($query)) {
                $error_msg = method_exists($connection, 'error') ? $connection->error : 'Unknown error';
                error_log("[VENDOR SAVE ERROR] INSERT failed: $error_msg");
                error_log("[VENDOR SAVE ERROR] Query: $query");
                return false;
            }
        } catch (Exception $e) {
            error_log("[VENDOR SAVE ERROR] INSERT exception: " . $e->getMessage());
            error_log("[VENDOR SAVE ERROR] Query: $query");
            return false;
        }
        
        if (method_exists($connection, 'insert_id')) {
            return $connection->insert_id;
        } else {
            return true;
        }
    }
}

/**
 * Get all vendors
 */
function get_vendors($connection, $active_only = true) {
    $where = $active_only ? "WHERE is_active = 1" : "";
    $query = "SELECT * FROM vendors $where ORDER BY vendor_name ASC";
    // Apply tenant filtering
    $query = apply_tenant_filter($query);
    $result = $connection->query($query);
    
    $vendors = [];
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $vendors[] = $row;
        }
    }
    return $vendors;
}

/**
 * Get vendor details with performance metrics
 */
function get_vendor_details($vendor_id, $connection) {
    global $db_type;
    
    $vendor_id = intval($vendor_id);
    $tenant_id = (int)($_SESSION['tenant_id'] ?? 1);
    
    // Check if tenant_id column exists
    $has_tenant_column = false;
    try {
        if ($db_type === 'sqlite') {
            $check = $connection->query("PRAGMA table_info('vendors')");
            $columns = [];
            while ($row = $check->fetch(PDO::FETCH_ASSOC)) {
                $columns[] = $row['name'];
            }
            $has_tenant_column = in_array('tenant_id', $columns);
        } else {
            $check = $connection->query("SHOW COLUMNS FROM vendors LIKE 'tenant_id'");
            $has_tenant_column = $check && $check->fetch() !== false;
        }
    } catch (Exception $e) {
        error_log("[GET VENDOR DETAILS] Could not check for tenant_id: " . $e->getMessage());
    }
    
    $where = "v.id = $vendor_id";
    if ($has_tenant_column) {
        $where .= " AND v.tenant_id = $tenant_id";
    }
    
    $query = "SELECT v.* FROM vendors v WHERE $where";
    $result = $connection->query($query);
    $vendor = $result ? $result->fetch_assoc() : null;
    
    if ($vendor) {
        // Get recent performance
        $perf_where = "vendor_id = $vendor_id";
        if ($has_tenant_column && table_exists('vendor_performance')) {
            // Check if vendor_performance has tenant_id
            try {
                if ($db_type === 'sqlite') {
                    $check = $connection->query("PRAGMA table_info('vendor_performance')");
                    $columns = [];
                    while ($row = $check->fetch(PDO::FETCH_ASSOC)) {
                        $columns[] = $row['name'];
                    }
                    if (in_array('tenant_id', $columns)) {
                        $perf_where .= " AND tenant_id = $tenant_id";
                    }
                } else {
                    $check = $connection->query("SHOW COLUMNS FROM vendor_performance LIKE 'tenant_id'");
                    if ($check && $check->fetch()) {
                        $perf_where .= " AND tenant_id = $tenant_id";
                    }
                }
            } catch (Exception $e) {
                error_log("[GET VENDOR DETAILS] Could not check vendor_performance tenant_id: " . $e->getMessage());
            }
        }
        
        $perf_query = "SELECT * FROM vendor_performance WHERE $perf_where ORDER BY metric_month DESC LIMIT 6";
        $perf_result = $connection->query($perf_query);
        $vendor['performance_history'] = [];
        if ($perf_result) {
            while ($row = $perf_result->fetch_assoc()) {
                $vendor['performance_history'][] = $row;
            }
        }
    }
    
    return $vendor;
}

// ============================================================================
// PURCHASE REQUEST FUNCTIONS
// ============================================================================

/**
 * Create purchase request
 */
function create_purchase_request($requestor_id, $items, $required_by_date, $priority = 'normal', $status = 'draft', $notes = '', $department = '', $cost_center = '', $site_location_id = 0, $warehouse_id = 0, $linked_work_order = '', $project_code = '', $budget_code = '', $gl_account = '', $expense_type = 'OpEx', $justification = '', $connection = null) {
    global $c;
    $connection = $connection ?? $c;
    
    $tenant_id = (int)($_SESSION['tenant_id'] ?? 1);
    $requestor_id = intval($requestor_id);
    $site_location_id = intval($site_location_id);
    $warehouse_id = intval($warehouse_id);
    $priority = $connection->real_escape_string($priority);
    $status = $connection->real_escape_string($status);
    $notes = $connection->real_escape_string($notes);
    $department = $connection->real_escape_string($department);
    $cost_center = $connection->real_escape_string($cost_center);
    $linked_work_order = $connection->real_escape_string($linked_work_order);
    $project_code = $connection->real_escape_string($project_code);
    $budget_code = $connection->real_escape_string($budget_code);
    $gl_account = $connection->real_escape_string($gl_account);
    $expense_type = $connection->real_escape_string($expense_type);
    $justification = $connection->real_escape_string($justification);
    $required_by = $required_by_date ? $connection->real_escape_string(date('Y-m-d', strtotime($required_by_date))) : null;
    
    // Generate PR number (fits VARCHAR(20) for current schema)
    $pr_number = 'PR-' . date('ymdHis') . '-' . rand(1000, 9999);
    
    // Initialize total
    $total_amount = 0;
    
    // Create PR header
    $required_by_field = $required_by ? "'$required_by'" : 'NULL';
    $query = "INSERT INTO purchase_requests 
             (pr_number, requestor_id, required_by_date, priority, status, total_amount, notes,
              department, cost_center, site_location_id, warehouse_id, linked_work_order, project_code, 
              budget_code, gl_account, expense_type, justification, tenant_id)
             VALUES ('$pr_number', $requestor_id, " . ($required_by ? "'$required_by'" : "NULL") . ", '$priority', '$status', $total_amount, '$notes',
                     '$department', '$cost_center', $site_location_id, $warehouse_id, '$linked_work_order', '$project_code',
                     '$budget_code', '$gl_account', '$expense_type', '$justification', $tenant_id)";

    if (!$connection->query($query)) {
        error_log('Purchase request insert failed: ' . $connection->error . ' | SQL: ' . $query);
        return false;
    }
    
    $pr_id = $connection->lastInsertId();
    
    // Insert items
    $total_amount = 0;
    foreach ($items as $item) {
        $part_id = intval($item['part_id'] ?? 0);
        $qty = floatval($item['quantity'] ?? 0);
        $unit_cost = floatval($item['unit_cost'] ?? 0);
        $est_total = $qty * $unit_cost;
        $total_amount += $est_total;
        
        $item_desc = $connection->real_escape_string($item['description'] ?? '');
        $uom = $connection->real_escape_string($item['unit_of_measure'] ?? 'EA');
        
        $query = "INSERT INTO purchase_request_items 
                     (pr_id, part_id, description, quantity, unit_of_measure, estimated_unit_cost, estimated_total)
                     VALUES ($pr_id, " . ($part_id ?: 'NULL') . ", '$item_desc', $qty, '$uom', $unit_cost, $est_total)";
        if (!$connection->query($query)) {
            error_log('Purchase request item insert failed: ' . $connection->error . ' | SQL: ' . $query);
        }
    }
    
    // Update PR total
    $update = "UPDATE purchase_requests SET total_amount = $total_amount WHERE id = $pr_id";
    if (!$connection->query($update)) {
        error_log('Purchase request total update failed: ' . $connection->error . ' | SQL: ' . $update);
    }
    
    return $pr_id;
}

/**
 * Get purchase request with items
 */
function get_purchase_request($pr_id, $connection) {
    $pr_id = intval($pr_id);
    $query = "SELECT pr.*, u.username as requestor_name 
              FROM purchase_requests pr
              LEFT JOIN users u ON pr.requestor_id = u.user_id
              WHERE pr.id = $pr_id";
    $result = $connection->query($query);
    $pr = $result ? $result->fetch_assoc() : null;
    
    if ($pr) {
        $items_query = "SELECT pri.*, pm.part_name, pm.part_code, 
                   pri.quantity AS quantity_requested, 
                   pri.estimated_total AS estimated_total_cost 
                   FROM purchase_request_items pri
                   LEFT JOIN parts_master pm ON pri.part_id = pm.id
                   WHERE pri.pr_id = $pr_id";
        $items_result = $connection->query($items_query);
        $pr['items'] = [];
        if ($items_result) {
            while ($row = $items_result->fetch_assoc()) {
                $pr['items'][] = $row;
            }
        }
    }
    
    return $pr;
}

/**
 * Approve purchase request
 */
function approve_purchase_request($pr_id, $approved_by_id, $notes = '', $connection = null) {
    global $c;
    $connection = $connection ?? $c;
    
    $pr_id = intval($pr_id);
    $approved_by_id = intval($approved_by_id);
    $notes = $connection->real_escape_string($notes);
    
    $query = "UPDATE purchase_requests 
             SET status = 'approved', 
                 approval_by_id = $approved_by_id,
                 approval_date = NOW(),
                 approval_notes = '$notes'
             WHERE id = $pr_id";
    
    return $connection->query($query) ? true : false;
}

// ============================================================================
// PURCHASE ORDER FUNCTIONS
// ============================================================================

/**
 * Create purchase order from PR or manual
 */
function create_purchase_order($vendor_id, $pr_id, $items, $ordered_by_id, 
                               $required_by_date, $metadata = [], $connection = null) {
    global $c;
    $connection = $connection ?? $c;
    
    $vendor_id = intval($vendor_id);
    $pr_id = intval($pr_id) ?: 'NULL';
    $ordered_by_id = intval($ordered_by_id);
    $required_by = $required_by_date ? date('Y-m-d', strtotime($required_by_date)) : null;
    $tenant_id = (int)($_SESSION['tenant_id'] ?? 1);
    
    // Generate PO number
    $po_number = 'PO-' . date('YmdHis');
    
    // Get vendor payment terms
    $vendor_query = "SELECT payment_terms FROM vendors WHERE id = $vendor_id AND tenant_id = $tenant_id";
    $vendor_result = $connection->query($vendor_query);
    $payment_terms = $vendor_result && ($row = $vendor_result->fetch_assoc()) 
                     ? $row['payment_terms'] : 'Net 30';

    // Metadata for CMMS fields
    $delivery_address = $connection->real_escape_string(trim($metadata['delivery_address'] ?? ''));
    $shipping_method = $connection->real_escape_string(trim($metadata['shipping_method'] ?? ''));
    $work_order_ref = $connection->real_escape_string(trim($metadata['work_order_ref'] ?? ''));
    $project_code = $connection->real_escape_string(trim($metadata['project_code'] ?? ''));
    $cost_center = $connection->real_escape_string(trim($metadata['cost_center'] ?? ''));
    $asset_id = $connection->real_escape_string(trim($metadata['asset_id'] ?? ''));
    $notes_content = $connection->real_escape_string(trim($metadata['notes'] ?? ''));

    $combined_notes = "";
    if ($work_order_ref !== '') $combined_notes .= "Work Order: $work_order_ref\n";
    if ($project_code !== '') $combined_notes .= "Project Code: $project_code\n";
    if ($cost_center !== '') $combined_notes .= "Cost Center: $cost_center\n";
    if ($asset_id !== '') $combined_notes .= "Asset ID: $asset_id\n";
    if ($delivery_address !== '') $combined_notes .= "Delivery Address: $delivery_address\n";
    if ($shipping_method !== '') $combined_notes .= "Shipping Method: $shipping_method\n";
    if ($notes_content !== '') $combined_notes .= "\n" . $notes_content;

    $expected_delivery_date = $metadata['expected_delivery_date'] ? date('Y-m-d', strtotime($metadata['expected_delivery_date'])) : null;
    $expected_delivery_date_sql = $expected_delivery_date ? "'$expected_delivery_date'" : 'NULL';

    // Insert PO header - SQLite compatible version with tenant_id
    $columns = ['po_number', 'vendor_id', 'pr_id', 'po_date', 'required_by_date', 'expected_delivery_date', 'payment_terms', 'ordered_by_id', 'notes', 'tenant_id'];
    $values = ["'$po_number'", $vendor_id, $pr_id, "NOW()", ($required_by ? "'$required_by'" : 'NULL'), $expected_delivery_date_sql, "'$payment_terms'", $ordered_by_id, "'$combined_notes'", $tenant_id];

    $query = "INSERT INTO purchase_orders (" . implode(', ', $columns) . ") VALUES (" . implode(', ', $values) . ")";

    if (!$connection->query($query)) {
        return false;
    }
    
    // Get last inserted ID using PDO method (works with both SQLite and MySQL)
    $po_id = $connection->lastInsertId();
    
    // Insert items and calculate totals
    $subtotal = 0;
    foreach ($items as $item) {
        $part_id = intval($item['part_id'] ?? 0);
        $qty = intval($item['quantity']);
        $unit_cost = floatval($item['unit_cost']);
        $line_total = $qty * $unit_cost;
        $subtotal += $line_total;
        
        $desc = $connection->real_escape_string($item['description'] ?? '');
        $uom = $connection->real_escape_string($item['unit_of_measure'] ?? 'EA');
        
        $query = "INSERT INTO purchase_order_items 
                 (po_id, part_id, description, quantity_ordered, unit_of_measure, unit_cost, line_total, tenant_id)
                 VALUES ($po_id, " . ($part_id ?: "NULL") . ", '$desc', $qty, '$uom', $unit_cost, $line_total, $tenant_id)";
        $connection->query($query);
        
        // Update stock on order
        if ($part_id) {
            $stock_query = "SELECT * FROM stock_locales WHERE part_id = $part_id AND tenant_id = $tenant_id LIMIT 1";
            $stock_result = $connection->query($stock_query);
            if ($stock_result && $stock_result->num_rows > 0) {
                $stock = $stock_result->fetch_assoc();
                $new_on_order = intval($stock['quantity_on_order']) + $qty;
                $update = "UPDATE stock_locales 
                          SET quantity_on_order = $new_on_order
                          WHERE part_id = $part_id AND tenant_id = $tenant_id";
                $connection->query($update);
            }
        }
    }
    
    // Update PO totals
    $tax = $subtotal * 0.18; // 18% VAT
    $po_total = $subtotal + $tax;
    
    $update = "UPDATE purchase_orders 
              SET subtotal = $subtotal, tax_amount = $tax, po_total = $po_total,
                  status = 'submitted'
              WHERE id = $po_id AND tenant_id = $tenant_id";
    $connection->query($update);
    
    return $po_id;
}

/**
 * Get purchase order with items
 */
function get_purchase_order($po_id, $connection) {
    $po_id = intval($po_id);
    $tenant_id = (int)($_SESSION['tenant_id'] ?? 1);
    $query = "SELECT po.*, v.vendor_name, v.address AS vendor_address, v.contact_person AS vendor_contact_person,
                     v.phone AS vendor_phone, v.email AS vendor_email, u.username as ordered_by_name
              FROM purchase_orders po
              LEFT JOIN vendors v ON po.vendor_id = v.id
              LEFT JOIN users u ON po.ordered_by_id = u.user_id
              WHERE po.id = $po_id AND po.tenant_id = $tenant_id";
    $result = $connection->query($query);
    $po = $result ? $result->fetch_assoc() : null;
    
    if ($po) {
        $items_query = "SELECT poi.*, pm.part_name, pm.part_code
                       FROM purchase_order_items poi
                       LEFT JOIN parts_master pm ON poi.part_id = pm.id
                       WHERE poi.po_id = $po_id AND poi.tenant_id = $tenant_id";
        $items_result = $connection->query($items_query);
        $po['items'] = [];
        if ($items_result) {
            while ($row = $items_result->fetch_assoc()) {
                $po['items'][] = $row;
            }
        }
    }
    
    return $po;
}

/**
 * Approve purchase order
 */
function approve_purchase_order($po_id, $approved_by_id, $connection = null) {
    global $c;
    $connection = $connection ?? $c;
    
    $po_id = intval($po_id);
    $approved_by_id = intval($approved_by_id);
    
    $query = "UPDATE purchase_orders 
             SET status = 'confirmed', 
                 approval_date = NOW(),
                 approved_by_id = $approved_by_id
             WHERE id = $po_id";
    
    return $connection->query($query) ? true : false;
}

// ============================================================================
// GOODS RECEIPT FUNCTIONS
// ============================================================================

/**
 * Create goods receipt
 */
function create_goods_receipt($po_id, $warehouse_location_id, $received_by_id, $connection = null) {
    global $c;
    $connection = $connection ?? $c;
    
    $po_id = intval($po_id);
    $warehouse_location_id = intval($warehouse_location_id);
    $received_by_id = intval($received_by_id);
    
    // Validate inputs
    if ($po_id <= 0) {
        error_log("GR creation failed: Invalid PO ID ($po_id)");
        return false;
    }
    if ($warehouse_location_id <= 0) {
        error_log("GR creation failed: Invalid warehouse location ID ($warehouse_location_id)");
        return false;
    }
    if ($received_by_id <= 0) {
        error_log("GR creation failed: Invalid received_by_id ($received_by_id) - user may not be logged in");
        return false;
    }
    
    // Validate warehouse location exists (skip if checks fail gracefully)
    $loc_check = $connection->query("SELECT id FROM warehouse_locations WHERE id = $warehouse_location_id");
    if ($loc_check) {
        $loc_row = $loc_check->fetch_assoc();
        if (!$loc_row) {
            error_log("GR creation failed: Warehouse location $warehouse_location_id does not exist");
            return false;
        }
    }
    
    // Validate user exists (skip if checks fail gracefully)
    $user_check = $connection->query("SELECT user_id FROM users WHERE user_id = $received_by_id");
    if ($user_check) {
        $user_row = $user_check->fetch_assoc();
        if (!$user_row) {
            error_log("GR creation failed: User $received_by_id does not exist");
            return false;
        }
    }
    
    // Generate GR number
    $gr_number = 'GR-' . date('YmdHis') . '-' . uniqid();
    
    $query = "INSERT INTO goods_receipts 
             (gr_number, po_id, received_by_id, warehouse_location_id, tenant_id)
             VALUES ('$gr_number', $po_id, $received_by_id, $warehouse_location_id, {$_SESSION['tenant_id']})";
    
    if (!$connection->query($query)) {
        error_log("GR creation failed: INSERT query failed for po_id=$po_id, user=$received_by_id, location=$warehouse_location_id");
        return false;
    }

    if (method_exists($connection, 'lastInsertId')) {
        return intval($connection->lastInsertId());
    }

    return isset($connection->insert_id) ? intval($connection->insert_id) : false;
}

/**
 * Add received items to goods receipt
 */
function add_receipt_item($gr_id, $po_item_id, $quantity_received, $part_id, $unit_cost, 
                          $condition = 'good', $connection = null) {
    global $c;
    $connection = $connection ?? $c;
    
    $gr_id = intval($gr_id);
    $po_item_id = intval($po_item_id);
    $quantity_received = intval($quantity_received);
    $part_id = intval($part_id);
    $unit_cost = floatval($unit_cost);
    $condition = $connection->real_escape_string($condition);
    $received_cost = $quantity_received * $unit_cost;
    
    // Validate inputs
    if ($gr_id <= 0) {
        error_log("Receipt item add failed: Invalid GR ID ($gr_id)");
        return false;
    }
    if ($po_item_id <= 0) {
        error_log("Receipt item add failed: Invalid PO item ID ($po_item_id)");
        return false;
    }
    if ($part_id <= 0) {
        error_log("Receipt item add failed: Invalid part ID ($part_id)");
        return false;
    }
    
    $query = "INSERT INTO goods_receipt_items 
             (gr_id, po_item_id, part_id, quantity_received, quantity_accepted,
              unit_cost, received_cost, received_condition, tenant_id)
             VALUES ($gr_id, $po_item_id, $part_id, $quantity_received, $quantity_received,
                     $unit_cost, $received_cost, '$condition', {$_SESSION['tenant_id']})";;
    
    if (!$connection->query($query)) {
        error_log("Receipt item insert failed for gr_id=$gr_id, po_item_id=$po_item_id, part_id=$part_id");
        return false;
    }
    
    // Update PO item received quantity
    $update = "UPDATE purchase_order_items 
              SET quantity_received = quantity_received + $quantity_received
              WHERE id = $po_item_id";
    $connection->query($update);
    
    // Update stock safely
    $gr_query = "SELECT warehouse_location_id FROM goods_receipts WHERE id = $gr_id";
    $gr_result = $connection->query($gr_query);
    if ($gr_result && ($gr_row = $gr_result->fetch_assoc())) {
        $wl_id = intval($gr_row['warehouse_location_id']);
        if ($wl_id > 0) {
            // Only update stock if warehouse location is valid
            $updated = update_stock($part_id, $wl_id, $quantity_received,
                        'received', $gr_id, null, 'Goods Receipt', $connection);
            if (!$updated) {
                error_log("Stock update failed for part $part_id at location $wl_id");
                // Don't fail the receipt item insert - stock update is non-critical
            }
        } else {
            error_log("Receipt item $po_item_id: Invalid warehouse location ID ($wl_id)");
        }
    }
    
    return true;
}

/**
 * Complete goods receipt and update inventory
 */
function complete_goods_receipt($gr_id, $quality_check_by_id = null, $connection = null) {
    global $c;
    $connection = $connection ?? $c;
    
    $gr_id = intval($gr_id);
    $quality_check_by_id = $quality_check_by_id ? intval($quality_check_by_id) : 'NULL';
    
    $query = "UPDATE goods_receipts 
             SET is_complete = 1,
                 quality_check_status = 'passed',
                 quality_check_by_id = $quality_check_by_id,
                 quality_check_date = NOW()
             WHERE id = $gr_id AND tenant_id = {$_SESSION['tenant_id']}";
    
    if ($connection->query($query)) {
        // Update related PO status
        $po_query = "SELECT po_id FROM goods_receipts WHERE id = $gr_id AND tenant_id = {$_SESSION['tenant_id']}";
        $po_result = $connection->query($po_query);
        if ($po_result && ($po_row = $po_result->fetch_assoc())) {
            $po_id = intval($po_row['po_id']);
            
            // Check if all items received
            $check_query = "SELECT 
                           poi.id, 
                           poi.quantity_ordered,
                           COALESCE(poi.quantity_received, 0) as qty_received
                           FROM purchase_order_items poi
                           WHERE poi.po_id = $po_id AND poi.tenant_id = {$_SESSION['tenant_id']}";
            $check_result = $connection->query($check_query);
            $all_received = true;
            
            if ($check_result) {
                while ($item = $check_result->fetch_assoc()) {
                    if (intval($item['qty_received']) < intval($item['quantity_ordered'])) {
                        $all_received = false;
                        break;
                    }
                }
            }
            
            $new_status = $all_received ? 'received' : 'partially_received';
            $update_po = "UPDATE purchase_orders SET status = '$new_status' WHERE id = $po_id AND tenant_id = {$_SESSION['tenant_id']}";
            $connection->query($update_po);
        }
        
        return true;
    }
    
    return false;
}

// ============================================================================
// INVENTORY ANALYTICS FUNCTIONS
// ============================================================================

/**
 * Get stock status summary
 */
function get_stock_status_summary($connection) {
    // Calculate stock status in real-time from actual stock data
    $query = "SELECT 
                COUNT(DISTINCT pm.id) as total_parts,
                SUM(CASE 
                    WHEN COALESCE(sl.total_on_hand, 0) = 0 THEN 1
                    WHEN COALESCE(sl.total_on_hand, 0) < pm.safety_stock_level OR COALESCE(sl.total_on_hand, 0) = 0 THEN 1
                    ELSE 0
                END) as critical_count,
                SUM(CASE 
                    WHEN COALESCE(sl.total_on_hand, 0) >= pm.safety_stock_level 
                    AND COALESCE(sl.total_on_hand, 0) <= pm.reorder_point THEN 1
                    ELSE 0
                END) as low_count,
                SUM(CASE 
                    WHEN COALESCE(sl.total_on_hand, 0) > pm.reorder_point 
                    AND COALESCE(sl.total_on_hand, 0) <= pm.maximum_quantity THEN 1
                    ELSE 0
                END) as normal_count,
                SUM(CASE 
                    WHEN COALESCE(sl.total_on_hand, 0) > pm.maximum_quantity THEN 1
                    ELSE 0
                END) as overstock_count
              FROM parts_master pm
              LEFT JOIN (SELECT part_id, SUM(quantity_on_hand) as total_on_hand 
                        FROM stock_locales WHERE tenant_id = " . tenant_id() . " GROUP BY part_id) sl ON pm.id = sl.part_id
              WHERE pm.is_active = 1 AND pm.tenant_id = " . tenant_id();
    $result = $connection->query($query);
    $summary = $result ? $result->fetch_assoc() : null;
    return $summary ?: [
        'total_parts' => 0,
        'critical_count' => 0,
        'low_count' => 0,
        'normal_count' => 0,
        'overstock_count' => 0
    ];
}

/**
 * Initialize inventory summary table if it doesn't exist (fallback for missing migrations)
 */
function ensure_inventory_summary_table($connection) {
    global $db_type;

    // Determine database-specific syntax
    $auto_increment = ($db_type === 'sqlite') ? 'AUTOINCREMENT' : 'AUTO_INCREMENT';
    $engine_clause = ($db_type === 'sqlite') ? '' : 'ENGINE=InnoDB DEFAULT CHARSET=utf8mb4';
    $timestamp_default = ($db_type === 'sqlite') ? 'TEXT DEFAULT CURRENT_TIMESTAMP' : 'TIMESTAMP DEFAULT CURRENT_TIMESTAMP';
    $timestamp_update = ($db_type === 'sqlite') ? '' : 'ON UPDATE CURRENT_TIMESTAMP';

    if ($db_type === 'sqlite') {
        // SQLite: Check if table exists
        $check = $connection->query("SELECT name FROM sqlite_master WHERE type='table' AND name='inventory_summary'");
        $exists = $check && $check->fetch(PDO::FETCH_ASSOC);
    } else {
        // MySQL: Check if table exists
        $check = $connection->query("SHOW TABLES LIKE 'inventory_summary'");
        $exists = $check && $check->num_rows > 0;
    }

    if (!$exists) {
        $sql = "CREATE TABLE IF NOT EXISTS inventory_summary (
            id INTEGER PRIMARY KEY {$auto_increment},
            part_id INTEGER NOT NULL,
            total_on_hand INTEGER DEFAULT 0,
            total_reserved INTEGER DEFAULT 0,
            total_available INTEGER DEFAULT 0,
            total_on_order INTEGER DEFAULT 0,
            monthly_usage INTEGER DEFAULT 0,
            safety_stock_level INTEGER DEFAULT 0,
            reorder_point INTEGER DEFAULT 0,
            stock_status VARCHAR(20) DEFAULT 'normal',
            months_of_supply DECIMAL(5,2),
            abc_classification VARCHAR(1),
            annual_usage_value DECIMAL(12,2),
            last_updated {$timestamp_default} {$timestamp_update}
        ) {$engine_clause}";

        $connection->query($sql);
    }
    return true;
}

/**
 * Ensure core inventory tables exist
 */
function ensure_inventory_tables($connection) {
    global $db_type;

    // Determine database-specific syntax
    $auto_increment = ($db_type === 'sqlite') ? 'AUTOINCREMENT' : 'AUTO_INCREMENT';
    $engine_clause = ($db_type === 'sqlite') ? '' : 'ENGINE=InnoDB DEFAULT CHARSET=utf8mb4';
    $timestamp_default = ($db_type === 'sqlite') ? 'TEXT DEFAULT CURRENT_TIMESTAMP' : 'TIMESTAMP DEFAULT CURRENT_TIMESTAMP';
    $timestamp_update = ($db_type === 'sqlite') ? '' : 'ON UPDATE CURRENT_TIMESTAMP';

    $tables = [];

    $tables[] = "CREATE TABLE IF NOT EXISTS parts_master (
        id INTEGER PRIMARY KEY {$auto_increment},
        part_code VARCHAR(100) UNIQUE,
        part_number VARCHAR(100),
        part_name VARCHAR(255) NOT NULL,
        description TEXT,
        category VARCHAR(100),
        subcategory VARCHAR(100),
        asset_compatibility TEXT,
        unit_of_measure VARCHAR(50),
        manufacturer VARCHAR(100),
        oem_part_number VARCHAR(100),
        vendor_reference VARCHAR(255),
        supplier_id INTEGER,
        warranty_period_days INTEGER DEFAULT 0,
        safety_stock_level INTEGER DEFAULT 0,
        minimum_quantity INTEGER DEFAULT 0,
        maximum_quantity INTEGER DEFAULT 0,
        reorder_point INTEGER DEFAULT 0,
        lead_time_days INTEGER DEFAULT 0,
        criticality_level VARCHAR(20),
        abc_classification VARCHAR(1),
        total_on_hand INTEGER DEFAULT 0,
        total_reserved INTEGER DEFAULT 0,
        total_issued INTEGER DEFAULT 0,
        total_on_order INTEGER DEFAULT 0,
        unit_cost DECIMAL(12,2) DEFAULT 0,
        is_active INTEGER DEFAULT 1,
        created_at {$timestamp_default},
        updated_at {$timestamp_default} {$timestamp_update}
    ) {$engine_clause}";

    $tables[] = "CREATE TABLE IF NOT EXISTS vendors (
        id INTEGER PRIMARY KEY {$auto_increment},
        vendor_code VARCHAR(100) UNIQUE,
        vendor_name VARCHAR(255) NOT NULL,
        address TEXT,
        contact_person VARCHAR(255),
        phone VARCHAR(50),
        email VARCHAR(255),
        is_active INTEGER DEFAULT 1,
        created_at {$timestamp_default},
        updated_at {$timestamp_default} {$timestamp_update}
    ) {$engine_clause}";

    $tables[] = "CREATE TABLE IF NOT EXISTS warehouses (
        id INTEGER PRIMARY KEY {$auto_increment},
        warehouse_code VARCHAR(100) UNIQUE NOT NULL,
        warehouse_name VARCHAR(255) NOT NULL,
        location VARCHAR(255),
        manager_id INTEGER,
        max_capacity INTEGER DEFAULT 0,
        current_usage INTEGER DEFAULT 0,
        phone VARCHAR(50),
        tenant_id INTEGER NOT NULL DEFAULT 1,
        is_active INTEGER DEFAULT 1,
        created_at {$timestamp_default},
        updated_at {$timestamp_default} {$timestamp_update}
    ) {$engine_clause}";

    $tables[] = "CREATE TABLE IF NOT EXISTS warehouse_locations (
        id INTEGER PRIMARY KEY {$auto_increment},
        warehouse_id INTEGER NOT NULL,
        location_code VARCHAR(100) NOT NULL,
        location_name VARCHAR(255),
        zone VARCHAR(50),
        aisle VARCHAR(50),
        rack VARCHAR(50),
        bin VARCHAR(50),
        max_capacity INTEGER DEFAULT 0,
        current_usage INTEGER DEFAULT 0,
        tenant_id INTEGER NOT NULL DEFAULT 1,
        is_active INTEGER DEFAULT 1,
        created_at {$timestamp_default},
        updated_at {$timestamp_default} {$timestamp_update},
        UNIQUE (warehouse_id, location_code, tenant_id)
    ) {$engine_clause}";

    $tables[] = "CREATE TABLE IF NOT EXISTS stock_locations (
        id INTEGER PRIMARY KEY {$auto_increment},
        location_name VARCHAR(255) NOT NULL,
        description TEXT,
        is_active INTEGER DEFAULT 1,
        created_at {$timestamp_default},
        updated_at {$timestamp_default} {$timestamp_update}
    ) {$engine_clause}";

    $tables[] = "CREATE TABLE IF NOT EXISTS stock_locales (
        id INTEGER PRIMARY KEY {$auto_increment},
        part_id INTEGER NOT NULL,
        warehouse_location_id INTEGER NOT NULL,
        quantity_on_hand INTEGER DEFAULT 0,
        quantity_reserved INTEGER DEFAULT 0,
        quantity_available INTEGER DEFAULT 0,
        quantity_on_order INTEGER DEFAULT 0,
        quantity_issued INTEGER DEFAULT 0,
        minimum_quantity INTEGER DEFAULT 0,
        maximum_quantity INTEGER DEFAULT 0,
        last_received_date TEXT NULL,
        last_issued_date TEXT NULL,
        last_updated {$timestamp_default} {$timestamp_update}
    ) {$engine_clause}";

    $tables[] = "CREATE TABLE IF NOT EXISTS purchase_requests (
        id INTEGER PRIMARY KEY {$auto_increment},
        pr_number VARCHAR(100) UNIQUE,
        requestor_id INTEGER,
        required_by_date TEXT,
        priority VARCHAR(50) DEFAULT 'normal',
        status VARCHAR(50) DEFAULT 'draft',
        total_amount DECIMAL(12,2) DEFAULT 0,
        approval_by_id INTEGER NULL,
        approval_date TEXT NULL,
        approval_notes TEXT,
        notes TEXT,
        created_at {$timestamp_default},
        updated_at {$timestamp_default} {$timestamp_update}
    ) {$engine_clause}";

    $tables[] = "CREATE TABLE IF NOT EXISTS consumables (
        id INTEGER PRIMARY KEY {$auto_increment},
        name VARCHAR(255) NOT NULL,
        category VARCHAR(100),
        subcategory VARCHAR(100),
        description TEXT,
        unit VARCHAR(50) DEFAULT 'pcs',
        location VARCHAR(255),
        warehouse_location_id INTEGER DEFAULT NULL,
        supplier VARCHAR(255),
        current_stock INTEGER DEFAULT 0,
        min_stock INTEGER DEFAULT 0,
        cost_per_unit DECIMAL(12,2) DEFAULT 0,
        is_active INTEGER DEFAULT 1,
        tenant_id INTEGER DEFAULT 1,
        created_at {$timestamp_default},
        last_updated {$timestamp_default} {$timestamp_update}
    ) {$engine_clause}";

    $tables[] = "CREATE TABLE IF NOT EXISTS consumable_usage (
        id INTEGER PRIMARY KEY {$auto_increment},
        consumable_id INTEGER NOT NULL,
        quantity_used DECIMAL(12,2) DEFAULT 0,
        work_order_id INTEGER NULL,
        usage_date {$timestamp_default},
        notes TEXT,
        created_at {$timestamp_default}
    ) {$engine_clause}";

    $tables[] = "CREATE TABLE IF NOT EXISTS purchase_request_items (
        id INTEGER PRIMARY KEY {$auto_increment},
        pr_id INTEGER NOT NULL,
        part_id INTEGER NULL,
        description TEXT,
        quantity INTEGER DEFAULT 0,
        unit_of_measure VARCHAR(50),
        estimated_unit_cost DECIMAL(12,2) DEFAULT 0,
        estimated_total DECIMAL(12,2) DEFAULT 0,
        created_at {$timestamp_default},
        updated_at {$timestamp_default} {$timestamp_update}
    ) {$engine_clause}";

    $tables[] = "CREATE TABLE IF NOT EXISTS purchase_orders (
        id INTEGER PRIMARY KEY {$auto_increment},
        po_number VARCHAR(100) UNIQUE,
        vendor_id INTEGER,
        po_date TEXT,
        expected_date TEXT,
        payment_terms VARCHAR(100),
        delivery_address TEXT,
        shipping_method VARCHAR(100),
        status VARCHAR(50) DEFAULT 'open',
        total_amount DECIMAL(12,2) DEFAULT 0,
        notes TEXT,
        created_at {$timestamp_default},
        updated_at {$timestamp_default} {$timestamp_update}
    ) {$engine_clause}";

    $tables[] = "CREATE TABLE IF NOT EXISTS purchase_order_items (
        id INTEGER PRIMARY KEY {$auto_increment},
        po_id INTEGER NOT NULL,
        part_id INTEGER NOT NULL,
        quantity_ordered INTEGER DEFAULT 0,
        quantity_received INTEGER DEFAULT 0,
        unit_cost DECIMAL(12,2) DEFAULT 0,
        total_cost DECIMAL(12,2) DEFAULT 0,
        created_at {$timestamp_default},
        updated_at {$timestamp_default} {$timestamp_update}
    ) {$engine_clause}";

    $tables[] = "CREATE TABLE IF NOT EXISTS work_order_spares (
        id INTEGER PRIMARY KEY {$auto_increment},
        wo_id INTEGER NOT NULL,
        spare_id INTEGER NOT NULL,
        quantity_used INTEGER DEFAULT 0,
        created_at {$timestamp_default},
        updated_at {$timestamp_default} {$timestamp_update}
    ) {$engine_clause}";

    $tables[] = "CREATE TABLE IF NOT EXISTS goods_receipts (
        id INTEGER PRIMARY KEY {$auto_increment},
        grn_number VARCHAR(100) UNIQUE,
        po_id INTEGER,
        received_by_id INTEGER,
        warehouse_location_id INTEGER,
        receipt_date TEXT,
        status VARCHAR(50) DEFAULT 'open',
        total_amount DECIMAL(12,2) DEFAULT 0,
        notes TEXT,
        created_at {$timestamp_default},
        updated_at {$timestamp_default} {$timestamp_update}
    ) {$engine_clause}";

    $tables[] = "CREATE TABLE IF NOT EXISTS goods_receipt_items (
        id INTEGER PRIMARY KEY {$auto_increment},
        gr_id INTEGER NOT NULL,
        po_item_id INTEGER NOT NULL,
        part_id INTEGER NOT NULL,
        quantity_received INTEGER DEFAULT 0,
        quantity_accepted INTEGER DEFAULT 0,
        unit_cost DECIMAL(12,2) DEFAULT 0,
        received_cost DECIMAL(12,2) DEFAULT 0,
        received_condition VARCHAR(50) DEFAULT 'good',
        created_at {$timestamp_default},
        updated_at {$timestamp_default} {$timestamp_update}
    ) {$engine_clause}";

    $tables[] = "CREATE TABLE IF NOT EXISTS wo_parts (
        id INTEGER PRIMARY KEY {$auto_increment},
        wo_id INTEGER NOT NULL,
        part_id INTEGER NOT NULL,
        quantity_required INTEGER DEFAULT 0,
        quantity_reserved INTEGER DEFAULT 0,
        quantity_issued INTEGER DEFAULT 0,
        status VARCHAR(50) DEFAULT 'pending',
        cost DECIMAL(12,2) DEFAULT 0,
        created_at {$timestamp_default},
        updated_at {$timestamp_default} {$timestamp_update}
    ) {$engine_clause}";

    $tables[] = "CREATE TABLE IF NOT EXISTS inventory_transactions (
        id INTEGER PRIMARY KEY {$auto_increment},
        part_id INTEGER NOT NULL,
        wo_id INTEGER NULL,
        transaction_type VARCHAR(50) NOT NULL,
        action VARCHAR(50) NULL,
        reference_type VARCHAR(50),
        reference_id INTEGER,
        warehouse_location_id INTEGER NULL,
        quantity INTEGER DEFAULT 0,
        quantity_change INTEGER DEFAULT 0,
        quantity_before INTEGER DEFAULT 0,
        quantity_changed INTEGER DEFAULT 0,
        quantity_after INTEGER DEFAULT 0,
        resulting_balance INTEGER DEFAULT 0,
        transaction_date {$timestamp_default},
        created_at {$timestamp_default},
        reason VARCHAR(200) NULL,
        notes TEXT,
        user_id INTEGER
    ) {$engine_clause}";

    foreach ($tables as $sql) {
        $connection->query($sql);
    }

    // Add any missing warehouse columns for older schemas
    if ($db_type === 'sqlite') {
        $warehouse_alter_queries = [
            "ALTER TABLE warehouses ADD COLUMN warehouse_code VARCHAR(100) UNIQUE",
            "ALTER TABLE warehouses ADD COLUMN location VARCHAR(255)",
            "ALTER TABLE warehouses ADD COLUMN manager_id INTEGER",
            "ALTER TABLE warehouses ADD COLUMN max_capacity INTEGER DEFAULT 0",
            "ALTER TABLE warehouses ADD COLUMN current_usage INTEGER DEFAULT 0",
            "ALTER TABLE warehouses ADD COLUMN phone VARCHAR(50)",
            "ALTER TABLE warehouses ADD COLUMN updated_at TEXT"
        ];

        foreach ($warehouse_alter_queries as $query) {
            try {
                $connection->exec($query);
            } catch (Exception $e) {
                // Column might already exist, ignore error
            }
        }

        $warehouse_location_alter_queries = [
            "ALTER TABLE warehouse_locations ADD COLUMN zone TEXT",
            "ALTER TABLE warehouse_locations ADD COLUMN aisle TEXT",
            "ALTER TABLE warehouse_locations ADD COLUMN rack TEXT",
            "ALTER TABLE warehouse_locations ADD COLUMN bin TEXT",
            "ALTER TABLE warehouse_locations ADD COLUMN max_capacity INTEGER DEFAULT 0",
            "ALTER TABLE warehouse_locations ADD COLUMN current_usage INTEGER DEFAULT 0"
        ];

        foreach ($warehouse_location_alter_queries as $query) {
            try {
                $connection->exec($query);
            } catch (Exception $e) {
                // Column might already exist, ignore error
            }
        }
    } else {
        $connection->query("ALTER TABLE warehouses ADD COLUMN IF NOT EXISTS warehouse_code VARCHAR(100) UNIQUE");
        $connection->query("ALTER TABLE warehouses ADD COLUMN IF NOT EXISTS location VARCHAR(255)");
        $connection->query("ALTER TABLE warehouses ADD COLUMN IF NOT EXISTS manager_id INT");
        $connection->query("ALTER TABLE warehouses ADD COLUMN IF NOT EXISTS max_capacity INT DEFAULT 0");
        $connection->query("ALTER TABLE warehouses ADD COLUMN IF NOT EXISTS current_usage INT DEFAULT 0");
        $connection->query("ALTER TABLE warehouses ADD COLUMN IF NOT EXISTS phone VARCHAR(50)");
        $connection->query("ALTER TABLE warehouses ADD COLUMN IF NOT EXISTS updated_at DATETIME DEFAULT CURRENT_TIMESTAMP");

        $connection->query("ALTER TABLE warehouse_locations ADD COLUMN IF NOT EXISTS zone VARCHAR(50)");
        $connection->query("ALTER TABLE warehouse_locations ADD COLUMN IF NOT EXISTS aisle VARCHAR(50)");
        $connection->query("ALTER TABLE warehouse_locations ADD COLUMN IF NOT EXISTS rack VARCHAR(50)");
        $connection->query("ALTER TABLE warehouse_locations ADD COLUMN IF NOT EXISTS bin VARCHAR(50)");
        $connection->query("ALTER TABLE warehouse_locations ADD COLUMN IF NOT EXISTS max_capacity INT DEFAULT 0");
        $connection->query("ALTER TABLE warehouse_locations ADD COLUMN IF NOT EXISTS current_usage INT DEFAULT 0");
    }

    // Add any missing columns from older inventory_transactions schema
    if ($db_type === 'sqlite') {
        // SQLite syntax for adding columns
        $alter_queries = [
            "ALTER TABLE inventory_transactions ADD COLUMN quantity_change INTEGER DEFAULT 0",
            "ALTER TABLE inventory_transactions ADD COLUMN quantity_before INTEGER DEFAULT 0",
            "ALTER TABLE inventory_transactions ADD COLUMN quantity_changed INTEGER DEFAULT 0",
            "ALTER TABLE inventory_transactions ADD COLUMN quantity_after INTEGER DEFAULT 0",
            "ALTER TABLE inventory_transactions ADD COLUMN resulting_balance INTEGER DEFAULT 0",
            "ALTER TABLE inventory_transactions ADD COLUMN transaction_date TEXT DEFAULT CURRENT_TIMESTAMP",
            "ALTER TABLE inventory_transactions ADD COLUMN reason VARCHAR(200) NULL"
        ];

        foreach ($alter_queries as $query) {
            try {
                $connection->exec($query);
            } catch (Exception $e) {
                // Column might already exist, ignore error
            }
        }

        // Add action column
        try {
            $connection->exec("ALTER TABLE inventory_transactions ADD COLUMN action VARCHAR(50) NULL");
        } catch (Exception $e) {
            // Column might already exist, ignore error
        }
    } else {
        // MySQL syntax
        $connection->query("ALTER TABLE inventory_transactions ADD COLUMN IF NOT EXISTS quantity_change INT DEFAULT 0");
        $connection->query("ALTER TABLE inventory_transactions ADD COLUMN IF NOT EXISTS quantity_before INT DEFAULT 0");
        $connection->query("ALTER TABLE inventory_transactions ADD COLUMN IF NOT EXISTS quantity_changed INT DEFAULT 0");
        $connection->query("ALTER TABLE inventory_transactions ADD COLUMN IF NOT EXISTS quantity_after INT DEFAULT 0");
        $connection->query("ALTER TABLE inventory_transactions ADD COLUMN IF NOT EXISTS resulting_balance INT DEFAULT 0");
        $connection->query("ALTER TABLE inventory_transactions ADD COLUMN IF NOT EXISTS transaction_date DATETIME DEFAULT CURRENT_TIMESTAMP");
        $connection->query("ALTER TABLE inventory_transactions ADD COLUMN IF NOT EXISTS reason VARCHAR(200) NULL");
    }

    // Ensure the inventory_summary table exists too
    ensure_inventory_summary_table($connection);

    return true;
}

/**
 * Get parts needing reorder
 */
function get_reorder_parts($connection) {
    $query = "SELECT pm.id, pm.part_code, pm.part_name, pm.reorder_point, pm.safety_stock_level,
                     pm.lead_time_days, pm.unit_cost,
                     COALESCE(sl.total_on_hand, 0) as current_stock,
                     GREATEST(pm.reorder_point - COALESCE(sl.total_on_hand, 0), 0) as quantity_needed,
                     GREATEST(pm.reorder_point - COALESCE(sl.total_on_hand, 0), 0) * pm.unit_cost as reorder_value
              FROM parts_master pm
              LEFT JOIN (SELECT part_id, SUM(quantity_on_hand) as total_on_hand 
                        FROM stock_locales WHERE tenant_id = " . tenant_id() . " GROUP BY part_id) sl ON pm.id = sl.part_id
              WHERE pm.is_active = 1 AND pm.tenant_id = " . tenant_id() . "
              AND COALESCE(sl.total_on_hand, 0) <= pm.reorder_point
              ORDER BY quantity_needed DESC";
    
    $result = $connection->query($query);
    $parts = [];
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $parts[] = $row;
        }
    }
    return $parts;
}

/**
 * Get inventory value report
 */
function get_inventory_value_report($connection) {
    $query = "SELECT 
                pm.id, pm.part_code, pm.part_name, pm.criticality_level,
                COALESCE(SUM(sl.quantity_on_hand), 0) as total_qty,
                pm.unit_cost,
                COALESCE(SUM(sl.quantity_on_hand), 0) * COALESCE(pm.unit_cost, 0) as total_value,
                pm.abc_classification,
                CASE 
                    WHEN COALESCE(SUM(sl.quantity_on_hand), 0) = 0 THEN 'critical'
                    WHEN COALESCE(SUM(sl.quantity_on_hand), 0) < pm.safety_stock_level THEN 'critical'
                    WHEN COALESCE(SUM(sl.quantity_on_hand), 0) <= pm.reorder_point THEN 'low'
                    WHEN COALESCE(SUM(sl.quantity_on_hand), 0) > pm.maximum_quantity THEN 'overstock'
                    ELSE 'normal'
                END as stock_status
              FROM parts_master pm
              LEFT JOIN stock_locales sl ON pm.id = sl.part_id AND sl.tenant_id = pm.tenant_id
              WHERE pm.is_active = 1 AND pm.tenant_id = " . tenant_id() . "
              GROUP BY pm.id, pm.part_code, pm.part_name, pm.criticality_level, pm.unit_cost, pm.safety_stock_level, pm.reorder_point, pm.maximum_quantity, pm.abc_classification
              ORDER BY total_value DESC";
    
    $result = $connection->query($query);
    $data = [];
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $data[] = $row;
        }
    }
    return $data;
}

// ============================================================================
// UTILITY FUNCTIONS
// ============================================================================

/**
 * Calculate ABC classification based on Pareto principle (80/20)
 */
function update_abc_classifications($connection) {
    // Get all parts with their annual usage value
    $query = "SELECT pm.id, 
                     SUM(sl.quantity_on_hand) * pm.unit_cost as annual_value
              FROM parts_master pm
              LEFT JOIN stock_locales sl ON pm.id = sl.part_id
              WHERE pm.is_active = 1
              GROUP BY pm.id
              ORDER BY annual_value DESC";
    
    $result = $connection->query($query);
    $parts = [];
    $total_value = 0;
    
    while ($row = $result->fetch_assoc()) {
        $parts[] = $row;
        $total_value += floatval($row['annual_value']);
    }
    
    // Assign ABC classification
    $cumulative = 0;
    foreach ($parts as $part) {
        $cumulative += floatval($part['annual_value']);
        $percentage = ($total_value > 0) ? ($cumulative / $total_value) * 100 : 0;
        
        if ($percentage <= 80) {
            $class = 'A';
        } elseif ($percentage <= 95) {
            $class = 'B';
        } else {
            $class = 'C';
        }
        
        $update = "UPDATE parts_master SET abc_classification = '$class' WHERE id = " . intval($part['id']);
        $connection->query($update);
    }
    
    return true;
}

// ============================================================================
// AUTO-STOCK REDUCTION FOR WORK ORDERS
// ============================================================================

/**
 * Reserve parts for a work order (called when creating WO)
 * Moves stock from "available" to "reserved"
 */
function reserve_parts_for_work_order($wo_id, $parts_array, $connection) {
    /**
     * $parts_array = [
     *     ['part_id' => 5, 'quantity' => 2, 'warehouse_location_id' => 2],
     *     ['part_id' => 12, 'quantity' => 0.5, 'warehouse_location_id' => 2],
     * ]
     */
    
    if (!$connection) {
        global $c;
        $connection = $c;
    }

    try {
        $connection->beginTransaction();

        foreach ($parts_array as $part) {
            $part_id = intval($part['part_id']);
            $quantity = floatval($part['quantity']);
            $warehouse_location_id = intval($part['warehouse_location_id'] ?? 2);  // Default to first location

            // Get current stock in this location
            $stmt = $connection->prepare("
                SELECT id, quantity_on_hand, quantity_reserved, quantity_available
                FROM stock_locales
                WHERE part_id = ? AND warehouse_location_id = ?
                LIMIT 1
            ");
            $stmt->bind_param('ii', $part_id, $warehouse_location_id);
            $stmt->execute();
            $result = $stmt->get_result();
            $stock = $result->fetch_assoc();

            if (!$stock) {
                throw new Exception("Stock not found for part $part_id in location $warehouse_location_id");
            }

            if ($stock['quantity_available'] < $quantity) {
                throw new Exception("Insufficient stock: need $quantity but only have " . $stock['quantity_available']);
            }

            // Update stock_locales: move from available to reserved
            $stmt = $connection->prepare("
                UPDATE stock_locales
                SET quantity_reserved = quantity_reserved + ?
                WHERE id = ?
            ");
            $stmt->bind_param('ddi', $quantity, $quantity, $stock['id']);
            $stmt->execute();

            // Log transaction
            $action = 'RESERVED_FOR_WO';
            $notes = "Reserved for work order WO#$wo_id";
            $stmt = $connection->prepare("
                INSERT INTO inventory_transactions 
                (part_id, wo_id, warehouse_location_id, action, quantity, notes)
                VALUES (?, ?, ?, ?, ?, ?)
            ");
            $stmt->bind_param('iiisds', 
                $part_id, $wo_id, $warehouse_location_id, $action, $quantity, $notes
            );
            $stmt->execute();
        }

        $connection->commit();
        return ['success' => true, 'message' => 'Parts reserved successfully'];
    } catch (Exception $e) {
        $connection->rollback();
        return ['success' => false, 'error' => $e->getMessage()];
    }
}

/**
 * Issue parts for a work order (called when work order is completed)
 * Moves stock from "reserved" to "consumed"
 */
function issue_parts_for_work_order($wo_id, $connection) {
    if (!$connection) {
        global $c;
        $connection = $c;
    }

    try {
        $connection->beginTransaction();

        // Get all reserved parts for this WO
        $stmt = $connection->prepare("
            SELECT part_id, warehouse_location_id, SUM(quantity) as reserved_qty
            FROM inventory_transactions
            WHERE wo_id = ? AND action = 'RESERVED_FOR_WO'
            GROUP BY part_id, warehouse_location_id
        ");
        $stmt->bind_param('i', $wo_id);
        $stmt->execute();
        $result = $stmt->get_result();

        while ($row = $result->fetch_assoc()) {
            $part_id = intval($row['part_id']);
            $warehouse_location_id = intval($row['warehouse_location_id']);
            $quantity = floatval($row['reserved_qty']);

            // Get current stock
            $stmt2 = $connection->prepare("
                SELECT id, quantity_reserved
                FROM stock_locales
                WHERE part_id = ? AND warehouse_location_id = ?
            ");
            $stmt2->bind_param('ii', $part_id, $warehouse_location_id);
            $stmt2->execute();
            $stock_result = $stmt2->get_result();
            $stock = $stock_result->fetch_assoc();

            if ($stock && $stock['quantity_reserved'] >= $quantity) {
                // Move from reserved to consumed (on_hand is already reduced)
                $stmt2 = $connection->prepare("
                    UPDATE stock_locales
                    SET quantity_on_hand = quantity_on_hand - ?,
                        quantity_reserved = quantity_reserved - ?
                    WHERE id = ?
                ");
                $stmt2->bind_param('ddi', $quantity, $quantity, $stock['id']);
                $stmt2->execute();

                // Log transaction
                $action = 'ISSUED_FROM_WO';
                $notes = "Issued from completed work order WO#$wo_id";
                $stmt2 = $connection->prepare("
                    INSERT INTO inventory_transactions 
                    (part_id, wo_id, warehouse_location_id, action, quantity, notes)
                    VALUES (?, ?, ?, ?, ?, ?)
                ");
                $stmt2->bind_param('iiisds', 
                    $part_id, $wo_id, $warehouse_location_id, $action, $quantity, $notes
                );
                $stmt2->execute();
            }
        }

        $connection->commit();
        return ['success' => true, 'message' => 'Parts issued successfully'];
    } catch (Exception $e) {
        $connection->rollback();
        return ['success' => false, 'error' => $e->getMessage()];
    }
}

// ============================================================================
// EQUIPMENT SPARES / COMPATIBILITY FUNCTIONS
// ============================================================================

/**
 * Get all equipment for dropdown selection
 */
function get_equipment_list($connection, $active_only = true) {
    $where = $active_only ? "WHERE status != 'Inactive'" : "";
    $query = "SELECT id, description, manufacturer, model, serial_number 
              FROM equipment 
              $where
              ORDER BY manufacturer, model, description";
    $result = $connection->query($query);
    
    $equipment = [];
    if ($result) {
        while ($row = $result->fetch(PDO::FETCH_ASSOC)) {
            $equipment[] = $row;
        }
    }
    return $equipment;
}

/**
 * Get spare equipment associations for a part
 */
function get_part_equipment_spares($part_id, $connection) {
    $part_id = intval($part_id);
    $query = "SELECT es.*, e.description, e.manufacturer, e.model, e.serial_number
              FROM equipment_spares es
              JOIN equipment e ON es.equipment_id = e.id
              WHERE es.part_id = $part_id
              ORDER BY e.manufacturer, e.model";
    $result = $connection->query($query);
    
    $spares = [];
    if ($result) {
        while ($row = $result->fetch(PDO::FETCH_ASSOC)) {
            $spares[] = $row;
        }
    }
    return $spares;
}

/**
 * Attach a part to equipment as a spare (creates or updates equipment_spare record)
 */
function attach_part_to_equipment($part_id, $equipment_id, $quantity = 0, $connection = null) {
    global $db_type, $c;
    $connection = $connection ?? $c;
    
    $part_id = intval($part_id);
    $equipment_id = intval($equipment_id);
    $quantity = intval($quantity);
    
    // Check if spare already exists for this equipment-part combination
    $query = "SELECT id, quantity FROM equipment_spares 
              WHERE part_id = $part_id AND equipment_id = $equipment_id LIMIT 1";
    $result = $connection->query($query);
    
    if ($result) {
        $row = ($db_type === 'sqlite') ? $result->fetch(PDO::FETCH_ASSOC) : $result->fetch_assoc();
        
        if ($row) {
            // Update existing
            $spare_id = $row['id'];
            if ($quantity > 0) {
                $update = "UPDATE equipment_spares SET quantity = $quantity WHERE id = $spare_id";
                $connection->query($update);
            }
            return $spare_id;
        }
    }
    
    // Insert new
    $get_part = "SELECT part_name, part_number FROM parts_master WHERE id = $part_id";
    $part_res = $connection->query($get_part);
    
    if (!$part_res) {
        return false;
    }
    
    $part_data = ($db_type === 'sqlite') ? $part_res->fetch(PDO::FETCH_ASSOC) : $part_res->fetch_assoc();
    
    if (!$part_data) {
        return false;
    }
    
    if ($db_type === 'sqlite') {
        $part_name = str_replace("'", "''", $part_data['part_name']);
        $part_number = str_replace("'", "''", $part_data['part_number']);
    } else {
        $part_name = $connection->real_escape_string($part_data['part_name']);
        $part_number = $connection->real_escape_string($part_data['part_number']);
    }
    
    $insert = "INSERT INTO equipment_spares 
               (equipment_id, part_id, part_name, part_number, quantity)
               VALUES ($equipment_id, $part_id, '$part_name', '$part_number', $quantity)";
    
    if ($connection->query($insert)) {
        if ($db_type === 'sqlite') {
            return $connection->lastInsertId();
        } else {
            return $connection->insert_id;
        }
    }
    
    return false;
}

/**
 * Handle equipment spare associations when saving a part
 * Called from save_part() to sync selected equipment
 */
function save_part_equipment_associations($part_id, $equipment_ids, $connection) {
    global $db_type;
    
    if (!$part_id || !is_array($equipment_ids)) {
        return true;
    }
    
    $part_id = intval($part_id);
    
    // Get currently associated equipment
    $current_query = "SELECT equipment_id FROM equipment_spares WHERE part_id = $part_id";
    $current_result = $connection->query($current_query);
    $current_equipment = [];
    
    if ($current_result) {
        while ($row = ($db_type === 'sqlite') ? $current_result->fetch(PDO::FETCH_ASSOC) : $current_result->fetch_assoc()) {
            $current_equipment[] = intval($row['equipment_id']);
        }
    }
    
    // Equipment IDs to remove (not in new list)
    $to_remove = array_diff($current_equipment, $equipment_ids);
    foreach ($to_remove as $eq_id) {
        $eq_id = intval($eq_id);
        $connection->query("DELETE FROM equipment_spares WHERE part_id = $part_id AND equipment_id = $eq_id");
    }
    
    // Equipment IDs to add (in new list but not currently associated)
    $to_add = array_diff($equipment_ids, $current_equipment);
    foreach ($to_add as $eq_id) {
        attach_part_to_equipment($part_id, $eq_id, 0, $connection);
    }
    
    return true;
}

/**
 * Rebuild lifecycle analytics from completed work orders
 * Syncs inventory with actual spare part and consumable usage
 * Called automatically after work order completion
 */
function rebuild_lifecycle_analytics($connection) {
    global $db_type;
    
    try {
        // Sync parts_master totals with stock_locales (sum all warehouse locations)
        if ($db_type === 'sqlite') {
            $sync_query = "UPDATE parts_master SET total_on_hand = (
                            SELECT COALESCE(SUM(quantity_on_hand), 0) 
                            FROM stock_locales 
                            WHERE part_id = parts_master.id
                        ) WHERE is_active = 1";
        } else {
            $sync_query = "UPDATE parts_master pm SET total_on_hand = (
                            SELECT COALESCE(SUM(quantity_on_hand), 0) 
                            FROM stock_locales 
                            WHERE part_id = pm.id
                        ) WHERE pm.is_active = 1";
        }
        $connection->query($sync_query);
        
        // Record inventory transactions for work_order_spares usage
        $wo_spares_query = "SELECT wos.id, wos.wo_id, wos.spare_id, wos.quantity_used,
                                   es.part_id, wo.submit_date
                            FROM work_order_spares wos
                            JOIN equipment_spares es ON wos.spare_id = es.id
                            JOIN work_orders wo ON wos.wo_id = wo.wo_id
                            WHERE wo.wo_status = 'Completed' AND es.part_id IS NOT NULL";
        
        $wo_spares_result = $connection->query($wo_spares_query);
        if ($wo_spares_result) {
            while ($row = ($db_type === 'sqlite') ? $wo_spares_result->fetch(PDO::FETCH_ASSOC) : $wo_spares_result->fetch_assoc()) {
                $check_query = "SELECT COUNT(*) as count FROM inventory_transactions 
                               WHERE reference_type = 'work_order_spare'
                               AND reference_id = " . intval($row['wos']) . "
                               AND part_id = " . intval($row['part_id']);
                $check_result = $connection->query($check_query);
                
                if ($check_result) {
                    if ($db_type === 'sqlite') {
                        $check_row = $check_result->fetch(PDO::FETCH_ASSOC);
                    } else {
                        $check_row = $check_result->fetch_assoc();
                    }
                    
                    if (intval($check_row['count'] ?? 0) == 0) {
                        $insert_query = "INSERT INTO inventory_transactions 
                                        (transaction_type, part_id, warehouse_location_id, quantity_change, 
                                         reference_type, reference_id, transaction_date, reason, notes)
                                        VALUES ('issue', " . intval($row['part_id']) . ", 1, " . 
                                        (-floatval($row['quantity_used'])) . ", 
                                        'work_order_spare', " . intval($row['wos']) . ",
                                        '" . $row['submit_date'] . "', 'Work Order Spare Usage', 'Auto-recorded')";
                        $connection->query($insert_query);
                    }
                }
            }
        }
        
        // Sync consumables usage with stock reduction
        $consumables_query = "SELECT woc.id, woc.work_order_id, woc.consumable_id, woc.quantity_required,
                                     c.current_stock
                              FROM work_order_consumables woc
                              JOIN consumables c ON woc.consumable_id = c.id
                              JOIN work_orders wo ON woc.work_order_id = wo.wo_id
                              WHERE wo.wo_status = 'Completed' AND woc.is_consumed = 0";
        
        $consumables_result = $connection->query($consumables_query);
        if ($consumables_result) {
            while ($row = ($db_type === 'sqlite') ? $consumables_result->fetch(PDO::FETCH_ASSOC) : $consumables_result->fetch_assoc()) {
                $consumable_id = intval($row['consumable_id']);
                $qty_required = floatval($row['quantity_required']);
                $current_stock = intval($row['current_stock']);
                
                // Reduce consumable stock
                $new_stock = max(0, $current_stock - $qty_required);
                $update_query = "UPDATE consumables SET current_stock = {$new_stock}, last_updated = NOW() 
                                WHERE id = {$consumable_id}";
                
                if ($connection->query($update_query)) {
                    // Mark as consumed
                    $mark_query = "UPDATE work_order_consumables SET is_consumed = 1, quantity_used = {$qty_required}
                                  WHERE id = " . intval($row['id']);
                    $connection->query($mark_query);
                }
            }
        }
        
        return true;
    } catch (Exception $e) {
        error_log("Error rebuilding lifecycle analytics: " . $e->getMessage());
        return false;
    }
}

?>
