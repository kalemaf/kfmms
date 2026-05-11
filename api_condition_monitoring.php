<?php
/**
 * API: Condition Monitoring Data Collection
 * 
 * Allows IoT sensors, manual data entry, or external systems to submit
 * real-time condition monitoring data for predictive analysis.
 * 
 * Endpoint: api_condition_monitoring.php
 * Method: POST
 * Content-Type: application/json
 */

require_once 'config.inc.php';
require_once 'common.inc.php';
require_once 'libraries/predictive_maintenance.php';

header('Content-Type: application/json');

// Response helper
function json_response($success, $message, $data = null, $http_code = 200) {
    http_response_code($http_code);
    echo json_encode([
        'success' => $success,
        'message' => $message,
        'data' => $data,
        'timestamp' => date('Y-m-d H:i:s')
    ]);
    exit;
}

// Validate API key and authentication
function validate_api_request() {
    $headers = getallheaders();
    
    // Check for Bearer token
    if (isset($headers['Authorization'])) {
        $auth = $headers['Authorization'];
        if (preg_match('/Bearer\s+(.+)/i', $auth, $matches)) {
            $token = $matches[1];
            // Validate token (simplified - in production, use JWT or OAuth)
            return validate_api_token($token);
        }
    }
    
    // Fallback to session-based auth
    if (isset($_SESSION['user_id'])) {
        return true;
    }
    
    json_response(false, 'Unauthorized - invalid or missing credentials', null, 401);
}

function validate_api_token($token) {
    // Simplified validation - in production use proper JWT/OAuth
    // For now, check against hardcoded or database tokens
    return strlen($token) > 10; // Basic validation
}

// Main request handler
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(false, 'Only POST requests are allowed', null, 405);
}

// Validate authentication
validate_api_request();

// Parse JSON payload
$json = file_get_contents('php://input');
$data = json_decode($json, true);

if (!$data) {
    json_response(false, 'Invalid or empty JSON payload', null, 400);
}

$tenant_id = $_SESSION['tenant_id'] ?? (isset($data['tenant_id']) ? $data['tenant_id'] : 1);
$technician_id = $_SESSION['user_id'] ?? (isset($data['technician_id']) ? $data['technician_id'] : null);

// ============ BATCH DATA SUBMISSION ============
if (isset($data['batch']) && is_array($data['batch'])) {
    $results = [];
    $errors = [];
    
    foreach ($data['batch'] as $index => $record) {
        try {
            $result = submit_condition_data($record, $tenant_id, $technician_id);
            if ($result['success']) {
                $results[] = [
                    'index' => $index,
                    'status' => 'success',
                    'id' => $result['id']
                ];
            } else {
                $errors[] = [
                    'index' => $index,
                    'status' => 'error',
                    'message' => $result['message']
                ];
            }
        } catch (Exception $e) {
            $errors[] = [
                'index' => $index,
                'status' => 'error',
                'message' => $e->getMessage()
            ];
        }
    }
    
    $success_count = count($results);
    $error_count = count($errors);
    
    json_response(
        $error_count === 0,
        "$success_count records submitted, $error_count errors",
        [
            'successful' => $results,
            'failed' => $errors
        ]
    );
}

// ============ SINGLE RECORD SUBMISSION ============
try {
    $result = submit_condition_data($data, $tenant_id, $technician_id);
    
    if ($result['success']) {
        json_response(
            true,
            'Condition data recorded successfully',
            [
                'id' => $result['id'],
                'equipment_id' => $data['equipment_id'],
                'parameter_type' => $data['parameter_type']
            ],
            201
        );
    } else {
        json_response(false, $result['message'], null, 400);
    }
} catch (Exception $e) {
    json_response(false, 'Error processing request: ' . $e->getMessage(), null, 500);
}

/**
 * Submit condition monitoring data
 */
function submit_condition_data($data, $tenant_id, $technician_id) {
    global $connection;
    
    // Validate required fields
    $required = ['equipment_id', 'parameter_type', 'measured_value'];
    foreach ($required as $field) {
        if (!isset($data[$field]) || $data[$field] === '') {
            return ['success' => false, 'message' => "Missing required field: $field"];
        }
    }
    
    $equipment_id = (int)$data['equipment_id'];
    $parameter_type = trim($data['parameter_type']);
    $measured_value = (float)$data['measured_value'];
    $unit = $data['unit'] ?? null;
    $threshold_normal = $data['threshold_normal'] ?? null;
    $threshold_warning = $data['threshold_warning'] ?? null;
    $threshold_critical = $data['threshold_critical'] ?? null;
    $notes = $data['notes'] ?? null;
    
    // Determine status based on thresholds
    $status = 'Normal';
    if ($threshold_critical && $measured_value >= $threshold_critical) {
        $status = 'Critical';
    } elseif ($threshold_warning && $measured_value >= $threshold_warning) {
        $status = 'Warning';
    }
    
    // Calculate trend
    $trend = calculate_parameter_trend($equipment_id, $parameter_type);
    
    try {
        $stmt = $connection->prepare("
            INSERT INTO condition_monitoring 
            (equipment_id, parameter_type, measured_value, unit, 
             threshold_normal, threshold_warning, threshold_critical, 
             status, trend_indicator, notes, technician_id, tenant_id)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        
        $stmt->bindParam(1, $equipment_id, PDO::PARAM_INT);
        $stmt->bindParam(2, $parameter_type, PDO::PARAM_STR);
        $stmt->bindParam(3, $measured_value, PDO::PARAM_STR);
        $stmt->bindParam(4, $unit, PDO::PARAM_STR);
        $stmt->bindParam(5, $threshold_normal, PDO::PARAM_STR);
        $stmt->bindParam(6, $threshold_warning, PDO::PARAM_STR);
        $stmt->bindParam(7, $threshold_critical, PDO::PARAM_STR);
        $stmt->bindParam(8, $status, PDO::PARAM_STR);
        $stmt->bindParam(9, $trend, PDO::PARAM_STR);
        $stmt->bindParam(10, $notes, PDO::PARAM_STR);
        $stmt->bindParam(11, $technician_id, PDO::PARAM_INT);
        $stmt->bindParam(12, $tenant_id, PDO::PARAM_INT);
        
        if ($stmt->execute()) {
            $id = $connection->lastInsertId();
            
            // Generate alert if status is warning or critical
            if ($status !== 'Normal') {
                $confidence = $status === 'Critical' ? 0.95 : 0.75;
                create_predictive_alert(
                    $equipment_id,
                    null,
                    'condition_anomaly',
                    $status,
                    "$parameter_type - $status Status",
                    "$parameter_type reading: $measured_value $unit (threshold: $threshold_critical)",
                    "Review condition monitoring data and schedule inspection",
                    $confidence
                );
            }
            
            return ['success' => true, 'id' => $id];
        } else {
            return ['success' => false, 'message' => 'Failed to insert condition data'];
        }
    } catch (Exception $e) {
        return ['success' => false, 'message' => $e->getMessage()];
    }
}

/**
 * Calculate trend (increasing, decreasing, stable)
 */
function calculate_parameter_trend($equipment_id, $parameter_type) {
    global $connection;
    
    // Get last 5 measurements
    $stmt = $connection->prepare("
        SELECT measured_value FROM condition_monitoring
        WHERE equipment_id = ? AND parameter_type = ?
        ORDER BY recorded_at DESC LIMIT 5
    ");
    
    $stmt->bindParam(1, $equipment_id, PDO::PARAM_INT);
    $stmt->bindParam(2, $parameter_type, PDO::PARAM_STR);
    $stmt->execute();
    
    $values = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    if (count($values) < 2) {
        return 'Stable';
    }
    
    // Calculate trend
    $first = end($values);
    $last = reset($values);
    $change = (($last - $first) / $first) * 100;
    
    if ($change > 5) return 'Increasing';
    if ($change < -5) return 'Decreasing';
    return 'Stable';
}

?>
