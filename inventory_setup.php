<?php
/**
 * Professional Inventory Management System
 * Modern dashboard with analytics, parts management, and stock monitoring
 */

require_once 'config.inc.php';
require_once 'common.inc.php';
require_once 'libraries/inventory_manager.php';

// Ensure inventory schema is built/updated
if (function_exists('ensure_inventory_tables')) {
    ensure_inventory_tables($connection);
}

// AUTO-SYNC: Keep equipment_spares quantities synchronized with parts_master
// This ensures equipment spares always show up with correct stock levels
if ($connection) {
    $sync_spares = $connection->query("
        SELECT es.id, es.part_id, es.quantity, pm.total_on_hand
        FROM equipment_spares es
        JOIN parts_master pm ON es.part_id = pm.id
        WHERE es.quantity != pm.total_on_hand
    ");
    
    if ($sync_spares) {
        while ($spare = $sync_spares->fetch_assoc()) {
            $part_id = intval($spare['part_id']);
            $spare_qty = intval($spare['quantity']);
            
            // Update stock_locales
            $existing = $connection->query("SELECT id FROM stock_locales WHERE part_id = $part_id LIMIT 1");
            if ($existing && $existing->num_rows > 0) {
                $connection->query("UPDATE stock_locales SET quantity_on_hand = $spare_qty WHERE part_id = $part_id");
            } else {
                $connection->query("INSERT INTO stock_locales (part_id, warehouse_location_id, quantity_on_hand) VALUES ($part_id, 1, $spare_qty)");
            }
            
            // Update parts_master
            $connection->query("UPDATE parts_master SET total_on_hand = $spare_qty WHERE id = $part_id");
        }
    }
}

// Prevent caching to ensure updated data is displayed
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');

// Handle add/edit part submission
$add_message = '';
$edit_part_data = null;

$vendors_result = $connection->query("SELECT vendor_code, vendor_name FROM vendors WHERE is_active = 1 ORDER BY vendor_name");
$vendors_query = apply_tenant_filter("SELECT vendor_code, vendor_name FROM vendors WHERE is_active = 1 ORDER BY vendor_name");
$vendors_result = $connection->query($vendors_query);
$vendors_list = [];
while ($row = $vendors_result->fetch_assoc()) {
    $vendors_list[] = $row;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $part_code = $connection->real_escape_string(trim($_POST['part_code'] ?? ''));
    $part_number = $connection->real_escape_string(trim($_POST['part_number'] ?? ''));
    $part_name = $connection->real_escape_string(trim($_POST['part_name'] ?? ''));
    $description = $connection->real_escape_string(trim($_POST['description'] ?? ''));
    $category = $connection->real_escape_string(trim($_POST['category'] ?? ''));
    $sub_category = $connection->real_escape_string(trim($_POST['sub_category'] ?? ''));
    $asset_compatibility = $connection->real_escape_string(trim($_POST['asset_compatibility'] ?? ''));
    $unit_of_measure = $connection->real_escape_string(trim($_POST['unit_of_measure'] ?? ''));
    $manufacturer = $connection->real_escape_string(trim($_POST['manufacturer'] ?? ''));
    $oem_part_number = $connection->real_escape_string(trim($_POST['oem_part_number'] ?? ''));
    $supplier_part_number = $connection->real_escape_string(trim($_POST['supplier_part_number'] ?? ''));
    $vendor_reference = $connection->real_escape_string(trim($_POST['vendor_reference'] ?? ''));
    $warranty_period_months = max(0, intval($_POST['warranty_period_months'] ?? 0));
    $minimum_quantity = max(0, intval($_POST['minimum_quantity'] ?? 0));
    $maximum_quantity = max(0, intval($_POST['maximum_quantity'] ?? 0));
    $lead_time_days = max(0, intval($_POST['lead_time_days'] ?? 0));
    $unit_cost = floatval($_POST['unit_cost'] ?? 0);
    $reorder_point = max(0, intval($_POST['reorder_point'] ?? 0));
    $safety_stock_level = max(0, intval($_POST['safety_stock_level'] ?? 0));
    $criticality_level = $connection->real_escape_string(trim($_POST['criticality_level'] ?? 'low'));
    $abc_classification = $connection->real_escape_string(trim($_POST['abc_classification'] ?? 'C'));
    $is_hazmat = isset($_POST['is_hazmat']) ? 1 : 0;
    $is_serialized = isset($_POST['is_serialized']) ? 1 : 0;
    $notes = $connection->real_escape_string(trim($_POST['notes'] ?? ''));
    $total_on_hand = max(0, intval($_POST['total_on_hand'] ?? 0));

    if ($_POST['action'] === 'add_part') {
        if ($part_code && $part_name) {
            error_log('Adding new part: ' . $part_code . ' - ' . $part_name);
            $sql = "INSERT INTO parts_master (part_code, part_number, part_name, description, category, sub_category, asset_compatibility, unit_of_measure, manufacturer, oem_part_number, supplier_part_number, vendor_reference, warranty_period_months, safety_stock_level, minimum_quantity, maximum_quantity, reorder_point, lead_time_days, criticality_level, abc_classification, unit_cost, total_on_hand, is_hazmat, is_serialized, is_active, notes, tenant_id, created_at, updated_at) " .
                   "VALUES ('$part_code', '$part_number', '$part_name', '$description', '$category', '$sub_category', '$asset_compatibility', '$unit_of_measure', '$manufacturer', '$oem_part_number', '$supplier_part_number', '$vendor_reference', $warranty_period_months, $safety_stock_level, $minimum_quantity, $maximum_quantity, $reorder_point, $lead_time_days, '$criticality_level', '$abc_classification', $unit_cost, $total_on_hand, $is_hazmat, $is_serialized, 1, '$notes', " . tenant_id() . ", NOW(), NOW())";
            if ($connection->query($sql)) {
                $add_message = 'Part added successfully.';
                error_log('Part added successfully, ID: ' . $connection->insert_id);
                if ($total_on_hand > 0) {
                    $part_id = $connection->insert_id;
                    $location_id = 1;
                    $ident = $connection->query("SELECT id FROM warehouse_locations ORDER BY id LIMIT 1");
                    if ($ident && $ident->num_rows > 0) {
                        $location_id = intval($ident->fetch_assoc()['id']);
                    }
                    $connection->query("INSERT INTO stock_locales (part_id, warehouse_location_id, quantity_on_hand, quantity_reserved, updated_at) VALUES ($part_id, $location_id, $total_on_hand, 0, NOW())");
                }
            } else {
                $add_message = 'Failed to add part: ' . $connection->error;
                error_log('Failed to add part: ' . $connection->error);
            }
        } else {
            $add_message = 'Part Code and Part Name are required.';
        }
    } elseif ($_POST['action'] === 'edit_part' && !empty($_POST['part_id'])) {
        if ($part_code && $part_name) {
            $part_id = intval($_POST['part_id']);
            $sql = "UPDATE parts_master SET part_code='$part_code', part_number='$part_number', part_name='$part_name', description='$description', category='$category', sub_category='$sub_category', asset_compatibility='$asset_compatibility', unit_of_measure='$unit_of_measure', manufacturer='$manufacturer', oem_part_number='$oem_part_number', supplier_part_number='$supplier_part_number', vendor_reference='$vendor_reference', warranty_period_months=$warranty_period_months, safety_stock_level=$safety_stock_level, minimum_quantity=$minimum_quantity, maximum_quantity=$maximum_quantity, reorder_point=$reorder_point, lead_time_days=$lead_time_days, criticality_level='$criticality_level', abc_classification='$abc_classification', unit_cost=$unit_cost, total_on_hand=$total_on_hand, is_hazmat=$is_hazmat, is_serialized=$is_serialized, notes='$notes', updated_at=NOW() WHERE id=$part_id AND tenant_id = " . tenant_id();
            if ($connection->query($sql)) {
                $add_message = 'Part updated successfully.';
                // Update stock_locales to reflect total_on_hand if there is existing stock locale
                $existing = $connection->query("SELECT id FROM stock_locales WHERE part_id=$part_id LIMIT 1");
                if ($existing && $existing->num_rows > 0) {
                    $connection->query("UPDATE stock_locales SET quantity_on_hand=$total_on_hand, quantity_reserved=0, updated_at=NOW() WHERE part_id=$part_id");
                } else {
                    $location_id = 1;
                    $ident = $connection->query("SELECT id FROM warehouse_locations ORDER BY id LIMIT 1");
                    if ($ident && $ident->num_rows > 0) {
                        $location_id = intval($ident->fetch_assoc()['id']);
                    }
                    $connection->query("INSERT INTO stock_locales (part_id, warehouse_location_id, quantity_on_hand, quantity_reserved, updated_at) VALUES ($part_id, $location_id, $total_on_hand, 0, NOW())");
                }
            } else {
                $add_message = 'Failed to update part: ' . $connection->error;
            }
        } else {
            $add_message = 'Part Code and Part Name are required.';
        }
    } elseif ($_POST['action'] === 'adjust_stock' && !empty($_POST['part_id']) && isset($_POST['quantity'])) {
        error_log('Stock adjustment processing started for part_id: ' . $_POST['part_id']);
        $part_id = intval($_POST['part_id']);
        $adjustment_type = $_POST['adjustment_type'] ?? 'addition';
        $quantity = max(0, intval($_POST['quantity']));
        $reason = $connection->real_escape_string(trim($_POST['reason'] ?? 'Stock adjustment'));

        if ($quantity <= 0) {
            $add_message = 'Quantity must be greater than zero.';
        } else {
            // Ensure stock_locales exists for this part
            $stock_check_query = "SELECT id FROM stock_locales WHERE part_id = $part_id LIMIT 1";
            $stock_check_query = apply_tenant_filter($stock_check_query);
            $stock_check = $connection->query($stock_check_query);
            if (!$stock_check || $stock_check->num_rows == 0) {
                // Get current total_on_hand
                $total_query = "SELECT total_on_hand FROM parts_master WHERE id = $part_id";
                $total_query = apply_tenant_filter($total_query);
                $total_result = $connection->query($total_query);
                $current_total = 0;
                if ($total_result && $row = $total_result->fetch_assoc()) {
                    $current_total = intval($row['total_on_hand']);
                }
                // Insert stock_locales with current total
                $connection->query("INSERT INTO stock_locales (part_id, warehouse_location_id, quantity_on_hand, quantity_reserved, tenant_id, updated_at) VALUES ($part_id, 1, $current_total, 0, " . tenant_id() . ", NOW())");
            }

            $loc_query = "SELECT warehouse_location_id FROM stock_locales WHERE part_id=$part_id ORDER BY id LIMIT 1";
            $loc_query = apply_tenant_filter($loc_query);
            $loc_result = $connection->query($loc_query);
            $warehouse_location_id = 1;
            if ($loc_result && $loc_result->num_rows > 0) {
                $warehouse_location_id = intval($loc_result->fetch_assoc()['warehouse_location_id']);
            } else {
                $ident_query = "SELECT id FROM warehouse_locations ORDER BY id LIMIT 1";
                $ident_query = apply_tenant_filter($ident_query);
                $ident = $connection->query($ident_query);
                if ($ident && $ident->num_rows > 0) {
                    $warehouse_location_id = intval($ident->fetch_assoc()['id']);
                }
            }

            $qty_change = $adjustment_type === 'reduction' ? -$quantity : $quantity;
            // ensure update_stock function exists
            if (function_exists('update_stock')) {
                $user_id = intval($_SESSION['user_id'] ?? 0);
                error_log('Calling update_stock: part_id=' . $part_id . ', loc=' . $warehouse_location_id . ', qty_change=' . $qty_change);
                $ok = update_stock($part_id, $warehouse_location_id, $qty_change, 'adjustment', null, $user_id, $reason, $connection);
                if ($ok) {
                    $add_message = 'Stock adjusted successfully.';
                    error_log('Stock adjusted successfully');
                } else {
                    $add_message = 'Failed to adjust stock.';
                    error_log('Failed to adjust stock');
                }
            } else {
                $add_message = 'Inventory adjustment function not available.';
                error_log('update_stock function not found');
            }
        }
    } else {
        $add_message = 'Invalid stock operation or missing required fields.';
    }
}

// Check if edit mode from query
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['edit_part_id'])) {
    $edit_part_id = intval($_GET['edit_part_id']);
    $edit_query = "SELECT * FROM parts_master WHERE id=$edit_part_id LIMIT 1";
    $edit_query = apply_tenant_filter($edit_query);
    $edit_result = $connection->query($edit_query);
    if ($edit_result && $edit_result->num_rows > 0) {
        $edit_part_data = $edit_result->fetch_assoc();
    }
}


// Get inventory analytics data
$analytics = [];
$parts = [];
$low_stock_items = [];
$recent_transactions = [];
$show_reorder_report = isset($_GET['report']) && $_GET['report'] === 'reorder';
$reorder_filters = [
    'category' => trim($_GET['category'] ?? ''),
    'supplier' => trim($_GET['supplier'] ?? ''),
    'below_reorder' => isset($_GET['below_reorder']) ? 1 : 0,
    'stock_status' => trim($_GET['stock_status'] ?? ''),
    'active_only' => isset($_GET['active_only']) ? 1 : 0,
];

// Search and filter parameters
$search_query = trim($_GET['search'] ?? '');
$category_filter = trim($_GET['category_filter'] ?? '');
$manufacturer_filter = trim($_GET['manufacturer_filter'] ?? '');
$stock_status_filter = trim($_GET['stock_status_filter'] ?? '');
$sort_by = trim($_GET['sort'] ?? 'name'); // name, code, stock, cost
$sort_order = trim($_GET['order'] ?? 'asc'); // asc, desc

$reorder_report = [];

if ($connection) {
    // Inventory Analytics
    $analytics_query = "SELECT COUNT(DISTINCT COALESCE(NULLIF(part_code,''), part_number, part_name)) as count FROM parts_master WHERE is_active = 1";
    $analytics_query = apply_tenant_filter($analytics_query);
    $analytics['total_parts'] = $connection->query($analytics_query)->fetch_assoc()['count'] ?? 0;

    $analytics_query = "SELECT SUM(unit_cost * total_on_hand) as value FROM parts_master WHERE is_active = 1";
    $analytics_query = apply_tenant_filter($analytics_query);
    $analytics['total_value'] = $connection->query($analytics_query)->fetch_assoc()['value'] ?? 0;

    $analytics_query = "SELECT COUNT(DISTINCT COALESCE(NULLIF(part_code,''), part_number, part_name)) as count FROM parts_master WHERE total_on_hand <= reorder_point AND is_active = 1";
    $analytics_query = apply_tenant_filter($analytics_query);
    $analytics['low_stock_count'] = $connection->query($analytics_query)->fetch_assoc()['count'] ?? 0;

    $analytics_query = "SELECT COUNT(DISTINCT COALESCE(NULLIF(part_code,''), part_number, part_name)) as count FROM parts_master WHERE total_on_hand = 0 AND is_active = 1";
    $analytics_query = apply_tenant_filter($analytics_query);
    $analytics['out_of_stock_count'] = $connection->query($analytics_query)->fetch_assoc()['count'] ?? 0;

    $analytics_query = "SELECT COUNT(*) as count FROM inventory_transactions WHERE DATE(created_at) = " . get_current_date_sql();
    $analytics_query = apply_tenant_filter($analytics_query);
    $analytics['total_transactions'] = $connection->query($analytics_query)->fetch_assoc()['count'] ?? 0;

    // Build search and filter conditions
    $where_conditions = ["pm.is_active = 1"];
    $params = [];

    if (!empty($search_query)) {
        $search_term = $connection->real_escape_string($search_query);
        $where_conditions[] = "(pm.part_code LIKE '%$search_term%' OR pm.part_name LIKE '%$search_term%' OR pm.part_number LIKE '%$search_term%' OR pm.description LIKE '%$search_term%' OR pm.manufacturer LIKE '%$search_term%')";
    }

    if (!empty($category_filter)) {
        $category_term = $connection->real_escape_string($category_filter);
        $where_conditions[] = "pm.category LIKE '%$category_term%'";
    }

    if (!empty($manufacturer_filter)) {
        $manufacturer_term = $connection->real_escape_string($manufacturer_filter);
        $where_conditions[] = "pm.manufacturer LIKE '%$manufacturer_term%'";
    }

    if (!empty($stock_status_filter)) {
        switch ($stock_status_filter) {
            case 'out-of-stock':
                $where_conditions[] = "pm.total_on_hand = 0";
                break;
            case 'low-stock':
                $where_conditions[] = "pm.total_on_hand > 0 AND pm.total_on_hand <= pm.reorder_point";
                break;
            case 'medium-stock':
                $where_conditions[] = "pm.total_on_hand > pm.reorder_point AND pm.total_on_hand <= pm.safety_stock_level";
                break;
            case 'good-stock':
                $where_conditions[] = "pm.total_on_hand > pm.safety_stock_level";
                break;
        }
    }

    $where_clause = implode(" AND ", $where_conditions);

    // Build ORDER BY clause
    $order_by = "part_name " . strtoupper($sort_order); // default
    switch ($sort_by) {
        case 'code':
            $order_by = "part_code " . strtoupper($sort_order);
            break;
        case 'stock':
            $order_by = "total_on_hand " . strtoupper($sort_order);
            break;
        case 'cost':
            $order_by = "unit_cost " . strtoupper($sort_order);
            break;
        case 'name':
        default:
            $order_by = "part_name " . strtoupper($sort_order);
            break;
    }

    // Get parts with stock levels and search/filter
    $query = "
        SELECT
            MAX(pm.id) AS id,
            COALESCE(NULLIF(pm.part_code, ''), pm.part_number, pm.part_name) AS part_code,
            MAX(pm.part_name) AS part_name,
            MAX(pm.part_number) AS part_number,
            MAX(pm.description) AS description,
            MAX(pm.category) AS category,
            MAX(pm.manufacturer) AS manufacturer,
            MAX(pm.criticality_level) AS criticality_level,
            MAX(pm.unit_cost) AS unit_cost,
            SUM(pm.total_on_hand) AS total_on_hand,
            MAX(pm.reorder_point) AS reorder_point,
            MAX(pm.safety_stock_level) AS safety_stock_level,
            CASE
                WHEN SUM(pm.total_on_hand) = 0 THEN 'out-of-stock'
                WHEN SUM(pm.total_on_hand) <= MAX(pm.reorder_point) THEN 'low-stock'
                WHEN SUM(pm.total_on_hand) <= MAX(pm.safety_stock_level) THEN 'medium-stock'
                ELSE 'good-stock'
            END as stock_status
        FROM parts_master pm
        WHERE $where_clause
        GROUP BY COALESCE(NULLIF(pm.part_code, ''), pm.part_number, pm.part_name)
        ORDER BY
            CASE
                WHEN SUM(pm.total_on_hand) = 0 THEN 1
                WHEN SUM(pm.total_on_hand) <= MAX(pm.reorder_point) THEN 2
                WHEN SUM(pm.total_on_hand) <= MAX(pm.safety_stock_level) THEN 3
                ELSE 4
            END,
            $order_by
        LIMIT 100
    ";
    
    // Apply tenant filtering
    $query = apply_tenant_filter($query);

    $result = $connection->query($query);

    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $parts[] = $row;
        }
    }

    // Get low stock items specifically
    $low_stock_query = "
        SELECT
            MAX(pm.id) AS id,
            COALESCE(NULLIF(pm.part_code, ''), pm.part_number, pm.part_name) AS part_code,
            MAX(pm.part_name) AS part_name,
            SUM(pm.total_on_hand) AS total_on_hand,
            MAX(pm.reorder_point) AS reorder_point,
            MAX(pm.safety_stock_level) AS safety_stock_level,
            (MAX(pm.reorder_point) - SUM(pm.total_on_hand)) as shortage_qty
        FROM parts_master pm
        WHERE pm.total_on_hand <= pm.reorder_point
        AND pm.is_active = 1
        GROUP BY COALESCE(NULLIF(pm.part_code, ''), pm.part_number, pm.part_name)
        ORDER BY shortage_qty DESC
        LIMIT 10
    ";
    
    // Apply tenant filtering
    $low_stock_query = apply_tenant_filter($low_stock_query);

    $low_stock_result = $connection->query($low_stock_query);

    if ($low_stock_result) {
        while ($row = $low_stock_result->fetch_assoc()) {
            $low_stock_items[] = $row;
        }
    }

    if ($show_reorder_report) {
        $category_filter = $connection->real_escape_string($reorder_filters['category']);
        $supplier_filter = $connection->real_escape_string($reorder_filters['supplier']);
        $status_filter = $connection->real_escape_string($reorder_filters['stock_status']);

        $where_clauses = [
            'pm.is_active = 1'
        ];

        if ($reorder_filters['active_only']) {
            $where_clauses[] = 'pm.is_active = 1';
        }
        if ($category_filter !== '') {
            $where_clauses[] = "pm.category = '$category_filter'";
        }
        if ($supplier_filter !== '') {
            $where_clauses[] = "pm.manufacturer = '$supplier_filter'";
        }

        $where = implode(' AND ', $where_clauses);

        $reorder_sql = "SELECT
            pm.id,
            pm.part_code,
            pm.part_name,
            pm.description,
            pm.category,
            pm.manufacturer AS supplier,
            pm.unit_cost,
            pm.total_on_hand,
            pm.reorder_point,
            pm.minimum_quantity,
            pm.maximum_quantity,
            pm.safety_stock_level,
            pm.lead_time_days,
            COALESCE(sl.on_hand_qty, 0) AS on_hand_qty,
            COALESCE(sl.reserved_qty, 0) AS reserved_qty,
            COALESCE(sl.on_order_qty, 0) AS on_order_qty,
            (COALESCE(sl.on_hand_qty, 0) - COALESCE(sl.reserved_qty, 0)) AS available_qty,
            IF(pm.maximum_quantity > 0,
                GREATEST(pm.maximum_quantity - (COALESCE(sl.on_hand_qty, 0) - COALESCE(sl.reserved_qty, 0)), 0),
                GREATEST(pm.reorder_point - (COALESCE(sl.on_hand_qty, 0) - COALESCE(sl.reserved_qty, 0)), 0)
            ) AS suggested_qty,
            (IF(pm.maximum_quantity > 0,
                GREATEST(pm.maximum_quantity - (COALESCE(sl.on_hand_qty, 0) - COALESCE(sl.reserved_qty, 0)), 0),
                GREATEST(pm.reorder_point - (COALESCE(sl.on_hand_qty, 0) - COALESCE(sl.reserved_qty, 0)), 0)
            ) * pm.unit_cost) AS suggested_value,
            IF((COALESCE(sl.on_hand_qty, 0) - COALESCE(sl.reserved_qty, 0)) <= pm.reorder_point, 'Below Reorder', 'Healthy') AS reorder_status
        FROM parts_master pm
        LEFT JOIN (
            SELECT part_id,
                   SUM(quantity_on_hand) AS on_hand_qty,
                   SUM(quantity_reserved) AS reserved_qty,
                   SUM(quantity_on_order) AS on_order_qty
            FROM stock_locales WHERE tenant_id = " . tenant_id() . "
            GROUP BY part_id
        ) sl ON sl.part_id = pm.id
        WHERE $where AND pm.tenant_id = " . tenant_id();

        if ($reorder_filters['below_reorder']) {
            $reorder_sql .= " AND (COALESCE(sl.on_hand_qty, 0) - COALESCE(sl.reserved_qty, 0)) <= pm.reorder_point";
        }
        if ($status_filter === 'below_reorder') {
            $reorder_sql .= " AND (COALESCE(sl.on_hand_qty, 0) - COALESCE(sl.reserved_qty, 0)) <= pm.reorder_point";
        } elseif ($status_filter === 'healthy') {
            $reorder_sql .= " AND (COALESCE(sl.on_hand_qty, 0) - COALESCE(sl.reserved_qty, 0)) > pm.reorder_point";
        }

        $reorder_sql .= " ORDER BY (COALESCE(sl.on_hand_qty, 0) - COALESCE(sl.reserved_qty, 0)) ASC, pm.part_name ASC LIMIT 200";

        $reorder_result = $connection->query($reorder_sql);
        if ($reorder_result) {
            while ($row = $reorder_result->fetch_assoc()) {
                $reorder_report[] = $row;
            }
        }

        if (isset($_GET['export_csv']) && intval($_GET['export_csv']) === 1) {
            while (ob_get_level() > 0) {
                ob_end_clean();
            }
            header('Content-Type: text/csv; charset=UTF-8');
            header('Content-Disposition: attachment; filename="reorder_report_' . date('Ymd_His') . '.csv"');
            echo "\xEF\xBB\xBF";
            $output = fopen('php://output', 'w');
            @fputcsv($output, ['Part Code', 'Description', 'Category', 'Supplier', 'On Hand', 'Reserved', 'On Order', 'Available', 'Reorder Point', 'Suggested Qty', 'Lead Time Days', 'Reorder Value', 'Status'], ',', '"', '\\');
            foreach ($reorder_report as $row) {
                @fputcsv($output, [
                    $row['part_code'] ?? '',
                    $row['part_name'] ?? '',
                    $row['category'] ?? '',
                    $row['supplier'] ?? '',
                    intval($row['on_hand_qty']),
                    intval($row['reserved_qty']),
                    intval($row['on_order_qty']),
                    intval($row['available_qty']),
                    intval($row['reorder_point']),
                    intval($row['suggested_qty']),
                    intval($row['lead_time_days']),
                    number_format(floatval($row['suggested_value'] ?? 0), 2, '.', ''),
                    $row['reorder_status'] ?? '',
                ], ',', '"', '\\');
            }
            fclose($output);
            exit;
        }
    }

    // Get recent transactions (support old and new fields)
    $transaction_query = "
        SELECT
            it.transaction_type,
            pm.part_name,
            it.quantity_change,
            COALESCE(it.created_at, it.transaction_date, " . get_current_timestamp_sql() . ") AS created_at,
            u.username
        FROM inventory_transactions it
        LEFT JOIN parts_master pm ON it.part_id = pm.id
        LEFT JOIN users u ON it.user_id = u.user_id
        ORDER BY COALESCE(it.created_at, it.transaction_date) DESC
        LIMIT 10
    ";
    
    // Apply tenant filtering
    $transaction_query = apply_tenant_filter($transaction_query);

    $transaction_result = $connection->query($transaction_query);

    if ($transaction_result) {
        while ($row = $transaction_result->fetch_assoc()) {
            $recent_transactions[] = $row;
        }
    }
}

// Stock status colors
function get_stock_status_color($status) {
    switch ($status) {
        case 'out-of-stock': return 'danger';
        case 'low-stock': return 'danger';
        case 'medium-stock': return 'warning';
        case 'good-stock': return 'success';
        default: return 'secondary';
    }
}

function get_stock_status_icon($status) {
    switch ($status) {
        case 'out-of-stock': return 'fas fa-times-circle';
        case 'low-stock': return 'fas fa-exclamation-triangle';
        case 'medium-stock': return 'fas fa-exclamation-circle';
        case 'good-stock': return 'fas fa-check-circle';
        default: return 'fas fa-question-circle';
    }
}

function get_stock_status_text($status) {
    switch ($status) {
        case 'out-of-stock': return 'Out of Stock';
        case 'low-stock': return 'Low Stock';
        case 'medium-stock': return 'Medium Stock';
        case 'good-stock': return 'Good Stock';
        default: return 'Unknown';
    }
}
?>

<div class="inventory-layout">
<div class="page-header">
    <h1 class="page-title"><i class="fas fa-boxes me-3"></i>Inventory Management</h1>
    <p class="page-subtitle">Professional inventory analytics, parts management, and stock level monitoring</p>
</div>

<!-- Analytics Dashboard -->
<div class="row inventory-analytics">
    <div class="col-md-3">
        <div class="card border-primary">
            <div class="card-body text-center">
                <i class="fas fa-cubes fa-2x text-primary mb-2"></i>
                <h4 class="card-title"><?php echo number_format($analytics['total_parts']); ?></h4>
                <p class="card-text text-muted">Total Parts</p>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card border-success">
            <div class="card-body text-center">
                <i class="fas fa-dollar-sign fa-2x text-success mb-2"></i>
                <h4 class="card-title">$<?php echo number_format($analytics['total_value'], 2); ?></h4>
                <p class="card-text text-muted">Total Value</p>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card border-warning">
            <div class="card-body text-center">
                <i class="fas fa-exclamation-triangle fa-2x text-warning mb-2"></i>
                <h4 class="card-title"><?php echo $analytics['low_stock_count']; ?></h4>
                <p class="card-text text-muted">Low Stock Items</p>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card border-danger">
            <div class="card-body text-center">
                <i class="fas fa-times-circle fa-2x text-danger mb-2"></i>
                <h4 class="card-title"><?php echo $analytics['out_of_stock_count']; ?></h4>
                <p class="card-text text-muted">Out of Stock</p>
            </div>
        </div>
    </div>
</div>

<!-- Action Buttons -->
<div class="row inventory-actions">
    <div class="col-12">
        <div class="d-flex gap-2 flex-wrap">
            <button class="btn btn-primary" onclick="showAddPartModal()">
                <i class="fas fa-plus me-2"></i>Add New Part
            </button>
            <button class="btn btn-success" onclick="showStockAdjustmentModal()">
                <i class="fas fa-adjust me-2"></i>Stock Adjustment
            </button>
            <button class="btn btn-info" onclick="showReorderReport()">
                <i class="fas fa-shopping-cart me-2"></i>Reorder Report
            </button>
            <button class="btn btn-secondary" onclick="exportInventory()">
                <i class="fas fa-download me-2"></i>Export Data
            </button>
        </div>
    </div>
</div>

<?php if ($show_reorder_report): ?>
<div class="card mb-4 reorder-report-card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <div>
            <h5 class="card-title mb-0"><i class="fas fa-shopping-cart me-2"></i>Reorder Report</h5>
            <small class="text-muted">Run Date: <?php echo date('Y-m-d H:i'); ?></small>
        </div>
        <div>
            <a href="index.php?nav=inventory" class="btn btn-sm btn-secondary me-2">
                <i class="fas fa-arrow-left me-1"></i>Back to Inventory
            </a>
            <button type="button" class="btn btn-sm btn-success" onclick="exportReorderCsv()">
                <i class="fas fa-file-csv me-1"></i>Export CSV
            </button>
        </div>
    </div>
    <div class="card-body">
        <form method="get" action="index.php" class="row g-3 mb-3">
            <input type="hidden" name="nav" value="inventory">
            <input type="hidden" name="report" value="reorder">
            <div class="col-md-3">
                <label class="form-label">Category</label>
                <input type="text" class="form-control" name="category" value="<?php echo htmlspecialchars($reorder_filters['category']); ?>" placeholder="e.g. Mechanical">
            </div>
            <div class="col-md-3">
                <label class="form-label">Supplier</label>
                <input type="text" class="form-control" name="supplier" value="<?php echo htmlspecialchars($reorder_filters['supplier']); ?>" placeholder="Supplier or manufacturer">
            </div>
            <div class="col-md-2">
                <label class="form-label">Stock Status</label>
                <select class="form-select" name="stock_status">
                    <option value="">All</option>
                    <option value="below_reorder" <?php echo $reorder_filters['stock_status'] === 'below_reorder' ? 'selected' : ''; ?>>Below Reorder</option>
                    <option value="healthy" <?php echo $reorder_filters['stock_status'] === 'healthy' ? 'selected' : ''; ?>>Healthy</option>
                </select>
            </div>
            <div class="col-md-2 d-flex align-items-end">
                <div class="form-check">
                    <input class="form-check-input" type="checkbox" name="below_reorder" id="belowReorder" value="1" <?php echo $reorder_filters['below_reorder'] ? 'checked' : ''; ?>>
                    <label class="form-check-label" for="belowReorder">Only below reorder point</label>
                </div>
            </div>
            <div class="col-md-2 d-flex align-items-end">
                <button type="submit" class="btn btn-primary w-100">Run Report</button>
            </div>
        </form>

        <div class="table-responsive">
            <table class="table table-hover table-sm align-middle">
                <thead class="table-light">
                    <tr>
                        <th>Part Code</th>
                        <th>Description</th>
                        <th>Category</th>
                        <th>Supplier</th>
                        <th>On Hand</th>
                        <th>Reserved</th>
                        <th>On Order</th>
                        <th>Available</th>
                        <th>Reorder Point</th>
                        <th>Suggested Qty</th>
                        <th>Lead Time</th>
                        <th>Reorder Value</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($reorder_report)): ?>
                        <tr>
                            <td colspan="13" class="text-center text-muted py-4">No reorder items found for the selected filters.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($reorder_report as $item): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($item['part_code'] ?? ''); ?></td>
                                <td><?php echo htmlspecialchars($item['part_name'] ?? ''); ?></td>
                                <td><?php echo htmlspecialchars($item['category'] ?? ''); ?></td>
                                <td><?php echo htmlspecialchars($item['supplier'] ?? ''); ?></td>
                                <td><?php echo intval($item['on_hand_qty']); ?></td>
                                <td><?php echo intval($item['reserved_qty']); ?></td>
                                <td><?php echo intval($item['on_order_qty']); ?></td>
                                <td><?php echo intval($item['available_qty']); ?></td>
                                <td><?php echo intval($item['reorder_point']); ?></td>
                                <td><?php echo intval($item['suggested_qty']); ?></td>
                                <td><?php echo intval($item['lead_time_days']); ?></td>
                                <td><?php echo number_format(floatval($item['suggested_value'] ?? 0), 2); ?></td>
                                <td>
                                    <span class="badge bg-<?php echo $item['available_qty'] <= $item['reorder_point'] ? 'danger' : 'success'; ?>">
                                        <?php echo htmlspecialchars($item['reorder_status'] ?? ''); ?>
                                    </span>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- Main Content -->
<div class="row">
    <!-- Parts Inventory Section - Full Width -->
    <div class="col-12">
        <div class="card inventory-card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="card-title mb-0"><i class="fas fa-list me-2"></i>Parts Inventory</h5>
                <div class="d-flex gap-2">
                    <button class="btn btn-sm btn-primary" onclick="showAddPartModal()">
                        <i class="fas fa-plus me-1"></i>Add Part
                    </button>
                    <button class="btn btn-sm btn-success" onclick="showStockAdjustmentModal()">
                        <i class="fas fa-adjust me-1"></i>Adjust Stock
                    </button>
                </div>
            </div>
            <div class="card-body p-0">
                <!-- Search and Filter Form -->
                <div class="search-filter-container p-3 border-bottom bg-light">
                    <form method="GET" class="row g-3">
                        <div class="col-md-4">
                            <label class="form-label fw-bold">
                                <i class="fas fa-search me-2"></i>Search Parts
                            </label>
                            <div class="input-group">
                                <input type="text" class="form-control" name="search" 
                                       value="<?php echo htmlspecialchars($search_query); ?>" 
                                       placeholder="Search by part code, name, number, description...">
                                <button class="btn btn-outline-primary" type="submit">
                                    <i class="fas fa-search"></i>
                                </button>
                            </div>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label fw-bold">
                                <i class="fas fa-tag me-2"></i>Category
                            </label>
                            <select class="form-select" name="category_filter">
                                <option value="">All Categories</option>
                                <option value="Mechanical" <?php echo $category_filter === 'Mechanical' ? 'selected' : ''; ?>>Mechanical</option>
                                <option value="Electrical" <?php echo $category_filter === 'Electrical' ? 'selected' : ''; ?>>Electrical</option>
                                <option value="Hydraulic" <?php echo $category_filter === 'Hydraulic' ? 'selected' : ''; ?>>Hydraulic</option>
                                <option value="Pneumatic" <?php echo $category_filter === 'Pneumatic' ? 'selected' : ''; ?>>Pneumatic</option>
                                <option value="Structural" <?php echo $category_filter === 'Structural' ? 'selected' : ''; ?>>Structural</option>
                                <option value="Consumable" <?php echo $category_filter === 'Consumable' ? 'selected' : ''; ?>>Consumable</option>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label fw-bold">
                                <i class="fas fa-industry me-2"></i>Manufacturer
                            </label>
                            <input type="text" class="form-control" name="manufacturer_filter" 
                                   value="<?php echo htmlspecialchars($manufacturer_filter); ?>" 
                                   placeholder="Filter by manufacturer">
                        </div>
                        <div class="col-md-2">
                            <label class="form-label fw-bold">
                                <i class="fas fa-chart-line me-2"></i>Stock Status
                            </label>
                            <select class="form-select" name="stock_status_filter">
                                <option value="">All Status</option>
                                <option value="out-of-stock" <?php echo $stock_status_filter === 'out-of-stock' ? 'selected' : ''; ?>>Out of Stock</option>
                                <option value="low-stock" <?php echo $stock_status_filter === 'low-stock' ? 'selected' : ''; ?>>Low Stock</option>
                                <option value="medium-stock" <?php echo $stock_status_filter === 'medium-stock' ? 'selected' : ''; ?>>Medium Stock</option>
                                <option value="good-stock" <?php echo $stock_status_filter === 'good-stock' ? 'selected' : ''; ?>>Good Stock</option>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label fw-bold">
                                <i class="fas fa-sort me-2"></i>Sort By
                            </label>
                            <select class="form-select" name="sort">
                                <option value="name" <?php echo $sort_by === 'name' ? 'selected' : ''; ?>>Name</option>
                                <option value="code" <?php echo $sort_by === 'code' ? 'selected' : ''; ?>>Part Code</option>
                                <option value="stock" <?php echo $sort_by === 'stock' ? 'selected' : ''; ?>>Stock Level</option>
                                <option value="cost" <?php echo $sort_by === 'cost' ? 'selected' : ''; ?>>Unit Cost</option>
                            </select>
                        </div>
                        <div class="col-12">
                            <div class="d-flex gap-2">
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-filter me-2"></i>Apply Filters
                                </button>
                                <a href="inventory_setup.php" class="btn btn-outline-secondary">
                                    <i class="fas fa-times me-2"></i>Clear Filters
                                </a>
                                <?php if (!empty($search_query) || !empty($category_filter) || !empty($manufacturer_filter) || !empty($stock_status_filter)): ?>
                                    <div class="ms-auto">
                                        <span class="badge bg-info">
                                            <i class="fas fa-info-circle me-1"></i>
                                            <?php echo count($parts); ?> results found
                                        </span>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </form>
                </div>

                <?php if (empty($parts)): ?>
                    <?php if (!empty($add_message)): ?>
                        <div class="alert alert-<?php echo strpos($add_message, 'successfully') !== false ? 'success' : 'danger'; ?> m-3" role="alert">
                            <?php echo htmlspecialchars($add_message); ?>
                        </div>
                    <?php endif; ?>

                    <div class="text-center py-5">
                        <i class="fas fa-box-open fa-4x text-muted mb-3"></i>
                        <h5 class="text-muted">No Parts Found</h5>
                        <p class="text-muted">
                            <?php if (!empty($search_query) || !empty($category_filter) || !empty($manufacturer_filter) || !empty($stock_status_filter)): ?>
                                No parts match your search criteria. Try adjusting your filters.
                            <?php else: ?>
                                Start by adding your first inventory item
                            <?php endif; ?>
                        </p>
                        <button class="btn btn-primary mt-3" onclick="showAddPartModal()">
                            <i class="fas fa-plus me-2"></i>Add New Part
                        </button>
                    </div>
                <?php else: ?>
                    <div class="inventory-table-container">
                        <table class="table table-hover table-sm mb-0">
                            <thead class="table-light sticky-top">
                                <tr>
                                    <th style="width: 120px;">Part Code</th>
                                    <th style="width: 200px;">Name & Description</th>
                                    <th style="width: 100px;">Category</th>
                                    <th style="width: 100px;">Manufacturer</th>
                                    <th style="width: 100px;">Criticality</th>
                                    <th style="width: 100px;">Stock Level</th>
                                    <th style="width: 120px;">Status</th>
                                    <th style="width: 100px;">Unit Cost</th>
                                    <th style="width: 120px;">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($parts as $part): ?>
                                <tr>
                                    <td><strong><?php echo htmlspecialchars($part['part_code']); ?></strong></td>
                                    <td>
                                        <div class="fw-bold"><?php echo htmlspecialchars($part['part_name']); ?></div>
                                        <?php if ($part['description']): ?>
                                            <small class="text-muted"><?php echo htmlspecialchars(substr($part['description'], 0, 40)); ?><?php echo strlen($part['description']) > 40 ? '...' : ''; ?></small>
                                        <?php endif; ?>
                                    </td>
                                    <td><span class="badge bg-light text-dark"><?php echo htmlspecialchars($part['category'] ?? 'N/A'); ?></span></td>
                                    <td><?php echo htmlspecialchars($part['manufacturer'] ?? 'N/A'); ?></td>
                                    <td>
                                        <span class="badge bg-<?php 
                                            switch($part['criticality_level']) {
                                                case 'critical': echo 'danger'; break;
                                                case 'high': echo 'warning'; break;
                                                case 'medium': echo 'info'; break;
                                                default: echo 'secondary';
                                            }
                                        ?>">
                                            <?php echo ucfirst($part['criticality_level'] ?? 'low'); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <div class="d-flex flex-column align-items-center">
                                            <span class="badge bg-secondary fs-6"><?php echo $part['total_on_hand']; ?></span>
                                            <?php if ($part['reorder_point'] > 0): ?>
                                                <small class="text-muted">Reorder: <?php echo $part['reorder_point']; ?></small>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                    <td>
                                        <span class="badge bg-<?php echo get_stock_status_color($part['stock_status']); ?>">
                                            <i class="<?php echo get_stock_status_icon($part['stock_status']); ?> me-1"></i>
                                            <?php echo get_stock_status_text($part['stock_status']); ?>
                                        </span>
                                    </td>
                                    <td class="fw-bold">$<?php echo number_format($part['unit_cost'] ?? 0, 2); ?></td>
                                    <td>
                                        <div class="btn-group btn-group-sm">
                                            <button type="button" class="btn btn-outline-primary btn-sm" onclick="viewPartDetails(<?php echo $part['id']; ?>)">
                                                <i class="fas fa-eye"></i>
                                            </button>
                                            <button type="button" class="btn btn-outline-warning btn-sm" onclick="editPart(<?php echo $part['id']; ?>)">
                                                <i class="fas fa-edit"></i>
                                            </button>
                                            <button type="button" class="btn btn-outline-success btn-sm" onclick="adjustStock(<?php echo $part['id']; ?>)">
                                                <i class="fas fa-plus-minus"></i>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Low Stock Alert Section - Below Parts Inventory -->
<div class="row mt-4">
    <div class="col-12">
        <div class="card inventory-card">
            <div class="card-header bg-danger text-white">
                <h6 class="card-title mb-0"><i class="fas fa-exclamation-triangle me-2"></i>Low Stock Alert</h6>
            </div>
            <div class="card-body">
                <?php if (empty($low_stock_items)): ?>
                    <div class="text-center py-4">
                        <i class="fas fa-check-circle fa-3x text-success mb-3"></i>
                        <h6 class="text-muted">All items are sufficiently stocked</h6>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th style="width: 150px;">Part Code</th>
                                    <th>Part Name</th>
                                    <th style="width: 120px;" class="text-center">Current Stock</th>
                                    <th style="width: 120px;" class="text-center">Reorder Point</th>
                                    <th style="width: 120px;" class="text-center">Shortage</th>
                                    <th style="width: 150px;" class="text-center">Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($low_stock_items as $item): ?>
                                <tr>
                                    <td><strong class="text-danger"><?php echo htmlspecialchars($item['part_code']); ?></strong></td>
                                    <td><?php echo htmlspecialchars($item['part_name']); ?></td>
                                    <td class="text-center">
                                        <span class="badge bg-secondary"><?php echo $item['total_on_hand']; ?></span>
                                    </td>
                                    <td class="text-center">
                                        <span class="badge bg-warning"><?php echo $item['reorder_point']; ?></span>
                                    </td>
                                    <td class="text-center">
                                        <span class="badge bg-danger"><?php echo max(0, $item['shortage_qty']); ?></span>
                                    </td>
                                    <td class="text-center">
                                        <span class="badge bg-danger">
                                            <i class="fas fa-exclamation-triangle me-1"></i>Low Stock
                                        </span>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Recent Transactions Section -->
<div class="row mt-4">
    <div class="col-12">
        <div class="card inventory-card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h6 class="card-title mb-0"><i class="fas fa-history me-2"></i>Recent Transactions</h6>
                <small class="text-muted"><?php echo count($recent_transactions); ?> items</small>
            </div>
            <div class="card-body">
                <?php if (empty($recent_transactions)): ?>
                    <p class="text-muted mb-0 text-center">No recent transactions</p>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover table-sm mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th style="width: 150px;">Part Name</th>
                                    <th style="width: 120px;">Transaction Type</th>
                                    <th style="width: 120px;" class="text-center">Quantity Change</th>
                                    <th style="width: 150px;">Date & Time</th>
                                    <th style="width: 100px;">User</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($recent_transactions as $transaction): ?>
                                <tr>
                                    <td><strong><?php echo htmlspecialchars($transaction['part_name'] ?? 'Unknown Part'); ?></strong></td>
                                    <td><span class="badge bg-info"><?php echo ucfirst($transaction['transaction_type']); ?></span></td>
                                    <td class="text-center">
                                        <span class="badge bg-<?php echo $transaction['quantity_change'] > 0 ? 'success' : 'danger'; ?> fs-6">
                                            <?php echo $transaction['quantity_change'] > 0 ? '+' : ''; ?><?php echo $transaction['quantity_change']; ?>
                                        </span>
                                    </td>
                                    <td><?php echo date('M d, Y H:i', strtotime($transaction['created_at'])); ?></td>
                                    <td><?php echo htmlspecialchars($transaction['username'] ?? 'System'); ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>
</div>

<!-- Modals -->
<!-- Add Part Modal -->
<div class="modal fade" id="addPartModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fas fa-plus me-2"></i>Add New Part</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="addPartForm" method="post" action="index.php?nav=inventory">
                <input type="hidden" name="action" value="add_part">
                <input type="hidden" name="nav" value="inventory">
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label class="form-label">Part Code *</label>
                                <input type="text" class="form-control" name="part_code" required>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label class="form-label">Part Number</label>
                                <input type="text" class="form-control" name="part_number">
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label class="form-label">Part Name *</label>
                                <input type="text" class="form-control" name="part_name" required>
                            </div>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Description</label>
                        <textarea class="form-control" name="description" rows="2"></textarea>
                    </div>
                    <div class="row">
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label class="form-label">Category</label>
                                <select class="form-select" name="category">
                                    <option value="Mechanical">Mechanical</option>
                                    <option value="Electrical">Electrical</option>
                                    <option value="Hydraulic">Hydraulic</option>
                                    <option value="Other">Other</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label class="form-label">Sub Category</label>
                                <input type="text" class="form-control" name="sub_category" placeholder="e.g. Pumps, Motors">
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label class="form-label">Criticality Level</label>
                                <select class="form-select" name="criticality_level">
                                    <option value="low">Low</option>
                                    <option value="medium">Medium</option>
                                    <option value="high">High</option>
                                    <option value="critical">Critical</option>
                                </select>
                            </div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label class="form-label">Asset Compatibility</label>
                                <input type="text" class="form-control" name="asset_compatibility" placeholder="e.g. Pump-101, Motor-200">
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label class="form-label">Unit of Measure</label>
                                <input type="text" class="form-control" name="unit_of_measure" placeholder="pcs/kg/l">
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label class="form-label">ABC Classification</label>
                                <select class="form-select" name="abc_classification">
                                    <option value="A">A (High Value)</option>
                                    <option value="B">B (Medium Value)</option>
                                    <option value="C">C (Low Value)</option>
                                </select>
                            </div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label class="form-label">Manufacturer</label>
                                <input type="text" class="form-control" name="manufacturer">
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label class="form-label">OEM Part Number</label>
                                <input type="text" class="form-control" name="oem_part_number">
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label class="form-label">Supplier Part Number</label>
                                <input type="text" class="form-control" name="supplier_part_number">
                            </div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label class="form-label">Vendor Reference</label>
                                <select class="form-select" name="vendor_reference">
                                    <option value="">Select vendor reference</option>
                                    <?php foreach ($vendors_list as $vendor): ?>
                                        <option value="<?php echo htmlspecialchars($vendor['vendor_code']); ?>"><?php echo htmlspecialchars($vendor['vendor_name'] . ' (' . $vendor['vendor_code'] . ')'); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label class="form-label">Warranty Period (months)</label>
                                <input type="number" class="form-control" name="warranty_period_months" min="0">
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="mb-3">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="is_hazmat" value="1" id="is_hazmat">
                                    <label class="form-check-label" for="is_hazmat">
                                        Hazardous Material
                                    </label>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-4">
                            <div class="mb-3">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="is_serialized" value="1" id="is_serialized">
                                    <label class="form-check-label" for="is_serialized">
                                        Serialized Item
                                    </label>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label class="form-label">Min Quantity</label>
                                <input type="number" class="form-control" name="minimum_quantity" min="0">
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label class="form-label">Max Quantity</label>
                                <input type="number" class="form-control" name="maximum_quantity" min="0">
                            </div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label class="form-label">Lead Time (days)</label>
                                <input type="number" class="form-control" name="lead_time_days" min="0">
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label class="form-label">Unit Cost</label>
                                <div class="input-group">
                                    <span class="input-group-text">$</span>
                                    <input type="number" class="form-control" name="unit_cost" step="0.01" min="0">
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label class="form-label">Reorder Point</label>
                                <input type="number" class="form-control" name="reorder_point" min="0">
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label class="form-label">Safety Stock</label>
                                <input type="number" class="form-control" name="safety_stock_level" min="0">
                            </div>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Notes</label>
                        <textarea class="form-control" name="notes" rows="2" placeholder="Additional notes about this part"></textarea>
                    </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Add Part</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Stock Adjustment Modal -->
<div class="modal fade" id="stockAdjustmentModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fas fa-adjust me-2"></i>Stock Adjustment</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="stockAdjustmentForm" method="post">
                <input type="hidden" name="action" value="adjust_stock">
                <input type="hidden" name="nav" value="inventory">
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Select Part</label>
                        <select class="form-select" name="part_id" required>
                            <option value="">Choose a part...</option>
                            <?php foreach ($parts as $part): ?>
                            <option value="<?php echo $part['id']; ?>">
                                <?php echo htmlspecialchars($part['part_code'] . ' - ' . $part['part_name']); ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Adjustment Type</label>
                        <select class="form-select" name="adjustment_type" required>
                            <option value="addition" selected>Stock Addition</option>
                            <option value="reduction">Stock Reduction</option>
                            <option value="correction">Stock Correction</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Quantity</label>
                        <input type="number" class="form-control" name="quantity" required min="1" value="1">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Reason</label>
                        <textarea class="form-control" name="reason" rows="2" placeholder="Enter reason for adjustment"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Adjust Stock</button>
                </div>
            </form>
        </div>
    </div>
</div>

<style>
.timeline {
    position: relative;
    padding-left: 30px;
}

.timeline-item {
    position: relative;
    margin-left: -30px;
    padding-left: 40px;
}

.timeline-marker {
    position: absolute;
    left: -8px;
    top: 0;
    width: 16px;
    height: 16px;
    border-radius: 50%;
    border: 3px solid white;
    box-shadow: 0 0 0 2px #dee2e6;
}

.timeline-content {
    background: #f8f9fa;
    padding: 10px 15px;
    border-radius: 8px;
    border-left: 3px solid #007bff;
}

.card {
    box-shadow: 0 2px 10px rgba(0,0,0,0.05);
    border: 1px solid #e9ecef;
}

.card-header {
    border-bottom: 1px solid #e9ecef;
    font-weight: 600;
}

.badge {
    font-size: 0.75em;
}

.btn-group-sm .btn {
    padding: 0.25rem 0.5rem;
}

/* Search and Filter Styles */
.search-filter-container {
    background: #f8f9fa;
    border: 1px solid #dee2e6;
    border-radius: 0.375rem;
    padding: 1.5rem;
    margin-bottom: 1.5rem;
    box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075);
}

.search-filter-container .form-label {
    font-weight: 600;
    color: #495057;
    margin-bottom: 0.5rem;
}

.search-filter-container .form-control,
.search-filter-container .form-select {
    border: 1px solid #ced4da;
    border-radius: 0.25rem;
    padding: 0.375rem 0.75rem;
    font-size: 0.875rem;
}

.search-filter-container .form-control:focus,
.search-filter-container .form-select:focus {
    border-color: #80bdff;
    box-shadow: 0 0 0 0.2rem rgba(0, 123, 255, 0.25);
}

.search-filter-container .btn {
    padding: 0.375rem 1rem;
    font-size: 0.875rem;
    font-weight: 500;
}

.search-filter-container .btn-primary {
    background-color: #007bff;
    border-color: #007bff;
}

.search-filter-container .btn-secondary {
    background-color: #6c757d;
    border-color: #6c757d;
}

.search-filter-container .row {
    align-items: end;
}

.search-filter-container .col-md-3,
.search-filter-container .col-md-2,
.search-filter-container .col-md-1 {
    margin-bottom: 1rem;
}

@media (max-width: 768px) {
    .search-filter-container .row {
        text-align: center;
    }
    
    .search-filter-container .col-md-3,
    .search-filter-container .col-md-2,
    .search-filter-container .col-md-1 {
        margin-bottom: 0.75rem;
    }
}

/* Search Results Info */
.search-results-info {
    background: #e9ecef;
    border: 1px solid #ced4da;
    border-radius: 0.25rem;
    padding: 0.75rem 1rem;
    margin-bottom: 1rem;
    font-size: 0.875rem;
    color: #495057;
}

.search-results-info strong {
    color: #212529;
}

</style>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
// Bootstrap modal initialization - removed to avoid conflicts
// document.addEventListener('DOMContentLoaded', function() {
//     var modals = document.querySelectorAll('.modal');
//     modals.forEach(function(modal) {
//         new bootstrap.Modal(modal);
//     });
// });

function showAddPartModal() {
    var modal = new bootstrap.Modal(document.getElementById('addPartModal'));
    modal.show();
}

function showStockAdjustmentModal() {
    console.log('showStockAdjustmentModal called');
    try {
        var modalEl = document.getElementById('stockAdjustmentModal');
        if (!modalEl) {
            throw new Error('stockAdjustmentModal element not found');
        }
        if (modalEl.parentNode !== document.body) {
            document.body.appendChild(modalEl);
        }
        var modal = new bootstrap.Modal(modalEl, { backdrop: true, keyboard: true });
        console.log('Modal created', modal);
        modal.show();
        console.log('Modal shown');
    } catch (error) {
        console.error('Error showing stock adjustment modal:', error);
        alert('Error opening stock adjustment modal: ' + error.message);
    }
}

function showReorderReport() {
    window.location.href = 'index.php?nav=inventory&report=reorder';
}

function exportReorderCsv() {
    var url = new URL(window.location.href);
    url.searchParams.set('report', 'reorder');
    url.searchParams.set('export_csv', '1');
    window.location.href = url.toString();
}

function exportInventory() {
    alert('Export feature coming soon!');
}

function viewPartDetails(partId) {
    if (!partId || isNaN(partId)) {
        console.error('Invalid partId for viewPartDetails:', partId);
        return;
    }
    window.location.href = 'index.php?nav=inventory&edit_part_id=' + encodeURIComponent(partId) + '&view_only=1';
}

function editPart(partId) {
    if (!partId || isNaN(partId)) {
        console.error('Invalid partId for editPart:', partId);
        return;
    }
    window.location.href = 'index.php?nav=inventory&edit_part_id=' + encodeURIComponent(partId);
}

function adjustStock(partId) {
    if (!partId || isNaN(partId)) {
        console.error('Invalid partId for adjustStock:', partId);
        alert('Selected part is invalid. Please refresh and try again.');
        return;
    }

    var select = document.querySelector('#stockAdjustmentForm select[name="part_id"]');
    if (!select) {
        console.error('Stock adjustment select not found');
        alert('Stock adjustment form not available. Please reload the page.');
        return;
    }

    select.value = partId;

    var modalEl = document.getElementById('stockAdjustmentModal');
    if (!modalEl) {
        console.error('stockAdjustmentModal element not found');
        alert('Stock adjustment modal is not available. Please reload the page.');
        return;
    }
    if (modalEl.parentNode !== document.body) {
        document.body.appendChild(modalEl);
    }

    var modal = new bootstrap.Modal(modalEl, { backdrop: true, keyboard: true });
    modal.show();
}

// Form submissions
// Leave Add Part form as normal submit to server-side handler
var addPartForm = document.getElementById('addPartForm');
if (addPartForm) {
    addPartForm.addEventListener('submit', function() {
        // default behavior: submit to server and reload with message
    });
}

// Stock adjustments now submit normally to server-side handler
var stockAdjustmentForm = document.getElementById('stockAdjustmentForm');
if (stockAdjustmentForm) {
    stockAdjustmentForm.addEventListener('submit', function(event) {
        console.log('Stock adjustment form submitted');
        // default submit behavior to post adjustment and refresh UI
    });
}
</script>

    <a href="index.php?nav=dashboard">Back to Dashboard</a>
</p>