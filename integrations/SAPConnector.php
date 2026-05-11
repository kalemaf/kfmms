<?php
/**
 * SAP Connector
 * 
 * Integrates with SAP ERP via OData or REST API
 * Syncs work orders, inventory, and GL entries
 */

require_once 'ERPConnector.php';

class SAPConnector extends ERPConnector {
    
    private $session = null;
    
    public function connect() {
        // Validate config
        if (empty($this->config['host']) || empty($this->config['username']) || empty($this->config['password'])) {
            $this->log('Missing SAP configuration', 'ERROR');
            return false;
        }

        try {
            // Create SOAP client or cURL session
            $this->session = [
                'host' => $this->config['host'],
                'username' => $this->config['username'],
                'cookies' => []
            ];

            // Authenticate
            if (!$this->authenticate()) {
                $this->log('SAP authentication failed', 'ERROR');
                return false;
            }

            $this->log('Connected to SAP', 'INFO');
            return true;

        } catch (Exception $e) {
            $this->log('SAP connection error: ' . $e->getMessage(), 'ERROR');
            return false;
        }
    }

    public function disconnect() {
        $this->session = null;
        $this->log('Disconnected from SAP', 'INFO');
    }

    public function testConnection() {
        if (!$this->session) {
            return ['success' => false, 'error' => 'Not connected'];
        }

        try {
            // Test with simple API call
            $response = $this->apiCall('GET', '/sap/opu/odata/sap/API_EQUIPMENT/Equipment', []);
            return ['success' => true, 'response' => $response];
        } catch (Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    public function syncWorkOrder($wo_id, $wo_data) {
        if (!$this->session) {
            $this->log("Cannot sync WO #$wo_id to SAP - not connected", 'ERROR');
            return false;
        }

        try {
            // Map CMMS work order to SAP maintenance order
            $sap_data = [
                'NotificationType' => '01', // Maintenance notification
                'NotificationText' => $wo_data['title'] ?? '',
                'Equipment' => $wo_data['equipment_id'] ?? '',
                'RequiredStart' => $wo_data['due_date'] ?? '',
                'Description' => $wo_data['description'] ?? '',
                'PriorityLevel' => $this->mapPriority($wo_data['priority'] ?? 'Medium')
            ];

            // Get SAP transaction code from config
            $sap_wo_id = $this->apiCall('POST', '/sap/opu/odata/sap/API_MAINTENANCE_NOTIF', $sap_data);

            $this->log("Synced WO #$wo_id to SAP as notification {$sap_wo_id['NotificationNumber']}", 'INFO');

            // Store mapping
            $this->storeSAPMapping($wo_id, $sap_wo_id['NotificationNumber']);

            return true;

        } catch (Exception $e) {
            $this->log("Failed to sync WO #$wo_id: " . $e->getMessage(), 'ERROR');
            return false;
        }
    }

    public function syncInventory($inventory_id, $qty_used, $qty_on_hand) {
        if (!$this->session) {
            $this->log("Cannot sync inventory #$inventory_id to SAP - not connected", 'ERROR');
            return false;
        }

        try {
            // Update material master quantity in SAP
            $sap_data = [
                'OnHandQuantity' => $qty_on_hand,
                'ReservedQuantity' => $qty_used
            ];

            // Get SAP material ID from mapping
            $mat_id = $this->getSAPMaterialCode($inventory_id);
            if (!$mat_id) {
                $this->log("No SAP mapping for inventory #$inventory_id", 'WARN');
                return false;
            }

            $this->apiCall('PATCH', "/sap/opu/odata/sap/API_MATERIAL/$mat_id", $sap_data);

            $this->log("Synced inventory #$inventory_id quantity to SAP", 'INFO');
            return true;

        } catch (Exception $e) {
            $this->log("Failed to sync inventory: " . $e->getMessage(), 'ERROR');
            return false;
        }
    }

    public function syncGLEntry($wo_id, $amount, $account_code) {
        if (!$this->session) {
            $this->log("Cannot sync GL entry from WO #$wo_id - not connected", 'ERROR');
            return false;
        }

        try {
            // Create journal entry in SAP
            $sap_data = [
                'ControllingAreaName' => $this->config['controlling_area'] ?? 'CA01',
                'CompanyCodeName' => $this->config['company_code'] ?? 'CC01',
                'DocumentDate' => date('Y-m-d'),
                'AccountingDocumentType' => 'SA', // Standard document
                'PostingDate' => date('Y-m-d'),
                'DocumentHeaderText' => "Maintenance WO #$wo_id",
                'Items' => [
                    [
                        'GLAccount' => $account_code,
                        'DebitAmount' => $amount,
                        'CostCenter' => $this->config['cost_center'] ?? 'CC01'
                    ],
                    [
                        'GLAccount' => $this->config['offset_account'] ?? '61000', // Expense offset
                        'CreditAmount' => $amount
                    ]
                ]
            ];

            $result = $this->apiCall('POST', '/sap/opu/odata/sap/API_GL_DOCUMENTHEADER', $sap_data);

            $this->log("Created GL entry for WO #$wo_id, amount: $amount", 'INFO');
            return true;

        } catch (Exception $e) {
            $this->log("Failed to sync GL entry: " . $e->getMessage(), 'ERROR');
            return false;
        }
    }

    public function fetchEquipment() {
        if (!$this->session) {
            $this->log("Cannot fetch equipment from SAP - not connected", 'ERROR');
            return [];
        }

        try {
            $equipment = $this->apiCall('GET', '/sap/opu/odata/sap/API_EQUIPMENT/Equipment', ['$top' => 1000]);

            $count = 0;
            foreach ($equipment['value'] ?? [] as $eq) {
                // Check if already exists in CMMS
                $exists = mysqli_query($this->c,
                    "SELECT id FROM equipment WHERE sap_equipment_id='" . mysqli_real_escape_string($this->c, $eq['EquipmentNumber']) . "'"
                );

                if (mysqli_num_rows($exists) === 0) {
                    // Insert new equipment
                    $insert = "INSERT INTO equipment 
                               (equipment_code, name, description, status, sap_equipment_id)
                               VALUES (
                                '" . mysqli_real_escape_string($this->c, $eq['EquipmentNumber']) . "',
                                '" . mysqli_real_escape_string($this->c, $eq['Description'] ?? '') . "',
                                '" . mysqli_real_escape_string($this->c, $eq['ManufacturerName'] ?? '') . "',
                                'Active',
                                '" . mysqli_real_escape_string($this->c, $eq['EquipmentNumber']) . "'
                               )";
                    
                    if (mysqli_query($this->c, $insert)) {
                        $count++;
                    }
                }
            }

            $this->log("Fetched and synced $count equipment items from SAP", 'INFO');
            return $equipment['value'] ?? [];

        } catch (Exception $e) {
            $this->log("Failed to fetch equipment: " . $e->getMessage(), 'ERROR');
            return [];
        }
    }

    // === Private Helper Methods ===

    private function authenticate() {
        // Implement SAP authentication logic
        // Could use SOAP, REST, or custom auth mechanism
        return true;
    }

    private function apiCall($method, $endpoint, $data = []) {
        // Make HTTP request to SAP OData API
        $url = $this->config['host'] . $endpoint;
        
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
        curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
        curl_setopt($ch, CURLOPT_USERPWD, $this->config['username'] . ':' . $this->config['password']);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);

        if (!empty($data) && in_array($method, ['POST', 'PATCH', 'PUT'])) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
            curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        }

        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        if (function_exists('curl_close')) { curl_close($ch); }

        if ($http_code >= 400) {
            throw new Exception("SAP API error: HTTP $http_code: $response");
        }

        return json_decode($response, true);
    }

    private function mapPriority($cmms_priority) {
        $map = [
            'Critical' => '1',
            'High' => '2',
            'Medium' => '3',
            'Low' => '4'
        ];
        return $map[$cmms_priority] ?? '3';
    }

    private function storeSAPMapping($wo_id, $sap_wo_id) {
        $insert = "INSERT INTO erp_mappings (cmms_id, erp_id, erp_system, entity_type, created_at) 
                   VALUES (" . (int)$wo_id . ", '" . mysqli_real_escape_string($this->c, $sap_wo_id) . "', 'SAP', 'WorkOrder', NOW())
                   ON DUPLICATE KEY UPDATE erp_id='" . mysqli_real_escape_string($this->c, $sap_wo_id) . "'";
        mysqli_query($this->c, $insert);
    }

    private function getSAPMaterialCode($inventory_id) {
        $result = mysqli_query($this->c,
            "SELECT erp_id FROM erp_mappings 
             WHERE cmms_id=" . (int)$inventory_id . " 
             AND erp_system='SAP' 
             AND entity_type='Inventory'"
        );
        
        if ($result && mysqli_num_rows($result) > 0) {
            $row = mysqli_fetch_assoc($result);
            return $row['erp_id'];
        }
        
        return null;
    }
}

?>
