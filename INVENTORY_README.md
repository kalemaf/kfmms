# Professional Inventory Management Module - CMMS
## Installation & Setup Guide

---

## 🎯 Overview

This comprehensive inventory module adds professional inventory management capabilities to the CMMS system, including:

- **Parts Master** - Complete spare parts management with ABC analysis
- **Multi-Location Warehousing** - Track inventory across multiple warehouse locations
- **Vendor Management** - Supplier management with performance tracking
- **Purchase Workflow** - Complete PR → PO → GR process
- **Stock Control** - Real-time inventory tracking with reorder alerts
- **Analytics & Reports** - Comprehensive inventory insights and KPIs
- **Audit Trail** - Complete transaction logging for compliance

---

## 📋 System Requirements

- **PHP**: 7.4 or higher
- **MySQL**: 5.7 or higher
- **Bootstrap**: 5.3.0 (included via CDN)
- **Chart.js**: 4.4.0 (included via CDN)
- **Font Awesome**: 6.4.0 Icons (included via CDN)

---

## 🚀 Installation Steps

### Step 1: Access Setup Wizard

1. Log in to the CMMS system as a **Manager**
2. Navigate to: `inventory_setup.php`
3. Follow the 4-step setup wizard:
   - **Step 1**: Check Environment (verifies files are in place)
   - **Step 2**: Create Tables (executes SQL migration)
   - **Step 3**: Verify Setup (confirms all tables created)
   - **Step 4**: Complete (confirms ready to use)

### Step 2: Verify Installation

After setup, verify the following are accessible:
- **Inventory Menu** appears in the main navigation (managers only)
- All 9 inventory modules load without errors
- Database tables are created successfully

### Step 3: Initial Configuration

1. **Create Vendors** (`/inventory/vendor_management.php`)
   - Add your regular suppliers
   - Set payment terms and lead times

2. **Create Warehouses** (`/inventory/warehouse_management.php`)
   - Set up physical storage locations
   - Create zones, aisles, racks, bins

3. **Create Parts** (`/inventory/parts_master.php`)
   - Add spare parts and components
   - Set criticality levels and reorder points

4. **Start Using** the Purchasing workflow

---

## 📁 File Structure

### Core Files
```
├── inventory_setup.php                    ← Setup wizard (run this first!)
├── run_inventory_migration.php            ← Migration helper (optional)
├── migrations/
│   └── 002_inventory_module.sql           ← Database schema (15 tables)
├── libraries/
│   └── inventory_manager.php              ← Business logic library (25+ functions)
└── inventory/
    ├── nav.php                            ← Navigation sidebar
    ├── inventory_analytics.php            ← Dashboard & analytics
    ├── parts_master.php                   ← Parts CRUD
    ├── warehouse_management.php           ← Warehouse & locations
    ├── vendor_management.php              ← Vendor CRUD
    ├── purchase_requests.php              ← Purchase requests workflow
    ├── purchase_orders.php                ← Purchase orders workflow
    └── goods_receipt.php                  ← Goods receipt workflow
```

---

## 🗄️ Database Schema

### 15 Tables Created:

**Masters**
- `vendors` - Vendor/supplier information with ratings
- `warehouses` - Physical warehouse locations
- `warehouse_locations` - Storage zones (zone/aisle/rack/bin)
- `parts_master` - Spare parts with ABC classification

**Transactions**
- `purchase_requests` - PR records and approval workflow
- `purchase_request_items` - PR line items
- `purchase_orders` - PO records and approval chain
- `purchase_order_items` - PO line items
- `goods_receipts` - Receipt documentation
- `goods_receipt_items` - Received items with QC status

**Inventory**
- `stock_locales` - On-hand quantities at each location
- `wo_parts` - Work order parts integration
- `part_vendors` - Part-vendor pricing cross-reference

**Analytics**
- `inventory_transactions` - Complete audit trail (all movements)
- `vendor_performance` - Monthly vendor metrics
- `inventory_summary` - Analytics aggregation (auto-updated)

**Indexes**: 14 strategic indexes for performance
**Triggers**: 2 automatic triggers for calculations
**Sample Data**: Included for testing

---

## 🔄 Workflow Processes

### Process 1: Parts Replenishment

```
Low Stock Alert
    ↓
Create Purchase Request
    ↓
Approve PR
    ↓
Create Purchase Order (from PR)
    ↓
Approve PO
    ↓
Goods Receipt (from PO)
    ↓
Receive Items & Update Inventory
```

### Process 2: Work Order Integration

```
Create Work Order
    ↓
Add Parts Required (wo_parts)
    ↓
Reserve Stock
    ↓
Issue Parts When WO Starts
    ↓
Return Unused (if any)
    ↓
Mark WO Complete
```

---

## 📊 Key Features

### Analytics Dashboard
- Real-time stock status (Normal/Low/Critical/Overstock)
- Reorder recommendations with shortage calculations
- Inventory value report (top 20 parts)
- ABC classification analysis
- Stock distribution pie chart

### Parts Master
- 25 fields per part (including criticality, safety stock, lead times)
- ABC classification (auto-calculated or manual)
- Category filtering and search
- Reorder point management
- OEM/supplier cross-reference

### Warehouse Management
- Hierarchical storage (Warehouse → Zone → Aisle → Rack → Bin)
- Per-location inventory tracking
- Capacity monitoring
- Stock adjustment with reason tracking
- Real-time available quantity calculation

### Vendor Management
- 5-star performance ratings
- Payment terms tracking
- Lead time management
- Performance metrics (on-time %, quality)
- Order history and totals

### Purchase Orders
- Dynamic vendor selection with info panel
- Real-time item totals and tax calculation
- Approval workflow (Draft → Submitted → Approved)
- Received quantity tracking
- Integration with Goods Receipt

### Goods Receipt
- Quality control status tracking
- Condition flags (Good/Damaged/Defective)
- Automatic inventory updates
- PO reference linking
- Receipt date and user tracking

---

## 🔌 Integration Points

### With Work Orders
- Parts can be reserved for specific work orders
- Parts status tracked (Reserved/Issued/Used/Returned)
- Work order completion triggers inventory returns
- Stock availability visible when creating WOs

### With SLA System
- Parts delivery times tracked in SLA metrics
- Stock availability affects operational SLA
- Vendor performance tied to delivery SLAs

### With Reports
- Inventory value in financial reports
- Parts usage trends in maintenance analytics
- Vendor performance in supplier reports

---

## 💼 Business Logic Functions (inventory_manager.php)

### Parts Management (3 functions)
- `save_part()` - Create/update parts
- `get_part()` - Retrieve single part
- `get_parts()` - List with filters

### Warehouse & Stock (6 functions)
- `get_warehouses()` - List all warehouses
- `get_warehouse_locations()` - List zones/locations
- `get_total_stock()` - Aggregate quantities
- `update_stock()` - Adjust inventory with audit trail
- `reserve_stock()` - Reserve for work orders
- `issue_stock()` - Issue parts from inventory

### Vendor Management (3 functions)
- `save_vendor()` - Create/update vendors
- `get_vendors()` - List vendors
- `get_vendor_details()` - With performance history

### Purchase Requests (3 functions)
- `create_purchase_request()` - Create PR with items
- `get_purchase_request()` - Retrieve PR
- `approve_purchase_request()` - Approval workflow

### Purchase Orders (4 functions)
- `create_purchase_order()` - Create from PR or manual
- `get_purchase_order()` - Retrieve PO
- `approve_purchase_order()` - Approval tracking
- Auto-updates vendor totals and stock on order

### Goods Receipt (3 functions)
- `create_goods_receipt()` - Create GR from PO
- `add_receipt_item()` - Add received items
- `complete_goods_receipt()` - Finalize and update inventory

### Analytics (4 functions)
- `get_stock_status_summary()` - Count by status
- `get_reorder_parts()` - Parts below reorder point
- `get_inventory_value_report()` - Value by part
- `update_abc_classifications()` - Auto-calculate ABC

---

## 🔐 Security Features

- All user inputs sanitized (via `sanitizeInput()`)
- SQL prepared statements for data integrity
- Role-based access (managers only)
- Complete audit trail of all changes
- User tracking on all transactions
- Transaction support for critical operations

---

## 📈 Analytics & Metrics

### Stock Status Metrics
- Critical Stock: Below 20% of reorder point
- Low Stock: Below reorder point
- Normal Stock: Reorder to max level
- Overstock: Above maximum level

### Vendor Performance
- On-time delivery %
- Quality rating (1-5 stars)
- Total orders and spending
- Monthly performance tracking

### Inventory Health
- Total value by classification
- Inventory turn rate (implied)
- Part usage trends
- Warehouse utilization

---

## 🛠️ Troubleshooting

### Tables Not Created
- Ensure database user has CREATE TABLE privileges
- Check MySQL error logs
- Verify connection is to correct database
- Run setup wizard again

### Navigation Not Showing
- Ensure logged in as Manager
- Clear browser cache
- Verify `title.php` was updated correctly

### Stock Not Updating
- Verify `inventory_transactions` table created
- Check trigger execution logs
- Confirm `complete_goods_receipt()` was called

### Performance Issues
- Verify all 14 indexes created
- Check MySQL slow query log
- Review `inventory_summary` population (auto-updated)

---

## 📞 Support

For issues:
1. Check the error messages in the module pages
2. Review the troubleshooting section above
3. Verify all database tables exist
4. Check user privileges and roles
5. Review database transaction logs

---

## 📄 License & Credits

Part of the Maintenix (Computerized Maintenance Management System) project.
Professional inventory module with enterprise-grade features.

---

## Version History

- **v1.0** - Initial release with 15 tables, 25+ functions, 9 UI modules
  - Complete parts master and warehouse management
  - Full purchasing workflow (PR → PO → GR)
  - Vendor management with performance tracking
  - Comprehensive analytics and reorder system
  - Complete audit trail and transaction logging

---

**Setup Time**: 5-10 minutes
**Learning Curve**: Low (intuitive interface)
**Maintenance**: Minimal (self-healing with triggers)
