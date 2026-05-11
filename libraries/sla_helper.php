<?php
/**
 * SLA Tracking Helper Functions
 * Manages Service Level Agreement calculations for work orders
 */

/**
 * Update acknowledged_at timestamp when WO is first assigned/acknowledged
 * 
 * @param mysqli $connection Database connection
 * @param int $wo_id Work Order ID
 * @return bool Success status
 */
function set_acknowledged_timestamp($connection, $wo_id) {
    // Only set if not already set
    $check = $connection->query("SELECT acknowledged_at FROM work_orders WHERE wo_id = $wo_id");
    if ($check && $row = $check->fetch_assoc()) {
        if (empty($row['acknowledged_at'])) {
            $result = $connection->query("UPDATE work_orders SET acknowledged_at = NOW() WHERE wo_id = $wo_id");
            return $result ? true : false;
        }
    }
    return true;
}

/**
 * Update completed_at and calculate SLA status when WO is completed
 * 
 * @param mysqli $connection Database connection
 * @param int $wo_id Work Order ID
 * @return bool Success status
 */
function set_completed_timestamp($connection, $wo_id) {
    // Get the work order details
    $result = $connection->query("SELECT created_at, acknowledged_at, sla_response_limit, sla_completion_limit FROM work_orders WHERE wo_id = $wo_id");
    
    if (!$result || !($row = $result->fetch_assoc())) {
        return false;
    }
    
    $created_at = $row['created_at'];
    $acknowledged_at = $row['acknowledged_at'] ?: date('Y-m-d H:i:s');
    $sla_response_limit = (int)$row['sla_response_limit'];
    $sla_completion_limit = (int)$row['sla_completion_limit'];
    
    // Update completed_at
    $connection->query("UPDATE work_orders SET completed_at = NOW() WHERE wo_id = $wo_id");
    
    // Calculate SLA status
    $sla_status = calculate_sla_status($connection, $wo_id, $created_at, $acknowledged_at, $sla_response_limit, $sla_completion_limit);
    
    // Update SLA status
    $sla_status_escaped = $connection->real_escape_string($sla_status);
    $connection->query("UPDATE work_orders SET sla_status = '$sla_status_escaped' WHERE wo_id = $wo_id");
    
    return true;
}

/**
 * Calculate SLA status based on response and completion times
 * 
 * @param mysqli $connection Database connection
 * @param int $wo_id Work Order ID
 * @param string $created_at Creation timestamp
 * @param string $acknowledged_at Acknowledgment timestamp
 * @param int $response_limit Response SLA in hours
 * @param int $completion_limit Completion SLA in hours
 * @return string 'On Time' or 'Breached'
 */
function calculate_sla_status($connection, $wo_id, $created_at, $acknowledged_at, $response_limit, $completion_limit) {
    // Get current WO details
    $result = $connection->query("SELECT created_at, acknowledged_at, completed_at, wo_status FROM work_orders WHERE wo_id = $wo_id");
    
    if (!$result) {
        return 'On Time';
    }
    
    $wo = $result->fetch_assoc();
    $created = new DateTime($wo['created_at']);
    
    // Check Response SLA (if acknowledged)
    if (!empty($wo['acknowledged_at'])) {
        $acknowledged = new DateTime($wo['acknowledged_at']);
        $response_diff = $created->diff($acknowledged);
        $response_hours = ($response_diff->days * 24) + $response_diff->h + ($response_diff->i > 0 ? 1 : 0);
        
        if ($response_hours > $response_limit) {
            return 'Breached';
        }
    }
    
    // Check Completion SLA (if completed)
    if (!empty($wo['completed_at'])) {
        $completed = new DateTime($wo['completed_at']);
        $completion_diff = $created->diff($completed);
        $completion_hours = ($completion_diff->days * 24) + $completion_diff->h + ($completion_diff->i > 0 ? 1 : 0);
        
        if ($completion_hours > $completion_limit) {
            return 'Breached';
        }
    }
    
    return 'On Time';
}

/**
 * Get SLA metrics for dashboard/reporting
 * 
 * @param mysqli $connection Database connection
 * @return array Array with sla_breached_count and sla_ontime_count
 */
function get_sla_metrics($connection) {
    $result = $connection->query("SELECT sla_status, COUNT(*) as cnt FROM work_orders WHERE wo_status = 'Completed' GROUP BY sla_status");
    
    $metrics = [
        'sla_breached' => 0,
        'sla_ontime' => 0,
        'sla_percentage' => 0
    ];
    
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            if ($row['sla_status'] === 'Breached') {
                $metrics['sla_breached'] = (int)$row['cnt'];
            } elseif ($row['sla_status'] === 'On Time') {
                $metrics['sla_ontime'] = (int)$row['cnt'];
            }
        }
    }
    
    $total = $metrics['sla_breached'] + $metrics['sla_ontime'];
    $metrics['sla_percentage'] = $total > 0 ? round(($metrics['sla_ontime'] / $total) * 100, 2) : 0;
    
    return $metrics;
}

/**
 * Get SLA response time in hours for a completed WO
 * 
 * @param mysqli $connection Database connection
 * @param int $wo_id Work Order ID
 * @return float Response time in hours (decimal)
 */
function get_sla_response_time($connection, $wo_id) {
    $result = $connection->query("SELECT created_at, acknowledged_at FROM work_orders WHERE wo_id = $wo_id");
    
    if (!$result || !($row = $result->fetch_assoc())) {
        return 0;
    }
    
    if (empty($row['acknowledged_at'])) {
        return 0;
    }
    
    $created = new DateTime($row['created_at']);
    $acknowledged = new DateTime($row['acknowledged_at']);
    $diff = $created->diff($acknowledged);
    
    // Convert to hours with decimals
    $hours = ($diff->days * 24) + $diff->h + ($diff->i / 60);
    return round($hours, 2);
}

/**
 * Get SLA completion time in hours for a completed WO
 * 
 * @param mysqli $connection Database connection
 * @param int $wo_id Work Order ID
 * @return float Completion time in hours (decimal)
 */
function get_sla_completion_time($connection, $wo_id) {
    $result = $connection->query("SELECT created_at, completed_at FROM work_orders WHERE wo_id = $wo_id");
    
    if (!$result || !($row = $result->fetch_assoc())) {
        return 0;
    }
    
    if (empty($row['completed_at'])) {
        return 0;
    }
    
    $created = new DateTime($row['created_at']);
    $completed = new DateTime($row['completed_at']);
    $diff = $created->diff($completed);
    
    // Convert to hours with decimals
    $hours = ($diff->days * 24) + $diff->h + ($diff->i / 60);
    return round($hours, 2);
}

/**
 * Recalculate all SLA statuses (useful for bulk updates)
 * 
 * @param mysqli $connection Database connection
 * @return int Number of records updated
 */
function recalculate_all_sla_statuses($connection) {
    $result = $connection->query("SELECT wo_id, created_at, acknowledged_at, completed_at, sla_response_limit, sla_completion_limit FROM work_orders WHERE completed_at IS NOT NULL");
    
    if (!$result) {
        return 0;
    }
    
    $updated = 0;
    while ($wo = $result->fetch_assoc()) {
        $sla_status = calculate_sla_status(
            $connection,
            $wo['wo_id'],
            $wo['created_at'],
            $wo['acknowledged_at'],
            (int)$wo['sla_response_limit'],
            (int)$wo['sla_completion_limit']
        );
        
        $sla_status_escaped = $connection->real_escape_string($sla_status);
        $update_result = $connection->query("UPDATE work_orders SET sla_status = '$sla_status_escaped' WHERE wo_id = {$wo['wo_id']}");
        
        if ($update_result) {
            $updated++;
        }
    }
    
    return $updated;
}
?>
