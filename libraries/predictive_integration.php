<?php
/**
 * Predictive Maintenance Integration Library
 * 
 * Integrates predictive maintenance across the entire CMMS application:
 * - Equipment lifecycle tracking
 * - Work order impact analysis
 * - Automatic maintenance scheduling
 * - Work order request prioritization
 * - Dashboard metrics aggregation
 */

/**
 * Helper function to get a single value from query
 */
function query_single_value($sql_query, $params = []) {
    global $connection, $db_type;
    
    try {
        if ($db_type === 'sqlite' && !empty($params)) {
            // Use prepared statement for parameterized queries
            $stmt = $connection->prepare($sql_query);
            for ($i = 0; $i < count($params); $i++) {
                $stmt->bindParam($i + 1, $params[$i]);
            }
            $stmt->execute();
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
        } else {
            // Direct query for simple SQL
            $result = $connection->query($sql_query)->fetch(PDO::FETCH_ASSOC);
        }
        
        if ($result && is_array($result)) {
            return reset($result);  // Get first value from row
        }
        return null;
    } catch (Exception $e) {
        error_log("query_single_value error: " . $e->getMessage());
        return null;
    }
}

/**
 * Synchronize all equipment from equipment table to asset_lifecycle
 * Ensures every equipment in the system has a predictive lifecycle record
 */
function sync_all_equipment_to_asset_lifecycle() {
    global $connection, $db_type;
    
    try {
        $tenant_id = $_SESSION['tenant_id'] ?? 1;
        $synced_count = 0;
        
        // Get all equipment for this tenant that don't have a lifecycle record yet
        $stmt = $connection->prepare("
            SELECT id, description
            FROM equipment 
            WHERE tenant_id = ? 
            AND id NOT IN (
                SELECT equipment_id FROM asset_lifecycle WHERE tenant_id = ?
            )
        ");
        $stmt->execute([$tenant_id, $tenant_id]);
        $equipment_list = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // For each equipment without a lifecycle record, create one
        foreach ($equipment_list as $eq) {
            try {
                // Insert new asset_lifecycle record with sensible defaults
                $insert_stmt = $connection->prepare("
                    INSERT INTO asset_lifecycle 
                    (equipment_id, asset_category, expected_lifecycle_hours, 
                     expected_lifecycle_cycles, expected_lifecycle_days,
                     criticality, installation_date, last_service_date, 
                     current_runtime_hours, current_cycles, usage_unit, tenant_id, created_at, updated_at)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)
                ");
                
                // Use sensible defaults for lifecycle expectations
                // These can be customized per equipment type
                $expected_hours = 40000;      // 40,000 hours = ~5 years at 8 hrs/day
                $expected_cycles = 50000;     // 50,000 cycles
                $expected_days = 1825;        // 5 years
                
                $insert_stmt->execute([
                    $eq['id'],                    // equipment_id
                    'Equipment',                  // asset_category
                    $expected_hours,              // expected_lifecycle_hours
                    $expected_cycles,             // expected_lifecycle_cycles
                    $expected_days,               // expected_lifecycle_days
                    'Medium',                     // default criticality
                    date('Y-m-d'),                // installation_date (today)
                    date('Y-m-d'),                // last_service_date (today)
                    0,                            // current_runtime_hours (0)
                    0,                            // current_cycles (0)
                    'hours',                      // usage_unit
                    $tenant_id                    // tenant_id
                ]);
                
                $synced_count++;
                error_log("✅ Synced equipment ID {$eq['id']}: {$eq['description']} to asset_lifecycle");
                
            } catch (Exception $e) {
                error_log("⚠️ Failed to sync equipment ID {$eq['id']}: " . $e->getMessage());
                continue;
            }
        }
        
        return [
            'success' => true,
            'synced_count' => $synced_count,
            'message' => "Successfully synced $synced_count equipment to predictive system"
        ];
        
    } catch (Exception $e) {
        error_log("Error in sync_all_equipment_to_asset_lifecycle: " . $e->getMessage());
        return [
            'success' => false,
            'synced_count' => 0,
            'message' => "Sync failed: " . $e->getMessage()
        ];
    }
}

/**
 * Initialize a single equipment in the asset_lifecycle system
 * Called when new equipment is added
 */
function init_equipment_lifecycle($equipment_id) {
    global $connection;
    
    try {
        $tenant_id = $_SESSION['tenant_id'] ?? 1;
        
        // Check if already initialized
        $existing = $connection->query("
            SELECT id FROM asset_lifecycle 
            WHERE equipment_id = ? AND tenant_id = ?
        ")->fetch();
        
        if ($existing) {
            return true;  // Already initialized
        }
        
        // Insert new lifecycle record
        $stmt = $connection->prepare("
            INSERT INTO asset_lifecycle 
            (equipment_id, asset_category, expected_lifecycle_hours, 
             expected_lifecycle_cycles, expected_lifecycle_days,
             criticality, installation_date, last_service_date, 
             current_runtime_hours, current_cycles, usage_unit, tenant_id, created_at, updated_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)
        ");
        
        $stmt->execute([
            $equipment_id,                    // equipment_id
            'Equipment',                      // asset_category
            40000,                            // expected_lifecycle_hours (40,000 hours)
            50000,                            // expected_lifecycle_cycles (50,000 cycles)
            1825,                             // expected_lifecycle_days (5 years)
            'Medium',                         // default criticality
            date('Y-m-d'),                    // installation_date
            date('Y-m-d'),                    // last_service_date
            0,                                // current_runtime_hours
            0,                                // current_cycles
            'hours',                          // usage_unit
            $tenant_id                        // tenant_id
        ]);
        
        return true;
        
    } catch (Exception $e) {
        error_log("Error initializing equipment lifecycle: " . $e->getMessage());
        return false;
    }
}

/**
 * Update equipment lifecycle when a work order is completed
 * Calculates hours/cycles used and updates predictive status
 */
function update_equipment_from_workorder($workorder_id) {
    global $connection, $db_type;
    
    try {
        // Get work order details
        $wo = query_single_row("SELECT 
            wo_id, equipment, wo_status, work_open_date, complete_date, 
            hours_worked, cost, wo_notes, failure_code, repair_description
            FROM work_orders 
            WHERE wo_id = ?", [$workorder_id]);
        
        if (!$wo) return false;
        
        // Skip if not completed
        if ($wo['wo_status'] !== 'Completed' && $wo['wo_status'] !== 'Closed') {
            return false;
        }
        
        // Get equipment ID
        $equipment_id = query_single_value("SELECT equipment_id FROM equipment WHERE equipment = ?", [$wo['equipment']]);
        if (!$equipment_id) return false;
        
        // Calculate actual hours worked
        $hours = floatval($wo['hours_worked'] ?? 0);
        if ($hours <= 0 && $wo['work_open_date'] && $wo['complete_date']) {
            $open = new DateTime($wo['work_open_date']);
            $close = new DateTime($wo['complete_date']);
            $hours = $close->diff($open)->h + ($close->diff($open)->days * 24);
        }
        
        // Update asset lifecycle with usage data
        $query = "UPDATE asset_lifecycle 
                  SET current_runtime_hours = current_runtime_hours + ?,
                      current_cycles = current_cycles + 1,
                      last_service_date = CURRENT_DATE,
                      updated_at = CURRENT_TIMESTAMP
                  WHERE equipment_id = ? AND tenant_id = ?";
        
        $connection->prepare($query)->execute([$hours, $equipment_id, $_SESSION['tenant_id'] ?? 1]);
        
        // Record condition monitoring data (if failure was detected)
        if ($wo['failure_code']) {
            $connection->prepare("
                INSERT INTO condition_monitoring 
                (equipment_id, parameter_type, measured_value, unit, status, recorded_at, notes, tenant_id)
                VALUES (?, ?, 1, 'failure', ?, CURRENT_TIMESTAMP, ?, ?)
            ")->execute([$equipment_id, $wo['failure_code'], 'Critical', $wo['repair_description'], $_SESSION['tenant_id'] ?? 1]);
        }
        
        // Check for alerts after update
        check_asset_for_alerts($equipment_id);
        
        return true;
        
    } catch (Exception $e) {
        error_log("Error updating equipment lifecycle: " . $e->getMessage());
        return false;
    }
}

/**
 * Check a single asset for predictive alerts
 */
function check_asset_for_alerts($equipment_id) {
    global $connection;
    
    try {
        $lifecycle = query_single_row("
            SELECT * FROM asset_lifecycle 
            WHERE equipment_id = ? AND tenant_id = ?",
            [$equipment_id, $_SESSION['tenant_id'] ?? 1]
        );
        
        if (!$lifecycle) return;
        
        // Calculate usage percentages
        $lifecycle_pct = calculate_usage_percentage($lifecycle);
        $health = get_health_status($lifecycle_pct);
        
        // Check if alert already exists
        $existing = query_single_row("
            SELECT * FROM predictive_alerts 
            WHERE equipment_id = ? AND alert_status = 'Active' 
            AND severity IN ('Critical', 'Warning')
            AND tenant_id = ?",
            [$equipment_id, $_SESSION['tenant_id'] ?? 1]
        );
        
        // Create alert if in warning or critical status
        if (in_array($health, ['Warning', 'Critical'])) {
            if (!$existing) {
                $severity = ($health === 'Critical') ? 'Critical' : 'Warning';
                $title = $severity === 'Critical' 
                    ? "Equipment reaching end of lifecycle"
                    : "Equipment maintenance approaching";
                
                create_predictive_alert(
                    $equipment_id,
                    $title,
                    $severity,
                    $lifecycle_pct
                );
            }
        } else if ($existing) {
            // Resolve alert if status improved
            $connection->prepare("
                UPDATE predictive_alerts 
                SET alert_status = 'Resolved', resolved_date = CURRENT_TIMESTAMP
                WHERE id = ? AND tenant_id = ?"
            )->execute([$existing['id'], $_SESSION['tenant_id'] ?? 1]);
        }
        
    } catch (Exception $e) {
        error_log("Error checking asset alerts: " . $e->getMessage());
    }
}

/**
 * Get equipment health status with predictive data
 */
function get_equipment_health_status($equipment_id) {
    global $connection;
    
    try {
        $lifecycle = query_single_row("
            SELECT * FROM asset_lifecycle 
            WHERE equipment_id = ? AND tenant_id = ?",
            [$equipment_id, $_SESSION['tenant_id'] ?? 1]
        );
        
        if (!$lifecycle) {
            return [
                'status' => 'Unknown',
                'health_percentage' => 50,
                'remaining_lifecycle_percentage' => 50,
                'urgent_action_needed' => false,
                'maintenance_recommendations' => []
            ];
        }
        
        $health_pct = calculate_usage_percentage($lifecycle);
        $health_status = get_health_status($health_pct);
        
        // Get recent failures
        $failures = query_to_array("
            SELECT COUNT(*) as count FROM work_orders 
            WHERE equipment_id = (SELECT equipment_id FROM equipment WHERE equipment_id = ?)
            AND wo_status IN ('Completed', 'Closed')
            AND failure_code IS NOT NULL
            AND complete_date >= date('now', '-30 days')
            AND tenant_id = ?
        ", [$equipment_id, $_SESSION['tenant_id'] ?? 1]);
        
        $recent_failures = isset($failures[0]) ? $failures[0]['count'] : 0;
        
        // Calculate MTBF for this equipment
        $mtbf_days = calculate_equipment_mtbf($equipment_id);
        
        // Get active alerts
        $alerts = query_to_array("
            SELECT * FROM predictive_alerts 
            WHERE equipment_id = ? AND alert_status = 'Active'
            AND tenant_id = ?
            ORDER BY severity DESC",
            [$equipment_id, $_SESSION['tenant_id'] ?? 1]
        );
        
        // Generate recommendations
        $recommendations = [];
        if ($health_pct > 80) {
            $recommendations[] = "Schedule preventive maintenance - equipment approaching lifecycle limit";
        }
        if ($recent_failures > 2) {
            $recommendations[] = "High failure rate detected - consider equipment replacement or major overhaul";
        }
        if ($health_status === 'Critical') {
            $recommendations[] = "⚠️ URGENT: Equipment in critical condition - schedule immediate inspection";
        }
        
        return [
            'status' => $health_status,
            'health_percentage' => intval($health_pct),
            'remaining_lifecycle_percentage' => 100 - intval($health_pct),
            'mtbf_days' => round($mtbf_days, 1),
            'recent_failures_30days' => $recent_failures,
            'urgent_action_needed' => in_array($health_status, ['Critical', 'Warning']),
            'active_alerts' => count($alerts),
            'maintenance_recommendations' => $recommendations,
            'last_service_date' => $lifecycle['last_service_date'],
            'lifecycle_data' => $lifecycle
        ];
        
    } catch (Exception $e) {
        error_log("Error getting equipment health: " . $e->getMessage());
        return null;
    }
}

/**
 * Calculate MTBF (Mean Time Between Failures) for specific equipment
 */
function calculate_equipment_mtbf($equipment_id) {
    global $connection, $db_type;
    
    try {
        // Get all completed work orders for this equipment in last 365 days
        if ($db_type === 'sqlite') {
            $result = $connection->query("
                SELECT 
                    COUNT(*) as failure_count,
                    (julianday(MAX(complete_date)) - julianday(MIN(work_open_date))) as days_span
                FROM work_orders 
                WHERE equipment_id = (SELECT equipment_id FROM equipment WHERE equipment_id = ?)
                AND wo_status IN ('Completed', 'Closed')
                AND work_open_date >= date('now', '-365 days')
                AND tenant_id = ?
            ")->fetch(PDO::FETCH_ASSOC);
        } else {
            $result = $connection->query("
                SELECT 
                    COUNT(*) as failure_count,
                    DATEDIFF(MAX(complete_date), MIN(work_open_date)) as days_span
                FROM work_orders 
                WHERE equipment_id = ?
                AND wo_status IN ('Completed', 'Closed')
                AND work_open_date >= DATE_SUB(NOW(), INTERVAL 365 DAY)
                AND tenant_id = ?
            ")->fetch(PDO::FETCH_ASSOC);
        }
        
        if ($result['failure_count'] === 0) {
            return 999; // No failures = high MTBF
        }
        
        $mtbf = $result['days_span'] / $result['failure_count'];
        return max(1, $mtbf); // Minimum 1 day
        
    } catch (Exception $e) {
        error_log("Error calculating MTBF: " . $e->getMessage());
        return 0;
    }
}

/**
 * Get recommendations for work order priority based on predictive data
 */
function get_workorder_priority_recommendation($equipment_id, $failure_code = null) {
    $health = get_equipment_health_status($equipment_id);
    
    if (!$health) {
        return 'Normal';
    }
    
    // If equipment is in critical condition, make it high priority
    if ($health['urgent_action_needed'] || $health['status'] === 'Critical') {
        return 'High';
    }
    
    // If recent failures, increase priority
    if ($health['recent_failures_30days'] > 2) {
        return 'High';
    }
    
    // If equipment is in warning state
    if ($health['status'] === 'Warning') {
        return 'Medium';
    }
    
    return 'Normal';
}

/**
 * Get recommended maintenance schedule for equipment
 * Based on usage patterns and historical failures
 */
function get_equipment_maintenance_schedule($equipment_id) {
    global $connection;
    
    try {
        $lifecycle = query_single_row("
            SELECT * FROM asset_lifecycle 
            WHERE equipment_id = ? AND tenant_id = ?",
            [$equipment_id, $_SESSION['tenant_id'] ?? 1]
        );
        
        if (!$lifecycle) return [];
        
        $schedule = [];
        
        // Based on runtime hours
        if ($lifecycle['expected_lifecycle_hours'] > 0) {
            $hours_per_week = $lifecycle['current_runtime_hours'] > 0 
                ? ($lifecycle['current_runtime_hours'] / (date('o') - date_create($lifecycle['installation_date'])->format('o') * 52))
                : 0;
            
            if ($hours_per_week > 0) {
                $weeks_remaining = ($lifecycle['expected_lifecycle_hours'] - $lifecycle['current_runtime_hours']) / $hours_per_week;
                $schedule[] = [
                    'type' => 'Usage-Based (Hours)',
                    'remaining_weeks' => max(0, ceil($weeks_remaining)),
                    'remaining_hours' => $lifecycle['expected_lifecycle_hours'] - $lifecycle['current_runtime_hours'],
                    'urgency' => $weeks_remaining < 4 ? 'Urgent' : ($weeks_remaining < 12 ? 'Soon' : 'Scheduled')
                ];
            }
        }
        
        // Based on cycles
        if ($lifecycle['expected_lifecycle_cycles'] > 0) {
            $cycles_per_week = $lifecycle['current_cycles'] > 0 
                ? ($lifecycle['current_cycles'] / (date('o') - date_create($lifecycle['installation_date'])->format('o') * 52))
                : 0;
            
            if ($cycles_per_week > 0) {
                $weeks_remaining = ($lifecycle['expected_lifecycle_cycles'] - $lifecycle['current_cycles']) / $cycles_per_week;
                $schedule[] = [
                    'type' => 'Usage-Based (Cycles)',
                    'remaining_weeks' => max(0, ceil($weeks_remaining)),
                    'remaining_cycles' => $lifecycle['expected_lifecycle_cycles'] - $lifecycle['current_cycles'],
                    'urgency' => $weeks_remaining < 4 ? 'Urgent' : ($weeks_remaining < 12 ? 'Soon' : 'Scheduled')
                ];
            }
        }
        
        // Time-based maintenance
        if ($lifecycle['expected_lifecycle_days'] > 0) {
            $installation = new DateTime($lifecycle['installation_date']);
            $now = new DateTime();
            $days_elapsed = $now->diff($installation)->days;
            $days_remaining = $lifecycle['expected_lifecycle_days'] - $days_elapsed;
            
            $schedule[] = [
                'type' => 'Time-Based',
                'remaining_days' => max(0, $days_remaining),
                'urgency' => $days_remaining < 30 ? 'Urgent' : ($days_remaining < 90 ? 'Soon' : 'Scheduled')
            ];
        }
        
        return $schedule;
        
    } catch (Exception $e) {
        error_log("Error getting maintenance schedule: " . $e->getMessage());
        return [];
    }
}

/**
 * Create automatic maintenance tasks based on lifecycle milestones
 */
function create_lifecycle_maintenance_task($equipment_id, $trigger_event = 'usage_threshold') {
    global $connection;
    
    try {
        $equipment = query_single_row("
            SELECT * FROM equipment WHERE equipment_id = ? AND tenant_id = ?",
            [$equipment_id, $_SESSION['tenant_id'] ?? 1]
        );
        
        if (!$equipment) return false;
        
        $schedule_entry = [
            'equipment_id' => $equipment_id,
            'task_name' => "Lifecycle-based Maintenance - {$equipment['equipment']}",
            'task_description' => "Automatic preventive maintenance triggered by asset lifecycle threshold",
            'maintenance_type' => 'Predictive',
            'trigger_type' => $trigger_event,
            'trigger_value' => 80,
            'trigger_unit' => 'percent',
            'next_due_date' => date('Y-m-d', strtotime('+7 days')),
            'priority' => 'High',
            'status' => 'Active',
            'tenant_id' => $_SESSION['tenant_id'] ?? 1
        ];
        
        $connection->prepare("
            INSERT INTO maintenance_schedule 
            (equipment_id, task_name, task_description, maintenance_type, trigger_type, 
             trigger_value, trigger_unit, next_due_date, priority, status, tenant_id)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ")->execute(array_values($schedule_entry));
        
        return true;
        
    } catch (Exception $e) {
        error_log("Error creating lifecycle maintenance task: " . $e->getMessage());
        return false;
    }
}

/**
 * Get health indicator HTML for display in UI
 */
function equipment_health_badge($equipment_id) {
    $health = get_equipment_health_status($equipment_id);
    
    if (!$health) {
        return '<span class="badge bg-secondary">No Data</span>';
    }
    
    $status = $health['status'];
    $pct = $health['health_percentage'];
    
    $badge_class = 'bg-success'; // Healthy
    if ($status === 'Warning') {
        $badge_class = 'bg-warning';
    } elseif ($status === 'Critical') {
        $badge_class = 'bg-danger';
    } elseif ($status === 'Caution') {
        $badge_class = 'bg-info';
    }
    
    return "<span class='badge $badge_class'>$status ($pct%)</span>";
}

/**
 * Get dashboard metrics for equipment overview
 */
function get_equipment_dashboard_metrics() {
    global $connection;
    
    try {
        $tenant_id = $_SESSION['tenant_id'] ?? 1;
        
        // Get total equipment
        $total = query_single_value("SELECT COUNT(*) FROM equipment WHERE tenant_id = ?", [$tenant_id]) ?? 0;
        
        // Get critical health - simplified for SQLite compatibility
        $critical = 0;
        try {
            $critical_data = query_to_array("
                SELECT equipment_id FROM asset_lifecycle 
                WHERE tenant_id = ? 
                AND expected_lifecycle_hours > 0 
                AND current_runtime_hours > 0
            ", [$tenant_id]);
            
            foreach ($critical_data as $row) {
                $usage_pct = ($row['current_runtime_hours'] / $row['expected_lifecycle_hours']) * 100;
                if ($usage_pct > 90) {
                    $critical++;
                }
            }
        } catch (Exception $e) {
            error_log("Error calculating critical health: " . $e->getMessage());
            $critical = 0;
        }
        
        // Get due for maintenance - check existence first
        $due = 0;
        try {
            // Check if maintenance_schedule table exists and has relevant columns
            $schema_check = query_to_array("PRAGMA table_info(maintenance_schedule)", []);
            if (!empty($schema_check)) {
                $due = query_single_value("
                    SELECT COUNT(*) FROM maintenance_schedule 
                    WHERE tenant_id = ?
                ", [$tenant_id]) ?? 0;
            }
        } catch (Exception $e) {
            error_log("Maintenance schedule check: " . $e->getMessage());
            $due = 0;
        }
        
        // Get active alerts
        $alerts = 0;
        try {
            $alerts = query_single_value("
                SELECT COUNT(*) FROM predictive_alerts 
                WHERE tenant_id = ?
            ", [$tenant_id]) ?? 0;
        } catch (Exception $e) {
            error_log("Alerts check: " . $e->getMessage());
            $alerts = 0;
        }
        
        $metrics = [
            'total_equipment' => intval($total),
            'critical_health' => intval($critical),
            'due_for_maintenance' => intval($due),
            'active_predictive_alerts' => intval($alerts)
        ];
        
        return $metrics;
        
    } catch (Exception $e) {
        error_log("Error getting equipment dashboard metrics: " . $e->getMessage());
        return ['total_equipment' => 0, 'critical_health' => 0, 'due_for_maintenance' => 0, 'active_predictive_alerts' => 0];
    }
}

?>
