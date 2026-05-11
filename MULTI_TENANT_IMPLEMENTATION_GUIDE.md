# 🏗️ KFMMS Multi-Tenant SaaS Architecture Implementation Guide

## 📋 Overview

This guide walks you through implementing a professional multi-tenant SaaS system for KFMMS. The architecture ensures:

✅ **Complete Data Isolation** - Each company only sees its own data  
✅ **Automatic Tenant Filtering** - No manual SQL WHERE clauses needed  
✅ **Role-Based Access Control** - Admin, Manager, Technician roles  
✅ **Production-Ready** - Enterprise-grade security  
✅ **Scalable** - Unlimited companies can use the system  

---

## 🗂️ Project Structure

```
/kfmms
├── /app
│   ├── /Middleware
│   │   └── TenantMiddleware.php          ← Enforces tenant context
│   ├── /Models
│   │   ├── WorkOrder.php
│   │   ├── Equipment.php
│   │   ├── Inventory.php
│   │   └── [Your other models]
│   ├── /Controllers
│   │   ├── WorkOrderController.php
│   │   ├── EquipmentController.php
│   │   └── [Your other controllers]
│   ├── BaseModel.php                    ← All models extend this
│   ├── AuthenticationManager.php        ← Auth + tenant initialization
│   └── CompanyService.php               ← Company registration
│
├── /migrations
│   ├── multi_tenant_schema.php          ← Schema definitions
│   └── run_multi_tenant_migration.php   ← Migration runner
│
├── /storage
│   └── /uploads
│       ├── /tenant_1
│       ├── /tenant_2
│       └── [isolated per company]
│
├── config.inc.php                       ← Database config (update needed)
└── [Existing files...]
```

---

## 🚀 Step-by-Step Implementation

### Step 1: Update config.inc.php

Add the middleware inclusion at the top of your `config.inc.php`:

```php
<?php
// At the very top of config.inc.php
require_once __DIR__ . '/app/Middleware/TenantMiddleware.php';
require_once __DIR__ . '/app/AuthenticationManager.php';

// Your existing database connection code...
$db_type = getenv('DB_TYPE') ?? 'sqlite';

// Initialize database connection
// [Your existing connection code]
?>
```

### Step 2: Run Database Migration

Execute the migration to add tenant_id to all tables:

```bash
php migrations/run_multi_tenant_migration.php
```

**What happens:**
- ✅ Creates `companies` table
- ✅ Adds `tenant_id` column to all tables
- ✅ Creates indexes for performance
- ✅ Sets up foreign keys

**Expected Output:**
```
🚀 Starting Multi-Tenant Migration
Database: sqlite

✅ COMPLETED: create_companies_table
✅ COMPLETED: add_tenant_to_users
✅ COMPLETED: add_tenant_to_work_orders
✅ COMPLETED: add_tenant_to_equipment
✅ COMPLETED: add_tenant_to_spare_parts
✅ COMPLETED: add_tenant_to_inventory
✅ COMPLETED: add_tenant_to_purchase_orders
✅ COMPLETED: add_tenant_to_licenses
✅ COMPLETED: add_tenant_to_audit_logs

📊 MIGRATION REPORT
✅ Completed: 9
❌ Errors: 0

✨ Migration finished!
```

### Step 3: Update Authentication (auth.php)

Replace your login logic with:

```php
<?php
require_once 'config.inc.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = $_POST['email'] ?? '';
    $password = $_POST['password'] ?? '';
    
    // Use the new authentication manager
    $auth_manager = new AuthenticationManager($connection, $db_type);
    $result = $auth_manager->authenticate($email, $password);
    
    if ($result['success']) {
        // Tenant context is now initialized
        // User can access their data
        header('Location: dashboard.php');
    } else {
        $error = $result['message'];
    }
}
?>
```

### Step 4: Create Your First Model

All models must extend `BaseModel`:

```php
<?php
require_once __DIR__ . '/../BaseModel.php';

class WorkOrder extends BaseModel {
    protected $table = 'work_orders';
    
    public function getAllForTenant() {
        // Automatically filtered by tenant_id
        return $this->all('1=1', []);
    }
    
    public function getByStatus($status) {
        return $this->all('status = ?', [$status]);
    }
}
?>
```

**Key Feature:** All queries automatically include `WHERE tenant_id = ?`

### Step 5: Create Your First Controller

```php
<?php
require_once __DIR__ . '/../Middleware/TenantMiddleware.php';
require_once __DIR__ . '/../Models/WorkOrder.php';

class WorkOrderController {
    private $model;
    
    public function __construct($connection, $db_type) {
        $this->model = new WorkOrder($connection, $db_type);
    }
    
    public function index() {
        // User automatically gets only their company's data
        $work_orders = $this->model->getAllForTenant();
        
        return [
            'success' => true,
            'data' => $work_orders
        ];
    }
}
?>
```

### Step 6: Update Existing Pages

For any page that displays company data, add tenant verification:

```php
<?php
require_once 'app/Middleware/TenantMiddleware.php';

// This ensures user is logged in with valid tenant context
$tenant_id = tenant();  // Gets current company ID

// Now all your queries should use tenant_id
$query = "SELECT * FROM work_orders WHERE tenant_id = ?";
// Execute with $tenant_id
?>
```

---

## 🔒 Critical Security Rules

### ❌ NEVER DO THIS:

```php
// BAD - No tenant isolation!
SELECT * FROM work_orders;
SELECT * FROM equipment;
SELECT * FROM users;
```

### ✅ ALWAYS DO THIS:

```php
// GOOD - Data is isolated per tenant
$tenant_id = tenant();
SELECT * FROM work_orders WHERE tenant_id = ?;
SELECT * FROM equipment WHERE tenant_id = ?;
SELECT * FROM users WHERE tenant_id = ?;
```

### When Using Models:

```php
// Models handle tenant filtering automatically
$model = new WorkOrder($connection, $db_type);
$work_orders = $model->all();  // Already filtered!
```

---

## 👥 User Registration for New Company

### Step 1: Register Company

```php
<?php
require_once 'config.inc.php';

$service = new CompanyService($connection, $db_type);

$result = $service->register([
    'name' => 'ACME Corporation',
    'email' => 'acme@company.com',
    'phone' => '+1-555-0000',
    'address' => '123 Main St',
    'city' => 'New York',
    'state' => 'NY',
    'country' => 'USA',
    'postal_code' => '10001'
]);

if ($result['success']) {
    $company_id = $result['company_id'];
    echo "Company registered! ID: " . $company_id;
} else {
    echo "Error: " . $result['message'];
}
?>
```

### Step 2: Create First Admin User

```php
<?php
require_once 'config.inc.php';

$auth = new AuthenticationManager($connection, $db_type);

$result = $auth->registerUser([
    'email' => 'admin@acme.com',
    'password' => 'SecurePassword123!',
    'full_name' => 'John Admin',
    'role' => 'admin',
    'tenant_id' => 1  // The company ID from step 1
]);

if ($result['success']) {
    echo "Admin user created!";
} else {
    echo "Error: " . $result['message'];
}
?>
```

### Step 3: Login

```php
<?php
require_once 'config.inc.php';

$auth = new AuthenticationManager($connection, $db_type);

$result = $auth->authenticate('admin@acme.com', 'SecurePassword123!');

if ($result['success']) {
    // Session now contains:
    // $_SESSION['user_id'] = 1
    // $_SESSION['tenant_id'] = 1  ← User sees only company 1's data
    // $_SESSION['role'] = 'admin'
    
    header('Location: dashboard.php');
}
?>
```

---

## 🗂️ File Storage Isolation

Files must be stored in tenant-specific directories:

```php
<?php
// Get tenant-specific upload directory
$tenant_id = tenant();
$upload_dir = "storage/uploads/tenant_" . $tenant_id . "/";

// When user uploads a file
if (isset($_FILES['equipment_image'])) {
    $filename = $_FILES['equipment_image']['name'];
    $filepath = $upload_dir . time() . "_" . $filename;
    
    move_uploaded_file(
        $_FILES['equipment_image']['tmp_name'],
        $filepath
    );
}

// When retrieving files
$query = "SELECT file_path FROM equipment_images WHERE equipment_id = ? AND tenant_id = ?";
// Only return files that belong to the user's tenant
?>
```

---

## 🛡️ Role-Based Access Control

### Check User Role

```php
<?php
// Get current role
$role = userRole();

// Check if admin
if (isAdmin()) {
    // Admin operations
}

// Check if manager or admin
if (TenantMiddleware::isManager()) {
    // Manager operations
}

// Prevent unauthorized actions
if (!isAdmin()) {
    http_response_code(403);
    echo json_encode(['error' => 'Forbidden']);
    exit;
}
?>
```

### Available Roles

- `admin` - Full company access, user management
- `manager` - Create/edit work orders, manage equipment
- `technician` - View assigned work orders, submit updates
- `user` - Read-only access to relevant data

---

## 📊 API Endpoints (JSON)

All endpoints automatically enforce tenant isolation:

```bash
# Get all work orders for current tenant
GET /api/work_orders.php
Response: {"success": true, "data": [...], "count": 5}

# Get specific work order (with tenant verification)
GET /api/work_orders.php?id=123
Response: {"success": true, "data": {...}}

# Create work order
POST /api/work_orders.php
Body: {"title": "Repair", "equipment_id": 5}
Response: {"success": true, "work_order_id": 456}

# Update work order
PUT /api/work_orders.php?id=456
Body: {"status": "completed"}
Response: {"success": true, "message": "Updated"}

# Get work orders by status
GET /api/work_orders.php?status=open
Response: {"success": true, "data": [...]}
```

---

## 🔄 Database Relationships

```
companies (company_id)
    ↓ (tenant_id)
    ├─→ users
    ├─→ work_orders
    ├─→ equipment
    ├─→ spare_parts
    ├─→ inventory
    ├─→ purchase_orders
    ├─→ audit_logs
    └─→ company_licenses
```

**Every table has `tenant_id` that references `companies.company_id`**

---

## ✅ Pre-Launch Checklist

- [ ] Migration script ran successfully
- [ ] All models extend `BaseModel`
- [ ] All controllers use tenant middleware
- [ ] No queries missing `WHERE tenant_id = ?`
- [ ] File upload directories created per tenant
- [ ] Admin user created for test company
- [ ] Login tested and session initializes tenant context
- [ ] Verified user A cannot see User B's company data
- [ ] API endpoints return proper 401/403 errors
- [ ] Audit logs recording login attempts
- [ ] Role-based access control tested

---

## 🚨 Troubleshooting

### Issue: "Unauthorized: No valid tenant context"
**Cause:** User not logged in  
**Solution:** Call `tenant()` only after successful authentication

### Issue: "Access denied to this resource"
**Cause:** User trying to access another company's data  
**Solution:** All queries correctly filtered by tenant_id

### Issue: Data from other companies showing
**Cause:** Missing `tenant_id` in WHERE clause  
**Solution:** Use BaseModel instead of raw SQL, or add `AND tenant_id = ?`

### Issue: Migration failed
**Cause:** Table column already exists  
**Solution:** Check if migration already ran, or manually verify column exists

---

## 📚 Examples: Migration from Old Code

### Before (No Tenant Isolation)
```php
$query = "SELECT * FROM work_orders WHERE status = 'open'";
$result = mysqli_query($connection, $query);
while ($row = mysqli_fetch_assoc($result)) {
    echo $row['title'];
}
```

### After (With Tenant Isolation)
```php
$model = new WorkOrder($connection, $db_type);
$work_orders = $model->getByStatus('open');
foreach ($work_orders as $order) {
    echo $order['title'];
    // Only shows work orders for current tenant
}
```

---

## 🎓 Advanced: Custom Queries with Tenant Context

```php
<?php
class CustomReportModel extends BaseModel {
    protected $table = 'work_orders';
    
    public function getMonthlyReport() {
        $query = "
            SELECT 
                DATE_FORMAT(created_at, '%Y-%m') as month,
                COUNT(*) as total_orders,
                SUM(CASE WHEN status='completed' THEN 1 ELSE 0 END) as completed
            FROM {$this->table}
            WHERE tenant_id = ?
            GROUP BY DATE_FORMAT(created_at, '%Y-%m')
        ";
        
        return $this->execute($query, [$this->tenant_id]);
    }
}
?>
```

**Key Point:** `$this->tenant_id` is automatically set in BaseModel constructor!

---

## 🎉 You're Ready!

Your KFMMS is now:
- ✅ Multi-tenant capable
- ✅ Enterprise-grade secure
- ✅ Scalable for unlimited companies
- ✅ Production-ready

**Next Steps:**
1. Update remaining controllers to use this pattern
2. Migrate existing pages to use new models
3. Deploy to production
4. Register your first paying customers

---

**Questions?** Check that tenant context is initialized after login and all queries include `tenant_id`.
