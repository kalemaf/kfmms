<?php
/**
 * API endpoint for fetching equipment spares and parts (AJAX)
 * Returns JSON array of spares and parts for a given equipment_id
 */

require_once 'config.inc.php';
require_once 'common.inc.php';
require_once 'libraries/inventory_manager.php';

header('Content-Type: application/json');

$items = [];
$error = null;

try {
    if (isset($_GET['equipment_id']) && is_numeric($_GET['equipment_id']) && $connection) {
        $equipment_id = (int)$_GET['equipment_id'];
        
        // Get equipment-specific spares (tenant-aware)
        $tenant_id = (int)($_SESSION['tenant_id'] ?? 1);
        $spares_query = "SELECT id, part_name, part_number, quantity, 'spare' as type FROM equipment_spares WHERE equipment_id={$equipment_id} AND tenant_id = {$tenant_id} ORDER BY part_name";
        $spares_query = apply_tenant_filter($spares_query);
        $q = $connection->query($spares_query);
        if ($q) {
            while ($row = $q->fetch_assoc()) {
                $items[] = $row;
            }
        }
        
        // Get general parts from parts_master that might be compatible
        $parts = get_parts($connection);
        if ($parts) {
            foreach ($parts as $part) {
                // Check if part is compatible with this equipment (basic check)
                $compatible = true; // For now, include all parts
                if ($compatible) {
                    $stock = get_total_stock($part['id'], $connection);
                    $items[] = [
                        'id' => $part['id'],
                        'part_name' => $part['part_name'],
                        'part_number' => $part['part_code'] ?? $part['part_number'] ?? '',
                        'quantity' => $stock['total_on_hand'] ?? 0,
                        'type' => 'part'
                    ];
                }
            }
        }
    } else {
        $error = "Invalid equipment_id or database connection not available";
    }
} catch (Exception $e) {
    $error = $e->getMessage();
    error_log("api_spares.php error: " . $e->getMessage());
}

if ($error) {
    echo json_encode(['error' => $error, 'items' => $items]);
} else {
    echo json_encode($items);
}
