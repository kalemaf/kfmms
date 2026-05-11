<?php
/**
 * Rebuild Spare Parts Lifecycle Analytics from completed work orders
 * This script syncs analytics with actual inventory transactions
 * Run after work orders are completed to update analytics cache
 */

require_once 'config.inc.php';
require_once 'common.inc.php';
require_once 'spare_integration_functions.php';
require_once 'libraries/inventory_manager.php';

echo "<h3>Spare Parts Lifecycle Analytics - Backend Sync</h3>";

if (!$connection) {
    die("Database connection failed");
}

$sync_results = [
    'work_order_spares_synced' => 0,
    'consumables_synced' => 0,
    'inventory_updated' => 0,
    'errors' => []
];

// Step 1: Sync work_order_spares usage with actual inventory
echo "<h4>Step 1: Syncing Work Order Spares Usage...</h4>";
try {
    $query = "SELECT wos.id, wos.wo_id, wos.spare_id, wos.quantity_used, 
                     es.part_id, pm.unit_cost, wo.submit_date
              FROM work_order_spares wos
              JOIN equipment_spares es ON wos.spare_id = es.id
              LEFT JOIN parts_master pm ON es.part_id = pm.id
              JOIN work_orders wo ON wos.wo_id = wo.wo_id
              WHERE wo.wo_status = 'Completed'";
    
    $result = $connection->query($query);
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $part_id = intval($row['part_id']);
            $qty_used = floatval($row['quantity_used']);
            $cost = floatval($row['unit_cost'] ?? 0);
            $submit_date = $row['submit_date'];
            
            // Record in inventory_transactions if not already recorded
            $check_query = "SELECT COUNT(*) as count FROM inventory_transactions 
                           WHERE reference_type = 'work_order' 
                           AND reference_id = " . intval($row['wo_id']) . "
                           AND part_id = {$part_id}";
            $check_result = $connection->query($check_query);
            if ($check_result && $check_result->fetch_assoc()['count'] == 0) {
                $insert_query = "INSERT INTO inventory_transactions 
                                (transaction_type, part_id, warehouse_location_id, quantity_change, 
                                 reference_type, reference_id, transaction_date, reason, notes)
                                VALUES ('issue', {$part_id}, 1, " . (-$qty_used) . ", 
                                        'work_order', " . intval($row['wo_id']) . ", 
                                        '{$submit_date}', 'Work Order Completion', 'Spare issued')";
                if ($connection->query($insert_query)) {
                    $sync_results['inventory_updated']++;
                }
            }
            $sync_results['work_order_spares_synced']++;
        }
        echo "<p>✓ Synced " . $sync_results['work_order_spares_synced'] . " spare part usage records</p>";
    }
} catch (Exception $e) {
    $sync_results['errors'][] = "Work order spares sync error: " . $e->getMessage();
    echo "<p style='color:red'>✗ Error: " . htmlspecialchars($e->getMessage()) . "</p>";
}

// Step 2: Sync work_order_consumables usage
echo "<h4>Step 2: Syncing Work Order Consumables Usage...</h4>";
try {
    $query = "SELECT woc.id, woc.work_order_id, woc.consumable_id, woc.quantity_required, 
                     woc.unit_cost, wo.submit_date, c.current_stock
              FROM work_order_consumables woc
              JOIN work_orders wo ON woc.work_order_id = wo.wo_id
              JOIN consumables c ON woc.consumable_id = c.id
              WHERE wo.wo_status = 'Completed' AND woc.is_consumed = 0";
    
    $result = $connection->query($query);
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $consumable_id = intval($row['consumable_id']);
            $qty_required = floatval($row['quantity_required']);
            $current_stock = intval($row['current_stock']);
            
            // Update consumable stock
            $new_stock = max(0, $current_stock - $qty_required);
            $update_query = "UPDATE consumables 
                            SET current_stock = {$new_stock},
                                last_updated = NOW()
                            WHERE id = {$consumable_id}";
            
            if ($connection->query($update_query)) {
                // Mark as consumed
                $mark_query = "UPDATE work_order_consumables 
                              SET is_consumed = 1, quantity_used = {$qty_required}
                              WHERE id = " . intval($row['id']);
                $connection->query($mark_query);
                $sync_results['consumables_synced']++;
            }
        }
        echo "<p>✓ Synced " . $sync_results['consumables_synced'] . " consumable usage records</p>";
    }
} catch (Exception $e) {
    $sync_results['errors'][] = "Consumables sync error: " . $e->getMessage();
    echo "<p style='color:red'>✗ Error: " . htmlspecialchars($e->getMessage()) . "</p>";
}

// Step 3: Sync parts_master totals with stock_locales
echo "<h4>Step 3: Syncing Parts Master Inventory Totals...</h4>";
try {
    $query = "SELECT DISTINCT pm.id FROM parts_master pm";
    $result = $connection->query($query);
    $sync_count = 0;
    
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $part_id = intval($row['id']);
            
            // Calculate total from stock_locales
            $total_query = "SELECT COALESCE(SUM(quantity_on_hand), 0) as total FROM stock_locales 
                           WHERE part_id = {$part_id}";
            $total_result = $connection->query($total_query);
            if ($total_result) {
                $total_row = $total_result->fetch_assoc();
                $total_qty = intval($total_row['total']);
                
                // Update parts_master
                $update_query = "UPDATE parts_master SET total_on_hand = {$total_qty} WHERE id = {$part_id}";
                if ($connection->query($update_query)) {
                    $sync_count++;
                }
            }
        }
        echo "<p>✓ Updated inventory totals for " . $sync_count . " parts</p>";
    }
} catch (Exception $e) {
    $sync_results['errors'][] = "Parts master sync error: " . $e->getMessage();
    echo "<p style='color:red'>✗ Error: " . htmlspecialchars($e->getMessage()) . "</p>";
}

// Summary
echo "<h4>Sync Summary</h4>";
echo "<table border='1' cellpadding='10'>";
echo "<tr><td>Work Order Spares Synced</td><td>" . $sync_results['work_order_spares_synced'] . "</td></tr>";
echo "<tr><td>Consumables Synced</td><td>" . $sync_results['consumables_synced'] . "</td></tr>";
echo "<tr><td>Inventory Records Updated</td><td>" . $sync_results['inventory_updated'] . "</td></tr>";
if (count($sync_results['errors']) > 0) {
    echo "<tr><td colspan='2' style='color:red'><strong>Errors:</strong><br>";
    foreach ($sync_results['errors'] as $error) {
        echo "• " . htmlspecialchars($error) . "<br>";
    }
    echo "</td></tr>";
}
echo "</table>";

echo "<p><a href='index.php?nav=lifecycle' class='btn btn-primary'>Back to Analytics</a></p>";
?>
