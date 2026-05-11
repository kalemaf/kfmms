# CMMS Integration & API System

## Overview

Complete REST API with third-party integrations including:
- ✅ **REST API** for all CMMS operations
- ✅ **ERP Connectors** (SAP, NetSuite)
- ✅ **Cloud Storage** (AWS S3, Google Drive, Azure Blob)
- ✅ **GL Mapping** (Accounting integration)

---

## 1. REST API Framework

### Base URL
```
https://your-cmms-server.com/api/v1/
```

### Authentication

#### Option 1: API Key (Simple)
```bash
curl -H "X-API-Key: your-api-key-here" \
  https://your-cmms/api/v1/work_orders
```

#### Option 2: Bearer Token (OAuth)
```bash
curl -H "Authorization: Bearer your-access-token" \
  https://your-cmms/api/v1/work_orders
```

### Available Endpoints

#### Work Orders
```
GET    /api/v1/work_orders              - List all WOs
GET    /api/v1/work_orders/123          - Get specific WO
POST   /api/v1/work_orders              - Create new WO
PUT    /api/v1/work_orders/123          - Update WO
DELETE /api/v1/work_orders/123          - Delete WO
```

#### Equipment
```
GET    /api/v1/equipment                - List equipment
GET    /api/v1/equipment/456            - Get equipment details
POST   /api/v1/equipment                - Create equipment
PUT    /api/v1/equipment/456            - Update equipment
```

#### Inventory
```
GET    /api/v1/inventory                - List inventory
POST   /api/v1/inventory/check-out      - Check out items
```

#### Other Resources
```
/api/v1/maintenance
/api/v1/users
/api/v1/assets
/api/v1/vendors
```

### Example: Create Work Order

**Request:**
```bash
curl -X POST https://your-cmms/api/v1/work_orders \
  -H "X-API-Key: your-key" \
  -H "Content-Type: application/json" \
  -d '{
    "title": "Replace pump seal",
    "description": "Main cooling loop pump maintenance",
    "mechanic_id": 5,
    "due_date": "2026-03-25",
    "priority": "High"
  }'
```

**Response (201 Created):**
```json
{
  "success": true,
  "message": "Resource created",
  "data": {
    "id": 123,
    "wo_id": "WO-1234567890",
    "title": "Replace pump seal",
    "status": "Pending",
    "created_date": "2026-03-19T10:30:00Z"
  }
}
```

### Pagination

List endpoints support pagination:
```bash
GET /api/v1/work_orders?page=2&per_page=50
```

Response includes:
```json
{
  "success": true,
  "data": [...],
  "pagination": {
    "page": 2,
    "per_page": 50,
    "total": 500,
    "total_pages": 10,
    "has_next": true,
    "has_prev": true
  }
}
```

### Error Responses

```json
{
  "success": false,
  "error": "Invalid input",
  "code": 422,
  "errors": {
    "title": "Required field",
    "mechanic_id": "Invalid mechanic"
  }
}
```

---

## 2. ERP Integrations

### SAP Integration

**Configuration:**
```php
$sap_config = [
    'host' => 'https://sap-server.example.com',
    'username' => 'cmms_user',
    'password' => 'secure_password',
    'controlling_area' => 'CA01',
    'company_code' => 'CC01',
    'cost_center' => 'CC01'
];

$sap = new SAPConnector($c, $sap_config);
$sap->connect();
```

**Sync Work Order to SAP:**
```php
$wo_data = [
    'title' => 'Pump maintenance',
    'description' => 'Annual service',
    'equipment_id' => 'EQUIP-001',
    'due_date' => '2026-03-25',
    'priority' => 'High',
    'status' => 'Pending'
];

$result = $sap->syncWorkOrder($wo_id, $wo_data);
// Creates maintenance notification in SAP
```

**Sync Inventory to SAP:**
```php
$sap->syncInventory($inventory_id, $qty_used, $qty_on_hand);
// Updates material master in SAP
```

**Sync GL Entry to SAP:**
```php
$sap->syncGLEntry($wo_id, 1500, '61000');
// Posts maintenance expense to GL account 61000
```

**Fetch Equipment from SAP:**
```php
$equipment = $sap->fetchEquipment();
// Imports equipment master data from SAP
```

### NetSuite Integration

**Configuration:**
```php
$netsuite_config = [
    'client_id' => 'your-client-id',
    'client_secret' => 'your-client-secret',
    'instance_url' => 'https://system.netsuite.com',
    'subsidiary' => '1',
    'warehouse_vendor' => '5'
];

$netsuite = new NetSuiteConnector($c, $netsuite_config);
$netsuite->connect();
```

**Sync Work Order to NetSuite:**
```php
$netsuite->syncWorkOrder($wo_id, $wo_data);
// Creates Support Case in NetSuite
// Stores mapping of WO to NetSuite Case ID
```

**Sync Costs to NetSuite:**
```php
$netsuite->syncGLEntry($wo_id, 2500, '5100');
// Creates journal entry in NetSuite
```

---

## 3. Cloud Storage Integration

### AWS S3

**Configuration:**
```php
$s3_config = [
    'access_key' => 'AKIAIOSFODNN7EXAMPLE',
    'secret_key' => 'wJalrXUtnFEMI/K7MDENG/bPxRfiCYEXAMPLEKEY',
    'bucket' => 'cmms-attachments',
    'region' => 'us-east-1',
    'cloudfront_domain' => 'cdn.example.com'  // Optional
];

$s3 = new S3CloudStorage($c, $s3_config);
$s3->connect();
```

**Upload Work Order Photo:**
```php
$local_file = '/tmp/equipment_photo.jpg';
$remote_path = 'wo/' . $wo_id . '/photo_' . time() . '.jpg';

if ($s3->upload($local_file, $remote_path)) {
    $url = $s3->getPublicURL($remote_path);
    // Store $url in database
}
```

**Download Attachment:**
```php
$s3->download('wo/123/photo_1234567890.jpg', '/var/www/downloads/photo.jpg');
```

### Google Drive

**Configuration:**
```php
$drive_config = [
    'client_id' => 'your-client-id.apps.googleusercontent.com',
    'client_secret' => 'your-secret',
    'access_token' => 'ya29.token...',
    'folder_id' => 'root'  // Or specific folder ID
];

$drive = new GoogleDriveStorage($c, $drive_config);
$drive->connect();
```

**Upload Inspection Report:**
```php
$file_id = $drive->upload('/reports/wo_123_inspection.pdf', 'wo_123_inspection.pdf');
// File ID stored in database for later access
```

### Azure Blob Storage

**Configuration:**
```php
$azure_config = [
    'account_name' => 'cmmsaccount',
    'account_key' => 'your-account-key',
    'container' => 'cmms-files'
];

$azure = new AzureBlobStorage($c, $azure_config);
$azure->connect();
```

---

## 4. GL (General Ledger) Mapping

### Setup GL Account Mapping

**For Equipment Type:**
```php
$gl = new GLMapping($c);

// Map all pump maintenance to account 61200
$gl->mapEquipmentToAccount(
    equipment_id: 42,
    gl_account: '61200',
    cost_center: 'MAINT-001'
);
```

### Create Work Order Journal Entry

**Auto-Create when WO Completed:**
```php
$result = $gl->createWOJournalEntry(
    wo_id: 123,
    amount: 1500.00,
    journal_type: 'WO_COMPLETION'
);

// Returns journal entry in Draft status
// {
//   "journal_entry_id": 456,
//   "debit_account": "61000",
//   "credit_account": "21000",
//   "amount": 1500.00
// }
```

### Post Journal Entry to GL

**Finalize Journal Entry:**
```php
$result = $gl->postJournalEntry(456);
// Journal entry now Posted to GL
// GL transactions created for both accounts
```

### Cost Allocation (Split across multiple GL accounts)

**Example: Allocate pump maintenance cost:**
```php
$gl->allocateWOCost(123, [
    ['account' => '61000', 'percentage' => 60, 'description' => 'Labor'],
    ['account' => '61500', 'percentage' => 25, 'description' => 'Parts'],
    ['account' => '61600', 'percentage' => 15, 'description' => 'Misc']
]);

// $1500 WO split as:
// - $900 to account 61000
// - $375 to account 61500
// - $225 to account 61600
```

### Get Account Balance

**Generate Report:**
```php
$balance = $gl->getAccountBalance('61000', '2026-01-01', '2026-03-31');

// Returns:
// {
//   "account_code": "61000",
//   "debits": 15000.00,
//   "credits": 0.00,
//   "balance": 15000.00
// }
```

---

## 5. Webhooks (Optional)

Subscribe to CMMS events:

```
POST /api/v1/webhooks/subscribe
{
  "event": "work_order.completed",
  "url": "https://your-system.com/webhook/wo-completed",
  "secret": "webhook-secret-key"
}
```

**Webhook Payload:**
```json
{
  "event": "work_order.completed",
  "timestamp": "2026-03-19T15:30:00Z",
  "data": {
    "wo_id": 123,
    "wo_number": "WO-1234567890",
    "title": "Pump seal replacement",
    "completion_date": "2026-03-19T15:30:00Z",
    "total_cost": 1500.00
  }
}
```

---

## 6. Database Schema

### API Tables
```sql
CREATE TABLE api_clients (
  id INT PRIMARY KEY AUTO_INCREMENT,
  user_id INT,
  client_name VARCHAR(255),
  api_key VARCHAR(255) UNIQUE,
  api_secret VARCHAR(255),
  redirect_uri VARCHAR(500),
  active BOOLEAN DEFAULT 1,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  last_used TIMESTAMP
);

CREATE TABLE api_tokens (
  id INT PRIMARY KEY AUTO_INCREMENT,
  user_id INT,
  token VARCHAR(255) UNIQUE,
  client_name VARCHAR(255),
  expires_at TIMESTAMP NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  last_used TIMESTAMP,
  active BOOLEAN DEFAULT 1
);

CREATE TABLE api_logs (
  id INT PRIMARY KEY AUTO_INCREMENT,
  client_id INT,
  endpoint VARCHAR(500),
  method VARCHAR(10),
  status_code INT,
  response_time_ms INT,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
```

### ERP Mapping Tables
```sql
CREATE TABLE erp_mappings (
  id INT PRIMARY KEY AUTO_INCREMENT,
  cmms_id INT,
  erp_id VARCHAR(255),
  erp_system VARCHAR(50),  -- 'SAP', 'NetSuite', 'Oracle'
  entity_type VARCHAR(50), -- 'WorkOrder', 'Inventory', etc.
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY (cmms_id, erp_system, entity_type)
);

CREATE TABLE erp_sync_log (
  id INT PRIMARY KEY AUTO_INCREMENT,
  system VARCHAR(50),
  message TEXT,
  status VARCHAR(20), -- 'INFO', 'WARN', 'ERROR'
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
```

### GL Tables
```sql
CREATE TABLE gl_journal_entries (
  id INT PRIMARY KEY AUTO_INCREMENT,
  entity_type VARCHAR(50),
  entity_id INT,
  journal_type VARCHAR(50),
  debit_account VARCHAR(20),
  debit_amount DECIMAL(12,2),
  credit_account VARCHAR(20),
  credit_amount DECIMAL(12,2),
  description TEXT,
  status VARCHAR(20), -- 'Draft', 'Posted'
  created_by INT,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  posted_date TIMESTAMP NULL
);

CREATE TABLE gl_transactions (
  id INT PRIMARY KEY AUTO_INCREMENT,
  account_code VARCHAR(20),
  debit_amount DECIMAL(12,2),
  credit_amount DECIMAL(12,2),
  transaction_type VARCHAR(10),
  journal_entry_id INT,
  transaction_date DATE,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE equipment_gl_mappings (
  id INT PRIMARY KEY AUTO_INCREMENT,
  equipment_id INT,
  gl_account VARCHAR(20),
  cost_center VARCHAR(20),
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY (equipment_id)
);
```

---

## 7. Implementation Checklist

### API Setup
- [ ] Configure API database tables
- [ ] Register API clients: `APIAuth::registerClient($c, $user_id, 'My App')`
- [ ] Generate API keys for external partners
- [ ] Test endpoints with Postman or similar

### ERP Integration
- [ ] Install ERP SDK (e.g., SAP JCo, NetSuite SDK)
- [ ] Configure ERP credentials in CMMS
- [ ] Test ERP connection: `$sap->testConnection()`
- [ ] Map CMMS PMs to ERP maintenance orders
- [ ] Schedule nightly sync jobs

### Cloud Storage
- [ ] Choose provider (S3, Google Drive, Azure)
- [ ] Create storage bucket/container
- [ ] Configure provider credentials
- [ ] Test upload/download operations

### GL Mapping
- [ ] Define GL account chart of accounts
- [ ] Map equipment types to GL accounts
- [ ] Set default accounts for different WO types
- [ ] Configure cost center allocation

---

## 8. Security Best Practices

✅ **API Keys**
- Rotate keys regularly
- Use different keys per client
- Monitor key usage logs
- Revoke compromised keys immediately

✅ **Cloud Storage**
- Use service accounts (not user credentials)
- Enable encryption (S3 SSE, Azure encryption)
- Configure bucket policies (private by default)
- Use temporary pre-signed URLs

✅ **ERP Connections**
- Encrypt credentials in database
- Use VPN for ERP connections
- Whitelist IP addresses
- Monitor ERP audit logs

✅ **GL Posting**
- Require approval for GL entries above threshold
- Maintain complete audit trail
- Reconcile GL entries weekly
- Use balanced journal entries only

---

## Support & Resources

- API Documentation: `/api/v1/docs`
- ERP Integration Guide: See vendor documentation
- S3 Setup: https://aws.amazon.com/s3/
- NetSuite API: https://developers.netsuite.com/
- Google Drive API: https://developers.google.com/drive
