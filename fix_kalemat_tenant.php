<?php
/**
 * Fix tenant_id for all users to match company_id
 * and clear data for new companies
 */
require_once 'config.inc.php';
require_once 'common.inc.php';

echo "<h1>Fixing User Tenant IDs</h1>";

// Fix all users where tenant_id != company_id
$connection->exec("UPDATE users SET tenant_id = company_id WHERE tenant_id != company_id");
echo "<p>✓ Updated users set tenant_id = company_id</p>";

// Verify the fix
echo "<h2>Users After Fix</h2>";
$users = $connection->query("SELECT user_id, username, company_id, tenant_id, role FROM users ORDER BY company_id, user_id")->fetchAll(PDO::FETCH_ASSOC);
echo "<pre>";
print_r($users);
echo "</pre>";

// Now clear data for new companies (company_id > 3)
echo "<h1>Clearing Data for New Companies</h1>";

// Get list of new companies
$new_companies = $connection->query("SELECT id, company_name FROM companies WHERE id > 3")->fetchAll(PDO::FETCH_ASSOC);
echo "<h2>New Companies to Clear:</h2>";
print_r($new_companies);

// Tables to clear for new companies
$tables_to_clear = [
    'work_orders', 'equipment', 'inventory', 'inventory_transactions',
    'parts_master', 'purchase_requests', 'purchase_request_items',
    'purchase_orders', 'purchase_order_items', 'goods_receipts',
    'goods_receipt_items', 'pm_schedules', 'pm_tasks', 'pm_required_parts',
    'pm_consumables', 'work_order_spares', 'work_order_consumables',
    'wo_parts', 'equipment_spares', 'consumables', 'consumable_usage',
    'vendors', 'part_vendors', 'warehouses', 'warehouse_locations',
    'stock_locations', 'stock_locale', 'mechanics', 'personnel',
    'sites_locations', 'work_order_requests', 'hot_jobs',
    'vendor_performance', 'goods_receipt_notes', 'payment_orders'
];

foreach ($new_companies as $company) {
    $company_id = $company['id'];
    echo "<h3>Clearing company $company_id ({$company['company_name']})...</h3>";
    
    foreach ($tables_to_clear as $table) {
        try {
            // Check if table has tenant_id column
            $has_tenant = $connection->query("PRAGMA table_info($table)")->fetchAll(PDO::FETCH_ASSOC);
            $has_tenant_col = false;
            foreach ($has_tenant as $col) {
                if ($col['name'] === 'tenant_id') {
                    $has_tenant_col = true;
                    break;
                }
            }
            
            if ($has_tenant_col) {
                $stmt = $connection->prepare("DELETE FROM $table WHERE tenant_id = ?");
                $stmt->execute([$company_id]);
                $count = $stmt->rowCount();
                if ($count > 0) {
                    echo "  - $table: $count records deleted<br>";
                }
            }
        } catch (Exception $e) {
            // Table might not exist or have no tenant_id
        }
    }
}

echo "<h2>Verification - Work Orders by Tenant</h2>";
$wo_by_tenant = $connection->query("SELECT tenant_id, COUNT(*) as cnt FROM work_orders GROUP BY tenant_id ORDER BY tenant_id")->fetchAll(PDO::FETCH_ASSOC);
echo "<pre>";
print_r($wo_by_tenant);
echo "</pre>";

echo "<h2>Verification - Users After Fix</h2>";
$users = $connection->query("SELECT user_id, username, company_id, tenant_id, role FROM users ORDER BY company_id, user_id")->fetchAll(PDO::FETCH_ASSOC);
echo "<pre>";
print_r($users);
echo "</pre>";

echo "<p style='color:green'><strong>✓ Done! User tenant_ids now match company_ids</strong></p>";