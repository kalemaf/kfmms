INTEGRATION IMPLEMENTATION CHECKLIST
=====================================

Date Started: _____________
Completed By: _____________
Environment: [ ] Development  [ ] Staging  [ ] Production

## PHASE 1: DATABASE SETUP (Estimated: 1-2 hours)

### 1.1 API Tables
- [ ] Create api_clients table
- [ ] Create api_tokens table
- [ ] Create api_logs table
- [ ] Verify no duplicate records after creation
- [ ] Test insert: INSERT INTO api_clients (client_name, api_key, api_secret)

### 1.2 ERP Tables
- [ ] Create erp_mappings table (cmms_id ↔ sap_id / netsuite_id)
- [ ] Create erp_sync_log table (tracks all sync operations)
- [ ] Create wo_to_sap_mapping table
- [ ] Create wo_to_netsuite_mapping table
- [ ] Add indexes on mapping tables for fast lookups

### 1.3 GL Accounting Tables
- [ ] Create chart_of_accounts table
- [ ] Create gl_journal_entries table
- [ ] Create gl_transactions table (debits/credits)
- [ ] Create equipment_gl_mappings table
- [ ] Create wo_cost_allocations table
- [ ] Insert default GL accounts (61000, 21000, etc.)
- [ ] Verify account structure matches your chart

### 1.4 Cloud Storage Tables
- [ ] Create cloud_storage_log table
- [ ] Add indexes on object_path for search performance

**Database Verification:**
```bash
php -c config.inc.php -r "
require_once 'config.inc.php';
\$result = mysqli_query(\$c, 'SHOW TABLES LIKE \"api_%\"');
echo mysqli_num_rows(\$result) . ' API tables found\n';
"
```

---

## PHASE 2: API CONFIGURATION (Estimated: 30 mins)

### 2.1 Register First API Client
```bash
php integration_quickstart_examples.php
```
Look for line: 
```
API Key: xxxx-xxxx-xxxx
API Secret: yyyy-yyyy-yyyy
```

Save these securely (password manager, NOT code)

- [ ] API Key stored securely
- [ ] API Secret stored securely
- [ ] Test API call with key: `curl -H "X-API-Key: xxx" http://localhost/api/v1/work_orders`

### 2.2 Create Bearer Token
```php
require_once 'api/api_auth.php';
$token = APIAuth::createToken($c, 1, 'My App');
// Returns: ['token' => '...', 'expires' => '...']
```

- [ ] Bearer token generated
- [ ] Token stored for mobile/external apps
- [ ] Token expiration date noted: ________________

### 2.3 API Rate Limiting
- [ ] Confirm rate limit: 1000 requests/hour (in api_auth.php line ~140)
- [ ] Adjust if needed for your traffic: `define('API_RATE_LIMIT', 1000);`
- [ ] Test rate limiting: Hit API 1001 times in 1 hour, confirm 429 response

### 2.4 CORS Configuration
- [ ] Update CORS_ORIGINS in /api/v1/index.php:
  ```php
  define('CORS_ORIGINS', [
      'https://your-domain.com',
      'https://app.your-domain.com'
  ]);
  ```
- [ ] Add any third-party integration domains (SAP, NetSuite, etc.)

**API Testing:**
```bash
curl -X GET https://your-cmms.com/api/v1/work_orders \
  -H "X-API-Key: xxx" \
  -H "Content-Type: application/json"
```

Expected response should be JSON with work orders array

---

## PHASE 3: SAP INTEGRATION (Estimated: 2-4 hours)

### 3.1 SAP Environment Details
- [ ] SAP Host URL: _____________________________
- [ ] SAP Company Code: _____________________________
- [ ] SAP Controlling Area: _____________________________
- [ ] SAP Cost Center: _____________________________
- [ ] SAP Username: _____________________________
- [ ] SAP Password: _____________________________ (store in env var)

### 3.2 SAP Configuration Code
Update `integrations/SAPConnector.php` line ~20:

```php
if (empty($config)) {
    $config = [
        'host' => $_ENV['SAP_HOST'],
        'username' => $_ENV['SAP_USER'],
        'password' => $_ENV['SAP_PASSWORD'],
        'company_code' => $_ENV['SAP_COMPANY_CODE'],
        'controlling_area' => $_ENV['SAP_CONTROLLING_AREA'],
        'cost_center' => $_ENV['SAP_COST_CENTER']
    ];
}
```

- [ ] Environment variables created (.env file or server env)
- [ ] Credentials tested: `$sap->testConnection()`
- [ ] Result shows: "SAP Connection Successful"

### 3.3 SAP OData Endpoints
Confirm these exist in your SAP system (ask SAP admin):

- [ ] `/sap/opu/odata/sap/c_workorderheader_cds/` (Work orders)
- [ ] `/sap/opu/odata/sap/c_material_cds/` (Materials/Equipment)
- [ ] `/sap/opu/odata/sap/c_journalentry_cds/` (GL entries)

Ask SAP admin to provide equivalent endpoints if different

### 3.4 SAP Authorization
Create SAP user with permissions for:

- [ ] Create maintenance notifications (PM01)
- [ ] Change maintenance notifications (PM02)
- [ ] Post goods movements (MB1C)
- [ ] Create journal entries (FB50)
- [ ] Display equipment (IE02)

Testing:
```bash
php -r "
require_once 'config.inc.php';
require_once 'integrations/SAPConnector.php';

\$config = [
    'host' => $_ENV['SAP_HOST'],
    'username' => $_ENV['SAP_USER'],
    'password' => $_ENV['SAP_PASSWORD'],
    'company_code' => 'XXXX',
    'controlling_area' => 'XXXX',
    'cost_center' => 'XXXX'
];

\$sap = new SAPConnector(\$c, \$config);
if (\$sap->connect()) {
    \$result = \$sap->testConnection();
    echo \$result['message'] . '\\n';
} else {
    echo 'Failed to connect\\n';
}
"
```

Expected: "✓ SAP Connection Successful"

- [ ] SAP connection test passed
- [ ] User created in SAP with proper permissions
- [ ] First work order synced successfully

---

## PHASE 4: NETSUITE INTEGRATION (Estimated: 2-3 hours)

### 4.1 NetSuite OAuth Setup
- [ ] NetSuite Account ID: _____________________________
- [ ] NetSuite Realm (domain): _____________________________
- [ ] Create OAuth Application in NetSuite:
  - [ ] Name: CMMS Integration
  - [ ] Redirect URI: `https://your-cmms.com/api/oauth/callback`
  - [ ] OAuth Client ID: _____________________________
  - [ ] OAuth Client Secret: _____________________________ (SECURE!)

### 4.2 NetSuite Configuration
Update `integrations/NetSuiteConnector.php` line ~25:

```php
$config = [
    'client_id' => $_ENV['NETSUITE_CLIENT_ID'],
    'client_secret' => $_ENV['NETSUITE_CLIENT_SECRET'],
    'instance_url' => 'https://xxxx-api.netsuite.com',  // From account settings
    'subsidiary' => '1'
];
```

- [ ] OAuth credentials in environment variables
- [ ] Instance URL confirmed
- [ ] Subsidiary ID verified in NetSuite

### 4.3 NetSuite Custom Fields
Create custom fields for CMMS data:

In NetSuite: Customization > Lists, Records & Fields > Custom Fields > Support Cases

- [ ] custfield_cmms_wo_id (Text field) - for work order reference
- [ ] custfield_cmms_status (Text field) - for status sync
- [ ] custfield_cmms_priority (Dropdown) - allows Pending/In Progress/Complete

Getting Field IDs:
```bash
# In NetSuite, go to Setup > Integration > Web Services > OAuth 2.0 Tokens
# Generate token, then call API:
curl -X GET "https://xxxx.suitetalk.api.netsuite.com/services/rest/query/v1/suiteql" \
  -H "Authorization: Bearer #{access_token}" \
  -H "Content-Type: application/json" \
  -d '{"q":"SELECT * FROM customFieldType LIMIT 5"}'
```

Update `NetSuiteConnector.php` line ~40 with actual field IDs:

```php
'custom_fields' => [
    'cmms_wo_id' => 'custfield_123456',
    'cmms_status' => 'custfield_123457',
    'cmms_priority' => 'custfield_123458'
]
```

- [ ] Custom fields created in NetSuite
- [ ] Field IDs added to NetSuiteConnector.php
- [ ] Test OAuth connection: `$ns->testConnection()`

### 4.4 NetSuite Permissions
Request NetSuite admin to grant permissions:

- [ ] View Support Cases
- [ ] Create Support Cases
- [ ] Edit Support Cases
- [ ] View Contacts
- [ ] View Items
- [ ] Create Journal Entries
- [ ] Edit Journal Entries

Testing:
```bash
php -r "
require_once 'config.inc.php';
require_once 'integrations/NetSuiteConnector.php';

\$config = [...];  // Your config from 4.2
\$ns = new NetSuiteConnector(\$c, \$config);
if (\$ns->connect()) {
    \$result = \$ns->testConnection();
    echo \$result['message'] . '\\n';
}
"
```

- [ ] NetSuite connection test passed
- [ ] OAuth token refreshes properly
- [ ] First work order synced as Support Case

---

## PHASE 5: CLOUD STORAGE SETUP (Estimated: 2 hours per provider)

### 5.1 AWS S3 (if using)

- [ ] AWS Account created
- [ ] S3 Bucket name: _____________________________
- [ ] S3 Region: _____________________________
- [ ] AWS Access Key ID: _____________________________
- [ ] AWS Secret Access Key: _____________________________ (SECURE!)

Configuration in code:
```php
$s3 = new S3CloudStorage($c, [
    'access_key' => $_ENV['AWS_ACCESS_KEY_ID'],
    'secret_key' => $_ENV['AWS_SECRET_ACCESS_KEY'],
    'bucket' => $_ENV['AWS_BUCKET'],
    'region' => $_ENV['AWS_REGION']
]);
```

IAM Permissions (create policy):
```json
{
    "Version": "2012-10-17",
    "Statement": [
        {
            "Effect": "Allow",
            "Action": [
                "s3:GetObject",
                "s3:PutObject",
                "s3:DeleteObject"
            ],
            "Resource": "arn:aws:s3:::your-bucket/*"
        }
    ]
}
```

- [ ] S3 bucket created
- [ ] IAM user created with above permissions
- [ ] Access key and secret in environment variables
- [ ] Test upload: `$s3->upload('test.pdf', 'test/test.pdf')`
- [ ] Verify file appears in S3 console
- [ ] Test download: Download file, verify content

### 5.2 Google Drive (if using)

- [ ] Google Cloud Project created
- [ ] OAuth 2.0 Client ID (Web): _____________________________
- [ ] OAuth 2.0 Client Secret: _____________________________ (SECURE!)
- [ ] Redirect URI registered: `https://your-cmms.com/api/oauth/google_callback`
- [ ] Google Folder ID for uploads: _____________________________

OAuth Token Generation (first time):
```bash
# 1. Redirect user to:
# https://accounts.google.com/o/oauth2/v2/auth?
#   client_id=YOUR_CLIENT_ID&
#   redirect_uri=https://your-cmms.com/api/oauth/google_callback&
#   response_type=code&
#   scope=https://www.googleapis.com/auth/drive

# 2. User authorizes, redirected back with ?code=XXX
# 3. Exchange code for token:
curl -X POST https://oauth2.googleapis.com/token \
  -d "code=XXX&client_id=YOUR_ID&client_secret=YOUR_SECRET&grant_type=authorization_code&redirect_uri=YOUR_REDIRECT"
```

Store the returned `access_token` and `refresh_token`

- [ ] Google Cloud project created
- [ ] OAuth 2.0 credentials configured
- [ ] First-time authorization completed
- [ ] Access token and refresh token stored
- [ ] Test upload works

### 5.3 Azure Blob Storage (if using)

- [ ] Azure Storage Account created: _____________________________
- [ ] Storage Account Key: _____________________________ (SECURE!)
- [ ] Container name: _____________________________

Configuration:
```php
$azure = new AzureBlobStorage($c, [
    'account_name' => $_ENV['AZURE_ACCOUNT_NAME'],
    'account_key' => $_ENV['AZURE_ACCOUNT_KEY'],
    'container' => $_ENV['AZURE_CONTAINER']
]);
```

- [ ] Azure Storage Account created
- [ ] Container created
- [ ] Storage key in environment variables
- [ ] Test upload successful
- [ ] Verify file in Azure portal

**Multi-Provider Testing:**
```bash
# Create test upload to all 3 providers
php -r "
require_once 'integrations/CloudStorageProvider.php';

\$test_file = tempfile();
file_put_contents(\$test_file, 'Test data ' . date('Y-m-d H:i:s'));

\$s3 = new S3CloudStorage(\$c, [...]);
\$s3->upload(\$test_file, 'test_s3.txt');
echo '✓ S3 upload OK\\n';

\$drive = new GoogleDriveStorage(\$c, [...]);
\$drive->upload(\$test_file, 'test_google.txt');
echo '✓ Google Drive upload OK\\n';

\$azure = new AzureBlobStorage(\$c, [...]);
\$azure->upload(\$test_file, 'test_azure.txt');
echo '✓ Azure upload OK\\n';
"
```

---

## PHASE 6: GL ACCOUNTING SETUP (Estimated: 1-2 hours)

### 6.1 Chart of Accounts
Create or verify GL accounts in your accounting system:

**Maintenance Accounts:**
- [ ] 61000 (Maintenance Labor) - primary expense account
- [ ] 61500 (Maintenance Parts & Equipment)
- [ ] 61600 (Maintenance Miscellaneous)
- [ ] 61700 (Maintenance Subcontractors)

**Payable Accounts:**
- [ ] 21000 (Accrued Payables) - credit account for WO completion
- [ ] 21100 (Payroll Liabilities)

**Asset Accounts (Optional):**
- [ ] 15000 (Equipment - Fixed Assets)
- [ ] 15100 (Accumulated Equipment Depreciation)

Or use your existing GL structure if different

- [ ] GL accounts verified/created
- [ ] Account codes matching your system noted

### 6.2 GL Configuration in CMMS
Update `integrations/GLMapping.php` line ~30:

```php
const DEFAULT_DEBIT_ACCOUNT = '61000';      // Maintenance Labor
const DEFAULT_CREDIT_ACCOUNT = '21000';     // Payables
const DEFAULT_COST_CENTER = 'MAINT';
```

- [ ] Default accounts configured
- [ ] Cost center code verified

### 6.3 Equipment GL Mapping
Link equipment types to GL accounts:

```php
\$gl = new GLMapping(\$c);
\$gl->mapEquipmentToAccount(
    equipment_id: 1,      // Pump equipment group
    gl_account: '61200',  // Pumps-specific account
    cost_center: 'MAINT01'
);
```

**Typical Equipment-to-GL Mapping:**
- [ ] Pumps → 61200
- [ ] Motors → 61210
- [ ] Compressors → 61220
- [ ] Chillers → 61230
- [ ] HVAC → 61240
- [ ] Electrical → 61250

How to implement:
```bash
php -r "
require_once 'config.inc.php';
require_once 'integrations/GLMapping.php';

\$gl = new GLMapping(\$c);

// Get all equipment
\$result = mysqli_query(\$c, 'SELECT id, equipment_type FROM equipment LIMIT 20');

while (\$row = mysqli_fetch_assoc(\$result)) {
    // Determine GL account based on type
    \$account = match(\$row['equipment_type']) {
        'Pump' => '61200',
        'Motor' => '61210',
        'Compressor' => '61220',
        default => '61000'
    };
    
    \$gl->mapEquipmentToAccount(\$row['id'], \$account, 'MAINT');
    echo \$row['id'] . ' -> ' . \$account . '\\n';
}
"
```

- [ ] Equipment GL mappings created
- [ ] At least 10 equipment items mapped

### 6.4 GL Entry Approval Workflow
Decide on GL approval process:

- [ ] Auto-approve (set to Posted immediately) → Risk: Audit trail
- [ ] Manual approval (Draft → Reviewed → Posted) → Recommended
- [ ] Automatic posting after 24 hours

If using manual approval, create dashboard:
```php
// Show draft GL entries awaiting approval
SELECT je.*, wo.title, wo.mechanic_id
FROM gl_journal_entries je
JOIN work_orders wo ON je.work_order_id = wo.id
WHERE je.status = 'Draft'
ORDER BY je.created_date DESC
```

- [ ] GL approval workflow decided
- [ ] Dashboard created if using manual approval

---

## PHASE 7: SYSTEM INTEGRATION (Estimated: 1-2 hours)

### 7.1 Work Order Completion Hook
When WO marked complete, trigger:
1. GL entry creation
2. SAP sync
3. NetSuite sync
4. PM instance status update

Current flow in mark_wo_complete.php or similar:
```php
// Existing: mark WO complete
mysqli_query($c, "UPDATE work_orders SET wo_status='Completed' WHERE id=$wo_id");

// ADD THESE:

// 1. Create GL entry
require_once 'integrations/GLMapping.php';
$gl = new GLMapping($c);
$wo = mysqli_fetch_assoc(mysqli_query($c, "SELECT * FROM work_orders WHERE id=$wo_id"));
$gl->createWOJournalEntry($wo_id, $wo['labor_cost'] + $wo['parts_cost']);

// 2. Sync to SAP
require_once 'integrations/SAPConnector.php';
$sap = new SAPConnector($c, [...]);
if ($sap->connect()) {
    $sap->syncWorkOrder($wo_id, $wo);
}

// 3. Sync to NetSuite
require_once 'integrations/NetSuiteConnector.php';
$ns = new NetSuiteConnector($c, [...]);
if ($ns->connect()) {
    $ns->syncWorkOrder($wo_id, $wo);
}

// 4. Update PM instance status
require_once 'pm_auto_sync_on_wo_complete.php';
```

- [ ] GL entry creation code added
- [ ] SAP sync code added
- [ ] NetSuite sync code added
- [ ] PM instance sync code added
- [ ] Tested: Complete a WO, verify GL entry created and synced

### 7.2 Equipment Import from ERP
Optionally auto-import equipment from SAP/NetSuite:

```bash
# Weekly task via cron:
php -r "
require_once 'config.inc.php';
require_once 'integrations/SAPConnector.php';

\$sap = new SAPConnector(\$c, [...config...]);
if (\$sap->connect()) {
    \$equipment = \$sap->fetchEquipment();
    echo 'Imported ' . count(\$equipment) . ' equipment items\\n';
}
"
```

- [ ] Equipment import tested
- [ ] Cron job created (if desired): `0 2 * * 0` (weekly, 2 AM Sunday)

### 7.3 Inventory Sync
If using inventory module, sync stock levels to ERP:

```bash
# Daily auto-sync via Windows Task Scheduler:
C:\Program Files\PHP\php.exe -f "c:\free-cmms 0.04\inventory_sync_to_erp.php"
```

- [ ] Inventory sync code created (or use existing)
- [ ] Task Scheduler/ cron job configured

---

## PHASE 8: TESTING & VALIDATION (Estimated: 2-4 hours)

### 8.1 End-to-End Test
Create a test work order and verify complete flow:

```bash
1. Create WO via API:
   POST /api/v1/work_orders
   { "title": "Test Pump Overhaul", "mechanic_id": 1, ... }

2. Upload attachment:
   POST /api/v1/work_orders/123/attachment
   - Verify uploaded to S3/Drive/Azure

3. Mark Complete:
   PUT /api/v1/work_orders/123
   { "status": "Completed" }

4. Verify:
   ✓ GL journal entry created (check gl_journal_entries)
   ✓ SAP notification created (check SAP system)
   ✓ NetSuite support case created (check NetSuite)
   ✓ PM instance marked Completed (check pm_instances)
   ✓ Attachment accessible from cloud storage
```

Checklist:
- [ ] Test WO created via API
- [ ] Attachment uploaded to cloud
- [ ] WO marked complete
- [ ] GL entry visible in CMMS
- [ ] GL entry visible in SAP
- [ ] GL entry visible in NetSuite
- [ ] PM instance status synced
- [ ] Cloud storage file accessible

### 8.2 Load Test
Verify system handles realistic load:

```bash
# 100 concurrent API requests
ab -n 1000 -c 100 https://your-cmms.com/api/v1/work_orders \
  -H "X-API-Key: xxx"

# Expected: <1% failure rate, response time <500ms
```

- [ ] Load test passed
- [ ] Response times acceptable

### 8.3 Error Handling
Test error scenarios:

| Test | Expected Result | Verified |
|------|-----------------|----------|
| Invalid API key | 401 Unauthorized | [ ] |
| Missing required field | 422 Validation Error | [ ] |
| Rate limit exceeded | 429 Too Many Requests | [ ] |
| SAP connection down | Graceful error, retry | [ ] |
| Cloud storage full | Return error, log in DB | [ ] |
| GL account invalid | Transaction rolled back | [ ] |

- [ ] All error handling tested
- [ ] Error messages appropriate (no sensitive info exposed)

### 8.4 Security Testing
- [ ] API keys not logged or visible in UI
- [ ] Passwords not in config files (use env vars)
- [ ] CORS properly restricted to allowed domains
- [ ] Database credentials not in code
- [ ] Rate limiting prevents API abuse
- [ ] SQL injection prevention verified (parameterized queries)

---

## PHASE 9: DOCUMENTATION (Estimated: 1 hour)

- [ ] INTEGRATION_AND_API_GUIDE.md reviewed and customized
- [ ] INTEGRATION_QUICKSTART_EXAMPLES.php customized with your credentials
- [ ] API endpoint documentation generated
- [ ] SAP sync mapping documented
- [ ] NetSuite custom field mapping documented
- [ ] GL account mapping documented
- [ ] Cloud storage file structure documented
- [ ] Backup/recovery procedures documented
- [ ] On-call support procedures documented

---

## PHASE 10: DEPLOYMENT (Estimated: 1-2 hours)

### 10.1 Pre-Deployment Checklist
- [ ] All configurations verified in staging environment
- [ ] Database migrations tested (create tables script)
- [ ] Backup created before database changes
- [ ] Team notified of deployment window
- [ ] Rollback plan documented
- [ ] Monitoring alerts configured

### 10.2 Deployment Steps
1. [ ] Backup database: `mysqldump free_cmms > backup_2026-03-20.sql`
2. [ ] Deploy code to production
3. [ ] Create database tables (script in INTEGRATION_AND_API_GUIDE.md)
4. [ ] Test API endpoints in production
5. [ ] Test SAP connection
6. [ ] Test NetSuite connection
7. [ ] Test cloud storage
8. [ ] Monitor error logs for 1 hour

### 10.3 Post-Deployment
- [ ] Verification email sent to stakeholders
- [ ] Documentation updated with production URLs
- [ ] Support team briefed on new features
- [ ] Monitor for errors (check error_log, database logs)
- [ ] Collect user feedback for first week

---

## PHASE 11: ONGOING MAINTENANCE

### Monthly Tasks
- [ ] Review API usage stats (api_logs table)
- [ ] Check cloud storage costs
- [ ] Review ERP sync errors (erp_sync_log)
- [ ] Audit GL journal entries
- [ ] Backup database and verify restore

### Quarterly Tasks
- [ ] Update API documentation
- [ ] Review and adjust rate limiting
- [ ] Analyze GL account balances
- [ ] Plan for ERP version upgrades

### Annual Tasks
- [ ] Security audit of API
- [ ] Performance optimization review
- [ ] Disaster recovery drill
- [ ] Update API deprecation policies

---

## COMPLETION SIGN-OFF

**Integration Implementation Completed By:**

Name: ____________________  Date: ________________

Role: ____________________  Signature: ________________

**Approved By:**

Name: ____________________  Date: ________________

Role (IT Director/Manager): ______________________  Signature: ________________

**Go-Live Date: ____________________**

**Support Contact (for issues): ____________________________**

**Escalation Contact (urgent): ____________________________**

---

## ROLLBACK PROCEDURE (If Issues)

If critical issues arise post-deployment:

1. Immediate: Stop API by disabling `/api/v1/index.php`
   ```php
   <?php http_response_code(503); exit('Temporarily unavailable'); ?>
   ```

2. Database: Restore from backup
   ```bash
   mysql free_cmms < backup_2026-03-20.sql
   ```

3. Code: Revert to previous version
   ```bash
   git checkout HEAD~1
   git push origin production
   ```

4. ERP Sync: Verify no partially-synced transactions
   ```sql
   SELECT * FROM erp_sync_log WHERE status='Pending';
   ```

5. Notify: Contact SAP & NetSuite admins of rollback

6. Post-Incident: Document what went wrong, update procedures

**Rollback Contact: ____________________**

**Rollback Approval Required From: ____________________**

---

## NOTES

Use this section to track issues, decisions, and lessons learned:

_________________________________________________________________

_________________________________________________________________

_________________________________________________________________

_________________________________________________________________

---

Last Updated: 2026-03-20
Version: 1.0 - Initial Deployment Checklist
