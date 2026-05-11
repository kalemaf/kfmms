# Purchase Request & Inventory Workflow

## 1. WHERE DOES A PURCHASE REQUEST (PR) GO?

### PR Workflow Path:

```
[Create PR in /inventory/purchase_requests.php]
        ↓
[INSERT into purchase_requests table]
        ↓
    ┌───────────────────────────────────────────┐
    │ purchase_requests                         │
    │ - Row with pr_number, status, items, etc │
    │ - Status starts as "draft"                │
    └───────────────────────────────────────────┘
        ↓
[Manager Approves PR]
        ↓
    ┌───────────────────────────────────────────┐
    │ Status changes: draft → approved          │
    └───────────────────────────────────────────┘
        ↓
[Convert PR to Purchase Order (PO)]
        ↓
    ┌───────────────────────────────────────────┐
    │ purchase_orders                           │
    │ - linked_pr_id points back to PR          │
    │ - PO sent to vendor                       │
    │ - Status: Draft → Sent to Vendor          │
    └───────────────────────────────────────────┘
        ↓
[Vendor Receives Order, Sends Goods]
        ↓
[Create Goods Receipt Note (GRN)]
        ↓
    ┌───────────────────────────────────────────┐
    │ goods_receipt_notes                       │
    │ - Links to PO                             │
    │ - Copies items to goods_receipt_items     │
    └───────────────────────────────────────────┘
        ↓
[Stock Received - UPDATE stock_locales]
        ↓
    ┌───────────────────────────────────────────┐
    │ stock_locales                             │
    │ - quantity_on_hand INCREASES              │
    │ - part appears in analytics               │
    └───────────────────────────────────────────┘
```

### Database Tables in Order:

| Table | Purpose | When Created |
|-------|---------|--------------|
| `purchase_requests` | Initial request from department | User clicks "Create PR" |
| `purchase_request_items` | Line items in the PR | When user adds items to PR |
| `purchase_orders` | Formal order to vendor | Manager converts PR to PO |
| `purchase_order_items` | Items being ordered | When PO is created |
| `goods_receipt_notes` | Goods arrival notification | When supplier delivers |
| `goods_receipt_items` | Items received | When GRN is created |
| `stock_locales` | **Actual inventory** | When GRN items are confirmed |

---

## 2. WHY IS CURRENT STOCK SHOWING ZERO?

### Causes:

**A. No data has been received yet**
- `stock_locales` table is empty
- No goods receipts created and confirmed
- System is brand new with test parts only

**B. Stock was received but not confirmed**
- GRN created but not finalized
- Items in `goods_receipt_items` but not yet moved to `stock_locales`

**C. Parts exist but no warehouse location**
- `warehouse_locations` table might be empty
- Stock can only be recorded at specific warehouse locations

### How to Check Current Stock:

```sql
-- Check if any stock exists
SELECT * FROM stock_locales;

-- Check stock for all parts
SELECT 
    pm.part_code,
    pm.part_name,
    COALESCE(SUM(sl.quantity_on_hand), 0) as total_on_hand,
    COALESCE(SUM(sl.quantity_reserved), 0) as reserved,
    COALESCE(SUM(sl.quantity_available), 0) as available
FROM parts_master pm
LEFT JOIN stock_locales sl ON pm.id = sl.part_id
WHERE pm.is_active = 1
GROUP BY pm.id;

-- Check warehouse locations
SELECT * FROM warehouse_locations;

-- Check if warehouse has storage capacity
SELECT id, warehouse_name, location_name FROM warehouse_locations LIMIT 5;
```

### Solution: Initialize Stock

```sql
-- 1. Create warehouse location if needed
INSERT INTO warehouses (warehouse_code, warehouse_name, location, is_active)
VALUES ('WH-01', 'Main Warehouse', 'Building A', 1);

INSERT INTO warehouse_locations 
    (warehouse_id, location_code, location_name, max_capacity, is_active)
VALUES 
    (1, 'LOC-001', 'Shelf A-1', 1000, 1),
    (1, 'LOC-002', 'Shelf A-2', 1000, 1);

-- 2. Add initial stock for each part
INSERT INTO stock_locales 
    (part_id, warehouse_location_id, quantity_on_hand, last_received_date)
SELECT 
    pm.id, 
    wl.id,
    100,  -- Initial quantity, adjust as needed
    NOW()
FROM parts_master pm, warehouse_locations wl
WHERE pm.is_active = 1
AND pm.part_code IN ('675y', 'gear', 'y654', '786cm', '54600')
AND wl.location_code = 'LOC-001'
AND NOT EXISTS (
    SELECT 1 FROM stock_locales 
    WHERE part_id = pm.id 
    AND warehouse_location_id = wl.id
);
```

---

## 3. AUTO-REDUCE STOCK WHEN PARTS USED IN WORK ORDERS

### Current State: Stock Reduction NOT Automatic

The system has **two separate modules**:
1. **Work Orders** - assign maintenance tasks, consume parts
2. **Inventory** - track stock levels

**They don't automatically sync yet.**

### How to Enable Auto-Stock Reduction

#### Option 1: Manual Stock Deduction (Current)

When work order is completed:
1. Go to inventory/stock_management.php
2. Manually reduce stock for each part used
3. Creates inventory transaction log

#### Option 2: Link Work Order Parts to Stock (RECOMMENDED)

Modify `save.php` to track work order part usage:

```php
// When work order parts are added/updated:

// 1. Track part usage in wo_parts table
INSERT INTO wo_parts (wo_id, part_id, quantity_required, status)
VALUES ($wo_id, $part_id, $qty, 'pending');

// 2. When work order is COMPLETED:
$query = "UPDATE stock_locales 
          SET quantity_on_hand = quantity_on_hand - quantity_required,
              last_issued_date = NOW()
          FROM wo_parts 
          WHERE wo_parts.part_id = stock_locales.part_id
          AND wo_parts.wo_id = $wo_id
          AND wo_parts.status = 'issued'";
```

### Implementation Plan:

#### Step 1: Create Work Order Parts Integration
```php
// In inventory_manager.php, add:

function reserve_parts_for_wo($wo_id, $parts, $connection) {
    // Reserve parts when WO is assigned
    foreach ($parts as $part) {
        $part_id = $part['part_id'];
        $qty = $part['quantity'];
        
        // Reserve inventory
        $query = "UPDATE stock_locales 
                 SET quantity_reserved = quantity_reserved + $qty
                 WHERE part_id = $part_id";
        $connection->query($query);
        
        // Track in wo_parts
        $insert = "INSERT INTO wo_parts 
                  (wo_id, part_id, quantity_required, status)
                  VALUES ($wo_id, $part_id, $qty, 'reserved')";
        $connection->query($insert);
    }
}

function issue_parts_for_wo($wo_id, $connection) {
    // Issue parts when work starts
    $query = "UPDATE stock_locales sl
             SET quantity_reserved = quantity_reserved - wp.quantity_required,
                 quantity_issued = quantity_issued + wp.quantity_required,
                 quantity_on_hand = quantity_on_hand - wp.quantity_required
             FROM wo_parts wp
             WHERE wp.part_id = sl.part_id
             AND wp.wo_id = $wo_id
             AND wp.status = 'reserved'";
    $connection->query($query);
    
    // Update wo_parts status
    $update = "UPDATE wo_parts SET status = 'issued' WHERE wo_id = $wo_id";
    $connection->query($update);
}

function return_unused_parts($wo_id, $connection) {
    // Return parts if work order is cancelled
    $query = "UPDATE stock_locales sl
             SET quantity_reserved = quantity_reserved - wp.quantity_required,
                 quantity_on_hand = quantity_on_hand + wp.quantity_required
             FROM wo_parts wp
             WHERE wp.part_id = sl.part_id
             AND wp.wo_id = $wo_id
             AND wp.status = 'reserved'";
    $connection->query($query);
    
    $update = "UPDATE wo_parts SET status = 'returned' WHERE wo_id = $wo_id";
    $connection->query($update);
}
```

#### Step 2: Hook into Work Order Lifecycle

In `save.php`, after work order is saved:
```php
if ($document === 'work_order') {
    $wo_id = $insertkeywo_id;
    
    // When WO status = "Assigned"
    if ($_POST['wo_status'] === 'Assigned') {
        $parts = $_POST['parts'] ?? []; // Parts array from form
        reserve_parts_for_wo($wo_id, $parts, $connection);
    }
    
    // When WO status = "In Progress"
    if ($_POST['wo_status'] === 'In Progress') {
        issue_parts_for_wo($wo_id, $connection);
    }
    
    // When WO status = "Cancelled"
    if ($_POST['wo_status'] === 'Cancelled') {
        return_unused_parts($wo_id, $connection);
    }
}
```

---

## 4. Complete Workflow Example: Chain Part

```
User Input:
  Part: "675y" (chain)
  Required: 100 units
  
Step 1: CREATE PR
  INSERT into purchase_requests (pr_number='PR-20260302-1234', status='draft')
  INSERT into purchase_request_items (part_id=1, quantity_requested=100)
  
Step 2: MANAGER APPROVES PR
  UPDATE purchase_requests SET status='approved' WHERE id=1
  
Step 3: CONVERT PR TO PO
  INSERT into purchase_orders (linked_pr_id=1, vendor_id=2, status='draft')
  INSERT into purchase_order_items (po_id=1, part_id=1, quantity_ordered=100)
  UPDATE purchase_requests SET status='po_created' WHERE id=1
  
Step 4: SEND PO TO VENDOR
  UPDATE purchase_orders SET status='Sent to Vendor' WHERE id=1
  
Step 5: VENDOR DELIVERS GOODS
  Create GRN in inventory/goods_receipt.php
  
Step 6: CONFIRM RECEIPT
  INSERT into goods_receipt_items (received_qty=100, confirmed=1)
  UPDATE stock_locales 
    SET quantity_on_hand = quantity_on_hand + 100
    WHERE part_id=1 AND warehouse_location_id=1
  
Step 7: STOCK NOW SHOWS IN ANALYTICS
  /inventory/inventory_analytics.php shows:
  - Total Parts: Updated
  - Normal Stock: 100 units of "675y"
  - Total Inventory Value: Updated
```

---

## 5. Status Stages Explained

### Purchase Request Statuses:
- **Draft** → Initial creation
- **Pending Approval** → Awaiting manager review
- **Approved** → Ready to convert to PO
- **Rejected** → Denied by manager
- **Archived** → Closed/inactive

### Purchase Order Statuses:
- **Draft** → Initial creation
- **Sent to Vendor** → Order placed
- **Acknowledged** → Vendor confirmed receipt
- **Partially Received** → Some items arrived
- **Fully Received** → All items confirmed
- **Closed** → Complete

### Stock Locales Statuses:
- **quantity_on_hand** → Available for use
- **quantity_reserved** → Set aside for work orders (not available)
- **quantity_issued** → Actively being used in work
- **quantity_available** → on_hand - reserved (automatically calculated)

---

## 6. Quick Setup Checklist

- [ ] Create warehouse & warehouse locations
- [ ] Initialize parts_master with part codes & unit costs
- [ ] Initialize stock_locales with stock levels
- [ ] Create test PR
- [ ] Approve PR
- [ ] Convert to PO
- [ ] Create GRN to receive goods
- [ ] Verify stock appears in analytics
- [ ] Create work order using parts
- [ ] Verify parts reserved when WO assigned
- [ ] Verify parts issued when WO starts
