<?php
/**
 * API Authentication Handler
 * 
 * Handles API key and Bearer token authentication
 * Also manages API client registration and rate limiting
 */

class APIAuth {
    
    /**
     * Authenticate API request
     * Supports: Bearer token or X-API-Key header
     */
    public static function authenticate() {
        $c = $GLOBALS['connection'];
        
        // Get authorization header
        $auth_header = self::getAuthHeader();
        
        if (empty($auth_header)) {
            return ['success' => false, 'error' => 'Missing authentication', 'code' => 401];
        }

        // Check for Bearer token
        if (strpos($auth_header, 'Bearer ') === 0) {
            $token = substr($auth_header, 7);
            return self::authenticateToken($c, $token);
        }

        // Check for API Key
        if (strpos($auth_header, 'ApiKey ') === 0) {
            $key = substr($auth_header, 7);
            return self::authenticateAPIKey($c, $key);
        }

        // Check X-API-Key header
        $api_key = $_SERVER['HTTP_X_API_KEY'] ?? null;
        if (!empty($api_key)) {
            return self::authenticateAPIKey($c, $api_key);
        }

        return ['success' => false, 'error' => 'Invalid authentication method', 'code' => 401];
    }

    private static function getAuthHeader() {
        if (isset($_SERVER['HTTP_AUTHORIZATION'])) {
            return $_SERVER['HTTP_AUTHORIZATION'];
        }
        
        if (isset($_SERVER['REDIRECT_HTTP_AUTHORIZATION'])) {
            return $_SERVER['REDIRECT_HTTP_AUTHORIZATION'];
        }

        // Apache workaround
        if (function_exists('getallheaders')) {
            $headers = getallheaders();
            if (isset($headers['Authorization'])) {
                return $headers['Authorization'];
            }
        }

        return null;
    }

    private static function authenticateToken($c, $token) {
        $result = mysqli_query($c, 
            "SELECT id, user_id, client_name, expires_at 
             FROM api_tokens 
             WHERE token='" . mysqli_real_escape_string($c, $token) . "' 
             AND active=1 
             AND (expires_at IS NULL OR expires_at > NOW())
             LIMIT 1"
        );

        if (!$result || mysqli_num_rows($result) === 0) {
            return ['success' => false, 'error' => 'Invalid or expired token', 'code' => 401];
        }

        $token_data = mysqli_fetch_assoc($result);
        
        // Update last used
        mysqli_query($c, 
            "UPDATE api_tokens SET last_used=NOW() WHERE id=" . (int)$token_data['id']
        );

        return [
            'success' => true,
            'user_id' => $token_data['user_id'],
            'client' => $token_data['client_name'],
            'code' => 200
        ];
    }

    private static function authenticateAPIKey($c, $api_key) {
        $result = mysqli_query($c,
            "SELECT id, user_id, client_name, active 
             FROM api_clients 
             WHERE api_key='" . mysqli_real_escape_string($c, $api_key) . "' 
             AND active=1
             LIMIT 1"
        );

        if (!$result || mysqli_num_rows($result) === 0) {
            return ['success' => false, 'error' => 'Invalid API key', 'code' => 401];
        }

        $client = mysqli_fetch_assoc($result);
        
        // Check rate limit
        $rate_limit = self::checkRateLimit($c, $client['id']);
        if (!$rate_limit['allowed']) {
            return ['success' => false, 'error' => 'Rate limit exceeded', 'code' => 429];
        }

        // Update last used
        mysqli_query($c, 
            "UPDATE api_clients SET last_used=NOW() WHERE id=" . (int)$client['id']
        );

        return [
            'success' => true,
            'user_id' => $client['user_id'],
            'client' => $client['client_name'],
            'code' => 200
        ];
    }

    private static function checkRateLimit($c, $client_id) {
        // Get rate limit config (e.g., 1000 requests per hour)
        $limit_per_hour = 1000;
        
        // Count requests in last hour
        $result = mysqli_query($c,
            "SELECT COUNT(*) as count FROM api_logs 
             WHERE client_id=" . (int)$client_id . " 
             AND created_at > DATE_SUB(NOW(), INTERVAL 1 HOUR)"
        );
        
        $row = mysqli_fetch_assoc($result);
        
        if ($row['count'] >= $limit_per_hour) {
            return ['allowed' => false, 'remaining' => 0];
        }

        return ['allowed' => true, 'remaining' => $limit_per_hour - $row['count']];
    }

    /**
     * Create a new API token for a user
     */
    public static function createToken($c, $user_id, $client_name, $expires_days = 365) {
        $token = bin2hex(random_bytes(32));
        $expires = date('Y-m-d H:i:s', strtotime("+$expires_days days"));

        $insert = "INSERT INTO api_tokens 
                   (user_id, token, client_name, expires_at, created_at) 
                   VALUES 
                   (" . (int)$user_id . ", 
                    '" . mysqli_real_escape_string($c, $token) . "',
                    '" . mysqli_real_escape_string($c, $client_name) . "',
                    '$expires',
                    NOW())";

        if (mysqli_query($c, $insert)) {
            return ['success' => true, 'token' => $token];
        }

        return ['success' => false, 'error' => mysqli_error($c)];
    }

    /**
     * Register a new API client
     */
    public static function registerClient($c, $user_id, $client_name, $redirect_uri = null) {
        $api_key = bin2hex(random_bytes(16));
        $api_secret = bin2hex(random_bytes(32));

        $insert = "INSERT INTO api_clients 
                   (user_id, client_name, api_key, api_secret, redirect_uri, created_at) 
                   VALUES 
                   (" . (int)$user_id . ", 
                    '" . mysqli_real_escape_string($c, $client_name) . "',
                    '" . mysqli_real_escape_string($c, $api_key) . "',
                    '" . mysqli_real_escape_string($c, $api_secret) . "',
                    " . (!empty($redirect_uri) ? "'" . mysqli_real_escape_string($c, $redirect_uri) . "'" : "NULL") . ",
                    NOW())";

        if (mysqli_query($c, $insert)) {
            return [
                'success' => true,
                'api_key' => $api_key,
                'api_secret' => $api_secret
            ];
        }

        return ['success' => false, 'error' => mysqli_error($c)];
    }
}

?>
