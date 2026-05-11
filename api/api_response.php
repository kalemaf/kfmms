<?php
/**
 * API Response Helper
 * 
 * Standardizes all API responses
 */

class APIResponse {
    
    /**
     * Success response
     */
    public static function success($data = null, $message = 'Success', $code = 200) {
        http_response_code($code);
        echo json_encode([
            'success' => true,
            'message' => $message,
            'data' => $data,
            'timestamp' => date('Y-m-d H:i:s')
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        exit;
    }

    /**
     * Error response
     */
    public static function error($message, $code = 400, $details = null) {
        http_response_code($code);
        $response = [
            'success' => false,
            'error' => $message,
            'code' => $code,
            'timestamp' => date('Y-m-d H:i:s')
        ];
        
        if (!empty($details)) {
            $response['details'] = $details;
        }

        echo json_encode($response, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        exit;
    }

    /**
     * Paginated response
     */
    public static function paginated($data, $page, $per_page, $total, $message = 'Success') {
        $total_pages = ceil($total / $per_page);
        
        http_response_code(200);
        echo json_encode([
            'success' => true,
            'message' => $message,
            'data' => $data,
            'pagination' => [
                'page' => (int)$page,
                'per_page' => (int)$per_page,
                'total' => (int)$total,
                'total_pages' => (int)$total_pages,
                'has_next' => $page < $total_pages,
                'has_prev' => $page > 1
            ],
            'timestamp' => date('Y-m-d H:i:s')
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        exit;
    }

    /**
     * Created response (201)
     */
    public static function created($data, $location = null) {
        http_response_code(201);
        $response = [
            'success' => true,
            'message' => 'Resource created',
            'data' => $data,
            'timestamp' => date('Y-m-d H:i:s')
        ];

        if (!empty($location)) {
            header('Location: ' . $location);
        }

        echo json_encode($response, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        exit;
    }

    /**
     * No content response (204)
     */
    public static function noContent() {
        http_response_code(204);
        exit;
    }

    /**
     * Validation error response
     */
    public static function validationError($errors) {
        http_response_code(422);
        echo json_encode([
            'success' => false,
            'message' => 'Validation error',
            'code' => 422,
            'errors' => $errors,
            'timestamp' => date('Y-m-d H:i:s')
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        exit;
    }
}

?>
