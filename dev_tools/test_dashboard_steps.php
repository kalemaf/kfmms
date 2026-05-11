<?php
session_start();
$_SESSION['user_id'] = 1;
$_SESSION['role'] = 'admin';
$_SESSION['tenant_id'] = 1;

error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "Step 1: Load config\n";
require_once 'config.inc.php';
require_once 'common.inc.php';
echo "Step 2: Config loaded\n";

echo "Step 3: Load performance schema\n";
require_once 'libraries/slaService.php';
echo "Step 4: SLA Service loaded\n";

require_once 'libraries/performanceService.php';
echo "Step 5: Performance Service loaded\n";

require_once 'libraries/repeatFailureService.php';
echo "Step 6: Repeat Failure Service loaded\n";

require_once 'libraries/performance_schema.php';
echo "Step 7: Performance Schema loaded\n";

// Check authentication and authorization
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

// Only managers and supervisors can access
$user_role = $_SESSION['role'] ?? 'operator';
echo "Step 8: User role: " . $user_role . "\n";

if (!in_array($user_role, ['manager', 'maintenance manager', 'supervisor', 'admin'])) {
    die('Access Denied');
}

echo "Step 9: Access check passed\n";

// Initialize tables if needed
echo "Step 10: Initializing tables\n";
initialize_performance_monitoring_tables($connection);
echo "Step 11: Tables initialized\n";

echo "All steps completed successfully!\n";
?>
