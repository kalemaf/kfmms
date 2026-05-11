<?php
/**
 * Base Model Class for Multi-Tenant Data Isolation
 * 
 * Every model MUST extend this class to ensure:
 * 1. All queries are automatically filtered by tenant_id
 * 2. No accidental data leakage between companies
 * 3. Consistent tenant context usage
 */

require_once __DIR__ . '/Middleware/TenantMiddleware.php';

abstract class BaseModel {
    protected $connection;
    protected $db_type;
    protected $table;
    protected $tenant_id;
    
    public function __construct($connection, $db_type) {
        $this->connection = $connection;
        $this->db_type = $db_type;
        $this->tenant_id = tenant();  // Get tenant from middleware
    }
    
    /**
     * Get all records for current tenant
     * 
     * @param string $where Additional WHERE clause
     * @param array $params Parameters for prepared statement
     * @return array
     */
    public function all($where = '', $params = []) {
        $query = "SELECT * FROM {$this->table} WHERE tenant_id = ?";
        
        if ($where) {
            $query .= " AND {$where}";
        }
        
        array_unshift($params, $this->tenant_id);
        
        return $this->execute($query, $params);
    }
    
    /**
     * Find a record by ID and ensure it belongs to current tenant
     * 
     * @param int $id
     * @param string $id_column
     * @return array|null
     */
    public function find($id, $id_column = 'id') {
        $query = "SELECT * FROM {$this->table} 
                 WHERE {$id_column} = ? AND tenant_id = ?";
        
        $result = $this->execute($query, [$id, $this->tenant_id]);
        
        return $result[0] ?? null;
    }
    
    /**
     * Create a new record
     * 
     * @param array $data
     * @return int Last insert ID
     */
    public function create($data) {
        // Automatically add tenant_id
        $data['tenant_id'] = $this->tenant_id;
        
        $columns = implode(', ', array_keys($data));
        $placeholders = implode(', ', array_fill(0, count($data), '?'));
        
        $query = "INSERT INTO {$this->table} ({$columns}) 
                 VALUES ({$placeholders})";
        
        $values = array_values($data);
        
        if ($this->db_type === 'sqlite') {
            $stmt = $this->connection->prepare($query);
            $stmt->execute($values);
            return $this->connection->lastInsertId();
        } else {
            $stmt = $this->connection->prepare($query);
            $types = str_repeat('s', count($values));
            $stmt->bind_param($types, ...$values);
            $stmt->execute();
            return $this->connection->insert_id;
        }
    }
    
    /**
     * Update a record
     * 
     * @param int $id
     * @param array $data
     * @param string $id_column
     * @return bool
     */
    public function update($id, $data, $id_column = 'id') {
        // Ensure tenant_id can't be changed
        unset($data['tenant_id']);
        
        $set = implode(', ', array_map(fn($k) => "$k = ?", array_keys($data)));
        $query = "UPDATE {$this->table} 
                 SET {$set} 
                 WHERE {$id_column} = ? AND tenant_id = ?";
        
        $values = array_values($data);
        $values[] = $id;
        $values[] = $this->tenant_id;
        
        if ($this->db_type === 'sqlite') {
            $stmt = $this->connection->prepare($query);
            return $stmt->execute($values);
        } else {
            $stmt = $this->connection->prepare($query);
            $types = str_repeat('s', count($values) - 2) . 'ii';
            $stmt->bind_param($types, ...$values);
            return $stmt->execute();
        }
    }
    
    /**
     * Delete a record
     * 
     * @param int $id
     * @param string $id_column
     * @return bool
     */
    public function delete($id, $id_column = 'id') {
        $query = "DELETE FROM {$this->table} 
                 WHERE {$id_column} = ? AND tenant_id = ?";
        
        if ($this->db_type === 'sqlite') {
            $stmt = $this->connection->prepare($query);
            return $stmt->execute([$id, $this->tenant_id]);
        } else {
            $stmt = $this->connection->prepare($query);
            $stmt->bind_param('ii', $id, $this->tenant_id);
            return $stmt->execute();
        }
    }
    
    /**
     * Execute a query with tenant context
     * 
     * @param string $query
     * @param array $params
     * @return array
     */
    protected function execute($query, $params = []) {
        try {
            if ($this->db_type === 'sqlite') {
                $stmt = $this->connection->prepare($query);
                $stmt->execute($params);
                return $stmt->fetchAll(PDO::FETCH_ASSOC);
            } else {
                $stmt = $this->connection->prepare($query);
                $types = str_repeat('s', count($params));
                $stmt->bind_param($types, ...$params);
                $stmt->execute();
                $result = $stmt->get_result();
                return $result->fetch_all(MYSQLI_ASSOC);
            }
        } catch (Exception $e) {
            error_log("Database error in " . $this->table . ": " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Count records for current tenant
     * 
     * @param string $where Additional WHERE clause
     * @param array $params Parameters
     * @return int
     */
    public function count($where = '', $params = []) {
        $query = "SELECT COUNT(*) as count FROM {$this->table} 
                 WHERE tenant_id = ?";
        
        if ($where) {
            $query .= " AND {$where}";
        }
        
        array_unshift($params, $this->tenant_id);
        
        $result = $this->execute($query, $params);
        return $result[0]['count'] ?? 0;
    }
}
?>
