<?php
/**
 * Simple test: Check if sites_locations and warehouses populate in the form
 */

// Set up minimal environment
$_SESSION = ['tenant_id' => 1, 'user_id' => 1, 'user' => 'admin', 'role' => 'admin'];
$_GET = ['action' => 'create'];
$_SERVER['REQUEST_METHOD'] = 'GET';

// Open the form output and search for the dropdowns
ob_start();
require_once __DIR__ . '/purchase_request.php';
$html = ob_get_clean();

// Search for the dropdown selects
echo "===== FORM ANALYSIS =====\n\n";

// Extract site_location select section
preg_match('/<select name="site_location_id".*?<\/select>/is', $html, $site_select);
if (!empty($site_select)) {
    echo "SITE/LOCATION DROPDOWN:\n";
    echo "========================\n";
    
    $select_html = $site_select[0];
    // Count options
    $option_count = substr_count($select_html, '<option');
    echo "Total options found: $option_count\n";
    
    // Extract option values
    preg_match_all('/<option[^>]*value="([^"]*)"[^>]*>([^<]*)<\/option>/i', $select_html, $options);
    
    if (!empty($options[1])) {
        echo "Options:\n";
        for ($i = 0; $i < count($options[1]); $i++) {
            $val = $options[1][$i];
            $text = $options[2][$i];
            if ($text === 'Choose site/location') {
                echo "  (empty placeholder)\n";
            } else {
                echo "  - $text (value: $val)\n";
            }
        }
    }
} else {
    echo "[✗] Site/Location select NOT FOUND\n";
}

echo "\n";

// Extract warehouse select section
preg_match('/<select name="warehouse_id".*?<\/select>/is', $html, $warehouse_select);
if (!empty($warehouse_select)) {
    echo "WAREHOUSE DROPDOWN:\n";
    echo "===================\n";
    
    $select_html = $warehouse_select[0];
    // Count options
    $option_count = substr_count($select_html, '<option');
    echo "Total options found: $option_count\n";
    
    // Extract option values
    preg_match_all('/<option[^>]*value="([^"]*)"[^>]*>([^<]*)<\/option>/i', $select_html, $options);
    
    if (!empty($options[1])) {
        echo "Options:\n";
        for ($i = 0; $i < count($options[1]); $i++) {
            $val = $options[1][$i];
            $text = $options[2][$i];
            if ($text === 'Choose warehouse') {
                echo "  (empty placeholder)\n";
            } else {
                echo "  - $text (value: $val)\n";
            }
        }
    }
} else {
    echo "[✗] Warehouse select NOT FOUND\n";
}

echo "\n";

// Check form submit buttons
if (strpos($html, 'Save Draft') !== false) {
    echo "[✓] Form submit buttons found\n";
} else {
    echo "[✗] Form submit buttons NOT found\n";
}

// Check for form method
if (preg_match('/<form[^>]*method="POST"/', $html)) {
    echo "[✓] Form method is POST\n";
} else {
    echo "[✗] Form method is NOT POST\n";
}

?>
