<?php
// Simulate the actual form rendering
$_SESSION = ['tenant_id' => 1, 'user_id' => 1, 'user' => 'developer', 'role' => 'admin'];
$_GET = ['action' => 'create'];
$_SERVER['REQUEST_METHOD'] = 'GET';

ob_start();
require_once __DIR__ . '/purchase_request.php';
$html = ob_get_clean();

echo "FORM RENDERING CHECK\n";
echo str_repeat("=", 70) . "\n\n";

// Check for all dropdowns
$dropdowns = [
    'site_location_id' => 'Site/Location',
    'warehouse_id' => 'Warehouse',
    'linked_work_order' => 'Linked Work Order',
];

foreach ($dropdowns as $name => $label) {
    echo "CHECKING: $label\n";
    echo str_repeat("-", 70) . "\n";
    
    // Find the select with this name
    $pattern = '/<select[^>]*name="' . preg_quote($name) . '"[^>]*>.*?<\/select>/is';
    if (preg_match($pattern, $html, $matches)) {
        $select_html = $matches[0];
        $option_count = substr_count($select_html, '<option');
        
        // Extract option text values
        preg_match_all('/<option[^>]*value="([^"]*)"[^>]*>([^<]*)<\/option>/i', $select_html, $options);
        
        echo "Found: YES\n";
        echo "Options count: $option_count\n";
        echo "Options:\n";
        for ($i = 0; $i < min(5, count($options[2])); $i++) {
            $val = $options[1][$i];
            $text = $options[2][$i];
            if (empty($text) || $text === $label) {
                echo "  - (placeholder)\n";
            } else {
                echo "  - $text (value: $val)\n";
            }
        }
        if (count($options[2]) > 5) {
            echo "  ... and " . (count($options[2]) - 5) . " more\n";
        }
        echo "\n";
    } else {
        echo "Found: NO ✗\n";
        echo "\n";
    }
}

// Check form structure
echo "FORM STRUCTURE\n";
echo str_repeat("-", 70) . "\n";

if (preg_match('/<div[^>]*class="[^"]*form-section[^"]*"[^>]*>.*?Requester.*?<\/div>\s*<\/div>/is', $html, $matches)) {
    echo "[✓] Requester & Organizational Info section found\n";
    
    // Count col-md-4 divs in this section (should be 6-7 for the form fields)
    $section = $matches[0];
    $col_count = substr_count($section, 'col-md-4');
    echo "[✓] Form fields (col-md-4): $col_count\n";
} else {
    echo "[✗] Requester section not found\n";
}

// Check for form method
if (preg_match('/<form[^>]*method="POST"/', $html)) {
    echo "[✓] Form method: POST\n";
} else {
    echo "[✗] Form method: NOT POST\n";
}

// Check for submit buttons
if (strpos($html, 'Save Draft') !== false && strpos($html, 'Submit for Approval') !== false) {
    echo "[✓] Submit buttons: Found\n";
} else {
    echo "[✗] Submit buttons: Missing\n";
}

?>
