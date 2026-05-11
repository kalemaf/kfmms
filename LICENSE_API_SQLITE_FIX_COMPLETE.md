# License API - JSON Error Fix & SQLite Migration

## Root Cause of "Unexpected end of JSON input" Error

The issue was **SQLite incompatibility with the `NOW()` function**:

- **MySQL**: Uses `NOW()` for current timestamp
- **SQLite**: Uses `CURRENT_TIMESTAMP` or `datetime('now')`

When license_api.php executed with SQLite, the `NOW()` function would fail silently or return incomplete results, causing the JSON response to be cut off or malformed, resulting in: **"SyntaxError: Unexpected end of JSON input"**

---

## Fixes Applied

### 1. license_api.php - Database Compatibility
**Changed:** All `NOW()` function calls to use `get_current_timestamp_sql()`

```php
// BEFORE (SQLite incompatible):
$update_query = "UPDATE system_control SET activation_date = NOW() WHERE company_id = ?";

// AFTER (Works for both MySQL & SQLite):
$timestamp = get_current_timestamp_sql();  // Returns 'NOW()' for MySQL, 'CURRENT_TIMESTAMP' for SQLite
$update_query = "UPDATE system_control SET activation_date = {$timestamp} WHERE company_id = ?";
```

**Functions Updated:**
- Line 83: System control UPDATE query
- Line 98: System control INSERT query  
- Line 241: License actions INSERT query

### 2. auth.php - Session-Level Company Lock Enforcement
- Added company_id to session variables
- Check for locked company before allowing login

### 3. index.php - Runtime Company Lock Detection
- Verify company unlock status on every page load
- Auto-logout if company becomes locked during session

---

## MySQL to SQLite Migration

### Quick Start

**Option 1: Automatic Migration** (Recommended)
```bash
cd c:\free-cmms 0.04
php migrate_mysql_to_sqlite_complete.php
```

**Option 2: Manual SQLite Setup**
```bash
# Create database directory
mkdir c:\free-cmms 0.04\database

# The system will auto-create database.db on first run
# Verify in admin_roles.php that schema is created
```

### How It Works

1. **Detects** current database type (MySQL or SQLite)
2. **Connects** to MySQL if configured
3. **Converts** MySQL schema to SQLite format
4. **Migrates** all data with proper type conversions:
   - `BIGINT` → `INTEGER`
   - `VARCHAR(n)` → `TEXT`
   - `DATETIME` → `TEXT`
   - `DECIMAL` → `REAL`
5. **Verifies** critical tables exist and have required columns
6. **Runs** schema updates if needed

---

## Database Type Configuration

Check your `.env` or direct `config.inc.php`:

```php
// Database connection settings
$db_type = env('DB_TYPE', 'sqlite');  // Set to 'mysql' or 'sqlite'

// MySQL settings (if using MySQL)
$hostName = env('DB_HOST', '127.0.0.1');
$userName = env('DB_USER', 'root');
$password = env('DB_PASS', 'password');
$databaseName = env('DB_NAME', 'maintenix');

// SQLite settings (if using SQLite)
$db_file = env('DB_FILE', __DIR__ . '/database/maintenix.db');
```

---

## Required Tables for Company Lock System

The system_control table must have these columns:

```sql
CREATE TABLE system_control (
    control_id INTEGER PRIMARY KEY AUTOINCREMENT,
    company_id INTEGER NOT NULL UNIQUE,
    system_activated INTEGER DEFAULT 0,
    system_locked INTEGER DEFAULT 0,           -- NEW: Lock flag
    lock_reason TEXT,                           -- NEW: Why locked
    activation_date TEXT DEFAULT CURRENT_TIMESTAMP,
    subscription_status VARCHAR(50) DEFAULT 'Trial'
);
```

**User Requirements:**
```sql
ALTER TABLE users ADD COLUMN company_id INTEGER;  -- Link user to company
```

---

## Testing the Fix

### Test 1: Lock then Activate
```
1. Go to admin_roles.php → Companies tab
2. Find a company (e.g., "hardware world uganda")
3. Click "Lock" button (🔒 becomes "🔒 Locked")
4. Click "Activate" button
5. Expected: ✅ "System activated successfully" (no JSON error)
6. Database shows: system_locked=0, system_activated=1
```

### Test 2: Login with Locked Company
```
1. Create/find a user assigned to a company
2. Lock the company
3. Try to login as that user
4. Expected: ❌ Error: "System is locked for your organization"
5. User CANNOT access any pages
```

### Test 3: Session Lock Detection
```
1. Login as normal user
2. Have another admin lock that user's company
3. User refreshes page or navigates
4. Expected: Auto-logout to login page
5. User CANNOT stay logged in after lock applied
```

### Test 4: Deactivate Then Activate (Repeated)
```
1. Deactivate company (Lock button appears)
2. Activate company (Activate button appears)
3. Repeat 5 times
4. Expected: All operations show success (no intermittent JSON errors)
5. Check browser F12 console: No "SyntaxError" or "JSON parse error"
```

---

## Files Modified

1. **license_api.php** (Fixed NOW() → get_current_timestamp_sql())
   - Date function compatibility for SQLite
   - All 3 query types updated

2. **auth.php** (Company lock check at login)
   - Query system_control.system_locked for user's company
   - Store company_id in session

3. **index.php** (Runtime company lock check)
   - Check on every page load if company still unlocked
   - Auto-logout if locked

4. **NEW: migrate_mysql_to_sqlite_complete.php** (Full migration tool)
   - Schema conversion from MySQL to SQLite
   - Data migration with type conversions
   - Automatic column updates

---

## Troubleshooting

### Still Getting JSON Error?

1. **Check PHP logs:**
   ```bash
   tail -f c:\free-cmms 0.04\logs\php_errors.log  # Linux
   Get-Content c:\free-cmms 0.04\logs\php_errors.log -Tail 20  # PowerShell
   ```

2. **Verify database connection:**
   ```bash
   php -r "require 'config.inc.php'; echo 'DB: ' . $db_type . '\n';"
   ```

3. **Test API directly with curl:**
   ```bash
   curl -X POST http://localhost:8000/license_api.php \
     -d "action=get_status&company_id=1"
   ```

### Company Users Still Can Login After Lock?

1. Verify `users` table has `company_id` column
2. Check that auth.php is checking system_control.system_locked
3. Run migration script to ensure schema updated

### Database Migration Failed?

1. Verify MySQL connection credentials
2. Check MySQL database exists and has tables
3. Ensure SQLite directory is writable: `chmod 755 database/`
4. Run migration script again with more details

---

## Performance Notes

- SQLite: Single-file database, lightweight, good for < 100 users
- MySQL: Better for production with 100+ concurrent users
- Both are now fully compatible with this codebase

---

## Deployment Checklist

- [ ] Run migration script: `php migrate_mysql_to_sqlite_complete.php`
- [ ] Verify database location readable/writable
- [ ] Test login flow (should detect locked companies)
- [ ] Test activate/deactivate (should show success)
- [ ] Check browser console (F12) for JSON errors
- [ ] Test with different user roles (user, admin, developer)
- [ ] Backup current database before running migration

---

## Summary

| Issue | Fix | Result |
|-------|-----|--------|
| `NOW()` not recognized by SQLite | Use `get_current_timestamp_sql()` | ✅ Proper date handling |
| Users from locked companies can login | Check `system_locked` in auth.php | ✅ Login blocked |
| Users stay logged in after company lock | Check at page load in index.php | ✅ Auto-logout |
| JSON parse error on activate | Combined all above fixes | ✅ Clean JSON response |

All three files now pass PHP syntax validation ✅

