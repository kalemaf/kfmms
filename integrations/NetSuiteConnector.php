<?php
/**
 * NetSuite Connector
 * 
 * Integrates with NetSuite via REST API v2
 * Syncs work orders (as Support Cases), inventory, and GL entries
 */

require_once 'ERPConnector.php';

class NetSuiteConnector extends ERPConnector {
    
    private $access_token = null;
    private $instance_url = null;
    
    public function connect() {
        if (empty($this->config['client_id']) || empty($this->config['client_secret'])) {
            $this->log('Missing NetSuite OAuth configuration', 'ERROR');
            return false;
        }

        try {
            // OAuth 2.0 authentication
            if (!$this->authenticateOAuth()) {
                $this->log('NetSuite OAuth authentication failed', 'ERROR');
                return false;
            }

            $this->log('Connected to NetSuite', 'INFO');
            return true;

        } catch (Exception $e) {
            $this->log('NetSuite connection error: ' . $e->getMessage(), 'ERROR');
            return false;
        }
    }

    public function disconnect() {
        $this->access_token = null;
        $this->instance_url = null;
        $this->log('Disconnected from NetSuite', 'INFO');
    }

    public function testConnection() {
        if (!$this->access_token) {
            return ['success' => false, 'error' => 'Not connected'];
        }

        try {
            $response = $this->apiCall('GET', '/services/rest/record/v1/supportcase', []);
            return ['success' => true, 'response' => $response];
        } catch (Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    public function syncWorkOrder($wo_id, $wo_data) {
        if (!$this->access_token) {
            $this->log("Cannot sync WO #$wo_id to NetSuite - not connected", 'ERROR');
            return false;
        }

        try {
            // Create/update Support Case in NetSuite
            $netsuite_data = [
                'title' => $wo_data['title'] ?? '',
                'inboundEmail' => [
                    'id' => -41  // Support Case email form
                ],
                'status' => $this->mapWOStatus($wo_data['status'] ?? 'Pending'),
                'priority' => $this->mapPriority($wo_data['priority'] ?? 'Medium'),
                'dueDate' => $wo_data['due_date'] ?? '',
                'description' => $wo_data['description'] ?? '',
                'customFieldList' => [
                    'customField' => [
                        [
                            'internalId' => 'custfield_cmms_wo_id',
                            'value' => $wo_id
                        ],
                        [
                            'internalId' => 'custfield_equipment_id',
                            'value' => $wo_data['equipment_id'] ?? ''
                        ]
                    ]
                ]
            ];

            // Check if already synced
            $existing = $this->getNetSuiteCaseID($wo_id);
            
            if ($existing) {
                // Update existing
                $result = $this->apiCall('PATCH', "/services/rest/record/v1/supportcase/{$existing}", $netsuite_data);
                $ns_id = $existing;
            } else {
                // Create new
                $result = $this->apiCall('POST', '/services/rest/record/v1/supportcase', $netsuite_data);
                $ns_id = $result['id'] ?? null;
            }

            if ($ns_id) {
                $this->storeNetSuiteMapping($wo_id, $ns_id);
                $this->log("Synced WO #$wo_id to NetSuite case $ns_id", 'INFO');
                return true;
            }

            return false;

        } catch (Exception $e) {
            $this->log("Failed to sync WO #$wo_id: " . $e->getMessage(), 'ERROR');
            return false;
        }
    }

    public function syncInventory($inventory_id, $qty_used, $qty_on_hand) {
        if (!$this->access_token) {
            $this->log("Cannot sync inventory #$inventory_id to NetSuite - not connected", 'ERROR');
            return false;
        }

        try {
            // Get NetSuite Item ID
            $item_id = $this->getNetSuiteItemID($inventory_id);
            if (!$item_id) {
                $this->log("No NetSuite mapping for inventory #$inventory_id", 'WARN');
                return false;
            }

            // Create inventory transaction (Purchase to Stock)
            $netsuite_data = [
                'tranid' => 'INV-' . $inventory_id . '-' . date('Ymd'),
                'entity' => [
                    'id' => $this->config['warehouse_vendor'] ?? '1'
                ],
                'subsidiary' => [
                    'id' => $this->config['subsidiary'] ?? '1'
                ],
                'items' => [
                    'item' => [
                        [
                            'item' => [
                                'id' => $item_id
                            ],
                            'quantity' => $qty_on_hand,
                            'rate' => 0,  // Inventory adjustment
                            'amount' => 0
                        ]
                    ]
                ]
            ];

            $result = $this->apiCall('POST', '/services/rest/record/v1/inventoryadjustment', $netsuite_data);

            $this->log("Synced inventory #$inventory_id to NetSuite, qty: $qty_on_hand", 'INFO');
            return true;

        } catch (Exception $e) {
            $this->log("Failed to sync inventory: " . $e->getMessage(), 'ERROR');
            return false;
        }
    }

    public function syncGLEntry($wo_id, $amount, $account_code) {
        if (!$this->access_token) {
            $this->log("Cannot sync GL entry from WO #$wo_id - not connected", 'ERROR');
            return false;
        }

        try {
            // Create Journal Entry in NetSuite
            $netsuite_data = [
                'subsidiary' => [
                    'id' => $this->config['subsidiary'] ?? '1'
                ],
                'trandate' => date('Y-m-d'),
                'lineitems' => [
                    'lineitem' => [
                        [
                            'account' => [
                                'id' => $account_code
                            ],
                            'debit' => $amount,
                            'memo' => "Maintenance WO #$wo_id"
                        ],
                        [
                            'account' => [
                                'id' => $this->config['offset_account'] ?? '5100'  // Maintenance expense
                            ],
                            'credit' => $amount
                        ]
                    ]
                ],
                'customFieldList' => [
                    'customField' => [
                        [
                            'internalId' => 'custfield_cmms_wo_id',
                            'value' => $wo_id
                        ]
                    ]
                ]
            ];

            $result = $this->apiCall('POST', '/services/rest/record/v1/journalentry', $netsuite_data);

            $this->log("Created GL entry for WO #$wo_id, amount: $amount", 'INFO');
            return true;

        } catch (Exception $e) {
            $this->log("Failed to sync GL entry: " . $e->getMessage(), 'ERROR');
            return false;
        }
    }

    public function fetchEquipment() {
        if (!$this->access_token) {
            $this->log("Cannot fetch equipment from NetSuite - not connected", 'ERROR');
            return [];
        }

        try {
            // Fetch items marked as equipment
            $equipment = $this->apiCall('GET', '/services/rest/query/v1/records/item?q=type=Equipment', []);

            $count = 0;
            foreach ($equipment['records'] ?? [] as $eq) {
                // Check if already exists
                $exists = mysqli_query($this->c,
                    "SELECT id FROM equipment WHERE netsuite_item_id='" . mysqli_real_escape_string($this->c, $eq['id']) . "'"
                );

                if (mysqli_num_rows($exists) === 0) {
                    // Insert new equipment
                    $insert = "INSERT INTO equipment 
                               (equipment_code, name, description, status, netsuite_item_id)
                               VALUES (
                                '" . mysqli_real_escape_string($this->c, $eq['name'] ?? '') . "',
                                '" . mysqli_real_escape_string($this->c, $eq['itemid'] ?? '') . "',
                                '" . mysqli_real_escape_string($this->c, $eq['description'] ?? '') . "',
                                'Active',
                                '" . mysqli_real_escape_string($this->c, $eq['id']) . "'
                               )";
                    
                    if (mysqli_query($this->c, $insert)) {
                        $count++;
                    }
                }
            }

            $this->log("Fetched and synced $count equipment items from NetSuite", 'INFO');
            return $equipment['records'] ?? [];

        } catch (Exception $e) {
            $this->log("Failed to fetch equipment: " . $e->getMessage(), 'ERROR');
            return [];
        }
    }

    // === Private Helper Methods ===

    private function authenticateOAuth() {
        $token_url = 'https://system.netsuite.com/oauth/authorize';
        
        // Use client credentials or authorization code flow
        $ch = curl_init($token_url);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query([
            'grant_type' => 'client_credentials',
            'client_id' => $this->config['client_id'],
            'client_secret' => $this->config['client_secret'],
            'scope' => 'rest_webservices'
        ]));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

        $response = curl_exec($ch);
        if (function_exists('curl_close')) { curl_close($ch); }

        $data = json_decode($response, true);
        if (empty($data['access_token'])) {
            return false;
        }

        $this->access_token = $data['access_token'];
        $this->instance_url = $this->config['instance_url'] ?? 'https://system.netsuite.com';
        
        return true;
    }

    private function apiCall($method, $endpoint, $data = []) {
        $url = $this->instance_url . $endpoint;
        
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: Bearer ' . $this->access_token,
            'Content-Type: application/json'
        ]);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);

        if (!empty($data) && in_array($method, ['POST', 'PATCH', 'PUT'])) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        }

        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        if (function_exists('curl_close')) { curl_close($ch); }

        if ($http_code >= 400) {
            throw new Exception("NetSuite API error: HTTP $http_code: $response");
        }

        return json_decode($response, true);
    }

    private function mapWOStatus($status) {
        $map = [
            'Pending' => 1,      // New
            'In Progress' => 2,  // In Progress
            'Completed' => 3,    // Resolved
            'Cancelled' => 4     // Closed
        ];
        return $map[$status] ?? 1;
    }

    private function mapPriority($priority) {
        $map = [
            'Critical' => 1,
            'High' => 2,
            'Medium' => 3,
            'Low' => 4
        ];
        return $map[$priority] ?? 3;
    }

    private function getNetSuiteCaseID($wo_id) {
        $result = mysqli_query($this->c,
            "SELECT erp_id FROM erp_mappings 
             WHERE cmms_id=" . (int)$wo_id . " 
             AND erp_system='NetSuite' 
             AND entity_type='WorkOrder' LIMIT 1"
        );
        
        if ($result && mysqli_num_rows($result) > 0) {
            $row = mysqli_fetch_assoc($result);
            return $row['erp_id'];
        }
        
        return null;
    }

    private function storeNetSuiteMapping($wo_id, $ns_id) {
        $insert = "INSERT INTO erp_mappings (cmms_id, erp_id, erp_system, entity_type, created_at) 
                   VALUES (" . (int)$wo_id . ", '" . mysqli_real_escape_string($this->c, $ns_id) . "', 'NetSuite', 'WorkOrder', NOW())
                   ON DUPLICATE KEY UPDATE erp_id='" . mysqli_real_escape_string($this->c, $ns_id) . "'";
        mysqli_query($this->c, $insert);
    }

    private function getNetSuiteItemID($inventory_id) {
        $result = mysqli_query($this->c,
            "SELECT erp_id FROM erp_mappings 
             WHERE cmms_id=" . (int)$inventory_id . " 
             AND erp_system='NetSuite' 
             AND entity_type='Inventory' LIMIT 1"
        );
        
        if ($result && mysqli_num_rows($result) > 0) {
            $row = mysqli_fetch_assoc($result);
            return $row['erp_id'];
        }
        
        return null;
    }
}

?>
