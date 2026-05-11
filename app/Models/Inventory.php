<?php
/**
 * Inventory Model
 * 
 * Manages inventory and spare parts for a specific tenant
 */

require_once __DIR__ . '/../BaseModel.php';

class Inventory extends BaseModel {
    protected $table = 'inventory';
    
    /**
     * Get all inventory items for current tenant
     * 
     * @return array
     */
    public function getAllForTenant() {
        return $this->all('1=1', []);
    }
    
    /**
     * Get low stock items
     * 
     * @param int $threshold
     * @return array
     */
    public function getLowStock($threshold = 10) {
        return $this->all('quantity <= ?', [$threshold]);
    }
    
    /**
     * Get inventory by category
     * 
     * @param string $category
     * @return array
     */
    public function getByCategory($category) {
        return $this->all('category = ?', [$category]);
    }
    
    /**
     * Add inventory item
     * 
     * @param array $data
     * @return int Inventory ID
     */
    public function addItem($data) {
        return $this->create($data);
    }
    
    /**
     * Update inventory quantity
     * 
     * @param int $item_id
     * @param int $quantity_change
     * @return bool
     */
    public function updateQuantity($item_id, $quantity_change) {
        $query = "UPDATE {$this->table} 
                 SET quantity = quantity + ? 
                 WHERE inventory_id = ? AND tenant_id = ?";
        
        if ($this->db_type === 'sqlite') {
            $stmt = $this->connection->prepare($query);
            return $stmt->execute([$quantity_change, $item_id, $this->tenant_id]);
        } else {
            $stmt = $this->connection->prepare($query);
            $stmt->bind_param('iii', $quantity_change, $item_id, $this->tenant_id);
            return $stmt->execute();
        }
    }
    
    /**
     * Get total inventory value
     * 
     * @return float
     */
    public function getTotalValue() {
        $query = "SELECT SUM(quantity * unit_cost) as total_value 
                 FROM {$this->table} 
                 WHERE tenant_id = ?";
        
        $result = $this->execute($query, [$this->tenant_id]);
        return $result[0]['total_value'] ?? 0;
    }
}
?>
