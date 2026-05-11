<?php
/**
 * Navigation Header Component
 * Provides consistent navigation across all application pages
 */

require_once 'config.inc.php';
require_once 'common.inc.php';

// Get current navigation and user info
$current_nav = $_SESSION['nav'] ?? 'dashboard';
$username = $_SESSION['user'] ?? 'User';
$user_role = $_SESSION['role'] ?? '';
$company_name = $company_name ?? '';

// Navigation menu items with icons
if ($user_role === 'technician') {
    $nav_items = [
        'dashboard' => ['icon' => 'fas fa-chart-line', 'label' => 'Dashboard', 'title' => 'System Overview'],
        'work_orders' => ['icon' => 'fas fa-clipboard-list', 'label' => 'Work Orders', 'title' => 'View and complete work orders'],
        'work_requests' => ['icon' => 'fas fa-file-contract', 'label' => 'Work Requests', 'title' => 'Submit work order requests'],
    ];
} elseif ($user_role === 'operator') {
    $nav_items = [
        'work_requests' => ['icon' => 'fas fa-file-contract', 'label' => 'Work Requests', 'title' => 'Submit work order requests'],
    ];
} else {
    $nav_items = [
        'dashboard' => ['icon' => 'fas fa-chart-line', 'label' => 'Dashboard', 'title' => 'System Overview'],
        'work_orders' => ['icon' => 'fas fa-clipboard-list', 'label' => 'Work Orders', 'title' => 'Manage Work Orders'],
        'manage_artisans' => ['icon' => 'fas fa-hammer', 'label' => 'Artisans', 'title' => 'Technician Management', 'href' => 'index.php?nav=manage_artisans'],
        'work_requests' => ['icon' => 'fas fa-file-contract', 'label' => 'Work Requests', 'title' => 'Request Work Orders'],
        'pm' => ['icon' => 'fas fa-tools', 'label' => 'Preventive Maintenance', 'title' => 'PM Schedules & Tasks'],
        'equipment' => ['icon' => 'fas fa-cogs', 'label' => 'Equipment', 'title' => 'Equipment Management'],
        'inventory' => ['icon' => 'fas fa-boxes', 'label' => 'Inventory', 'title' => 'Inventory & Parts Management'],
        'consumables' => ['icon' => 'fas fa-box-open', 'label' => 'Consumables', 'title' => 'Consumables Management'],
        'warehouses' => ['icon' => 'fas fa-warehouse', 'label' => 'Warehouses', 'title' => 'Warehouse & Location Management', 'href' => 'inventory/warehouse_management.php'],
        'purchase_requests' => ['icon' => 'fas fa-shopping-cart', 'label' => 'Purchase Requests', 'title' => 'Purchase Request Management'],
        'purchase_orders' => ['icon' => 'fas fa-file-invoice-dollar', 'label' => 'Purchase Orders', 'title' => 'Purchase Order Management', 'href' => 'inventory/purchase_orders.php'],
        'vendors' => ['icon' => 'fas fa-industry', 'label' => 'Supplier Management', 'title' => 'Manage supplier relationships'],
        'goods_receipt' => ['icon' => 'fas fa-truck', 'label' => 'Goods Receipt', 'title' => 'Goods Receipt Notes', 'href' => 'inventory/goods_receipt.php'],
        'analytics' => ['icon' => 'fas fa-chart-bar', 'label' => 'Analytics', 'title' => 'System Analytics'],
        'maintenance_report' => ['icon' => 'fas fa-calendar-alt', 'label' => 'Maintenance Report', 'title' => 'Monthly Maintenance Report'],
        'lifecycle' => ['icon' => 'fas fa-recycle', 'label' => 'Lifecycle', 'title' => 'Spare Parts Lifecycle'],
        'reports' => ['icon' => 'fas fa-file-alt', 'label' => 'Reports', 'title' => 'System Reports'],
        'technician_performance' => ['icon' => 'fas fa-tachometer-alt', 'label' => 'Performance', 'title' => 'Technician Performance', 'href' => 'index.php?nav=technician_performance'],
    ];
}

// Admin menu items (only for admin/manager/supervisor/maintenance manager/developer)
$admin_nav_items = [];
if (in_array($user_role, ['admin', 'manager', 'maintenance manager', 'supervisor', 'developer'], true)) {
    $admin_nav_items = [
        'admin' => ['icon' => 'fas fa-user-shield', 'label' => 'Administration', 'title' => 'System Administration']
    ];
}

// Copyright information (backend-generated)
$copyright_year = date('Y'); // Current year
$copyright_holder = 'KFMMS'; // Default company name

// Get company information from database if user is logged in
$contact_phone = '+256773095310'; // Default phone
if (isset($_SESSION['user_id'])) {
    $user_company_id = get_user_company_id($_SESSION['user_id']);
    if ($user_company_id) {
        $company_info = get_company_by_id($user_company_id);
        if ($company_info) {
            $copyright_holder = $company_info['company_name'] ?? 'KFMMS';
            // Get contact phone from company if available
            global $connection;
            $phone_query = "SELECT contact_phone FROM companies WHERE company_id = ? LIMIT 1";
            $phone_stmt = $connection->prepare($phone_query);
            if ($phone_stmt) {
                $phone_stmt->bind_param('i', $user_company_id);
                $phone_stmt->execute();
                $phone_result = $phone_stmt->get_result();
                if ($phone_result->num_rows > 0) {
                    $phone_row = $phone_result->fetch_assoc();
                    if (!empty($phone_row['contact_phone'])) {
                        $contact_phone = $phone_row['contact_phone'];
                    }
                }
            }
        }
    }
}

$copyright_text = "© {$copyright_year} {$copyright_holder}. All rights reserved";
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($nav_items[$current_nav]['label'] ?? $company_name); ?> - <?php echo htmlspecialchars($company_name); ?></title>

    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome Icons -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <!-- Custom Modern CSS -->
    <link href="styles/modern.css" rel="stylesheet">

    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background: radial-gradient(circle at top left, rgba(102,126,234,0.22), transparent 24%), radial-gradient(circle at top right, rgba(16,185,129,0.18), transparent 18%), linear-gradient(180deg, #eef2ff 0%, #e0f2fe 45%, #dbeafe 100%); min-height: 100vh; color: #1f2937; background-attachment: fixed; }
        
        /* Header Styling */
        .cmms-header { background: linear-gradient(135deg, #5b7fff 0%, #0f172a 100%); color: white; box-shadow: 0 4px 18px rgba(15,23,42,0.18); display: flex; flex-direction: column; }
        
        /* Logo and Branding Section */
        .cmms-header-top { 
            display: flex; 
            align-items: center; 
            justify-content: center;
            gap: 20px; 
            padding: 18px 35px;
            background: linear-gradient(135deg, rgba(0,0,0,0.1) 0%, rgba(0,0,0,0.05) 100%);
            border-bottom: 2px solid rgba(255,255,255,0.25);
            margin: 0;
        }
        .cmms-logo { height: 65px; width: auto; object-fit: contain; filter: drop-shadow(0 2px 4px rgba(0,0,0,0.2)); flex-shrink: 0; }
        .cmms-branding { flex: 0 0 auto; display: flex; flex-direction: column; justify-content: center; }
        .cmms-branding-title { font-size: 24px; font-weight: 800; margin: 0; letter-spacing: 1px; text-transform: uppercase; text-shadow: 0 2px 4px rgba(0,0,0,0.2); }
        .cmms-branding-slogan { font-size: 14px; font-style: italic; margin: 5px 0 0 0; opacity: 1; font-weight: 400; color: #e8f0fe; text-shadow: 0 1px 3px rgba(0,0,0,0.15); max-width: 500px; }
        .cmms-branding-phone { font-size: 12px; margin: 3px 0 0 0; opacity: 0.9; font-weight: 500; color: #ffffff; text-shadow: 0 1px 2px rgba(0,0,0,0.1); }
        .cmms-branding-website { font-size: 12px; margin: 2px 0 0 0; opacity: 0.85; font-weight: 500; color: #dce7ff; text-shadow: 0 1px 2px rgba(0,0,0,0.1); }
        .cmms-branding-copyright { font-size: 11px; margin: 2px 0 0 0; opacity: 0.8; font-weight: 400; color: #e8f0fe; text-shadow: 0 1px 2px rgba(0,0,0,0.1); }
        
        /* Navigation Container */
        .cmms-navbar-container { 
            display: flex; 
            align-items: center; 
            justify-content: space-between; 
            padding: 8px 35px;
            gap: 20px;
            flex-wrap: wrap;
            background: linear-gradient(90deg, rgba(0,0,0,0.15) 0%, transparent 100%);
        }
        
        /* Navigation Menu */
        .cmms-nav { 
            display: flex; 
            gap: 0; 
            flex: 1; 
            justify-content: center; 
            align-items: center; 
            flex-wrap: wrap;
            min-width: 600px;
        }
        
        .cmms-nav a { 
            color: white; 
            text-decoration: none; 
            padding: 10px 14px; 
            font-size: 14px; 
            font-weight: 600; 
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1); 
            border-bottom: 3px solid transparent; 
            display: inline-flex; 
            align-items: center; 
            gap: 5px; 
            white-space: nowrap;
            position: relative;
        }
        
        .cmms-nav a i { font-size: 15px; color: white; }
        
        .cmms-nav a::after {
            content: '';
            position: absolute;
            bottom: -10px;
            left: 0;
            right: 0;
            height: 3px;
            background: #ffd700;
            transform: scaleX(0);
            transition: transform 0.3s ease;
        }
        
        .cmms-nav a:hover::after,
        .cmms-nav a.current::after { 
            transform: scaleX(1);
        }
        
        .cmms-nav a:hover { 
            background-color: rgba(255,255,255,0.12); 
        }
        
        .cmms-nav a.current { 
            background-color: rgba(255,255,255,0.15); 
            font-weight: 700; 
        }
        
        /* User Section */
        .cmms-user { 
            display: flex; 
            align-items: center; 
            gap: 12px; 
            font-size: 11px; 
            white-space: nowrap;
            flex-shrink: 0;
        }
        
        .cmms-user-info { 
            color: rgba(255,255,255,0.95); 
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 6px;
        }
        
        .cmms-user a { 
            color: white; 
            text-decoration: none; 
            padding: 12px 20px; 
            background: rgba(255,255,255,0.2); 
            border-radius: 4px; 
            transition: all 0.3s ease; 
            font-size: 14px; 
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: 5px;
        }
        
        .cmms-user a:hover { 
            background: rgba(255,255,255,0.3);
        }
        
        /* Page Content */
        .container { padding: 25px 40px; max-width: 100%; margin: 0; }
        .page-header { margin-bottom: 30px; padding: 25px; background: rgba(255,255,255,0.88); backdrop-filter: blur(12px); border: 1px solid rgba(148,163,184,0.16); border-radius: 18px; box-shadow: 0 16px 32px rgba(15,23,42,0.08); }
        .page-title { color: #1e293b; margin: 0; font-size: 32px; font-weight: 700; }
        .page-subtitle { color: #7f8c8d; margin: 8px 0 0 0; font-size: 15px; }
        
        /* Responsive Design */
        @media (max-width: 1400px) {
            .cmms-navbar-container { padding: 8px 25px; }
            .cmms-nav { min-width: 500px; }
        }
        
        @media (max-width: 1200px) {
            .cmms-nav { min-width: 400px; gap: 0; }
            .cmms-nav a { padding: 10px 10px; font-size: 13px; }
            .cmms-header-top { gap: 15px; padding: 15px 25px; }
        }
        
        @media (max-width: 768px) {
            .cmms-header-top { 
                flex-direction: column;
                align-items: center;
                justify-content: center;
                padding: 15px 20px;
                gap: 10px;
            }
            .cmms-logo { height: 50px; }
            .cmms-branding-title { font-size: 20px; }
            .cmms-branding-slogan { font-size: 12px; max-width: 100%; }
            .cmms-branding-phone { font-size: 11px; }
            .cmms-branding-copyright { font-size: 10px; }
            .cmms-navbar-container { 
                flex-direction: column; 
                height: auto; 
                gap: 10px; 
                padding: 10px 20px;
                align-items: stretch;
            }
            .cmms-nav { 
                justify-content: flex-start; 
                width: 100%; 
                overflow-x: auto; 
                padding-bottom: 8px;
                min-width: auto;
            }
            .cmms-nav a { padding: 8px 10px; font-size: 12px; }
            .cmms-user { 
                justify-content: flex-end; 
                width: 100%; 
                padding-top: 8px; 
                border-top: 1px solid rgba(255,255,255,0.1);
                font-size: 10px;
            }
            .container { padding: 15px 20px; }
        }
        
        /* Print Styling */
        @media print {
            .cmms-navbar-container, .cmms-user { display: none !important; }
            .cmms-header { background: white; color: #333; }
            .cmms-header-top { background: white; border-bottom: 2px solid #333; justify-content: center; }
            .cmms-branding-title { color: #333; text-shadow: none; }
            .cmms-branding-slogan { color: #666; text-shadow: none; }
            .cmms-branding-phone { color: #555; text-shadow: none; }
            .cmms-branding-copyright { color: #777; text-shadow: none; }
            .cmms-logo { filter: none; }
        }
    </style>
</head>
<body>

<div class="cmms-header">
    <div class="cmms-header-top">
        <img src="images/kimage.png" alt="KFMMS Logo" class="cmms-logo">
        <div class="cmms-branding">
            <div class="cmms-branding-title">KFMMS</div>
            <div class="cmms-branding-slogan">The Computerized Maintenance Management System Enterprise</div>
            <div class="cmms-branding-phone"><?php echo htmlspecialchars($contact_phone); ?></div>
            <div class="cmms-branding-website">www.kfmms.com</div>
            <div class="cmms-branding-copyright"><?php echo htmlspecialchars($copyright_text); ?></div>
        </div>
    </div>
    <div class="cmms-navbar-container">
        <nav class="cmms-nav">
            <?php foreach ($nav_items as $nav_key => $nav_item): ?>
                <a href="<?php echo htmlspecialchars($nav_item['href'] ?? 'index.php?nav=' . $nav_key); ?>" class="<?php echo ($current_nav === $nav_key ? 'current' : ''); ?>" title="<?php echo htmlspecialchars($nav_item['title']); ?>">
                    <i class="<?php echo htmlspecialchars($nav_item['icon']); ?>"></i>
                    <span><?php echo htmlspecialchars($nav_item['label']); ?></span>
                </a>
            <?php endforeach; ?>
            <?php foreach ($admin_nav_items as $nav_key => $nav_item): ?>
                <a href="index.php?nav=<?php echo $nav_key; ?>" class="<?php echo ($current_nav === $nav_key ? 'current' : ''); ?>" title="<?php echo htmlspecialchars($nav_item['title']); ?>">
                    <i class="<?php echo htmlspecialchars($nav_item['icon']); ?>"></i>
                    <span><?php echo htmlspecialchars($nav_item['label']); ?></span>
                </a>
            <?php endforeach; ?>
        </nav>
        <div class="cmms-user">
            <span class="cmms-user-info">
                <i class="fas fa-user-circle"></i> 
                <?php echo htmlspecialchars($username); ?>
                <?php if ($user_role): ?><span style="opacity: 0.8;">(<?php echo htmlspecialchars($user_role); ?>)</span><?php endif; ?>
            </span>
            <a href="auth.php?logout=1"><i class="fas fa-sign-out-alt"></i> Logout</a>
        </div>
    </div>
</div>

<div class="container">
