# User Creation Workflow Documentation

## Overview
The user creation system has been enhanced to enforce password security by requiring new users to change their password on first login. This document describes the complete workflow and technical implementation.

## Workflow Steps

### 1. User Creation (Admin Interface)
When an admin creates a new user via the admin interface (`admin_roles.php`):

1. Admin navigates to the admin panel and selects "Create User"
2. Admin fills in user details: username, email, role, phone
3. System automatically generates a temporary password using `PasswordManager::generateTemporaryPassword()`
4. System hashes the temporary password using bcrypt
5. User is inserted into the database with:
   - `must_change_password` = 1 (boolean/integer flag)
   - `temporary_password` = encrypted UUID-based password
   - `password_generated_at` = current timestamp
6. Admin sees the temporary password displayed on the success screen
7. Admin communicates the temporary password to the new user

**Temporary Password Format:**
- UUID-based: `xxxxxxxx-xxxx-xxxx-xxxx-xxxxxxxxxxxx`
- Example: `550e8400-e29b-41d4-a716-446655440000`
- Contains characters: alphanumeric + hyphens
- No special characters to avoid issues with command-line shells or email clients

### 2. First Login
When a new user logs in for the first time:

1. User navigates to login page (`welcome.php`)
2. User enters username and the temporary password
3. `auth.php` authenticates the user:
   - Verifies username and password hash match
   - Updates `last_login_at` timestamp
   - Sets session variables including `must_change_password = 1`
4. **BEFORE** checking maintenance mode, auth.php checks for `must_change_password` flag
5. If flag is set, redirects user to `force_password_change.php`
6. If flag is not set, normal redirect to `index.php`

### 3. Force Password Change Page
The `force_password_change.php` page enforces password security:

**Features:**
- Beautiful, professional UI matching the login style
- User greeting: "Welcome! [username]"
- Informational banner explaining the requirement
- Real-time password requirement validation:
  - ✓ Minimum 8 characters
  - ✓ At least one uppercase letter (A-Z)
  - ✓ At least one lowercase letter (a-z)
  - ✓ At least one number (0-9)
  - ✓ Special character OR at least 2 uppercase letters

**User Actions:**
1. User enters new password
2. User confirms password
3. Frontend JavaScript validates password meets all requirements
4. User clicks "Change Password"
5. Server-side validation:
   - Verifies both passwords match
   - Re-validates password strength
   - If valid, hashes password and updates user record:
     - Sets new `password_hash`
     - Clears `must_change_password` flag to 0
     - Updates `password_changed_at` to current timestamp
   - If invalid, shows error message with specific reason
6. On success, displays green confirmation message
7. Redirects to `index.php` after 2 seconds
8. User is now fully authenticated and can access system normally

### 4. Error Handling

**Validation Errors:**
- If `must_change_password` is not set when accessing the page, user is redirected to `index.php`
- If passwords don't match: displays error
- If password doesn't meet requirements: displays which requirements are unmet
- If database update fails: displays database error message

**Security:**
- Page requires active session and `user_id` to be set
- Page redirects to welcome.php if not authenticated
- Session is maintained during the process
- Password is hashed before storage

## Database Schema

### New Columns in `users` Table

```sql
-- MySQL
`must_change_password` BOOLEAN NOT NULL DEFAULT FALSE COMMENT 'Flag to force password change on next login'
`temporary_password` VARCHAR(255) NULL COMMENT 'Temporary password for new users'
`password_generated_at` DATETIME NULL COMMENT 'When temporary password was generated'

-- SQLite
must_change_password INTEGER NOT NULL DEFAULT 0
temporary_password VARCHAR(255)
password_generated_at TEXT
```

### Migration
Both SQLite and MySQL databases are automatically migrated:
- SQLite: Migration runs on application startup if columns are missing
- MySQL: Existing databases need manual migration or will receive NULL for new columns

**Manual MySQL Migration:**
```sql
ALTER TABLE users ADD COLUMN must_change_password BOOLEAN NOT NULL DEFAULT FALSE;
ALTER TABLE users ADD COLUMN temporary_password VARCHAR(255) NULL;
ALTER TABLE users ADD COLUMN password_generated_at DATETIME NULL;
ALTER TABLE users ADD INDEX idx_must_change_password (must_change_password);
```

## Technical Components

### PasswordManager Class (`app/PasswordManager.php`)

**Methods:**

1. **generateTemporaryPassword()** - Static method
   - Generates UUID-based temporary password
   - Format: `xxxxxxxx-xxxx-xxxx-xxxx-xxxxxxxxxxxx`
   - Returns: string

2. **hashPassword($password)** - Static method
   - Uses bcrypt algorithm (PASSWORD_BCRYPT)
   - Cost factor: 10
   - Parameters: password string
   - Returns: hashed password string

3. **validatePassword($password)** - Static method
   - Validates password against requirements
   - Returns: array with 'valid' (bool) and 'errors' (array of strings)
   - Requirements:
     - Minimum 8 characters
     - At least one uppercase letter
     - At least one lowercase letter
     - At least one digit
     - At least one special character OR multiple uppercase letters

4. **getPasswordRequirements()** - Static method
   - Returns array of requirement descriptions
   - Used for frontend validation display

### Files Modified

1. **admin_roles.php**
   - Added require for `app/PasswordManager.php`
   - Modified user creation to generate temporary password
   - Updated success message to show temporary password

2. **auth.php**
   - Added check for `must_change_password` column
   - Redirects to `force_password_change.php` before maintenance check

3. **Database Schema Files**
   - `clean_security.sql`: Added new columns
   - `minimal_security.sql`: Added new columns
   - `config.inc.php`: Added SQLite schema updates and migrations

### New Files

1. **force_password_change.php** (240+ lines)
   - Complete password change interface
   - Frontend validation
   - Server-side validation
   - Both SQLite and MySQL support

2. **app/PasswordManager.php** (90+ lines)
   - Password generation, hashing, validation
   - Utility methods for password management

## Testing Checklist

### Pre-requisites
- Database has the new columns (run migrations if needed)
- `app/` directory exists (or create it)
- Files are in correct locations

### Test 1: User Creation
- [ ] Admin can navigate to user creation page
- [ ] Admin enters user details
- [ ] Temporary password is displayed
- [ ] Success message shows temporary password

### Test 2: First Login
- [ ] User logs in with username and temporary password
- [ ] Auth succeeds and redirects to `force_password_change.php`
- [ ] User sees welcome message with their username

### Test 3: Password Requirements Validation
- [ ] Frontend shows all 5 requirements
- [ ] Real-time validation shows which requirements are met
- [ ] ✓ icon appears for met requirements
- [ ] ○ icon appears for unmet requirements

### Test 4: Password Change Submission
- [ ] Submitting non-matching passwords shows error
- [ ] Submitting weak password shows specific errors
- [ ] Submitting matching, strong password succeeds
- [ ] Success message appears
- [ ] User is redirected to index.php

### Test 5: Session State
- [ ] After password change, `must_change_password` is 0
- [ ] User can access dashboard normally
- [ ] User is not prompted for password change again

### Test 6: Direct Access
- [ ] Accessing `force_password_change.php` without session redirects to welcome
- [ ] Accessing page after password changed redirects to index.php
- [ ] All security checks work correctly

### Test 7: Database Compatibility
- [ ] Works with SQLite
- [ ] Works with MySQL
- [ ] Both database types handle password changes

## Configuration Notes

### Environment Variables
No special environment variables required. The system uses existing configuration from `config.inc.php`.

### Permissions
- Requires admin role to create users
- Users can change their own password
- Password change is enforced on first login

### Security Considerations

1. **Password Storage:**
   - Temporary passwords are hashed before storage
   - Original passwords are never stored

2. **Session Security:**
   - Session is regenerated on login
   - `must_change_password` flag is stored in session
   - Page requires valid session to function

3. **Password Validation:**
   - Server-side validation is mandatory
   - Frontend validation provides UX feedback
   - Both strong and weak passwords are properly distinguished

4. **Audit Trail:**
   - `password_generated_at`: When temporary password was created
   - `password_changed_at`: When user changed password
   - `last_login_at`: When user last logged in

## Troubleshooting

### Issue: "Database error: Unable to prepare statement"
- **Cause:** Database schema missing columns
- **Solution:** Run migrations or check database connection

### Issue: Temporary password not displayed
- **Cause:** JavaScript error or server error
- **Solution:** Check browser console, check server logs

### Issue: User redirects to index.php instead of force_password_change
- **Cause:** `must_change_password` flag is 0 or not set
- **Solution:** Verify database schema, check flag value in users table

### Issue: Password change shows validation error
- **Cause:** Password doesn't meet requirements
- **Solution:** Check requirement display, ensure password meets all 5 requirements

## Future Enhancements

1. Email notification with temporary password to user
2. Password expiration and renewal workflows
3. Audit logging for password changes
4. Admin ability to force password reset
5. Two-factor authentication integration
6. Password history to prevent reuse
7. Admin interface to view password change logs

## References

- **PasswordManager:** `app/PasswordManager.php`
- **Force Change Page:** `force_password_change.php`
- **Admin Panel:** `admin_roles.php`
- **Login Handler:** `auth.php`
- **Database Schema:** `config.inc.php`, `clean_security.sql`, `minimal_security.sql`
