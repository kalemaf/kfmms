<?php
/**
 * Main Entry Point for CMMS Application
 * Handles navigation and includes appropriate modules
 */

// Handle static file requests
$request_uri = $_SERVER['REQUEST_URI'] ?? '';
if (preg_match('#^/(images|styles|js|css|attachments|uploads|storage/uploads/tenant_\d+)/(.+)$#', $request_uri, $matches)) {
    $dir = $matches[1];
    $file = $matches[2];
    $file_path = __DIR__ . '/' . $dir . '/' . $file;

    // Security check - prevent directory traversal
    if (strpos($file, '..') === false && file_exists($file_path)) {
        $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
        $mime_types = [
            'png' => 'image/png',
            'jpg' => 'image/jpeg',
            'jpeg' => 'image/jpeg',
            'gif' => 'image/gif',
            'ico' => 'image/x-icon',
            'css' => 'text/css',
            'js' => 'application/javascript',
            'pdf' => 'application/pdf'
        ];

        if (isset($mime_types[$ext])) {
            header('Content-Type: ' . $mime_types[$ext]);
            header('Cache-Control: public, max-age=86400');
            readfile($file_path);
            exit;
        }
    }
    // If file doesn't exist or invalid extension, return 404
    header('HTTP/1.1 404 Not Found');
    exit;
}

// Handle favicon.ico requests (special case)
if ($request_uri === '/favicon.ico') {
    $favicon_path = __DIR__ . '/images/favicon.ico';
    if (file_exists($favicon_path)) {
        header('Content-Type: image/png'); // Serve as PNG since it's actually a PNG file
        header('Cache-Control: public, max-age=86400'); // Cache for 24 hours
        readfile($favicon_path);
        exit;
    } else {
        header('HTTP/1.1 404 Not Found');
        exit;
    }
}

// --- Allow framing for development (localhost/127.0.0.1) ---
$is_localhost = isset($_SERVER['HTTP_HOST']) && preg_match('/^(localhost|127\.0\.0\.1)(:\d+)?$/', $_SERVER['HTTP_HOST']);
if ($is_localhost) {
    // In development, allow framing from localhost
    header('Access-Control-Allow-Origin: http://127.0.0.1:8000', false);
    header('Access-Control-Allow-Credentials: true', false);
} else {
    // In production, use DENY to prevent clickjacking
    header('X-Frame-Options: DENY', false);
}

// --- Standardized session handling ---
require_once 'config.inc.php';
require_once 'common.inc.php';

// Start output buffering so later header() calls in included modules can still send headers
ob_start();

// Remove debug output for production
// error_log("[DEBUG] index.php SID=" . session_id() . ", SESSION=" . json_encode($_SESSION));

// ========================================================================
// CHECK MAINTENANCE MODE - Redirect non-admin users to maintenance page
// ========================================================================
$maintenance_file = __DIR__ . '/maintenance.flag';
if (file_exists($maintenance_file)) {
    // Maintenance is active - redirect to maintenance page unless user is admin or developer
    $user_role = strtolower($_SESSION['role'] ?? '');
    $is_admin = ($user_role === 'admin' || $user_role === 'developer');
    if (!$is_admin) {
        require_once 'maintenance_mode.php';
        exit;
    }
}

// Require login
if (empty($_SESSION['user'])) {
    header('Location: welcome.php');
    exit;
}

// Check if user's company is locked
if (!empty($_SESSION['company_id'])) {
    global $connection, $db_type;
    $ctrl_check = $connection->prepare("SELECT system_locked, lock_reason FROM system_control WHERE company_id = ? LIMIT 1");
    if ($ctrl_check) {
        $ctrl_check->bind_param('i', $_SESSION['company_id']);
        $ctrl_check->execute();
        $ctrl_result = $ctrl_check->get_result();
        if ($ctrl_row = $ctrl_result->fetch_assoc()) {
            if ($ctrl_row['system_locked']) {
                // Lock message and redirect
                $_SESSION['lock_message'] = 'System locked: ' . ($ctrl_row['lock_reason'] ?: 'Administrative lock');
                header('Location: auth.php?logout=1&redirect=login');
                exit;
            }
        }
        $ctrl_check->close();
    }
}

// Check license status - redirect to license gate if not valid
$license_check = check_user_license();
if (!$license_check['valid'] && !isset($_GET['bypass_license'])) {
    header('Location: license_gate.php');
    exit;
}

$c = $connection;
$username = $_SESSION['user'] ?? 'User';
$user_group = $_SESSION['group'] ?? '';

// Get navigation parameter
$nav = $_GET['nav'] ?? $_SESSION['nav'] ?? 'dashboard';
$_SESSION['nav'] = $nav;
$user_role = $_SESSION['role'] ?? '';
if ($user_role === 'technician') {
    $allowed_tech_nav = ['dashboard', 'work_orders', 'work_requests'];
    if (!in_array($nav, $allowed_tech_nav, true)) {
        $nav = 'dashboard';
    }
}

// ========================================================================
// IMPORTANT: Handle redirects BEFORE including title.php (which outputs HTML)
// This prevents "headers already sent" errors
// ========================================================================

// Check for work order completion redirect (must happen before header output)
if ($nav === 'work_orders' && isset($_GET['complete']) && is_numeric($_GET['complete'])) {
    $completeId = (int)$_GET['complete'];
    $statusFilter = trim($_GET['status'] ?? '');

    if ($connection) {
        $woRow = safe_query_row("SELECT wo_status, complete_date FROM work_orders WHERE wo_id=" . (int)$completeId . " LIMIT 1");
        if ($woRow && $woRow['wo_status'] !== 'Completed') {
            // Instead of completing immediately, redirect to completion confirmation page
            $basePath = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/\\');
            $completionUrl = ($basePath === '' ? '' : $basePath) . '/complete_work_order.php';
            header('Location: ' . $completionUrl . '?wo_id=' . $completeId . ($statusFilter !== '' ? '&status=' . urlencode($statusFilter) : ''));
            exit;
        } else {
            $redirectMsg = 'Work order #' . $completeId . ' is already Completed.';
        }
        if (isset($redirectMsg)) {
            $basePath = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/\\');
            $workOrdersUrl = ($basePath === '' ? '' : $basePath) . '/index.php';
            header('Location: ' . $workOrdersUrl . '?nav=work_orders' . ($statusFilter !== '' ? '&status=' . urlencode($statusFilter) : '') . '&msg=' . urlencode($redirectMsg));
            exit;
        }
    }
}

// Include title/navigation header
require_once 'title.php';

// Handle different navigation sections
switch ($nav) {
    case 'dashboard':
        require_once 'dashboard.php';
        break;

    case 'work_orders':
        require_once 'work_order.php';
        break;

    case 'work_requests':
        require_once 'work_order_requests.php';
        break;

    case 'pm':
        require_once 'automated_maintenance.php';
        break;

    case 'admin':
        require_once 'admin.php';
        break;

    case 'analytics':
        require_once 'analytics_dashboard.php';
        break;

    case 'maintenance_report':
        require_once 'maintenance_report.php';
        break;

    case 'lifecycle':
        require_once 'lifecycle_analytics_impl.php';
        break;

    case 'technician_performance':
        require_once 'technician_performance_dashboard.php';
        break;

    case 'manage_artisans':
        require_once 'manage_artisans.php';
        break;

    case 'reports':
        // Default to SLA compliance report
        require_once 'reports/sla_compliance.php';
        break;

    case 'equipment':
        require_once 'equipment.php';
        break;

    case 'equipment_spares':
        require_once 'equipment_spares.php';
        break;

    case 'purchase_orders':
        header('Location: inventory/purchase_orders.php');
        exit;

    case 'purchase_requests':
        require_once 'purchase_request.php';
        break;

    case 'inventory':
        require_once 'inventory_setup.php';
        break;

    case 'consumables':
        header('Location: inventory/consumables.php');
        exit;

    case 'goods_receipt':
        header('Location: inventory/goods_receipt.php');
        exit;

    case 'vendors':
        require_once 'vendors.php';
        break;

    case 'users':
        require_once 'access.php';
        break;

    case 'admin_roles':
        require_once 'admin_roles.php';
        break;

    case 'audit':
        require_once 'audit_logs.php';
        break;

    case 'health_check':
        require_once 'health_check.php';
        break;

    case 'developer':
        require_once 'developer_admin.php';
        break;

    case 'pending_user_authorizations':
        require_once 'pending_user_authorizations.php';
        break;

    default:
        // Default to dashboard
        require_once 'dashboard.php';
        break;
}

// Include footer if needed
?>
</body>
</html>