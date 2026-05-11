<?php
/**
 * Equipment Model
 * 
 * Manages equipment for a specific tenant
 */

require_once __DIR__ . '/../BaseModel.php';

class Equipment extends BaseModel {
    protected $table = 'equipment';
    
    /**
     * Get all equipment for current tenant
     * 
     * @return array
     */
    public function getAllForTenant() {
        return $this->all('1=1', []);
    }
    
    /**
     * Get equipment by location
     * 
     * @param string $location
     * @return array
     */
    public function getByLocation($location) {
        return $this->all('location = ?', [$location]);
    }
    
    /**
     * Get equipment by status
     * 
     * @param string $status
     * @return array
     */
    public function getByStatus($status) {
        return $this->all('status = ?', [$status]);
    }
    
    /**
     * Create new equipment
     * 
     * @param array $data
     * @return int Equipment ID
     */
    public function addEquipment($data) {
        return $this->create($data);
    }
    
    /**
     * Update equipment details
     * 
     * @param int $equipment_id
     * @param array $data
     * @return bool
     */
    public function updateEquipment($equipment_id, $data) {
        return $this->update($equipment_id, $data, 'equipment_id');
    }
    
    /**
     * Count equipment by status
     * 
     * @param string $status
     * @return int
     */
    public function countByStatus($status) {
        return $this->count('status = ?', [$status]);
    }
}
?>
