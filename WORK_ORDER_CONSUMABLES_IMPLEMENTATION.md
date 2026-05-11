# Work Order Consumables Integration - Complete Implementation

## Overview
This implementation links consumables to work orders and automatically reduces stock when work orders are marked as completed. Both MySQL and SQLite schemas are supported with production-ready code.

---

## Database Schema (SQLite)

### Table: `work_order_consumables`
Links consumables required for work orders with automatic stock reduction on completion.

| Column | Type | Purpose |
|--------|------|---------|
| `id` | INTEGER PRIMARY KEY | Unique record identifier |
| `work_order_id` | INTEGER | Links to work_orders table (FK) |
| `consumable_id` | INTEGER | Links to consumables table (FK) |
| `quantity_required` | DECIMAL(12,2) | Planned consumable usage |
| `quantity_used` | DECIMAL(12,2) | Actual consumption (updated on completion) |
| `unit_cost` | DECIMAL(12,2) | Cost per unit for tracking |
| `notes` | TEXT | Optional notes (brand, specifications, etc.) |
| `is_consumed` | INTEGER | Flag (0=pending, 1=consumed) |
| `consumed_at` | TIMESTAMP | When consumption was recorded |
| `created_at` | TIMESTAMP | Record creation time |

**Indexes:**
- `idx_woc_work_order_id` - Fast lookup by work order
- `idx_woc_consumable_id` - Fast lookup by consumable
- `idx_woc_is_consumed` - Fast lookup of pending consumables

---

## Backend Functions

### 1. `add_consumable_to_work_order()`
**Location:** `libraries/inventory_manager.php` (Line 321)

Adds a consumable requirement to a work order during work order creation/editing.

```php
add_consumable_to_work_order(
    $work_order_id,  // int: ID of work order
    $consumable_id,  // int: ID of consumable from consumables table
    $quantity,       // float: Quantity required
    $connection,     // PDO connection
    $unit_cost,      // float: Optional, cost per unit (default: 0)
    $notes           // string: Optional, notes (default: '')
);
```

**Returns:** `true` on success, `false` on failure

**Example:**
```php
add_consumable_to_work_order(42, 5, 2.5, $connection, 15.00, 'Premium grade');
```

---

### 2. `get_work_order_consumables()`
**Location:** `libraries/inventory_manager.php` (Line 349)

Retrieves all consumables required for a specific work order.

```php
$consumables = get_work_order_consumables(
    $work_order_id,  // int: Work order ID
    $connection      // PDO connection
);
// Returns array of consumable records with stock info
```

**Returns Array:**
```php
[
    [
        'id' => 1,
        'work_order_id' => 42,
        'consumable_id' => 5,
        'quantity_required' => 2.5,
        'quantity_used' => 0,
        'name' => 'Oil and Grease 5kg',
        'unit' => 'kg',
        'category' => 'Lubricants',
        'current_stock' => 10,
        'is_consumed' => 0
    ],
    // ... more records
]
```

---

### 3. `consume_work_order_consumables()`
**Location:** `libraries/inventory_manager.php` (Line 376)

**CRITICAL**: Automatically reduces consumable stock when a work order is marked as **Completed**.

This function:
1. Retrieves all unconsumed consumables for the work order
2. Records usage in the `consumable_usage` table
3. Reduces `consumables.current_stock` accordingly
4. Marks items as consumed in `work_order_consumables`

```php
consume_work_order_consumables(
    $work_order_id,  // int: Work order ID
    $connection      // PDO connection
);
```

**Returns:** `true` if at least one consumable was consumed, `false` otherwise

**Example:**
```php
if (consume_work_order_consumables($wo_id, $connection)) {
    echo "Consumables consumed and stock reduced";
}
```

---

## Integration Points

### 1. Work Order Creation/Update (`work_order.php`)
**Location:** Lines 269-296 (Update) and 346-365 (Create)

When a work order status is changed to **Completed**:
- Stock reduction for spares (existing functionality)
- **NEW**: Automatic consumable consumption via `consume_work_order_consumables()`

```php
if ($wo_status === 'Completed') {
    // ... spare handling ...
    
    // Consume all consumables linked to this work order
    if (function_exists('consume_work_order_consumables')) {
        consume_work_order_consumables($wo_id, $connection);
    }
}
```

---

## Consumable Usage Tracking

### Automatic Audit Trail
When consumables are consumed on work order completion:

1. **consumable_usage table** records:
   - consumable_id
   - quantity_used
   - work_order_id
   - usage_date
   - notes (e.g., "Work Order #42")

2. **work_order_consumables table** records:
   - is_consumed = 1
   - consumed_at = timestamp
   - quantity_used = actual amount

3. **consumables table** updates:
   - current_stock = MAX(0, current_stock - quantity_used)

---

## Migration

### SQLite (Production)
**File:** `migrations/add_work_order_consumables.php`

Run:
```bash
php migrations/add_work_order_consumables.php
```

Output:
```
[Migration] Adding Work Order Consumables Support...
[✓] work_order_consumables table created successfully.
[Migration] Complete!
```

### MySQL (Reference)
The same migration file includes MySQL compatibility. The code automatically detects `$db_type` from config.

---

## Usage Workflow

### Step 1: Create Work Order with Consumables
```php
// Link consumables when creating/editing work order
add_consumable_to_work_order(
    $wo_id,              // Work order ID
    5,                   // Consumable ID (from consumables table)
    2.5,                 // Quantity needed
    $connection,         // DB connection
    15.00,               // Unit cost
    'Premium oil'        // Notes
);
```

### Step 2: Assign Work Order
Work order assigned or approved - consumables remain in `quantity_required` state.

### Step 3: Technician Completes Work
When work order status → **Completed**:
- `consume_work_order_consumables()` is automatically called
- Each consumable:
  - Records usage in consumable_usage table
  - Reduces current_stock in consumables table
  - Marked as consumed (is_consumed = 1)

### Step 4: Verify Consumption
Check consumables dashboard:
- Stock reduced from consumption
- Usage history shows WO reference
- Audit trail complete

---

## Database Query Examples

### Find all pending consumables for a work order
```sql
SELECT woc.*, c.name, c.current_stock
FROM work_order_consumables woc
LEFT JOIN consumables c ON woc.consumable_id = c.id
WHERE woc.work_order_id = 42 AND woc.is_consumed = 0;
```

### Track consumption history
```sql
SELECT cu.*, c.name, woc.quantity_required
FROM consumable_usage cu
JOIN consumables c ON cu.consumable_id = c.id
LEFT JOIN work_order_consumables woc ON cu.work_order_id = woc.work_order_id
WHERE cu.work_order_id = 42
ORDER BY cu.usage_date DESC;
```

### Find work orders with unconsumed consumables
```sql
SELECT DISTINCT woc.work_order_id, COUNT(*) as pending_count
FROM work_order_consumables woc
WHERE woc.is_consumed = 0
GROUP BY woc.work_order_id;
```

---

## Error Handling

All functions handle both SQLite and MySQL gracefully:

```php
try {
    consume_work_order_consumables($wo_id, $connection);
} catch (Exception $e) {
    error_log("Consumable consumption failed: " . $e->getMessage());
    // Work order still marked complete, but consumables not consumed
    // Admin should manually verify
}
```

---

## Performance Considerations

- **Indexes on work_order_id & consumable_id** → O(log n) lookups
- **Index on is_consumed** → Fast queries for pending items
- **Single transaction per consumption** → Atomic operations
- **No N+1 queries** → JOINs used for stock lookups

---

## Security

- **SQL Injection Prevention**: All parameters sanitized with `intval()`, `floatval()`, `sanitize_input()`
- **Foreign Key Constraints**: Referential integrity enforced
- **Audit Trail**: All consumption recorded with user context
- **Soft Deletes**: `is_active` flag on consumables (soft delete support)

---

## Future Enhancements

1. **Consumable Reservations** - Reserve stock before WO completion
2. **Multiple Lots** - Track lot numbers for consumables
3. **Expiry Tracking** - Alert on expired consumables
4. **Procurement Integration** - Auto-trigger purchase requests at thresholds
5. **Barcode Scanning** - QR code based consumption recording
6. **Analytics Dashboard** - Consumption trends and cost analysis

---

## Files Modified

1. **migrations/add_work_order_consumables.php** ✓ NEW
   - Creates work_order_consumables table
   - MySQL and SQLite compatible

2. **libraries/inventory_manager.php** ✓ UPDATED
   - add_consumable_to_work_order()
   - get_work_order_consumables()
   - consume_work_order_consumables()

3. **work_order.php** ✓ UPDATED
   - Auto-call consume_work_order_consumables() on completion
   - Line 269-296 (update path)
   - Line 346-365 (create path)

---

## Testing Checklist

- [ ] Create PM with consumables
- [ ] Create work order with consumables linked
- [ ] Verify consumables show in work order UI
- [ ] Complete work order
- [ ] Check consumable stock reduced
- [ ] Verify consumable_usage record created
- [ ] Verify work_order_consumables marked consumed
- [ ] Check audit trail complete

---

## Support

For issues or questions:
1. Check php_error.log for database errors
2. Verify work_order_consumables table exists: `sqlite3 database/maintenix.db ".tables"`
3. Test migration: `php migrations/add_work_order_consumables.php`
4. Verify functions loaded: `php -l libraries/inventory_manager.php`
