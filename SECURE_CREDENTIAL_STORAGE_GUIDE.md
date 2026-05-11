# Secure Credential Storage & Management Guide

**Objective**: Store ERP/API credentials securely so they're never exposed in code or logs  
**Audience**: Lead developers, DevOps engineers, system administrators  
**Updated**: March 19, 2026

---

## GOLDEN RULE

**❌ NEVER:**
- Hardcode credentials in PHP files
- Commit .env to version control
- Log credentials to error_log
- Send credentials via unencrypted channel
- Display credentials in error messages

**✅ ALWAYS:**
- Store in environment variables or encrypted files
- Use `.env` file (ignored by .gitignore)
- Rotate credentials annually
- Audit all credential access
- Encrypt credentials at rest

---

## QUICK START (5 MINUTES)

### Step 1: Create .env File

```bash
# Copy template
cp .env.example .env

# Edit .env with your credentials
nano .env
```

### Step 2: Add to .gitignore

```bash
# Ensure .env is never committed
echo ".env" >> .gitignore
echo ".env.local" >> .gitignore
echo ".env.*.php" >> .gitignore
```

### Step 3: Load in Your Code

```php
// At the top of config.inc.php or api/v1/index.php:

// Load environment variables from .env file
$env_file = __DIR__ . '/.env';
if (file_exists($env_file)) {
    $env_vars = parse_ini_file($env_file);
    foreach ($env_vars as $key => $value) {
        $_ENV[$key] = $value;
        putenv("$key=$value");
    }
}

// Now use:
$sap_host = $_ENV['SAP_HOST'] ?? null;
$sap_user = $_ENV['SAP_USERNAME'] ?? null;
$sap_pass = $_ENV['SAP_PASSWORD'] ?? null;
```

### Step 4: File Permissions

```bash
# Make .env readable only by owner (user: 600)
chmod 600 .env

# Verify
ls -la .env
# Should show: -rw------- (only owner can read)
```

---

## DETAILED STORAGE METHODS

### METHOD 1: Environment Variables (.env file)

**Best for**: Small teams, development, staging servers

**Setup:**
```bash
# 1. Create .env
SAP_HOST=https://sap.example.com
SAP_USERNAME=cmms_user
SAP_PASSWORD=MySecure$Pass123
NETSUITE_CLIENT_ID=xxx-xxx-xxx
NETSUITE_CLIENT_SECRET=yyy-yyy-yyy
AWS_ACCESS_KEY_ID=AKIA...
AWS_SECRET_ACCESS_KEY=...
```

**Usage in Code:**
```php
$sap_config = [
    'host' => $_ENV['SAP_HOST'],
    'username' => $_ENV['SAP_USERNAME'],
    'password' => $_ENV['SAP_PASSWORD']
];
```

**Pros:**
- Simple to implement
- Works with all frameworks
- Easy to change per environment
- Composer/Laravel compatible

**Cons:**
- Credentials on disk in plaintext
- Must protect file permissions
- No encryption at rest
- Visible in process listing if careless

**Security Hardening:**
```php
// Validate credentials are loaded
if (empty($_ENV['SAP_PASSWORD'])) {
    error_log("ERROR: SAP_PASSWORD not set in .env");
    die("Configuration error");
}

// Clear sensitive variables after use
unset($_ENV['SAP_PASSWORD']);

// Don't display in debug output
if (isset($_GET['debug'])) {
    // Mask sensitive data
    $config_display = $_ENV;
    $config_display['SAP_PASSWORD'] = '***REDACTED***';
}
```

---

### METHOD 2: Encrypted Environment Variables

**Best for**: Production servers with high security requirements

**Setup with GPG Encryption:**

```bash
# 1. Create plaintext credentials file
cat > credentials.txt << EOF
SAP_HOST=https://sap.example.com
SAP_USERNAME=cmms_user
SAP_PASSWORD=MySecure$Pass123
EOF

# 2. Encrypt the file
gpg --cipher-algo AES256 --symmetric credentials.txt
# Creates: credentials.txt.gpg (encrypted)
# Removes: credentials.txt (plaintext deleted)

# 3. Store GPG passphrase separately (e.g., in another file, or pass as env var)

# 4. Deploy only credentials.txt.gpg to server

# 5. In your application, decrypt on startup:
php decrypt_credentials.php
```

**PHP Decryption Script (decrypt_credentials.php):**

```php
<?php
/**
 * Decrypt credentials at application startup
 * Run once, store in memory, clear from disk
 */

// Store GPG passphrase in environment (set by deployment system)
$gpg_passphrase = $_ENV['GPG_PASSPHRASE'] ?? null;

if (!$gpg_passphrase) {
    die("ERROR: GPG_PASSPHRASE not set. Cannot decrypt credentials.");
}

// Decrypt credentials file
$encrypted_file = __DIR__ . '/credentials.txt.gpg';
$decrypted_file = tempnam(sys_get_temp_dir(), 'creds_');

// Use GPG to decrypt
$cmd = sprintf(
    'echo %s | gpg --quiet --batch --yes --passphrase-fd 0 --output %s %s',
    escapeshellarg($gpg_passphrase),
    escapeshellarg($decrypted_file),
    escapeshellarg($encrypted_file)
);

$result = shell_exec($cmd);

if (!file_exists($decrypted_file)) {
    die("ERROR: Failed to decrypt credentials");
}

// Load decrypted credentials into $_ENV
$credentials = parse_ini_file($decrypted_file);
foreach ($credentials as $key => $value) {
    $_ENV[$key] = $value;
    putenv("$key=$value");
}

// Securely delete temporary file
unlink($decrypted_file);

// Clear GPG passphrase from memory
unset($gpg_passphrase);
?>
```

**Pros:**
- Credentials encrypted at rest on disk
- Passphrase is separate from credentials
- Industry standard (GPG)
- Good for high-security environments

**Cons:**
- Requires GPG installation
- More complex setup
- Performance: decrypt on every startup
- Requires passphrase management

---

### METHOD 3: Key Vault Service

**Best for**: Enterprise environments, multiple servers

**Options:**
- AWS Secrets Manager
- Azure Key Vault
- HashiCorp Vault
- 1Password Secrets Automation

**Setup Example (AWS Secrets Manager):**

```php
<?php
// Load credentials from AWS Secrets Manager at startup

require 'vendor/autoload.php';

use Aws\SecretsManager\SecretsManagerClient;
use Aws\Exception\AwsException;

$client = new SecretsManagerClient([
    'version' => 'latest',
    'region'  => 'us-east-1'
]);

try {
    $result = $client->getSecretValue([
        'SecretId' => 'cmms/sap-credentials'
    ]);
    
    $secret = json_decode($result['SecretString'], true);
    
    // Load into $_ENV
    foreach ($secret as $key => $value) {
        $_ENV[$key] = $value;
    }
    
} catch (AwsException $e) {
    error_log("ERROR: Failed to retrieve secrets: " . $e->getMessage());
    die("Configuration error");
}
?>
```

**Pros:**
- Credentials never stored on disk
- Audit trail of all access
- Automatic rotation available
- High security, industry standard
- IAM-based access control

**Cons:**
- Requires cloud subscription ($)
- More complex deployment
- Requires AWS/Azure/etc setup
- Internet connectivity needed

---

### METHOD 4: PHP Constants (Limited Use)

**Best for**: Development only, NOT production

**Setup:**

```php
// config.inc.php
define('SAP_HOST', 'https://sap.example.com');
define('SAP_USERNAME', 'cmms_user');

// Access via:
$host = SAP_HOST;
```

**❌ ISSUES:**
- Constants are immutable
- Appear in phpinfo()
- Hard to change per environment
- Not recommended for sensitive data

---

## CREDENTIAL ROTATION PLAN

### Annual Rotation Process

```
Year 1: Original credentials issued
Year 2: Issue new credentials (30-day overlap)
        - Update .env with new credentials
        - Test integration still works
        - SAP/NetSuite admin disables old credentials
Year 3: Repeat
```

### Rotation Checklist

```
☐ Generate new SAP service account (or OAuth token)
☐ Generate new NetSuite OAuth credentials
☐ Generate new AWS/API keys
☐ Test new credentials in staging environment
☐ Update .env on production server
☐ Restart application to reload environment
☐ Verify all integrations working with new credentials
☐ Request ERP admin revoke old credentials
☐ Document change in security log
☐ Alert team that credentials have rotated
```

**Automation (recommended):**

```bash
# cron job: Run annually (e.g., Jan 1 at 2 AM)
0 2 1 1 * /home/deploy/rotate_credentials.sh
```

---

## CREDENTIAL VALIDATION

### On Startup: Verify Credentials Are Loaded

```php
<?php
// In config.inc.php or application bootstrap:

function validateCredentials() {
    $required = [
        'SAP_HOST',
        'SAP_USERNAME',
        'SAP_PASSWORD',
        'API_TOKEN_SECRET'
    ];
    
    $missing = [];
    foreach ($required as $key) {
        if (empty($_ENV[$key])) {
            $missing[] = $key;
        }
    }
    
    if (!empty($missing)) {
        error_log("ERROR: Missing required credentials: " . implode(', ', $missing));
        // In production, fail hard
        if (php_sapi_name() !== 'cli') {
            http_response_code(500);
            die("Application misconfiguration");
        }
    }
}

validateCredentials();
?>
```

### Never Log Credentials

```php
// ❌ WRONG - credential exposed in log
error_log("Connecting to SAP with password: " . $_ENV['SAP_PASSWORD']);

// ✅ RIGHT - password masked
error_log("Connecting to SAP as " . $_ENV['SAP_USERNAME']);

// ✅ RIGHT - use try/catch without logging full error
try {
    $sap->connect();
} catch (Exception $e) {
    error_log("SAP connection failed (check credentials)");
    // Don't log the full exception message if it contains credentials
}
```

---

## PER-ENVIRONMENT CONFIGURATION

### Development (Local Machine)

```bash
# .env.local (ignored by git)
SAP_HOST=https://sap-test.example.com
SAP_USERNAME=dev_user
SAP_PASSWORD=devpass123
APP_ENV=development
DEBUG=true
```

### Staging Server

```bash
# /etc/environment (system-wide, protected)
export SAP_HOST=https://sap-staging.example.com
export SAP_USERNAME=staging_user
export SAP_PASSWORD=stagingpass456
```

### Production Server

```bash
# Deploy only: Encrypted credentials in secure location
# /opt/cmms/.env.encrypted
# Decrypted at application startup via GPG or Vault

# Permissions:
# chmod 600 /opt/cmms/.env.encrypted
# ls -la shows: -rw------- 

# Owner only:
# chown cmms:cmms /opt/cmms/.env.encrypted
```

---

## SECURITY AUDIT CHECKLIST

Before going to production, verify:

```
☐ .env file exists and contains all credentials
☐ .env is in .gitignore (never committed)
☐ .env file permissions are 600 (owner only)
☐ No credentials hardcoded in any PHP file
☐ No credentials visible in error logs
☐ No credentials in PHP comments
☐ Database doesn't store plaintext passwords
☐ API keys never logged in api_logs table
☐ Staging server uses different credentials than prod
☐ No credentials in application output/debug
☐ Team members know not to share .env file
☐ Backup procedure includes securing .env
☐ Credential rotation scheduled annually
☐ Access to .env restricted to authorized personnel
☐ Audit trail shows who can access credentials
☐ Disaster recovery plan includes credential recovery
```

---

## TROUBLESHOOTING

### "Credentials not loading"

```php
// Debug: Check what's in $_ENV
error_log(print_r($_ENV, true));  // Shows all env vars

// Check if .env file exists
if (!file_exists('.env')) {
    error_log(".env file not found at: " . __DIR__ . '/.env');
}

// Verify parse_ini_file works
$parsed = parse_ini_file('.env');
if ($parsed === false) {
    error_log("Failed to parse .env file");
}
```

### "Permission denied reading .env"

```bash
# Check file ownership
ls -la .env

# Should be: -rw------- (600) with your user as owner

# Fix:
chmod 600 .env
chown $USER:$USER .env  # Replace $USER with actual username
```

### "Credentials work locally but not on server"

```php
// Different PHP versions handle environment variables differently

// Test on server:
php -r "print_r($_ENV);"

// Check php.ini setting:
php -r "phpinfo();" | grep variables_order

// If needed, explicitly set:
putenv("SAP_HOST=" . $_ENV['SAP_HOST']);
```

---

## BEST PRACTICES SUMMARY

1. **Store in .env** (never in PHP files)
2. **Protect permissions** (chmod 600)
3. **Ignore in git** (.gitignore)
4. **Validate on startup** (fail if missing)
5. **Never log credentials** (mask in output)
6. **Use different creds per environment** (dev/staging/prod)
7. **Rotate annually** (set calendar reminder)
8. **Encrypt at rest** (for production)
9. **Use key vault** (for enterprise)
10. **Audit access** (who, when, what was accessed)

---

## QUICK REFERENCE CARD

```
METHOD              SECURITY    COMPLEXITY    BEST FOR
─────────────────────────────────────────────────────────
.env file           Medium      Low           Development, Staging
Encrypted .env      High        Medium        Production
Key Vault (AWS)     Very High   High          Enterprise
Constants           Low         Low           AVOID
Hardcoded           None        Low           NEVER

ROTATION FREQUENCY: Annually
STORAGE LOCATION: /root or /opt (owner only)
BACKUP: Encrypted, separate location
ACCESS: Lead dev + ops only
```

---

**Questions? Contact your IT Security team.**
