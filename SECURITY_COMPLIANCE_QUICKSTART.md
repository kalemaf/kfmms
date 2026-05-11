# SECURITY & COMPLIANCE QUICK START
## Implementation Guide - Free-CMMS v0.04

---

## Table of Contents
1. [5-Minute Overview](#5-minute-overview)
2. [Day 1: Database Setup](#day-1-database-setup)
3. [Day 2-3: Authentication Upgrade](#day-2-3-authentication-upgrade)
4. [Day 4: 2FA Setup](#day-4-2fa-setup)
5. [Day 5: Role-Based Access Control](#day-5-role-based-access-control)
6. [Day 6: Encryption](#day-6-encryption)
7. [Day 7: Compliance Audit Logging](#day-7-compliance-audit-logging)
8. [Testing Checklist](#testing-checklist)
9. [Production Deployment](#production-deployment)
10. [Support & References](#support--references)

---

## 5-Minute Overview

### What You're Building

A complete enterprise security system with:

| Feature | What It Does |
|---------|-------------|
| **Bcrypt Passwords** | Passwords stored as strong hashes, not plaintext |
| **Two-Factor Auth** | Users verify login with authenticator app (Google Authenticator) |
| **Role-Based Access** | Users have roles (Admin, Manager, Technician); roles have permissions |
| **Encryption** | Sensitive data like phone numbers encrypted at-rest |
| **Audit Logging** | Every action (login, create WO, etc.) logged for compliance |
| **Compliance Reports** | Auto-generate SOX/GDPR/ISO 15001 compliance documents |

### Architecture

```
User Login
    ↓ (SecurityManager)
Verify Password (Bcrypt)
    ↓ (TwoFactorAuth)
Verify 2FA Code
    ↓ (RoleBasedAccessControl)
Check Permissions
    ↓
Grant Access
    ↓ (ComplianceAuditor)
Log Action
    ↓
(if sensitive data) → Encrypt/Decrypt with DataEncryption
```

### Timeline

- **Day 1:** Database setup (1 hour)
- **Day 2-3:** Authentication upgrade (4-6 hours)
- **Day 4:** 2FA setup (3-4 hours) — optional but recommended
- **Day 5:** RBAC implementation (4-5 hours)
- **Day 6:** Encryption setup (2-3 hours)
- **Day 7:** Audit logging integration (3-4 hours)
- **Day 8-9:** Testing and validation (8 hours)

**Total: ~8-10 business days for full implementation**

---

## Day 1: Database Setup

### Step 1: Execute SQL Schema

```bash
cd /path/to/cmms

# Backup existing database first
mysqldump -u user -p database > backup_$(date +%Y%m%d).sql

# Execute security schema
mysql -u user -p database < security_schema.sql
```

### Step 2: Verify Tables Created

```bash
mysql -u user -p database -e "
SELECT TABLE_NAME 
FROM INFORMATION_SCHEMA.TABLES 
WHERE TABLE_SCHEMA = 'database' 
AND TABLE_NAME IN (
    'users', 'roles', 'role_permissions', 'user_roles',
    'failed_login_attempts', 'two_factor_codes', 'compliance_audit_log'
)
ORDER BY TABLE_NAME;
"
```

Expected output includes:
```
compliance_audit_log
encryption_audit_log
failed_login_attempts
password_history
role_permissions
roles
security_audit_log
two_factor_codes
trusted_devices
user_permissions
user_roles
```

### Step 3: Migrate Existing User Passwords

If you have existing users in the old `groups` table:

```php
<?php
require 'config.inc.php';

// One-time migration script
$sql = "SELECT uname, passwd FROM groups";
$result = mysqli_query($connection, $sql);

while ($row = mysqli_fetch_assoc($result)) {
    $hash = password_hash($row['passwd'], PASSWORD_BCRYPT, ['cost' => 12]);
    
    // Insert into users table if not exists
    $check = "SELECT user_id FROM users WHERE username = ?";
    $stmt = $connection->prepare($check);
    $stmt->bind_param('s', $row['uname']);
    $stmt->execute();
    $check_result = $stmt->get_result();
    
    if ($check_result->num_rows === 0) {
        $insert = "INSERT INTO users (username, password_hash, role, is_active, created_at) 
                   VALUES (?, ?, 'user', 1, NOW())";
        $stmt = $connection->prepare($insert);
        $stmt->bind_param('ss', $row['uname'], $hash);
        $stmt->execute();
        echo "Migrated: {$row['uname']}\n";
    }
    $stmt->close();
}
?>
```

Run once:
```bash
php migrate_users.php
```

✅ **Day 1 Complete:** All tables created and existing users migrated

---

## Day 2-3: Authentication Upgrade

### Step 1: Create `.env` File

```bash
cd /path/to/cmms
cp .env.example .env
chmod 600 .env
```

Edit `.env` and add:

```bash
# Session Configuration
SESSION_TIMEOUT=3600                    # 1 hour
MAX_LOGIN_ATTEMPTS=5                    # Lock after 5 failures
LOCKOUT_DURATION=900                    # 15 minutes
SECURE_COOKIES=true                     # HTTPS only
CSRF_ENABLED=true

# Encryption (generate with: php -r "require 'DataEncryption.php'; echo DataEncryption::generateEncryptionKey();")
ENCRYPTION_KEY=your_base64_encoded_key

# Password Policy
PASSWORD_MIN_LENGTH=12
PASSWORD_EXPIRATION_DAYS=90
PASSWORD_HISTORY_COUNT=5
PASSWORD_WARNING_DAYS=14

# Compliance
SOX_RETENTION_DAYS=2555                 # 7 years
GDPR_RETENTION_DAYS=1095                # 3 years
```

Generate encryption key:
```bash
php -r "require 'DataEncryption.php'; echo DataEncryption::generateEncryptionKey();"
```

Copy output into `.env` as `ENCRYPTION_KEY=...`

### Step 2: Update auth.php

Replace existing `auth.php` with SecurityManager integration:

```php
<?php
session_start();

require 'config.inc.php';
require 'SecurityManager.php';

// Enable error logging
ini_set('log_errors', 1);
ini_set('error_log', 'logs/security.log');

// Initialize security
$security = new SecurityManager($connection, [
    'session_timeout' => intval(getenv('SESSION_TIMEOUT') ?: 3600),
    'max_login_attempts' => intval(getenv('MAX_LOGIN_ATTEMPTS') ?: 5),
    'lockout_duration' => intval(getenv('LOCKOUT_DURATION') ?: 900),
    'secure_cookies' => getenv('SECURE_COOKIES') === 'true',
    'csrf_enabled' => getenv('CSRF_ENABLED') === 'true'
]);

// Initialize session
if (!$security->initializeSession()) {
    // Session expired or invalid
    $_SESSION = [];
    session_destroy();
    header('Location: /index.php?expired=1');
    exit;
}

// Handle login
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['login'])) {
    // Verify CSRF token
    if (!isset($_POST['csrf_token']) || !$security->verifyCSRFToken($_POST['csrf_token'])) {
        $error = 'Invalid request. Please try again.';
    } else {
        $username = $_POST['username'] ?? '';
        $password = $_POST['password'] ?? '';
        
        $result = $security->authenticate($username, $password);
        
        if ($result['success']) {
            if ($result['requires_2fa']) {
                // Redirect to 2FA verification
                $_SESSION['2fa_pending'] = true;
                $_SESSION['user_id_pending'] = $result['user_id'];
                header('Location: verify_2fa.php');
                exit;
            } else {
                // Login successful, redirect to dashboard
                header('Location: index.php');
                exit;
            }
        } else {
            $error = $result['message'];
        }
    }
}

// Handle logout
if (isset($_GET['logout'])) {
    $security->logout();
    header('Location: /index.php');
    exit;
}

// Get CSRF token for form
$csrf_token = $security->generateCSRFToken();
?>

<!DOCTYPE html>
<html>
<head>
    <title>CMMS Login</title>
    <style>
        body { font-family: Arial, sans-serif; background: #f5f5f5; }
        .login-box { max-width: 400px; margin: 100px auto; background: white; padding: 30px; border-radius: 5px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        .form-group { margin-bottom: 15px; }
        label { display: block; margin-bottom: 5px; font-weight: bold; }
        input { width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 3px; box-sizing: border-box; }
        button { width: 100%; padding: 12px; background: #007bff; color: white; border: none; border-radius: 3px; cursor: pointer; }
        button:hover { background: #0056b3; }
        .error { color: #d32f2f; margin-bottom: 15px; }
        .info { color: #1976d2; font-size: 12px; margin-top: 5px; }
    </style>
</head>
<body>
    <div class="login-box">
        <h1>CMMS Login</h1>
        
        <?php if (isset($_GET['expired'])): ?>
            <div class="error">Your session has expired. Please login again.</div>
        <?php endif; ?>
        
        <?php if (isset($error)): ?>
            <div class="error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>
        
        <form method="POST">
            <div class="form-group">
                <label for="username">Username or Email:</label>
                <input type="text" id="username" name="username" required autocomplete="username">
            </div>
            
            <div class="form-group">
                <label for="password">Password:</label>
                <input type="password" id="password" name="password" required autocomplete="current-password">
            </div>
            
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
            <input type="hidden" name="login" value="1">
            
            <button type="submit">Login</button>
            
            <div class="info">
                ⚠️ New security system: Passwords must be at least 12 characters with uppercase, lowercase, numbers, and special characters.
            </div>
        </form>
    </div>
</body>
</html>
?>
```

### Step 3: Test Login

1. Start web server:
```bash
php -S localhost:8000
```

2. Visit: http://localhost:8000/auth.php

3. Try login with existing credentials (password will need reset since they were hashed differently)

### Step 4: Create Password Reset Page

Create `reset_password.php`:

```php
<?php
require 'config.inc.php';
require 'SecurityManager.php';
require 'PasswordPolicyEnforcer.php';

session_start();

if (!isset($_SESSION['user_id'])) {
    header('Location: auth.php');
    exit;
}

$security = new SecurityManager($connection);
$policy = new PasswordPolicyEnforcer($connection);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $old_password = $_POST['old_password'] ?? '';
    $new_password = $_POST['new_password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    
    // Validate new password
    $validation = $policy->validatePassword($new_password, $_SESSION['user']);
    if (!$validation['valid']) {
        $error = 'Password does not meet requirements: ' . implode(', ', $validation['errors']);
    } elseif ($new_password !== $confirm_password) {
        $error = 'Passwords do not match';
    } else {
        // Reset password
        $result = $security->resetPassword($_SESSION['user_id'], $old_password, $new_password);
        if ($result['success']) {
            $success = 'Password changed successfully';
        } else {
            $error = $result['message'];
        }
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Change Password</title>
    <style>
        body { font-family: Arial, sans-serif; }
        .container { max-width: 500px; margin: 50px auto; }
        .form-group { margin-bottom: 15px; }
        label { display: block; font-weight: bold; margin-bottom: 5px; }
        input { width: 100%; padding: 8px; border: 1px solid #ddd; }
        button { padding: 10px 20px; background: #007bff; color: white; border: none; cursor: pointer; }
        .error { color: #d32f2f; margin-bottom: 15px; }
        .success { color: #388e3c; margin-bottom: 15px; }
        .requirements { font-size: 12px; color: #666; margin-top: 10px; }
    </style>
</head>
<body>
    <div class="container">
        <h1>Change Password</h1>
        
        <?php if (isset($error)): ?>
            <div class="error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>
        
        <?php if (isset($success)): ?>
            <div class="success"><?php echo htmlspecialchars($success); ?></div>
        <?php endif; ?>
        
        <form method="POST">
            <div class="form-group">
                <label>Current Password:</label>
                <input type="password" name="old_password" required>
            </div>
            
            <div class="form-group">
                <label>New Password:</label>
                <input type="password" name="new_password" required>
                <div class="requirements">
                    Password must contain:
                    <ul>
                        <li>At least 12 characters</li>
                        <li>One uppercase letter (A-Z)</li>
                        <li>One lowercase letter (a-z)</li>
                        <li>One number (0-9)</li>
                        <li>One special character (!@#$%^&*)</li>
                    </ul>
                </div>
            </div>
            
            <div class="form-group">
                <label>Confirm Password:</label>
                <input type="password" name="confirm_password" required>
            </div>
            
            <button type="submit">Change Password</button>
        </form>
    </div>
</body>
</html>
?>
```

✅ **Days 2-3 Complete:** Users can login with bcrypt passwords and reset passwords

---

## Day 4: 2FA Setup

### Step 1: Create 2FA Setup Page

Create `setup_2fa.php`:

```php
<?php
require 'config.inc.php';
require 'TwoFactorAuth.php';

session_start();

if (!isset($_SESSION['user_id'])) {
    header('Location: auth.php');
    exit;
}

$twofa = new TwoFactorAuth($connection);

// Generate QR code if not yet setup
if (!isset($_SESSION['totp_setup'])) {
    $setup = $twofa->setupTOTP($_SESSION['user_id'], $_SESSION['user'], $_SESSION['email']);
    $_SESSION['totp_setup'] = true;
    $_SESSION['totp_secret'] = $setup['secret'];
} else {
    $setup = [
        'qr_code_url' => 'https://api.qrserver.com/v1/create-qr-code/?size=300x300&data=' . urlencode("otpauth://totp/CMMS:" . $_SESSION['email'] . "?secret=" . $_SESSION['totp_secret']),
        'secret' => $_SESSION['totp_secret'] ?? ''
    ];
}

// Verify code
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['verify_code'])) {
    $code = preg_replace('/\s+/', '', $_POST['code'] ?? '');
    
    $result = $twofa->confirmTOTP($_SESSION['user_id'], $code);
    if ($result['success']) {
        $_SESSION['backup_codes'] = $result['backup_codes'];
        $success = 'Two-Factor Authentication enabled!';
        unset($_SESSION['totp_setup']);
        unset($_SESSION['totp_secret']);
    } else {
        $error = $result['message'] ?? 'Invalid code';
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Setup Two-Factor Authentication</title>
    <style>
        body { font-family: Arial, sans-serif; background: #f5f5f5; }
        .container { max-width: 500px; margin: 50px auto; background: white; padding: 30px; border-radius: 5px; }
        .qr-code { text-align: center; margin: 20px 0; }
        .qr-code img { max-width: 100%; }
        .secret { background: #f0f0f0; padding: 10px; margin: 10px 0; font-family: monospace; }
        .form-group { margin-bottom: 15px; }
        input { width: 100%; padding: 10px; border: 1px solid #ddd; box-sizing: border-box; }
        button { padding: 12px; background: #007bff; color: white; border: none; cursor: pointer; width: 100%; }
        .error { color: #d32f2f; }
        .success { color: #388e3c; }
        .backup-codes { background: #fff9c4; padding: 15px; margin-top: 20px; border-radius: 3px; }
        .backup-codes h3 { margin-top: 0; }
    </style>
</head>
<body>
    <div class="container">
        <h1>Setup Two-Factor Authentication</h1>
        
        <?php if (isset($error)): ?>
            <div class="error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>
        
        <?php if (isset($success)): ?>
            <div class="success"><?php echo htmlspecialchars($success); ?></div>
            
            <?php if (isset($_SESSION['backup_codes'])): ?>
                <div class="backup-codes">
                    <h3>⚠️ Save Your Backup Codes</h3>
                    <p>Save these codes in a safe place. You can use them to regain access if you lose your authenticator device.</p>
                    <code><?php echo implode("\n", $_SESSION['backup_codes']); ?></code>
                </div>
            <?php endif; ?>
        <?php else: ?>
            <h2>Step 1: Scan QR Code</h2>
            <p>Use an authenticator app (Google Authenticator, Authy, Microsoft Authenticator):</p>
            
            <div class="qr-code">
                <img src="<?php echo htmlspecialchars($setup['qr_code_url']); ?>" alt="QR Code">
            </div>
            
            <h2>Step 2: Manual Entry</h2>
            <p>If you can't scan, enter this key manually:</p>
            <div class="secret"><?php echo htmlspecialchars($setup['secret']); ?></div>
            
            <h2>Step 3: Verify Code</h2>
            <p>Enter the 6-digit code from your authenticator:</p>
            
            <form method="POST">
                <div class="form-group">
                    <input type="text" name="code" placeholder="000000" maxlength="6" pattern="[0-9]{6}" required autofocus>
                </div>
                <input type="hidden" name="verify_code" value="1">
                <button type="submit">Verify & Enable 2FA</button>
            </form>
        <?php endif; ?>
    </div>
</body>
</html>
?>
```

### Step 2: Create 2FA Verification Page (Post-Login)

Create `verify_2fa.php`:

```php
<?php
require 'config.inc.php';
require 'TwoFactorAuth.php';

session_start();

if (!isset($_SESSION['2fa_pending']) || !isset($_SESSION['user_id_pending'])) {
    header('Location: auth.php');
    exit;
}

$twofa = new TwoFactorAuth($connection);
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $code = preg_replace('/\s+/', '', $_POST['code'] ?? '');
    $user_id = $_SESSION['user_id_pending'];
    
    // Try TOTP
    if ($twofa->verifyTOTP($code, $code)) {
        // Success - establish full session
        $_SESSION['user_id'] = $user_id;
        unset($_SESSION['2fa_pending']);
        unset($_SESSION['user_id_pending']);
        header('Location: index.php');
        exit;
    }
    
    // Try backup code
    if ($twofa->verifyBackupCode($user_id, $code)) {
        $_SESSION['user_id'] = $user_id;
        unset($_SESSION['2fa_pending']);
        unset($_SESSION['user_id_pending']);
        header('Location: index.php');
        exit;
    }
    
    $error = 'Invalid 2FA code or backup code';
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Verify Two-Factor Authentication</title>
    <style>
        body { font-family: Arial; background: #f5f5f5; }
        .container { max-width: 400px; margin: 100px auto; background: white; padding: 30px; border-radius: 5px; }
        .error { color: #d32f2f; margin-bottom: 15px; }
        input { width: 100%; padding: 10px; margin-bottom: 10px; border: 1px solid #ddd; }
        button { width: 100%; padding: 12px; background: #007bff; color: white; border: none; cursor: pointer; }
    </style>
</head>
<body>
    <div class="container">
        <h1>Verify 2FA Code</h1>
        
        <?php if ($error): ?>
            <div class="error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>
        
        <form method="POST">
            <p>Enter the 6-digit code from your authenticator app:</p>
            <input type="text" name="code" placeholder="000000" maxlength="6" pattern="[0-9]{6}" required autofocus>
            <button type="submit">Verify</button>
        </form>
        
        <p style="margin-top: 20px; font-size: 12px; color: #666;">
            Or enter one of your backup codes if you don't have access to your authenticator.
        </p>
    </div>
</body>
</html>
?>
```

✅ **Day 4 Complete:** Users can setup and verify 2FA

---

## Day 5: Role-Based Access Control

### Step 1: Create Admin User Management Page

Create `admin_users.php`:

```php
<?php
require 'config.inc.php';
require 'SecurityManager.php';
require 'RoleBasedAccessControl.php';

session_start();

if (!SecurityManager::isAuthenticated() || SecurityManager::getCurrentRole() !== 'admin') {
    header('HTTP/1.0 403 Forbidden');
    exit('Access denied');
}

$rbac = new RoleBasedAccessControl($connection);
$current_user = $_SESSION['user_id'];

// Get all users
$users_sql = "SELECT user_id, username, email, role FROM users ORDER BY username";
$users_result = $connection->query($users_sql);
$users = [];
while ($row = $users_result->fetch_assoc()) {
    $row['roles'] = $rbac->getUserRoles($row['user_id']);
    $users[] = $row;
}

// Assign role to user
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $target_user = intval($_POST['user_id'] ?? 0);
    $role_id = intval($_POST['role_id'] ?? 0);
    
    if ($_POST['action'] === 'assign_role') {
        $result = $rbac->assignRoleToUser($target_user, $role_id, $current_user);
        $message = $result['message'];
    } elseif ($_POST['action'] === 'remove_role') {
        $result = $rbac->removeRoleFromUser($target_user, $role_id, $current_user);
        $message = $result['message'];
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>User Management</title>
    <style>
        body { font-family: Arial; margin: 20px; }
        table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        th, td { padding: 12px; text-align: left; border-bottom: 1px solid #ddd; }
        th { background: #f0f0f0; font-weight: bold; }
        button { padding: 8px 12px; background: #007bff; color: white; border: none; cursor: pointer; }
        .message { padding: 10px; margin-bottom: 20px; background: #d4edda; color: #155724; border-radius: 3px; }
    </style>
</head>
<body>
    <h1>User Management</h1>
    
    <?php if (isset($message)): ?>
        <div class="message"><?php echo htmlspecialchars($message); ?></div>
    <?php endif; ?>
    
    <table>
        <tr>
            <th>Username</th>
            <th>Email</th>
            <th>Roles</th>
            <th>Actions</th>
        </tr>
        <?php foreach ($users as $user): ?>
        <tr>
            <td><?php echo htmlspecialchars($user['username']); ?></td>
            <td><?php echo htmlspecialchars($user['email']); ?></td>
            <td>
                <?php 
                foreach ($user['roles'] as $role) {
                    echo htmlspecialchars($role['role_name']) . ' ';
                }
                ?>
            </td>
            <td>
                <form method="POST" style="display: inline;">
                    <input type="hidden" name="user_id" value="<?php echo $user['user_id']; ?>">
                    <input type="hidden" name="role_id" value="2">
                    <input type="hidden" name="action" value="assign_role">
                    <button type="submit">Add Role</button>
                </form>
            </td>
        </tr>
        <?php endforeach; ?>
    </table>
</body>
</html>
?>
```

### Step 2: Add Permission Checks to Existing Code

In your work order creation page, add:

```php
<?php
require 'RoleBasedAccessControl.php';

session_start();

if (!SecurityManager::isAuthenticated()) {
    header('Location: auth.php');
    exit;
}

$rbac = new RoleBasedAccessControl($connection);

// Check permission
if (!$rbac->hasPermission($_SESSION['user_id'], 'work_orders', 'create')) {
    header('HTTP/1.0 403 Forbidden');
    exit('You do not have permission to create work orders');
}

// ... rest of create WO logic
?>
```

### Step 3: Setup Default Roles

Run this once:

```php
<?php
require 'config.inc.php';
require 'RoleBasedAccessControl.php';

$rbac = new RoleBasedAccessControl($connection);

// Default roles are already in database from security_schema.sql
// Just assign admin role to your admin user
$rbac->assignRoleToUser(1, 1, 'SYSTEM'); // Assign admin role (ID 1) to user ID 1

echo "Roles configured!";
?>
```

✅ **Day 5 Complete:** RBAC is implemented and users have role-based permissions

---

## Day 6: Encryption

### Step 1: Configure Encryption Key

```bash
# Generate key
php -r "require 'DataEncryption.php'; echo DataEncryption::generateEncryptionKey();"

# Add to .env
echo "ENCRYPTION_KEY=paste_output_here" >> .env

# Verify it's set
grep ENCRYPTION_KEY .env
```

### Step 2: Encrypt Sensitive Fields

```php
<?php
require 'config.inc.php';
require 'DataEncryption.php';

$encryption = new DataEncryption($connection, getenv('ENCRYPTION_KEY'));

// Example: Encrypt user phone numbers
$sql = "SELECT user_id, phone FROM users WHERE phone IS NOT NULL AND phone NOT LIKE '%encrypted%'";
$result = $connection->query($sql);

while ($row = $result->fetch_assoc()) {
    $encrypted = $encryption->encrypt($row['phone']);
    
    $update = "UPDATE users SET phone = ? WHERE user_id = ?";
    $stmt = $connection->prepare($update);
    $stmt->bind_param('si', $encrypted, $row['user_id']);
    $stmt->execute();
    
    echo "Encrypted phone for user {$row['user_id']}\n";
}

echo "Encryption complete!";
?>
```

Run:
```bash
php encrypt_data.php
```

### Step 3: Update Code to Decrypt on Display

In user profile page:

```php
<?php
require 'DataEncryption.php';

$encryption = new DataEncryption($connection, getenv('ENCRYPTION_KEY'));

// Get user
$sql = "SELECT phone FROM users WHERE user_id = ?";
$stmt = $connection->prepare($sql);
$stmt->bind_param('i', $_SESSION['user_id']);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();

// Decrypt for display
if ($user['phone']) {
    $decrypted_phone = $encryption->decrypt($user['phone']);
    echo "Phone: " . htmlspecialchars($decrypted_phone);
    
    // Log access
    $encryption->logSensitiveDataAccess($_SESSION['user_id'], 'user', $_SESSION['user_id'], 'phone', 'Profile view');
}
?>
```

✅ **Day 6 Complete:** Sensitive data is encrypted at rest

---

## Day 7: Compliance Audit Logging

### Step 1: Integrate Auditor into Key Operations

In work order creation:

```php
<?php
require 'ComplianceAuditor.php';

$auditor = new ComplianceAuditor($connection);

// When WO is created
// ... create WO ...

$auditor->logWorkOrderCreation($user_id, $wo_id, [
    'priority' => $priority,
    'estimated_cost' => $estimated_cost,
    'assigned_to' => $assigned_to
]);
?>
```

In work order completion:

```php
<?php
// When WO is marked complete
$auditor->logWorkOrderCompletion($user_id, $wo_id, $actual_cost);
?>
```

### Step 2: Generate Compliance Reports

Create `compliance_reports.php`:

```php
<?php
require 'config.inc.php';
require 'ComplianceAuditor.php';
require 'ComplianceReportGenerator.php';

session_start();

if (!SecurityManager::isAuthenticated() || SecurityManager::getCurrentRole() !== 'admin') {
    header('HTTP/1.0 403 Forbidden');
    exit;
}

$auditor = new ComplianceAuditor($connection);
$reporter = new ComplianceReportGenerator($connection, $auditor);

// Get last 90 days
$end_date = date('Y-m-d');
$start_date = date('Y-m-d', strtotime('-90 days'));

// Generate SOX report
if (isset($_GET['report']) && $_GET['report'] === 'sox') {
    $report = $reporter->generateSOXReport($start_date, $end_date);
    header('Content-Type: application/json');
    echo json_encode($report, JSON_PRETTY_PRINT);
    exit;
}

// Generate GDPR report
if (isset($_GET['report']) && $_GET['report'] === 'gdpr') {
    $report = $reporter->generateGDPRReport($start_date, $end_date);
    header('Content-Type: application/json');
    echo json_encode($report, JSON_PRETTY_PRINT);
    exit;
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Compliance Reports</title>
    <style>
        body { font-family: Arial; margin: 20px; }
        .report-link { display: inline-block; padding: 15px 20px; margin: 10px; background: #007bff; color: white; text-decoration: none; border-radius: 3px; }
        .report-link:hover { background: #0056b3; }
    </style>
</head>
<body>
    <h1>Compliance Reports</h1>
    <p>Period: <?php echo $start_date; ?> to <?php echo $end_date; ?></p>
    
    <a href="?report=sox" class="report-link">SOX Audit Trail (Financial)</a>
    <a href="?report=gdpr" class="report-link">GDPR Data Handling</a>
    <a href="?report=iso15001" class="report-link">ISO 15001 Maintenance</a>
    
    <h2>Report Definitions</h2>
    <ul>
        <li><strong>SOX:</strong> Financial transaction audit trail (Sarbanes-Oxley compliance)</li>
        <li><strong>GDPR:</strong> Personal data handling and access logs</li>
        <li><strong>ISO 15001:</strong> Maintenance schedule compliance and equipment status</li>
    </ul>
</body>
</html>
?>
```

✅ **Day 7 Complete:** All activities are logged and compliance reports can be generated

---

## Testing Checklist

Before deploying to production, verify:

### Authentication (30 min)
- [ ] User can login with correct password
- [ ] User is denied with incorrect password
- [ ] Account locks after 5 failed attempts
- [ ] Account unlocks after 15 minutes  
- [ ] Session expires after 1 hour idle
- [ ] User can reset password from profile
- [ ] Password meets 12-char + complexity requirements

### Two-Factor Authentication (45 min)
- [ ] User can setup TOTP (QR code)
- [ ] User can scan QR code in Google Authenticator
- [ ] User can enter 6-digit code to verify login
- [ ] Backup codes are generated and work
- [ ] Device trust option works
- [ ] 2FA can be disabled

### Role-Based Access Control (60 min)
- [ ] Admin can create new roles
- [ ] Admin can assign roles to users
- [ ] User with Technician role cannot create work orders (only Manager+)
- [ ] User with Manager role can approve WOs
- [ ] Viewer role can only read dashboards
- [ ] Permission changes take effect immediately

### Encryption (45 min)
- [ ] Sensitive fields show as encrypted in database
- [ ] Decrypted value is correct when displayed
- [ ] Encryption audit log records all access
- [ ] Key rotation process works

### Audit Logging (60 min)
- [ ] Login/logout logged with IP address
- [ ] Work order creation logged with details
- [ ] Work order modification logged with changes
- [ ] Access control changes logged
- [ ] Audit logs visible in admin panel
- [ ] Old logs automatically archived after 7 years

### Compliance Reports (45 min)
- [ ] SOX report shows all transactions
- [ ] GDPR report shows data access patterns
- [ ] ISO 15001 report shows PM compliance
- [ ] Reports can be exported as JSON/CSV/HTML

### Security Headers (15 min)
- [ ] HTTPS enforced (no HTTP)
- [ ] HSTS header present
- [ ] CSRF tokens on all forms
- [ ] Cookies marked as HttpOnly and Secure

---

## Production Deployment

### Pre-Deployment

1. **Backup Database**
```bash
mysqldump -u user -p database > backup_pre_security.sql
```

2. **Backup .env**
```bash
cp .env .env.backup
```

3. **Review File Permissions**
```bash
chmod 600 .env                      # Owner only
chmod 755 /var/www/html            # Web directory
chmod 700 /var/www/html/config     # Config directory
```

4. **Enable HTTPS**
- Install SSL certificate (Let's Encrypt for free)
- Configure web server to force HTTPS

5. **Configure .env for Production**
```bash
SESSION_TIMEOUT=3600
SECURE_COOKIES=true
HTTPS_ONLY=true
ENCRYPTION_KEY=your_production_key
```

### Deployment Steps

1. **Deploy Code**
```bash
git clone ... (or copy files)
composer install  # Install dependencies if using Composer
chmod -R 755 app/
chmod 700 .env
```

2. **Run Database Schema**
```bash
mysql -u user -p database < security_schema.sql
```

3. **Test Authentication**
```bash
curl -X POST https://cmms.example.com/auth.php \
  -d "username=admin&password=test_password"
```

4. **Test API (if using REST API)**
```bash
curl -X GET https://cmms.example.com/api/v1/users \
  -H "Authorization: Bearer token_here"
```

5. **Verify Logs**
```bash
tail -f logs/security.log
tail -f logs/error.log
```

6. **Monitor for Errors**
- Check failed login attempts
- Monitor CPU/memory  
- Verify database connections
- Check disk space (for logs)

### Post-Deployment

1. **Announce to Users**
```
Subject: CMMS Security Enhancements

Dear Users,

We've upgraded CMMS with enterprise security features:
- Passwords now require 12+ characters with special characters
- Two-factor authentication available (optional/required based on role)
- All activities logged for compliance
- Enhanced encryption for sensitive data

Please reset your password on first login.

Support: [email]
```

2. **Roll Out in Phases**
- Week 1: Admins & managers test
- Week 2: Department leads uses
- Week 3-4: Full user rollout

3. **Monitor**
- Watch for login issues
- Review audit logs for anomalies
- Collect user feedback

---

## Support & References

### Documentation Files
- **SECURITY_ARCHITECTURE.md** - Complete technical reference
- **security_schema.sql** - Database schema
- **SecurityManager.php** - Authentication class
- **TwoFactorAuth.php** - 2FA class
- **RoleBasedAccessControl.php** - RBAC class
- **DataEncryption.php** - Encryption class
- **ComplianceAuditor.php** - Audit logging class
- **ComplianceReportGenerator.php** - Report generation class

### Key Contacts
- **Security Issues:** security@example.com
- **Support:** support@example.com
- **Compliance:** compliance@example.com

### Common Issues

**Q: Users forgot their password?**
A: Use password reset page or admin can force reset

**Q: 2FA device lost?**
A: Use backup codes to login, then regenerate new codes

**Q: Encryption key lost?**
A: Contact security team immediately. Old encrypted data cannot be recovered.

**Q: Audit logs growing too large?**
A: System automatically archives logs older than 7 years, runs monthly

**Q: Need to change password expiration?**
A: Edit PASSWORD_EXPIRATION_DAYS in .env and restart

---

**Implementation Complete! Your CMMS now has enterprise-grade security and compliance.**

