<?php
/**
 * Equipment Controller
 * 
 * Example controller for equipment management with tenant isolation
 */

require_once __DIR__ . '/../Middleware/TenantMiddleware.php';
require_once __DIR__ . '/../Models/Equipment.php';

class EquipmentController {
    private $model;
    private $connection;
    private $db_type;
    
    public function __construct($connection, $db_type) {
        $this->connection = $connection;
        $this->db_type = $db_type;
        $this->model = new Equipment($connection, $db_type);
    }
    
    /**
     * GET /api/equipment
     * Get all equipment for current tenant
     */
    public function index() {
        try {
            $equipment = $this->model->getAllForTenant();
            
            return [
                'success' => true,
                'data' => $equipment,
                'count' => count($equipment)
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * GET /api/equipment/:id
     * Get specific equipment
     */
    public function show($id) {
        try {
            $equipment = $this->model->find($id, 'equipment_id');
            
            if (!$equipment) {
                return [
                    'success' => false,
                    'error' => 'Equipment not found'
                ];
            }
            
            return [
                'success' => true,
                'data' => $equipment
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * POST /api/equipment
     * Create new equipment
     */
    public function store($data) {
        try {
            if (!TenantMiddleware::isManager()) {
                return [
                    'success' => false,
                    'error' => 'Insufficient permissions'
                ];
            }
            
            $equipment_id = $this->model->addEquipment($data);
            
            return [
                'success' => true,
                'message' => 'Equipment added',
                'equipment_id' => $equipment_id
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * PUT /api/equipment/:id
     * Update equipment
     */
    public function update($id, $data) {
        try {
            if (!TenantMiddleware::isManager()) {
                return [
                    'success' => false,
                    'error' => 'Insufficient permissions'
                ];
            }
            
            $success = $this->model->updateEquipment($id, $data);
            
            return [
                'success' => $success,
                'message' => $success ? 'Equipment updated' : 'Update failed'
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * GET /api/equipment/status/:status
     * Get equipment by status
     */
    public function getByStatus($status) {
        try {
            $equipment = $this->model->getByStatus($status);
            
            return [
                'success' => true,
                'data' => $equipment,
                'status' => $status
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * GET /api/equipment/location/:location
     * Get equipment by location
     */
    public function getByLocation($location) {
        try {
            $equipment = $this->model->getByLocation($location);
            
            return [
                'success' => true,
                'data' => $equipment,
                'location' => $location
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
}
?>
