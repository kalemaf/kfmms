<?php
/**
 * Migration: Clean up duplicate warehouse_locations and fix tenant_id issues
 */

require_once __DIR__ . '/../config.inc.php';

$db = $connection;
$db_type = $GLOBALS['db_type'] ?? 'sqlite';

try {
    echo "[Migration] Cleaning up warehouse_locations table...\n";
    
    if ($db_type === 'sqlite') {
        // Get all locations, keep only the first one for each warehouse_id, location_code, tenant_id
        $result = $db->query("SELECT id, warehouse_id, location_code, tenant_id, COUNT(*) as cnt 
                              FROM warehouse_locations 
                              GROUP BY warehouse_id, location_code, tenant_id 
                              HAVING cnt > 1");
        
        if ($result) {
            $duplicates = $result->fetchAll(PDO::FETCH_ASSOC);
            foreach ($duplicates as $dup) {
                $warehouse_id = $dup['warehouse_id'];
                $location_code = $dup['location_code'];
                $tenant_id = $dup['tenant_id'];
                
                echo "  - Found duplicates for warehouse_id=$warehouse_id, location_code=$location_code, tenant_id=$tenant_id\n";
                
                // Keep the first one, delete the rest
                $result2 = $db->query("SELECT id FROM warehouse_locations 
                                       WHERE warehouse_id = $warehouse_id 
                                       AND location_code = '$location_code' 
                                       AND tenant_id = $tenant_id 
                                       ORDER BY id ASC");
                
                $ids = [];
                while ($row = $result2->fetch(PDO::FETCH_ASSOC)) {
                    $ids[] = $row['id'];
                }
                
                if (count($ids) > 1) {
                    // Keep the first, delete the rest
                    $ids_to_delete = array_slice($ids, 1);
                    $id_list = implode(',', $ids_to_delete);
                    $db->exec("DELETE FROM warehouse_locations WHERE id IN ($id_list)");
                    echo "    ✓ Deleted " . count($ids_to_delete) . " duplicate(s)\n";
                }
            }
        }
    }
    
    echo "[Migration] ✓ Successfully cleaned up warehouse_locations\n";
} catch (Exception $e) {
    echo "[Migration Error] " . $e->getMessage() . "\n";
    exit(1);
}
?>
