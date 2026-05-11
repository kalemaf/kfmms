# 📋 Purchasing System - Quick Reference Card

## Quick Links
```
Purchase Requests:   purchase_request.php
Purchase Orders:     purchase_order.php
Goods Receipts:      goods_receipt.php
Vendor Management:   vendors.php
```

---

## Common Tasks Cheat Sheet

### ✨ Create a Purchase Request
1. Go to `purchase_request.php?action=create`
2. Fill: Department, Priority, Justification
3. Click "Add Item to PR" → Enter part details, qty, cost
4. Repeat steps 3 for each item
5. Click "Save PR"
6. **Status: Draft** ✏️

### ▶️ Submit PR for Approval
1. Open Draft PR
2. Click "Approve - Level 1 (Supervisor)"
3. **Status: Pending Approval** ⏳
4. Manager reviews → Approves Level 2
5. If >$1000: Admin approves Level 3
6. **Status: Approved** ✅

### ❌ Reject a PR
1. Open PR in Draft or Pending status
2. Enter rejection reason in text box
3. Click "Reject"
4. **Status: Rejected** ✗
5. Requester can revise and resubmit

### 📦 Convert PR to Purchase Order
1. Open Approved PR
2. Click "Create PO" button
3. Select Vendor from dropdown
4. Verify items populated correctly
5. Click "Save PO"
6. **Status: Draft** ✏️

### 🛒 Create Manual Purchase Order
1. Go to `purchase_order.php?action=create`
2. Select Vendor (required)
3. Set PO Date and Expected Delivery
4. Click "Save PO"
5. Add line items using form
6. Adjust costs (discount, tax, shipping)
7. Click "Save PO"
8. **Status: Draft** ✏️

### 📤 Send PO to Vendor
1. Open Draft PO
2. Verify all details correct
3. Click "Send to Vendor"
4. Print or email to vendor
5. **Status: Sent to Vendor** 📨

### 📥 Receive Goods & Create GRN
1. Goods arrive at warehouse
2. Go to `goods_receipt.php?action=create`
3. Select Purchase Order
4. System creates GRN automatically
5. For each line item:
   - Enter quantities (received/rejected)
   - Enter batch number
   - Enter storage location
   - Set inspection status
   - Click "Record Receipt"
6. Click "Close & Archive GRN"
7. **PO Status: Fully Received** ✅

### 🏢 Add New Vendor
1. Go to `vendors.php?action=create`
2. Enter Vendor Name (required)
3. Fill contact info (Name, Email, Phone)
4. Fill address info
5. Set Payment Terms (e.g., "Net 30")
6. Set Status to "Active"
7. Click "Save Vendor"
8. **Vendor Code: Auto-generated** (V0001)

### 🔍 Find a Document
1. Go to list view (e.g., `purchase_request.php?action=list`)
2. Use search box: Enter PR number, vendor name, etc.
3. Use filter dropdowns for status, type, etc.
4. Click "Search"
5. Results display matching documents

---

## Status Quick Reference

### PR Status Flow
```
Draft
  ↓ (Submit to approvers)
Pending Approval
  ↓ (Manager approves)
Approved ✅
  ↓ (Convert to PO)
(Becomes PO)

If Rejected:
Status: Rejected
Action: Revise and resubmit
```

### PO Status Flow
```
Draft → Sent to Vendor → Acknowledged → Partially Received → Fully Received → Closed
         ↓ (Cancel option)
      Cancelled
```

### GRN Status Flow
```
Draft → Partially Received → Fully Received ✅ → Archived
```

---

## Key Fields to Remember

### When Creating PR
- ✅ Department
- ✅ Priority
- ✅ Part Description
- ✅ Quantity & Unit Cost
- ✅ Justification

### When Creating PO
- ✅ Vendor (required)
- ✅ Part Number
- ✅ Expected Delivery Date
- ✅ Delivery Location
- ✅ Payment Terms

### When Receiving Goods
- ✅ Quantity Received
- ✅ Quantity Rejected
- ✅ Storage Location
- ✅ Inspection Status
- ✅ Batch/Serial Numbers (if applicable)

---

## Permission Quick Check

**Can I approve a PR?**
- Admin: YES (all levels)
- Manager: YES (Level 1 & 2)
- Lead: YES (Level 1 only)
- User: NO

**Can I create a PO?**
- Admin: YES
- Manager: YES
- Lead: NO
- User: NO

**Can I receive goods (create GRN)?**
- Admin: YES
- Manager: YES
- Lead: NO
- User: NO

**Can I manage vendors?**
- Admin: YES
- Manager: YES
- Lead: NO
- User: NO

---

## Cost Calculation Formulas

### Line Item Total
```
Line Total = Quantity × Unit Price - Item Discount
```

### Order Summary
```
Subtotal = SUM(all line totals)
Subtotal After Discount = Subtotal - Order Discount
Tax Amount = Subtotal After Discount × Tax %
Grand Total = Subtotal After Discount + Tax + Shipping
```

---

## Number Format Reference

### Auto-Generated Numbers
```
Purchase Request: PR-2026-00001
Purchase Order:   PO-2026-00001
GRN:              GRN-2026-00001
Vendor Code:      V0001
```

### Vendor Code Format
```
V0001 = First vendor (manual creation)
V0002 = Second vendor
etc.

Auto-generates if not specified
```

---

## Common Mistakes & Fixes

### ❌ "Cannot edit PR"
**Fix:** PR is no longer in Draft status. Only Draft PRs can be edited.

### ❌ "Cannot delete vendor"
**Fix:** Vendor is used in Purchase Orders. Set status to "Inactive" instead.

### ❌ "Cannot create PO without vendor"
**Fix:** Select a vendor from the dropdown (must have status: Active).

### ❌ "GRN items not showing"
**Fix:** Ensure PO has line items added before creating GRN.

### ❌ "Approval button missing"
**Fix:** Check user role - only Manager and Admin can approve. Verify user group in database.

---

## Database Quick Queries

### Count Pending Approvals
```sql
SELECT COUNT(*) FROM purchase_requests 
WHERE status = 'Pending Approval';
```

### List Overdue POs
```sql
SELECT po_number, vendor_name, expected_delivery_date
FROM purchase_orders po
LEFT JOIN vendors v ON po.vendor_id = v.id
WHERE expected_delivery_date < DATE(NOW())
AND po.status NOT IN ('Fully Received', 'Closed');
```

### Vendor Usage Count
```sql
SELECT v.vendor_name, COUNT(po.id) as num_orders
FROM vendors v
LEFT JOIN purchase_orders po ON v.id = po.vendor_id
GROUP BY v.id
ORDER BY num_orders DESC;
```

### Monthly Spend
```sql
SELECT 
    DATE_FORMAT(po_date, '%Y-%m') as month,
    SUM(grand_total) as total_spent
FROM purchase_orders
GROUP BY DATE_FORMAT(po_date, '%Y-%m')
ORDER BY month DESC;
```

---

## Keyboard Shortcuts

| Action | Keyboard |
|--------|----------|
| Save Form | Ctrl+S or Click Save |
| Close Dialog | Esc |
| Search | Ctrl+F (browser) |
| Go to PR List | Alt+1 (browser dependent) |
| Print List | Ctrl+P |

---

## File Locations
```
CMMS Root/
├── purchase_request.php       ← PR management
├── purchase_order.php         ← PO management
├── goods_receipt.php          ← GRN management
├── vendors.php                ← Vendor management
├── purchase_tables.sql        ← Database migration
├── PURCHASING_SYSTEM_README.md       ← Full documentation
├── PURCHASING_QUICK_SETUP.md         ← Setup guide
├── PURCHASING_WORKFLOWS_API.md       ← API reference
└── PURCHASING_SYSTEM_OVERVIEW.md     ← Overview (this index)
```

---

## Contact & Support

For detailed information, see:
- **Setup Issues:** PURCHASING_QUICK_SETUP.md
- **How Things Work:** PURCHASING_SYSTEM_README.md
- **Workflows & API:** PURCHASING_WORKFLOWS_API.md
- **System Overview:** PURCHASING_SYSTEM_OVERVIEW.md

---

## Quick Test Workflow (15 minutes)

### Step 1: Create Vendor (2 min)
1. vendor.php?action=create
2. Enter: "Test Supplier Inc" as name
3. Enter: "test@supplier.com" as email
4. Click "Save Vendor"

### Step 2: Create PR (3 min)
1. purchase_request.php?action=create
2. Enter: "Maintenance" as department
3. Enter: "High" as priority
4. Add Line Item: Part=BEARING, Qty=10, Cost=$5
5. Click "Save PR"

### Step 3: Approve PR (2 min)
1. Note PR number
2. Click "Approve - Level 1"
3. Status → Pending Approval

### Step 4: Create PO (3 min)
1. Click "Create PO"
2. Select "Test Supplier Inc" as vendor
3. Click "Save PO"
4. Click "Send to Vendor"

### Step 5: Receive Goods (3 min)
1. Go to goods_receipt.php
2. Click item receipt form
3. Enter: Qty Received=10, Location=BIN-A1
4. Set Inspection=Passed
5. Click "Record Receipt"
6. Click "Close & Archive"

✅ **Complete workflow tested!**

---

**Last Updated:** February 27, 2026  
**Version:** Quick Reference v1.0  
**Print Friendly:** ✅ Yes
