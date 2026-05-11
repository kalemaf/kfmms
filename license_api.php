<?php
/**
 * License Management API
 * Handles company license activation, deactivation, and status updates
 */

require_once 'config.inc.php';
require_once 'common.inc.php';

// Only set headers in web context (not CLI)
if (php_sapi_name() !== 'cli') {
    header('Content-Type: application/json; charset=utf-8');
}

// Check database availability
if (!$db_available || $connection === null) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Database not available']);
    exit;
}

// Check authentication
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

// Check super admin access
$user_role = strtolower($_SESSION['role'] ?? '');
$is_admin = in_array($user_role, ['developer', 'admin']);

if (!$is_admin) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Forbidden - Admin access required']);
    exit;
}

$action = $_REQUEST['action'] ?? '';
$response = ['success' => false, 'message' => '', 'data' => null];

// Add to debug log
error_log("License API: action=$action");

try {
    switch ($action) {
        case 'activate':
            $company_id = (int)($_POST['company_id'] ?? $_GET['company_id'] ?? 0);
            
            if ($company_id <= 0) {
                $response['error'] = 'Invalid company ID';
                break;
            }
            
            // Verify company exists
            $check_query = "SELECT company_id FROM companies WHERE company_id = ?";
            $stmt = $connection->prepare($check_query);
            if ($stmt === false) {
                throw new Exception('Prepare failed for check company: ' . ($db_type === 'sqlite' ? print_r($connection->errorInfo(), true) : $connection->error));
            }
            if ($db_type === 'sqlite') {
                $stmt->bindParam(1, $company_id, PDO::PARAM_INT);
                $stmt->execute();
                $company_exists = $stmt->fetch(PDO::FETCH_ASSOC) !== false;
            } else {
                $stmt->bind_param('i', $company_id);
                $stmt->execute();
                $result = $stmt->get_result();
                $company_exists = $result->num_rows > 0;
                $stmt->close();
            }
            
            if (!$company_exists) {
                $response['error'] = 'Company not found';
                break;
            }
            
            // Check if system_control exists
            $ctrl_query = "SELECT control_id FROM system_control WHERE company_id = ?";
            $stmt = $connection->prepare($ctrl_query);
            if ($stmt === false) {
                throw new Exception('Prepare failed for check system_control: ' . ($db_type === 'sqlite' ? print_r($connection->errorInfo(), true) : $connection->error));
            }
            if ($db_type === 'sqlite') {
                $stmt->bindParam(1, $company_id, PDO::PARAM_INT);
                $stmt->execute();
                $ctrl_exists = $stmt->fetch(PDO::FETCH_ASSOC) !== false;
            } else {
                $stmt->bind_param('i', $company_id);
                $stmt->execute();
                $result = $stmt->get_result();
                $ctrl_exists = $result->num_rows > 0;
                $stmt->close();
            }
            
            // Update or create system_control
            if ($ctrl_exists) {
                $timestamp = get_current_timestamp_sql();
                $update_query = "UPDATE system_control SET system_activated = ?, system_locked = ?, lock_reason = NULL, activation_date = {$timestamp} WHERE company_id = ?";
                $stmt = $connection->prepare($update_query);
                if ($stmt === false) {
                    throw new Exception('Prepare failed for update system_control: ' . ($db_type === 'sqlite' ? print_r($connection->errorInfo(), true) : $connection->error));
                }
                if ($db_type === 'sqlite') {
                    $true = 1;
                    $false = 0;
                    $stmt->bindParam(1, $true, PDO::PARAM_INT);
                    $stmt->bindParam(2, $false, PDO::PARAM_INT);
                    $stmt->bindParam(3, $company_id, PDO::PARAM_INT);
                } else {
                    $true = 1;
                    $false = 0;
                    $stmt->bind_param('iii', $true, $false, $company_id);
                }
            } else {
                $timestamp = get_current_timestamp_sql();
                $insert_query = "INSERT INTO system_control (company_id, system_activated, system_locked, activation_date) VALUES (?, ?, ?, {$timestamp})";
                $stmt = $connection->prepare($insert_query);
                if ($stmt === false) {
                    throw new Exception('Prepare failed for insert system_control: ' . ($db_type === 'sqlite' ? print_r($connection->errorInfo(), true) : $connection->error));
                }
                if ($db_type === 'sqlite') {
                    $true = 1;
                    $false = 0;
                    $stmt->bindParam(1, $company_id, PDO::PARAM_INT);
                    $stmt->bindParam(2, $true, PDO::PARAM_INT);
                    $stmt->bindParam(3, $false, PDO::PARAM_INT);
                } else {
                    $true = 1;
                    $false = 0;
                    $stmt->bind_param('iii', $company_id, $true, $false);
                }
            }
            
            if ($stmt->execute()) {
                // Also activate license if it exists
                $lic_update = "UPDATE company_licenses SET is_active = 1 WHERE company_id = ?";
                if ($db_type === 'sqlite') {
                    $lic_stmt = $connection->prepare($lic_update);
                    if ($lic_stmt === false) {
                        throw new Exception('Prepare failed for update company_licenses: ' . print_r($connection->errorInfo(), true));
                    }
                    if ($lic_stmt) {
                        $lic_stmt->bindParam(1, $company_id, PDO::PARAM_INT);
                        $lic_stmt->execute();
                    }
                } else {
                    $lic_stmt = $connection->prepare($lic_update);
                    if ($lic_stmt === false) {
                        throw new Exception('Prepare failed for update company_licenses: ' . $connection->error);
                    }
                    if ($lic_stmt) {
                        $lic_stmt->bind_param('i', $company_id);
                        $lic_stmt->execute();
                        $lic_stmt->close();
                    }
                }
                
                // Log action
                try {
                    log_system_activation($company_id, $_SESSION['user_id'] ?? 0, 'system_activated', 'System activated via admin_roles');
                } catch (Exception $log_err) {
                    // Log errors don't block activation
                }
                
                $response['success'] = true;
                $response['message'] = 'System activated successfully';
            } else {
                $error_msg = $db_type === 'sqlite' ? print_r($stmt->errorInfo(), true) : $stmt->error;
                $response['error'] = 'Failed to execute activation: ' . $error_msg;
            }
            break;
            
        case 'deactivate':
            $company_id = (int)($_POST['company_id'] ?? $_GET['company_id'] ?? 0);
            $lock_reason = $_POST['lock_reason'] ?? 'Administrative deactivation';
            
            if ($company_id <= 0) {
                $response['error'] = 'Invalid company ID';
                break;
            }
            
            $update_query = "UPDATE system_control SET system_activated = ?, system_locked = ?, lock_reason = ? WHERE company_id = ?";
            $stmt = $connection->prepare($update_query);
            if ($stmt === false) {
                throw new Exception('Prepare failed for deactivate: ' . ($db_type === 'sqlite' ? print_r($connection->errorInfo(), true) : $connection->error));
            }
            if ($db_type === 'sqlite') {
                $false = 0;
                $true = 1;
                $stmt->bindParam(1, $false, PDO::PARAM_INT);
                $stmt->bindParam(2, $true, PDO::PARAM_INT);
                $stmt->bindParam(3, $lock_reason, PDO::PARAM_STR);
                $stmt->bindParam(4, $company_id, PDO::PARAM_INT);
            } else {
                $false = 0;
                $true = 1;
                $stmt->bind_param('iisi', $false, $true, $lock_reason, $company_id);
            }
            
            if ($stmt->execute()) {
                // Log action
                try {
                    log_system_activation($company_id, $_SESSION['user_id'] ?? 0, 'system_deactivated', $lock_reason);
                } catch (Exception $log_err) {
                    // Log errors don't block deactivation
                }
                $response['success'] = true;
                $response['message'] = 'System deactivated successfully';
            } else {
                $response['error'] = 'Failed to deactivate system';
            }
            break;
            
        case 'get_status':
            $company_id = (int)($_GET['company_id'] ?? 0);
            
            if ($company_id <= 0) {
                $response['error'] = 'Invalid company ID';
                break;
            }
            
            $status_query = "SELECT sc.system_activated, sc.system_locked, sc.subscription_status, 
                                   cl.is_active as license_active, cl.license_key
                            FROM system_control sc
                            LEFT JOIN company_licenses cl ON sc.company_id = cl.company_id AND cl.is_active = 1
                            WHERE sc.company_id = ?";
            
            $stmt = $connection->prepare($status_query);
            if ($stmt === false) {
                throw new Exception('Prepare failed for get_status: ' . ($db_type === 'sqlite' ? print_r($connection->errorInfo(), true) : $connection->error));
            }
            if ($db_type === 'sqlite') {
                $stmt->bindParam(1, $company_id, PDO::PARAM_INT);
                $stmt->execute();
                $status = $stmt->fetch(PDO::FETCH_ASSOC);
            } else {
                $stmt->bind_param('i', $company_id);
                $stmt->execute();
                $result = $stmt->get_result();
                $status = $result->fetch_assoc();
                $stmt->close();
            }
            
            if ($status) {
                $response['success'] = true;
                $response['data'] = [
                    'activated' => (bool)$status['system_activated'],
                    'locked' => (bool)$status['system_locked'],
                    'subscription' => $status['subscription_status'],
                    'license_active' => (bool)$status['license_active'],
                    'license_key' => $status['license_key']
                ];
            } else {
                $response['error'] = 'System control not found';
            }
            break;
            
        default:
            $response['error'] = 'Invalid action';
    }
} catch (Exception $e) {
    $response['error'] = 'Error: ' . $e->getMessage();
    http_response_code(400);
}

// Set HTTP status code based on success
if (!isset($http_status_set)) {
    http_response_code($response['success'] ? 200 : 400);
}

// Output JSON response
$json = json_encode($response);
if ($json === false) {
    $json = json_encode(['success' => false, 'error' => 'Failed to encode response']);
}
if ($json === false) {
    $json = '{"success":false,"error":"JSON encoding error"}';
}
echo $json;

/**
 * Log system activation/deactivation action for audit trail
 */
function log_system_activation($company_id, $user_id, $action, $details = '') {
    global $connection, $db_type;
    
    try {
        $timestamp = get_current_timestamp_sql();
        $log_query = "INSERT INTO license_actions (license_id, user_id, action, action_details, action_date) 
                     SELECT license_id, ?, ?, ?, {$timestamp} FROM company_licenses WHERE company_id = ? LIMIT 1";
        
        $stmt = $connection->prepare($log_query);
        if ($stmt === false) {
            throw new Exception('Prepare failed for log: ' . ($db_type === 'sqlite' ? print_r($connection->errorInfo(), true) : $connection->error));
        }
        if ($db_type === 'sqlite') {
            $stmt->bindParam(1, $user_id, PDO::PARAM_INT);
            $stmt->bindParam(2, $action, PDO::PARAM_STR);
            $stmt->bindParam(3, $details, PDO::PARAM_STR);
            $stmt->bindParam(4, $company_id, PDO::PARAM_INT);
            $stmt->execute();
        } else {
            $stmt->bind_param('issi', $user_id, $action, $details, $company_id);
            $stmt->execute();
            $stmt->close();
        }
    } catch (Exception $e) {
        // Silently fail logging
    }
}
?>
