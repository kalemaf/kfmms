<?php
/**
 * Clear data for new companies (company_id > 3)
 */
require_once 'config.inc.php';
require_once 'common.inc.php';

echo "<h1>Clearing Data for New Companies</h1>";

// Get list of new companies
$companies = $connection->query("SELECT id, company_name FROM companies WHERE id > 3 ORDER BY id")->fetchAll(PDO::FETCH_ASSOC);

echo "<h2>Companies to clear: " . count($companies) . "</h2>";
foreach ($companies as $c) {
    echo "- Company {$c['id']}: {$c['company_name']}<br>";
}

// Tables with tenant_id to clear
$tables = [
    'work_orders' => 'tenant_id',
    'equipment' => 'tenant_id',
    'inventory' => 'tenant_id',
    'purchase_requests' => 'tenant_id',
    'purchase_orders' => 'tenant_id',
    'pm_schedules' => 'tenant_id',
    'consumables' => 'tenant_id',
    'vendors' => 'tenant_id',
    'warehouses' => 'tenant_id',
    'equipment_spares' => 'tenant_id'
];

foreach ($companies as $company) {
    $cid = $company['id'];
    echo "<h3>Company $cid ({$company['company_name']})</h3>";
    
    foreach ($tables as $table => $col) {
        try {
            $stmt = $connection->prepare("DELETE FROM $table WHERE $col = ?");
            $stmt->execute([$cid]);
            $cnt = $stmt->rowCount();
            if ($cnt > 0) echo "  $table: $cnt deleted<br>";
        } catch (Exception $e) {
            // skip
        }
    }
}

echo "<h2>Work Orders by Tenant (After)</h2>";
$wo = $connection->query("SELECT tenant_id, COUNT(*) as cnt FROM work_orders GROUP BY tenant_id")->fetchAll(PDO::FETCH_ASSOC);
print_r($wo);

echo "<p><strong>✓ Done!</strong></p>";