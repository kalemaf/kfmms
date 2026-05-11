# User Creation System - Quick Reference

## 📋 Quick Overview

**What:** Enforced password change on first login for new users
**Why:** Ensures users set their own strong passwords instead of using temporary ones
**How:** Admin creates user → System generates temp password → User logs in → Forced password change → User accesses system

---

## 🔧 Key Files

| File | Purpose | Lines |
|------|---------|-------|
| `app/PasswordManager.php` | Password operations | 140 |
| `force_password_change.php` | Password change UI | 240+ |
| `auth.php` | Login flow (updated) | - |
| `admin_roles.php` | User creation (updated) | - |
| `config.inc.php` | Database schema (updated) | - |

---

## 🚀 Usage Examples

### For Admin (Creating Users)

```php
// In admin_roles.php - happens automatically
$temporary_password = PasswordManager::generateTemporaryPassword();
// Output: "X3#Km@9pLq2" (12 characters)

$password_hash = PasswordManager::hashPassword($temporary_password);
// Output: hashed password ready for database

// Admin sees in success message:
// "User created! Temporary password: X3#Km@9pLq2"
```

### For Users (Changing Password)

```html
<!-- On force_password_change.php -->
<form method="POST">
    <input type="password" name="new_password" placeholder="Enter new password">
    <input type="password" name="confirm_password" placeholder="Confirm password">
    <button type="submit">Change Password</button>
</form>
```

### For Developers (Using PasswordManager)

```php
require_once 'app/PasswordManager.php';

// Generate temporary password
$temp_pwd = PasswordManager::generateTemporaryPassword();

// Hash a password
$hash = PasswordManager::hashPassword($plain_password);

// Verify a password
$is_valid = PasswordManager::verifyPassword($plain_password, $hash);

// Validate password meets requirements
$validation = PasswordManager::validatePassword($new_password);
if ($validation['valid']) {
    // All requirements met
} else {
    // Show errors: $validation['errors']
}

// Check if hash needs upgrading
if (PasswordManager::needsRehash($hash)) {
    // Rehash with new cost factor
}

// Format for display
$display = PasswordManager::formatPasswordForDisplay($password);
// Output: "Pass - word - 123 - AB"
```

---

## 📊 Database Schema

### New Columns in `users` Table

```sql
-- For SQLite
must_change_password INTEGER NOT NULL DEFAULT 0
temporary_password VARCHAR(255)
password_generated_at TEXT

-- For MySQL
must_change_password BOOLEAN NOT NULL DEFAULT FALSE
temporary_password VARCHAR(255) NULL
password_generated_at DATETIME NULL

-- Add index for performance
CREATE INDEX idx_must_change_password ON users(must_change_password);
```

### Manual Migration (if needed)

```sql
-- MySQL
ALTER TABLE users ADD COLUMN must_change_password BOOLEAN NOT NULL DEFAULT FALSE;
ALTER TABLE users ADD COLUMN temporary_password VARCHAR(255) NULL;
ALTER TABLE users ADD COLUMN password_generated_at DATETIME NULL;
ALTER TABLE users ADD INDEX idx_must_change_password (must_change_password);

-- SQLite (already auto-migrated, but manual if needed)
ALTER TABLE users ADD COLUMN must_change_password INTEGER NOT NULL DEFAULT 0;
ALTER TABLE users ADD COLUMN temporary_password VARCHAR(255);
ALTER TABLE users ADD COLUMN password_generated_at TEXT;
```

---

## 🔒 Password Requirements

**All requirements must be met:**

1. ✅ Minimum 8 characters
2. ✅ At least one uppercase (A-Z)
3. ✅ At least one lowercase (a-z)
4. ✅ At least one digit (0-9)
5. ✅ Special character **OR** two uppercase letters

**Valid passwords:**
- `Pass@word123` ✅ (has special char)
- `PassWORD123` ✅ (two uppercase letters)
- `SecureP@ss99` ✅ (has special char)

**Invalid passwords:**
- `password123` ❌ (no uppercase)
- `PASSWORD123` ❌ (no lowercase)
- `PassWord` ❌ (no digit, no special char, only 1 uppercase)
- `Pass@` ❌ (too short)

---

## 🔄 User Flow Diagram

```
┌─────────────────┐
│ Admin Creates   │
│ New User        │
└────────┬────────┘
         │
         ▼
┌─────────────────────────────────────┐
│ System Generates Temporary Password │
│ (12 chars with mixed types)         │
└────────┬────────────────────────────┘
         │
         ▼
┌─────────────────────────────────────┐
│ Admin Sees Success Message With     │
│ Temporary Password                  │
└────────┬────────────────────────────┘
         │
         ▼ (Admin communicates password)
┌─────────────────────────────────────┐
│ User Logs In With                   │
│ Username + Temporary Password       │
└────────┬────────────────────────────┘
         │
         ▼
┌─────────────────────────────────────┐
│ auth.php Detects                    │
│ must_change_password = 1            │
└────────┬────────────────────────────┘
         │
         ▼
┌─────────────────────────────────────┐
│ User Redirected to                  │
│ force_password_change.php           │
└────────┬────────────────────────────┘
         │
         ▼
┌─────────────────────────────────────┐
│ User Enters New Password            │
│ Sees Real-Time Validation           │
└────────┬────────────────────────────┘
         │
         ▼
┌─────────────────────────────────────┐
│ User Clicks Change Password         │
│ Backend Validates + Hashes          │
└────────┬────────────────────────────┘
         │
         ▼
┌─────────────────────────────────────┐
│ System Updates Database             │
│ - Sets new password_hash            │
│ - Sets must_change_password = 0     │
│ - Updates password_changed_at       │
└────────┬────────────────────────────┘
         │
         ▼
┌─────────────────────────────────────┐
│ User Redirected to Dashboard        │
│ (index.php)                         │
└────────┬────────────────────────────┘
         │
         ▼
┌─────────────────────────────────────┐
│ User Can Now Access System          │
│ With New Password                   │
└─────────────────────────────────────┘
```

---

## 🛡️ Security Model

**Hashing:**
- Algorithm: bcrypt
- Cost factor: 12 (intentionally slow for security)
- Time per hash: 100-200ms (acceptable for first login)

**Session Management:**
- Session regenerated on successful login
- `must_change_password` flag stored in session
- Force password change page checks session validity

**Access Control:**
- Page requires valid `$_SESSION['user_id']`
- Missing session = redirect to login
- Invalid `must_change_password` = redirect to dashboard

**Data Validation:**
- Prepared statements prevent SQL injection
- htmlspecialchars() prevents XSS
- Input length validation
- Role validation against whitelist

---

## 🧪 Quick Test

```bash
# Check implementation is complete
bash verify_user_creation.sh

# Expected output:
# ✓ app/PasswordManager.php exists
# ✓ force_password_change.php exists
# ✓ auth.php redirects to force_password_change.php
# ✓ admin_roles.php uses PasswordManager
# ✓ config.inc.php has must_change_password column
# ✓ clean_security.sql has must_change_password column
# ✓ minimal_security.sql has must_change_password column
# ✓ force_password_change.php has password change logic
```

---

## 🐛 Debugging

### Enable Debug Output

```php
// In force_password_change.php, add after line 15:
error_log('DEBUG: User ID = ' . $user_id);
error_log('DEBUG: Database type = ' . $db_type);
error_log('DEBUG: Must change = ' . $_SESSION['must_change_password']);

// Check server error log for output
```

### Check Database State

```sql
-- Check user's password change status
SELECT user_id, username, must_change_password, password_changed_at 
FROM users WHERE username = 'testuser';

-- Check when temporary password was generated
SELECT user_id, username, password_generated_at 
FROM users WHERE username = 'testuser';
```

### Browser Console

```javascript
// Check for JavaScript errors
// Open browser DevTools (F12)
// Go to Console tab
// Reload page
// Look for any red error messages
```

---

## 📝 Integration Checklist

- [ ] `app/PasswordManager.php` exists and readable
- [ ] `force_password_change.php` exists and readable
- [ ] `auth.php` updated with must_change_password check
- [ ] `admin_roles.php` updated to use PasswordManager
- [ ] Database schema updated (migration or manual)
- [ ] `config.inc.php` has database schema updates
- [ ] All required columns present in database
- [ ] `get_current_timestamp_sql()` function works
- [ ] No PHP errors in error log
- [ ] Test user creation works
- [ ] Test first login redirects to password change
- [ ] Test password change works
- [ ] Test second login goes to dashboard

---

## 🔗 Related Files

**Documentation:**
- [USER_CREATION_WORKFLOW.md](USER_CREATION_WORKFLOW.md) - Complete workflow
- [TESTING_GUIDE.md](TESTING_GUIDE.md) - Detailed test scenarios
- [IMPLEMENTATION_COMPLETE.md](IMPLEMENTATION_COMPLETE.md) - Project summary

**Configuration:**
- [config.inc.php](config.inc.php) - Database connection and schema
- [clean_security.sql](clean_security.sql) - MySQL schema
- [minimal_security.sql](minimal_security.sql) - MySQL minimal schema

**Code:**
- [app/PasswordManager.php](app/PasswordManager.php) - Password class
- [force_password_change.php](force_password_change.php) - UI/logic
- [auth.php](auth.php) - Login integration
- [admin_roles.php](admin_roles.php) - User creation integration

---

## 💡 Common Questions

**Q: Why use temporary passwords?**
A: Admins don't know what password to use, and users choose stronger passwords when they set them.

**Q: Why bcrypt with cost=12?**
A: Makes password hashing slow (intentionally), making brute force attacks impractical.

**Q: Why can't users bypass password change?**
A: The `must_change_password` flag is checked in auth.php before any other redirects.

**Q: What if user loses their new password?**
A: Admin can reset via change_password.php (admin can change user's password) or user can request password reset if implemented.

**Q: Does this work with existing users?**
A: Yes! Existing users have `must_change_password = 0`, so they skip the forced change.

**Q: Can I customize password requirements?**
A: Yes! Edit `PasswordManager::validatePassword()` and update `force_password_change.php` requirements list.

---

## 📞 Support

For issues, see:
1. [TESTING_GUIDE.md](TESTING_GUIDE.md) - Troubleshooting section
2. Server error logs - Check for PHP errors
3. Browser console - Check for JavaScript errors
4. Database logs - Check for SQL errors

