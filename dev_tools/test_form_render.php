<?php
// Simulate accessing purchase_request.php?action=create
$_GET['action'] = 'create';
$_GET['id'] = 0;
$_SERVER['REQUEST_METHOD'] = 'GET';

// Set up session
if (!isset($_SESSION)) {
    session_start();
}
$_SESSION['tenant_id'] = 1;
$_SESSION['user_id'] = 1;
$_SESSION['user'] = 'admin';
$_SESSION['email'] = 'admin@test.com';
$_SESSION['role'] = 'admin';

require_once __DIR__ . '/config.inc.php';
require_once __DIR__ . '/common.inc.php';
require_once __DIR__ . '/libraries/inventory_manager.php';

// Mock the get_current_user_info function if needed
if (!function_exists('get_current_user_info')) {
    function get_current_user_info() {
        return [
            'id' => 1,
            'username' => 'admin',
            'email' => 'admin@test.com'
        ];
    }
}

echo "Simulating: index.php?nav=purchase_requests&action=create\n";
echo str_repeat("=", 70) . "\n\n";

// Capture the output
ob_start();

// Include purchase_request.php
include 'purchase_request.php';

$output = ob_get_clean();

// Search for the form and extract relevant sections
if (strpos($output, 'New Purchase Request') !== false) {
    echo "[✓] Form page detected\n\n";
} else {
    echo "[✗] Form page NOT FOUND\n\n";
}

// Extract the Requester & Organizational Info section
$pattern = '/Requester & Organizational Info.*?<\/div>\s*<\/div>/is';
if (preg_match($pattern, $output, $matches)) {
    echo "[✓] Requester & Organizational Info section found\n\n";
    
    // Check for site_location dropdown
    if (strpos($output, 'site_location_id') !== false) {
        echo "[✓] site_location_id field present in HTML\n";
    } else {
        echo "[✗] site_location_id field MISSING\n";
    }
    
    // Check for warehouse dropdown  
    if (strpos($output, 'warehouse_id') !== false) {
        echo "[✓] warehouse_id field present in HTML\n";
    } else {
        echo "[✗] warehouse_id field MISSING\n";
    }
    
    // Count options in each dropdown
    $site_options = substr_count($output, 'name="site_location_id"') > 0 ? preg_match_all('/<option[^>]*value="[^"]*"[^>]*>/', $output) : 0;
    $warehouse_options = substr_count($output, 'name="warehouse_id"') > 0 ? preg_match_all('/<option[^>]*value="[^"]*"[^>]*>/', $output) : 0;
    
    echo "\n[~] Rough option count:\n";
    echo "    - site_location options: " . $site_options . "\n";
    echo "    - warehouse options: " . $warehouse_options . "\n";
    
} else {
    echo "[✗] Requester & Organizational Info section NOT FOUND\n";
}

// Check for any PHP errors in the output
if (strpos($output, 'error') !== false || strpos($output, 'Error') !== false || strpos($output, 'ERROR') !== false) {
    echo "\n[!] Warning: Possible error messages in output\n";
}

// Show first 2000 chars of relevant section
echo "\n" . str_repeat("-", 70) . "\n";
echo "SAMPLE OUTPUT (first 3000 chars):\n";
echo str_repeat("-", 70) . "\n";
echo substr($output, 0, 3000) . "\n...\n";

?>
