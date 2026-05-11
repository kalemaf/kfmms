<?php
/**
 * Simple HTTP request to check dashboard via built-in PHP server
 */

echo "Attempting to fetch dashboard from PHP built-in server...\n\n";

$url = 'http://127.0.0.1:8000/technician_performance_dashboard.php';

$ch = curl_init($url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HEADER, true);
curl_setopt($ch, CURLOPT_NOBODY, false);
curl_setopt($ch, CURLOPT_TIMEOUT, 5);
curl_setopt($ch, CURLOPT_VERBOSE, false);

$response = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curl_error = curl_error($ch);
curl_close($ch);

echo "HTTP Response Code: $http_code\n";
echo "Curl Error: " . ($curl_error ? $curl_error : "None") . "\n\n";

if ($response) {
    list($headers, $body) = explode("\r\n\r\n", $response, 2);
    
    echo "=== RESPONSE HEADERS ===\n";
    echo $headers . "\n\n";
    
    echo "=== RESPONSE BODY (first 1000 chars) ===\n";
    $body_preview = substr($body, 0, 1000);
    echo $body_preview;
    if (strlen($body) > 1000) echo "\n... (truncated)\n";
    
    if ($http_code == 500) {
        echo "\n\n=== 500 ERROR DETECTED ===\n";
        if (strpos($body, 'Fatal error') !== false) {
            echo "Contains: Fatal error\n";
        }
        if (strpos($body, 'Warning') !== false) {
            echo "Contains: Warning\n";
        }
        if (strpos($body, 'ERROR') !== false) {
            echo "Contains: ERROR\n";
        }
    }
} else {
    echo "No response received\n";
}
?>
