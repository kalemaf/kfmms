<?php
/**
 * Multi-Tenant Authentication System
 * 
 * This file handles:
 * 1. User authentication
 * 2. Tenant context initialization
 * 3. Session management with tenant isolation
 * 4. Login/Logout with proper cleanup
 */

require_once __DIR__ . '/app/Middleware/TenantMiddleware.php';

class AuthenticationManager {
    private $connection;
    private $db_type;
    
    public function __construct($connection, $db_type) {
        $this->connection = $connection;
        $this->db_type = $db_type;
    }
    
    /**
     * Authenticate user and initialize tenant context
     * 
     * @param string $email
     * @param string $password
     * @return array ['success' => bool, 'message' => string, 'user' => array]
     */
    public function authenticate($email, $password) {
        try {
            // Get user with tenant info
            $query = "SELECT u.user_id, u.email, u.password, u.full_name, u.role, 
                             u.tenant_id, c.name as company_name, c.is_locked
                      FROM users u
                      INNER JOIN companies c ON u.tenant_id = c.company_id
                      WHERE u.email = ? AND u.is_active = 1";
            
            if ($this->db_type === 'sqlite') {
                $stmt = $this->connection->prepare($query);
                $stmt->execute([$email]);
                $user = $stmt->fetch(PDO::FETCH_ASSOC);
            } else {
                $stmt = $this->connection->prepare($query);
                $stmt->bind_param('s', $email);
                $stmt->execute();
                $result = $stmt->get_result();
                $user = $result->fetch_assoc();
            }
            
            if (!$user) {
                return [
                    'success' => false,
                    'message' => 'Invalid email or password'
                ];
            }
            
            // Check if company is locked
            if ($user['is_locked'] == 1) {
                return [
                    'success' => false,
                    'message' => 'Company account is locked. Contact support.'
                ];
            }
            
            // Verify password
            if (!password_verify($password, $user['password'])) {
                // Log failed attempt
                $this->logAuthAttempt($user['user_id'], $user['tenant_id'], false);
                
                return [
                    'success' => false,
                    'message' => 'Invalid email or password'
                ];
            }
            
            // Initialize tenant context in session
            TenantMiddleware::initializeTenantContext(
                $user['user_id'],
                $user['tenant_id'],
                $user['role']
            );
            
            // Log successful login
            $this->logAuthAttempt($user['user_id'], $user['tenant_id'], true);
            
            // Update last login
            $this->updateLastLogin($user['user_id'], $user['tenant_id']);
            
            return [
                'success' => true,
                'message' => 'Login successful',
                'user' => [
                    'user_id' => $user['user_id'],
                    'email' => $user['email'],
                    'name' => $user['full_name'],
                    'role' => $user['role'],
                    'tenant_id' => $user['tenant_id'],
                    'company_name' => $user['company_name']
                ]
            ];
            
        } catch (Exception $e) {
            error_log("Authentication error: " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Authentication failed'
            ];
        }
    }
    
    /**
     * Register a new user for a company
     * 
     * @param array $data
     * @return array
     */
    public function registerUser($data) {
        try {
            // Validate input
            if (empty($data['email']) || empty($data['password']) || empty($data['tenant_id'])) {
                return [
                    'success' => false,
                    'message' => 'Missing required fields'
                ];
            }
            
            // Check if user already exists
            $checkQuery = "SELECT user_id FROM users WHERE email = ? AND tenant_id = ?";
            
            if ($this->db_type === 'sqlite') {
                $stmt = $this->connection->prepare($checkQuery);
                $stmt->execute([$data['email'], $data['tenant_id']]);
                $exists = $stmt->fetch(PDO::FETCH_ASSOC);
            } else {
                $stmt = $this->connection->prepare($checkQuery);
                $stmt->bind_param('si', $data['email'], $data['tenant_id']);
                $stmt->execute();
                $result = $stmt->get_result();
                $exists = $result->fetch_assoc();
            }
            
            if ($exists) {
                return [
                    'success' => false,
                    'message' => 'User already exists in this company'
                ];
            }
            
            // Hash password
            $hashed_password = password_hash($data['password'], PASSWORD_BCRYPT);
            
            // Insert new user
            $insertQuery = "INSERT INTO users 
                           (email, password, full_name, role, tenant_id, is_active, created_at)
                           VALUES (?, ?, ?, ?, ?, 1, CURRENT_TIMESTAMP)";
            
            if ($this->db_type === 'sqlite') {
                $stmt = $this->connection->prepare($insertQuery);
                $stmt->execute([
                    $data['email'],
                    $hashed_password,
                    $data['full_name'] ?? $data['email'],
                    $data['role'] ?? 'user',
                    $data['tenant_id']
                ]);
                $new_user_id = $this->connection->lastInsertId();
            } else {
                $role = $data['role'] ?? 'user';
                $full_name = $data['full_name'] ?? $data['email'];
                
                $stmt = $this->connection->prepare($insertQuery);
                $stmt->bind_param('ssssi', 
                    $data['email'],
                    $hashed_password,
                    $full_name,
                    $role,
                    $data['tenant_id']
                );
                $stmt->execute();
                $new_user_id = $this->connection->insert_id;
            }
            
            return [
                'success' => true,
                'message' => 'User registered successfully',
                'user_id' => $new_user_id
            ];
            
        } catch (Exception $e) {
            error_log("Registration error: " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Registration failed'
            ];
        }
    }
    
    /**
     * Log authentication attempts
     */
    private function logAuthAttempt($user_id, $tenant_id, $success) {
        try {
            $action = $success ? 'login_success' : 'login_failed';
            $timestamp = $this->db_type === 'sqlite' ? 'CURRENT_TIMESTAMP' : 'NOW()';
            
            $query = "INSERT INTO audit_logs 
                     (user_id, tenant_id, action, ip_address, created_at)
                     VALUES (?, ?, ?, ?, $timestamp)";
            
            $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
            
            if ($this->db_type === 'sqlite') {
                $stmt = $this->connection->prepare($query);
                $stmt->execute([$user_id, $tenant_id, $action, $ip]);
            } else {
                $stmt = $this->connection->prepare($query);
                $stmt->bind_param('iiss', $user_id, $tenant_id, $action, $ip);
                $stmt->execute();
            }
        } catch (Exception $e) {
            // Silently fail logging
        }
    }
    
    /**
     * Update last login timestamp
     */
    private function updateLastLogin($user_id, $tenant_id) {
        try {
            $query = "UPDATE users SET last_login = CURRENT_TIMESTAMP 
                     WHERE user_id = ? AND tenant_id = ?";
            
            if ($this->db_type === 'sqlite') {
                $stmt = $this->connection->prepare($query);
                $stmt->execute([$user_id, $tenant_id]);
            } else {
                $stmt = $this->connection->prepare($query);
                $stmt->bind_param('ii', $user_id, $tenant_id);
                $stmt->execute();
            }
        } catch (Exception $e) {
            // Silently fail
        }
    }
    
    /**
     * Logout user
     */
    public static function logout() {
        TenantMiddleware::destroyTenantContext();
        return [
            'success' => true,
            'message' => 'Logged out successfully'
        ];
    }
}

// Helper function for quick authentication
function authenticateUser($email, $password) {
    global $connection, $db_type;
    
    $auth = new AuthenticationManager($connection, $db_type);
    return $auth->authenticate($email, $password);
}
?>
