<?php
/**
 * Audit Logger Class
 * Handles automatic logging of system activities and security events
 * Logs to security_audit_log and compliance_audit_log tables
 */

class AuditLogger {
    private $connection;
    private $db_type;
    
    // Severity levels
    const SEVERITY_INFO = 'info';
    const SEVERITY_WARNING = 'warning';
    const SEVERITY_ERROR = 'error';
    const SEVERITY_CRITICAL = 'critical';
    
    public function __construct($connection, $db_type = 'sqlite') {
        $this->connection = $connection;
        $this->db_type = $db_type;
    }
    
    /**
     * Log a security event
     * @param string $event_type The type of event (login, logout, permission_change, etc.)
     * @param int $user_id The user ID
     * @param string $username The username
     * @param string $details Event details (JSON or text)
     * @param string $severity Event severity level
     * @return bool True if logged successfully
     */
    public function logSecurityEvent($event_type, $user_id = null, $username = 'system', $details = '', $severity = self::SEVERITY_INFO) {
        try {
            $ip_address = $this->getClientIP();
            $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown';
            
            // Convert details to JSON if it's an array
            if (is_array($details)) {
                $details = json_encode($details);
            }
            
            $sql = "INSERT INTO security_audit_log (event_type, user_id, username, ip_address, user_agent, details, severity) 
                    VALUES (?, ?, ?, ?, ?, ?, ?)";
            
            if ($this->db_type === 'sqlite') {
                $stmt = $this->connection->prepare($sql);
                $stmt->bindParam(1, $event_type, PDO::PARAM_STR);
                $stmt->bindParam(2, $user_id, PDO::PARAM_INT);
                $stmt->bindParam(3, $username, PDO::PARAM_STR);
                $stmt->bindParam(4, $ip_address, PDO::PARAM_STR);
                $stmt->bindParam(5, $user_agent, PDO::PARAM_STR);
                $stmt->bindParam(6, $details, PDO::PARAM_STR);
                $stmt->bindParam(7, $severity, PDO::PARAM_STR);
            } else {
                $stmt = $this->connection->prepare($sql);
                $stmt->bind_param('sisssss', $event_type, $user_id, $username, $ip_address, $user_agent, $details, $severity);
            }
            
            $result = $stmt->execute();
            if ($this->db_type === 'sqlite') {
                $stmt = null;
            } else {
                $stmt->close();
            }
            
            return $result;
        } catch (Exception $e) {
            error_log("[AuditLogger] Error logging security event: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Log a compliance event (data changes, access control changes, etc.)
     * @param string $action The action performed (create, update, delete, etc.)
     * @param int $user_id The user ID
     * @param string $resource_type Type of resource (user, work_order, equipment, etc.)
     * @param string $resource_id The ID of the resource
     * @param array $old_values Previous values (for updates)
     * @param array $new_values New values (for updates/creates)
     * @param string $compliance_type Compliance type (SOX, GDPR, HIPAA, etc.)
     * @return bool True if logged successfully
     */
    public function logComplianceEvent($action, $user_id, $resource_type, $resource_id, $old_values = [], $new_values = [], $compliance_type = 'general') {
        try {
            $ip_address = $this->getClientIP();
            $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown';
            $session_id = session_id();
            
            $sql = "INSERT INTO compliance_audit_log (user_id, action, resource_type, resource_id, old_values, new_values, ip_address, user_agent, session_id, compliance_type) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
            
            $old_values_json = json_encode($old_values);
            $new_values_json = json_encode($new_values);
            
            if ($this->db_type === 'sqlite') {
                $stmt = $this->connection->prepare($sql);
                $stmt->bindParam(1, $user_id, PDO::PARAM_INT);
                $stmt->bindParam(2, $action, PDO::PARAM_STR);
                $stmt->bindParam(3, $resource_type, PDO::PARAM_STR);
                $stmt->bindParam(4, $resource_id, PDO::PARAM_STR);
                $stmt->bindParam(5, $old_values_json, PDO::PARAM_STR);
                $stmt->bindParam(6, $new_values_json, PDO::PARAM_STR);
                $stmt->bindParam(7, $ip_address, PDO::PARAM_STR);
                $stmt->bindParam(8, $user_agent, PDO::PARAM_STR);
                $stmt->bindParam(9, $session_id, PDO::PARAM_STR);
                $stmt->bindParam(10, $compliance_type, PDO::PARAM_STR);
            } else {
                $stmt = $this->connection->prepare($sql);
                $stmt->bind_param('isssssssss', $user_id, $action, $resource_type, $resource_id, $old_values_json, $new_values_json, $ip_address, $user_agent, $session_id, $compliance_type);
            }
            
            $result = $stmt->execute();
            if ($this->db_type === 'sqlite') {
                $stmt = null;
            } else {
                $stmt->close();
            }
            
            return $result;
        } catch (Exception $e) {
            error_log("[AuditLogger] Error logging compliance event: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Log a user login attempt (successful or failed)
     * @param string $username Username or email
     * @param bool $success Whether login was successful
     * @param int $user_id User ID (if successful)
     * @param string $reason Reason for failure (if unsuccessful)
     * @return bool
     */
    public function logLoginAttempt($username, $success = false, $user_id = null, $reason = '') {
        $event_type = $success ? 'login_success' : 'login_failed';
        $severity = $success ? self::SEVERITY_INFO : self::SEVERITY_WARNING;
        $details = $reason ? "Reason: $reason" : '';
        
        return $this->logSecurityEvent(
            $event_type,
            $user_id,
            $username,
            $details,
            $severity
        );
    }
    
    /**
     * Log a user logout
     * @param int $user_id User ID
     * @param string $username Username
     * @return bool
     */
    public function logLogout($user_id, $username = 'unknown') {
        return $this->logSecurityEvent('logout', $user_id, $username, 'User session ended');
    }
    
    /**
     * Log work order creation
     * @param int $user_id User ID
     * @param int $work_order_id Work order ID
     * @param array $order_data Work order data
     * @return bool
     */
    public function logWorkOrderCreated($user_id, $work_order_id, $order_data = []) {
        return $this->logComplianceEvent(
            'create',
            $user_id,
            'work_order',
            (string)$work_order_id,
            [],
            $order_data,
            'operational'
        );
    }
    
    /**
     * Log work order update
     * @param int $user_id User ID
     * @param int $work_order_id Work order ID
     * @param array $old_data Previous data
     * @param array $new_data New data
     * @return bool
     */
    public function logWorkOrderUpdated($user_id, $work_order_id, $old_data = [], $new_data = []) {
        return $this->logComplianceEvent(
            'update',
            $user_id,
            'work_order',
            (string)$work_order_id,
            $old_data,
            $new_data,
            'operational'
        );
    }
    
    /**
     * Log user creation
     * @param int $admin_user_id Admin user ID
     * @param int $new_user_id New user ID
     * @param array $user_data User data
     * @return bool
     */
    public function logUserCreated($admin_user_id, $new_user_id, $user_data = []) {
        return $this->logComplianceEvent(
            'create',
            $admin_user_id,
            'user',
            (string)$new_user_id,
            [],
            $user_data,
            'SOX'
        );
    }
    
    /**
     * Log user update
     * @param int $admin_user_id Admin user ID
     * @param int $updated_user_id Updated user ID
     * @param array $old_data Previous data
     * @param array $new_data New data
     * @return bool
     */
    public function logUserUpdated($admin_user_id, $updated_user_id, $old_data = [], $new_data = []) {
        return $this->logComplianceEvent(
            'update',
            $admin_user_id,
            'user',
            (string)$updated_user_id,
            $old_data,
            $new_data,
            'SOX'
        );
    }
    
    /**
     * Log permission change
     * @param int $admin_user_id Admin user ID
     * @param int $target_user_id User whose permissions changed
     * @param string $old_role Previous role
     * @param string $new_role New role
     * @return bool
     */
    public function logPermissionChange($admin_user_id, $target_user_id, $old_role = '', $new_role = '') {
        return $this->logComplianceEvent(
            'permission_change',
            $admin_user_id,
            'user_role',
            (string)$target_user_id,
            ['role' => $old_role],
            ['role' => $new_role],
            'SOX'
        );
    }
    
    /**
     * Log password change
     * @param int $user_id User ID
     * @param int $changed_by User ID who made the change (admin)
     * @return bool
     */
    public function logPasswordChange($user_id, $changed_by = null) {
        $event_type = $changed_by === null ? 'password_change_self' : 'password_change_admin';
        return $this->logSecurityEvent(
            $event_type,
            $changed_by ?? $user_id,
            "User $user_id",
            "Password changed for user ID: $user_id",
            self::SEVERITY_WARNING
        );
    }
    
    /**
     * Log equipment change
     * @param int $user_id User ID
     * @param int $equipment_id Equipment ID
     * @param string $action Action (create/update/delete)
     * @param array $old_data Previous data
     * @param array $new_data New data
     * @return bool
     */
    public function logEquipmentChange($user_id, $equipment_id, $action = 'update', $old_data = [], $new_data = []) {
        return $this->logComplianceEvent(
            $action,
            $user_id,
            'equipment',
            (string)$equipment_id,
            $old_data,
            $new_data,
            'operational'
        );
    }
    
    /**
     * Log inventory transaction
     * @param int $user_id User ID
     * @param int $item_id Item ID
     * @param string $transaction_type Type (in/out/adjustment)
     * @param int $quantity Quantity changed
     * @param array $details Additional details
     * @return bool
     */
    public function logInventoryTransaction($user_id, $item_id, $transaction_type, $quantity, $details = []) {
        $details['transaction_type'] = $transaction_type;
        $details['quantity'] = $quantity;
        
        return $this->logComplianceEvent(
            'transaction',
            $user_id,
            'inventory_item',
            (string)$item_id,
            ['quantity' => 0],
            $details,
            'operational'
        );
    }
    
    /**
     * Log system event
     * @param string $event_type Type of system event
     * @param string $details Event details
     * @param string $severity Severity level
     * @return bool
     */
    public function logSystemEvent($event_type, $details = '', $severity = self::SEVERITY_INFO) {
        return $this->logSecurityEvent($event_type, null, 'system', $details, $severity);
    }
    
    /**
     * Get client IP address
     * @return string Client IP address
     */
    private function getClientIP() {
        if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
            $ip = $_SERVER['HTTP_CLIENT_IP'];
        } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $ip = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR'])[0];
        } else {
            $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
        }
        return filter_var($ip, FILTER_VALIDATE_IP) ? $ip : '0.0.0.0';
    }
    
    /**
     * Get recent audit logs (security)
     * @param int $limit Number of logs to retrieve
     * @param string $event_type Optional filter by event type
     * @return array Array of audit logs
     */
    public function getSecurityLogs($limit = 50, $event_type = '') {
        try {
            $sql = "SELECT * FROM security_audit_log";
            if ($event_type) {
                $sql .= " WHERE event_type = ?";
            }
            $sql .= " ORDER BY log_id DESC LIMIT ?";
            
            if ($this->db_type === 'sqlite') {
                $stmt = $this->connection->prepare($sql);
                if ($event_type) {
                    $stmt->bindParam(1, $event_type, PDO::PARAM_STR);
                    $stmt->bindParam(2, $limit, PDO::PARAM_INT);
                } else {
                    $stmt->bindParam(1, $limit, PDO::PARAM_INT);
                }
            } else {
                $stmt = $this->connection->prepare($sql);
                if ($event_type) {
                    $stmt->bind_param('si', $event_type, $limit);
                } else {
                    $stmt->bind_param('i', $limit);
                }
            }
            
            $stmt->execute();
            $result = $stmt->fetch(PDO::FETCH_ASSOC) ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];
            
            return $result ?? [];
        } catch (Exception $e) {
            error_log("[AuditLogger] Error retrieving security logs: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Get recent compliance logs
     * @param int $limit Number of logs to retrieve
     * @param string $resource_type Optional filter by resource type
     * @return array Array of compliance logs
     */
    public function getComplianceLogs($limit = 50, $resource_type = '') {
        try {
            $sql = "SELECT * FROM compliance_audit_log";
            if ($resource_type) {
                $sql .= " WHERE resource_type = ?";
            }
            $sql .= " ORDER BY log_id DESC LIMIT ?";
            
            if ($this->db_type === 'sqlite') {
                $stmt = $this->connection->prepare($sql);
                if ($resource_type) {
                    $stmt->bindParam(1, $resource_type, PDO::PARAM_STR);
                    $stmt->bindParam(2, $limit, PDO::PARAM_INT);
                } else {
                    $stmt->bindParam(1, $limit, PDO::PARAM_INT);
                }
            } else {
                $stmt = $this->connection->prepare($sql);
                if ($resource_type) {
                    $stmt->bind_param('si', $resource_type, $limit);
                } else {
                    $stmt->bind_param('i', $limit);
                }
            }
            
            $stmt->execute();
            $result = $stmt->fetch(PDO::FETCH_ASSOC) ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];
            
            return $result ?? [];
        } catch (Exception $e) {
            error_log("[AuditLogger] Error retrieving compliance logs: " . $e->getMessage());
            return [];
        }
    }
}
?>
