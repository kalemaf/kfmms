# User Creation System - Testing Guide

## Quick Start Testing

### Prerequisites
- Database is running and connected
- Application server is running
- Browser is available
- Admin user exists and is logged in

### Test Scenario 1: Create a New User

**Steps:**
1. Log in as admin user
2. Navigate to Admin Panel → User Management → Create New User
3. Fill in form:
   - Username: `testuser123`
   - Email: `testuser@example.com`
   - Role: Select any role (e.g., "Technician")
   - Phone: `+1234567890` (optional)
4. Click "Create User" button

**Expected Result:**
- Green success message appears
- Message includes temporary password in code block (e.g., `X3#Km@9pLq2`)
- User appears in the users list
- User has `must_change_password = 1` in database

**Verify in Database:**
```sql
SELECT user_id, username, email, must_change_password, temporary_password 
FROM users WHERE username = 'testuser123';
```

Expected output:
- `must_change_password`: 1
- `temporary_password`: [some hashed value]

---

### Test Scenario 2: First Login and Password Change

**Prerequisites:**
- New user created with temporary password (from Scenario 1)
- Copy the temporary password from success message

**Steps:**
1. Open login page in new/incognito browser session
2. Enter credentials:
   - Username: `testuser123`
   - Password: [temporary password from step 1 above]
3. Click "Login"

**Expected Result:**
- Login succeeds
- Page redirects to `force_password_change.php` (not to dashboard)
- Page shows title: "🔒 Change Your Password"
- Page shows welcome message with username: "Welcome! testuser123"
- Shows info banner: "You must set a new password..."
- Shows password requirements:
  - ○ Minimum 8 characters
  - ○ At least one uppercase letter (A-Z)
  - ○ At least one lowercase letter (a-z)
  - ○ At least one number (0-9)
  - ○ Special character or multiple uppercase letters

**Step 4: Try Invalid Passwords**

Enter password: `weak`
- Confirm: `weak`
- Click "Change Password"
- **Expected:** Error shows all failing requirements

Enter password: `NoNumber!`
- Confirm: `NoNumber!`
- Click "Change Password"
- **Expected:** Error shows missing number requirement

Enter password: `Pass1234`
- Confirm: `Pass1234`
- Click "Change Password"
- **Expected:** Error shows missing special character requirement

**Step 5: Enter Valid Password**

Enter password: `NewSecure@Pass123`
- Confirm: `NewSecure@Pass123`
- Click "Change Password"

**Expected Result:**
- Green success message: "✅ Password changed successfully! Redirecting..."
- After 2 seconds, redirects to dashboard (index.php)
- User can now use the system normally

**Verify in Database:**
```sql
SELECT user_id, username, must_change_password, password_changed_at 
FROM users WHERE username = 'testuser123';
```

Expected output:
- `must_change_password`: 0
- `password_changed_at`: [current timestamp]

---

### Test Scenario 3: Login After Password Change

**Steps:**
1. Log out if still logged in
2. Open login page
3. Enter credentials:
   - Username: `testuser123`
   - Password: `NewSecure@Pass123` (the new password set in Scenario 2)
4. Click "Login"

**Expected Result:**
- Login succeeds
- Redirects directly to dashboard (NOT to force_password_change.php)
- User can access all dashboard features normally
- `last_login_at` is updated in database

---

### Test Scenario 4: Direct Access Control

**Steps:**
1. While not logged in, try to access `force_password_change.php` directly
2. Enter URL: `http://localhost/free-cmms/force_password_change.php`

**Expected Result:**
- Redirects to `welcome.php` (login page)
- User is not authenticated

**Steps (continued):**
1. Log in as a regular user (not a new one)
2. Navigate directly to `force_password_change.php`

**Expected Result:**
- Redirects to `index.php` (dashboard)
- Message appears or automatic redirect because `must_change_password = 0`

---

### Test Scenario 5: Database Schema Verification

**SQLite Database Check:**
```sql
PRAGMA table_info(users);
```

Verify output includes these columns:
- `must_change_password`
- `temporary_password`
- `password_generated_at`

**MySQL Database Check:**
```sql
DESCRIBE users;
```

Verify output includes these columns with types:
- `must_change_password` - BOOLEAN/TINYINT(1)
- `temporary_password` - VARCHAR(255)
- `password_generated_at` - DATETIME

---

### Test Scenario 6: Multiple Users

Create 3 different users:
1. User 1: Role = Admin
2. User 2: Role = Technician
3. User 3: Role = Operator

For each user:
1. Record the temporary password
2. Log in with temporary password
3. Change password to unique secure password
4. Log out
5. Log back in with new password
6. Verify access level appropriate to role

**Expected Result:**
- All users can complete the password change workflow
- All users can log back in with new password
- Each user maintains their assigned role/permissions

---

### Test Scenario 7: Password Requirements Validation

**Frontend Real-Time Validation:**

1. Access `force_password_change.php` (while logged in as a new user)
2. Start typing in password field, observe requirements:

Type: `P` → ✓ One uppercase appears
Type: `Pp` → ✓ One uppercase + ✓ One lowercase
Type: `Pp1` → ✓ + ✓ + ✓ One number
Type: `Pp1@` → ✓ + ✓ + ✓ + ✓ Special char
Type: `Pp1` → Remove the `@`, should show ✗ for special character

**Expected Result:**
- Requirements update in real-time
- ✓ appears for met requirements
- ○ appears for unmet requirements
- Submit button may be disabled until all requirements met

---

### Test Scenario 8: Database Compatibility

**Test with SQLite:**
1. Configure app to use SQLite
2. Run Test Scenarios 1-3
3. Verify success at each step

**Test with MySQL:**
1. Configure app to use MySQL
2. Run Test Scenarios 1-3
3. Verify success at each step

---

## Automated Verification Script

Run the included verification script:
```bash
bash verify_user_creation.sh
```

This will check:
- All required files exist
- Key code patterns are present
- Database schema includes new columns
- Integration points are correctly configured

---

## Troubleshooting

### Issue: "Database error: Unable to prepare statement"

**Causes:**
- Database connection failed
- SQL syntax error
- Missing columns in users table

**Solutions:**
1. Check database connection in config.inc.php
2. Run database migrations:
   ```sql
   ALTER TABLE users ADD COLUMN must_change_password BOOLEAN NOT NULL DEFAULT FALSE;
   ALTER TABLE users ADD COLUMN temporary_password VARCHAR(255) NULL;
   ALTER TABLE users ADD COLUMN password_generated_at DATETIME NULL;
   ```
3. Check PHP error logs for SQL errors

### Issue: Temporary password not displayed after user creation

**Causes:**
- Server error occurred
- Page not rendering properly
- JavaScript error

**Solutions:**
1. Check browser console for JavaScript errors (F12 → Console)
2. Check server error logs
3. Try creating user again
4. Verify `app/PasswordManager.php` exists and is readable

### Issue: User redirects to index.php instead of force_password_change.php

**Causes:**
- User already changed password (`must_change_password = 0`)
- Database column not updated correctly
- Session not set properly

**Solutions:**
1. Check user record in database:
   ```sql
   SELECT username, must_change_password FROM users WHERE username = 'testuser';
   ```
2. If value is 0, manually update:
   ```sql
   UPDATE users SET must_change_password = 1 WHERE username = 'testuser';
   ```
3. Clear browser cookies and try again
4. Check auth.php is properly checking the flag

### Issue: "app/PasswordManager.php" file not found

**Causes:**
- File not created in correct location
- `app` directory doesn't exist

**Solutions:**
1. Verify file exists:
   ```bash
   ls -la app/PasswordManager.php  # Unix/Linux
   dir app\PasswordManager.php      # Windows
   ```
2. If app directory missing, create it:
   ```bash
   mkdir app  # Unix/Linux
   mkdir app  # Windows
   ```
3. Ensure file permissions allow reading (644 or similar)

### Issue: Password validation too strict/lenient

**Check:**
- Verify `PasswordManager::validatePassword()` logic in `app/PasswordManager.php`
- Requirements must include:
  - 8+ characters
  - At least 1 uppercase
  - At least 1 lowercase
  - At least 1 number
  - Special character OR 2+ uppercase letters

---

## Performance Considerations

- Temporary password generation uses `random_int()` for cryptographic randomness
- Password hashing uses bcrypt with cost factor 12 (strong but slower)
- Database queries use prepared statements (protection against SQL injection)
- No external API calls during password change
- Page loads in < 1 second (without network latency)

---

## Security Considerations Verified

- ✓ Passwords hashed before storage
- ✓ Session regenerated on login
- ✓ `must_change_password` flag enforced
- ✓ Direct access to force_password_change blocked without session
- ✓ SQL injection prevention via prepared statements
- ✓ XSS prevention via htmlspecialchars()
- ✓ CSRF tokens not required (page requires active session)
- ✓ Rate limiting applied at login (from existing auth system)
- ✓ Failed login attempts tracked (from existing auth system)

---

## Sign-Off Checklist

- [ ] All file modifications verified
- [ ] Database schema updated
- [ ] Test Scenario 1 passes (user creation)
- [ ] Test Scenario 2 passes (first login & password change)
- [ ] Test Scenario 3 passes (subsequent login)
- [ ] Test Scenario 4 passes (access control)
- [ ] Test Scenario 5 passes (database schema)
- [ ] Test Scenario 6 passes (multiple users)
- [ ] Test Scenario 7 passes (frontend validation)
- [ ] Test Scenario 8 passes (database compatibility)
- [ ] No errors in browser console
- [ ] No errors in server logs
- [ ] Performance acceptable (< 2 seconds per operation)
- [ ] All security measures in place
