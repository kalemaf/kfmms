<?php
/**
 * ERP Sync Audit Logger
 * 
 * Tracks all ERP integration activities for:
 * - Compliance & audit requirements
 * - Troubleshooting failed syncs
 * - Performance monitoring
 * - Security analysis
 * 
 * Usage:
 *   require_once 'integrations/ERPAuditLogger.php';
 *   $audit = new ERPAuditLogger($database_connection);
 *   
 *   $audit->logSync('SAP', 'WorkOrder', 123, 'Started', [
 *       'wo_title' => 'Pump maintenance',
 *       'amount' => 2500
 *   ]);
 */

class ERPAuditLogger {
    private $connection;
    private $log_table = 'erp_sync_audit_log';
    private $error_table = 'erp_sync_errors';
    
    public function __construct($database_connection) {
        $this->connection = $database_connection;
        $this->ensureTablesExist();
    }
    
    /**
     * Create audit log tables if they don't exist
     */
    private function ensureTablesExist() {
        // Main audit log table
        $sql = "CREATE TABLE IF NOT EXISTS {$this->log_table} (
            id INT PRIMARY KEY AUTO_INCREMENT,
            timestamp DATETIME DEFAULT CURRENT_TIMESTAMP,
            erp_system VARCHAR(50) NOT NULL,       -- 'SAP', 'NetSuite', 'Oracle'
            object_type VARCHAR(50) NOT NULL,      -- 'WorkOrder', 'Equipment', 'Inventory', 'GLEntry'
            object_id INT NOT NULL,                -- CMMS ID (wo_id, equipment_id, etc.)
            action VARCHAR(50) NOT NULL,           -- 'Create', 'Update', 'Sync', 'Delete'
            status VARCHAR(20) NOT NULL,           -- 'Started', 'Success', 'Failed', 'Timeout'
            erp_transaction_id VARCHAR(100),       -- SAP document number, NS case ID, etc.
            request_data LONGTEXT,                 -- JSON of what was sent
            response_data LONGTEXT,                -- JSON of what was returned
            duration_ms INT,                       -- How long sync took
            user_id INT,                           -- Who triggered this
            ip_address VARCHAR(45),                -- IP of requester
            error_message TEXT,                    -- Error details if failed
            INDEX idx_timestamp (timestamp),
            INDEX idx_erp_system (erp_system),
            INDEX idx_status (status),
            INDEX idx_object_id (object_id),
            INDEX idx_duration (duration_ms)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";
        
        mysqli_query($this->connection, $sql);
        
        // Error details table
        $sql2 = "CREATE TABLE IF NOT EXISTS {$this->error_table} (
            id INT PRIMARY KEY AUTO_INCREMENT,
            sync_log_id INT NOT NULL,
            error_code VARCHAR(50),                -- 'HTTP_400', 'AUTH_FAILED', 'TIMEOUT'
            error_message TEXT,
            error_details LONGTEXT,                -- Full stack trace
            retry_count INT DEFAULT 0,
            retried_at DATETIME,
            resolved_at DATETIME,
            resolution_notes TEXT,
            FOREIGN KEY (sync_log_id) REFERENCES {$this->log_table}(id),
            INDEX idx_unresolved (resolved_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";
        
        mysqli_query($this->connection, $sql2);
    }
    
    /**
     * Log a sync operation (Start → Success/Failed)
     * 
     * @param string $erp_system SAP, NetSuite, Oracle, etc.
     * @param string $object_type WorkOrder, Equipment, GLEntry, etc.
     * @param int $object_id CMMS ID (wo_id, equipment_id, etc.)
     * @param string $action Create, Update, Delete, Sync
     * @param string $status Started, Success, Failed, Timeout
     * @param array $options ['request_data', 'response_data', 'duration_ms', 'erp_transaction_id', 'error_message']
     * 
     * @return int Log ID for later reference
     */
    public function logSync($erp_system, $object_type, $object_id, $action, $status, $options = []) {
        $timestamp = date('Y-m-d H:i:s');
        $request_data = json_encode($options['request_data'] ?? []);
        $response_data = json_encode($options['response_data'] ?? []);
        $duration_ms = $options['duration_ms'] ?? 0;
        $erp_transaction_id = $options['erp_transaction_id'] ?? null;
        $user_id = $options['user_id'] ?? null;
        $ip_address = $options['ip_address'] ?? $this->getClientIP();
        $error_message = $options['error_message'] ?? null;
        
        // Escape strings to prevent SQL injection
        $erp_system = mysqli_real_escape_string($this->connection, $erp_system);
        $object_type = mysqli_real_escape_string($this->connection, $object_type);
        $action = mysqli_real_escape_string($this->connection, $action);
        $status = mysqli_real_escape_string($this->connection, $status);
        $erp_transaction_id = $erp_transaction_id ? "'" . mysqli_real_escape_string($this->connection, $erp_transaction_id) . "'" : "NULL";
        $ip_address = mysqli_real_escape_string($this->connection, $ip_address);
        $error_message = $error_message ? "'" . mysqli_real_escape_string($this->connection, $error_message) . "'" : "NULL";
        
        $sql = "INSERT INTO {$this->log_table} 
                (timestamp, erp_system, object_type, object_id, action, status, 
                 erp_transaction_id, request_data, response_data, duration_ms, 
                 user_id, ip_address, error_message)
                VALUES 
                ('$timestamp', '$erp_system', '$object_type', $object_id, '$action', '$status',
                 $erp_transaction_id, '$request_data', '$response_data', $duration_ms,
                 " . ($user_id ? $user_id : "NULL") . ", '$ip_address', $error_message)";
        
        if (!mysqli_query($this->connection, $sql)) {
            error_log("Audit log insertion failed: " . mysqli_error($this->connection));
            return false;
        }
        
        $log_id = mysqli_insert_id($this->connection);
        
        // If synced successfully, alert on success
        if ($status === 'Success') {
            error_log("[AUDIT] ERP Sync successful: $erp_system $object_type #$object_id (Log ID: $log_id)");
        }
        
        // If failed, alert on failure
        if ($status === 'Failed' || $status === 'Timeout') {
            error_log("[AUDIT] ERP Sync FAILED: $erp_system $object_type #$object_id - $error_message (Log ID: $log_id)");
        }
        
        return $log_id;
    }
    
    /**
     * Log an error for a sync operation
     * 
     * @param int $sync_log_id ID from logSync()
     * @param string $error_code HTTP_400, AUTH_FAILED, TIMEOUT, etc.
     * @param string $error_message User-friendly error message
     * @param string $error_details Full stack trace or response
     */
    public function logError($sync_log_id, $error_code, $error_message, $error_details = '') {
        $error_code = mysqli_real_escape_string($this->connection, $error_code);
        $error_message = mysqli_real_escape_string($this->connection, $error_message);
        $error_details = mysqli_real_escape_string($this->connection, $error_details);
        
        $sql = "INSERT INTO {$this->error_table}
                (sync_log_id, error_code, error_message, error_details)
                VALUES
                ($sync_log_id, '$error_code', '$error_message', '$error_details')";
        
        if (!mysqli_query($this->connection, $sql)) {
            error_log("Error log insertion failed: " . mysqli_error($this->connection));
        }
    }
    
    /**
     * Mark an error as resolved
     */
    public function resolveError($error_id, $resolution_notes = '') {
        $resolution_notes = mysqli_real_escape_string($this->connection, $resolution_notes);
        $resolved_at = date('Y-m-d H:i:s');
        
        $sql = "UPDATE {$this->error_table}
                SET resolved_at = '$resolved_at', resolution_notes = '$resolution_notes'
                WHERE id = $error_id";
        
        mysqli_query($this->connection, $sql);
    }
    
    /**
     * Get sync history for an object
     */
    public function getSyncHistory($object_type, $object_id, $limit = 10) {
        $object_type = mysqli_real_escape_string($this->connection, $object_type);
        
        $sql = "SELECT * FROM {$this->log_table}
                WHERE object_type = '$object_type' AND object_id = $object_id
                ORDER BY timestamp DESC
                LIMIT $limit";
        
        $result = mysqli_query($this->connection, $sql);
        $history = [];
        
        while ($row = mysqli_fetch_assoc($result)) {
            $history[] = $row;
        }
        
        return $history;
    }
    
    /**
     * Get unresolved errors
     */
    public function getUnresolvedErrors($erp_system = null, $limit = 20) {
        $where = "WHERE resolved_at IS NULL";
        
        if ($erp_system) {
            $erp_system = mysqli_real_escape_string($this->connection, $erp_system);
            $where .= " AND ale.sync_log_id IN (
                        SELECT id FROM {$this->log_table}
                        WHERE erp_system = '$erp_system'
                    )";
        }
        
        $sql = "SELECT ale.*, asl.erp_system, asl.object_type, asl.object_id, asl.timestamp
                FROM {$this->error_table} ale
                JOIN {$this->log_table} asl ON ale.sync_log_id = asl.id
                $where
                ORDER BY ale.id DESC
                LIMIT $limit";
        
        $result = mysqli_query($this->connection, $sql);
        $errors = [];
        
        while ($row = mysqli_fetch_assoc($result)) {
            $errors[] = $row;
        }
        
        return $errors;
    }
    
    /**
     * Get sync statistics for reporting
     */
    public function getSyncStats($erp_system = null, $days = 30) {
        $where = "WHERE timestamp >= DATE_SUB(NOW(), INTERVAL $days DAY)";
        
        if ($erp_system) {
            $erp_system = mysqli_real_escape_string($this->connection, $erp_system);
            $where .= " AND erp_system = '$erp_system'";
        }
        
        $sql = "SELECT
                    COUNT(*) as total_syncs,
                    SUM(CASE WHEN status = 'Success' THEN 1 ELSE 0 END) as successful,
                    SUM(CASE WHEN status = 'Failed' THEN 1 ELSE 0 END) as failed,
                    SUM(CASE WHEN status = 'Timeout' THEN 1 ELSE 0 END) as timeouts,
                    ROUND(AVG(duration_ms), 2) as avg_duration_ms,
                    MAX(duration_ms) as max_duration_ms,
                    MIN(duration_ms) as min_duration_ms,
                    COUNT(DISTINCT erp_system) as erp_systems_used
                FROM {$this->log_table}
                $where";
        
        $result = mysqli_query($this->connection, $sql);
        return mysqli_fetch_assoc($result);
    }
    
    /**
     * Get sync stats by ERP system
     */
    public function getSyncStatsBySystem($days = 30) {
        $sql = "SELECT
                    erp_system,
                    COUNT(*) as total_syncs,
                    SUM(CASE WHEN status = 'Success' THEN 1 ELSE 0 END) as successful,
                    SUM(CASE WHEN status = 'Failed' THEN 1 ELSE 0 END) as failed,
                    ROUND(AVG(duration_ms), 2) as avg_duration_ms,
                    COUNT(DISTINCT object_id) as unique_objects
                FROM {$this->log_table}
                WHERE timestamp >= DATE_SUB(NOW(), INTERVAL $days DAY)
                GROUP BY erp_system
                ORDER BY total_syncs DESC";
        
        $result = mysqli_query($this->connection, $sql);
        $stats = [];
        
        while ($row = mysqli_fetch_assoc($result)) {
            $stats[] = $row;
        }
        
        return $stats;
    }
    
    /**
     * Get recent sync activity
     */
    public function getRecentActivity($limit = 50) {
        $sql = "SELECT * FROM {$this->log_table}
                ORDER BY timestamp DESC
                LIMIT $limit";
        
        $result = mysqli_query($this->connection, $sql);
        $activity = [];
        
        while ($row = mysqli_fetch_assoc($result)) {
            $activity[] = $row;
        }
        
        return $activity;
    }
    
    /**
     * Get slowest syncs (performance optimization)
     */
    public function getSlowestSyncs($limit = 20) {
        $sql = "SELECT 
                    erp_system,
                    object_type,
                    object_id,
                    timestamp,
                    duration_ms,
                    status
                FROM {$this->log_table}
                ORDER BY duration_ms DESC
                LIMIT $limit";
        
        $result = mysqli_query($this->connection, $sql);
        $syncs = [];
        
        while ($row = mysqli_fetch_assoc($result)) {
            $syncs[] = $row;
        }
        
        return $syncs;
    }
    
    /**
     * Archive old logs (data cleanup)
     * Run monthly: DELETE logs older than 90 days
     */
    public function archiveOldLogs($days_to_keep = 90) {
        $cutoff_date = date('Y-m-d H:i:s', strtotime("-$days_to_keep days"));
        
        // Delete old errors first (referential integrity)
        $sql1 = "DELETE FROM {$this->error_table}
                 WHERE sync_log_id IN (
                    SELECT id FROM {$this->log_table}
                    WHERE timestamp < '$cutoff_date'
                 )";
        
        // Delete old logs
        $sql2 = "DELETE FROM {$this->log_table}
                 WHERE timestamp < '$cutoff_date'";
        
        mysqli_query($this->connection, $sql1);
        mysqli_query($this->connection, $sql2);
        
        $affected = mysqli_affected_rows($this->connection);
        error_log("[AUDIT] Archived $affected old sync logs (before $cutoff_date)");
        
        return $affected;
    }
    
    /**
     * Get client IP address
     */
    private function getClientIP() {
        if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
            return $_SERVER['HTTP_CLIENT_IP'];
        } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            return $_SERVER['HTTP_X_FORWARDED_FOR'];
        } else {
            return $_SERVER['REMOTE_ADDR'] ?? 'CLI';
        }
    }
}

// ============================================================================
// USAGE EXAMPLES
// ============================================================================

/*

// ===== EXAMPLE 1: Log successful SAP sync =====

require_once 'integrations/ERPAuditLogger.php';
require_once 'integrations/SAPConnector.php';

$audit = new ERPAuditLogger($c);

$start_time = microtime(true);

try {
    $sap = new SAPConnector($c, $sap_config);
    if ($sap->connect()) {
        $result = $sap->syncWorkOrder(123, $wo_data);
        
        $duration_ms = round((microtime(true) - $start_time) * 1000);
        
        if ($result['success']) {
            // Log success
            $audit->logSync(
                'SAP',
                'WorkOrder',
                123,
                'Sync',
                'Success',
                [
                    'request_data' => $wo_data,
                    'response_data' => $result,
                    'duration_ms' => $duration_ms,
                    'erp_transaction_id' => $result['sap_notification_id'],
                    'user_id' => $_SESSION['user_id'] ?? null
                ]
            );
        } else {
            // Log failure
            $log_id = $audit->logSync(
                'SAP',
                'WorkOrder',
                123,
                'Sync',
                'Failed',
                [
                    'duration_ms' => $duration_ms,
                    'error_message' => $result['error'],
                    'user_id' => $_SESSION['user_id'] ?? null
                ]
            );
            
            // Log error details
            $audit->logError($log_id, 'SAP_ERROR', $result['error'], $result['details']);
        }
    }
} catch (Exception $e) {
    $duration_ms = round((microtime(true) - $start_time) * 1000);
    
    $log_id = $audit->logSync(
        'SAP',
        'WorkOrder',
        123,
        'Sync',
        'Failed',
        [
            'duration_ms' => $duration_ms,
            'error_message' => $e->getMessage(),
        ]
    );
    
    $audit->logError($log_id, 'EXCEPTION', $e->getMessage(), $e->getTraceAsString());
}

// ===== EXAMPLE 2: View sync history =====

$history = $audit->getSyncHistory('WorkOrder', 123, 10);

echo "<h3>Work Order #123 Sync History</h3>";
echo "<table>";
foreach ($history as $sync) {
    echo "<tr>";
    echo "<td>" . $sync['timestamp'] . "</td>";
    echo "<td>" . $sync['erp_system'] . "</td>";
    echo "<td>" . $sync['status'] . "</td>";
    echo "<td>" . $sync['duration_ms'] . "ms</td>";
    echo "</tr>";
}
echo "</table>";

// ===== EXAMPLE 3: Get unresolved errors =====

$errors = $audit->getUnresolvedErrors('SAP');

foreach ($errors as $error) {
    echo "Error ID: " . $error['id'] . "\n";
    echo "Code: " . $error['error_code'] . "\n";
    echo "Message: " . $error['error_message'] . "\n";
}

// ===== EXAMPLE 4: Get sync statistics =====

$stats = $audit->getSyncStats('SAP', 30);  // Last 30 days

echo "Total SAP syncs: " . $stats['total_syncs'] . "\n";
echo "Successful: " . $stats['successful'] . "\n";
echo "Failed: " . $stats['failed'] . "\n";
echo "Success rate: " . round(($stats['successful'] / $stats['total_syncs']) * 100, 2) . "%\n";
echo "Average duration: " . $stats['avg_duration_ms'] . "ms\n";

// ===== EXAMPLE 5: Monthly cleanup =====

// Add to cron job:
// 0 0 1 * * php -f /home/cmms/cleanup_audit_logs.php

$audit->archiveOldLogs(90);  // Keep 90 days, delete older

*/

?>
