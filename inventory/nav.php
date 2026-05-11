<?php
// --- Standardized session handling ---
require_once("../config.inc.php");
session_save_path($session_save_path);
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (!empty($debug_mode)) {
    error_log("[DEBUG] inventory/nav.php SID=" . session_id() . ", SESSION=" . json_encode($_SESSION));
}

if (!isset($_SESSION['user'])) {
    header("Location: ../auth.php");
    exit;
}
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <style type="text/css">
        body {
            margin: 0;
            padding: 0;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #34495e;
            color: #ecf0f1;
        }

        .nav-container {
            padding: 10px 0;
        }

        .nav-item {
            padding: 10px 15px;
            color: #ecf0f1;
            text-decoration: none;
            display: block;
            border-left: 3px solid transparent;
            cursor: pointer;
            transition: all 0.2s ease;
            font-size: 13px;
            font-weight: 500;
        }

        .nav-item:hover {
            background: #2c3e50;
            border-left-color: #3498db;
            color: #3498db;
        }

        .nav-item.active {
            background: #2c3e50;
            border-left-color: #3498db;
            color: #3498db;
            font-weight: 600;
        }

        .nav-section {
            padding: 8px 15px;
            color: #95a5a6;
            font-size: 11px;
            font-weight: 600;
            text-transform: uppercase;
            border-bottom: 1px solid #2c3e50;
            margin-top: 5px;
        }

        i {
            margin-right: 8px;
            width: 16px;
            text-align: center;
        }

        .icon {
            display: inline-block;
            margin-right: 8px;
            width: 16px;
            text-align: center;
        }
    </style>
</head>
<body onload="javascript:setClass();">

<div class="nav-container">
    
    <div class="nav-section">📊 Dashboard & Reports</div>
    <a href="inventory_analytics.php" class="nav-item" id="nav_analytics">
        <span class="icon">📈</span>Analytics
    </a>
    
    <div class="nav-section">🔧 Core Operations</div>
    <a href="parts_master.php" class="nav-item" id="nav_parts">
        <span class="icon">📦</span>Parts Master
    </a>
    <a href="warehouse_management.php" class="nav-item" id="nav_warehouse">
        <span class="icon">🏢</span>Warehouses
    </a>
    
    <div class="nav-section">🛒 Purchasing</div>
    <a href="../purchase_request.php?action=list" class="nav-item" id="nav_pr">
        <span class="icon">📋</span>Purchase Requests
    </a>
    <a href="../purchase_order.php?action=list" class="nav-item" id="nav_po">
        <span class="icon">📄</span>Purchase Orders
    </a>
    <a href="../goods_receipt.php?action=list" class="nav-item" id="nav_gr">
        <span class="icon">📥</span>Goods Receipt
    </a>
    
    <div class="nav-section">👥 Supplier Management</div>
    <a href="../vendors.php?action=list" class="nav-item" id="nav_vendor">
        <span class="icon">🤝</span>Suppliers
    </a>

</div>

<script type="text/javascript">
function setClass() {
    // Get current filename from referrer or document location
    const url = window.location.href;
    const filename = url.split('/').pop().split('?')[0];
    
    // Map filenames to nav item IDs
    const navMap = {
        'inventory_analytics.php': 'nav_analytics',
        'parts_master.php': 'nav_parts',
        'warehouse_management.php': 'nav_warehouse',
        'purchase_requests.php': 'nav_pr',
        'purchase_orders.php': 'nav_po',
        'goods_receipt.php': 'nav_gr',
        'vendor_management.php': 'nav_vendor',
        'vendors.php': 'nav_vendor'
    };
    
    // Set active class
    const activeId = navMap[filename];
    if (activeId) {
        const elements = document.getElementsByClassName('nav-item');
        for (let el of elements) {
            el.classList.remove('active');
        }
        const activeEl = document.getElementById(activeId);
        if (activeEl) {
            activeEl.classList.add('active');
        }
    }
}
</script>

</body>
</html>
