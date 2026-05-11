<?php
/**
 * Debug: Purchase Request Dropdown Data
 * Check if dropdowns are loading data correctly
 */

require_once 'config.inc.php';
require_once 'common.inc.php';

// Simulate user session
if (!isset($_SESSION['tenant_id'])) {
    $_SESSION['tenant_id'] = 1;
}

$tenant_id = (int)($_SESSION['tenant_id'] ?? 1);

echo "PURCHASE REQUEST DROPDOWN DEBUG\n";
echo str_repeat("=", 70) . "\n";
echo "Current Tenant ID: $tenant_id\n";
echo str_repeat("=", 70) . "\n\n";

// 1. Check Sites/Locations
echo "1. SITE/LOCATION DROPDOWN\n";
echo "-" . str_repeat("-", 68) . "\n";
$sites_locations_list = query_to_array("SELECT id, full_location FROM sites_locations WHERE is_active = 1 AND tenant_id = $tenant_id ORDER BY full_location");
echo "Query:\n  SELECT id, full_location FROM sites_locations WHERE is_active = 1 AND tenant_id = $tenant_id\n";
echo "Result: " . count($sites_locations_list) . " records\n";
if (count($sites_locations_list) > 0) {
    echo "Data:\n";
    foreach ($sites_locations_list as $loc) {
        echo "  - {$loc['full_location']} (id={$loc['id']})\n";
    }
} else {
    echo "⚠ WARNING: No sites/locations found for tenant $tenant_id\n";
    // Check all sites/locations
    $all_sites = query_to_array("SELECT id, full_location, tenant_id FROM sites_locations WHERE is_active = 1");
    echo "All active sites/locations in database: " . count($all_sites) . "\n";
    foreach ($all_sites as $site) {
        echo "  - {$site['full_location']} (tenant={$site['tenant_id']})\n";
    }
}

// 2. Check Warehouses
echo "\n2. WAREHOUSE DROPDOWN\n";
echo "-" . str_repeat("-", 68) . "\n";
$warehouses_list = query_to_array("SELECT id, warehouse_name, warehouse_code FROM warehouses WHERE is_active = 1 AND tenant_id = $tenant_id ORDER BY warehouse_name");
echo "Query:\n  SELECT id, warehouse_name, warehouse_code FROM warehouses WHERE is_active = 1 AND tenant_id = $tenant_id\n";
echo "Result: " . count($warehouses_list) . " records\n";
if (count($warehouses_list) > 0) {
    echo "Data:\n";
    foreach ($warehouses_list as $wh) {
        echo "  - {$wh['warehouse_name']} ({$wh['warehouse_code']}) (id={$wh['id']})\n";
    }
} else {
    echo "⚠ WARNING: No warehouses found for tenant $tenant_id\n";
    // Check all warehouses
    $all_wh = query_to_array("SELECT id, warehouse_name, warehouse_code, tenant_id FROM warehouses WHERE is_active = 1");
    echo "All active warehouses in database: " . count($all_wh) . "\n";
    foreach ($all_wh as $w) {
        echo "  - {$w['warehouse_name']} (tenant={$w['tenant_id']})\n";
    }
}

// 3. Check Work Orders
echo "\n3. WORK ORDER DROPDOWN\n";
echo "-" . str_repeat("-", 68) . "\n";
$work_orders_list = query_to_array("SELECT wo_id, descriptive_text FROM work_orders WHERE tenant_id = $tenant_id ORDER BY submit_date DESC LIMIT 50");
echo "Query:\n  SELECT wo_id, descriptive_text FROM work_orders WHERE tenant_id = $tenant_id\n";
echo "Result: " . count($work_orders_list) . " records\n";
if (count($work_orders_list) > 0) {
    echo "Data:\n";
    foreach ($work_orders_list as $wo) {
        echo "  - WO #{$wo['wo_id']}: {$wo['descriptive_text']}\n";
    }
} else {
    echo "⚠ WARNING: No work orders found for tenant $tenant_id\n";
    // Check all work orders
    $all_wo = query_to_array("SELECT wo_id, descriptive_text, tenant_id FROM work_orders");
    echo "All work orders in database: " . count($all_wo) . "\n";
    foreach ($all_wo as $w) {
        echo "  - WO #{$w['wo_id']}: {$w['descriptive_text']} (tenant={$w['tenant_id']})\n";
    }
}

// 4. Check Project Code
echo "\n4. PROJECT CODE\n";
echo "-" . str_repeat("-", 68) . "\n";
echo "Status: ✓ TEXT INPUT FIELD (NOT a dropdown)\n";
echo "This is a text field where users enter project codes like: CapEx-2026-01\n";
echo "No database query needed - users type the code manually\n";

echo "\n" . str_repeat("=", 70) . "\n";
echo "DIAGNOSIS:\n";
echo "-" . str_repeat("-", 68) . "\n";

$has_issues = false;

if (count($sites_locations_list) === 0) {
    echo "❌ ISSUE 1: Site/Location dropdown is EMPTY\n";
    echo "   Cause: No sites/locations exist for tenant $tenant_id\n";
    echo "   Solution: Create sites/locations for this tenant in database\n";
    $has_issues = true;
}

if (count($warehouses_list) === 0) {
    echo "❌ ISSUE 2: Warehouse dropdown is EMPTY\n";
    echo "   Cause: No warehouses exist for tenant $tenant_id\n";
    echo "   Solution: Create warehouses for this tenant in database\n";
    $has_issues = true;
}

if (count($work_orders_list) === 0) {
    echo "❌ ISSUE 3: Work Order dropdown is EMPTY\n";
    echo "   Cause: No work orders exist for tenant $tenant_id\n";
    echo "   Solution: Create work orders for this tenant\n";
    $has_issues = true;
}

if (!$has_issues) {
    echo "✓ All dropdowns have data!\n";
    echo "Dropdowns should be showing options correctly.\n";
}

echo "\nProject Code: ✓ Working as designed (text input field)\n";

echo "\n";
?>
