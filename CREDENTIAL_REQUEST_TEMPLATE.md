# CMMS ERP Integration - Credential Request Form

**Project**: Free-CMMS 0.04 REST API Integration with Enterprise Systems  
**Date Needed By**: [DATE]  
**Requester**: [YOUR NAME - Lead Developer]  
**Integration Timeline**: 1-2 weeks

---

## EMAIL TEMPLATE - SEND TO ERP ADMIN/MANAGER

---

**Subject:** CMMS Integration - ERP Service Account & OAuth Credential Request

**To:** [ERP Admin Name]

Hi [Admin Name],

We're implementing a REST API integration between our CMMS (Free-CMMS 0.04) and **[SAP / NetSuite / BOTH]** to automate:
- Work order synchronization to maintenance notifications
- GL entry posting for maintenance costs
- Inventory updates
- Equipment master data synchronization

To proceed with Phase 3 testing, we need service account credentials for the integration system.

**Please provide the following by [DATE]:**

---

## FOR SAP INTEGRATION (if applicable)

### Service Account Details:
- [ ] **Hostname/URL**: `___________________________________________`
- [ ] **Service Account Username**: `___________________________________________`
- [ ] **Service Account Password**: `___________________________________________`
  - *Security Note: We'll store this in encrypted environment variables (not in code)*
  - *Will be rotated annually per company security policy*

### SAP System Configuration:
- [ ] **Client Number**: `_________` (e.g., 100)
- [ ] **Company Code**: `_________` (e.g., US01)
- [ ] **Controlling Area**: `_________` (e.g., CA01)
- [ ] **Cost Center**: `_________` (e.g., MAINT01)
- [ ] **Spec**: URL for Maintenance Notification OData service
  - Example: `/sap/opu/odata/sap/c_workorderheader_cds/`
  
### Required SAP Authorizations (for the service user):
- [ ] Transaction **PM01** (Create maintenance notifications)
- [ ] Transaction **PM02** (Change maintenance notifications)
- [ ] Transaction **MB1C** (Post goods movements/inventory)
- [ ] Transaction **FB50** (Create journal entries)
- [ ] Transaction **IE02** (View equipment master)

### Additional Info:
- [ ] Is this **production** or **test** instance? `_________`
- [ ] Are there any IP restrictions? Need to whitelist our server: `_________`
- [ ] SSL certificate requirements? (self-signed OK?) `_________`

---

## FOR NETSUITE INTEGRATION (if applicable)

### OAuth 2.0 Setup:
- [ ] **Account ID**: `_________` (e.g., 1234567)
- [ ] **Realm**: `_________` (production: system.netsuite.com OR sandbox: sandbox.netsuite.com)
- [ ] **Instance URL**: `https://_____.netsuite.com` (e.g., https://1234567-api.netsuite.com)
- [ ] **OAuth Client ID**: `___________________________________________`
- [ ] **OAuth Client Secret**: `___________________________________________`
  - *Security Note: We'll store in encrypted environment variables*
  - *Will rotate credentials annually*

### OAuth Application Details:
- [ ] Application Name created in NetSuite: `_________`
- [ ] Redirect URI registered: `https://[your-cmms-domain]/api/oauth/netsuite_callback`
- [ ] Scopes granted: `rest_webservices` (needed for API calls)

### NetSuite Permissions (API Role):
- [ ] **Support Case** permission: Create, Read, Update
- [ ] **Journal Entry** permission: Create, Read
- [ ] **Inventory Adjustment** permission: Create, Read
- [ ] **Equipment** (custom record) permission: Read
- [ ] **Subsidiary** permissions: Access to `_________`

### Custom Fields Created:
- [ ] **cmms_wo_id** (Text field) - stores CMMS work order ID
- [ ] **cmms_status** (Text field) - mirrors CMMS WO status
- [ ] **cmms_priority** (Dropdown) - Priority mapping

### Additional Info:
- [ ] Is this **production** or **sandbox** instance? `_________`
- [ ] Any IP whitelist restrictions? Server IP: `_________`
- [ ] Is 2-factor authentication enforced? How do we handle in API? `_________`

---

## SHARED REQUIREMENTS (SAP & NetSuite)

### Testing & Validation:
- [ ] Can we perform test syncs in *this environment* without affecting prod data?
  - OR do we need a *separate test instance*?
- [ ] What is the rollback procedure if a sync fails?
- [ ] Who monitors ERP sync failures on the ERP side?

### Audit & Compliance:
- [ ] Are API calls logged in SAP/NetSuite? Where can we view audit trail?
- [ ] Are there any compliance/audit requirements for GL posting?
- [ ] Do we need to implement 2-factor authentication for the service account?

### Support & Escalation:
- [ ] Who is the primary contact for ERP support during integration? `_________`
- [ ] Backup contact for escalations? `_________`
- [ ] What are your support hours? `_________`

---

## INSTRUCTIONS FOR PROVIDING CREDENTIALS

**NEVER send credentials via:**
- ❌ Email (clear text)
- ❌ Slack
- ❌ Teams
- ❌ Unencrypted message

**INSTEAD use ONE of these methods:**

### Option 1: Password Manager (Preferred)
1. Create credentials in your company's password manager (1Password, LastPass, etc.)
2. Share access link with requester
3. Requester copies to local encrypted .env file

### Option 2: Encrypted File
1. Create .txt file with credentials
2. Encrypt using: `gpg --encrypt credentials.txt`
3. Share encrypted file + password separately (over phone)

### Option 3: In-Person / Secure Channel
1. Provide credentials in person
2. OR secure video call with screen share (record not allowed)

---

## NEXT STEPS AFTER CREDENTIALS RECEIVED

1. ✅ **Lead Dev receives credentials** → Stores in encrypted .env file (not in git)
2. ✅ **Test connection** → Verifies SAP/NetSuite can be reached
3. ✅ **Staging deployment** → Test sync in safe environment
4. ✅ **Production deployment** → One-time sync to production after approval
5. ✅ **Audit log setup** → Begin tracking all ERP calls
6. ✅ **Monitoring** → Alert on sync failures

---

## QUESTIONS?

Contact: [YOUR NAME]  
Email: [YOUR EMAIL]  
Phone: [YOUR PHONE]

**Timeline:** We'll begin Phase 3 testing once credentials are received.

---

# ALTERNATIVE: CREDENTIAL REQUEST FORM (use if you prefer)

If your company prefers a form instead of email, use this form:

```
CMMS ERP INTEGRATION - CREDENTIAL REQUEST FORM

Requester Name: _________________________________
Requester Title: _________________________________
Department: _________________________________
Date Submitted: _________________________________

1. Integration Target:
   ☐ SAP   ☐ NetSuite   ☐ Both

2. Environment:
   ☐ Production   ☐ Test/Sandbox

3. Credentials Needed By:
   _________________________________

4. Service Account Access Level:
   ☐ Read-only (view only, no changes)
   ☐ Read-Write (create notifications, GL entries, inventory)
   ☐ Admin (full access)

5. Approval Chain:
   ☐ IT Manager: _________________________ (Signature)
   ☐ ERP Admin: _________________________ (Signature)
   ☐ Finance/GL Manager: _________________________ (Signature) [if GL access]

6. Security Acknowledgment:
   ☐ I understand credentials will be:
      - Stored in encrypted .env file (not in code)
      - Accessible only to [TEAM MEMBERS]
      - Rotated annually per policy
      - Audited for all access attempts

   Signature: _________________________ Date: _________

7. Compliance Requirements:
   ☐ All API calls will be logged and audited
   ☐ Credentials will never be committed to version control
   ☐ Access will be reviewed quarterly
   ☐ Unauthorized access attempts will trigger alerts

Submit to: [SECURITY TEAM OR ERP ADMIN]
```

---

## TIPS FOR SUCCESS

✅ **DO:**
- Provide 2 weeks lead time (admins are busy)
- Explain business value (saves manual data entry)
- Be specific about what permissions are needed
- Mention this is for automation, not human logins
- Provide rollback/reversal plan

❌ **DON'T:**
- Ask for admin/superuser credentials
- Request production access immediately (test first)
- Give credentials to the entire team (only Lead Dev)
- Change credentials after receiving them (breaks integration)
- Forget to rotate credentials annually

---

## CHECKLIST FOR LEAD DEV

Before requesting credentials:

- [ ] Read and understand [INTEGRATION_AND_API_GUIDE.md](INTEGRATION_AND_API_GUIDE.md)
- [ ] Complete Phase 1-2 of [INTEGRATION_DEPLOYMENT_CHECKLIST.md](INTEGRATION_DEPLOYMENT_CHECKLIST.md)
- [ ] Have database tables created
- [ ] Have .env file ready (with placeholders)
- [ ] Know your server's public IP (for whitelisting, if needed)
- [ ] Decide: test instance first, or go straight to production?
- [ ] Identify who will approve credential request (IT Manager)
- [ ] Schedule credential handoff meeting with ERP Admin

---

**When ready, send the above email to your ERP Admin and await response. Typical turnaround: 3-5 business days.**
