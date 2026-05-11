<?php
error_reporting(E_ALL);
ini_set('display_errors', '1');

echo "Testing check_predictive_status.php...\n";

try {
    ob_start();
    include 'check_predictive_status.php';
    $content = ob_get_clean();
    
    if (strlen($content) > 100) {
        echo "✅ Page loaded successfully!\n";
        echo "Output length: " . strlen($content) . " bytes\n";
        echo "First 200 chars:\n";
        echo substr($content, 0, 200) . "\n";
    } else {
        echo "⚠️ Page content too short\n";
    }
} catch (Throwable $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    echo "File: " . $e->getFile() . "\n";
    echo "Line: " . $e->getLine() . "\n";
}
?>
