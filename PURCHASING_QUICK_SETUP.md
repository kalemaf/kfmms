# Quick Setup Guide - Purchasing System

## 🚀 Quick Start (5 minutes)

### Step 1: Import Database Tables
```bash
# Via command line
mysql -u username -p database_name < purchase_tables.sql

# Via phpMyAdmin:
# 1. Login to phpMyAdmin
# 2. Select your database
# 3. Click "Import" tab
# 4. Choose purchase_tables.sql file
# 5. Click "Import" button
```

### Step 2: Verify File Uploads
Ensure these files exist in your CMMS root directory:
- ✅ `purchase_request.php`
- ✅ `purchase_order.php`
- ✅ `goods_receipt.php`
- ✅ `vendors.php`
- ✅ `purchase_tables.sql`
- ✅ `PURCHASING_SYSTEM_README.md`

### Step 3: Update Navigation
Menu option added automatically to `nav.php`
- New "Purchasing" menu section for managers/admins
- Links to all four modules

### Step 4: Test Access
1. Login to CMMS as Admin or Manager
2. Look for "Purchasing" in main menu
3. Click "Manage Vendors" to see vendor list
4. Try creating a new vendor

---

## 📋 Setup Checklist

### Database
- [ ] Database tables created from SQL script
- [ ] Sample vendors loaded
- [ ] Sample parts loaded
- [ ] User permissions configured

### Files
- [ ] All PHP files copied to root directory
- [ ] `flash.php` utility available (for error messages)
- [ ] `config.inc.php` database connection working

### User Roles
- [ ] At least one "manager" user created
- [ ] At least one "admin" user created
- [ ] Test user with "lead" role (optional)

### Navigation
- [ ] Menu displays "Purchasing" option
- [ ] All four module links clickable
- [ ] Links open without errors

---

## 🔑 Key Menu Links

### For Creating Documents
1. **Purchase Request:** `purchase_request.php?action=create`
2. **Purchase Order:** `purchase_order.php?action=create`
3. **Goods Receipt:** `goods_receipt.php?action=create` (or from PO)
4. **Vendor:** `vendors.php?action=create`

### For Listing/Management
1. **All PRs:** `purchase_request.php?action=list`
2. **All POs:** `purchase_order.php?action=list`
3. **All GRNs:** `goods_receipt.php?action=list`
4. **All Vendors:** `vendors.php?action=list`

### For Editing
1. **Edit PR:** `purchase_request.php?action=edit&id=1`
2. **Edit PO:** `purchase_order.php?action=edit&id=1`
3. **Edit GRN:** `goods_receipt.php?action=edit&id=1`
4. **Edit Vendor:** `vendors.php?action=edit&id=1`

---

## 🎯 Typical Workflow

### Scenario: Maintenance needs parts

1. **Supervisor creates PR (Step 1-3 min)**
   - Go to `purchase_request.php?action=create`
   - Fill department, priority, justification
   - Add line items (parts needed)
   - Save (Draft status)

2. **Manager approves PR (1-2 hours)**
   - Email notification (if configured)
   - Reviews PR total cost vs budget
   - Clicks "Approve - Level 1"
   - Status → Pending Approval

3. **Plant Manager approves (1-2 hours)**
   - Reviews for capacity/strategic fit
   - Clicks "Approve - Level 2"
   - If cost > $1000, escalates to Finance

4. **Finance approves final (optional, 1-2 hours)**
   - Admin review for high-value ($1000+)
   - Clicks "Approve - Level 3"
   - Status → Approved ✅

5. **Purchaser creates PO (30 min)**
   - Click "Create PO" from approved PR
   - Select vendor from dropdown
   - Items auto-populated from PR
   - Set payment terms, delivery date
   - Save (Draft status)

6. **Purchaser sends to vendor (5 min)**
   - Review line items and costs
   - Click "Send to Vendor"
   - Print or email to vendor
   - Status → Sent to Vendor

7. **Vendor delivers goods (7-30 days)**
   - Receive delivery at warehouse
   - Get delivery note
   - Get vendor invoice

8. **Receiving creates GRN (30 min)**
   - Go to `goods_receipt.php`
   - Click "Create from PO"
   - System auto-creates GRN with line items
   - For each item: enter quantities received/rejected
   - Enter batch numbers, storage location
   - Set inspection status (Passed/Failed)
   - Save each item
   - Click "Close & Archive"
   - Status → Fully Received ✅

✅ **Complete!** Parts are now received and inventory is updated.

---

## 🔧 Configuration Tips

### Change PR Number Format
Edit `purchase_request.php`, function `generate_pr_number()`:

**Current:** PR-2026-00001
**Alternative:** PR-0001
```php
// Find this line:
$prefix = "PR-" . date("Y");
// Change to:
$prefix = "PR-";
```

### Change Approval Threshold
Edit `purchase_request.php`, line ~150:
```php
// Current: Level 3 approval required at $1000
if ($pr['total_estimated_cost'] > 1000 && $group === 'admin')

// Change to:
if ($pr['total_estimated_cost'] > 5000 && $group === 'admin')  // $5000 threshold
```

### Customize Vendor Types
Edit vendor creation form to add new types:
```html
<select name="vendor_type">
    <option>Parts Supplier</option>
    <option>Service Provider</option>
    <option>Equipment Vendor</option>
    <option>Maintenance Supplier</option>
    <option>Your Custom Type Here</option>  <!-- ADD NEW -->
</select>
```

### Add New Unit of Measure
Edit `purchase_request.php`, `purchase_order.php`, `goods_receipt.php`:

Find:
```html
<select name="unit_of_measure">
    <option>EA</option>
    <option>BOX</option>
    ...
</select>
```

Add:
```html
<option>YOUR_UOM</option>
```

---

## 🧪 Test Data

The system includes sample data:

**Vendors:**
- V0001: ABC Hydraulics Inc (Parts Supplier)
- V0002: XYZ Bearings Ltd (Parts Supplier)
- V0003: Industrial Maintenance Services (Service Provider)
- V0004: Pump & Motor Supply (Equipment Vendor)

**Parts:**
- PART001: Hydraulic Pump Assembly
- PART002: Ball Bearing 6205
- PART003: Hydraulic Oil ISO 46
- PART004: Seal & Gasket Kit

### Create Test PR
1. Go to `purchase_request.php?action=create`
2. Fill basic info
3. Add PART001 (Hydraulic Pump)
4. Save
5. Approve (click "Approve - Level 1")
6. Navigate back and convert to PO

---

## 🔐 Permissions & Security

### User Level Access
```
Admin        → Full access to all modules
Manager      → Create/approve PRs, manage POs, create GRNs
Lead         → Create PRs, limited approvals
User         → View-only access
Unauthenticated → Redirected to login
```

### Session Security
- All modules check for active session
- User context captured in all actions
- Audit log tracks who did what and when

### Data Protection
- Foreign key constraints prevent orphaned records
- Status-based edit restrictions prevent unauthorized changes
- Vendor deletion prevented if used in POs

---

## 📞 Troubleshooting

### "Access Denied" when viewing vendor page
- **Check:** User group is "manager" or "admin"
- **SQL:** `SELECT group FROM users WHERE uname = 'username';`
- **Fix:** Update user in database to "manager" group

### PR/PO/GRN numbers not generating
- **Check:** Auto-increment on id columns
- **SQL:** `SHOW CREATE TABLE purchase_requests;`
- **Fix:** Run: `ALTER TABLE purchase_requests MODIFY id INT AUTO_INCREMENT;`

### "Cannot create PO" when PR is approved
- **Check:** Fields match what expected (vendor, items, etc.)
- **Debug:** Check browser console for JS errors
- **Fix:** Navigate directly: `purchase_order.php?action=create&pr_id=1`

### Line items not appearing in GRN
- **Check:** PO items were created before creating GRN
- **SQL:** `SELECT COUNT(*) FROM purchase_order_items WHERE po_id = 1;`
- **Fix:** Ensure PO has items, then create GRN fresh

### Vendor cannot be deleted
- **Reason:** This is intentional (data integrity)  
- **Solution:** Set vendor status to "Inactive" instead
- **Alternative:** Check for POs: `SELECT COUNT(*) FROM purchase_orders WHERE vendor_id = 1;`

---

## 📊 Next Steps

1. **Email Integration** (Optional)
   - Configure SMTP in `config.inc.php`
   - Add notification emails on PR approval
   - Send PO confirmation to vendors

2. **Barcode Integration** (Optional)
   - Add barcode scanning to GRN receipt
   - Automatically update storage location
   - Speed up physical inventory count

3. **Reporting Dashboard** (Optional)
   - Add metrics display to main menu
   - Show pending approvals count
   - Show overdue PO count
   - Show vendor performance ratings

4. **Mobile Interface** (Advanced)
   - Create mobile-friendly GRN receipt form
   - Quick vendor lookup
   - Receipt photo capture

---

## 📚 Related Documentation

- **Full System Guide:** `PURCHASING_SYSTEM_README.md`
- **Database Schema:** `purchase_tables.sql`
- **Main CMMS Docs:** `README.txt`

---

## ✅ Completion Checklist

- [ ] Database tables imported
- [ ] All PHP files present
- [ ] Menu updated with Purchasing link
- [ ] Test user can access vendor module
- [ ] Can create new vendor
- [ ] Can create PR in Draft status
- [ ] Can approve PR (requires manager role)
- [ ] Can convert PR to PO
- [ ] Can create GRN from PO
- [ ] Can record item receipt in GRN

**Once all checkboxes are complete, the purchasing system is ready for production use!**

---

**System Version:** Maintenix 0.04+
**Last Updated:** February 27, 2026
**Support:** See PURCHASING_SYSTEM_README.md for detailed documentation
