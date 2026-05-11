<?php
/**
 * Get the next work order number for a specific tenant
 * Supports per-company work order numbering where each company has its own sequence
 *
 * @param PDO $connection Database connection
 * @param int $tenant_id Tenant/Company ID
 * @return int Next work order number for the tenant
 */
function get_next_wo_number($connection, $tenant_id) {
    try {
        // Get the maximum wo_number for this tenant
        $stmt = $connection->prepare("SELECT MAX(wo_number) as max_number FROM work_orders WHERE tenant_id = ?");
        if (!$stmt) {
            return 1; // Fallback if prepare fails
        }
        
        $stmt->execute([$tenant_id]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        $next_number = ($result && $result['max_number']) ? (int)$result['max_number'] + 1 : 1;
        
        return $next_number;
    } catch (Exception $e) {
        // Fallback: return 1 if query fails
        error_log("Error getting next WO number: " . $e->getMessage());
        return 1;
    }
}

/**
 * Get the work order display number (wo_number) from the global wo_id
 * Maps internal wo_id to the user-visible wo_number
 *
 * @param PDO $connection Database connection
 * @param int $wo_id Global work order ID
 * @return string Display number in format "WO #{wo_number}"
 */
function get_wo_display_number($connection, $wo_id) {
    try {
        $stmt = $connection->prepare("SELECT wo_number FROM work_orders WHERE wo_id = ? LIMIT 1");
        if (!$stmt) {
            return "WO #{$wo_id}"; // Fallback to wo_id if query fails
        }
        
        $stmt->execute([$wo_id]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($result && !empty($result['wo_number'])) {
            return "WO #" . (int)$result['wo_number'];
        }
        
        // Fallback if wo_number is missing
        return "WO #{$wo_id}";
    } catch (Exception $e) {
        error_log("Error getting WO display number: " . $e->getMessage());
        return "WO #{$wo_id}";
    }
}

/**
 * Format a work order reference for display
 * Shows the per-company wo_number instead of global wo_id
 *
 * @param array $wo_row Work order database row with wo_id and tenant_id
 * @param PDO|null $connection Optional database connection (uses global if not provided)
 * @return string Formatted WO reference "WO #N"
 */
function format_wo_reference($wo_row, $connection = null) {
    if (!$connection) {
        global $connection;
    }
    
    if (!$wo_row || !isset($wo_row['wo_id'])) {
        return "WO #?";
    }
    
    // Check if connection is valid before attempting query
    if (!$connection || !is_object($connection)) {
        // Fallback if connection unavailable
        return "WO #" . (isset($wo_row['wo_number']) ? (int)$wo_row['wo_number'] : $wo_row['wo_id']);
    }
    
    return get_wo_display_number($connection, $wo_row['wo_id']);
}
