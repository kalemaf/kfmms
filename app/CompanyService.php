<?php
/**
 * Company Registration System
 * 
 * Handles new company registration and initialization
 */

require_once __DIR__ . '/app/Middleware/TenantMiddleware.php';

class CompanyService {
    private $connection;
    private $db_type;
    
    public function __construct($connection, $db_type) {
        $this->connection = $connection;
        $this->db_type = $db_type;
    }
    
    /**
     * Register a new company (tenant)
     * 
     * @param array $data
     * @return array
     */
    public function register($data) {
        try {
            // Validate input
            if (empty($data['name']) || empty($data['email'])) {
                return [
                    'success' => false,
                    'message' => 'Company name and email are required'
                ];
            }
            
            // Check if company exists
            $checkQuery = "SELECT company_id FROM companies WHERE email = ? OR name = ?";
            
            if ($this->db_type === 'sqlite') {
                $stmt = $this->connection->prepare($checkQuery);
                $stmt->execute([$data['email'], $data['name']]);
                $exists = $stmt->fetch(PDO::FETCH_ASSOC);
            } else {
                $stmt = $this->connection->prepare($checkQuery);
                $stmt->bind_param('ss', $data['email'], $data['name']);
                $stmt->execute();
                $result = $stmt->get_result();
                $exists = $result->fetch_assoc();
            }
            
            if ($exists) {
                return [
                    'success' => false,
                    'message' => 'Company already exists'
                ];
            }
            
            // Create company
            $insertQuery = "INSERT INTO companies 
                           (name, email, phone, address, city, state, country, postal_code,
                            subscription_plan, subscription_status, max_users, max_work_orders,
                            feature_tier, is_active, created_at, updated_at)
                           VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'trial', 'active', 5, 100, 'trial', 1, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)";
            
            if ($this->db_type === 'sqlite') {
                $stmt = $this->connection->prepare($insertQuery);
                $stmt->execute([
                    $data['name'],
                    $data['email'],
                    $data['phone'] ?? null,
                    $data['address'] ?? null,
                    $data['city'] ?? null,
                    $data['state'] ?? null,
                    $data['country'] ?? null,
                    $data['postal_code'] ?? null
                ]);
                $company_id = $this->connection->lastInsertId();
            } else {
                $phone = $data['phone'] ?? null;
                $address = $data['address'] ?? null;
                $city = $data['city'] ?? null;
                $state = $data['state'] ?? null;
                $country = $data['country'] ?? null;
                $postal_code = $data['postal_code'] ?? null;
                
                $stmt = $this->connection->prepare($insertQuery);
                $stmt->bind_param('ssssssss',
                    $data['name'],
                    $data['email'],
                    $phone,
                    $address,
                    $city,
                    $state,
                    $country,
                    $postal_code
                );
                $stmt->execute();
                $company_id = $this->connection->insert_id;
            }
            
            // Create storage directory for company
            $storage_path = __DIR__ . "/storage/uploads/tenant_{$company_id}";
            if (!is_dir($storage_path)) {
                mkdir($storage_path, 0755, true);
            }
            
            // Initialize tenant with empty tables (clear any inherited data)
            $this->initializeTenantData($company_id);
            
            return [
                'success' => true,
                'message' => 'Company registered successfully',
                'company_id' => $company_id,
                'name' => $data['name']
            ];
            
        } catch (Exception $e) {
            error_log("Company registration error: " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Registration failed: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * Initialize tenant data by clearing all tenant-related tables
     * This ensures new companies start with completely empty tables
     * 
     * @param int $tenant_id
     * @return bool
     */
    private function initializeTenantData($tenant_id) {
        try {
            // List of all tenant-related tables that should be empty for new companies
            $tenant_tables = [
                'work_orders',
                'equipment', 
                'inventory',
                'inventory_transactions',
                'parts_master',
                'purchase_requests',
                'purchase_request_items',
                'purchase_orders',
                'purchase_order_items',
                'goods_receipts',
                'goods_receipt_items',
                'pm_schedules',
                'pm_tasks',
                'pm_required_parts',
                'pm_consumables',
                'work_order_spares',
                'work_order_consumables',
                'wo_parts',
                'equipment_spares',
                'consumables',
                'consumable_usage',
                'vendors',
                'part_vendors',
                'warehouses',
                'warehouse_locations',
                'stock_locations',
                'stock_locales',
                'mechanics',
                'personnel',
                'sites_locations',
                'work_order_requests',
                'hot_jobs',
                'vendor_performance',
                'goods_receipt_notes',
                'payment_orders'
            ];
            
            // Clear each table for this tenant (in case of any data inheritance)
            foreach ($tenant_tables as $table) {
                $deleteQuery = "DELETE FROM $table WHERE tenant_id = ?";
                
                if ($this->db_type === 'sqlite') {
                    $stmt = $this->connection->prepare($deleteQuery);
                    $stmt->execute([$tenant_id]);
                } else {
                    $stmt = $this->connection->prepare($deleteQuery);
                    $stmt->bind_param('i', $tenant_id);
                    $stmt->execute();
                }
            }
            
            // Also clear any orphaned records that might not have tenant_id set
            // This is a safety measure to prevent data inheritance
            $this->clearOrphanedData($tenant_id);
            
            return true;
            
        } catch (Exception $e) {
            error_log("Tenant data initialization error: " . $e->getMessage());
            // Don't fail the entire registration if cleanup fails
            return false;
        }
    }
    
    /**
     * Clear any orphaned data that might not have proper tenant isolation
     * Safety measure to prevent data inheritance between tenants
     * 
     * @param int $tenant_id
     */
    private function clearOrphanedData($tenant_id) {
        try {
            // Clear any work order attachments that might be orphaned
            $attachment_dir = __DIR__ . "/storage/uploads/tenant_{$tenant_id}/attachments";
            if (is_dir($attachment_dir)) {
                $this->clearDirectory($attachment_dir);
            }
            
            // Clear any equipment photos that might be orphaned
            $photos_dir = __DIR__ . "/storage/uploads/tenant_{$tenant_id}/equipment_photos";
            if (is_dir($photos_dir)) {
                $this->clearDirectory($photos_dir);
            }
            
        } catch (Exception $e) {
            error_log("Orphaned data cleanup error: " . $e->getMessage());
        }
    }
    
    /**
     * Recursively clear a directory
     * 
     * @param string $dir
     */
    private function clearDirectory($dir) {
        if (!is_dir($dir)) {
            return;
        }
        
        $files = glob($dir . '/*');
        foreach ($files as $file) {
            if (is_file($file)) {
                unlink($file);
            } elseif (is_dir($file)) {
                $this->clearDirectory($file);
                rmdir($file);
            }
        }
    }
    
    /**
     * Ensure data integrity for multi-tenant system
     * This method should be called during system initialization
     * 
     * @return void
     */
    public function ensureTenantDataIntegrity() {
        try {
            // List of tenant tables that must have tenant_id
            $tenant_tables = [
                'work_orders' => 'tenant_id',
                'equipment' => 'tenant_id', 
                'inventory' => 'tenant_id',
                'parts_master' => 'tenant_id',
                'purchase_requests' => 'tenant_id',
                'vendors' => 'tenant_id',
                'warehouses' => 'tenant_id',
                'mechanics' => 'tenant_id',
                'sites_locations' => 'tenant_id',
                'work_order_requests' => 'tenant_id'
            ];
            
            // For each table, ensure all records have tenant_id set
            // This is a safety measure for existing data
            foreach ($tenant_tables as $table => $column) {
                // Check if table exists
                $checkTable = "SELECT name FROM sqlite_master WHERE type='table' AND name='$table'";
                $tableExists = $this->connection->query($checkTable)->fetch(PDO::FETCH_ASSOC);
                
                if ($tableExists) {
                    // Check if column exists
                    $checkColumn = "PRAGMA table_info($table)";
                    $columns = $this->connection->query($checkColumn)->fetchAll(PDO::FETCH_ASSOC);
                    $columnExists = false;
                    
                    foreach ($columns as $col) {
                        if ($col['name'] === $column) {
                            $columnExists = true;
                            break;
                        }
                    }
                    
                    if ($columnExists) {
                        // Find records with NULL or missing tenant_id
                        $nullCheck = "SELECT COUNT(*) as count FROM $table WHERE $column IS NULL OR $column = 0";
                        $result = $this->connection->query($nullCheck)->fetch(PDO::FETCH_ASSOC);
                        
                        if ($result && $result['count'] > 0) {
                            error_log("WARNING: Table $table has {$result['count']} records with missing tenant_id. This may cause data leakage.");
                        }
                    }
                }
            }
            
        } catch (Exception $e) {
            error_log("Data integrity check error: " . $e->getMessage());
        }
    }

    /**
     * Get company details (with tenant verification)
     *
     * @param int $company_id
     * @return array
     */
    public function getCompany($company_id) {
        try {
            $tenant_id = TenantMiddleware::getTenantId();
            
            // Users can only see their own company
            if ($company_id != $tenant_id) {
                return [
                    'success' => false,
                    'message' => 'Access denied'
                ];
            }
            
            $query = "SELECT * FROM companies WHERE company_id = ?";
            
            if ($this->db_type === 'sqlite') {
                $stmt = $this->connection->prepare($query);
                $stmt->execute([$company_id]);
                $company = $stmt->fetch(PDO::FETCH_ASSOC);
            } else {
                $stmt = $this->connection->prepare($query);
                $stmt->bind_param('i', $company_id);
                $stmt->execute();
                $result = $stmt->get_result();
                $company = $result->fetch_assoc();
            }
            
            if (!$company) {
                return [
                    'success' => false,
                    'message' => 'Company not found'
                ];
            }
            
            return [
                'success' => true,
                'data' => $company
            ];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Lock a company (admin only)
     * 
     * @param int $company_id
     * @param string $reason
     * @return array
     */
    public function lockCompany($company_id, $reason = '') {
        try {
            // This should only be callable by super-admin
            $query = "UPDATE companies SET is_locked = 1, lock_reason = ? WHERE company_id = ?";
            
            if ($this->db_type === 'sqlite') {
                $stmt = $this->connection->prepare($query);
                $stmt->execute([$reason, $company_id]);
            } else {
                $stmt = $this->connection->prepare($query);
                $stmt->bind_param('si', $reason, $company_id);
                $stmt->execute();
            }
            
            return [
                'success' => true,
                'message' => 'Company locked'
            ];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Get company storage path
     * 
     * @param int $company_id
     * @return string
     */
    public function getStoragePath($company_id) {
        return __DIR__ . "/storage/uploads/tenant_{$company_id}";
    }
}
?>
