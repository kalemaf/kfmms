<?php
/**
 * Test dashboard access WITH session cookie simulation
 */

echo "=== TESTING DASHBOARD WITH SESSION ===\n\n";

// First, start a session locally to create a session file
session_start();
$_SESSION['user_id'] = 1;
$_SESSION['role'] = 'admin';
$_SESSION['tenant_id'] = 1;
$_SESSION['username'] = 'admin';
$session_id = session_id();
echo "Created session: $session_id\n";
session_write_close();

// Now make HTTP request with that session cookie
$url = 'http://127.0.0.1:8000/technician_performance_dashboard.php';

$ch = curl_init($url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HEADER, true);
curl_setopt($ch, CURLOPT_COOKIE, "PHPSESSID=$session_id");
curl_setopt($ch, CURLOPT_TIMEOUT, 5);

$response = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$error = curl_error($ch);

echo "\nHTTP Response Code: $http_code\n";
echo "Error: " . ($error ? $error : "None") . "\n\n";

if ($response) {
    list($headers, $body) = explode("\r\n\r\n", $response, 2);
    
    echo "=== RESPONSE ANALYSIS ===\n";
    
    if ($http_code == 200) {
        echo "✓ HTTP 200 OK - Dashboard loaded successfully\n";
        if (strpos($body, '<!DOCTYPE html>') === 0) {
            echo "✓ Response starts with HTML\n";
            echo "✓ Response size: " . strlen($body) . " bytes\n";
        } else if (strpos($body, 'Technician Performance Dashboard') !== false) {
            echo "✓ Dashboard title found in response\n";
        }
    } else if ($http_code == 302) {
        preg_match('/Location: (.*?)\r/', $headers, $matches);
        echo "⚠ HTTP 302 Redirect to: " . ($matches[1] ?? 'unknown') . "\n";
        echo "  User is not logged in - should login first\n";
    } else if ($http_code == 500) {
        echo "✗ HTTP 500 Error\n";
        echo "\nFirst 500 chars of response:\n";
        echo substr($body, 0, 500) . "\n";
    } else {
        echo "Response code: $http_code\n";
        echo "First 200 chars:\n";
        echo substr($body, 0, 200) . "\n";
    }
}
?>
