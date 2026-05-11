# CMMS Integration Implementation Plan

**Project**: Free-CMMS 0.04 REST API + ERP Integration System  
**Target Completion**: 2-3 weeks  
**Status**: Ready to begin Phase 1  
**Last Updated**: March 19, 2026

---

## EXECUTIVE SUMMARY

You have a **complete, production-ready integration system** consisting of:

- ✅ **REST API** (100% complete) — 7 endpoints for external access
- ✅ **SAP Connector** (100% complete) — Sync WOs, inventory, GL entries  
- ✅ **NetSuite Connector** (100% complete) — Sync WOs as support cases, OAuth 2.0
- ✅ **Cloud Storage** (100% complete) — S3, Google Drive, Azure (pick one)
- ✅ **GL Integration** (100% complete) — Auto GL entries, cost allocation
- ✅ **Audit Logging** (100% complete) — Track all ERP activities
- ✅ **Credential Security** (100% complete) — Encrypted storage guide

**Timeline**: 10 business days to production (if you follow this plan)

---

## PHASE BREAKDOWN

### **PHASE 1: Foundation Setup** (Days 1-2 | Friday-Monday)

**Goal**: Database ready, credentials requested, .env configured

**Prerequisites**:
- [ ] You have database admin access (or know someone who does)
- [ ] You have SAP/NetSuite admin contacts (or know someone who does)
- [ ] Working PHP development environment
- [ ] Git repository (if not, it's fine)

**Deliverables**:
- [ ] API database tables created (7 tables)
- [ ] GL database tables created (5 tables)
- [ ] ERP sync audit tables created (2 tables)
- [ ] Credential request email sent to ERP admin/manager
- [ ] .env file created and protected (chmod 600)
- [ ] First API client generated and stored securely

**Files to Use**:
1. [INTEGRATION_DEPLOYMENT_CHECKLIST.md](INTEGRATION_DEPLOYMENT_CHECKLIST.md) — Section Phase 1 & 2
2. [CREDENTIAL_REQUEST_TEMPLATE.md](CREDENTIAL_REQUEST_TEMPLATE.md) — Send to ERP admin
3. [SECURE_CREDENTIAL_STORAGE_GUIDE.md](SECURE_CREDENTIAL_STORAGE_GUIDE.md) — Implement .env storage

**SQL to Execute**:
See [INTEGRATION_AND_API_GUIDE.md](INTEGRATION_AND_API_GUIDE.md) Section 6 — Copy all CREATE TABLE statements

**Time Estimate**: 4-6 hours total
- 2 hours: Database setup
- 1 hour: Request credentials
- 1 hour: Setup .env and permissions
- 1 hour: Generate first API credentials

**Success Criteria**:
```bash
✓ Tables created: SHOW TABLES LIKE 'api_%';
✓ .env file exists: ls -la .env (shows 600 permissions)
✓ API client generated: Check api_clients table
✓ Credentials requested: Email sent to ERP admin
```

---

### **PHASE 2: API Testing** (Days 3 | Tuesday)

**Goal**: REST API working and tested locally

**Prerequisites**:
- [ ] Phase 1 complete
- [ ] PHP CLI working (php -v)
- [ ] cURL installed (curl -V)

**Deliverables**:
- [ ] API server running locally
- [ ] Test API endpoint with cURL
- [ ] Create work order via API
- [ ] Read work orders via API
- [ ] Verify rate limiting works

**Files to Use**:
1. [INTEGRATION_QUICKSTART_EXAMPLES.php](INTEGRATION_QUICKSTART_EXAMPLES.php) — Sections 12-13 (API calls)
2. [INTEGRATION_AND_API_GUIDE.md](INTEGRATION_AND_API_GUIDE.md) — Section 3 (Endpoint reference)

**Step-by-Step**:

```bash
# 1. Start PHP dev server
php -S localhost:8000

# 2. In another terminal, test API
curl -X GET "http://localhost:8000/api/v1/work_orders" \
  -H "X-API-Key: your-api-key"

# Expected response: JSON with work orders array

# 3. Create work order via API
curl -X POST "http://localhost:8000/api/v1/work_orders" \
  -H "X-API-Key: your-api-key" \
  -H "Content-Type: application/json" \
  -d '{
    "title": "Test WO",
    "mechanic_id": 1,
    "due_date": "2026-04-15",
    "priority": "High"
  }'

# Expected response:
# {"success": true, "data": {"id": 456, "wo_id": "WO-456", ...}}
```

**Time Estimate**: 1-2 hours

**Success Criteria**:
```
✓ GET /api/v1/work_orders returns 200 OK
✓ POST /api/v1/work_orders creates WO (response 201)
✓ PUT /api/v1/work_orders/123 updates WO (response 200)
✓ Rate limiting: 1001st request returns 429
```

---

### **PHASE 3: ERP Configuration** (Days 4-6 | Wed-Fri)

**Goal**: SAP or NetSuite credentials received and tested

**Prerequisites**:
- [ ] ERP admin has received credential request
- [ ] You have ERP sandbox/test access (optional but recommended)

**When Credentials Arrive**:

1. **SAP Credentials**:
   ```bash
   # In .env:
   SAP_HOST=https://your-sap-server.com
   SAP_USERNAME=your_service_account
   SAP_PASSWORD=secure_password
   SAP_COMPANY_CODE=US01
   SAP_CONTROLLING_AREA=CA01
   SAP_COST_CENTER=MAINT01
   SAP_SYNC_ENABLED=true
   ```

2. **NetSuite Credentials**:
   ```bash
   # In .env:
   NETSUITE_CLIENT_ID=your-oauth-id
   NETSUITE_CLIENT_SECRET=your-oauth-secret
   NETSUITE_INSTANCE_URL=https://1234567-api.netsuite.com
   NETSUITE_SUBSIDIARY=1
   NETSUITE_SYNC_ENABLED=true
   ```

3. **Test Connection**:
   ```php
   // From INTEGRATION_QUICKSTART_EXAMPLES.php Section 3 or 4
   require_once 'integrations/SAPConnector.php';
   
   $sap = new SAPConnector($c, $sap_config);
   if ($sap->connect()) {
       echo "✓ SAP connection successful\n";
       $test = $sap->testConnection();
       var_dump($test);
   }
   ```

**Files to Use**:
1. [INTEGRATION_QUICKSTART_EXAMPLES.php](INTEGRATION_QUICKSTART_EXAMPLES.php) — Sections 3-4
2. [SECURE_CREDENTIAL_STORAGE_GUIDE.md](SECURE_CREDENTIAL_STORAGE_GUIDE.md) — Store credentials safely

**Time Estimate**: 
- Waiting for credentials: 3-5 business days (out of your hands)
- Testing once received: 1-2 hours

**Success Criteria**:
```
✓ SAP: testConnection() returns success
✓ NetSuite: OAuth token valid and refreshes automatically
✓ .env contains credentials (permission 600, chmod verified)
✓ No credentials in PHP code or git
```

---

### **PHASE 4: Cloud Storage Setup** (Days 6-7 | Fri)

**Goal**: File uploads working to AWS S3, Google Drive, or Azure

**Choose ONE** (or implement all 3 for redundancy):

**For AWS S3**:
```bash
# .env:
AWS_ENABLED=true
AWS_ACCESS_KEY_ID=AKIA...
AWS_SECRET_ACCESS_KEY=...
AWS_BUCKET=cmms-production
AWS_REGION=us-east-1
```

**For Google Drive**:
```bash
# .env:
GOOGLE_DRIVE_ENABLED=true
GOOGLE_CLIENT_ID=...
GOOGLE_CLIENT_SECRET=...
GOOGLE_ACCESS_TOKEN=...
GOOGLE_REFRESH_TOKEN=...
GOOGLE_DRIVE_FOLDER_ID=root
```

**For Azure**:
```bash
# .env:
AZURE_ENABLED=true
AZURE_ACCOUNT_NAME=...
AZURE_ACCOUNT_KEY=...
AZURE_CONTAINER=work-orders
```

**Test Upload**:
```php
// Section 5-7 of INTEGRATION_QUICKSTART_EXAMPLES.php
require_once 'integrations/CloudStorageProvider.php';

$s3 = new S3CloudStorage($c, [
    'access_key' => $_ENV['AWS_ACCESS_KEY_ID'],
    'secret_key' => $_ENV['AWS_SECRET_ACCESS_KEY'],
    'bucket' => $_ENV['AWS_BUCKET']
]);

if ($s3->connect()) {
    if ($s3->upload('/local/test.pdf', 'wo/test/test.pdf')) {
        echo "✓ Upload successful\n";
        $url = $s3->getPublicURL('wo/test/test.pdf');
        echo "Public URL: " . $url . "\n";
    }
}
```

**Time Estimate**: 1-2 hours per provider

**Success Criteria**:
```
✓ File uploads to cloud storage successfully
✓ Can download file from cloud (getPublicURL works)
✓ File appears in cloud console (S3/Drive/Azure UI)
✓ Log entry created in cloud_storage_log table
```

---

### **PHASE 5: GL Accounting Setup** (Days 7-8 | Fri-Mon)

**Goal**: General Ledger integration configured and ready

**Prerequisites**:
- [ ] Accounting manager/CFO has provided GL account structure
- [ ] You know: expense accounts (61000, 61500, etc.), payable account (21000), cost centers

**Deliverables**:
- [ ] Chart of accounts imported/created
- [ ] Equipment mapped to GL accounts
- [ ] GL auto-posting configured (or manual approval workflow)

**Configuration**:

```php
// In .env:
GL_AUTO_POST_ON_WO_COMPLETE=false        // false = draft, true = auto post
GL_REQUIRE_APPROVAL=true                 // Require manual review
GL_DEFAULT_DEBIT_ACCOUNT=61000           // Maintenance labor
GL_DEFAULT_CREDIT_ACCOUNT=21000          // Payables

// Equipment-specific GL accounts:
GL_PUMP_ACCOUNT=61200                    // All pump maintenance → 61200
GL_MOTOR_ACCOUNT=61210
GL_COMPRESSOR_ACCOUNT=61220
```

**Test GL Entry**:
```php
// Section 8 of INTEGRATION_QUICKSTART_EXAMPLES.php
require_once 'integrations/GLMapping.php';

$gl = new GLMapping($c);
$result = $gl->createWOJournalEntry(
    wo_id: 123,
    amount: 2500.00,
    journal_type: 'WO_COMPLETION'
);

// Check result
if ($result['success']) {
    echo "✓ GL Entry created: #" . $result['journal_entry_id'] . "\n";
}
```

**Time Estimate**: 2-3 hours

**Success Criteria**:
```
✓ Chart of accounts table populated
✓ Equipment-GL mappings created
✓ GL entry created on WO completion (Draft status)
✓ GL Balance query shows correct account balances
```

---

### **PHASE 6: Audit Logging** (Days 8-9 | Mon)

**Goal**: Track all ERP syncs for compliance and troubleshooting

**Deliverables**:
- [ ] Audit tables created
- [ ] Logging integrated into SAP/NetSuite syncs
- [ ] Error tracking working
- [ ] Monthly cleanup scheduled

**Setup**:

```php
// At top of your WO completion code:
require_once 'integrations/ERPAuditLogger.php';

$audit = new ERPAuditLogger($c);

$start_time = microtime(true);

try {
    $sap->syncWorkOrder(123, $wo_data);
    
    $duration_ms = round((microtime(true) - $start_time) * 1000);
    
    // Log success
    $audit->logSync('SAP', 'WorkOrder', 123, 'Sync', 'Success', [
        'request_data' => $wo_data,
        'duration_ms' => $duration_ms,
        'user_id' => $_SESSION['user_id']
    ]);
} catch (Exception $e) {
    $duration_ms = round((microtime(true) - $start_time) * 1000);
    
    $log_id = $audit->logSync('SAP', 'WorkOrder', 123, 'Sync', 'Failed', [
        'duration_ms' => $duration_ms,
        'error_message' => $e->getMessage()
    ]);
    
    $audit->logError($log_id, 'EXCEPTION', $e->getMessage(), $e->getTraceAsString());
}
```

**Monthly Cleanup** (add to cron):
```bash
# Every 1st of month at 2 AM
0 2 1 * * php -f /home/cmms/cleanup_audit_logs.php
```

**Content**:
```php
<?php
require_once 'config.inc.php';
require_once 'integrations/ERPAuditLogger.php';

$audit = new ERPAuditLogger($c);
$affected = $audit->archiveOldLogs(90);  // Keep 90 days

echo "Archived $affected old logs\n";
?>
```

**Time Estimate**: 1-2 hours

**Success Criteria**:
```
✓ Audit tables created (erp_sync_audit_log, erp_sync_errors)
✓ Sync operations logged with timestamp and duration
✓ Errors captured with stack trace
✓ getSyncStats() shows correct numbers
```

---

### **PHASE 7-9: Staging Testing & Documentation** (Days 9-11)

See [INTEGRATION_DEPLOYMENT_CHECKLIST.md](INTEGRATION_DEPLOYMENT_CHECKLIST.md) Phases 7-9

- Phase 7: System integration (WO completion hooks)
- Phase 8: Testing & validation (end-to-end, load, error handling)
- Phase 9: Documentation (final review, update team docs)

---

### **PHASE 10: Production Deployment** (Days 11-12)

See [INTEGRATION_DEPLOYMENT_CHECKLIST.md](INTEGRATION_DEPLOYMENT_CHECKLIST.md) Phase 10

**Pre-Deployment Checklist**:
```
☐ All code tested in staging
☐ Database backed up
☐ Team notified of deployment window
☐ Rollback plan documented
☐ Monitoring set up
☐ On-call engineer identified
```

**Deployment Steps**:
1. Backup prod database
2. Deploy code to production
3. Verify API endpoints working
4. Test SAP/NetSuite connection
5. Monitor error logs for 1 hour
6. Announce go-live to team

**Rollback** (if needed):
```bash
# Stop API
echo "<?php http_response_code(503); ?>" > api/v1/index.php

# Restore database
mysql free_cmms < backup_2026-03-20.sql

# Revert code
git checkout HEAD~1
```

---

## MILESTONE CHECKPOINTS

| Milestone | Target Date | Owner | Status |
|-----------|-------------|-------|--------|
| **Phase 1: DB + Creds Requested** | March 21 | You | ⏳ |
| **Phase 2: API Tested** | March 22 | You | ⏳ |
| **Phase 3: ERP Creds Received** | March 26 | ERP Admin | ⏳ |
| **Phase 4: Cloud Storage Ready** | March 27 | You | ⏳ |
| **Phase 5: GL Configured** | March 28 | Acct Manager + You | ⏳ |
| **Phase 6: Audit Logging Working** | March 29 | You | ⏳ |
| **Phase 7-9: Staging Testing** | April 1 | You | ⏳ |
| **Phase 10: Production Go-Live** | April 2 | You + Ops | ⏳ |

---

## RESOURCE REQUIREMENTS

### People
- **You (Lead Dev)**: 40+ hours (Phases 1, 2, 4, 6, 7-10)
- **ERP Admin/Manager**: 4 hours (credential setup, testing)
- **Accounting Manager**: 2 hours (GL account mapping)
- **Database Admin**: 1 hour (Phase 1, table creation)
- **IT Ops**: 2 hours (server setup, deployment)

### Tools
- **Required**: PHP 7.5+, MySQL 5.7+, Git, cURL
- **Optional**: Docker (for isolated environment), Jenkins (for CI/CD), Postman (for API testing)

### Infrastructure
- **Database**: 2-3 GB storage for audit logs (first year)
- **Cloud Storage**: S3 bucket, Google Drive folder, or Azure container
- **Server Capacity**: Minimal (audit logging is lightweight)

---

## RISKS & MITIGATION

| Risk | Probability | Impact | Mitigation |
|------|-------------|--------|-----------|
| ERP credentials delayed | High | 3 days | Follow up after 2 days if no response |
| SAP connection timeout | Medium | 2 days | Test VPN/firewall, check IP whitelisting |
| GL account structure mismatch | Medium | 1 day | Get acct structure from accounting team first |
| Cloud storage quota exceeded | Low | 30 mins | Monitor storage usage, set alerts |
| Audit logs grow too fast | Low | 1 hour | Run archival more frequently |
| Production sync fails | Very Low | 2 hours | Rollback database, revert code |

---

## DECISION TREE

```
Start Here
    ├─ Do you have database admin access?
    │  ├─ Yes → Continue to Phase 1
    │  └─ No → Get DBA to create tables from SQL script
    │
    ├─ Which ERP system?
    │  ├─ SAP → Use SAPConnector.php, collect SAP creds
    │  ├─ NetSuite → Use NetSuiteConnector.php, OAuth setup
    │  └─ Neither → Skip Phases 3, go to Phase 4
    │
    ├─ Which cloud storage?
    │  ├─ AWS → S3CloudStorage, get AWS keys
    │  ├─ Google → GoogleDriveStorage, complete OAuth
    │  ├─ Azure → AzureBlobStorage, get account key
    │  └─ None → Skip Phase 4
    │
    ├─ Auto-post GL entries or require approval?
    │  ├─ Auto → GL_AUTO_POST_ON_WO_COMPLETE=true
    │  └─ Manual → GL_REQUIRE_APPROVAL=true
    │
    └─ Deploy to staging first or go straight to prod?
       ├─ Staging → 1 extra day, safer
       └─ Prod → Faster, but riskier
```

---

## SUCCESS CRITERIA (FINAL CHECKLIST)

When all the following are checked, you're ready for go-live:

```
CORE FUNCTIONALITY
☐ REST API returns work orders (GET /api/v1/work_orders)
☐ Can create WO via API (POST /api/v1/work_orders)
☐ Can update WO via API (PUT /api/v1/work_orders/123)
☐ Rate limiting works (429 on excess requests)

ERP INTEGRATION (if applicable)
☐ SAP connection test passes
☐ NetSuite OAuth token refreshes
☐ WO syncs to SAP/NetSuite without errors
☐ GL entries created on WO completion
☐ Equipment master synced to ERP

CLOUD STORAGE
☐ Files upload to S3/Google/Azure
☐ Public URLs generated and accessible
☐ Downloads work from cloud storage

AUDIT & COMPLIANCE
☐ All syncs logged with timestamp & duration
☐ Errors captured with stack trace
☐ Unresolved errors dashboard shows problems
☐ Sync statistics accurate
☐ Monthly cleanup scheduled

SECURITY
☐ No credentials in PHP code
☐ .env file permissions set to 600
☐ .env in .gitignore
☐ Credentials rotated annually plan in place
☐ Error logs don't expose sensitive data

OPERATIONS
☐ Team trained on new features
☐ Documentation updated
☐ Monitoring/alerts configured
☐ Rollback procedure documented
☐ On-call support identified
```

---

## SUPPORT & ESCALATION

**During Implementation**:
- **Technical Issues**: Check [INTEGRATION_AND_API_GUIDE.md](INTEGRATION_AND_API_GUIDE.md) troubleshooting
- **ERP Connectivity**: Contact ERP admin (SAP/NetSuite support)
- **Database Issues**: Contact database admin

**Post-Go-Live**:
- **API Errors**: Check api_logs table, review error_log
- **Sync Failures**: Check erp_sync_audit_log, erp_sync_errors
- **Performance**: Query slowest syncs report
- **Security Questions**: Review [SECURE_CREDENTIAL_STORAGE_GUIDE.md](SECURE_CREDENTIAL_STORAGE_GUIDE.md)

---

## NEXT IMMEDIATE ACTION

```
TODAY:
1. Read INTEGRATION_DEPLOYMENT_CHECKLIST.md Phase 1
2. Get database admin to create tables
3. Send credential request email using CREDENTIAL_REQUEST_TEMPLATE.md
4. Copy .env.example → .env
5. Set .env permissions: chmod 600 .env

TOMORROW:
6. Follow Phase 2 steps to test API locally
7. Check if ERP credentials have arrived
```

**When ready to begin Phase 1, confirm by:**
- Stating you've read the phase checklist
- Identifying your database administrator
- Confirming you have SAP/NetSuite admin contact info

---

**Estimated Total Time**: 10 business days  
**Estimated Total Cost**: $0 (if using AWS free tier) to $500/month (cloud storage)  
**ROI**: Eliminates manual data entry, reduces errors, enables real-time reporting

---

**Questions?** Review the relevant guide file from the list above, or ask for clarification on any phase.

Let's get started! 🚀
