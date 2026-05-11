<?php
/**
 * Common Functions and Utilities for CMMS Application
 * Contains shared functions used across the application
 */

// Include per-company work order numbering helpers
require_once __DIR__ . '/wo_numbering_helpers.inc.php';

// ========== HTTPS & SECURITY ENFORCEMENT ==========
// Enforce HTTPS in production (check if not already redirected by .htaccess)
if (php_sapi_name() !== 'cli' && !empty($_SERVER['HTTP_HOST'])) {
    $is_https = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || 
                !empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https';
    
    $is_localhost = preg_match('/^(localhost|127\.0\.0\.1|::1)(:\d+)?$/', $_SERVER['HTTP_HOST']);

    if (!$is_https && !$is_localhost) {
        $redirect_url = 'https://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];
        header('HTTP/1.1 301 Moved Permanently');
        header('Location: ' . $redirect_url);
        exit('Redirecting to HTTPS...');
    }
}

// ========== SECURE SESSION CONFIGURATION ==========
// Configure secure session cookies before session_start()
if (php_sapi_name() !== 'cli') {
    // Determine if connection is HTTPS
    $is_https = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || 
                !empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https';
    
    // Session cookie configuration for PHP 7.3+
    if (PHP_VERSION_ID >= 70300) {
        session_set_cookie_params([
            'lifetime' => 3600,           // 1 hour session timeout
            'path'     => '/',            // Available across entire site
            'domain'   => $_SERVER['HTTP_HOST'] ?? '',
            'secure'   => (bool)$is_https,     // Only transmit over HTTPS
            'httponly' => true,           // Not accessible via JavaScript
            'samesite' => 'Strict'        // Prevent CSRF attacks
        ]);
    } else {
        // Fallback for older PHP versions
        session_set_cookie_params(3600, '/', $_SERVER['HTTP_HOST'] ?? '', (bool)$is_https, true);
        ini_set('session.cookie_samesite', 'Strict');
    }
    
    // Session security options
    ini_set('session.use_strict_mode', 1);          // Only accept valid session IDs
    ini_set('session.use_only_cookies', 1);         // Don't allow session ID in URL
    ini_set('session.cookie_httponly', 1);          // Prevent JavaScript access
    ini_set('session.cookie_secure', (int)$is_https);  // Only send over HTTPS
    ini_set('session.gc_maxlifetime', 3600);        // 1 hour garbage collection
}

if (session_status() === PHP_SESSION_NONE && php_sapi_name() !== 'cli') {
    if (!empty($session_save_path)) {
        session_save_path($session_save_path);
    }
    @session_start();
}

// Regenerate session ID on login (should be called in auth.php after login)
function secure_session_regenerate() {
    if (session_status() === PHP_SESSION_ACTIVE) {
        session_regenerate_id(true);
    }
}


/**
 * Get the current tenant ID from session
 * Global helper function for tenant isolation
 *
 * @return int
 * @throws Exception if user is not authenticated or tenant context is missing
 */
function tenant_id() {
    if (!isset($_SESSION['tenant_id']) || $_SESSION['tenant_id'] <= 0) {
        throw new Exception('Unauthorized: No valid tenant context');
    }
    return (int)$_SESSION['tenant_id'];
}

/**
 * Get the current user ID from session
 * Global helper function for user identification
 *
 * @return int
 * @throws Exception if user is not authenticated
 */
function user_id() {
    if (!isset($_SESSION['user_id']) || $_SESSION['user_id'] <= 0) {
        throw new Exception('Unauthorized: User not authenticated');
    }
    return (int)$_SESSION['user_id'];
}

/**
 * Get the current user role
 * Global helper function for role checking
 *
 * @return string
 */
function user_role() {
    return $_SESSION['role'] ?? 'guest';
}

/**
 * Check if current user is admin
 * Global helper function for admin checks
 *
 * @return bool
 */
function is_admin() {
    return user_role() === 'admin';
}

/**
 * Check if current user is manager or admin
 * Global helper function for manager checks
 *
 * @return bool
 */
function is_manager() {
    $role = user_role();
    return in_array($role, ['admin', 'manager']);
}

/**
 * Get tenant-specific upload directory path
 * Ensures file isolation between tenants
 *
 * @param string $subfolder Optional subfolder within tenant directory
 * @return string Absolute path to tenant upload directory
 * @throws Exception if tenant context is missing
 */
function get_tenant_upload_path($subfolder = '') {
    $tenant_id = 0;
    try {
        $tenant_id = tenant_id();
    } catch (Exception $e) {
        // Fallback to session or default
        $tenant_id = (int)($_SESSION['tenant_id'] ?? 1);
    }
    if ($tenant_id <= 0) $tenant_id = 1;
    
    $base_path = __DIR__ . '/storage/uploads/tenant_' . $tenant_id;
    
    // Create directory if it doesn't exist
    if (!is_dir($base_path)) {
        mkdir($base_path, 0755, true);
    }
    
    if (!empty($subfolder)) {
        $sub_path = $base_path . '/' . trim($subfolder, '/');
        if (!is_dir($sub_path)) {
            mkdir($sub_path, 0755, true);
        }
        return $sub_path;
    }
    
    return $base_path;
}

/**
 * Get tenant-specific upload URL
 * Returns web-accessible URL for tenant files
 *
 * @param string $subfolder Optional subfolder within tenant directory
 * @return string Web URL to tenant upload directory
 * @throws Exception if tenant context is missing
 */
function get_tenant_upload_url($subfolder = '') {
    $tenant_id = 0;
    try {
        $tenant_id = tenant_id();
    } catch (Exception $e) {
        $tenant_id = (int)($_SESSION['tenant_id'] ?? 1);
    }
    if ($tenant_id <= 0) $tenant_id = 1;
    
    $base_url = '/storage/uploads/tenant_' . $tenant_id;
    
    if (!empty($subfolder)) {
        return $base_url . '/' . trim($subfolder, '/');
    }
    
    return $base_url;
}

/**
 * Generate or return a CSRF token for the current user session.
 *
 * @return string
 */
function generate_csrf_token() {
    if (session_status() !== PHP_SESSION_ACTIVE) {
        @session_start();
    }
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Verify a CSRF token provided by a form submission.
 *
 * @param string $token
 * @return bool
 */
function verify_csrf_token($token) {
    if (session_status() !== PHP_SESSION_ACTIVE) {
        @session_start();
    }
    if (empty($token) || empty($_SESSION['csrf_token'])) {
        return false;
    }
    return hash_equals($_SESSION['csrf_token'], $token);
}

/**
 * Render a hidden CSRF token input for HTML forms.
 *
 * @return string
 */
function csrf_input_tag() {
    return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars(generate_csrf_token(), ENT_QUOTES, 'UTF-8') . '">';
}

/**
 * Safe INSERT wrapper that automatically adds tenant_id
 * Use this instead of direct $connection->query() for INSERT statements
 * 
 * @param string $table Table name
 * @param array $data Column => value pairs
 * @return mixed Query result
 */
function safe_insert($table, $data) {
    global $connection, $db_type, $db_available;
    
    if (!$db_available) {
        return false;
    }
    
    // Add tenant_id for operational tables
    $tenant_tables = [
        'work_orders', 'equipment', 'inventory', 'inventory_transactions', 
        'parts_master', 'purchase_requests', 'purchase_request_items', 
        'purchase_orders', 'purchase_order_items', 'goods_receipts', 
        'goods_receipt_items', 'pm_schedules', 'pm_tasks', 'pm_required_parts', 
        'pm_consumables', 'work_order_spares', 'work_order_consumables', 
        'wo_parts', 'equipment_spares', 'consumables', 'consumable_usage', 
        'vendors', 'part_vendors', 'warehouses', 'warehouse_locations', 
        'stock_locations', 'stock_locales', 'mechanics', 'personnel', 
        'sites_locations', 'work_order_requests', 'hot_jobs', 'vendor_performance', 
        'goods_receipt_notes', 'payment_orders'
    ];
    
    if (in_array($table, $tenant_tables)) {
        $data['tenant_id'] = (int)($_SESSION['tenant_id'] ?? 1);
    }
    
    $columns = array_keys($data);
    $values = array_values($data);
    
    $columnList = '`' . implode('`, `', $columns) . '`';
    $placeholders = str_repeat('?,', count($values) - 1) . '?';
    
    $sql = "INSERT INTO `$table` ($columnList) VALUES ($placeholders)";
    
    if ($db_type === 'sqlite') {
        try {
            $stmt = $connection->prepare($sql);
            if ($stmt) {
                foreach ($values as $i => $value) {
                    $stmt->bindValue($i + 1, $value);
                }
                return $stmt->execute();
            }
            return false;
        } catch (Exception $e) {
            return false;
        }
    } else {
        $stmt = $connection->prepare($sql);
        if ($stmt) {
            $types = '';
            foreach ($values as $value) {
                if (is_int($value)) $types .= 'i';
                elseif (is_float($value)) $types .= 'd';
                else $types .= 's';
            }
            $stmt->bind_param($types, ...$values);
            return $stmt->execute();
        }
        return false;
    }
}

/**
 * Safe query wrapper that applies automatic tenant filtering
 * Use this instead of direct $connection->query() for SELECT queries
 * 
 * @param string $query SQL query
 * @return mixed Query result
 */
function safe_query($query) {
    global $connection, $db_type, $db_available;
    
    if (!$db_available) {
        return false;
    }
    
    // Apply tenant filter to SELECT queries
    $query = apply_tenant_filter($query);
    
    return $connection->query($query);
}

/**
 * Safe query wrapper for single row result
 * Use this instead of direct $connection->query() when expecting one row
 * 
 * @param string $query SQL query
 * @return array|null Row or null
 */
function safe_query_row($query) {
    global $connection, $db_type, $db_available;
    
    if (!$db_available) {
        return null;
    }
    
    // Apply tenant filter
    $query = apply_tenant_filter($query);
    
    if ($db_type === 'sqlite') {
        try {
            $result = $connection->query($query);
            return $result ? $result->fetch(PDO::FETCH_ASSOC) : null;
        } catch (Exception $e) {
            return null;
        }
    } else {
        $result = $connection->query($query);
        return $result ? $result->fetch_assoc() : null;
    }
}

/**
 * Safe query wrapper for multiple rows
 * Use this instead of direct $connection->query() when expecting multiple rows
 * 
 * @param string $query SQL query
 * @return array Rows
 */
function safe_query_all($query) {
    global $connection, $db_type, $db_available;
    
    if (!$db_available) {
        return [];
    }
    
    // Apply tenant filter
    $query = apply_tenant_filter($query);
    
    if ($db_type === 'sqlite') {
        try {
            $result = $connection->query($query);
            if (!$result) {
                return [];
            }
            $data = [];
            while ($row = $result->fetch(PDO::FETCH_ASSOC)) {
                $data[] = $row;
            }
            return $data;
        } catch (Exception $e) {
            return [];
        }
    } else {
        $result = $connection->query($query);
        if (!$result) {
            return [];
        }
        $data = [];
        while ($row = $result->fetch_assoc()) {
            $data[] = $row;
        }
        return $data;
    }
}

/**
 * Prepare and execute a SQL statement with bound parameters.
 * Supports SQLite PDO and MySQLi connections.
 *
 * @param string $query SQL statement with ? placeholders
 * @param array $params Parameter values to bind
 * @return object|false PDOStatement or mysqli_stmt on success, false on failure
 */
function db_prepare_execute($query, $params = []) {
    global $connection, $db_type, $db_available;

    if (!$db_available || !$connection) {
        return false;
    }

    if ($db_type === 'sqlite') {
        try {
            $stmt = $connection->prepare($query);
            if (!$stmt) {
                return false;
            }
            foreach (array_values($params) as $index => $value) {
                $stmt->bindValue($index + 1, $value);
            }
            $stmt->execute();
            return $stmt;
        } catch (Exception $e) {
            return false;
        }
    }

    $stmt = $connection->prepare($query);
    if (!$stmt) {
        return false;
    }

    if (!empty($params)) {
        $types = '';
        $values = array_values($params);
        foreach ($values as $value) {
            if (is_int($value)) {
                $types .= 'i';
            } elseif (is_float($value)) {
                $types .= 'd';
            } else {
                $types .= 's';
            }
        }

        $bindParams = array_merge([$types], $values);
        $refs = [];
        foreach ($bindParams as $key => $value) {
            $refs[$key] = &$bindParams[$key];
        }
        call_user_func_array([$stmt, 'bind_param'], $refs);
    }

    if (!$stmt->execute()) {
        return false;
    }

    return $stmt;
}

/**
 * Execute a SELECT query and return a single row.
 *
 * @param string $query SQL query with ? placeholders
 * @param array $params Parameter values to bind
 * @return array|null Single row or null
 */
function db_query_row_params($query, $params = []) {
    global $db_type;

    $stmt = db_prepare_execute($query, $params);
    if (!$stmt) {
        return null;
    }

    if ($db_type === 'sqlite') {
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    $result = $stmt->get_result();
    return $result ? $result->fetch_assoc() : null;
}

/**
 * Execute a SELECT query and return all rows.
 *
 * @param string $query SQL query with ? placeholders
 * @param array $params Parameter values to bind
 * @return array Rows
 */
function db_query_all_params($query, $params = []) {
    global $db_type;

    $stmt = db_prepare_execute($query, $params);
    if (!$stmt) {
        return [];
    }

    if ($db_type === 'sqlite') {
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    $result = $stmt->get_result();
    if (!$result) {
        return [];
    }

    $data = [];
    while ($row = $result->fetch_assoc()) {
        $data[] = $row;
    }
    return $data;
}

/**
 * Execute a non-select SQL statement with bound parameters.
 *
 * @param string $query SQL statement with ? placeholders
 * @param array $params Parameter values to bind
 * @return bool True on success
 */
function db_execute_params($query, $params = []) {
    $stmt = db_prepare_execute($query, $params);
    return $stmt !== false;
}

/**
 * Get the current tenant ID from session
 *
 * @return int Tenant ID or 0 if not set
 */
function get_current_tenant_id() {
    return (int)($_SESSION['tenant_id'] ?? 0);
}

/**
 * Apply automatic tenant filtering to SQL queries for multi-tenant data isolation
 * This ensures each company only sees its own data
 * 
 * @param string $query SQL query to filter
 * @return string Query with tenant filter applied
 */
function apply_tenant_filter($query) {
    // Get tenant_id from session directly (with fallback to function)
    $tenant_id = 0;
    if (isset($_SESSION['tenant_id']) && $_SESSION['tenant_id'] > 0) {
        $tenant_id = (int)$_SESSION['tenant_id'];
    } else {
        // Try function call as fallback
        try {
            $tenant_id = tenant_id();
        } catch (Exception $e) {
            // No tenant context - allow system queries (admin, setup, etc.)
            return $query;
        }
    }
    
    if ($tenant_id <= 0) {
        return $query; // No valid tenant
    }
    
    // Tables that need tenant filtering
    $tenant_tables = [
        'work_orders' => 'tenant_id',
        'equipment' => 'tenant_id', 
        'inventory' => 'tenant_id',
        'inventory_transactions' => 'tenant_id',
        'parts_master' => 'tenant_id',
        'purchase_requests' => 'tenant_id',
        'purchase_request_items' => 'tenant_id',
        'purchase_orders' => 'tenant_id',
        'purchase_order_items' => 'tenant_id',
        'goods_receipts' => 'tenant_id',
        'goods_receipt_items' => 'tenant_id',
        'pm_schedules' => 'tenant_id',
        'pm_masters' => 'tenant_id',
        'pm_tasks' => 'tenant_id',
        'pm_required_parts' => 'tenant_id',
        'pm_consumables' => 'tenant_id',
        'work_order_spares' => 'tenant_id',
        'work_order_consumables' => 'tenant_id',
        'wo_parts' => 'tenant_id',
        'equipment_spares' => 'tenant_id',
        'consumables' => 'tenant_id',
        'consumable_usage' => 'tenant_id',
        'vendors' => 'tenant_id',
        'part_vendors' => 'tenant_id',
        'warehouses' => 'tenant_id',
        'warehouse_locations' => 'tenant_id',
        'stock_locations' => 'tenant_id',
        'stock_locales' => 'tenant_id',
        'mechanics' => 'tenant_id',
        'personnel' => 'tenant_id',
        'sites_locations' => 'tenant_id',
        'work_order_requests' => 'tenant_id',
        'hot_jobs' => 'tenant_id',
        'vendor_performance' => 'tenant_id',
        'goods_receipt_notes' => 'tenant_id',
        'payment_orders' => 'tenant_id'
    ];
    
    // Check if query is SELECT
    $query_trimmed = ltrim($query);
    if (!preg_match('/^SELECT\s+/i', $query_trimmed)) {
        return $query; // Only filter SELECT queries
    }
    
    // Check each table and add tenant filter if not present
    foreach ($tenant_tables as $table => $column) {
        // More robust pattern - check if table appears in FROM clause
        $pattern = '/\bFROM\s+' . preg_quote($table, '/') . '\b/i';
        if (preg_match($pattern, $query)) {
            // Check if tenant filter already exists (check for table.column format too)
            if (!preg_match('/\b' . $column . '\s*=/i', $query) && !preg_match('/' . $table . '\.' . $column . '\s*=/i', $query)) {
                // Determine the column reference - only use alias if there's a JOIN
                $col_ref = $column;
                if (preg_match('/\bJOIN\s+\w+/i', $query)) {
                    // Has JOINs - extract alias from FROM clause
                    if (preg_match('/\bFROM\s+' . preg_quote($table, '/') . '\s+(\w+)/i', $query, $matches)) {
                        $alias = $matches[1];
                        // Make sure alias is not a SQL keyword
                        if (strtoupper($alias) !== 'WHERE' && strtoupper($alias) !== 'GROUP' && strtoupper($alias) !== 'ORDER' && strtoupper($alias) !== 'LIMIT') {
                            $col_ref = $alias . '.' . $column;
                        }
                    }
                }
                
                // Add WHERE clause
                if (preg_match('/\s+WHERE\s+/i', $query)) {
                    $query = preg_replace(
                        '/(\s+WHERE\s+)/i',
                        '$1' . $col_ref . ' = ' . $tenant_id . ' AND ',
                        $query,
                        1
                    );
                } elseif (preg_match('/\s+GROUP BY\s+/i', $query)) {
                    $query = preg_replace(
                        '/(\s+GROUP BY\s+)/i',
                        ' WHERE ' . $col_ref . ' = ' . $tenant_id . ' GROUP BY ',
                        $query,
                        1
                    );
                } elseif (preg_match('/\s+ORDER BY\s+/i', $query)) {
                    $query = preg_replace(
                        '/(\s+ORDER BY\s+)/i',
                        ' WHERE ' . $col_ref . ' = ' . $tenant_id . ' ORDER BY ',
                        $query,
                        1
                    );
                } elseif (preg_match('/\s+LIMIT\s+/i', $query)) {
                    $query = preg_replace(
                        '/(\s+LIMIT\s+)/i',
                        ' WHERE ' . $column . ' = ' . $tenant_id . ' LIMIT ',
                        $query,
                        1
                    );
                } else {
                    // No WHERE, GROUP BY, ORDER BY, or LIMIT - add WHERE at end
                    $query = $query . ' WHERE ' . $column . ' = ' . $tenant_id;
                }
            }
            break; // Only handle first matching table
        }
    }
    
    return $query;
}

// ============================================================================
// UTILITY FUNCTIONS
// ============================================================================

/**
 * Sanitize user input for database queries
 *
 * @param string $input User input to sanitize
 * @return string Sanitized input
 */
function sanitize_input($input) {
    global $connection, $db_type, $db_available;

    if (!$db_available) {
        return trim($input); // Basic sanitization when no DB
    }

    // For both SQLite (PDO) and MySQL, prepared statements are preferred
    // This function provides basic escaping as a fallback
    $input = trim($input);
    
    if ($db_type === 'sqlite') {
        // SQLite PDO: escape single quotes by doubling them
        return str_replace("'", "''", $input);
    } else {
        return mysqli_real_escape_string($connection, $input);
    }
}

/**
 * Get user-friendly error message
 *
 * @param string $error_code Error code
 * @return string User-friendly error message
 */
function get_error_message($error_code) {
    $messages = [
        'db_connect' => 'Database connection failed. Please try again later.',
        'auth_failed' => 'Invalid username or password.',
        'access_denied' => 'You do not have permission to access this resource.',
        'invalid_input' => 'Invalid input provided.',
        'file_upload' => 'File upload failed.',
        'session_expired' => 'Your session has expired. Please log in again.'
    ];

    return $messages[$error_code] ?? 'An unexpected error occurred.';
}

/**
 * Log application events
 *
 * @param string $message Log message
 * @param string $level Log level (INFO, WARNING, ERROR)
 */
function log_event($message, $level = 'INFO', $log_file = 'app.log') {
    $log_path = resolve_log_file_path($log_file);
    $timestamp = date('Y-m-d H:i:s');
    $log_entry = "[$timestamp] [$level] $message" . PHP_EOL;

    file_put_contents($log_path, $log_entry, FILE_APPEND);
    rotate_logs(parse_int(env('LOG_RETENTION_DAYS', '30'), 30));
}

/**
 * Get the configured log directory.
 *
 * @return string
 */
function get_log_dir() {
    $log_dir = env('LOG_DIR', __DIR__ . '/logs');
    if (!is_dir($log_dir)) {
        mkdir($log_dir, 0755, true);
    }
    return rtrim($log_dir, '/\\');
}

/**
 * Resolve a log file path inside the configured log directory.
 *
 * @param string $filename
 * @return string
 */
function resolve_log_file_path($filename) {
    return get_log_dir() . '/' . basename($filename);
}

/**
 * Rotate old log files based on retention settings.
 *
 * @param int $retentionDays
 * @return void
 */
function rotate_logs($retentionDays = 30) {
    if ($retentionDays <= 0) {
        return;
    }

    $log_dir = get_log_dir();
    $threshold = time() - ($retentionDays * 24 * 60 * 60);
    $files = glob($log_dir . '/*.log');
    if (!is_array($files)) {
        return;
    }

    foreach ($files as $file) {
        if (is_file($file) && filemtime($file) < $threshold) {
            @unlink($file);
        }
    }
}

/**
 * Check if user has required permission
 *
 * @param string $permission Required permission
 * @return bool True if user has permission
 */
function has_permission($permission) {
    if (!isset($_SESSION['user'])) {
        return false;
    }

    $user_permissions = $_SESSION['permissions'] ?? [];

    // Admin has all permissions
    if (in_array('admin', $user_permissions)) {
        return true;
    }

    return in_array($permission, $user_permissions);
}

/**
 * Get current user information
 *
 * @return array User information or empty array if not logged in
 */
function get_current_user_info() {
    if (!isset($_SESSION['user'])) {
        return [];
    }

    return [
        'id' => $_SESSION['user_id'] ?? null,
        'username' => $_SESSION['user'] ?? '',
        'email' => $_SESSION['email'] ?? '',
        'group' => $_SESSION['group'] ?? '',
        'permissions' => $_SESSION['permissions'] ?? []
    ];
}

/**
 * Format date for display
 *
 * @param string $date Date string
 * @param string $format Date format (default: 'M j, Y g:i A')
 * @return string Formatted date
 */
function format_date($date, $format = 'M j, Y g:i A') {
    if (empty($date) || $date === '0000-00-00 00:00:00') {
        return 'N/A';
    }

    $timestamp = strtotime($date);
    return date($format, $timestamp);
}

/**
 * Generate a random string
 *
 * @param int $length String length
 * @return string Random string
 */
function generate_random_string($length = 32) {
    return bin2hex(random_bytes($length / 2));
}

/**
 * Validate email address
 *
 * @param string $email Email address
 * @return bool True if valid
 */
function is_valid_email($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

/**
 * Determine if debug/development pages should be accessible.
 *
 * @return bool
 */
function is_debug_pages_enabled() {
    global $debug_mode, $debug_pages_enabled, $app_env;

    if (!empty($app_env) && strtolower($app_env) === 'production') {
        return false;
    }

    return !empty($debug_pages_enabled) || !empty($debug_mode);
}

/**
 * Require debug page access and deny the request in production.
 */
function require_debug_page_access() {
    if (!is_debug_pages_enabled()) {
        http_response_code(403);
        echo '<h1>403 Forbidden</h1>';
        echo '<p>Debug pages are disabled in production. Set APP_ENV=development or ENABLE_DEBUG_PAGES=true to enable them locally.</p>';
        exit;
    }
}

/**
 * Log a debug message only when debug mode is enabled.
 *
 * @param string $message The debug message to write.
 */
function log_debug($message) {
    global $debug_mode;
    if (!empty($debug_mode)) {
        error_log($message);
    }
}

/**
 * Send a permission request notification to the configured admin email.
 *
 * @param string $action The attempted action that requires approval.
 * @param string $reason Why the action was blocked or needs permission.
 * @param array $context Optional additional context for the notification.
 * @return bool True if email was sent successfully.
 */
function send_permission_request_notification($action, $reason, $context = []) {
    global $SMTP_ENABLED, $SMTP_HOST, $SMTP_PORT, $SMTP_USER, $SMTP_PASS, $SMTP_SECURE, $SMTP_FROM_EMAIL, $SMTP_FROM_NAME;

    $recipient = env('PERMISSION_REQUEST_NOTIFY_EMAIL', 'kalemaf876@gmail.com');
    if (!is_valid_email($recipient)) {
        return false;
    }

    $subject = "[Permission Request] {$action}";
    $timestamp = date('Y-m-d H:i:s');
    $url = $_SERVER['REQUEST_URI'] ?? 'N/A';
    $body = "A permission/approval validation event occurred.\n\n" .
            "Action: {$action}\n" .
            "Reason: {$reason}\n" .
            "Requested at: {$timestamp}\n" .
            "Page: {$url}\n\n";

    if (!empty($context) && is_array($context)) {
        $body .= "Context:\n";
        foreach ($context as $key => $value) {
            $body .= ucfirst($key) . ": " . trim((string)$value) . "\n";
        }
        $body .= "\n";
    }

    $body .= "Please review and take the appropriate approval action.";

    $fromEmail = $SMTP_FROM_EMAIL ?: 'no-reply@example.com';
    $fromName = $SMTP_FROM_NAME ?: 'CMMS Notification';

    $autoload = APP_ROOT . DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR . 'autoload.php';
    if (file_exists($autoload)) {
        require_once $autoload;
    }

    if (!empty($SMTP_ENABLED) && class_exists('PHPMailer\PHPMailer\PHPMailer')) {
        try {
            $mail = new PHPMailer\PHPMailer\PHPMailer(true);
            $mail->isSMTP();
            $mail->Host = $SMTP_HOST;
            $mail->Port = (int)$SMTP_PORT;
            $mail->SMTPAuth = true;
            if (!empty($SMTP_USER)) {
                $mail->Username = $SMTP_USER;
                $mail->Password = $SMTP_PASS;
            }
            if (!empty($SMTP_SECURE)) {
                $mail->SMTPSecure = $SMTP_SECURE;
            }
            $mail->setFrom($fromEmail, $fromName);
            $mail->addAddress($recipient);
            $mail->Subject = $subject;
            $mail->Body = $body;
            $mail->AltBody = $body;
            $mail->send();
            return true;
        } catch (Exception $e) {
            log_event('Permission notification failed: ' . $e->getMessage(), 'ERROR');
        }
    }

    $headers = 'From: ' . $fromName . ' <' . $fromEmail . '>\r\n';
    $headers .= 'Reply-To: ' . $fromEmail . '\r\n';
    $headers .= 'Content-Type: text/plain; charset=UTF-8\r\n';

    return @mail($recipient, $subject, $body, $headers);
}

/**
 * Get a list of supported countries for dropdowns
 *
 * @return array List of countries
 */
function get_country_list() {
    return [
        'Afghanistan', 'Albania', 'Algeria', 'Andorra', 'Angola', 'Antigua and Barbuda', 'Argentina', 'Armenia', 'Australia', 'Austria', 'Azerbaijan',
        'Bahamas', 'Bahrain', 'Bangladesh', 'Barbados', 'Belarus', 'Belgium', 'Belize', 'Benin', 'Bhutan', 'Bolivia', 'Bosnia and Herzegovina', 'Botswana', 'Brazil', 'Brunei', 'Bulgaria', 'Burkina Faso', 'Burundi',
        'Cabo Verde', 'Cambodia', 'Cameroon', 'Canada', 'Central African Republic', 'Chad', 'Chile', 'China', 'Colombia', 'Comoros', 'Costa Rica', 'Croatia', 'Cuba', 'Cyprus', 'Czech Republic',
        'Democratic Republic of the Congo', 'Denmark', 'Djibouti', 'Dominica', 'Dominican Republic',
        'Ecuador', 'Egypt', 'El Salvador', 'Equatorial Guinea', 'Eritrea', 'Estonia', 'Eswatini', 'Ethiopia',
        'Fiji', 'Finland', 'France',
        'Gabon', 'Gambia', 'Georgia', 'Germany', 'Ghana', 'Greece', 'Grenada', 'Guatemala', 'Guinea', 'Guinea-Bissau', 'Guyana',
        'Haiti', 'Honduras', 'Hungary',
        'Iceland', 'India', 'Indonesia', 'Iran', 'Iraq', 'Ireland', 'Israel', 'Italy', 'Ivory Coast',
        'Jamaica', 'Japan', 'Jordan',
        'Kazakhstan', 'Kenya', 'Kiribati', 'Kosovo', 'Kuwait', 'Kyrgyzstan',
        'Laos', 'Latvia', 'Lebanon', 'Lesotho', 'Liberia', 'Libya', 'Liechtenstein', 'Lithuania', 'Luxembourg',
        'Madagascar', 'Malawi', 'Malaysia', 'Maldives', 'Mali', 'Malta', 'Marshall Islands', 'Mauritania', 'Mauritius', 'Mexico', 'Micronesia', 'Moldova', 'Monaco', 'Mongolia', 'Montenegro', 'Morocco', 'Mozambique', 'Myanmar',
        'Namibia', 'Nauru', 'Nepal', 'Netherlands', 'New Zealand', 'Nicaragua', 'Niger', 'Nigeria', 'North Korea', 'North Macedonia', 'Norway',
        'Oman',
        'Pakistan', 'Palau', 'Panama', 'Papua New Guinea', 'Paraguay', 'Peru', 'Philippines', 'Poland', 'Portugal',
        'Qatar',
        'Romania', 'Russia', 'Rwanda',
        'Saint Kitts and Nevis', 'Saint Lucia', 'Saint Vincent and the Grenadines', 'Samoa', 'San Marino', 'Sao Tome and Principe', 'Saudi Arabia', 'Senegal', 'Serbia', 'Seychelles', 'Sierra Leone', 'Singapore', 'Slovakia', 'Slovenia', 'Solomon Islands', 'Somalia', 'South Africa', 'South Korea', 'South Sudan', 'Spain', 'Sri Lanka', 'Sudan', 'Suriname', 'Sweden', 'Switzerland', 'Syria',
        'Taiwan', 'Tajikistan', 'Tanzania', 'Thailand', 'Timor-Leste', 'Togo', 'Tonga', 'Trinidad and Tobago', 'Tunisia', 'Turkey', 'Turkmenistan', 'Tuvalu',
        'Uganda', 'Ukraine', 'United Arab Emirates', 'United Kingdom', 'United States', 'Uruguay', 'Uzbekistan',
        'Vanuatu', 'Vatican City', 'Venezuela', 'Vietnam',
        'Yemen',
        'Zambia', 'Zimbabwe'
    ];
}

/**
 * Get file extension
 *
 * @param string $filename Filename
 * @return string File extension (lowercase)
 */
function get_file_extension($filename) {
    return strtolower(pathinfo($filename, PATHINFO_EXTENSION));
}

/**
 * Check if file type is allowed
 *
 * @param string $filename Filename
 * @return bool True if allowed
 */
function is_allowed_file_type($filename) {
    global $allowed_file_types;
    $extension = get_file_extension($filename);
    return in_array($extension, $allowed_file_types ?? []);
}

/**
 * Generate a unique filename
 *
 * @param string $original_filename Original filename
 * @return string Unique filename
 */
function generate_unique_filename($original_filename) {
    $extension = get_file_extension($original_filename);
    return generate_random_string(16) . '.' . $extension;
}

// ============================================================================
// DATABASE UTILITY FUNCTIONS
// ============================================================================

/**
 * Execute a query and return results as array
 *
 * @param string $query SQL query
 * @return array Query results
 */
function query_to_array($query) {
    global $connection, $db_type, $db_available;

    if (!$db_available) {
        return []; // Return empty array when no database
    }

    // Auto-filter by tenant_id for multi-tenant isolation
    $query = apply_tenant_filter($query);

    if ($db_type === 'sqlite') {
        try {
            $result = $connection->query($query);
            if (!$result) {
                return [];
            }
            $data = [];
            while ($row = $result->fetch(PDO::FETCH_ASSOC)) {
                $data[] = $row;
            }
            return $data;
        } catch (Exception $e) {
            return [];
        }
    } else {
        $result = $connection->query($query);
        if (!$result) {
            return [];
        }

        $data = [];
        while ($row = $result->fetch_assoc()) {
            $data[] = $row;
        }
        return $data;
    }
}

/**
 * Get single row from query
 *
 * @param string $query SQL query
 * @return array|null Single row or null
 */
function query_single_row($query) {
    global $connection, $db_type, $db_available;

    if (!$db_available) {
        return null; // Return null when no database
    }

    // Auto-filter by tenant_id for multi-tenant isolation
    $query = apply_tenant_filter($query);

    if ($db_type === 'sqlite') {
        try {
            $result = $connection->query($query);
            return $result ? $result->fetch(PDO::FETCH_ASSOC) : null;
        } catch (Exception $e) {
            return null;
        }
    } else {
        $result = $connection->query($query);
        return $result ? $result->fetch_assoc() : null;
    }
}

/**
 * Check if table exists
 *
 * @param string $table_name Table name
 * @return bool True if table exists
 */
function table_exists($table_name) {
    global $connection, $db_type, $databaseName, $db_available;

    if (!$db_available) {
        return false; // No database available
    }

    if ($db_type === 'sqlite') {
        $result = $connection->query("SELECT name FROM sqlite_master WHERE type='table' AND name='$table_name'");
        return (bool)$result->fetch(PDO::FETCH_ASSOC);
    } else {
        $query = "SELECT COUNT(*) as cnt FROM information_schema.tables
                  WHERE table_schema = ? AND table_name = ?";
        $stmt = $connection->prepare($query);
        $stmt->bind_param('ss', $databaseName, $table_name);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        return ($row['cnt'] > 0);
    }
}

/**
 * Fetch a single row from a result set in a database-agnostic way
 *
 * @param object $result Result object from query
 * @return array|null Single row or null
 */
function fetch_assoc_compatible($result) {
    global $db_type;

    if (!$result) {
        return null;
    }

    if ($db_type === 'sqlite') {
        // PDO handling
        return $result->fetch(PDO::FETCH_ASSOC);
    } else {
        // MySQLi handling
        return $result->fetch_assoc();
    }
}

/**
 * Enforce operator users to only access the work order requests page.
 * Redirects any operator user away from other application pages.
 */
function enforce_operator_request_only() {
    if (session_status() !== PHP_SESSION_ACTIVE) {
        return;
    }

    $userRole = $_SESSION['role'] ?? '';
    if ($userRole !== 'operator') {
        return;
    }

    $allowedPages = [
        'work_order_requests.php',
        'auth.php',
    ];

    $currentPage = basename($_SERVER['PHP_SELF']);
    if (!in_array($currentPage, $allowedPages, true)) {
        header('Location: work_order_requests.php');
        exit;
    }
}

// Apply operator restriction immediately for all pages that include common.inc.php.
enforce_operator_request_only();

// ============================================================================
// LICENSE VALIDATION FUNCTIONS
// ============================================================================

/**
 * Check if user has a valid license
 *
 * @return array ['valid' => bool, 'message' => string, 'license' => array|null]
 */
function check_user_license() {
    global $connection, $db_available;

    if (!$db_available) {
        return ['valid' => false, 'message' => 'Database not available', 'license' => null];
    }

    if (empty($_SESSION['user'])) {
        return ['valid' => false, 'message' => 'User not logged in', 'license' => null];
    }

    $user_id = $_SESSION['user_id'] ?? null;
    if (!$user_id) {
        return ['valid' => false, 'message' => 'User ID not found in session', 'license' => null];
    }

    // DEVELOPER BYPASS: Allow developers and admins to bypass license checks
    $user_role = strtolower($_SESSION['role'] ?? '');
    $current_user_name = strtolower(trim($_SESSION['user'] ?? ''));

    if ($user_role === 'developer' || $user_role === 'admin' || $current_user_name === 'developer') {
        return ['valid' => true, 'message' => 'Developer/Admin bypass - License check skipped', 'license' => null];
    }

    // CONFIG BYPASS: Allow explicit bypass configuration for non-developer admin use
    if (!empty($developer_bypass_license)) {
        return ['valid' => true, 'message' => 'Developer bypass enabled - License check skipped', 'license' => null];
    }

    // Get user's company and license info
    $query = "SELECT cl.license_id, cl.license_key, cl.license_type, cl.purchased_seats, 
                     cl.used_seats, cl.expires_at, cl.is_active, cl.payment_term,
                     c.company_name, c.is_active as company_active
              FROM users u
              JOIN companies c ON u.company_id = c.company_id
              JOIN company_licenses cl ON c.company_id = cl.company_id
              WHERE u.user_id = ? AND cl.is_active = 1 AND c.is_active = 1
              ORDER BY cl.created_at DESC LIMIT 1";

    $stmt = $connection->prepare($query);
    if (!$stmt) {
        return ['valid' => false, 'message' => 'Database query failed', 'license' => null];
    }

    $stmt->bind_param('i', $user_id);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 0) {
        return ['valid' => false, 'message' => 'No active license found for your company', 'license' => null];
    }

    $license = $result->fetch_assoc();

    // Check if license is expired
    if (!empty($license['expires_at'])) {
        $expires_at = strtotime($license['expires_at']);
        $now = time();
        if ($expires_at < $now) {
            return ['valid' => false, 'message' => 'Your license has expired', 'license' => $license];
        }
    }

    // Check seat limit
    if ($license['used_seats'] >= $license['purchased_seats']) {
        return ['valid' => false, 'message' => 'License seat limit exceeded', 'license' => $license];
    }

    return ['valid' => true, 'message' => 'License is valid', 'license' => $license];
}

/**
 * Validate license key format and existence
 *
 * @param string $license_key License key to validate
 * @return array ['valid' => bool, 'message' => string, 'license' => array|null]
 */
function validate_license_key($license_key) {
    global $connection, $db_available;

    if (!$db_available) {
        return ['valid' => false, 'message' => 'Database not available', 'license' => null];
    }

    $license_key = trim(strtoupper($license_key));

    // Basic format check (16 character alphanumeric)
    if (!preg_match('/^[A-Z0-9]{16}$/', $license_key)) {
        return ['valid' => false, 'message' => 'Invalid license key format', 'license' => null];
    }

    // Check if license exists and is active
    $query = "SELECT cl.*, c.company_name, c.is_active as company_active
              FROM company_licenses cl
              JOIN companies c ON cl.company_id = c.company_id
              WHERE cl.license_key = ? AND cl.is_active = 1 AND c.is_active = 1";

    $stmt = $connection->prepare($query);
    if (!$stmt) {
        return ['valid' => false, 'message' => 'Database query failed', 'license' => null];
    }

    $stmt->bind_param('s', $license_key);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 0) {
        return ['valid' => false, 'message' => 'License key not found or inactive', 'license' => null];
    }

    $license = $result->fetch_assoc();

    // Check expiration
    if (!empty($license['expires_at'])) {
        $expires_at = strtotime($license['expires_at']);
        $now = time();
        if ($expires_at < $now) {
            return ['valid' => false, 'message' => 'License has expired', 'license' => $license];
        }
    }

    return ['valid' => true, 'message' => 'License key is valid', 'license' => $license];
}

/**
 * Activate license for current user/company
 *
 * @param string $license_key License key to activate
 * @return array ['success' => bool, 'message' => string]
 */
function activate_license($license_key) {
    global $connection, $db_available;

    if (!$db_available) {
        return ['success' => false, 'message' => 'Database not available'];
    }

    if (empty($_SESSION['user'])) {
        return ['success' => false, 'message' => 'User not logged in'];
    }

    $user_id = $_SESSION['user_id'] ?? null;
    if (!$user_id) {
        return ['success' => false, 'message' => 'User ID not found in session'];
    }

    // Validate the license key
    $validation = validate_license_key($license_key);
    if (!$validation['valid']) {
        return ['success' => false, 'message' => $validation['message']];
    }

    $license = $validation['license'];

    // Get user's company
    $query = "SELECT company_id FROM users WHERE user_id = ?";
    $stmt = $connection->prepare($query);
    $stmt->bind_param('i', $user_id);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 0) {
        return ['success' => false, 'message' => 'User company not found'];
    }

    $user = $result->fetch_assoc();

    // Check if license belongs to user's company
    if ($license['company_id'] != $user['company_id']) {
        return ['success' => false, 'message' => 'License key does not belong to your company'];
    }

    // Store license info in session
    $_SESSION['license_valid'] = true;
    $_SESSION['license_info'] = $license;

    // Log license activation
    log_license_action($license['license_id'], $user_id, 'license_activated', null, null, 'License activated via gate');

    return ['success' => true, 'message' => 'License activated successfully'];
}

/**
 * Log license-related actions
 *
 * @param int $license_id License ID
 * @param int|null $user_id User ID performing action, or null if no user is associated
 * @param string $action Action type
 * @param int|null $old_value Old value
 * @param int|null $new_value New value
 * @param string|null $details Additional details
 */
function log_license_action($license_id, $user_id, $action, $old_value = null, $new_value = null, $details = null) {
    global $connection, $db_available;

    if (!$db_available) {
        return;
    }

    $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';

    $query = "INSERT INTO license_audit_log (license_id, user_id, action, old_value, new_value, details, ip_address)
              VALUES (?, ?, ?, ?, ?, ?, ?)";

    $stmt = $connection->prepare($query);
    if ($stmt) {
        $stmt->bind_param('iississ', $license_id, $user_id, $action, $old_value, $new_value, $details, $ip);
        $stmt->execute();
    }
}

/**
 * Generate a random license key
 *
 * @return string 16-character uppercase alphanumeric license key
 */
function generate_license_key() {
    return strtoupper(substr(md5(uniqid(mt_rand(), true)), 0, 16));
}

/**
 * Get subscription plans
 *
 * @return array Available subscription plans
 */
function get_subscription_plans() {
    return [
        'trial' => [
            'name' => 'Trial',
            'seats' => 5,
            'price' => 0,
            'duration' => '30 days',
            'features' => ['Basic CMMS features', 'Up to 5 users', 'Email support'],
            'label' => 'Introductory access for evaluation'
        ],
        'basic' => [
            'name' => 'Basic',
            'seats' => 25,
            'price' => 49,
            'duration' => 'monthly',
            'features' => ['All CMMS features', 'Up to 25 users', 'Priority email support', 'Basic reporting'],
            'label' => 'Best for small maintenance teams'
        ],
        'professional' => [
            'name' => 'Professional',
            'seats' => 100,
            'price' => 149,
            'duration' => 'monthly',
            'features' => ['All Basic features', 'Up to 100 users', 'Phone support', 'Advanced analytics', 'API access'],
            'label' => 'Designed for growing operations'
        ],
        'enterprise' => [
            'name' => 'Enterprise',
            'seats' => 500,
            'price' => 399,
            'duration' => 'monthly',
            'features' => ['All Professional features', 'Unlimited users', 'Dedicated support', 'Custom integrations', 'On-premise deployment'],
            'label' => 'For large organizations with custom requirements'
        ]
    ];
}

/**
 * Get payment provider settings from environment.
 *
 * @return array
 */
function get_payment_provider_config() {
    return [
        'provider' => env('PAYMENT_PROVIDER', 'manual'),
        'stripe_secret_key' => env('STRIPE_SECRET_KEY', ''),
        'stripe_publishable_key' => env('STRIPE_PUBLISHABLE_KEY', ''),
        'payment_currency' => strtoupper(env('PAYMENT_CURRENCY', 'USD')),
        'stripe_success_url' => env('STRIPE_SUCCESS_URL', env('APP_URL', 'http://127.0.0.1:8000') . '/license_gate.php?after_payment=1'),
        'stripe_cancel_url' => env('STRIPE_CANCEL_URL', env('APP_URL', 'http://127.0.0.1:8000') . '/license_gate.php'),
        'stripe_webhook_secret' => env('STRIPE_WEBHOOK_SECRET', ''),
    ];
}

/**
 * Create a checkout session for a plan if Stripe is configured.
 *
 * @param string $plan_key
 * @param array $metadata Optional metadata to attach to the Stripe session
 * @return array ['success' => bool, 'redirect_url' => string|null, 'message' => string]
 */
function create_checkout_session($plan_key, $metadata = []) {
    $plans = get_subscription_plans();
    if (!isset($plans[$plan_key])) {
        return ['success' => false, 'redirect_url' => null, 'message' => 'Unknown subscription plan selected.'];
    }

    $plan = $plans[$plan_key];
    if ((int)$plan['price'] <= 0) {
        return ['success' => false, 'redirect_url' => null, 'message' => 'This plan does not require Stripe checkout. Activate it directly on the license gate.'];
    }

    $config = get_payment_provider_config();
    if ($config['provider'] !== 'stripe') {
        return ['success' => false, 'redirect_url' => null, 'message' => 'No payment provider configured.'];
    }

    if (empty($config['stripe_secret_key'])) {
        return ['success' => false, 'redirect_url' => null, 'message' => 'Stripe secret key is not configured.'];
    }

    $amount = (int)round($plan['price'] * 100);
    $currency = strtolower($config['payment_currency']);

    $payload = [
        'payment_method_types[]' => 'card',
        'mode' => 'payment',
        'line_items[0][price_data][currency]' => $currency,
        'line_items[0][price_data][product_data][name]' => 'KFMMS ' . $plan['name'] . ' Subscription',
        'line_items[0][price_data][product_data][description]' => 'Subscription plan purchase for KFMMS',
        'line_items[0][price_data][unit_amount]' => $amount,
        'line_items[0][quantity]' => 1,
        'success_url' => $config['stripe_success_url'],
        'cancel_url' => $config['stripe_cancel_url'],
        'metadata[plan_key]' => $plan_key,
        'metadata[product_name]' => $plan['name'],
    ];

    if (!empty($metadata['customer_email'])) {
        $payload['customer_email'] = $metadata['customer_email'];
        unset($metadata['customer_email']);
    }

    foreach ($metadata as $meta_key => $meta_value) {
        if ($meta_value === null || $meta_value === '') {
            continue;
        }
        $payload['metadata[' . $meta_key . ']'] = (string)$meta_value;
    }

    $postData = http_build_query($payload);

    $ch = curl_init('https://api.stripe.com/v1/checkout/sessions');
    curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
    curl_setopt($ch, CURLOPT_USERPWD, $config['stripe_secret_key'] . ':');
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/x-www-form-urlencoded']);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);

    if ($response === false) {
        return ['success' => false, 'redirect_url' => null, 'message' => 'Payment gateway error: ' . $curlError];
    }

    $payload = json_decode($response, true);
    if ($httpCode !== 200 || empty($payload['url'])) {
        $message = $payload['error']['message'] ?? 'Unable to create checkout session.';
        return ['success' => false, 'redirect_url' => null, 'message' => 'Stripe error: ' . $message];
    }

    return ['success' => true, 'redirect_url' => $payload['url'], 'message' => 'Redirecting to payment gateway...'];
}

/**
 * Get PayPal configuration values.
 *
 * @return array
 */
function get_paypal_config() {
    return [
        'provider' => env('PAYMENT_PROVIDER', 'manual'),
        'client_id' => env('PAYPAL_CLIENT_ID', ''),
        'secret' => env('PAYPAL_SECRET', ''),
        'environment' => strtolower(env('PAYPAL_ENVIRONMENT', 'live')),
        'success_url' => env('PAYPAL_SUCCESS_URL', env('APP_URL', 'http://127.0.0.1:8000') . '/paypal_return.php'),
        'cancel_url' => env('PAYPAL_CANCEL_URL', env('APP_URL', 'http://127.0.0.1:8000') . '/license_gate.php'),
        'merchant_name' => env('PAYPAL_MERCHANT_NAME', 'Efficraft Technologies Limited'),
        'webhook_id' => env('PAYPAL_WEBHOOK_ID', ''),
        'notification_email' => env('PAYMENT_NOTIFICATION_EMAIL', 'kalemaf876@gmail.com'),
    ];
}

/**
 * Verify a PayPal webhook signature by calling PayPal's verification API.
 *
 * @param array $headers
 * @param string $body
 * @param array $config
 * @return array ['success' => bool, 'message' => string]
 */
function verify_paypal_webhook_signature(array $headers, $body, array $config) {
    if (empty($config['webhook_id'])) {
        return ['success' => false, 'message' => 'PayPal webhook ID is not configured.'];
    }

    $tokenResult = get_paypal_access_token($config);
    if (!$tokenResult['success']) {
        return ['success' => false, 'message' => $tokenResult['message']];
    }

    $verifyPayload = [
        'auth_algo' => $headers['auth_algo'] ?? '',
        'cert_url' => $headers['cert_url'] ?? '',
        'transmission_id' => $headers['transmission_id'] ?? '',
        'transmission_sig' => $headers['transmission_sig'] ?? '',
        'transmission_time' => $headers['transmission_time'] ?? '',
        'webhook_id' => $config['webhook_id'],
        'webhook_event' => json_decode($body, true),
    ];

    if (empty($verifyPayload['auth_algo']) || empty($verifyPayload['cert_url']) || empty($verifyPayload['transmission_id']) || empty($verifyPayload['transmission_sig']) || empty($verifyPayload['transmission_time'])) {
        return ['success' => false, 'message' => 'Missing PayPal webhook verification headers.'];
    }

    $apiBase = get_paypal_api_base($config['environment']);
    $ch = curl_init($apiBase . '/v1/notifications/verify-webhook-signature');
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Authorization: Bearer ' . $tokenResult['access_token'],
    ]);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($verifyPayload));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

    $response = curl_exec($ch);
    $curlError = curl_error($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

    if ($response === false) {
        return ['success' => false, 'message' => 'PayPal verification error: ' . $curlError];
    }

    $responseData = json_decode($response, true);
    if ($httpCode !== 200 || empty($responseData['verification_status'])) {
        $message = $responseData['message'] ?? $responseData['name'] ?? 'Unable to verify PayPal webhook signature.';
        return ['success' => false, 'message' => 'PayPal verification failed: ' . $message];
    }

    return ['success' => $responseData['verification_status'] === 'SUCCESS', 'message' => $responseData['verification_status'] ?? 'unknown'];
}

/**
 * Process completed PayPal capture events and create the license.
 *
 * @param array $event
 * @return array
 */
function process_paypal_webhook_event(array $event) {
    $eventType = $event['event_type'] ?? ''; 
    $resource = $event['resource'] ?? [];
    $orderId = $resource['supplementary_data']['related_ids']['order_id'] ?? ($resource['order_id'] ?? '');

    if (empty($orderId)) {
        return ['success' => false, 'message' => 'PayPal webhook event missing order identifier.'];
    }

    $paymentOrder = get_payment_order($orderId);
    if (empty($paymentOrder)) {
        return ['success' => false, 'message' => 'Payment order not found for PayPal order: ' . $orderId];
    }

    if ($paymentOrder['status'] === 'completed') {
        return ['success' => true, 'message' => 'Payment order already completed.'];
    }

    $captureId = $resource['id'] ?? null;
    $transactionId = $captureId;
    $status = strtolower($resource['status'] ?? '');
    $captureOrderId = $orderId;

    if ($eventType === 'PAYMENT.CAPTURE.COMPLETED') {
        update_payment_order_status($orderId, 'completed', $transactionId, $captureId);

        $companyId = !empty($paymentOrder['company_id']) ? (int)$paymentOrder['company_id'] : resolve_company_id_for_payment(
            ['user_id' => $paymentOrder['user_id'] ?? null],
            $paymentOrder['customer_email'] ?? null
        );

        if (empty($companyId)) {
            return ['success' => false, 'message' => 'Unable to resolve company for PayPal payment.'];
        }

        $result = create_company_license_from_payment(
            $companyId,
            $paymentOrder['plan_key'],
            $transactionId,
            $orderId,
            $paymentOrder['customer_email'] ?? ''
        );

        if (!$result['success']) {
            return ['success' => false, 'message' => 'License creation failed: ' . $result['message']];
        }

        $paypalConfig = get_paypal_config();
        $notificationEmail = trim($paypalConfig['notification_email'] ?? '');
        if (!empty($notificationEmail) && is_valid_email($notificationEmail)) {
            $subject = 'PayPal capture processed for KFMMS';
            $body = "PayPal capture completed for order {$orderId}.\n" .
                    "Transaction: {$transactionId}\n" .
                    "Plan: {$paymentOrder['plan_key']}\n" .
                    "Customer email: {$paymentOrder['customer_email']}\n" .
                    "Company ID: {$companyId}\n";
            send_system_email($notificationEmail, $subject, $body);
        }

        if (!empty($paymentOrder['customer_email']) && is_valid_email($paymentOrder['customer_email'])) {
            $company = get_company_by_id($companyId);
            $planName = get_subscription_plans()[$paymentOrder['plan_key']]['name'] ?? ucfirst($paymentOrder['plan_key']);
            send_license_email(
                $paymentOrder['customer_email'],
                $company['company_name'] ?? 'Your organization',
                $planName,
                $result['license_key'],
                $result['license']['expires_at'] ?? null,
                (int)$result['license']['purchased_seats']
            );
        }

        return ['success' => true, 'message' => 'PayPal payment captured and license created.'];
    }

    if ($eventType === 'PAYMENT.CAPTURE.DENIED' || $eventType === 'PAYMENT.CAPTURE.REFUNDED' || $eventType === 'PAYMENT.CAPTURE.REVERSED') {
        update_payment_order_status($orderId, 'failed', $transactionId, $captureId);
        return ['success' => true, 'message' => 'PayPal payment event recorded: ' . $eventType];
    }

    return ['success' => true, 'message' => 'PayPal event ignored: ' . $eventType];
}

/**
 * Get the PayPal API base URL for the configured environment.
 *
 * @param string $environment
 * @return string
 */
function get_paypal_api_base($environment) {
    return strtolower($environment) === 'sandbox'
        ? 'https://api-m.sandbox.paypal.com'
        : 'https://api-m.paypal.com';
}

/**
 * Request a PayPal access token.
 *
 * @param array $config
 * @return array
 */
function get_paypal_access_token(array $config) {
    $apiBase = get_paypal_api_base($config['environment']);
    $ch = curl_init($apiBase . '/v1/oauth2/token');
    curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
    curl_setopt($ch, CURLOPT_USERPWD, $config['client_id'] . ':' . $config['secret']);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, 'grant_type=client_credentials');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Accept: application/json', 'Accept-Language: en_US']);

    $response = curl_exec($ch);
    $curlError = curl_error($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

    if ($response === false) {
        return ['success' => false, 'message' => 'PayPal auth error: ' . $curlError];
    }

    $payload = json_decode($response, true);
    if ($httpCode !== 200 || empty($payload['access_token'])) {
        $message = $payload['error_description'] ?? $payload['error'] ?? 'Unable to retrieve PayPal access token.';
        return ['success' => false, 'message' => 'PayPal auth error: ' . $message];
    }

    return ['success' => true, 'access_token' => $payload['access_token']];
}

/**
 * Ensure the payment_orders table exists for PayPal order tracking.
 */
function ensure_payment_orders_table() {
    global $connection, $db_type, $db_available;
    if (!$db_available || !is_object($connection)) {
        return;
    }

    if ($db_type === 'sqlite') {
        $sql = "CREATE TABLE IF NOT EXISTS payment_orders (
            payment_order_id INTEGER PRIMARY KEY AUTOINCREMENT,
            order_id TEXT NOT NULL UNIQUE,
            company_id INTEGER NULL,
            user_id INTEGER NULL,
            customer_email TEXT NULL,
            plan_key TEXT NOT NULL,
            amount REAL NOT NULL,
            currency TEXT NOT NULL,
            payment_provider TEXT NOT NULL,
            status TEXT NOT NULL DEFAULT 'pending',
            transaction_id TEXT NULL,
            capture_id TEXT NULL,
            created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
        )";
        $connection->exec($sql);
    } else {
        $sql = "CREATE TABLE IF NOT EXISTS payment_orders (
            payment_order_id INT(11) NOT NULL AUTO_INCREMENT,
            order_id VARCHAR(191) NOT NULL UNIQUE,
            company_id INT(11) NULL,
            user_id INT(11) NULL,
            customer_email VARCHAR(255) NULL,
            plan_key VARCHAR(100) NOT NULL,
            amount DOUBLE NOT NULL,
            currency VARCHAR(10) NOT NULL,
            payment_provider VARCHAR(50) NOT NULL,
            status VARCHAR(50) NOT NULL DEFAULT 'pending',
            transaction_id VARCHAR(255) NULL,
            capture_id VARCHAR(255) NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (payment_order_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
        $connection->query($sql);
    }
}

/**
 * Store a PayPal checkout order in the local database.
 */
function store_payment_order($orderId, $companyId, $userId, $customerEmail, $planKey, $amount, $currency, $provider) {
    global $connection;
    ensure_payment_orders_table();
    $stmt = $connection->prepare('INSERT OR REPLACE INTO payment_orders (order_id, company_id, user_id, customer_email, plan_key, amount, currency, payment_provider, status, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)');
    if (!$stmt) {
        $stmt = $connection->prepare('INSERT INTO payment_orders (order_id, company_id, user_id, customer_email, plan_key, amount, currency, payment_provider, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)');
    }
    if ($stmt) {
        $status = 'pending';
        $stmt->bind_param('siissdsss', $orderId, $companyId, $userId, $customerEmail, $planKey, $amount, $currency, $provider, $status);
        $stmt->execute();
    }
}

/**
 * Retrieve a payment order by PayPal order ID.
 */
function get_payment_order($orderId) {
    global $connection;
    $stmt = $connection->prepare('SELECT * FROM payment_orders WHERE order_id = ? LIMIT 1');
    if (!$stmt) {
        return null;
    }
    $stmt->bind_param('s', $orderId);
    $stmt->execute();
    $result = $stmt->get_result();
    if (!$result) {
        return null;
    }
    return $result->fetch_assoc();
}

/**
 * Update the status and transaction references for a payment order.
 */
function update_payment_order_status($orderId, $status, $transactionId = null, $captureId = null) {
    global $connection;
    $sql = 'UPDATE payment_orders SET status = ?, transaction_id = ?, capture_id = ?, updated_at = CURRENT_TIMESTAMP WHERE order_id = ?';
    $stmt = $connection->prepare($sql);
    if (!$stmt) {
        return false;
    }
    $stmt->bind_param('ssss', $status, $transactionId, $captureId, $orderId);
    return $stmt->execute();
}

/**
 * Create a PayPal checkout order and return the approval URL.
 *
 * @param string $plan_key
 * @param array $metadata
 * @return array
 */
function create_paypal_order($plan_key, $metadata = []) {
    global $connection;

    $plans = get_subscription_plans();
    if (!isset($plans[$plan_key])) {
        return ['success' => false, 'redirect_url' => null, 'message' => 'Unknown subscription plan selected.'];
    }

    $plan = $plans[$plan_key];
    if ((int)$plan['price'] <= 0) {
        return ['success' => false, 'redirect_url' => null, 'message' => 'This plan does not require PayPal checkout.'];
    }

    $config = get_paypal_config();
    if ($config['provider'] !== 'paypal') {
        return ['success' => false, 'redirect_url' => null, 'message' => 'PayPal is not configured as the payment provider.'];
    }
    if (empty($config['client_id']) || empty($config['secret'])) {
        return ['success' => false, 'redirect_url' => null, 'message' => 'PayPal credentials are not configured.'];
    }

    $tokenResult = get_paypal_access_token($config);
    if (!$tokenResult['success']) {
        return ['success' => false, 'redirect_url' => null, 'message' => $tokenResult['message']];
    }

    $amount = number_format((float)$plan['price'], 2, '.', '');
    $currency = strtoupper($config['payment_currency'] ?? env('PAYMENT_CURRENCY', 'USD'));
    $orderPayload = [
        'intent' => 'CAPTURE',
        'purchase_units' => [[
            'amount' => [
                'currency_code' => $currency,
                'value' => $amount,
            ],
            'description' => 'KFMMS ' . $plan['name'] . ' Subscription',
            'custom_id' => substr($plan_key, 0, 127),
            'invoice_id' => 'KFMMS-' . time() . '-' . strtoupper(bin2hex(random_bytes(3))),
        ]],
        'application_context' => [
            'brand_name' => $config['merchant_name'],
            'shipping_preference' => 'NO_SHIPPING',
            'user_action' => 'PAY_NOW',
            'return_url' => $config['success_url'],
            'cancel_url' => $config['cancel_url'],
        ],
    ];

    $payload = json_encode($orderPayload);
    $apiBase = get_paypal_api_base($config['environment']);
    $ch = curl_init($apiBase . '/v2/checkout/orders');
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Authorization: Bearer ' . $tokenResult['access_token'],
    ]);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

    $response = curl_exec($ch);
    $curlError = curl_error($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

    if ($response === false) {
        return ['success' => false, 'redirect_url' => null, 'message' => 'PayPal creation error: ' . $curlError];
    }

    $responseData = json_decode($response, true);
    if ($httpCode !== 201 || empty($responseData['id'])) {
        $message = $responseData['message'] ?? $responseData['details'][0]['description'] ?? 'Unable to create PayPal order.';
        return ['success' => false, 'redirect_url' => null, 'message' => 'PayPal error: ' . $message];
    }

    $approveUrl = null;
    foreach ($responseData['links'] ?? [] as $link) {
        if (!empty($link['rel']) && $link['rel'] === 'approve') {
            $approveUrl = $link['href'];
            break;
        }
    }
    if (empty($approveUrl)) {
        return ['success' => false, 'redirect_url' => null, 'message' => 'PayPal approval URL not found.'];
    }

    $companyId = isset($metadata['company_id']) ? (int)$metadata['company_id'] : null;
    $userId = isset($metadata['user_id']) ? (int)$metadata['user_id'] : null;
    $customerEmail = trim($metadata['customer_email'] ?? '');
    store_payment_order($responseData['id'], $companyId, $userId, $customerEmail, $plan_key, (float)$amount, $currency, 'paypal');

    return ['success' => true, 'redirect_url' => $approveUrl, 'message' => 'Redirecting to PayPal...'];
}

/**
 * Capture a PayPal order after approval.
 *
 * @param string $orderId
 * @return array
 */
function capture_paypal_order($orderId) {
    $config = get_paypal_config();
    if ($config['provider'] !== 'paypal') {
        return ['success' => false, 'message' => 'PayPal is not enabled.'];
    }

    $tokenResult = get_paypal_access_token($config);
    if (!$tokenResult['success']) {
        return ['success' => false, 'message' => $tokenResult['message']];
    }

    $apiBase = get_paypal_api_base($config['environment']);
    $ch = curl_init($apiBase . '/v2/checkout/orders/' . urlencode($orderId) . '/capture');
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Authorization: Bearer ' . $tokenResult['access_token'],
    ]);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, '{}');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

    $response = curl_exec($ch);
    $curlError = curl_error($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

    if ($response === false) {
        return ['success' => false, 'message' => 'PayPal capture error: ' . $curlError];
    }

    $responseData = json_decode($response, true);
    if ($httpCode !== 201) {
        $message = $responseData['message'] ?? $responseData['details'][0]['description'] ?? 'Unable to capture PayPal order.';
        return ['success' => false, 'message' => 'PayPal error: ' . $message];
    }

    $captureId = $responseData['purchase_units'][0]['payments']['captures'][0]['id'] ?? null;
    $transactionId = $captureId ?: ($responseData['id'] ?? null);
    $status = strtolower($responseData['status'] ?? '');
    $amount = $responseData['purchase_units'][0]['payments']['captures'][0]['amount']['value'] ?? null;
    $currency = $responseData['purchase_units'][0]['payments']['captures'][0]['amount']['currency_code'] ?? null;

    update_payment_order_status($orderId, $status === 'completed' ? 'completed' : 'failed', $transactionId, $captureId);

    return [
        'success' => $status === 'COMPLETED' || $status === 'completed',
        'message' => $status === 'COMPLETED' || $status === 'completed'
            ? 'Payment captured successfully.'
            : 'PayPal capture returned status: ' . ($responseData['status'] ?? 'unknown'),
        'transaction_id' => $transactionId,
        'capture_id' => $captureId,
        'amount' => $amount,
        'currency' => $currency,
    ];
}

/**
 * Generate raw PDF bytes for a simple receipt.
 *
 * @param array $data
 * @return string
 */
function generate_receipt_pdf(array $data) {
    $lines = [];
    $lines[] = 'KFMMS Payment Receipt';
    $lines[] = '---------------------------';
    $lines[] = 'Company: ' . ($data['company_name'] ?? 'N/A');
    $lines[] = 'Plan: ' . ($data['plan_name'] ?? 'N/A');
    $lines[] = 'Seats: ' . ($data['seats'] ?? 'N/A');
    $lines[] = 'Amount: ' . ($data['amount'] ?? 'N/A') . ' ' . ($data['currency'] ?? 'USD');
    $lines[] = 'Payment Provider: ' . ($data['provider'] ?? 'PayPal');
    $lines[] = 'Transaction ID: ' . ($data['transaction_id'] ?? 'N/A');
    $lines[] = 'Order ID: ' . ($data['order_id'] ?? 'N/A');
    $lines[] = 'Purchased By: ' . ($data['customer_email'] ?? 'N/A');
    $lines[] = 'Date: ' . date('Y-m-d H:i:s');
    $lines[] = '';
    $lines[] = 'License Key: ' . ($data['license_key'] ?? 'N/A');
    $lines[] = 'Expires: ' . ($data['expires_at'] ? date('M j, Y', strtotime($data['expires_at'])) : 'Permanent');
    $lines[] = '';
    $lines[] = 'Thank you for your purchase. Use the license key to activate your system.';

    $text = '';
    $y = 760;
    foreach ($lines as $line) {
        $escaped = str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], $line);
        $text .= "BT /F1 12 Tf 50 $y Td ($escaped) Tj ET\n";
        $y -= 18;
    }

    $objects = [];
    $objects[] = "1 0 obj<</Type/Catalog/Pages 2 0 R>>endobj";
    $objects[] = "2 0 obj<</Type/Pages/Count 1/Kids [3 0 R]>>endobj";
    $objects[] = "3 0 obj<</Type/Page/Parent 2 0 R/MediaBox [0 0 612 792]/Resources<</Font<</F1 4 0 R>>>>/Contents 5 0 R>>endobj";
    $objects[] = "4 0 obj<</Type/Font/Subtype/Type1/BaseFont/Helvetica>>endobj";
    $stream = "BT /F1 12 Tf 50 760 Td " . implode(' T* ', array_map(function ($line) {
        $escaped = str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], $line);
        return "($escaped) Tj";
    }, $lines)) . " ET";
    $objects[] = "5 0 obj<</Length " . strlen($stream) . ">>stream\n$stream\nendstreamendobj";

    $pdf = "%PDF-1.3\n";
    $offsets = [];
    $currentPos = strlen($pdf);
    foreach ($objects as $object) {
        $offsets[] = $currentPos;
        $pdf .= $object . "\n";
        $currentPos = strlen($pdf);
    }

    $xref = "xref\n0 " . (count($objects) + 1) . "\n0000000000 65535 f \n";
    foreach ($offsets as $offset) {
        $xref .= str_pad($offset, 10, '0', STR_PAD_LEFT) . " 00000 n \n";
    }

    $trailer = "trailer<</Size " . (count($objects) + 1) . "/Root 1 0 R>>\nstartxref\n" . strlen($pdf) . "\n%%EOF";
    $pdf .= $xref . $trailer;

    return $pdf;
}

/**
 * Send a plain-text message using SMTP when available, or PHP mail as a fallback.
 * Supports attachments when PHPMailer is installed.
 *
 * @param string $recipient
 * @param string $subject
 * @param string $body
 * @param string|null $fromEmail
 * @param string|null $fromName
 * @param string|null $attachmentData
 * @param string|null $attachmentName
 * @param string|null $cc
 * @return bool
 */
function send_system_email($recipient, $subject, $body, $fromEmail = null, $fromName = null, $attachmentData = null, $attachmentName = null, $cc = null) {
    global $SMTP_ENABLED, $SMTP_HOST, $SMTP_PORT, $SMTP_USER, $SMTP_PASS, $SMTP_SECURE, $SMTP_FROM_EMAIL, $SMTP_FROM_NAME;

    if (!is_valid_email($recipient)) {
        return false;
    }

    $fromEmail = $fromEmail ?: ($SMTP_FROM_EMAIL ?: 'no-reply@example.com');
    $fromName = $fromName ?: ($SMTP_FROM_NAME ?: 'CMMS Notification');

    $autoload = APP_ROOT . DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR . 'autoload.php';
    if (file_exists($autoload)) {
        require_once $autoload;
    }

    if (!empty($SMTP_ENABLED) && class_exists('PHPMailer\PHPMailer\PHPMailer')) {
        try {
            $mail = new PHPMailer\PHPMailer\PHPMailer(true);
            $mail->isSMTP();
            $mail->Host = $SMTP_HOST;
            $mail->Port = (int)$SMTP_PORT;
            $mail->SMTPAuth = !empty($SMTP_USER);
            if (!empty($SMTP_USER)) {
                $mail->Username = $SMTP_USER;
                $mail->Password = $SMTP_PASS;
            }
            if (!empty($SMTP_SECURE)) {
                $mail->SMTPSecure = $SMTP_SECURE;
            }
            $mail->setFrom($fromEmail, $fromName);
            $mail->addAddress($recipient);
            $mail->Subject = $subject;
            $mail->Body = $body;
            $mail->AltBody = $body;
            $mail->send();
            return true;
        } catch (Exception $e) {
            log_event('Email send failed: ' . $e->getMessage(), 'ERROR');
        }
    }

    $headers = "From: {$fromName} <{$fromEmail}>\r\n";
    $headers .= "Reply-To: {$fromEmail}\r\n";
    $headers .= "Content-Type: text/plain; charset=UTF-8\r\n";

    return @mail($recipient, $subject, $body, $headers);
}

/**
 * Send a license delivery email to the purchaser.
 *
 * @param string $recipient
 * @param string $company_name
 * @param string $plan_name
 * @param string $license_key
 * @param string|null $expires_at
 * @param int $seats
 * @return bool
 */
function send_license_email($recipient, $company_name, $plan_name, $license_key, $expires_at, $seats) {
    $expires_display = empty($expires_at) ? 'Permanent' : date('M j, Y', strtotime($expires_at));
    $subject = 'Your KFMMS License Key';
    $body = "Thank you for your KFMMS subscription purchase.\n\n" .
            "Company: {$company_name}\n" .
            "Plan: {$plan_name}\n" .
            "Seats: {$seats}\n" .
            "License Key: {$license_key}\n" .
            "Expires: {$expires_display}\n\n" .
            "Use this license key on the License Activation page: " . env('APP_URL', 'http://127.0.0.1:8000') . "/license_gate.php\n\n" .
            "If you need assistance, please contact support.\n";

    return send_system_email($recipient, $subject, $body);
}

/**
 * Get the company id for a given user.
 *
 * @param int $user_id
 * @return int|null
 */
function get_user_company_id($user_id) {
    global $connection, $db_available;

    if (!$db_available || empty($user_id)) {
        return null;
    }

    $query = 'SELECT company_id FROM users WHERE user_id = ? LIMIT 1';
    $stmt = $connection->prepare($query);
    if (!$stmt) {
        return null;
    }

    $stmt->bind_param('i', $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows === 0) {
        return null;
    }

    $row = $result->fetch_assoc();
    return !empty($row['company_id']) ? (int)$row['company_id'] : null;
}

/**
 * Find a company id using a user's email address.
 *
 * @param string $email
 * @return int|null
 */
function get_company_id_by_email($email) {
    global $connection, $db_available;

    if (!$db_available || !is_valid_email($email)) {
        return null;
    }

    $query = 'SELECT company_id FROM users WHERE email = ? LIMIT 1';
    $stmt = $connection->prepare($query);
    if (!$stmt) {
        return null;
    }

    $stmt->bind_param('s', $email);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows === 0) {
        return null;
    }

    $row = $result->fetch_assoc();
    return !empty($row['company_id']) ? (int)$row['company_id'] : null;
}

/**
 * Get a company record by id.
 *
 * @param int $company_id
 * @return array|null
 */
function get_company_by_id($company_id) {
    global $connection, $db_available;

    if (!$db_available || empty($company_id)) {
        return null;
    }

    $query = 'SELECT company_id, company_name FROM companies WHERE company_id = ? LIMIT 1';
    $stmt = $connection->prepare($query);
    if (!$stmt) {
        return null;
    }

    $stmt->bind_param('i', $company_id);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows === 0) {
        return null;
    }

    return $result->fetch_assoc();
}

/**
 * Get the currently active license for a company.
 *
 * @param int $company_id
 * @return array|null
 */
function get_active_company_license($company_id) {
    global $connection, $db_available;

    if (!$db_available || empty($company_id)) {
        return null;
    }

    $query = 'SELECT * FROM company_licenses WHERE company_id = ? AND is_active = 1 ORDER BY created_at DESC LIMIT 1';
    $stmt = $connection->prepare($query);
    if (!$stmt) {
        return null;
    }

    $stmt->bind_param('i', $company_id);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows === 0) {
        return null;
    }

    return $result->fetch_assoc();
}

/**
 * Get the license audit history for a company.
 *
 * @param int $company_id
 * @param int $limit
 * @return array
 */
function get_license_audit_history($company_id, $limit = 20) {
    global $connection, $db_available;

    if (!$db_available || empty($company_id)) {
        return [];
    }

    $query = 'SELECT * FROM license_audit_log WHERE company_id = ? ORDER BY timestamp DESC LIMIT ?';
    $stmt = $connection->prepare($query);
    if (!$stmt) {
        return [];
    }

    $stmt->bind_param('ii', $company_id, $limit);
    $stmt->execute();
    $result = $stmt->get_result();
    if (!$result) {
        return [];
    }

    $rows = [];
    while ($row = $result->fetch_assoc()) {
        $rows[] = $row;
    }

    return $rows;
}

/**
 * Calculate a license expiration timestamp for the requested plan.
 *
 * @param string $plan_key
 * @param int|null $startTimestamp
 * @return string|null
 */
function calculate_license_expiry($plan_key, $startTimestamp = null) {
    $startTimestamp = $startTimestamp ?: time();
    $planDurations = [
        'trial' => 30,
        'basic' => 30,
        'professional' => 30,
        'enterprise' => 30,
        'yearly' => 365,
        'permanent' => null,
    ];

    if (!array_key_exists($plan_key, $planDurations)) {
        $plan_key = 'basic';
    }

    if ($planDurations[$plan_key] === null) {
        return null;
    }

    return date('Y-m-d H:i:s', strtotime('+' . $planDurations[$plan_key] . ' days', $startTimestamp));
}

/**
 * Resolve a Stripe payment into a company id.
 *
 * @param array $metadata
 * @param string|null $customer_email
 * @return int|null
 */
function resolve_company_id_for_payment($metadata, $customer_email = null) {
    if (!empty($metadata['company_id']) && ctype_digit((string)$metadata['company_id'])) {
        return (int)$metadata['company_id'];
    }

    if (!empty($metadata['user_id']) && ctype_digit((string)$metadata['user_id'])) {
        return get_user_company_id((int)$metadata['user_id']);
    }

    if (!empty($customer_email) && is_valid_email($customer_email)) {
        return get_company_id_by_email($customer_email);
    }

    return null;
}

/**
 * Create or renew a company license after Stripe payment.
 *
 * @param int $company_id
 * @param string $plan_key
 * @param string|null $payment_reference
 * @param string|null $stripe_session_id
 * @param string|null $customer_email
 * @return array
 */
function create_company_license_from_payment($company_id, $plan_key, $payment_reference = null, $stripe_session_id = null, $customer_email = null) {
    global $connection, $db_available;

    if (!$db_available) {
        return ['success' => false, 'message' => 'Database not available'];
    }

    $plans = get_subscription_plans();
    if (!isset($plans[$plan_key])) {
        return ['success' => false, 'message' => 'Unknown subscription plan selected.'];
    }

    $company_id = (int)$company_id;
    $currentLicense = get_active_company_license($company_id);
    $startTimestamp = time();
    if (!empty($currentLicense['expires_at'])) {
        $existingExpiration = strtotime($currentLicense['expires_at']);
        if ($existingExpiration > $startTimestamp) {
            $startTimestamp = $existingExpiration;
        }
    }

    $expiresAt = calculate_license_expiry($plan_key, $startTimestamp);
    $licenseKey = generate_license_key();
    $paymentTerm = in_array($plan_key, ['yearly'], true) ? 'yearly' : 'monthly';
    $priceSeats = (int)$plans[$plan_key]['seats'];

    if ($currentLicense) {
        $deactivateStmt = $connection->prepare('UPDATE company_licenses SET is_active = 0 WHERE license_id = ?');
        if ($deactivateStmt) {
            $deactivateStmt->bind_param('i', $currentLicense['license_id']);
            $deactivateStmt->execute();
        }
    }

    if ($expiresAt === null) {
        $insertQuery = 'INSERT INTO company_licenses (company_id, license_key, purchased_seats, used_seats, license_type, payment_term, expires_at, is_active) VALUES (?, ?, ?, 0, ?, ?, NULL, 1)';
        $stmt = $connection->prepare($insertQuery);
        if ($stmt) {
            $stmt->bind_param('iisss', $company_id, $licenseKey, $priceSeats, $plan_key, $paymentTerm);
        }
    } else {
        $insertQuery = 'INSERT INTO company_licenses (company_id, license_key, purchased_seats, used_seats, license_type, payment_term, expires_at, is_active) VALUES (?, ?, ?, 0, ?, ?, ?, 1)';
        $stmt = $connection->prepare($insertQuery);
        if ($stmt) {
            $stmt->bind_param('iissss', $company_id, $licenseKey, $priceSeats, $plan_key, $paymentTerm, $expiresAt);
        }
    }

    if (!$stmt) {
        return ['success' => false, 'message' => 'Unable to prepare license creation statement.'];
    }

    if (!$stmt->execute()) {
        return ['success' => false, 'message' => 'Unable to save license record: ' . $stmt->error];
    }

    $licenseId = $connection->insert_id;
    $details = 'Stripe payment received';
    if (!empty($payment_reference)) {
        $details .= ' | ref=' . $payment_reference;
    }
    if (!empty($stripe_session_id)) {
        $details .= ' | session=' . $stripe_session_id;
    }
    if (!empty($customer_email)) {
        $details .= ' | email=' . $customer_email;
    }

    log_license_action($licenseId, null, 'license_created', null, $priceSeats, $details);
    $licenseRow = get_active_company_license($company_id);

    return [
        'success' => true,
        'message' => 'License record created successfully.',
        'license_key' => $licenseKey,
        'license' => $licenseRow,
        'company_id' => $company_id,
    ];
}

/**
 * Process a Stripe checkout session completed event.
 *
 * @param array $session
 * @return array
 */
function process_stripe_checkout_session_completed($session) {
    $metadata = $session['metadata'] ?? [];
    $planKey = trim($metadata['plan_key'] ?? '');
    $customerEmail = $session['customer_email'] ?? ($metadata['user_email'] ?? '');

    if (empty($planKey)) {
        return ['success' => false, 'message' => 'Stripe session missing plan metadata.'];
    }

    $companyId = resolve_company_id_for_payment($metadata, $customerEmail);
    if (empty($companyId)) {
        return ['success' => false, 'message' => 'Unable to resolve company from payment metadata.'];
    }

    $result = create_company_license_from_payment(
        $companyId,
        $planKey,
        $session['payment_intent'] ?? null,
        $session['id'] ?? null,
        $customerEmail
    );

    if (!$result['success']) {
        return $result;
    }

    $company = get_company_by_id($companyId);
    $planName = $metadata['product_name'] ?? ($planKey !== '' ? get_subscription_plans()[$planKey]['name'] ?? ucfirst($planKey) : 'Subscription');
    $emailSent = false;
    if (!empty($customerEmail)) {
        $emailSent = send_license_email(
            $customerEmail,
            $company['company_name'] ?? 'Your organization',
            $planName,
            $result['license_key'],
            $result['license']['expires_at'] ?? null,
            (int)$result['license']['purchased_seats']
        );
    }

    return [
        'success' => true,
        'message' => 'License processed' . ($emailSent ? ' and email notification sent.' : ' but notification email could not be delivered.'),
        'company_id' => $companyId,
    ];
}

/**
 * Process a Stripe invoice payment succeeded event.
 *
 * @param array $invoice
 * @return array
 */
function process_stripe_invoice_payment_succeeded($invoice) {
    $metadata = $invoice['metadata'] ?? [];
    $planKey = trim($metadata['plan_key'] ?? '');
    $subscriberEmail = $invoice['customer_email'] ?? ($metadata['user_email'] ?? '');

    if (empty($planKey)) {
        return ['success' => false, 'message' => 'Invoice missing plan metadata.'];
    }

    $companyId = resolve_company_id_for_payment($metadata, $subscriberEmail);
    if (empty($companyId)) {
        return ['success' => false, 'message' => 'Unable to resolve company from invoice metadata.'];
    }

    $result = create_company_license_from_payment(
        $companyId,
        $planKey,
        $invoice['id'] ?? null,
        $invoice['id'] ?? null,
        $subscriberEmail
    );

    if (!$result['success']) {
        return $result;
    }

    $company = get_company_by_id($companyId);
    $planName = $metadata['product_name'] ?? ($planKey !== '' ? get_subscription_plans()[$planKey]['name'] ?? ucfirst($planKey) : 'Subscription');
    $emailSent = false;
    if (!empty($subscriberEmail)) {
        $emailSent = send_license_email(
            $subscriberEmail,
            $company['company_name'] ?? 'Your organization',
            $planName,
            $result['license_key'],
            $result['license']['expires_at'] ?? null,
            (int)$result['license']['purchased_seats']
        );
    }

    return [
        'success' => true,
        'message' => 'Invoice processed' . ($emailSent ? ' and email notification sent.' : ' but notification email could not be delivered.'),
        'company_id' => $companyId,
    ];
}

/**
 * Verify Stripe webhook payload signature.
 *
 * @param string $payload
 * @param string $signatureHeader
 * @param string $secret
 * @param int $tolerance
 * @return array|null
 */
function verify_stripe_signature($payload, $signatureHeader, $secret, $tolerance = 300) {
    if (empty($payload) || empty($signatureHeader) || empty($secret)) {
        return null;
    }

    $parts = explode(',', $signatureHeader);
    $timestamp = null;
    $signatures = [];

    foreach ($parts as $part) {
        [$key, $value] = explode('=', trim($part), 2) + [null, null];
        if ($key === 't') {
            $timestamp = $value;
        } elseif ($key === 'v1') {
            $signatures[] = $value;
        }
    }

    if (empty($timestamp) || empty($signatures)) {
        return null;
    }

    $signedPayload = $timestamp . '.' . $payload;
    $valid = false;
    foreach ($signatures as $signature) {
        if (hash_equals(hash_hmac('sha256', $signedPayload, $secret), $signature)) {
            $valid = true;
            break;
        }
    }

    if (!$valid) {
        return null;
    }

    if (abs(time() - (int)$timestamp) > $tolerance) {
        return null;
    }

    $event = json_decode($payload, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        return null;
    }

    return $event;
}

/**
 * Returns the onboarding steps for new subscribers.
 *
 * @return array
 */
function get_customer_onboarding_steps() {
    return [
        ['title' => 'Choose a plan', 'description' => 'Select the subscription tier that matches your team size and feature requirements.'],
        ['title' => 'Complete secure checkout', 'description' => 'Pay securely using the integrated payment provider and receive a license key by email.'],
        ['title' => 'Activate your license', 'description' => 'Enter the license key on the gate page and log in to finish activation.'],
        ['title' => 'Start using KFMMS', 'description' => 'Access your dashboard, invite users, and begin managing maintenance workflows.'],
    ];
}

/**
 * Build a Renewal callout for an expired or soon-to-expire license.
 *
 * @param array|null $license
 * @return string|null
 */
function get_license_renewal_message($license) {
    if (empty($license) || empty($license['expires_at'])) {
        return null;
    }

    $expires_at = strtotime($license['expires_at']);
    $now = time();
    if ($expires_at < $now) {
        return 'Your license expired on ' . date('M j, Y', $expires_at) . '. Renew now to restore access.';
    }

    $daysLeft = (int)ceil(($expires_at - $now) / 86400);
    if ($daysLeft <= 14) {
        return 'Your license expires in ' . $daysLeft . ' days. Renew now to avoid interruption.';
    }

    return null;
}

?>