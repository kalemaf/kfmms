<?php
/**
 * CMMS REST API - Main Router
 * 
 * Base API endpoint for all third-party integrations
 * 
 * Usage:
 *   GET/POST /api/v1/work_orders
 *   GET/POST /api/v1/inventory
 *   GET/POST /api/v1/equipment
 *   etc.
 * 
 * Authentication: Bearer token in Authorization header
 * Content-Type: application/json
 */

// CORS headers
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-API-Key');
header('Content-Type: application/json; charset=UTF-8');

// Handle preflight
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

require_once 'config.inc.php';
require_once 'api_auth.php';
require_once 'api_response.php';

// Initialize database connection
$c = $GLOBALS['connection'] ?? null;
if (!$c) {
    APIResponse::error('Database connection failed', 500);
    exit;
}

// Parse request
$method = $_SERVER['REQUEST_METHOD'];
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$path = str_replace('/api/v1/', '', $path);
$path = trim($path, '/');

// Get request body
$body = json_decode(file_get_contents('php://input'), true);
$query = $_GET;

// Authenticate request
$auth = APIAuth::authenticate();
if (!$auth['success']) {
    APIResponse::error($auth['error'], $auth['code']);
    exit;
}

$api_user_id = $auth['user_id'];
$api_client = $auth['client'];

// Route to appropriate handler
$routes = [
    'work_orders' => 'api_work_orders.php',
    'equipment' => 'api_equipment.php',
    'inventory' => 'api_inventory.php',
    'maintenance' => 'api_maintenance.php',
    'users' => 'api_users.php',
    'assets' => 'api_assets.php',
    'vendors' => 'api_vendors.php',
];

$resource = explode('/', $path)[0];

if (!array_key_exists($resource, $routes)) {
    APIResponse::error('Unknown resource: ' . htmlspecialchars($resource), 404);
    exit;
}

// Load and execute handler
$handler_file = 'api_handlers/' . $routes[$resource];
if (!file_exists($handler_file)) {
    APIResponse::error('Handler not found', 500);
    exit;
}

require_once $handler_file;

// Execute handler (handlers should define these functions)
try {
    switch ($method) {
        case 'GET':
            if (function_exists('api_get')) {
                api_get($c, $path, $query, $api_user_id, $api_client);
            } else {
                APIResponse::error('GET not supported for this resource', 405);
            }
            break;
        
        case 'POST':
            if (function_exists('api_post')) {
                api_post($c, $path, $body, $api_user_id, $api_client);
            } else {
                APIResponse::error('POST not supported for this resource', 405);
            }
            break;
        
        case 'PUT':
            if (function_exists('api_put')) {
                api_put($c, $path, $body, $api_user_id, $api_client);
            } else {
                APIResponse::error('PUT not supported for this resource', 405);
            }
            break;
        
        case 'DELETE':
            if (function_exists('api_delete')) {
                api_delete($c, $path, $api_user_id, $api_client);
            } else {
                APIResponse::error('DELETE not supported for this resource', 405);
            }
            break;
        
        default:
            APIResponse::error('Unsupported HTTP method', 405);
    }
} catch (Exception $e) {
    APIResponse::error('Server error: ' . $e->getMessage(), 500);
}

mysqli_close($c);
?>
