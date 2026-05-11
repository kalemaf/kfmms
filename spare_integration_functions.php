<?php
/**
 * SPARE PARTS INTEGRATION FUNCTIONS
 * 
 * Manages the integration between equipment_spares and general inventory
 * Tracks costs and inventory transactions
 */

/**
 * Check if a part_id exists in parts_master table
 */
function part_id_exists($part_id, $connection) {
    if (!$part_id) return false;

    global $db_type;
    if ($db_type === 'sqlite' && $connection instanceof PDO) {
        $stmt = $connection->prepare("SELECT id FROM parts_master WHERE id = ?");
        $stmt->execute([$part_id]);
        return $stmt->fetch(PDO::FETCH_ASSOC) !== false;
    } else {
        $result = $connection->query("SELECT id FROM parts_master WHERE id = {$part_id} LIMIT 1");
        return $result && $result->num_rows > 0;
    }
}

/**
 * Find an existing parts_master record for a spare item.
 * Tries exact part_number/part_code match first, then part_name match.
 */
function find_existing_parts_master_row($part_name, $part_number, $connection) {
    global $db_type;
    
    $part_name = trim($part_name);
    $part_number = trim($part_number);

    if ($part_number !== '') {
        if ($db_type === 'sqlite' && $connection instanceof PDO) {
            $stmt = $connection->prepare("SELECT id FROM parts_master WHERE part_number = ? OR part_code = ? ORDER BY total_on_hand DESC, id ASC LIMIT 1");
            $stmt->execute([$part_number, $part_number]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($result) return $result['id'];
        } else {
            $escaped_number = $connection->real_escape_string($part_number);
            $query = "SELECT id FROM parts_master WHERE part_number = '{$escaped_number}' OR part_code = '{$escaped_number}' ORDER BY total_on_hand DESC, id ASC LIMIT 1";
            $result = $connection->query($query);
            if ($result && $result->num_rows > 0) {
                return $result->fetch_assoc()['id'];
            }
        }
    }

    if ($part_name !== '') {
        if ($db_type === 'sqlite' && $connection instanceof PDO) {
            $stmt = $connection->prepare("SELECT id FROM parts_master WHERE LOWER(part_name) = LOWER(?) ORDER BY total_on_hand DESC, id ASC LIMIT 1");
            $stmt->execute([$part_name]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($result) return $result['id'];
        } else {
            $escaped_name = $connection->real_escape_string($part_name);
            $query = "SELECT id FROM parts_master WHERE LOWER(part_name) = LOWER('{$escaped_name}') ORDER BY total_on_hand DESC, id ASC LIMIT 1";
            $result = $connection->query($query);
            if ($result && $result->num_rows > 0) {
                return $result->fetch_assoc()['id'];
            }
        }
    }

    return null;
}

/**
 * Normalize a part code from a spare item.
 */
function normalize_part_code($part_number, $part_name) {
    $part_number = trim($part_number);
    if ($part_number !== '') {
        return $part_number;
    }

    $code = preg_replace('/[^A-Za-z0-9]+/', '_', strtolower(trim($part_name)));
    $code = trim($code, '_');
    return $code !== '' ? $code : 'SPARE_' . time();
}

/**
 * Create or link an equipment spare to parts_master
 * Used to ensure spares are in the general inventory system
 */
function link_spare_to_parts_master($equipment_spare_id, $part_name, $part_number, $connection, $unit_cost = 0) {
    global $db_type;
    
    $part_id = find_existing_parts_master_row($part_name, $part_number, $connection);

    if (!$part_id) {
        $part_code = normalize_part_code($part_number, $part_name);
        
        if ($db_type === 'sqlite' && $connection instanceof PDO) {
            $stmt = $connection->prepare("INSERT INTO parts_master (part_code, part_number, part_name, unit_cost, is_active, total_on_hand) 
                       VALUES (?, ?, ?, ?, 1, 0)");
            if ($stmt->execute([$part_code, $part_number, $part_name, floatval($unit_cost)])) {
                $part_id = $connection->lastInsertId();
            } else {
                return false;
            }
        } else {
            $escaped_part_code = $connection->real_escape_string($part_code);
            $escaped_part_number = $connection->real_escape_string($part_number);
            $escaped_part_name = $connection->real_escape_string($part_name);

            $insert_sql = "INSERT INTO parts_master (part_code, part_number, part_name, unit_cost, is_active) 
                           VALUES (
                               '{$escaped_part_code}',
                               '{$escaped_part_number}',
                               '{$escaped_part_name}',
                               " . floatval($unit_cost) . ",
                               1
                           )";
            if ($connection->query($insert_sql)) {
                if (method_exists($connection, 'lastInsertId')) {
                    $part_id = $connection->lastInsertId();
                } elseif (isset($connection->insert_id)) {
                    $part_id = $connection->insert_id;
                } else {
                    $part_id = null;
                }
            } else {
                return false;
            }
        }
    }

    if (!$part_id) {
        return false;
    }

    if ($db_type === 'sqlite' && $connection instanceof PDO) {
        $stmt = $connection->prepare("UPDATE equipment_spares SET part_id = ? WHERE id = ?");
        $stmt->execute([$part_id, $equipment_spare_id]);
    } else {
        $update_sql = "UPDATE equipment_spares SET part_id = {$part_id} WHERE id = {$equipment_spare_id}";
        $connection->query($update_sql);
    }
    
    return $part_id;
}

/**
 * Get or create stock entry for a part in a warehouse
 */
function get_or_create_stock_locale($part_id, $connection, $warehouse_location_id = 1) {
    global $db_type;
    $tenant_id = (int)($_SESSION['tenant_id'] ?? 1);
    
    $part_id = (int)$part_id;
    $warehouse_location_id = (int)$warehouse_location_id;
    
    if ($db_type === 'sqlite' && $connection instanceof PDO) {
        $stmt = $connection->prepare("SELECT id FROM stock_locales WHERE part_id = ? AND warehouse_location_id = ? AND tenant_id = ?");
        $stmt->execute([$part_id, $warehouse_location_id, $tenant_id]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($result) {
            return $result['id'];
        } else {
            // Create new stock locale with tenant_id
            $insert_stmt = $connection->prepare("INSERT INTO stock_locales (part_id, warehouse_location_id, quantity_on_hand, tenant_id) 
                           VALUES (?, ?, 0, ?)");
            if ($insert_stmt->execute([$part_id, $warehouse_location_id, $tenant_id])) {
                return $connection->lastInsertId();
            }
            return false;
        }
    } else {
        $check_sql = "SELECT id FROM stock_locales WHERE part_id = {$part_id} AND warehouse_location_id = {$warehouse_location_id} AND tenant_id = {$tenant_id}";
        $result = $connection->query($check_sql);
        
        if ($result && $result->num_rows > 0) {
            $stock = $result->fetch_assoc();
            return $stock['id'];
        } else {
            // Create new stock locale with tenant_id
            $insert_sql = "INSERT INTO stock_locales (part_id, warehouse_location_id, quantity_on_hand, tenant_id) 
                           VALUES ({$part_id}, {$warehouse_location_id}, 0, {$tenant_id})";
            if ($connection->query($insert_sql)) {
                if (method_exists($connection, 'lastInsertId')) {
                    return $connection->lastInsertId();
                }
                return $connection->insert_id;
            }
            return false;
        }
    }
}

/**
 * Reduce spare inventory across both equipment_spares and stock_locales
 * Creates inventory transaction record for audit trail
 * Keeps equipment_spares and parts_master synchronized
 */
function reduce_spare_inventory($spare_id, $quantity, $wo_id, $user_id, $reason, $connection) {
    global $db_type;
    $tenant_id = (int)($_SESSION['tenant_id'] ?? 1);
    
    // Get spare details including part_id (with tenant_id filter)
    $spare_sql = "SELECT * FROM equipment_spares WHERE id = {$spare_id} AND tenant_id = {$tenant_id}";
    $spare_result = $connection->query($spare_sql);
    
    if (!$spare_result || $spare_result->num_rows === 0) {
        return false;
    }
    
    $spare = $spare_result->fetch_assoc();
    
    // Automatically link spare to parts_master if not already linked, or if linked part doesn't exist
    if (empty($spare['part_id']) || !part_id_exists($spare['part_id'], $connection)) {
        $linked = link_spare_to_parts_master($spare_id, $spare['part_name'], $spare['part_number'], $connection, 0);
        if ($linked) {
            $linked_spare = $connection->query("SELECT part_id FROM equipment_spares WHERE id = {$spare_id}");
            if ($linked_spare) {
                $spare['part_id'] = $linked_spare->fetch_assoc()['part_id'];
            }
        }
    }

    // Reduce equipment_spares quantity (with tenant_id filter)
    $new_spare_qty = max(0, intval($spare['quantity']) - $quantity);
    $connection->query("UPDATE equipment_spares 
                       SET quantity = {$new_spare_qty}
                       WHERE id = {$spare_id} AND tenant_id = {$tenant_id}");
    
    // If linked to parts_master, also reduce stock_locales
    if (!empty($spare['part_id'])) {
        $part_id = intval($spare['part_id']);
        $timestamp_func = ($db_type === 'sqlite') ? "datetime('now')" : "NOW()";
        
        // Get stock locale entry
        $stock_id = get_or_create_stock_locale($part_id, $connection, 1);
        
        // Reduce stock_locales quantity
        if ($db_type === 'sqlite') {
            $reduction_sql = "UPDATE stock_locales 
                              SET quantity_on_hand = CASE WHEN quantity_on_hand - {$quantity} < 0 THEN 0 ELSE quantity_on_hand - {$quantity} END,
                                  quantity_issued = quantity_issued + {$quantity},
                                  last_issued_date = {$timestamp_func}
                              WHERE id = {$stock_id}";
        } else {
            $reduction_sql = "UPDATE stock_locales 
                              SET quantity_on_hand = GREATEST(0, quantity_on_hand - {$quantity}),
                                  quantity_issued = quantity_issued + {$quantity},
                                  last_issued_date = {$timestamp_func}
                              WHERE id = {$stock_id}";
        }
        if (!$connection->query($reduction_sql)) {
            error_log("Error reducing stock_locales: " . $connection->error);
        }
        
        // Update total_on_hand in parts_master (sum of all stock_locales for this part)
        $update_total_sql = "UPDATE parts_master 
                            SET total_on_hand = (
                                SELECT COALESCE(SUM(quantity_on_hand), 0) 
                                FROM stock_locales 
                                WHERE part_id = {$part_id}
                            ),
                            total_issued = total_issued + {$quantity},
                            last_issued_date = {$timestamp_func}
                            WHERE id = {$part_id}";
        $connection->query($update_total_sql);
        
        // Also sync parts_master with equipment_spares if this was the last spare of this part
        $other_spares = $connection->query("SELECT COALESCE(SUM(quantity), 0) as total FROM equipment_spares WHERE part_id = {$part_id} AND id != {$spare_id}");
        if ($other_spares) {
            $row = $other_spares->fetch_assoc();
            $other_qty = intval($row['total']);
            $spare_qty = intval($spare['quantity']);
            
            // Ensure parts_master reflects sum of all spares + other stock
            $connection->query("UPDATE parts_master 
                               SET total_on_hand = ({$new_spare_qty} + {$other_qty})
                               WHERE id = {$part_id}");
        }
        
        // Create inventory transaction record
        $connection->query("
            INSERT INTO inventory_transactions 
            (transaction_type, part_id, warehouse_location_id, quantity_change, 
             reference_type, reference_id, transaction_date, user_id, reason, notes) 
            VALUES (
                'issue',
                {$part_id},
                1,
                " . (-$quantity) . ",
                'work_order',
                {$wo_id},
                {$timestamp_func},
                " . ($user_id ? $user_id : 0) . ",
                '" . $connection->real_escape_string($reason) . "',
                'Spare part issued for work order'
            )
        ");
    }
    
    return true;
}


/**
 * Auto-detect and reduce spares based on work order content
 * Enhanced with smarter matching algorithms
 */
function auto_reduce_spares($wo, $connection) {
    $text_to_analyze = strtolower($wo['description'] . ' ' . $wo['descriptive_text'] . ' ' . ($wo['action'] ?? ''));
    $tenant_id = (int)($_SESSION['tenant_id'] ?? 1);

    // Get equipment ID
    $equip_id = $wo['equipment'];
    if (!is_numeric($equip_id)) {
        // Try to get equipment ID from description
        $equip_result = $connection->query("SELECT id FROM equipment WHERE description = '" . $connection->real_escape_string($equip_id) . "' AND tenant_id = {$tenant_id} LIMIT 1");
        if ($equip_result && $equip_result->num_rows > 0) {
            $equip_id = $equip_result->fetch_assoc()['id'];
        } else {
            return; // Can't determine equipment ID
        }
    }

    // Get available spares for this equipment (filtered by tenant_id)
    $spares_result = $connection->query("SELECT id, part_name, part_number FROM equipment_spares WHERE equipment_id = " . intval($equip_id) . " AND tenant_id = {$tenant_id}");

    if ($spares_result) {
        while ($spare = $spares_result->fetch_assoc()) {
            $spare_name = strtolower($spare['part_name']);
            $part_number = strtolower($spare['part_number']);

            // Enhanced matching logic
            $detected = false;

            // 1. Exact match (original logic)
            if (strpos($text_to_analyze, $spare_name) !== false || strpos($text_to_analyze, $part_number) !== false) {
                $detected = true;
            }

            // 2. Partial word matching (e.g., "bearing" matches "roller bearing")
            if (!$detected) {
                $spare_words = explode(' ', $spare_name);
                foreach ($spare_words as $word) {
                    if (strlen($word) > 3 && strpos($text_to_analyze, $word) !== false) {
                        $detected = true;
                        break;
                    }
                }
            }

            // 3. Part number partial matching
            if (!$detected && !empty($part_number)) {
                // Check if part number appears anywhere in text
                if (strpos($text_to_analyze, $part_number) !== false) {
                    $detected = true;
                }
                // Check for partial part numbers (last few characters)
                elseif (strlen($part_number) > 3) {
                    $partial = substr($part_number, -4);
                    if (strpos($text_to_analyze, $partial) !== false) {
                        $detected = true;
                    }
                }
            }

            // 4. Synonym matching
            if (!$detected) {
                $synonyms = [
                    'bearing' => ['roller bearing', 'ball bearing', 'thrust bearing', 'needle bearing'],
                    'seal' => ['oil seal', 'shaft seal', 'mechanical seal'],
                    'pump' => ['centrifugal pump', 'gear pump', 'piston pump'],
                    'motor' => ['electric motor', 'ac motor', 'dc motor'],
                    'valve' => ['solenoid valve', 'ball valve', 'gate valve'],
                    'filter' => ['oil filter', 'air filter', 'fuel filter'],
                    'belt' => ['drive belt', 'timing belt', 'v-belt'],
                    'chain' => ['drive chain', 'roller chain', 'timing chain'],
                    'gear' => ['spur gear', 'helical gear', 'worm gear'],
                    'shaft' => ['drive shaft', 'output shaft', 'input shaft'],
                    'bush' => ['bushing', 'bronze bush', 'sleeve bearing']
                ];

                foreach ($synonyms as $common_term => $specific_terms) {
                    if (strpos($text_to_analyze, $common_term) !== false) {
                        // Check if this spare matches any of the specific terms
                        foreach ($specific_terms as $specific) {
                            if (strpos($spare_name, $specific) !== false || strpos($specific, $spare_name) !== false) {
                                $detected = true;
                                break 2;
                            }
                        }
                    }
                }
            }

            if ($detected) {
                // Check if this spare was already manually selected (with tenant_id filter)
                $already_used = false;
                $check_query = "SELECT COUNT(*) as count FROM work_order_spares WHERE wo_id = " . intval($wo['wo_id']) . " AND spare_id = " . intval($spare['id']) . " AND tenant_id = {$tenant_id}";
                $check_result = $connection->query($check_query);
                if ($check_result && $check_result->fetch_assoc()['count'] > 0) {
                    $already_used = true;
                }

                if (!$already_used) {
                    // Use integrated inventory system to reduce inventory
                    reduce_spare_inventory($spare['id'], 1, $wo['wo_id'], $_SESSION['user_id'] ?? 0, 'Auto-detected from WO#' . $wo['wo_id'], $connection);

                    // Record spare usage in work_order_spares (with tenant_id)
                    $connection->query("INSERT INTO work_order_spares (wo_id, spare_id, quantity_used, tenant_id) VALUES (" . intval($wo['wo_id']) . ", " . intval($spare['id']) . ", 1, {$tenant_id})");
                }
            }
        }
    }
    
    // Also check for common spare keywords
    $keyword_mappings = [
        'bolt' => 'Bolt',
        'bearing' => 'Bearing', 
        'shaft' => 'Shaft',
        'gear' => 'Gear',
        'seal' => 'Seal',
        'bush' => 'Bush',
        'chain' => 'Chain',
        'cam' => 'Cam',
        'filter' => 'Filter',
        'pump' => 'Pump',
        'motor' => 'Motor',
        'valve' => 'Valve',
        'hose' => 'Hose',
        'belt' => 'Belt'
    ];
    
    foreach ($keyword_mappings as $keyword => $generic_name) {
        if (strpos($text_to_analyze, $keyword) !== false) {
            // Try to find a matching spare in equipment_spares (with tenant_id filter)
            $spare_query = "SELECT id FROM equipment_spares 
                           WHERE equipment_id = " . intval($equip_id) . " 
                           AND tenant_id = {$tenant_id}
                           AND LOWER(part_name) LIKE '%" . $connection->real_escape_string($keyword) . "%'";
            $spare_result = $connection->query($spare_query);
            
            if ($spare_result && $spare_result->num_rows > 0) {
                $spare_id = $spare_result->fetch_assoc()['id'];
                
                // Check if already used (with tenant_id filter)
                $check_query = "SELECT COUNT(*) as count FROM work_order_spares WHERE wo_id = " . intval($wo['wo_id']) . " AND spare_id = $spare_id AND tenant_id = {$tenant_id}";
                $check_result = $connection->query($check_query);
                $already_used = ($check_result && $check_result->fetch_assoc()['count'] > 0);
                
                if (!$already_used) {
                    // Use integrated inventory system to reduce inventory
                    reduce_spare_inventory($spare_id, 1, $wo['wo_id'], $_SESSION['user_id'] ?? 0, 'Auto-detected keyword from WO#' . $wo['wo_id'], $connection);
                }
            }
        }
    }
}

/**
 * Get spare cost from parts_master
 */
function get_spare_cost($spare_id, $connection) {
    $sql = "SELECT pm.unit_cost 
            FROM equipment_spares es
            LEFT JOIN parts_master pm ON es.part_id = pm.id
            WHERE es.id = {$spare_id}
            LIMIT 1";
    $result = $connection->query($sql);
    
    if ($result && $result->num_rows > 0) {
        $row = $result->fetch_assoc();
        return floatval($row['unit_cost'] ?? 0);
    }
    return 0;
}

/**
 * Get total spare cost used in a work order
 */
function get_work_order_spare_cost($wo_id, $connection) {
    $sql = "SELECT SUM(wos.quantity_used * COALESCE(pm.unit_cost, 0)) as total_cost
            FROM work_order_spares wos
            JOIN equipment_spares es ON wos.spare_id = es.id
            LEFT JOIN parts_master pm ON es.part_id = pm.id
            WHERE wos.wo_id = {$wo_id}";
    $result = $connection->query($sql);
    
    if ($result) {
        $row = $result->fetch_assoc();
        return floatval($row['total_cost'] ?? 0);
    }
    return 0;
}

/**
 * Get detailed spare usage with costs for reporting
 */
function get_spare_usage_details($wo_id, $connection) {
    $sql = "SELECT 
            wos.quantity_used,
            es.part_name,
            es.part_number,
            pm.unit_cost,
            (wos.quantity_used * COALESCE(pm.unit_cost, 0)) as total_cost
            FROM work_order_spares wos
            JOIN equipment_spares es ON wos.spare_id = es.id
            LEFT JOIN parts_master pm ON es.part_id = pm.id
            WHERE wos.wo_id = {$wo_id}";
    
    $result = $connection->query($sql);
    $details = [];
    
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $details[] = $row;
        }
    }
    
    return $details;
}

/**
 * Update parts_master totals from inventory transactions
 */
function sync_parts_master_totals($part_id, $connection) {
    // Get total issued
    $issued_sql = "SELECT SUM(ABS(quantity_change)) as total 
                   FROM inventory_transactions 
                   WHERE part_id = {$part_id} AND transaction_type = 'issue'";
    $issued_result = $connection->query($issued_sql);
    $issued_qty = 0;
    if ($issued_result) {
        $row = $issued_result->fetch_assoc();
        $issued_qty = intval($row['total'] ?? 0);
    }
    
    // Get total on hand from stock_locales
    $on_hand_sql = "SELECT SUM(quantity_on_hand) as total 
                    FROM stock_locales 
                    WHERE part_id = {$part_id}";
    $on_hand_result = $connection->query($on_hand_sql);
    $on_hand_qty = 0;
    if ($on_hand_result) {
        $row = $on_hand_result->fetch_assoc();
        $on_hand_qty = intval($row['total'] ?? 0);
    }
    
    // Update parts_master
    $connection->query("UPDATE parts_master 
                       SET total_issued = {$issued_qty},
                           total_on_hand = {$on_hand_qty},
                           updated_at = NOW()
                       WHERE id = {$part_id}");
}

?>
