<?php
/**
 * Work Order Model
 * 
 * Automatically enforces tenant_id filtering on all queries
 */

require_once __DIR__ . '/../BaseModel.php';

class WorkOrder extends BaseModel {
    protected $table = 'work_orders';
    
    /**
     * Get all work orders for current tenant
     * 
     * @return array
     */
    public function getAllForTenant() {
        return $this->all('1=1', []);
    }
    
    /**
     * Get work orders by status
     * 
     * @param string $status
     * @return array
     */
    public function getByStatus($status) {
        return $this->all('status = ?', [$status]);
    }
    
    /**
     * Get work orders for specific equipment
     * 
     * @param int $equipment_id
     * @return array
     */
    public function getByEquipment($equipment_id) {
        return $this->all('equipment_id = ?', [$equipment_id]);
    }
    
    /**
     * Get assigned work orders for technician
     * 
     * @param int $user_id
     * @return array
     */
    public function getAssignedTo($user_id) {
        return $this->all('assigned_to = ?', [$user_id]);
    }
    
    /**
     * Create a new work order
     * 
     * @param array $data
     * @return int Work order ID
     */
    public function createWorkOrder($data) {
        return $this->create($data);
    }
    
    /**
     * Update work order status
     * 
     * @param int $work_order_id
     * @param string $status
     * @return bool
     */
    public function updateStatus($work_order_id, $status) {
        return $this->update($work_order_id, ['status' => $status], 'work_order_id');
    }
}
?>
