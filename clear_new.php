<?php
// Clear data for new companies - no output until after includes
ob_start();
include 'config.inc.php';
ob_end_clean();

echo "Connected to: " . DB_TYPE . "<br>";

// Get companies
$companies = $connection->query("SELECT id, company_name FROM companies WHERE id > 3 ORDER BY id")->fetchAll(PDO::FETCH_ASSOC);
echo "<h2>Companies: " . count($companies) . "</h2>";

// Tables to clear
$tables = ['work_orders', 'equipment', 'inventory', 'purchase_requests', 
           'purchase_orders', 'pm_schedules', 'consumables', 'vendors', 
           'warehouses', 'equipment_spares'];

foreach ($companies as $c) {
    $cid = $c['id'];
    echo "<h3>Company $cid: {$c['company_name']}</h3>";
    
    foreach ($tables as $table) {
        try {
            // Check if table exists and has tenant_id
            $info = $connection->query("PRAGMA table_info($table)")->fetchAll(PDO::FETCH_ASSOC);
            $has_tenant = false;
            foreach ($info as $col) {
                if ($col['name'] === 'tenant_id') {
                    $has_tenant = true;
                    break;
                }
            }
            
            if ($has_tenant) {
                $stmt = $connection->prepare("DELETE FROM $table WHERE tenant_id = ?");
                $stmt->execute([$cid]);
                $cnt = $stmt->rowCount();
                if ($cnt > 0) echo "  $table: $cnt deleted<br>";
            }
        } catch (Exception $e) {
            echo "  $table: Error - " . $e->getMessage() . "<br>";
        }
    }
}

// Verify
echo "<h2>Work Orders by Tenant</h2>";
$wo = $connection->query("SELECT tenant_id, COUNT(*) as cnt FROM work_orders GROUP BY tenant_id ORDER BY tenant_id")->fetchAll(PDO::FETCH_ASSOC);
foreach ($wo as $w) {
    echo "Tenant {$w['tenant_id']}: {$w['cnt']} work orders<br>";
}

echo "<p><strong>✓ Done!</strong></p>";