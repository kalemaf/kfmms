<?php
/**
 * Tenant Middleware - Enforces Multi-Tenant Data Isolation
 * 
 * This middleware ensures that:
 * 1. Every request is tied to a specific company (tenant)
 * 2. Users can only access data from their company
 * 3. No data leakage between companies
 * 4. Tenant context is available throughout the application
 */

class TenantMiddleware {
    
    /**
     * Get the current tenant ID from session
     * 
     * @return int|null
     * @throws Exception if user is not authenticated
     */
    public static function getTenantId() {
        if (session_status() === PHP_SESSION_NONE) {
            @session_start();
        }
        
        if (!isset($_SESSION['tenant_id']) || $_SESSION['tenant_id'] <= 0) {
            http_response_code(401);
            header('Content-Type: application/json');
            echo json_encode(['error' => 'Unauthorized: No valid tenant context']);
            exit;
        }
        
        return (int)$_SESSION['tenant_id'];
    }
    
    /**
     * Get current user ID
     * 
     * @return int
     * @throws Exception if user is not authenticated
     */
    public static function getUserId() {
        if (!isset($_SESSION['user_id']) || $_SESSION['user_id'] <= 0) {
            http_response_code(401);
            header('Content-Type: application/json');
            echo json_encode(['error' => 'Unauthorized: User not authenticated']);
            exit;
        }
        
        return (int)$_SESSION['user_id'];
    }
    
    /**
     * Get current user role
     * 
     * @return string
     */
    public static function getRole() {
        return $_SESSION['role'] ?? 'guest';
    }
    
    /**
     * Check if user has admin role within their tenant
     * 
     * @return bool
     */
    public static function isAdmin() {
        return self::getRole() === 'admin';
    }
    
    /**
     * Check if user has manager role
     * 
     * @return bool
     */
    public static function isManager() {
        $role = self::getRole();
        return in_array($role, ['admin', 'manager']);
    }
    
    /**
     * Verify tenant ownership of a resource
     * 
     * This must be called in every controller that retrieves data
     * to ensure users don't access other companies' data
     * 
     * @param int $resource_tenant_id The tenant_id of the resource being accessed
     * @return bool
     */
    public static function verifyTenantAccess($resource_tenant_id) {
        $current_tenant_id = self::getTenantId();
        
        if ((int)$resource_tenant_id !== $current_tenant_id) {
            http_response_code(403);
            header('Content-Type: application/json');
            echo json_encode(['error' => 'Forbidden: Access denied to this resource']);
            exit;
        }
        
        return true;
    }
    
    /**
     * Initialize tenant context on login
     * 
     * @param int $user_id
     * @param int $tenant_id
     * @param string $role
     * @return bool
     */
    public static function initializeTenantContext($user_id, $tenant_id, $role = 'user') {
        if (session_status() === PHP_SESSION_NONE) {
            @session_start();
        }
        
        $_SESSION['user_id'] = (int)$user_id;
        $_SESSION['tenant_id'] = (int)$tenant_id;
        $_SESSION['role'] = $role;
        $_SESSION['last_activity'] = time();
        
        return true;
    }
    
    /**
     * Destroy tenant context on logout
     * 
     * @return void
     */
    public static function destroyTenantContext() {
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_destroy();
        }
    }
}

// Global helper function for quick access
function tenant() {
    return TenantMiddleware::getTenantId();
}

function user() {
    return TenantMiddleware::getUserId();
}

function userRole() {
    return TenantMiddleware::getRole();
}

function isAdmin() {
    return TenantMiddleware::isAdmin();
}

function verifyTenant($tenant_id) {
    return TenantMiddleware::verifyTenantAccess($tenant_id);
}
?>
