# Complete PR & Inventory Workflow - Quick Reference

## Question 1: Where Do PR Orders Go in the Database?

### The Complete Journey:

```
1. USER CREATES PR (Purchase Request)
   Location: /inventory/purchase_requests.php
   Action: Click "Create PR" → Fill form → Submit
   Database Result: INSERT into purchases_requests table
   
2. PR RECORDED IN DATABASE
   Table: purchase_requests
   Fields: id, pr_number, status='pending', total_amount, created_by, created_at
   
3. PR ITEMS RECORDED
   Table: purchase_request_items
   Fields: pr_id, part_id, quantity, unit_price, line_total
   
4. MANAGER APPROVES PR
   Location: /inventory/purchase_requests.php (approval screen)
   Action: Manager reviews → Clicks "Approve"
   Database Result: UPDATE purchase_requests SET status='approved'
   
5. CONVERT PR TO PURCHASE ORDER
   Location: /inventory/purchase_orders.php
   Action: Click "Create PO from PR" → Select approved PRs → Generate
   Database Result: INSERT into purchase_orders table
   
6. PO ITEMS CREATED
   Table: purchase_order_items
   Fields: po_id, part_id, quantity, unit_price, line_total
   Reference: Links back to purchase_request_items via part_id
   
7. PO SENT TO VENDOR
   Status: purchase_orders.status = 'sent'
   Note: Vendor sends invoice/confirmation
   
8. GOODS RECEIVED
   Location: /inventory/goods_receipt.php
   Action: Receiving clerk receives goods → Creates GRN
   Database Result: INSERT into goods_receipt_notes table
   
9. GRN DETAILS RECORDED
   Table: goods_receipt_items
   Fields: grn_id, po_item_id, quantity_received, condition, location
   
10. STOCK UPDATED IN SYSTEM
    Table: stock_locales
    Action: UPDATE quantity_on_hand = quantity_on_hand + received_quantity
    Result: Stock now appears in Inventory Analytics!
```

### Database Tables Involved (IN ORDER):

| # | Table | Purpose | When Filled |
|---|-------|---------|------------|
| 1 | `purchase_requests` | Stores PR header | When PR created |
| 2 | `purchase_request_items` | Stores PR line items | When PR created |
| 3 | `purchase_orders` | Stores PO header | When converted from PR |
| 4 | `purchase_order_items` | Stores PO line items | When converted from PR |
| 5 | `goods_receipt_notes` | Stores GRN header | When goods received |
| 6 | `goods_receipt_items` | Stores GRN line items | When goods received |
| 7 | `stock_locales` | Actual inventory storage | When GRN confirmed |

---

## Question 2: Why Is Current Stock Showing Zero?

### Root Cause Analysis:

There are THREE possible reasons stock shows zero:

### Reason #1: NO WAREHOUSES DEFINED
```sql
-- Check if warehouses exist:
SELECT COUNT(*) FROM warehouses;

-- If 0, this is the problem!
-- Fix: Create warehouse first
INSERT INTO warehouses (warehouse_name, location, is_active)
VALUES ('Main Warehouse', 'Building A', 1);
```

### Reason #2: NO WAREHOUSE LOCATIONS
```sql
-- Check if locations exist:
SELECT COUNT(*) FROM warehouse_locations;

-- If 0, this is the problem!
-- Fix: Create at least one location
INSERT INTO warehouse_locations (warehouse_id, location_code, location_name, is_active)
VALUES (1, 'SHELF-A1', 'Shelf A - Row 1', 1);
```

### Reason #3: NO STOCK RECORDS CREATED (MOST COMMON)
```sql
-- Check if stock exists:
SELECT COUNT(*) FROM stock_locales;

-- If 0, stock shows zero because there IS no stock!
-- Fix: Either:
--   A) Create GRN to receive goods (brings stock in via purchasing)
--   B) Manually initialize stock (for initial setup)

-- Option A - Receive goods via GRN:
-- 1. Create PO from approved PR
-- 2. Mark PO as received
-- 3. Create GRN with quantities
-- 4. Stock automatically populates

-- Option B - Manual stock initialization:
INSERT INTO stock_locales 
(part_id, warehouse_id, location_id, quantity_on_hand, quantity_reserved, quantity_available)
VALUES (5, 1, 1, 100, 0, 100);  -- 100 units of part_id 5
```

### Verification Query:
Run this to see EXACTLY what's missing:
```sql
SELECT 
    'Parts' as item, COUNT(*) as count FROM parts_master WHERE is_active=1
UNION ALL
SELECT 'Warehouses', COUNT(*) FROM warehouses WHERE is_active=1
UNION ALL
SELECT 'Locations', COUNT(*) FROM warehouse_locations WHERE is_active=1
UNION ALL
SELECT 'Stock Records', COUNT(*) FROM stock_locales;
```

**Expected output for working system:**
- Parts: 50+ (you should have defined parts)
- Warehouses: 1+ (at least one warehouse)
- Locations: 1+ (at least one location per warehouse)
- Stock Records: 20+ (stock initialized or received via GRN)

---

## Question 3: Should Stock Auto-Reduce When Parts Used in Work Orders?

### SHORT ANSWER: 
**YES - and it CAN! But you must set it up.**

### CURRENT BEHAVIOR:
- Work orders and inventory are SEPARATE systems
- When you add parts to WO, nothing happens to stock
- When you close WO, still nothing happens
- Stock only changes when GRNs are received

### DESIRED BEHAVIOR:
- Parts should be RESERVED when WO created
- Parts should be CONSUMED when WO completed
- Stock automatically reduces
- Prevents over-allocation (can't use parts you don't have)

### HOW TO ENABLE AUTO-STOCK REDUCTION:

#### Step 1: Add Functions to inventory_manager.php
Copy entire content of `AUTO_STOCK_REDUCTION.php` to end of `inventory_manager.php`:
- `reserve_parts_for_work_order()` - Called when WO created
- `issue_parts_for_work_order()` - Called when WO completed

#### Step 2: Create inventory_transactions Table
Run SQL to create audit table:
```sql
CREATE TABLE inventory_transactions (
    id INT PRIMARY KEY AUTO_INCREMENT,
    part_id INT NOT NULL,
    wo_id INT,
    purchase_order_id INT,
    warehouse_id INT,
    action VARCHAR(50),
    quantity DECIMAL(10,2),
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX(part_id),
    INDEX(wo_id),
    INDEX(warehouse_id),
    FOREIGN KEY(part_id) REFERENCES parts_master(id)
);
```

#### Step 3: Hook Into Work Order Lifecycle
In `save.php`, add these calls:

**When creating WO:**
```php
require_once './libraries/inventory_manager.php';

$parts_to_reserve = [];
foreach ($_POST['wo_parts'] as $part) {
    $parts_to_reserve[] = [
        'part_id' => intval($part['part_id']),
        'quantity' => floatval($part['quantity']),
        'warehouse_id' => 1  // or from form
    ];
}

$result = reserve_parts_for_work_order($new_wo_id, $parts_to_reserve, $connection);
if (!$result['success']) {
    die("Cannot reserve parts: " . $result['error']);
}
```

**When completing WO:**
```php
if ($_POST['status'] === 'complete') {
    $result = issue_parts_for_work_order($wo_id, $connection);
    if (!$result['success']) {
        die("Error issuing stock: " . $result['error']);
    }
}
```

### RESULT AFTER SETUP:

#### Before:
1. WO created with 5 parts → Nothing changes
2. Stock still shows 100 units
3. WO completed → Stock still 100 units

#### After:
1. WO created with 5 parts → Stock shows 95 units (5 reserved)
2. System prevents WO if insufficient stock
3. WO completed → Stock shows 95 units (parts consumed)

---

## QUICK DEBUGGING CHECKLIST

Use this to verify your system is set up correctly:

```
☐ Inventory Analytics shows non-zero stock?
  → If NO: Run: SELECT COUNT(*) FROM stock_locales;
  → If 0: Need to initialize stock or create GRNs
  
☐ Can create PR and convert to PO?
  → If failing: Check /inventory/purchase_requests.php for errors
  
☐ Can receive goods via GRN?
  → If failing: Check /inventory/goods_receipt.php
  
☐ Stock updates when GRN created?
  → If NO: Check that warehouse/locations exist first
  
☐ Work orders consume stock?
  → If NO: You haven't set up auto-reduction yet
  → Run: AUTO_STOCK_REDUCTION.php setup steps
```

---

## VISUAL WORKFLOW

```
┌─────────────────────────────────────────────────────────────────┐
│ PURCHASING WORKFLOW                                             │
├─────────────────────────────────────────────────────────────────┤
│                                                                  │
│  User creates PR     Manager approves     Create PO            │
│  (/purchases)        (status=approved)    (/purchase_orders)   │
│        ↓                     ↓                   ↓              │
│   purchase_requests   purchase_requests   purchase_orders      │
│ ______________________________________________________________  │
│                                                                  │
│   Send to vendor     Goods arrive        Receive via GRN       │
│   (status=sent)      (/goods_receipt)    (create goods_...     │
│        ↓                     ↓            ...receipt_notes)     │
│   purchase_orders  goods_receipt_notes      ↓                  │
│ ______________________________________________________________  │
│                                                                  │
│                  STOCK UPDATED!                                 │
│                  stock_locales updated                          │
│                  ↓                                              │
│            Analytics show stock                                │
│            Work orders can now use parts                        │
│                                                                  │
└─────────────────────────────────────────────────────────────────┘
```

---

## PRACTICAL EXAMPLE: Ordering "Chain" Part

### Step 1: Check Stock
```
You notice chain stock is low (5 units, needs 50)
Go to Inventory Analytics → Click "Parts Requiring Reorder"
See: Chain, Quantity Needed: 45 units
```

### Step 2: Create PR
```
Click: "Create PR for this part"
Fill: Quantity: 50, Unit Price: $2.50, Required By: 2024-02-01
Submit
Result: PR#2024-001-5 created
```

### Step 3: Approve PR
```
Manager reviews PR
Clicks: "Approve"
Result: PR status = approved
```

### Step 4: Convert to PO
```
Buyer clicks: "Create Purchase Order"
Selects: PR#2024-001-5
Clicks: Generate PO
Result: PO#2024-00001 created with 50 units chain @ $2.50 each = $125
```

### Step 5: Send to Vendor
```
Buyer sends PO to vendor
Updates: PO status = Sent
Vendor sends confirmation
```

### Step 6: Receive Goods
```
Goods arrive
Receiving clerk goes to: /inventory/goods_receipt.php
Creates GRN#2024-001
Confirms: 50 units chain received in good condition
Location: Main Warehouse / Shelf A1
```

### Step 7: Stock Available
```
System automatically:
✓ Updates stock_locales: quantity_on_hand = 55 (5 existing + 50 new)
✓ Inventory Analytics shows: Chain - 55 units (NORMAL status)
✓ Work orders can now consume it
```

---

## FILES YOU'VE JUST CREATED

1. **pr_inventory_debugger.php** - Dashboard showing exact system status
   - Shows parts, warehouses, locations, stock counts
   - Lists recent PRs and stock levels
   - Highlights what's missing

2. **AUTO_STOCK_REDUCTION.php** - Complete auto-stock reduction implementation
   - Copy functions to inventory_manager.php
   - Add hooks to save.php
   - SQL schema included

3. **PR_INVENTORY_WORKFLOW_COMPLETE.md** - Comprehensive workflow guide
   - Complete database flow
   - Step-by-step setup
   - Integration with work orders

---

## NEXT STEPS

1. **Visit** `/pr_inventory_debugger.php` to see your system status
2. **If stock is zero:**
   - Run: `initialize_inventory_system.sql`
   - Creates warehouses, locations, and test stock
3. **To enable auto-stock reduction:**
   - Follow steps in AUTO_STOCK_REDUCTION.php
   - Add functions to inventory_manager.php
   - Hook into save.php work order lifecycle
4. **Test workflow:**
   - Create PR → Approve → Convert to PO → Create GRN → Verify stock

---

## SUPPORT QUERIES

Run these SQL queries to understand your system:

```sql
-- See all warehouses
SELECT * FROM warehouses;

-- See warehouse locations  
SELECT * FROM warehouse_locations;

-- See current stock levels
SELECT 
    pm.part_code,
    pm.part_name,
    sl.quantity_on_hand,
    sl.quantity_reserved,
    sl.quantity_available
FROM stock_locales sl
JOIN parts_master pm ON sl.part_id = pm.id;

-- See purchase request history
SELECT * FROM purchase_requests ORDER BY created_at DESC;

-- See current stock vs. what's been ordered (not yet received)
SELECT 
    pm.part_code,
    pm.part_name,
    COALESCE(sl.quantity_on_hand, 0) as on_hand,
    COALESCE(SUM(poi.quantity), 0) as pending_from_po
FROM parts_master pm
LEFT JOIN stock_locales sl ON pm.id = sl.part_id
LEFT JOIN purchase_order_items poi ON pm.id = poi.part_id
LEFT JOIN purchase_orders po ON poi.po_id = po.id AND po.status != 'complete'
GROUP BY pm.id;
```

---

Last Updated: 2024
System: Maintenix v0.04
Module: Purchasing & Inventory Integration
