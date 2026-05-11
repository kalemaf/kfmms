# 🔐 Role Management System

## Overview

The KFMMS Role Management System provides a comprehensive, professional interface for managing users, roles, permissions, companies, and licenses. This system is exclusively accessible to developers and provides full administrative control over the application.

## Features

### 👥 User Management
- Create new users with role assignments
- Delete existing users
- View user details with company associations
- Manage user status (active/inactive)

### 🏢 Company Management
- Create new companies
- Automatically generate license keys for companies
- Configure license types (Trial, Basic, Professional, Enterprise)
- Set purchased seat limits
- Reset company data (clears all work orders, equipment, inventory, etc.)

### 🔑 Role & Permission Management
- Create custom roles with specific permissions
- Assign permissions to roles
- Pre-configured roles: Admin, Maintenance Manager, Supervisor, Technician, Operator, Developer
- Granular permissions for different resources (users, companies, work orders, equipment, etc.)

### 📋 License System
- Automatic license key generation for new companies
- Seat-based licensing with usage tracking
- Multiple license types with different features
- License audit logging for compliance

## Access Requirements

- **Username**: `developer`
- **Password**: `DevPass12345!`
- **Role**: Developer (exclusive access)

## Developer Permissions

The developer role has exclusive access to:
- ✅ Create, read, update, delete users
- ✅ Create, read, update, delete companies
- ✅ Create, read, update, delete licenses
- ✅ Reset company data (clears all historical records)
- ✅ Full access to all system resources
- ✅ Role and permission management

## Database Schema

The system uses the following key tables:
- `users` - User accounts with role assignments
- `roles` - Role definitions
- `permissions` - Available permissions
- `role_permissions` - Role-permission mappings
- `companies` - Company information
- `company_licenses` - License management
- `license_audit_log` - Audit trail for license operations

## Usage Instructions

### Creating a New Company
1. Navigate to Role Management → Create New Company & Generate License
2. Fill in company details (name, email, contact info)
3. Select license type and number of seats
4. Submit to create company and generate license key
5. **Important**: Save the generated license key - it's required for user authentication

### Adding Users to a Company
1. Go to Create New User section
2. Enter username, email, and password
3. Select appropriate role
4. Choose the company from the dropdown
5. Submit to create the user account

### Resetting Company Data
1. Find the company in the Company & License Management table
2. Click "Reset Data" button
3. Confirm the action (this will delete all work orders, equipment, inventory, etc.)
4. System will clear all historical data for a fresh start

### Managing Roles and Permissions
1. Use "Create New Role" to define custom roles
2. Assign specific permissions to roles
3. Users can then be assigned to these roles

## Security Features

- **Exclusive Developer Access**: Only developer role can access role management
- **Prepared Statements**: All database operations use prepared statements
- **Password Hashing**: Secure password storage with bcrypt
- **Audit Logging**: All license operations are logged
- **Foreign Key Constraints**: Data integrity maintained
- **Input Validation**: All inputs are validated and sanitized

## License Key Format

License keys are automatically generated as 16-character uppercase alphanumeric strings (e.g., `A1B2C3D4E5F6G7H8`).

## Data Reset Functionality

The "Reset Company Data" feature clears the following tables for the selected company:
- work_orders
- equipment
- inventory_items
- purchase_orders
- goods_receipt_notes
- maintenance_schedules
- audit_logs

This provides a clean slate for new company onboarding.

## Navigation

Access the Role Management System through:
- Main Menu → Admin → Role Management
- Direct URL: `index.php?nav=admin_roles`

## Support

For technical support or questions about the Role Management System, contact the development team.