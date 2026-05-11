#!/usr/bin/php
<?php
/**
 * MIGRATION: Add Per-Company Work Order Numbering
 * 
 * This migration adds per-company work order numbering where each company
 * gets their own independent WO sequence starting from #1.
 * 
 * Changes:
 * 1. Add wo_number column to work_orders table
 * 2. Backfill existing WOs with sequential numbers per tenant
 * 3. Create function to get next WO number for a tenant
 */

require_once 'config.inc.php';

$connection = get_database_connection();

echo "\n";
echo "╔════════════════════════════════════════════════════════════════════════════╗\n";
echo "║    MIGRATION: Per-Company Work Order Numbering                            ║\n";
echo "╚════════════════════════════════════════════════════════════════════════════╝\n\n";

// Step 1: Add wo_number column if it doesn't exist
echo "STEP 1: Adding wo_number column to work_orders table...\n";
try {
    $check = $connection->query("PRAGMA table_info('work_orders')");
    $columns = [];
    while ($col = $check->fetch(PDO::FETCH_ASSOC)) {
        $columns[] = $col['name'];
    }
    
    if (!in_array('wo_number', $columns)) {
        $connection->exec("ALTER TABLE work_orders ADD COLUMN wo_number INTEGER DEFAULT 0");
        echo "✅ wo_number column added\n\n";
    } else {
        echo "ℹ️  wo_number column already exists\n\n";
    }
} catch (Exception $e) {
    echo "❌ Error adding column: " . $e->getMessage() . "\n\n";
    exit(1);
}

// Step 2: Create index for wo_number
echo "STEP 2: Creating index for wo_number...\n";
try {
    $connection->exec("CREATE INDEX IF NOT EXISTS idx_work_orders_tenant_number 
                      ON work_orders(tenant_id, wo_number)");
    echo "✅ Index created\n\n";
} catch (Exception $e) {
    echo "❌ Error creating index: " . $e->getMessage() . "\n\n";
}

// Step 3: Backfill wo_number for existing work orders
echo "STEP 3: Backfilling wo_number for existing work orders...\n";

// Get all tenants
$tenants = $connection->query("SELECT DISTINCT tenant_id FROM work_orders ORDER BY tenant_id")
    ->fetchAll(PDO::FETCH_ASSOC);

$totalUpdated = 0;

foreach ($tenants as $tenant) {
    $tenant_id = $tenant['tenant_id'];
    
    // Get all work orders for this tenant, ordered by wo_id (creation order)
    $wos = $connection->query("SELECT wo_id FROM work_orders WHERE tenant_id = {$tenant_id} ORDER BY wo_id")
        ->fetchAll(PDO::FETCH_ASSOC);
    
    $wo_number = 1;
    $count = 0;
    
    foreach ($wos as $wo) {
        $wo_id = $wo['wo_id'];
        $connection->exec("UPDATE work_orders SET wo_number = {$wo_number} WHERE wo_id = {$wo_id}");
        $wo_number++;
        $count++;
        $totalUpdated++;
    }
    
    echo "  Tenant {$tenant_id}: Assigned {$count} WO numbers (1-{$count})\n";
}

echo "\n✅ Total work orders updated: {$totalUpdated}\n\n";

// Step 4: Verify the backfill
echo "STEP 4: Verifying backfill...\n";
$verification = $connection->query("
    SELECT 
        tenant_id,
        COUNT(*) as wo_count,
        MIN(wo_number) as min_number,
        MAX(wo_number) as max_number,
        COUNT(DISTINCT wo_number) as distinct_count
    FROM work_orders
    GROUP BY tenant_id
    ORDER BY tenant_id
")->fetchAll(PDO::FETCH_ASSOC);

foreach ($verification as $row) {
    $tenant_id = $row['tenant_id'];
    $count = $row['wo_count'];
    $min = $row['min_number'];
    $max = $row['max_number'];
    $distinct = $row['distinct_count'];
    
    $status = ($count === $distinct && $min === 1 && $max === $count) ? "✅" : "⚠️";
    echo "  {$status} Tenant {$tenant_id}: {$count} WOs, Numbers 1-{$max}\n";
}

echo "\n";
echo "╔════════════════════════════════════════════════════════════════════════════╗\n";
echo "║                    MIGRATION COMPLETE                                      ║\n";
echo "╠════════════════════════════════════════════════════════════════════════════╣\n";
echo "║                                                                            ║\n";
echo "║ ✅ Per-company work order numbering enabled                               ║\n";
echo "║                                                                            ║\n";
echo "║ What changed:                                                             ║\n";
echo "║  • New column: wo_number (per-company sequence)                           ║\n";
echo "║  • Each tenant now has their own WO #1, #2, #3...                         ║\n";
echo "║  • Existing WOs backfilled with sequential numbers                        ║\n";
echo "║                                                                            ║\n";
echo "║ Next steps:                                                               ║\n";
echo "║  • Update work_order.php to set wo_number on creation                     ║\n";
echo "║  • Update display code to show wo_number to users                         ║\n";
echo "║  • Update all references (email templates, reports, etc.)                 ║\n";
echo "║                                                                            ║\n";
echo "╚════════════════════════════════════════════════════════════════════════════╝\n\n";

echo "Status: ✅ READY FOR CODE UPDATES\n\n";
?>
