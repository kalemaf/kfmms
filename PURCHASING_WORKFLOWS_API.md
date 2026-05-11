# Purchasing System - Workflow Diagrams & API Reference

## 📊 System Workflow Diagrams

### PR Approval Workflow

```
┌─────────────────────────────────────────────────────────────────┐
│                  PURCHASE REQUEST (PR) WORKFLOW                │
└─────────────────────────────────────────────────────────────────┘

REQUESTER PHASE
    │
    ├─ Create PR
    │  ├─ Add Basic Info (Dept, Priority, Justification)
    │  ├─ Add Line Items (Parts, Qty, Cost)
    │  └─ Save (Status: Draft)
    │
    └─ Submit for Approval
       └─ NO CHANGES - Stuck in Draft until approval

SUPERVISOR/APPROVER PHASE (Level 1)
    │
    ├─ Review PR
    │  ├─ Check Total Cost
    │  ├─ Verify Budget Code
    │  └─ Review Justification
    │
    ├─ APPROVE
    │  └─ Status: Pending Approval
    │      └─ Escalate to Manager if < $1000
    │
    └─ REJECT
       └─ Status: Draft (Requester revises)
          └─ Add Rejection Reason


MANAGER PHASE (Level 2) - Required for ALL amounts
    │
    ├─ Review PR
    │  ├─ Check Vendor Availability
    │  ├─ Verify Lead Times
    │  └─ Check Budget Codes
    │
    ├─ APPROVE
    │  ├─ Amount < $1000 → Status: APPROVED ✅
    │  └─ Amount ≥ $1000 → Status: Pending Approval (escalate)
    │
    └─ REJECT
       └─ Status: Draft (Requester revises)


FINANCE PHASE (Level 3) - Only if > $1000
    │
    ├─ Review PR
    │  ├─ Check Budget Availability
    │  ├─ Verify Cost Allocation
    │  └─ Check Annual Spend
    │
    ├─ APPROVE
    │  └─ Status: APPROVED ✅
    │
    └─ REJECT
       └─ Status: Draft (Requester revises)


PURCHASER PHASE (Post-Approval)
    │
    ├─ Review Approved PR
    │  └─ Lock - No further edits
    │
    └─ Convert to PO
       ├─ Create new PO
       ├─ Auto-populate line items from PR
       ├─ Select vendor
       └─ Status: Draft (PO workflow begins)
```

### PO Lifecycle Workflow

```
┌─────────────────────────────────────────────────────────────────┐
│                  PURCHASE ORDER (PO) WORKFLOW                  │
└─────────────────────────────────────────────────────────────────┘

CREATION & EDITING (DRAFT)
    │
    ├─ Create PO
    │  ├─ Manual creation (no PR)
    │  ├─ From Approved PR
    │  └─ Status: Draft
    │
    ├─ Add/Edit Line Items
    │  ├─ Part Number
    │  ├─ Quantity & Unit Price
    │  ├─ Discounts & Tax
    │  └─ Auto-calculate totals
    │
    ├─ Adjust Order Costs
    │  ├─ Order-level discount
    │  ├─ Tax calculation
    │  ├─ Shipping costs
    │  └─ Recalculate Grand Total
    │
    └─ Can DELETE or CANCEL while Draft


SENDING TO VENDOR
    │
    └─ "Send to Vendor" Click
       ├─ Status: Sent to Vendor
       ├─ Record sent_to_vendor_date
       ├─ Email/Print PO
       └─ Vendor reviews & confirms


VENDOR ACKNOWLEDGMENT
    │
    ├─ Vendor confirms receipt of PO
    │
    └─ Manually Mark as "Acknowledged"
       └─ Status: Acknowledged


GOODS DELIVERY PHASE
    │
    ├─ Goods in transit
    │  ├─ Track delivery status
    │  ├─ Monitor expected delivery date
    │  └─ Follow up if overdue
    │
    └─ Goods arrive at warehouse
       └─ RECEIVING CREATES GRN


RECEIVING & INSPECTION
    │
    └─ From PO "Partially Received" or "Fully Received"
       └─ Via GRN line items recording
          ├─ Quantity Received
          ├─ Inspection Result
          └─ Storage Location


FULL RECEIPT
    │
    └─ GRN Status: Fully Received
       └─ PO Status: Fully Received


CLOSING & INVOICING
    │
    ├─ Vendor sends invoice
    │  ├─ Match to PO (3-way match)
    │  ├─ Approve payment
    │  └─ Process payment
    │
    └─ Mark PO: Closed
       └─ Status: Closed (Archived)
```

### GRN Receipt Workflow

```
┌─────────────────────────────────────────────────────────────────┐
│                GOODS RECEIPT NOTE (GRN) WORKFLOW               │
└─────────────────────────────────────────────────────────────────┘

CREATION FROM PO
    │
    ├─ Open PO (Status: Sent/Acknowledged)
    │  └─ Click "GRN" button
    │
    └─ System Auto-Creates GRN
       ├─ Grant GRN Number (GRN-2026-00001)
       ├─ Copy all PO line items
       ├─ Default Inspection Status: Pending Inspection
       └─ Status: Draft


RECEIVING GOODS
    │
    └─ Goods Arrive → Record Receipt for Each Item
       │
       ├─ Scan/Verify Part Number
       │
       ├─ Count & Enter:
       │  ├─ Quantity Received
       │  ├─ Quantity Rejected (if damaged)
       │  ├─ Batch Number (from package)
       │  ├─ Serial Numbers (if valuable)
       │  └─ Storage Location (warehouse bin/shelf)
       │
       └─ Click "Record Receipt"
          └─ Line Item Saved


QUALITY INSPECTION
    │
    └─ QA Inspector Reviews Item
       │
       ├─ PASSED ✅
       │  └─ Set Inspection Status: Passed
       │
       ├─ FAILED ❌
       │  ├─ Set Inspection Status: Failed
       │  ├─ Enter Rejection Reason
       │  ├─ Add Notes
       │  └─ Prepare Return/Credit
       │
       └─ PENDING
          └─ Inspection Status: Pending Inspection
             └─ Requester's note: Not yet inspected


CLOSING GRN
    │
    └─ All Items Received & Inspected
       │
       ├─ No Pending Items
       │
       ├─ GRN Status Auto-Updates:
       │  ├─ Draft → Partially Received (some qty)
       │  └─ → Fully Received (all qty)
       │
       ├─ PO Status Auto-Updates:
       │  └─ Match GRN status
       │
       └─ Click "Close & Archive"
          └─ Status: Archived


INVENTORY UPDATE (Optional integration)
    │
    └─ System could auto-update parts_catalog:
       ├─ current_stock += quantity_received
       └─ Check if reorder_level breached
```

---

## 🔗 API Reference & Database Queries

### Common Queries

#### Get All Active Vendors
```sql
SELECT id, vendor_code, vendor_name, contact_person, email, phone
FROM vendors
WHERE status = 'Active'
ORDER BY vendor_name;
```

#### Get PRs Pending Approval
```sql
SELECT id, pr_number, requested_by, department, total_estimated_cost, priority
FROM purchase_requests
WHERE status IN ('Draft', 'Pending Approval')
ORDER BY created_at ASC;
```

#### Get Overdue POs
```sql
SELECT po.id, po.po_number, po.expected_delivery_date, v.vendor_name,
       DATEDIFF(CURDATE(), po.expected_delivery_date) as days_overdue
FROM purchase_orders po
LEFT JOIN vendors v ON po.vendor_id = v.id
WHERE po.expected_delivery_date < CURDATE()
  AND po.status NOT IN ('Fully Received', 'Closed', 'Cancelled')
ORDER BY po.expected_delivery_date ASC;
```

#### Get Pending GRN Inspections
```sql
SELECT grn.grn_number, grn.received_by, COUNT(gri.id) as pending_items
FROM goods_receipt_notes grn
LEFT JOIN goods_receipt_items gri ON grn.id = gri.grn_id
WHERE gri.inspection_status = 'Pending Inspection'
GROUP BY grn.id
ORDER BY grn.created_at DESC;
```

#### Get Vendor Performance (Last 6 Months)
```sql
SELECT v.vendor_code, v.vendor_name, v.quality_rating,
       COUNT(po.id) as num_orders,
       AVG(DATEDIFF(gri.inspection_date, po.po_date)) as avg_days_to_receive,
       SUM(CASE WHEN gri.inspection_status = 'Failed' THEN 1 ELSE 0 END) as failed_items
FROM vendors v
LEFT JOIN purchase_orders po ON v.id = po.vendor_id 
  AND po.po_date >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)
LEFT JOIN goods_receipt_items gri ON EXISTS (
    SELECT 1 FROM goods_receipt_notes grn 
    WHERE grn.vendor_id = v.id AND grn.po_id = po.id
)
GROUP BY v.id
ORDER BY v.vendor_name;
```

---

## 📱 Form Field Reference

### PR Creation Form Fields
```
REQUEST INFO:
├─ requested_by (text) - Default: Current user
├─ department (text) - e.g., "Maintenance", "Operations"
├─ priority (select) - Low/Medium/High/Emergency
├─ budget_code (text) - e.g., "MAINT-2026-Q1"
├─ cost_center (text) - e.g., "FACILITY-MAIN"
└─ linked_work_order_id (number, optional)

DESCRIPTION:
├─ justification (textarea) - WHY this purchase
└─ notes (textarea) - Additional context
```

### PR Line Item Fields
```
PART INFO:
├─ part_number (text) - Internal part code
├─ internal_part_number (text) - System part code
├─ oem_part_number (text) - Manufacturer part code
└─ description (text) - Item description

QUANTITY & COST:
├─ quantity_requested (decimal) - How many
├─ unit_of_measure (select) - EA/BOX/KIT/SET/PKG/METER/LITER/KG/LB
├─ estimated_unit_cost (decimal) - Cost per unit
└─ estimated_total_cost (auto-calculated) - qty × unit_cost

LOGISTICS:
├─ preferred_vendor_id (select) - Which vendor
├─ required_date (date) - When needed
└─ justification (text) - Why this item
```

### PO Creation Form Fields
```
HEADER:
├─ vendor_id (select) - Required vendor
├─ po_date (date) - PO issue date
├─ expected_delivery_date (date) - Expected arrival
├─ delivery_location (text) - Where to deliver
├─ linked_pr_id (number, optional) - Reference PR

PAYMENT:
├─ payment_terms (text) - e.g., "Net 30"
├─ currency (text) - USD/EUR/etc.
└─ notes (textarea)
```

### PO Line Item Fields
```
ITEM:
├─ part_number (text)
├─ description (text) - Required
├─ quantity_ordered (decimal) - Required
├─ unit_price (decimal) - Required
└─ unit_of_measure (select)

PRICING:
├─ discount_amount (decimal) - $ off
├─ discount_percent (decimal) - % off
└─ tax_percent (decimal) - % tax
```

### Cost Adjustments (PO Level)
```
ORDER TOTALS:
├─ discount_amount (decimal) - Order-level discount $
├─ discount_percent (decimal) - Order-level discount %
├─ tax_percent (decimal) - Tax on subtotal
└─ shipping_cost (decimal) - Shipping fees

AUTO-CALCULATED:
├─ subtotal - SUM(line totals)
├─ tax_amount - subtotal × tax%
└─ grand_total - subtotal - discounts + tax + shipping
```

### GRN Receipt Form Fields
```
DOCUMENT INFO:
├─ grn_date (date) - Receipt date
├─ received_by (text) - Who received
├─ delivery_note_number (text) - Courier reference
├─ vendor_invoice_number (text) - Invoice #
└─ vendor_invoice_date (date) - Invoice date

NOTES:
└─ notes (textarea)
```

### GRN Line Item Receipt Fields
```
QUANTITIES:
├─ quantity_received (decimal) - How many received
└─ quantity_rejected (decimal) - How many bad/damaged

TRACKING:
├─ batch_number (text) - Manufacturing lot #
├─ serial_numbers (textarea) - Serial #s (1 per line)
└─ storage_location (textarea) - Warehouse bin/location

QUALITY:
├─ inspection_status (select) - Pending/Passed/Failed/On Hold
└─ rejection_reason (text) - Why failed inspection

NOTES:
└─ received_notes (textarea) - Receiving notes
```

### Vendor Creation Form Fields
```
IDENTIFICATION:
├─ vendor_code (text) - Auto-generated or manual (V0001)
├─ vendor_name (text) - Required
├─ vendor_type (select) - Parts Supplier/Service Provider/etc.
├─ status (select) - Active/Inactive/Suspended
└─ quality_rating (select) - Excellent/Good/Average/Poor

CONTACT:
├─ contact_person (text)
├─ email (email)
├─ phone (text)
├─ fax (text)
└─ website (url)

ADDRESS:
├─ address (textarea)
├─ city (text)
├─ state_province (text)
├─ postal_code (text)
└─ country (text)

BUSINESS:
├─ payment_terms (text) - e.g., "Net 30"
├─ currency (text) - Default: USD
├─ lead_time_days (number) - Default: 7
├─ minimum_order_value (decimal)
├─ vat_gst_id (text)
└─ tax_id (text)

NOTES:
└─ notes (textarea)
```

---

## 🔐 Permission Matrix

```
                    | ADMIN | MANAGER | LEAD | USER
────────────────────┼───────┼─────────┼──────┼─────
CREATE PR           │  ✓    │    ✓    │  ✓   │  ✓
EDIT OWN PR DRAFT   │  ✓    │    ✓    │  ✓   │  ✓
EDIT OTHERS PR      │  ✓    │    -    │  -   │  -
APPROVE L1 (SUPR)   │  ✓    │    ✓    │  ✓   │  -
APPROVE L2 (MGR)    │  ✓    │    ✓    │  -   │  -
APPROVE L3 (FIN)    │  ✓    │    -    │  -   │  -
REJECT PR           │  ✓    │    ✓    │  ✓   │  -
────────────────────┼───────┼─────────┼──────┼─────
CREATE PO           │  ✓    │    ✓    │  -   │  -
EDIT PO DRAFT       │  ✓    │    ✓    │  -   │  -
SEND TO VENDOR      │  ✓    │    ✓    │  -   │  -
CANCEL PO           │  ✓    │    ✓    │  -   │  -
────────────────────┼───────┼─────────┼──────┼─────
CREATE GRN          │  ✓    │    ✓    │  -   │  -
RECORD RECEIPT      │  ✓    │    ✓    │  -   │  -
INSPECT ITEMS       │  ✓    │    ✓    │  -   │  -
CLOSE GRN           │  ✓    │    ✓    │  -   │  -
────────────────────┼───────┼─────────┼──────┼─────
MANAGE VENDORS      │  ✓    │    ✓    │  -   │  -
CREATE VENDOR       │  ✓    │    ✓    │  -   │  -
DELETE VENDOR       │  ✓    │    -    │  -   │  -
────────────────────┼───────┼─────────┼──────┼─────
VIEW ALL            │  ✓    │    ✓    │  ✓   │  ✓
PRINT DOCUMENTS     │  ✓    │    ✓    │  ✓   │  ✓
```

---

## 📊 Status Transitions

### PR Status Flow
```
Draft → Pending Approval → Approved ✅ → (Convert to PO)
 ↓           ↓                        
 ← ← ← Rejected ← ← ← (Can revise)

Archived (manually set after completion)
```

### PO Status Flow
```
Draft → Sent to Vendor → Acknowledged → Partially Received
                 ↓ (Cancel)                    ↓
              Cancelled                   Fully Received → Closed
```

### GRN Status Flow
```
Draft → Partially Received ✅ → Fully Received ✅ → Archived
         (Some qty received)    (All qty received)
```

### Vendor Status
```
Active → Inactive (Stop new orders)
     ↘          ↗
        Suspended (Investigation)
```

---

## 🧲 Integration Points

### With Work Orders
- Link PR to WO for maintenance context
- Track WO completion date vs PR creation
- Allocate costs to specific work orders

### With Equipment
- Link parts to equipment types
- Track part lifecycles by equipment
- Predict reorder needs by equipment failure patterns

### With Personnel
- Track approver performance
- Monitor approval times
- Identify bottlenecks

### With Inventory (Future)
- Auto-update current_stock on GRN receipt
- Trigger reorder when stock_level < reorder_level
- Track consumption patterns

---

## 📈 Key Metrics

### PR Metrics
```
Total PRs Created:        COUNT(*) from purchase_requests
Avg Time to Approval:     AVG(approved_by_level1_date - created_at)
Approval Rate:            COUNT(status='Approved') / Total × 100%
Rejection Rate:           COUNT(status='Rejected') / Total × 100%
Avg PR Value:             AVG(total_estimated_cost)
Pending Approval Count:   COUNT(status='Pending Approval')
```

### PO Metrics
```
Total Spend:              SUM(grand_total)
Avg Order Value:          AVG(grand_total)
Avg Lead Time:            AVG(expected_delivery_date - po_date)
On-Time Delivery %:       COUNT(delivery_date <= exp_delivery) / Total × 100%
Overdue POs:              COUNT(exp_delivery < TODAY)
Vendor Win Rate:          POs per vendor / Total POs
```

### GRN Metrics
```
Items Received:           SUM(quantity_received)
Items Rejected:           SUM(quantity_rejected)
Rejection %:              Rejected / Received × 100%
Inspection Pass %:        COUNT(status='Passed') / Total × 100%
Avg Time to Receipt:      AVG(grn_date - po_date_sent)
Pending Inspection:       COUNT(inspection_status='Pending')
```

### Vendor Metrics
```
Quality Score:            AVG(quality_rating)
Failure Rate:             Failed Items / Received Items × 100%
Avg Lead Time:            AVG(lead_time_days)
Payment Performance:      % Orders paid on-time
Pricing Trend:            Last Price vs Historical Avg % Change
Reorder Frequency:        COUNT(POs with this vendor)
```

---

**System Version:** Maintenix 0.04+
**Last Updated:** February 27, 2026
**API Reference Version:** 1.0
