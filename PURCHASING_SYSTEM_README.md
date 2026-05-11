# Maintenix - Professional Purchase Management System

## System Overview

This documentation covers the comprehensive Purchase Management System integrated into Maintenix, which includes:

1. **Purchase Requests (PR)** - Internal requests to buy parts or services
2. **Purchase Orders (PO)** - Official documents sent to vendors
3. **Goods Receipt Notes (GRN)** - Confirmation of physically received goods
4. **Vendor Management** - Supplier database and relationships

---

## Installation & Setup

### 1. Database Tables Installation

Execute the SQL file to create all necessary tables:

```bash
mysql -u username -p database_name < purchase_tables.sql
```

This file now contains the current application schema for purchasing, including `vendors`, `parts_master`, `purchase_requests`, `purchase_orders`, and related order/receipt tables.

Or import through phpMyAdmin:
1. Go to phpMyAdmin
2. Select your database
3. Click "Import" tab
4. Upload `purchase_tables.sql`
5. Click "Import"

This creates the following tables:
- `vendors`
- `parts_catalog`
- `purchase_requests`
- `purchase_request_items`
- `purchase_orders`
- `purchase_order_items`
- `goods_receipt_notes`
- `goods_receipt_items`
- `purchase_audit_log`

Sample vendors and parts are included in the migration script.

### 2. Access Control

Update your user group settings in the `users` table:

- **Manager**: Full access to PR approvals, PO management, GRN processing
- **Admin**: Full access to all purchasing functions, vendor management
- **Lead**: Can create PRs, limited approval rights
- **User**: View-only access to purchase documents

---

## Module Descriptions

## 📋 PURCHASE REQUEST (PR) Module
**File:** `purchase_request.php`

### Purpose
Internal document to request parts or services before official ordering.

### Key Features

**PR Header Fields:**
- PR Number (Auto-generated: PR-YYYY-#####)
- Request Date (Auto-set)
- Requested By (User name)
- Department (Maintenance, Operations, etc.)
- Priority (Low/Medium/High/Emergency)
- Linked Work Order (optional)
- Budget Code / Cost Center
- Status (Draft/Pending Approval/Approved/Rejected)

**Line Items:**
- Part Number (Internal system number)
- OEM Part Number (Manufacturer part number)
- Description
- Quantity Requested
- Unit of Measure (EA, BOX, KIT, SET, PKG, METER, LITER, KG, LB)
- Estimated Cost (unit and total)
- Preferred Vendor
- Required Date
- Justification / Reason

**Approval Workflow:**

```
Draft → Level 1 Approval (Supervisor) → Pending Approval
        ↓ (Can Reject)
      Rejected (with reason)

Pending Approval → Level 2 Approval (Plant Manager)
                ↓ (Can Reject)

High Value (>$1000) → Level 3 Approval (Finance/Admin)
                   ↓ (Can Reject)

Final Status: Approved or Rejected
```

**Database Schema:**
```sql
purchase_requests
├── id (Primary Key)
├── pr_number (Unique, Auto-generated)
├── requested_by
├── department
├── priority
├── linked_work_order_id (FK)
├── budget_code
├── cost_center
├── status (enum)
├── rejection_reason
├── total_estimated_cost
├── approved_by_level1/2/3
├── approved_by_level1/2/3_date
└── created_at, updated_at

purchase_request_items
├── id
├── pr_id (FK)
├── line_number
├── part_number
├── internal_part_number
├── oem_part_number
├── description
├── quantity_requested
├── unit_of_measure
├── estimated_unit_cost
├── estimated_total_cost
├── preferred_vendor_id (FK)
├── required_date
├── justification
└── notes
```

### Usage Flow

1. **Create PR:**
   - Click "Create New PR"
   - Fill in header information
   - Add line items with part details
   - Set justification and notes
   - Save (Status: Draft)

2. **Add Items:**
   - Use "Add Line Item" form in PR edit view
   - Search for part number or create new entry
   - Set quantity, cost, and vendor preference
   - System automatically calculates totals

3. **Submit for Approval:**
   - Click "Approve - Level 1" (Supervisor/Lead)
   - Status changes to "Pending Approval"
   - Manager can review and approve (Level 2)
   - If >$1000, Admin must approve (Level 3)

4. **Rejection:**
   - At any approval stage, click "Reject"
   - Provide rejection reason
   - PR stays as "Draft" for requester to revise

5. **Create PO:**
   - Once "Approved", PR can be converted to PO
   - Click "Create PO" from approved PR
   - System pre-populates PO with PR line items

---

## 🎯 PURCHASE ORDER (PO) Module
**File:** `purchase_order.php`

### Purpose
Official binding document sent to vendor for ordering goods/services.

### Key Features

**PO Header Fields:**
- PO Number (Auto-generated: PO-YYYY-#####)
- Vendor (from vendor database)
- Linked PR ID (optional reference)
- PO Date
- Expected Delivery Date
- Delivery Location
- Payment Terms (Net 30, etc.)
- Currency (default USD)
- Status (Draft/Sent to Vendor/Acknowledged/Partially Received/Fully Received/Closed/Cancelled)

**Line Items:**
- Part Number
- Description
- Quantity Ordered
- Unit Price
- Discount ($ or %)
- Tax %
- Unit of Measure
- Total Line Cost

**Cost Calculations:**
```
Subtotal = SUM(Quantity × Unit Price - Per-Item Discount)
Subtotal After Discount = Subtotal - Order-Level Discount
Tax = Subtotal After Discount × Tax %
Total Amount = Subtotal After Discount + Tax + Shipping + Other Costs
```

**Database Schema:**
```sql
purchase_orders
├── id (Primary Key)
├── po_number (Unique, Auto-generated)
├── vendor_id (FK → vendors)
├── linked_pr_id (FK → purchase_requests, nullable)
├── po_date
├── expected_delivery_date
├── delivery_location
├── payment_terms
├── currency
├── status (enum)
├── subtotal
├── tax_amount
├── tax_percent
├── shipping_cost
├── po_total        – legacy field (subtotal + tax)
├── total_amount    – grand total including discounts, shipping, other costs
├── other_cost      – additional line for professional PO
├── created_by
├── sent_to_vendor_date
├── discount_amount
├── discount_percent
├── notes
└── created_at, updated_at
```
purchase_order_items
├── id
├── po_id (FK)
├── line_number
├── part_number
├── description
├── quantity_ordered
├── unit_price
├── discount_amount
├── discount_percent
├── tax_percent
├── unit_of_measure
├── total_line_cost
└── notes
```

### Status Workflow

```
Draft
  ↓ (Fill items, add costs)
  ↓
Sent to Vendor (email/print sent to vendor)
  ↓
Acknowledged (vendor confirms receipt)
  ↓
Partially Received (GRN created, partial goods arrive)
  ↓
Fully Received (all goods received, GRN finalized)
  ↓
Closed (invoices matched, payment processed)
  
Alternative: Cancelled (can be from Draft or Sent status)
```

### Usage Flow

1. **Create PO from PR:**
   - Go to approved PR detail
   - Click "Create PO"
   - OR manually create new PO without PR

2. **Fill PO Details:**
   - Select vendor from dropdown
   - Set PO date and delivery date
   - Set payment terms and currency
   - Set expected delivery location

3. **Add Line Items:**
   - Use "Add Line Item" form
   - Enter part number, description, quantity
   - Set unit price and any discounts
   - System calculates totals

4. **Adjust Costs:**
   - Use "Order Cost Adjustments" section
   - Set order-level discounts ($ or %)
   - Set tax percentage
   - Add shipping costs
   - System recalculates grand total

5. **Send to Vendor:**
   - Click "Send to Vendor" when Draft is complete
   - Status changes to "Sent to Vendor"
   - Print or email PO to vendor
   - Record sent date

6. **Track Receipt:**
   - Wait for goods delivery
   - Create GRN when goods arrive
   - GRN updates PO status to "Partially Received" or "Fully Received"

---

## 📦 GOODS RECEIPT NOTE (GRN) Module
**File:** `goods_receipt.php`

### Purpose
Document physical receipt of ordered goods and perform quality inspection.

### Key Features

**GRN Header Fields:**
- GRN Number (Auto-generated: GRN-YYYY-#####)
- Linked PO (auto-populated from PO)
- GRN Date
- Received By (User name)
- Delivery Note Number (from courier/delivery)
- Vendor Invoice Number
- Vendor Invoice Date
- Status (Draft/Partially Received/Fully Received/Rejected/Archived)

**Line Items:**
- Part Number (from PO)
- Description (from PO)
- Quantity Ordered (from PO)
- Quantity Received (manual entry)
- Quantity Rejected (manual entry for damaged goods)
- Batch Number (for lot tracking)
- Serial Numbers (for high-value items)
- Storage Location (warehouse bin/location)
- Inspection Status (Pending Inspection/Passed/Failed/On Hold)
- Rejection Reason (if failed inspection)

**Automatic Updates:**
- GRN Status updated based on line items:
  - Draft → All items have 0 quantities
  - Partially Received → Some items have quantities < ordered
  - Fully Received → All quantities received match or exceed ordered
  
- PO Status updated on receipt:
  - Partially Received ← GRN shows partial receipt
  - Fully Received ← GRN shows all quantities received

**Database Schema:**
```sql
goods_receipt_notes
├── id (Primary Key)
├── grn_number (Unique, Auto-generated)
├── po_id (FK → purchase_orders)
├── grn_date
├── received_by
├── delivery_note_number
├── vendor_invoice_number
├── vendor_invoice_date
├── vendor_id (FK → vendors)
├── status (enum)
├── total_quantity_received
├── total_quantity_rejected
├── notes
└── created_at, updated_at

goods_receipt_items
├── id
├── grn_id (FK)
├── po_item_id (FK → purchase_order_items)
├── line_number
├── part_number
├── description
├── quantity_ordered
├── quantity_received
├── quantity_rejected
├── batch_number
├── serial_numbers
├── storage_location
├── inspection_status (enum)
├── rejection_reason
├── unit_of_measure
└── received_notes
```

### Usage Flow

1. **Create GRN from PO:**
   - Open Purchase Order
   - Once status is "Sent to Vendor" or "Acknowledged"
   - Click "GRN" link or go to goods_receipt.php?action=create&po_id=[ID]
   - System auto-creates GRN and copies all line items from PO

2. **Record Item Receipt:**
   - For each line item, scroll to "Record Receipt" form
   - Enter:
     - Quantity Received
     - Quantity Rejected (if any damaged)
     - Batch Number (if tracking lots)
     - Serial Numbers (if valuable items)
     - Storage Location (where item was stored)
   - Set Inspection Status:
     - **Pending Inspection** - Not yet checked by QA
     - **Passed** - Passed quality inspection
     - **Failed** - Failed inspection, record rejection reason
     - **On Hold** - Awaiting further decision
   - Add receipt notes

3. **Complete Receipt:**
   - When all items are received and inspected
   - Click "Close & Archive GRN"
   - GRN Status becomes "Archived"
   - PO Status becomes "Closed"

4. **Partial Receipt:**
   - If some items arrive later
   - Create additional GRN from same PO
   - Or continue receiving items in first GRN

---

## 🏢 VENDOR MANAGEMENT Module
**File:** `vendors.php`

### Purpose
Central database of suppliers and service providers.

### Key Features

**Vendor Information:**
- **Identification:**
  - Vendor Code (auto-generated or manual: V0001)
  - Vendor Name
  - Vendor Type (Parts Supplier/Service Provider/Equipment Vendor/Maintenance Supplier)
  - Status (Active/Inactive/Suspended)
  - Quality Rating (Excellent/Good/Average/Poor)

- **Contact Information:**
  - Contact Person
  - Email
  - Phone
  - Fax
  - Website

- **Address:**
  - Street Address
  - City
  - State/Province
  - Postal Code
  - Country

- **Business Terms:**
  - Payment Terms (Net 30, COD, etc.)
  - Currency (USD, EUR, etc.)
  - Lead Time (delivery days)
  - Minimum Order Value
  - VAT/GST ID
  - Tax ID

- **Notes:** Additional comments/blacklist warnings

**Database Schema:**
```sql
vendors
├── id (Primary Key)
├── vendor_code (Unique)
├── vendor_name
├── vendor_type (enum)
├── contact_person
├── email
├── phone
├── fax
├── website
├── address
├── city
├── state_province
├── postal_code
├── country
├── vat_gst_id
├── tax_id
├── payment_terms
├── currency
├── lead_time_days
├── minimum_order_value
├── quality_rating (enum)
├── status (enum)
├── notes
└── created_at, updated_at

parts_catalog
├── id
├── part_number (Unique)
├── oem_part_number
├── part_name
├── description
├── category
├── equipment_type
├── unit_of_measure
├── reorder_quantity
├── reorder_level
├── lead_time_days
├── standard_unit_cost
├── last_unit_cost
├── supplier_id (FK → vendors)
├── alternate_suppliers
├── storage_location
├── current_stock
├── status (enum)
├── notes
└── created_at, updated_at
```

### Usage Flow

1. **Create Vendor:**
   - Click "Create New Vendor"
   - Fill in all vendor information
   - System auto-generates vendor code if not provided
   - Set status to "Active"
   - Save

2. **Manage Vendor:**
   - Search vendors by code, name, contact, email
   - Filter by type or status
   - Edit vendor details
   - Update quality ratings based on performance

3. **Deactivate Vendor:**
   - Set status to "Inactive" or "Suspended"
   - Cannot delete vendors with existing purchase orders
   - Mark as "Suspended" to block future orders while keeping history

4. **Use in PO:**
   - When creating PO, select from "Active" vendors
   - Vendor payment terms pre-populate in PO
   - Vendor contact info available for reference

---

## 🔍 AUDIT LOG & REPORTING

**Audit Trail Table:** `purchase_audit_log`

Tracks all changes to purchase documents:
```sql
purchase_audit_log
├── id
├── document_type (enum: PR, PO, GRN)
├── document_id
├── document_number
├── action (Created, Updated, Approved, Rejected, Received)
├── action_by (Username)
├── action_date
├── old_value (previous value)
├── new_value (new value)
└── notes
```

### Reports Available:
1. **PR Approval Metrics** - Tracking approval bottlenecks
2. **Vendor Performance** - Quality ratings, lead times, costs
3. **Budget vs Actuals** - Spending against budget codes
4. **Overdue POs** - Orders not yet received past expected date
5. **GRN Status** - Items pending inspection, failed inspections

---

## 📊 Integration Points

### Links to Work Orders
- **PR Creation:** Link to Work Order for maintenance that necessitated the purchase
- **Cost Tracking:** PO/GRN costs can be matched to WO labor/material costs

### Links to Equipment
- **Parts Inventory:** Associate parts with specific equipment types
- **Replacement Tracking:** Monitor which parts are frequently replaced

### User Roles & Permissions
- **Supervisor/Lead:** Create and approve Level 1 PRs
- **Manager:** Approve Level 2 PRs and manage POs
- **Admin/Finance:** Final approval for high-value PRs, vendor management
- **User:** View-only access to purchase status

---

## ⚙️ Configuration & Customization

### Modify Approval Thresholds:
Edit `purchase_request.php`:
```php
// Change high-value threshold for 3-level approval (default: $1000)
if ($pr['total_estimated_cost'] > 1000 && $group === 'admin')
```

### Add Custom Approval Levels:
Extend `purchase_requests` table with additional fields:
```sql
ALTER TABLE purchase_requests ADD COLUMN approved_by_level4 VARCHAR(100);
ALTER TABLE purchase_requests ADD COLUMN approved_by_level4_date DATETIME;
```

### Customize Vendor Types:
Modify enum in `vendors` table:
```sql
ALTER TABLE vendors MODIFY COLUMN vendor_type 
  ENUM('Parts Supplier', 'Service Provider', 'Equipment Vendor', 'Maintenance Supplier', 'Specialty Vendor');
```

### Enable Email Notifications:
Add to `purchase_request.php` after approval:
```php
// Send email to approver
send_pr_approval_email($pr_id, $approver_email);
```

---

## 🐛 Troubleshooting

### Issue: PR Number Not Generating
- **Cause:** Database sequence issue
- **Solution:** Check `purchase_requests` table has AUTO_INCREMENT set
- **Fix:**
```sql
ALTER TABLE purchase_requests MODIFY COLUMN id INT AUTO_INCREMENT;
```

### Issue: GRN Line Items Don't Link to PO
- **Cause:** Foreign key constraint failure
- **Solution:** Ensure `po_item_id` is set when creating GRN items
- **Fix:** Use provided SQL script to ensure proper table structure

### Issue: Vendor Cannot Be Deleted
- **Cause:** Vendor still referenced in POs
- **Solution:** This is intentional (data integrity). Set vendor status to "Inactive" instead

### Issue: PO Totals Not Calculating
- **Cause:** Line item costs not calculated
- **Solution:** Verify unit price × quantity on each line item
- **Fix:** Recalculate by clicking "Update Costs & Totals"

---

## 📚 Best Practices

### For Requesters:
1. Create detailed PR descriptions for each line item
2. Include justification (maintenance need, equipment failure, etc.)
3. Link to relevant Work Orders for context
4. Request ahead of time (account for vendor lead times)
5. Use preferred vendors when possible

### For Approvers:
1. Review PRs promptly to avoid delays
2. Check budget codes and cost centers
3. Verify vendor is "Active"
4. For high-value items, request quotes from multiple vendors
5. Escalate questionable requests appropriately

### For Purchasers:
1. Match PO items exactly to approved PR
2. Negotiate prices with preferred vendors
3. Include clear payment terms
4. Specify delivery location and contact
5. Track PO status and follow up on late deliveries

### For Receiving:
1. Inspect goods immediately upon arrival
2. Match received quantities to PO quantities
3. Record any damaged items (quantity_rejected)
4. Use batch/serial number tracking for high-value parts
5. Store items in designated locations for inventory
6. Complete GRN inspection status within 24 hours

---

## 📖 Additional Resources

- **Database Schema Diagram:** See `purchase_tables.sql`
- **Sample Data:** Included in migration script (vendors, parts)
- **API Endpoints:** Available for integration with ERP systems
- **Audit Reports:** Available in purchase_audit_log table

---

## Support & Maintenance

### Database Backups
Regularly backup purchasing tables:
```bash
mysqldump -u user -p database vendors purchase_* > backup_purchasing.sql
```

### Regular Maintenance
- Archive old GRNs monthly
- Review vendor quality ratings quarterly
- Audit approval cycle times
- Update payment terms annually

### Future Enhancements
- Integration with barcode scanning for receipt
- Email approvals and notifications
- Mobile app for field approvals
- Integration with accounting system for cost allocation
- Predictive reorder based on consumption patterns

---

**Last Updated:** February 2026
**System Version:** Maintenix 0.04+
**Author:** Maintenix Development Team
