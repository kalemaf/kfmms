# SECURITY & COMPLIANCE ARCHITECTURE
## Free-CMMS v0.04 - Enterprise Security Implementation

---

## Table of Contents
1. [Overview](#overview)
2. [Security Architecture](#security-architecture)
3. [Core Security Classes](#core-security-classes)
4. [Authentication & Authorization](#authentication--authorization)
5. [Data Protection](#data-protection)
6. [Compliance Framework](#compliance-framework)
7. [Implementation Checklist](#implementation-checklist)
8. [Security Best Practices](#security-best-practices)

---

## Overview

This security and compliance framework provides enterprise-grade protection for CMMS with:

✅ **Authentication**
- Bcrypt password hashing
- Two-factor authentication (TOTP + Email)
- Session management with expiration
- Failed login attempt tracking
- Device trust/remember me

✅ **Authorization**
- Role-Based Access Control (RBAC)
- Permission matrix (70+ permissions)
- Resource-level access control
- Permission delegation
- Audit trail of all access changes

✅ **Encryption**
- AES-256-CBC at-rest encryption
- Field-level encryption for PII
- Transparent encrypt/decrypt
- Key rotation support
- Encryption audit logging

✅ **Compliance**
- SOX (Sarbanes-Oxley) audit trails (7-year retention)
- GDPR data handling & "right to be forgotten"
- ISO 15001 maintenance compliance
- Configurable data retention policies
- Automated report generation

---

## Security Architecture

### Layers

```
┌─────────────────────────────────────────────────┐
│  Application Layer                              │
│  (Work Orders, Equipment, Users, etc.)          │
└─────────────────────────────────────┬───────────┘
                                      │
┌─────────────────────────────────────▼───────────┐
│  Access Control Layer (RBAC)                     │
│  RoleBasedAccessControl.php                     │
│  - Permission validation                        │
│  - Resource access checks                       │
└─────────────────────────────────────┬───────────┘
                                      │
┌─────────────────────────────────────▼───────────┐
│  Authentication Layer                           │
│  SecurityManager.php + TwoFactorAuth.php        │
│  - Session management                          │
│  - Login/logout                                 │
│  - 2FA verification                             │
└─────────────────────────────────────┬───────────┘
                                      │
┌─────────────────────────────────────▼───────────┐
│  Encryption Layer                               │
│  DataEncryption.php                             │
│  - AES-256 encryption at rest                   │
│  - Field-level encryption for PII              │
│  - Key management                               │
└─────────────────────────────────────┬───────────┘
                                      │
┌─────────────────────────────────────▼───────────┐
│  Database Layer                                 │
│  Secured MySQL connection (SSL/TLS recommended) │
│  Parameterized queries                          │
│  Prepared statements                            │
└─────────────────────────────────────┬───────────┘
                                      │
┌─────────────────────────────────────▼───────────┐
│  Audit & Compliance Layer                       │
│  ComplianceAuditor.php                          │
│  - All actions logged                           │
│  - Compliance reports generated                 │
│  - Data retention enforced                      │
└─────────────────────────────────────┘
```

---

## Core Security Classes

### 1. SecurityManager.php
**Core authentication and session management**

```php
require 'SecurityManager.php';
$security = new SecurityManager($mysqli_connection, [
    'session_timeout' => 3600,          // 1 hour
    'max_login_attempts' => 5,          // Lock after 5 failed attempts
    'lockout_duration' => 900,          // 15 minutes
    'secure_cookies' => true,           // HTTPS only
    'csrf_enabled' => true              // CSRF protection
]);

// Initialize at start of each request
$security->initializeSession();

// Authenticate user
$result = $security->authenticate($username, $password);
if ($result['success']) {
    echo "Login successful";
    if ($result['requires_2fa']) {
        // Redirect to 2FA verification
    }
} else {
    echo $result['message'];
}

// Set secure HTTP headers
SecurityManager::setSecureHeaders();

// Logout
$security->logout();
```

**Key Methods:**
- `initializeSession()` - Setup secure session at request start
- `authenticate($username, $password)` - Login with brute-force protection
- `hashPassword($password)` - Hash with bcrypt
- `verifyPassword($password, $hash)` - Verify password
- `resetPassword($user_id, $old_password, $new_password)` - Password change
- `logSecurityEvent(...)` - Audit trail
- `generateCSRFToken()` / `verifyCSRFToken()` - CSRF protection

---

### 2. TwoFactorAuth.php
**Two-factor authentication (TOTP + Email)**

```php
require 'TwoFactorAuth.php';
$twofa = new TwoFactorAuth($mysqli_connection);

// 1. User enables TOTP
$setup = $twofa->setupTOTP($user_id, $username, $email);
// Returns: secret, QR code URL, manual entry text
// User scans QR with authenticator app (Google Authenticator, Authy, etc.)

// 2. User confirms with 6-digit code from authenticator
$confirm = $twofa->confirmTOTP($user_id, '123456');
// Returns: backup codes for account recovery

// 3. On login, verify 2FA code
$verified = $twofa->verifyTOTP($secret, $code);

// Alternative: Email-based 2FA
$twofa->sendEmailCode($user_id, 'user@example.com');
$verified = $twofa->verifyEmailCode($user_id, '123456');

// Trust device (30 days)
$twofa->trustDevice($user_id);
if ($twofa->isDeviceTrusted($user_id)) {
    // Skip 2FA on this device
}

// Backup codes for recovery
$backup_codes = $twofa->generateBackupCodes($user_id);
$twofa->verifyBackupCode($user_id, $code);
```

**Key Methods:**
- `setupTOTP()` - Generate TOTP secret & QR code
- `confirmTOTP()` - Verify TOTP setup & generate backups
- `verifyTOTP()` - Validate 6-digit authenticator code
- `sendEmailCode()` - Send code via email
- `verifyEmailCode()` - Validate email code
- `trustDevice()` - Remember device for 30 days
- `generateBackupCodes()` / `verifyBackupCode()` - Recovery codes

---

### 3. RoleBasedAccessControl.php
**Permission matrix and resource access control**

```php
require 'RoleBasedAccessControl.php';
$rbac = new RoleBasedAccessControl($mysqli_connection);

// Check if user can perform action
if ($rbac->hasPermission($user_id, 'work_orders', 'create')) {
    // User can create work orders
}

// Check if user can access specific resource
if ($rbac->hasResourcePermission($user_id, 'work_order', $wo_id, 'update')) {
    // User can edit this specific work order
}

// Assign role to user
$rbac->assignRoleToUser($user_id, $role_id, $admin_user_id);

// Grant custom permission
$rbac->grantPermissionToUser($user_id, 'reports.export', $admin_user_id);

// Revoke permission
$rbac->revokePermissionFromUser($user_id, 'reports.export', $admin_user_id);

// Delegate approval (temporary override)
$rbac->delegateApproval($from_user_id, $to_user_id, 'work_order_approval', 24); // 24 hours

// Get permission matrix
$permissions = $rbac->getPermissionMatrix($user_id);
```

**Built-in Roles:**
- **Admin**: Full access to all resources
- **Manager**: User management, work order approval, reports
- **Lead**: Work order updates, maintenance management
- **Technician**: Work order completion, inventory
- **Viewer**: Read-only access to dashboards

**Sample Permissions:**
```
users.create, users.read, users.update, users.delete
work_orders.create, work_orders.read, work_orders.update, work_orders.delete, work_orders.approve, work_orders.complete
equipment.create, equipment.read, equipment.update, equipment.delete
inventory.create, inventory.read, inventory.update, inventory.delete
maintenance.create, maintenance.read, maintenance.update
reports.read, reports.export
audit_logs.read, audit_logs.export
settings.read, settings.update
workflows.manage, workflows.approve
```

---

### 4. DataEncryption.php
**At-rest encryption for sensitive data**

```php
require 'DataEncryption.php';
$encryption = new DataEncryption($mysqli_connection, $key);

// Encrypt sensitive field
$encrypted_value = $encryption->encrypt($plaintext);
// Store in database

// Decrypt sensitive field
$plaintext = $encryption->decrypt($ciphertext);

// Encrypt work order field
$encryption->encryptWorkOrderField($wo_id, 'descriptive_text', $plaintext, $user_id);

// Decrypt work order field (with audit trail)
$plaintext = $encryption->decryptWorkOrderField($wo_id, 'descriptive_text', $user_id);

// Key rotation (long-running, batch operation)
$encryption->rotateEncryptionKey($old_key, $new_key);

// Get encryption audit log
$logs = $encryption->getEncryptionAuditLog('work_order', $wo_id);
```

**Configuration:**

Add to `.env`:
```bash
# Generate key: php -r "require 'DataEncryption.php'; echo DataEncryption::generateEncryptionKey();"
ENCRYPTION_KEY=base64_encoded_32_byte_key
```

**Fields to Encrypt:**
- User phone numbers
- User addresses
- Work order descriptions (if contain sensitive info)
- Equipment serial numbers
- Cost/financial data

---

### 5. PasswordPolicyEnforcer.php
**Password requirements and enforcement**

```php
require 'PasswordPolicyEnforcer.php';
$policy = new PasswordPolicyEnforcer($mysqli_connection, [
    'min_length' => 12,
    'require_uppercase' => true,
    'require_lowercase' => true,
    'require_numbers' => true,
    'require_special' => true,
    'expiration_days' => 90,
    'history_count' => 5,
    'max_age_warning_days' => 14
]);

// Validate password
$validation = $policy->validatePassword($new_password, $username);
if (!$validation['valid']) {
    echo "Errors: " . implode(', ', $validation['errors']);
}

// Set password (with history tracking)
$result = $policy->setPassword($user_id, $new_password);

// Check against breach database (Have I Been Pwned)
$breach_check = $policy->checkAgainstBreachDatabase($password);
if ($breach_check['breached']) {
    echo "Password found in {$breach_check['breach_count']} data breaches";
}

// Force password change
$policy->forcePasswordChange($user_id, 'Security policy requires periodic change');

// Get password status
$status = $policy->getPasswordExpirationStatus($user_id);
// Returns: 'expired', 'expiring_soon', 'valid', or 'never_expires'
```

**Password Requirements:**
- Minimum 12 characters
- At least one uppercase letter (A-Z)
- At least one lowercase letter (a-z)
- At least one number (0-9)
- At least one special character (!@#$%^&*)
- Cannot contain username
- Not in common password database
- No common patterns (qwerty, 123456, etc.)

**Password Expiration:**
- Default: 90 days
- Warning: 14 days before expiration
- Force change if breached

---

### 6. ComplianceAuditor.php
**Audit trail and compliance logging**

```php
require 'ComplianceAuditor.php';
$auditor = new ComplianceAuditor($mysqli_connection);

// Log login
$auditor->logLogin($user_id, $username, true);

// Log work order creation
$auditor->logWorkOrderCreation($user_id, $wo_id, ['cost' => 500]);

// Log modification
$auditor->logWorkOrderModification($user_id, $wo_id, ['status' => 'completed', 'cost' => 600]);

// Log financial transaction (SOX critical)
$auditor->logFinancialTransaction($user_id, 'PO_ISSUED', 1500.00, $po_id);

// Log sensitive data access
$auditor->logSensitiveDataAccess($user_id, 'work_order', $wo_id, 'phone_number', 'Customer contact');

// Log access control changes
$auditor->logAccessControlChange($admin_id, $user_id, 'role_added', ['role' => 'manager']);

// Get audit trail
$trail = $auditor->getAuditTrail('work_order', $wo_id);

// Archive old logs (7 year SOX requirement maintained)
$auditor->archiveOldLogs(2555); // 7 years

// GDPR: Delete user data (right to be forgotten)
$auditor->deleteUserData($user_id, $admin_id);
```

**Audit Log Fields:**
- Event Type (USER_LOGIN, WORK_ORDER_CREATED, etc.)
- User ID
- Resource Type & ID
- Action
- IP Address
- User Agent
- Timestamp
- JSON Details

**Retention Policies:**
- User access logs: 7 years (SOX)
- Work order data: 7 years
- Financial data: 7 years (SOX)
- User personal data: 3 years (GDPR)
- Temporary data: 90 days
- Audit logs: 7 years minimum

---

### 7. ComplianceReportGenerator.php
**Generate compliance reports**

```php
require 'ComplianceReportGenerator.php';
$reporter = new ComplianceReportGenerator($mysqli_connection, $auditor);

// SOX Report (financial audit trail)
$sox_report = $reporter->generateSOXReport('2026-01-01', '2026-12-31');
// Includes: Financial transactions, work order changes, access control changes

// GDPR Report (data handling)
$gdpr_report = $reporter->generateGDPRReport('2026-01-01', '2026-12-31');
// Includes: Data access, sensitive data access, data deletion requests

// ISO 15001 Report (maintenance compliance)
$iso_report = $reporter->generateISO15001Report('2026-01-01', '2026-12-31');
// Includes: PM compliance rate, equipment status, work order metrics

// User Access Report
$access_report = $reporter->generateUserAccessReport('2026-01-01', '2026-12-31');
// Includes: Login activity, failed attempts, active users

// Data breach notification (template)
$notification = $reporter->generateDataBreachNotification(
    'Unauthorized access to customer data',
    $affected_user_ids
);
// Includes: Email template, regulatory requirements, action items
```

---

## Authentication & Authorization

### Login Flow

```
1. User submits username + password
   ↓
2. SecurityManager::authenticate()
   - Check account not locked
   - Verify credentials against bcrypt hash
   - Check account is active
   - Check password not expired
   - Clear failed login attempts
   - Create session
   ↓
3. If 2FA enabled:
   - TwoFactorAuth::send2FACode()
   - Prompt user for 6-digit code
   ↓
4. On 2FA verification:
   - TwoFactorAuth::verifyTOTP() or verifyEmailCode()
   - Generate CSRF token
   - Log login event
   - Redirect to dashboard
```

### Access Control Flow

```
1. User requests resource (e.g., edit work order)
   ↓
2. Application calls:
   $rbac->hasResourcePermission($user_id, 'work_order', $wo_id, 'update')
   ↓
3. RBAC checks:
   - Does user's role have 'work_orders.update' permission?
   - Are there resource-level restrictions?
   - Has permission been delegated?
   ↓
4. If denied:
   - Log access denial
   - Return 403 Forbidden
   ↓
5. If allowed:
   - Grant access
   - Log action for audit trail
```

---

## Data Protection

### Encryption Strategy

**At Rest (Server):**
- AES-256-CBC for sensitive fields
- Each encryption uses random IV (initialization vector)
- Keys managed in .env (never hardcoded)

**In Transit (Network):**
- HTTPS/TLS 1.2+ required
- HTTP Strict Transport Security (HSTS) header
- Secure cookies (httponly, secure flags)

**Key Management:**
- Generate 32-byte key: `php -r "require 'DataEncryption.php'; echo DataEncryption::generateEncryptionKey();"`
- Store in `.env` as `ENCRYPTION_KEY=...`
- Rotate keys annually
- Archive old keys for decryption of historical data

### PII Protection

**Encrypted Fields:**
```
Users:
- phone (encrypted)
- address (encrypted)
- email (optional)

Work Orders:
- descriptive_text (if contains PII)
- notes (if contains PII)

Equipment:
- serial_number (optional)
- location_notes (optional)
```

### Password Hashing

- Algorithm: bcrypt (PASSWORD_BCRYPT)
- Cost: 12 (high security, slight delay acceptable)
- Never store plaintext passwords
- All password changes logged

---

## Compliance Framework

### SOX (Sarbanes-Oxley)

**Requirements:**
✅ Audit trail of all financial transactions
✅ User access control logging
✅ System changes documented
✅ Transaction data integrity
✅ 7-year retention of audit logs
✅ Segregation of duties

**CMMS Implementation:**
- ComplianceAuditor logs all financial transactions
- Work order costs tracked with user/timestamp
- PO creation/approval logged
- GL entries audited
- 7-year retention enforced automatically
- Reports generated quarterly

**Report:**
```
sox_report = reporter.generateSOXReport('2026-01-01', '2026-12-31')
// Shows all financial activity with full audit trail
```

### GDPR (General Data Protection Regulation)

**Requirements:**
✅ Legal basis documented for data processing
✅ Consent obtained where required
✅ Data subjects informed of processing
✅ Data deletion on request (right to be forgotten)
✅ Data breach notification within 72 hours
✅ Privacy by design
✅ Data processing impact assessment
✅ Data retention policies (delete when no longer needed)

**CMMS Implementation:**
- Data processing activities documented
- Consent tracking for non-contractual processing
- User deletion anonymizes personal data
- Encryption protects data confidentiality
- Audit logs track all data access/modifications
- Data retention policies configurable

**Report:**
```
gdpr_report = reporter.generateGDPRReport('2026-01-01', '2026-12-31')
// Data processing, access patterns, deletion requests
```

### ISO 15001 (Maintenance Management)

**Requirements:**
✅ Preventive maintenance scheduling
✅ Equipment maintenance tracking
✅ Maintenance records retention
✅ Maintenance personnel qualifications
✅ Spare parts management
✅ Work order documentation

**CMMS Implementation:**
- PM schedules enforce preventive maintenance
- Work order completion tracking
- Equipment maintenance history
- Maintenance record audit trail
- PM compliance metrics

**Report:**
```
iso_report = reporter.generateISO15001Report('2026-01-01', '2026-12-31')
// PM compliance rate, equipment status, work order metrics
```

---

## Implementation Checklist

### Phase 1: Database Setup (1 day)

- [ ] Create users table with password_hash (bcrypt), totp_secret, require_2fa
- [ ] Create roles table and role_permissions
- [ ] Create user_roles and user_permissions tables
- [ ] Create resource_restrictions table (for resource-level access)
- [ ] Create failed_login_attempts table
- [ ] Create two_factor_codes table
- [ ] Create backup_codes table
- [ ] Create trusted_devices table
- [ ] Create password_history table
- [ ] Create compliance_audit_log table
- [ ] Create encryption_audit_log table
- [ ] Create security_audit_log table

```sql
-- Execute SQL schema
mysql -u user -p database < security_schema.sql
```

### Phase 2: Authentication Upgrade (2 days)

- [ ] Update auth.php to use SecurityManager
- [ ] Migrate existing passwords to bcrypt hashes:
  ```php
  // One-time migration
  $sql = "SELECT user_id, passwd FROM groups";
  $result = mysqli_query($connection, $sql);
  while ($row = $result->fetch_assoc()) {
      $hash = password_hash($row['passwd'], PASSWORD_BCRYPT);
      $update = "UPDATE users SET password_hash = ? WHERE user_id = ?";
      // Update in users table
  }
  ```
- [ ] Create login form with SecurityManager integration
- [ ] Add CSRF token to all forms
- [ ] Implement session timeout (30 min idle, 8 hour max)
- [ ] Add logout functionality

### Phase 3: Two-Factor Authentication (2 days)

- [ ] Enable TOTP setup in user settings
- [ ] Allow email-based 2FA as alternative
- [ ] Generate backup codes on setup
- [ ] Create 2FA verification page post-login
- [ ] Add "trust device" option (30 days)
- [ ] Disable 2FA recovery process
- [ ] Communication to users about 2FA requirement

### Phase 4: Role-Based Access Control (3 days)

- [ ] Define roles and permissions matrix
- [ ] Create role assignment UI for admins
- [ ] Implement permission checks in application
- [ ] Add resource-level restrictions
- [ ] Create permission delegation feature
- [ ] Audit all permission changes
- [ ] Generate access control reports

### Phase 5: Encryption (2 days)

- [ ] Generate encryption key and store in .env
- [ ] Create sensitive fields (PII)
- [ ] Implement encrypt/decrypt for work order descriptions
- [ ] Implement encrypt/decrypt for user contact info
- [ ] Set up encryption audit logging
- [ ] Verify decrypt audit trail
- [ ] Plan for key rotation (annual)

### Phase 6: Password Policy (1 day)

- [ ] Configure password requirements
- [ ] Implement password validation
- [ ] Force password change for weak passwords
- [ ] Check against breach database (Have I Been Pwned)
- [ ] Implement password expiration (90 days)
- [ ] Maintain password history (prevent reuse)
- [ ] Add password reset capability

### Phase 7: Audit Logging (2 days)

- [ ] Integrate ComplianceAuditor into all major operations
- [ ] Log user logins/logouts
- [ ] Log work order changes
- [ ] Log financial transactions
- [ ] Log access control changes
- [ ] Log sensitive data access
- [ ] Verify audit trail in database
- [ ] Archive old logs (7-year retention)

### Phase 8: Compliance Reports (2 days)

- [ ] Test SOX report generation
- [ ] Test GDPR report generation
- [ ] Test ISO 15001 report generation
- [ ] Set up automated report scheduling
- [ ] Create review process for reports
- [ ] Prepare data breach notification template
- [ ] Document compliance procedures

### Phase 9: User Communication (1 day)

- [ ] Announce security enhancements
- [ ] Provide password reset instructions
- [ ] Guide users through 2FA setup
- [ ] Document compliance policies
- [ ] Create FAQ/help documentation
- [ ] Train administrators on new features

### Phase 10: Testing & Validation (2 days)

- [ ] Penetration test login system
- [ ] Test password policies
- [ ] Verify 2FA workflow
- [ ] Test access control enforcement
- [ ] Validate encryption/decryption
- [ ] Verify audit logging
- [ ] Generate sample reports
- [ ] Test account recovery workflows

---

## Security Best Practices

### Development

1. **Never hardcode credentials:**
   ```php
   // ❌ WRONG
   $password = '$2y$12$...';
   
   // ✅ CORRECT
   $password = getenv('DB_PASSWORD');
   ```

2. **Always use parameterized queries:**
   ```php
   // ❌ WRONG
   $sql = "SELECT * FROM users WHERE id = " . $_GET['id'];
   
   // ✅ CORRECT
   $sql = "SELECT * FROM users WHERE id = ?";
   $stmt = $connection->prepare($sql);
   $stmt->bind_param('i', $_GET['id']);
   ```

3. **Validate all input:**
   ```php
   $username = preg_replace('/[^a-zA-Z0-9_-]/', '', $_POST['username']);
   $email = filter_var($_POST['email'], FILTER_VALIDATE_EMAIL);
   ```

4. **Sanitize output:**
   ```php
   echo htmlspecialchars($username);
   ```

5. **Use HTTPS everywhere:**
   - Force HTTPS in .htaccess or nginx config
   - Set HSTS header

### Deployment

1. **Configure .env file:**
   ```bash
   ENCRYPTION_KEY=base64_encoded_key
   SESSION_TIMEOUT=3600
   MAX_LOGIN_ATTEMPTS=5
   FORCE_HTTPS=true
   ```

2. **Set proper file permissions:**
   ```bash
   chmod 600 .env                      # Owner only
   chmod 755 /var/www/html            # Web dir
   chmod 755 /var/www/html/uploads    # Upload dir
   chmod 700 /var/www/html/config     # Config dir
   ```

3. **Database security:**
   - Use SSL/TLS for database connections
   - Create limited-permission database user
   - Disable public database access

4. **Web server configuration:**
   ```nginx
   # Force HTTPS
   if ($scheme != "https") {
       return 301 https://$server_name$request_uri;
   }
   
   # HSTS header
   add_header Strict-Transport-Security "max-age=31536000; includeSubDomains" always;
   
   # Security headers
   add_header X-Content-Type-Options "nosniff" always;
   add_header X-Frame-Options "DENY" always;
   ```

5. **Monitor security:**
   - Enable error logging
   - Monitor failed login attempts
   - Review audit logs regularly
   - Set up alerts for suspicious activity

### User Management

1. **Initial password:** Generate random 16-char password, require change on first login
2. **Offboarding:** Deactivate account immediately, anonymize personal data after 90 days
3. **Access review:** Quarterly review of user permissions
4. **Incident response:** Document and follow incident response plan

---

## Next Steps

1. **Execute schema installation:** See SECURITY_SCHEMA.sql
2. **Update authentication:** Integrate SecurityManager into auth.php
3. **Configure 2FA:** Set up TOTP/email options
4. **Implement RBAC:** Define roles and assign permissions
5. **Enable encryption:** Configure .env and encrypt sensitive fields
6. **Setup audit logging:** Integrate ComplianceAuditor into operations
7. **Generate reports:** Test report generation for compliance
8. **User testing:** Phase in changes with user feedback
9. **Monitoring:** Set up alerts for security events
10. **Ongoing:** Annual security review and key rotation

