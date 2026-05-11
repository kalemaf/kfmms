# CMMS SaaS License Management System

## Overview
The license management system provides comprehensive company and license tracking for the CMMS platform.

## Fixed Issues
✅ **Blank Page on Activate** - Now uses dedicated API endpoint (`license_api.php`) with error handling
✅ **Database Schema** - Created verification and recovery scripts to ensure all tables exist
✅ **License Status** - Properly tracks system activation, licensing, and subscription status

## Files Created/Updated

### New Files
- **`license_api.php`** - RESTful API for license operations
  - `POST /license_api.php?action=activate&company_id=X` - Activate system
  - `POST /license_api.php?action=deactivate&company_id=X` - Deactivate system
  - `GET /license_api.php?action=get_status&company_id=X` - Get system status
  - Returns JSON with success status and detailed error messages

- **`verify_saas_db.php`** - Database verification and recovery
  - Checks all SaaS tables exist
  - Automatically creates missing tables
  - Adds required columns to existing tables
  - Safe to run multiple times

- **`setup_saas_db.php`** - Database setup utility
  - Creates all SaaS schema tables
  - Supports both MySQL and SQLite
  - Detailed output of setup progress

### Updated Files
- **`admin_roles.php`** - License management UI
  - Updated `activate_system` case with better error handling
  - Added `activateSystem()` JavaScript function
  - Form now submits via `license_api.php` instead of direct POST
  - Shows user-friendly error messages

## Database Tables

### Core Tables
- **`companies`** - Company information and metadata
  - `company_id` (PK), `company_name`, `contact_name`, `contact_email`, `industry`, `company_size`

- **`company_licenses`** - License keys and subscription info
  - `license_id` (PK), `company_id` (FK), `license_key`, `purchased_seats`, `used_seats`
  - `license_type` (trial/basic/professional/enterprise), `expires_at`, `is_active`

- **`system_control`** - Activation status per company
  - `control_id` (PK), `company_id` (FK, unique), `system_activated`, `system_locked`
  - `subscription_status`, `subscription_expires_at`, `max_users`, `feature_tier`

### Supporting Tables
- **`subscription_payments`** - Payment records and audit trail
- **`license_actions`** - License activation/deactivation audit log
- **`roles`**, **`permissions`** - User role and permission management
- **`system_updates`** - System version and update tracking

## Usage

### Activate a Company License
```bash
# Via Web
POST /admin_roles.php
  action=activate_system
  company_id=1

# Via API (Programmatic)
POST /license_api.php
  action=activate
  company_id=1
```

### Check License Status
```bash
GET /license_api.php?action=get_status&company_id=1
```

Response:
```json
{
  "success": true,
  "data": {
    "activated": true,
    "locked": false,
    "subscription": "trial",
    "license_active": true,
    "license_key": "57C2D84A2E87EAA6"
  }
}
```

## Setup Steps

### 1. Verify Database
Run the verification script to create missing tables:
```bash
php verify_saas_db.php
```

### 2. Test API
Test license activation via API:
```bash
curl -X POST http://localhost:8000/license_api.php \
  -d "action=activate&company_id=1"
```

### 3. Use Admin Interface
Access the company management UI:
- Open `http://localhost:8000/admin_roles.php`
- Login as admin/developer
- Go to "Companies" tab
- Click "Activate" button on desired company

## Troubleshooting

### Blank Page on Activate
**Status**: ✅ FIXED
- Now uses `license_api.php` API endpoint
- Returns JSON responses with error messages
- JavaScript shows alerts on success/failure
- Check browser console for detailed errors

### License Not Activating
Check the following:
1. Company exists: `SELECT * FROM companies WHERE company_id = X`
2. License exists: `SELECT * FROM company_licenses WHERE company_id = X`
3. system_control exists: `SELECT * FROM system_control WHERE company_id = X`
4. Check database permissions

### Database Table Not Found
Run the recovery script:
```bash
php verify_saas_db.php
```

It will:
- Identify missing tables
- Create them with proper schema
- Add required columns

## API Reference

### POST /license_api.php

#### Activate System
```
Parameters:
  action: "activate" (required)
  company_id: 1 (required)

Response:
  {
    "success": true/false,
    "message": "System activated successfully",
    "error": "Error message if failed"
  }
```

#### Deactivate System
```
Parameters:
  action: "deactivate" (required)
  company_id: 1 (required)
  lock_reason: "Reason for lock" (optional)

Response:
  {
    "success": true/false,
    "message": "System deactivated successfully",
    "error": "Error message if failed"
  }
```

#### Get Status
```
Parameters:
  action: "get_status" (required)
  company_id: 1 (required)

Response:
  {
    "success": true,
    "data": {
      "activated": boolean,
      "locked": boolean,
      "subscription": "trial|active|expired|suspended",
      "license_active": boolean,
      "license_key": "XXXX..."
    }
  }
```

## Error Handling

The API endpoint provides detailed error messages:
- `401 Unauthorized` - User not logged in
- `403 Forbidden` - User lacks admin privileges
- `400 Bad Request` - Invalid company ID or missing parameters
- `200 OK` - Success

All errors are returned as JSON with descriptive messages.

## Testing

### Quick Test
```php
<?php
$data = [
    'action' => 'get_status',
    'company_id' => 1
];

$ch = curl_init('http://localhost:8000/license_api.php');
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_COOKIE, 'PHPSESSID=...');  // Your session cookie
$response = curl_exec($ch);
echo $response;  // JSON response
?>
```

## Future Enhancements
- [ ] Email notifications on license expiration
- [ ] Automatic license renewal
- [ ] Usage analytics dashboard
- [ ] Bulk license operations
- [ ] License transfer between companies

---
**Last Updated**: April 22, 2026
**Status**: Production Ready ✅
