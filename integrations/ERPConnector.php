<?php
/**
 * ERP Connector Framework
 * 
 * Abstract base class for ERP integrations
 * Supports SAP, NetSuite, Oracle, and custom systems
 */

abstract class ERPConnector {
    
    protected $c;
    protected $config = [];
    protected $log = [];
    
    public function __construct($mysqli_connection, $config = []) {
        $this->c = $mysqli_connection;
        $this->config = $config;
    }

    /**
     * Connect to ERP system
     */
    abstract public function connect();

    /**
     * Disconnect from ERP
     */
    abstract public function disconnect();

    /**
     * Test connection
     */
    abstract public function testConnection();

    /**
     * Sync work order to ERP
     * Creates/updates maintenance order in ERP from CMMS WO
     */
    abstract public function syncWorkOrder($wo_id, $wo_data);

    /**
     * Sync inventory to ERP
     * Updates inventory counts in ERP from CMMS
     */
    abstract public function syncInventory($inventory_id, $qty_used, $qty_on_hand);

    /**
     * Sync GL entry to accounting
     * Creates journal entry for maintenance expense
     */
    abstract public function syncGLEntry($wo_id, $amount, $account_code);

    /**
     * Fetch equipment from ERP
     * Syncs asset data from ERP to CMMS
     */
    abstract public function fetchEquipment();

    /**
     * Logging helper
     */
    protected function log($message, $level = 'INFO') {
        $timestamp = date('Y-m-d H:i:s');
        $this->log[] = "[$timestamp] [$level] $message";
        
        // Also save to database
        $insert = "INSERT INTO erp_sync_log 
                   (system, message, status, created_at)
                   VALUES 
                   ('" . mysqli_real_escape_string($this->c, static::class) . "',
                    '" . mysqli_real_escape_string($this->c, $message) . "',
                    '$level',
                    NOW())";
        mysqli_query($this->c, $insert);
    }

    public function getLogs() {
        return $this->log;
    }
}

?>
