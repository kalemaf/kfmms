<?php
// Direct SQLite diagnostic

try {
    $db = new SQLite3('database/maintenix.db');
    
    echo "=== TENANT DIAGNOSTICS ===\n\n";
    
    // 1. Find dim company
    echo "1. COMPANIES TABLE:\n";
    $result = $db->query("SELECT id, company_name FROM companies ORDER BY id");
    if ($result) {
        while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
            $marker = (stripos($row['company_name'], 'dim') !== false) ? " <-- DIM COMPANY" : "";
            echo "   ID {$row['id']}: {$row['company_name']}{$marker}\n";
        }
    }
    
    // 2. Find dim user
    echo "\n2. USERS TABLE:\n";
    $result = $db->query("SELECT user_id, username, company_id FROM users ORDER BY user_id");
    if ($result) {
        while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
            $marker = (stripos($row['username'], 'dim') !== false) ? " <-- DIM USER" : "";
            echo "   User ID {$row['user_id']}: {$row['username']} -> Company {$row['company_id']}{$marker}\n";
        }
    }
    
    // 3. Work order distribution
    echo "\n3. WORK ORDERS BY TENANT:\n";
    $result = $db->query("SELECT tenant_id, COUNT(*) as count FROM work_orders GROUP BY tenant_id ORDER BY tenant_id");
    if ($result) {
        while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
            echo "   Tenant {$row['tenant_id']}: {$row['count']} work orders\n";
        }
    }
    
    // 4. All work orders
    echo "\n4. ALL WORK ORDERS:\n";
    $result = $db->query("SELECT wo_id, descriptive_text, tenant_id FROM work_orders ORDER BY wo_id");
    if ($result) {
        while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
            $desc = substr($row['descriptive_text'], 0, 35);
            echo "   WO #{$row['wo_id']}: \"{$desc}...\" -> Tenant {$row['tenant_id']}\n";
        }
    }
    
    // 5. Check tenant_id column existence
    echo "\n5. TABLE SCHEMA CHECK:\n";
    $result = $db->query("PRAGMA table_info(work_orders)");
    $hasTenantId = false;
    if ($result) {
        while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
            if ($row['name'] === 'tenant_id') {
                $hasTenantId = true;
                echo "   ✓ work_orders.tenant_id exists (default: {$row['dflt_value']})\n";
            }
        }
    }
    if (!$hasTenantId) {
        echo "   ✗ work_orders.tenant_id MISSING!\n";
    }
    
    // 6. Check default tenant assignments
    echo "\n6. WORK ORDERS WITH NULL TENANT_ID:\n";
    $result = $db->query("SELECT COUNT(*) as count FROM work_orders WHERE tenant_id IS NULL OR tenant_id = 0");
    if ($result) {
        $row = $result->fetchArray(SQLITE3_ASSOC);
        if ($row['count'] > 0) {
            echo "   ⚠️  Found {$row['count']} work orders with NULL or 0 tenant_id!\n";
        } else {
            echo "   ✓ No NULL tenant_id values\n";
        }
    }
    
    // 7. Check if new tenant was created properly
    echo "\n7. CHECKING NEW TENANT 'dim':\n";
    $result = $db->query("SELECT id FROM companies WHERE LOWER(company_name) LIKE '%dim%'");
    if ($result && ($row = $result->fetchArray(SQLITE3_ASSOC))) {
        $dim_tenant_id = $row['id'];
        echo "   Dim company ID/Tenant: {$dim_tenant_id}\n";
        
        // Count work orders for this tenant
        $result = $db->query("SELECT COUNT(*) as count FROM work_orders WHERE tenant_id = " . intval($dim_tenant_id));
        if ($result) {
            $row = $result->fetchArray(SQLITE3_ASSOC);
            echo "   Work orders for dim tenant: {$row['count']}\n";
            
            if ($row['count'] > 0) {
                echo "   ⚠️  DIM TENANT HAS {$row['count']} WORK ORDERS - SHOULD BE 0 OR ONLY THEIR OWN!\n";
                // Show which ones
                $result = $db->query("SELECT wo_id, descriptive_text FROM work_orders WHERE tenant_id = " . intval($dim_tenant_id));
                while ($wo = $result->fetchArray(SQLITE3_ASSOC)) {
                    echo "      - WO #{$wo['wo_id']}: {$wo['descriptive_text']}\n";
                }
            }
        }
    } else {
        echo "   ✗ Dim company not found!\n";
    }
    
    $db->close();
    
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
}
?>
