<?php
/**
 * Lifecycle Analytics Diagnostic Test
 * Visit this page to verify the lifecycle analytics page is working correctly
 */

session_start();

if (empty($_SESSION['user'])) {
    echo "Not logged in. Please <a href='index.php'>login first</a>.";
    exit;
}

echo "<h1>Lifecycle Analytics Diagnostic Test</h1>";
echo "<p>Testing if lifecycle_analytics_impl.php renders correctly...</p>";

require_once 'config.inc.php';
require_once 'common.inc.php';

echo "<pre>";
echo "Session Data:\n";
echo "  User: " . htmlspecialchars($_SESSION['user'] ?? 'NONE') . "\n";
echo "  Company ID: " . htmlspecialchars($_SESSION['company_id'] ?? 'NONE') . "\n";
echo "  Tenant ID: " . htmlspecialchars($_SESSION['tenant_id'] ?? 'NONE') . "\n";
echo "  Role: " . htmlspecialchars($_SESSION['role'] ?? 'NONE') . "\n";
echo "</pre>";

// Test the lifecycle analytics page rendering
ob_start();
try {
    $_GET['date_range'] = 'last_30d';
    require_once 'lifecycle_analytics_impl.php';
    $output = ob_get_clean();
    
    echo "<h2>✅ SUCCESS!</h2>";
    echo "<p>Lifecycle Analytics page rendered successfully!</p>";
    echo "<p>Output size: " . strlen($output) . " bytes</p>";
    echo "<p><a href='index.php?nav=lifecycle'>View Lifecycle Analytics Page</a></p>";
    
    // Show what was generated
    echo "<h3>Generated HTML Preview (first 1000 chars):</h3>";
    echo "<pre style='background: #f5f5f5; padding: 10px; overflow-x: auto;'>";
    echo htmlspecialchars(substr($output, 0, 1000)) . "...";
    echo "</pre>";
    
} catch (Exception $e) {
    echo "<h2>❌ ERROR!</h2>";
    echo "<p>Error message: " . htmlspecialchars($e->getMessage()) . "</p>";
    echo "<p>File: " . htmlspecialchars($e->getFile()) . "</p>";
    echo "<p>Line: " . htmlspecialchars($e->getLine()) . "</p>";
}

?>
