<?php
/**
 * Tenant Data Cleanup Script
 * 
 * This script fixes records with invalid or missing tenant_id values.
 * Use this to ensure all data is properly assigned to tenants.
 */

require_once __DIR__ . '/config.inc.php';

echo "======================================================\n";
echo "TENANT DATA CLEANUP\n";
echo "======================================================\n\n";

if (!$connection) {
    echo "ERROR: Database connection failed\n";
    exit(1);
}

$db_type = $GLOBALS['db_type'] ?? 'sqlite';

// Tables to clean and assign to default tenant (1) if tenant_id is invalid
$tables_to_clean = [
    'work_orders' => 'wo_id',
    'work_order_requests' => 'id',
    'equipment' => 'id',
    'parts_master' => 'id',
    'vendors' => 'id',
    'warehouses' => 'id',
    'consumables' => 'id',
    'pm_masters' => 'id',
    'purchase_requests' => 'id',
];

// Helper function to check if column exists
function column_exists($connection, $table, $column) {
    global $db_type;
    
    if ($db_type === 'sqlite') {
        try {
            $result = $connection->query("PRAGMA table_info('$table')");
            $columns = $result->fetchAll(PDO::FETCH_ASSOC);
            foreach ($columns as $col) {
                if ($col['name'] === $column) {
                    return true;
                }
            }
            return false;
        } catch (Exception $e) {
            return false;
        }
    } else {
        try {
            $result = $connection->query("SHOW COLUMNS FROM `$table` LIKE '$column'");
            return $result->num_rows > 0;
        } catch (Exception $e) {
            return false;
        }
    }
}

echo "Scanning tables for invalid tenant_id values...\n";
echo "-------------------------------------------\n\n";

foreach ($tables_to_clean as $table => $id_column) {
    echo "Table: $table\n";
    
    // Check if tenant_id column exists
    if (!column_exists($connection, $table, 'tenant_id')) {
        echo "  ✗ No tenant_id column found\n\n";
        continue;
    }
    
    // Count invalid records (tenant_id = 0 or NULL)
    try {
        $count_query = "SELECT COUNT(*) as invalid_count FROM $table WHERE tenant_id IS NULL OR tenant_id = 0";
        $result = $connection->query($count_query);
        
        if ($result) {
            $row = $db_type === 'sqlite' ? $result->fetch(PDO::FETCH_ASSOC) : $result->fetch_assoc();
            $invalid_count = $row['invalid_count'] ?? 0;
            
            if ($invalid_count > 0) {
                echo "  Found $invalid_count records with invalid tenant_id\n";
                
                // Show sample records
                $sample_query = "SELECT * FROM $table WHERE tenant_id IS NULL OR tenant_id = 0 LIMIT 3";
                $sample_result = $connection->query($sample_query);
                
                if ($sample_result) {
                    $samples = [];
                    while ($row = $db_type === 'sqlite' ? $sample_result->fetch(PDO::FETCH_ASSOC) : $sample_result->fetch_assoc()) {
                        $samples[] = $row;
                    }
                    
                    if (!empty($samples)) {
                        echo "  Sample records:\n";
                        foreach ($samples as $sample) {
                            $id_val = $sample[$id_column] ?? 'N/A';
                            if (isset($sample['vendor_name'])) {
                                echo "    - {$sample['vendor_name']} (ID: $id_val)\n";
                            } elseif (isset($sample['descriptive_text'])) {
                                echo "    - {$sample['descriptive_text']} (ID: $id_val)\n";
                            } elseif (isset($sample['name'])) {
                                echo "    - {$sample['name']} (ID: $id_val)\n";
                            } else {
                                echo "    - Record ID: $id_val\n";
                            }
                        }
                    }
                }
                
                // Fix: Assign to default tenant (1)
                echo "  → Assigning to default tenant (1)...\n";
                try {
                    $fix_query = "UPDATE $table SET tenant_id = 1 WHERE tenant_id IS NULL OR tenant_id = 0";
                    $connection->query($fix_query);
                    echo "  ✓ Fixed $invalid_count records\n\n";
                } catch (Exception $e) {
                    echo "  ✗ Error fixing records: " . $e->getMessage() . "\n\n";
                }
            } else {
                echo "  ✓ All records have valid tenant_id\n\n";
            }
        }
    } catch (Exception $e) {
        echo "  ✗ Error querying table: " . $e->getMessage() . "\n\n";
    }
}

echo "======================================================\n";
echo "✓ CLEANUP COMPLETE\n";
echo "======================================================\n";
echo "\nAll records with invalid tenant_id have been assigned\n";
echo "to the default tenant (1).\n\n";
echo "Next steps:\n";
echo "1. Review the audit report: tenant_isolation_audit.php\n";
echo "2. Create proper tenant assignments for new companies\n";
echo "3. Test data isolation between company logins\n";

?>
