<?php
/**
 * Work Order Controller
 * 
 * Example of how to properly handle requests with tenant isolation
 */

require_once __DIR__ . '/../Middleware/TenantMiddleware.php';
require_once __DIR__ . '/../Models/WorkOrder.php';

class WorkOrderController {
    private $model;
    private $connection;
    private $db_type;
    
    public function __construct($connection, $db_type) {
        $this->connection = $connection;
        $this->db_type = $db_type;
        $this->model = new WorkOrder($connection, $db_type);
    }
    
    /**
     * GET /api/work-orders
     * Get all work orders for current tenant
     */
    public function index() {
        try {
            // Tenant context is automatic via BaseModel
            $work_orders = $this->model->getAllForTenant();
            
            return [
                'success' => true,
                'data' => $work_orders,
                'count' => count($work_orders)
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * GET /api/work-orders/:id
     * Get specific work order (with tenant verification)
     */
    public function show($id) {
        try {
            $work_order = $this->model->find($id, 'work_order_id');
            
            if (!$work_order) {
                return [
                    'success' => false,
                    'error' => 'Work order not found'
                ];
            }
            
            return [
                'success' => true,
                'data' => $work_order
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * POST /api/work-orders
     * Create new work order
     */
    public function store($data) {
        try {
            // User must be manager or admin
            if (!TenantMiddleware::isManager()) {
                return [
                    'success' => false,
                    'error' => 'Insufficient permissions'
                ];
            }
            
            $work_order_id = $this->model->createWorkOrder($data);
            
            return [
                'success' => true,
                'message' => 'Work order created',
                'work_order_id' => $work_order_id
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * PUT /api/work-orders/:id
     * Update work order
     */
    public function update($id, $data) {
        try {
            if (!TenantMiddleware::isManager()) {
                return [
                    'success' => false,
                    'error' => 'Insufficient permissions'
                ];
            }
            
            $success = $this->model->update($id, $data, 'work_order_id');
            
            return [
                'success' => $success,
                'message' => $success ? 'Work order updated' : 'Update failed'
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * DELETE /api/work-orders/:id
     * Delete work order
     */
    public function delete($id) {
        try {
            if (!TenantMiddleware::isAdmin()) {
                return [
                    'success' => false,
                    'error' => 'Only admins can delete work orders'
                ];
            }
            
            $success = $this->model->delete($id, 'work_order_id');
            
            return [
                'success' => $success,
                'message' => $success ? 'Work order deleted' : 'Delete failed'
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * GET /api/work-orders/status/:status
     * Get work orders by status
     */
    public function getByStatus($status) {
        try {
            $work_orders = $this->model->getByStatus($status);
            
            return [
                'success' => true,
                'data' => $work_orders,
                'status' => $status
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
}

// Helper function for quick usage
function workOrderController($connection, $db_type) {
    return new WorkOrderController($connection, $db_type);
}
?>
