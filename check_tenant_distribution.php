<?php
require_once 'config.inc.php';

echo "=== Tenant Distribution in Key Tables ===\n\n";

$tables = ['parts_master', 'equipment', 'work_orders', 'vendors', 'warehouses', 'inventory'];

foreach ($tables as $table) {
    echo "--- $table ---\n";
    try {
        $result = $connection->query("SELECT tenant_id, COUNT(*) as cnt FROM $table GROUP BY tenant_id ORDER BY tenant_id");
        if ($result) {
            while ($row = $result->fetch(PDO::FETCH_ASSOC)) {
                echo "  tenant_id: {$row['tenant_id']} = {$row['cnt']} records\n";
            }
        }
    } catch (Exception $e) {
        echo "  Error: " . $e->getMessage() . "\n";
    }
    echo "\n";
}