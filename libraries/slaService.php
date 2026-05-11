<?php
/**
 * SLA Service - Core SLA calculation and tracking
 * 
 * Handles:
 * - SLA policy retrieval
 * - Response time SLA evaluation
 * - Completion time SLA evaluation
 * - Overdue tracking
 * - SLA record updates
 */

/**
 * Get SLA policy for given priority and tenant
 * 
 * @param int $tenant_id
 * @param string $priority Priority level (Critical, High, Medium, Low)
 * @return array|null SLA policy details
 */
function get_sla_policy($priority = 'High') {
    global $connection;
    $tenant_id = $_SESSION['tenant_id'] ?? 1;
    
    try {
        $stmt = $connection->prepare("
            SELECT * FROM sla_policies
            WHERE tenant_id = ? 
            AND priority_level = ?
            AND is_active = 1
            LIMIT 1
        ");
        $stmt->execute([$tenant_id, $priority]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        error_log("Error fetching SLA policy: " . $e->getMessage());
        return null;
    }
}

/**
 * Create or update SLA tracking record for a work order
 * Called when work order is assigned
 * 
 * @param int $work_order_id
 * @param int $technician_id
 * @param string $priority Priority level
 * @return bool Success status
 */
function create_work_order_sla($work_order_id, $technician_id, $priority = 'High') {
    global $connection;
    $tenant_id = $_SESSION['tenant_id'] ?? 1;
    
    try {
        // Get SLA policy
        $sla_policy = get_sla_policy($priority);
        if (!$sla_policy) {
            error_log("No SLA policy found for priority: $priority");
            return false;
        }
        
        // Calculate due dates
        $assigned_at = date('Y-m-d H:i:s');
        $response_due = date('Y-m-d H:i:s', strtotime($assigned_at . ' +' . $sla_policy['response_time_minutes'] . ' minutes'));
        $completion_due = date('Y-m-d H:i:s', strtotime($assigned_at . ' +' . $sla_policy['resolution_time_minutes'] . ' minutes'));
        
        // Check if SLA record already exists
        $check = $connection->prepare("
            SELECT id FROM work_order_sla
            WHERE tenant_id = ? AND work_order_id = ?
        ");
        $check->execute([$tenant_id, $work_order_id]);
        $existing = $check->fetch(PDO::FETCH_ASSOC);
        
        if ($existing) {
            // Update existing SLA record
            $stmt = $connection->prepare("
                UPDATE work_order_sla
                SET sla_policy_id = ?, 
                    assigned_at = ?,
                    assigned_to_technician_id = ?,
                    updated_at = CURRENT_TIMESTAMP
                WHERE id = ? AND tenant_id = ?
            ");
            $stmt->execute([
                $sla_policy['id'],
                $assigned_at,
                $technician_id,
                $existing['id'],
                $tenant_id
            ]);
        } else {
            // Insert new SLA record
            $stmt = $connection->prepare("
                INSERT INTO work_order_sla 
                (tenant_id, work_order_id, sla_policy_id, assigned_at, assigned_to_technician_id)
                VALUES (?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $tenant_id,
                $work_order_id,
                $sla_policy['id'],
                $assigned_at,
                $technician_id
            ]);
        }
        
        return true;
    } catch (Exception $e) {
        error_log("Error creating work order SLA: " . $e->getMessage());
        return false;
    }
}

/**
 * Update SLA tracking when technician acknowledges task
 * 
 * @param int $work_order_id
 * @return bool Success status
 */
function acknowledge_work_order_sla($work_order_id) {
    global $connection;
    $tenant_id = $_SESSION['tenant_id'] ?? 1;
    
    try {
        $acknowledged_at = date('Y-m-d H:i:s');
        
        // Get SLA record
        $stmt = $connection->prepare("
            SELECT wos.*, sp.response_time_minutes
            FROM work_order_sla wos
            JOIN sla_policies sp ON wos.sla_policy_id = sp.id
            WHERE wos.tenant_id = ? AND wos.work_order_id = ?
        ");
        $stmt->execute([$tenant_id, $work_order_id]);
        $sla_record = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$sla_record) {
            return false;
        }
        
        // Calculate response time and check if SLA met
        $assigned_time = strtotime($sla_record['assigned_at']);
        $acknowledged_time = strtotime($acknowledged_at);
        $response_minutes = intval(($acknowledged_time - $assigned_time) / 60);
        $sla_met = ($response_minutes <= $sla_record['response_time_minutes']) ? 1 : 0;
        
        // Update SLA record
        $stmt = $connection->prepare("
            UPDATE work_order_sla
            SET acknowledged_at = ?,
                response_time_minutes = ?,
                response_sla_met = ?,
                response_delay_minutes = ?,
                updated_at = CURRENT_TIMESTAMP
            WHERE tenant_id = ? AND work_order_id = ?
        ");
        
        $delay = max(0, $response_minutes - $sla_record['response_time_minutes']);
        $stmt->execute([
            $acknowledged_at,
            $response_minutes,
            $sla_met,
            ($sla_met ? 0 : $delay),
            $tenant_id,
            $work_order_id
        ]);
        
        return true;
    } catch (Exception $e) {
        error_log("Error acknowledging work order SLA: " . $e->getMessage());
        return false;
    }
}

/**
 * Update SLA tracking when work order is started
 * 
 * @param int $work_order_id
 * @return bool Success status
 */
function start_work_order_sla($work_order_id) {
    global $connection;
    $tenant_id = $_SESSION['tenant_id'] ?? 1;
    
    try {
        $started_at = date('Y-m-d H:i:s');
        
        $stmt = $connection->prepare("
            UPDATE work_order_sla
            SET started_at = ?,
                updated_at = CURRENT_TIMESTAMP
            WHERE tenant_id = ? AND work_order_id = ?
        ");
        $stmt->execute([$started_at, $tenant_id, $work_order_id]);
        
        return true;
    } catch (Exception $e) {
        error_log("Error starting work order SLA: " . $e->getMessage());
        return false;
    }
}

/**
 * Update SLA tracking when work order is completed
 * Calculates completion time and checks if resolution SLA met
 * 
 * @param int $work_order_id
 * @return bool Success status
 */
function complete_work_order_sla($work_order_id) {
    global $connection;
    $tenant_id = $_SESSION['tenant_id'] ?? 1;
    
    try {
        $completed_at = date('Y-m-d H:i:s');
        
        // Get SLA record with policy details
        $stmt = $connection->prepare("
            SELECT wos.*, sp.resolution_time_minutes
            FROM work_order_sla wos
            JOIN sla_policies sp ON wos.sla_policy_id = sp.id
            WHERE wos.tenant_id = ? AND wos.work_order_id = ?
        ");
        $stmt->execute([$tenant_id, $work_order_id]);
        $sla_record = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$sla_record) {
            return false;
        }
        
        // Calculate completion time and check if SLA met
        $assigned_time = strtotime($sla_record['assigned_at']);
        $completed_time = strtotime($completed_at);
        $completion_minutes = intval(($completed_time - $assigned_time) / 60);
        $sla_met = ($completion_minutes <= $sla_record['resolution_time_minutes']) ? 1 : 0;
        
        // Update SLA record
        $stmt = $connection->prepare("
            UPDATE work_order_sla
            SET completed_at = ?,
                completion_time_minutes = ?,
                completion_sla_met = ?,
                completion_delay_minutes = ?,
                is_overdue = ?,
                updated_at = CURRENT_TIMESTAMP
            WHERE tenant_id = ? AND work_order_id = ?
        ");
        
        $delay = max(0, $completion_minutes - $sla_record['resolution_time_minutes']);
        $is_overdue = ($sla_met) ? 0 : 1;
        
        $stmt->execute([
            $completed_at,
            $completion_minutes,
            $sla_met,
            ($sla_met ? 0 : $delay),
            $is_overdue,
            $tenant_id,
            $work_order_id
        ]);
        
        return true;
    } catch (Exception $e) {
        error_log("Error completing work order SLA: " . $e->getMessage());
        return false;
    }
}

/**
 * Get SLA summary for a work order
 * 
 * @param int $work_order_id
 * @return array|null SLA details
 */
function get_work_order_sla_summary($work_order_id) {
    global $connection;
    $tenant_id = $_SESSION['tenant_id'] ?? 1;
    
    try {
        $stmt = $connection->prepare("
            SELECT 
                wos.*,
                sp.priority_level,
                sp.response_time_minutes as sla_response_minutes,
                sp.resolution_time_minutes as sla_resolution_minutes,
                u.username as technician_name
            FROM work_order_sla wos
            LEFT JOIN sla_policies sp ON wos.sla_policy_id = sp.id
            LEFT JOIN users u ON wos.assigned_to_technician_id = u.user_id
            WHERE wos.tenant_id = ? AND wos.work_order_id = ?
        ");
        $stmt->execute([$tenant_id, $work_order_id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        error_log("Error fetching work order SLA summary: " . $e->getMessage());
        return null;
    }
}

/**
 * Check for overdue work orders
 * 
 * @param int $technician_id Optional - filter by technician
 * @return array List of overdue work orders
 */
function get_overdue_work_orders($technician_id = null) {
    global $connection;
    $tenant_id = $_SESSION['tenant_id'] ?? 1;
    
    try {
        $sql = "
            SELECT 
                wos.*,
                sp.priority_level,
                wo.descriptive_text,
                wo.description,
                u.username as technician_name
            FROM work_order_sla wos
            JOIN sla_policies sp ON wos.sla_policy_id = sp.id
            JOIN work_orders wo ON wos.work_order_id = wo.wo_id
            LEFT JOIN users u ON wos.assigned_to_technician_id = u.user_id
            WHERE wos.tenant_id = ?
            AND wos.is_overdue = 1
            AND wos.completed_at IS NULL
        ";
        
        $params = [$tenant_id];
        
        if ($technician_id) {
            $sql .= " AND wos.assigned_to_technician_id = ?";
            $params[] = $technician_id;
        }
        
        $sql .= " ORDER BY wos.completion_delay_minutes DESC";
        
        $stmt = $connection->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        error_log("Error fetching overdue work orders: " . $e->getMessage());
        return [];
    }
}
?>
