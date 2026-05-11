# First-Time Password Change System - Implementation Complete

## Overview

A complete first-time password change system has been implemented to fix the registration gap where developers create users but those users cannot access the system. The system ensures new users:

1. Receive a temporary password via email
2. Must change it on first login
3. Cannot bypass password change to access the system
4. Have full system access after changing their password

---

## System Architecture

### Database Schema

**New Columns Added to `users` Table:**
```sql
ALTER TABLE users ADD COLUMN password_change_required INTEGER DEFAULT 0;
ALTER TABLE users ADD COLUMN temporary_password_sent_at TEXT;
```

- `password_change_required`: Flag (0/1) indicating if user must change password
- `temporary_password_sent_at`: Timestamp when temporary password was sent

---

## Components Implemented

### 1. Email Functions (`access.php`)

#### `generate_temporary_password($length = 12)`
Generates a cryptographically secure temporary password with mixed character types:
- Length: 12 characters (configurable)
- Includes: Uppercase, lowercase, numbers, special characters
- Ensures password meets minimum strength requirements

#### `send_temporary_password_email($to_email, $username, $temp_password)`
Sends welcome email with temporary credentials:
- Uses PHPMailer for SMTP
- HTML formatted email with clear instructions
- Includes login portal link
- Falls back gracefully if SMTP not configured
- Logs errors for troubleshooting

**Email Template Features:**
- Welcome message with username
- Temporary password in code block
- Security warning about password change requirement
- Login portal link
- Professional styling with Maintenix branding

### 2. User Registration Flow (`access.php`)

**Modified User Creation Process:**
1. Developer creates authorization code
2. Admin approves authorization with phone/country_code
3. When user is created:
   - `password_change_required = 1` (flag set)
   - Phone number stored
   - Country code stored
   - Temporary password sent to email
   - WhatsApp integration enabled

**Registration Form Enhancements:**
- Country code dropdown (50+ countries with flags)
- Phone number input field
- Automatic phone/country code preservation from authorization

### 3. Password Change Page (`change_password.php`)

**Features:**
- Professional Bootstrap UI with gradient background
- Security-focused design
- Multi-step password validation

**Validation Rules:**
- Minimum 8 characters
- At least one uppercase letter (A-Z)
- At least one number (0-9)
- Password confirmation match

**Password Strength Indicator:**
- Real-time feedback as user types
- Visual indicators: Weak, Fair, Good
- Helpfully suggests improvements

**After Successful Change:**
- Password hash updated in database
- `password_change_required` flag reset to 0
- User redirected to dashboard
- Session updated to reflect no further password changes needed

### 4. Login Redirect Logic (`auth.php`)

**Login Process Modified:**
1. User enters credentials (username/email + password)
2. Credentials verified against password hash
3. Session variables set including:
   - `user_id`
   - `username`
   - `email`
   - `phone` (from session)
   - `country_code` (from session)
   - `password_change_required` flag
4. **NEW**: If `password_change_required = 1`:
   - Redirect to `change_password.php`
   - User cannot access any other page
5. If `password_change_required = 0`:
   - Check maintenance mode
   - Redirect to dashboard or maintenance page

---

## User Flow Diagram

```
User Created by Developer
    ↓
Temporary Password Generated
    ↓
Email Sent to New User
    ↓
New User Attempts Login
    ↓
Credentials Verified (using email-provided temp password)
    ↓
Is password_change_required = 1?
    ├─ YES → Redirect to change_password.php
    │        ↓
    │        User Enters Current (Temp) Password
    │        User Enters New Password
    │        User Confirms New Password
    │        ↓
    │        Password Hash Updated
    │        password_change_required = 0
    │        ↓
    │        Redirect to Dashboard
    │        ↓
    │        FULL ACCESS GRANTED
    │
    └─ NO → Redirect to Dashboard (normal login)
            FULL ACCESS GRANTED
```

---

## Email Configuration

**Required Settings in `config.inc.php`:**
```php
$SMTP_ENABLED = true;                      // Enable email sending
$SMTP_HOST = 'smtp.gmail.com';            // Your SMTP server
$SMTP_PORT = 587;                         // SMTP port (587=TLS, 465=SSL)
$SMTP_USER = 'your-email@gmail.com';      // SMTP username
$SMTP_PASS = 'app-password';              // SMTP password
$SMTP_SECURE = 'tls';                     // Security: tls/ssl/''
$SMTP_FROM_EMAIL = 'noreply@example.com'; // From email address
$SMTP_FROM_NAME = 'Maintenix';            // From display name
```

**Fallback Behavior:**
- If PHPMailer not installed: Emails logged as "assume success"
- If SMTP disabled: System assumes email was sent (audit trail in logs)
- Errors logged to PHP error log for troubleshooting

---

## Files Created/Modified

### New Files:
- `change_password.php` - Password change interface
- `test_password_change_flow.php` - System verification script
- `check_migration_status.php` - Migration diagnostics
- `apply_password_change_column.php` - Manual migration application

### Modified Files:
- `access.php` - Added helper functions, modified user registration
- `auth.php` - Added password change redirect logic, fetch new columns
- `migrations/010_add_password_change_required.sql` - Schema migration

---

## Testing & Verification

### System Test Script: `test_password_change_flow.php`

The comprehensive test script verifies:

1. ✓ Database columns exist and are correct type
2. ✓ All required functions are defined
3. ✓ SMTP email configuration is set
4. ✓ PHPMailer library is installed
5. ✓ Temporary password generation works
6. ✓ Required pages exist
7. ✓ Auth.php contains redirect logic

**Run Test:**
```bash
cd /path/to/cmms
php test_password_change_flow.php
```

### Manual End-to-End Test:

1. **Create Test User:**
   - Go to `access.php` (developer account)
   - Create new user with temporary password
   - User receives email with credentials

2. **First Login:**
   - Go to `auth.php`
   - Login with username and temporary password
   - Should redirect to `change_password.php`

3. **Change Password:**
   - Enter temporary password
   - Enter new password (must meet requirements)
   - Confirm new password
   - Click "Update Password"

4. **Verify Access:**
   - Should redirect to dashboard
   - User should have full system access
   - Logout and login with new password should work normally

---

## Security Considerations

### Password Security:
- Temporary passwords use cryptographically strong random generation
- Passwords hashed with PHP password_hash() (bcrypt)
- Temporary passwords never logged or displayed after initial email
- Session regenerated on login to prevent fixation attacks

### Email Security:
- SMTP connection encrypted (TLS/SSL)
- Credentials stored in .env or config.inc.php
- Email body sanitized HTML with no user-controlled content
- From address configurable for brand/domain specificity

### Access Control:
- Password change page checks session existence
- Users without `password_change_required` redirected to dashboard
- Cannot access system features while password change required
- Session updated after successful password change

---

## Configuration Checklist

- [ ] Email SMTP credentials configured in config.inc.php
- [ ] PHPMailer installed via composer (`composer install`)
- [ ] Migration applied (password_change_required column exists)
- [ ] Test script run successfully
- [ ] change_password.php page accessible
- [ ] auth.php contains redirect logic verified

---

## Troubleshooting

### Emails Not Being Sent:
1. Check `$SMTP_ENABLED = true` in config.inc.php
2. Verify SMTP credentials (username, password, host, port)
3. Check PHP error log: `/logs/php_error.log`
4. For Gmail: Use app-specific password, enable "Less secure app access"
5. Test with: `php test_password_change_flow.php`

### Column Not Found Error:
1. Run: `php apply_password_change_column.php`
2. Verify with: `php check_migration_status.php`
3. Check database structure: `PRAGMA table_info(users);`

### Redirect Not Working:
1. Verify auth.php modified correctly
2. Check session values: Add `echo $_SESSION['password_change_required'];` to debug
3. Ensure change_password.php exists and is readable

### Password Validation Fails:
1. Requirements: 8+ chars, 1 uppercase, 1 number
2. Special chars (!, @, #, $, %) recommended but optional
3. Passwords must not match (no repeated characters)
4. Error message displayed on form failure

---

## Future Enhancements

- [ ] Temporary password expiration (e.g., 24 hours)
- [ ] Email notification on successful password change
- [ ] Administrator password reset for locked accounts
- [ ] Password history to prevent reuse
- [ ] Strength meter with visual feedback
- [ ] Multi-factor authentication support
- [ ] Audit trail of all password changes
- [ ] Customizable password requirements by role

---

## Deployment Notes

**Production Deployment:**
1. Ensure email credentials are in production environment
2. Update `SMTP_FROM_EMAIL` to production domain
3. Test complete flow with single test user first
4. Monitor logs during initial rollout
5. Communicate new password requirements to team

**Rollback Instructions:**
1. If issues detected, revert auth.php redirects
2. Set `password_change_required = 0` for all users
3. Users can login without password change
4. Revisit and fix issues before re-enabling

---

## Support

For issues, check:
1. `test_password_change_flow.php` - System health check
2. PHP error log - Technical details
3. Database schema - Column existence
4. Email configuration - SMTP settings
5. change_password.php - Page accessibility

---

