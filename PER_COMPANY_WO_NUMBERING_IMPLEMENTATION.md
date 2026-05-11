# Per-Company Work Order Numbering Implementation Guide

## Overview

This guide explains how to implement per-company work order numbering where each company gets their own independent WO sequence starting from #1.

---

## What's Changing

### Before (Current System)
```
Company 1 sees:  WO #1, #2, #3, #4, #5  (Global numbering)
Company 2 sees:  WO #8                   (Global numbering - confusing!)
Company 3 sees:  WO #7
Company 4 sees:  WO #6
```

### After (New System)
```
Company 1 sees:  WO #1, #2, #3, #4, #5  (Per-company)
Company 2 sees:  WO #1                   (Their own sequence!)
Company 3 sees:  WO #1                   (Their own sequence!)
Company 4 sees:  WO #1                   (Their own sequence!)
```

---

## Database Changes

### New Column: `wo_number`
- **Purpose**: Stores per-company work order number (1, 2, 3...)
- **Type**: INTEGER
- **Default**: 0 (gets set when WO is created)
- **Index**: Created for `(tenant_id, wo_number)` for fast queries

### Migration Script
**File**: [migrate_per_company_wo_numbering.php](migrate_per_company_wo_numbering.php)

**What it does**:
1. Adds `wo_number` column to work_orders table
2. Backfills existing WOs with sequential numbers per tenant
3. Creates index for performance

**Run it**:
```bash
php migrate_per_company_wo_numbering.php
```

---

## Code Changes Required

### 1. Helper Functions (✅ Already Added)

**File**: [wo_numbering_helpers.inc.php](wo_numbering_helpers.inc.php)

**Functions**:
```php
// Get next WO number for a tenant
get_next_wo_number($connection, $tenant_id)

// Get display number from wo_id
get_wo_display_number($connection, $wo_id)

// Format WO reference for display
format_wo_reference($wo_row, $connection)
```

---

### 2. Update work_order.php (Where WOs are Created)

**Location**: [work_order.php](work_order.php)

**Change 1**: When creating a new work order (around line 408)

**Before**:
```php
$sql = "INSERT INTO work_orders 
        (pm_id, descriptive_text, requestor, ..., tenant_id) 
        VALUES 
        (" . implode(', ', $values) . ", " . (int)($_SESSION['tenant_id'] ?? 1) . ")";
```

**After**:
```php
// Get next work order number for this tenant
$tenant_id = (int)($_SESSION['tenant_id'] ?? 1);
$wo_number = get_next_wo_number($connection, $tenant_id);

$sql = "INSERT INTO work_orders 
        (pm_id, descriptive_text, requestor, ..., tenant_id, wo_number) 
        VALUES 
        (" . implode(', ', $values) . ", {$tenant_id}, {$wo_number})";
```

**Change 2**: CSV import (around line 91)

Apply the same change for CSV imports - get next WO number before inserting.

---

### 3. Update Display Code

**Anywhere you show WO ID to users**, change from wo_id to wo_number

**Example 1**: Dashboard listing

**Before**:
```php
echo "WO #" . $row['wo_id'];  // Shows: WO #8
```

**After**:
```php
echo format_wo_reference($row, $connection);  // Shows: WO #1 (for company 2)
```

**Example 2**: Work order detail page

**Before**:
```php
<h1>Work Order #<?php echo $work_order['wo_id']; ?></h1>
```

**After**:
```php
<h1>Work Order <?php echo format_wo_reference($work_order, $connection); ?></h1>
```

---

### 4. Update All Display Locations

Search for these patterns and update them:

**Pattern 1**: Direct wo_id display
```php
// Find: echo ... $row['wo_id'] or $work_order['wo_id']
// Replace with: format_wo_reference() or get_wo_display_number()
```

**Pattern 2**: WO references in templates
```php
// Find: "WO #" . $wo_id
// Replace with: format_wo_reference($wo_row)
```

**Pattern 3**: Email templates
```php
// Update confirmation emails, notifications to use new format
```

### Files That Likely Need Updates

Based on the codebase, these files likely reference wo_id and should be updated:

```
dashboard.php              - Recent work orders listing
work_order.php            - WO creation, editing, display
work_order_requests.php   - Request references to WOs
analytics_dashboard.php   - WO reports and charts
reports.php               - Any WO reports
email templates           - Confirmation/notification emails
search.php                - WO search results
inventory.php             - WO consumption tracking
```

---

## Critical: Backward Compatibility

### Internal vs. Display

**DO NOT** change the internal `wo_id`:
- ✅ Keep using wo_id internally for database references
- ✅ Keep foreign keys referencing wo_id
- ❌ Do NOT change wo_id values or logic

**DO** change what users see:
- ✅ Show wo_number to users everywhere
- ✅ Use format_wo_reference() for all user displays
- ✅ Update user-facing reports and exports

### Why This Matters

The internal wo_id is still needed for:
- Relationships (work_order_spares, work_order_consumables, etc.)
- Audit trails
- Database queries
- Historical references

---

## Implementation Checklist

### Phase 1: Database ✅
- [x] Add wo_number column
- [x] Create index
- [x] Backfill existing data
- [x] Add helper functions

### Phase 2: Code Updates (🔄 TODO)
- [ ] Update work_order.php INSERT for new WOs
- [ ] Update work_order.php CSV import
- [ ] Update work_order.php display logic
- [ ] Update dashboard.php display
- [ ] Update work_order_requests.php
- [ ] Update analytics_dashboard.php
- [ ] Update all email templates
- [ ] Update search results
- [ ] Test with multiple companies

### Phase 3: Testing (🔄 TODO)
- [ ] Create WO in Company 1 → Should be WO #1
- [ ] Create WO in Company 2 → Should be WO #1 (not inherited)
- [ ] Create WO in Company 1 → Should be WO #2
- [ ] Create WO in Company 3 → Should be WO #1
- [ ] Verify isolation is maintained
- [ ] Test CSV import
- [ ] Test editing existing WOs
- [ ] Test all display pages

### Phase 4: Deployment (🔄 TODO)
- [ ] Backup database
- [ ] Run migration script
- [ ] Deploy updated code
- [ ] Verify existing WOs display correctly
- [ ] Create test WOs to verify numbering
- [ ] Communicate change to users

---

## Implementation Steps

### Step 1: Run Migration (5 minutes)
```bash
cd /path/to/cmms
php migrate_per_company_wo_numbering.php
```

**Output should show**:
```
✅ wo_number column added
✅ Index created
  Tenant 1: Assigned 5 WO numbers (1-5)
  Tenant 31: Assigned 1 WO numbers (1-1)
  Tenant 32: Assigned 1 WO numbers (1-1)
  Tenant 33: Assigned 1 WO numbers (1-1)
✅ Total work orders updated: 8
```

### Step 2: Update work_order.php (20 minutes)

Find the INSERT statement for new work orders and add wo_number:

**Around line 400-415:**
```php
// GET next WO number
$tenant_id = (int)($_SESSION['tenant_id'] ?? 1);
$wo_number = get_next_wo_number($connection, $tenant_id);

// Then in INSERT
$sql = "INSERT INTO work_orders 
        (...existing columns..., tenant_id, wo_number) 
        VALUES 
        (...existing values..., {$tenant_id}, {$wo_number})";
```

### Step 3: Update Display Locations (30 minutes)

Replace all instances of showing wo_id to users:

Find/Replace pattern:
```
Find:    "WO #" . ($row['wo_id'] | $work_order['wo_id'] | $wo_id)
Replace: format_wo_reference($row) 

Find:    $work_order['wo_id']
Replace: get_wo_display_number($connection, $work_order['wo_id'])
```

---

## Example: Creating New WO

### Old Code
```php
$sql = "INSERT INTO work_orders 
        (descriptive_text, equipment, description, tenant_id) 
        VALUES 
        ('Test', 'Pump', 'Fix pump', " . (int)($_SESSION['tenant_id'] ?? 1) . ")";

$connection->query($sql);
$newWoId = get_last_insert_id($connection);
echo "Created WO #" . $newWoId;  // Shows WO #8
```

### New Code
```php
$tenant_id = (int)($_SESSION['tenant_id'] ?? 1);
$wo_number = get_next_wo_number($connection, $tenant_id);

$sql = "INSERT INTO work_orders 
        (descriptive_text, equipment, description, tenant_id, wo_number) 
        VALUES 
        ('Test', 'Pump', 'Fix pump', {$tenant_id}, {$wo_number})";

$connection->query($sql);
$newWoId = get_last_insert_id($connection);
echo "Created " . get_wo_display_number($connection, $newWoId);  // Shows WO #2 for Company 2
```

---

## Testing

### Test Case 1: New Company WO Numbering
1. Log in as Company 1 → Create WO → Should be WO #6 (next after existing 5)
2. Log in as Company 2 (dim) → Create WO → Should be WO #2 (next after existing 1)
3. Log in as Company 3 → Create WO → Should be WO #2 (next after existing 1)
4. Log in as Company 1 → Create WO → Should be WO #7

✅ **Expected**: Each company increments their own sequence

### Test Case 2: Multi-User Same Company
1. User A (Company 1) creates WO → WO #6
2. User B (Company 1) creates WO → WO #7
3. User A creates another WO → WO #8

✅ **Expected**: Continuous numbering within company

### Test Case 3: Display Across Pages
1. Create WO as Company 2
2. Check dashboard → Shows WO #2
3. Check work order list → Shows WO #2
4. Check work order detail → Shows WO #2
5. Check email notification → Shows WO #2

✅ **Expected**: Consistent display everywhere

---

## Rollback Plan (If Needed)

If something goes wrong:

```sql
-- Restore global numbering display (show wo_id instead of wo_number)
-- This keeps wo_number in database but reverts display logic

-- All display code reverts to:
-- echo "WO #" . $row['wo_id'];
```

The wo_number column stays in the database but is unused.

---

## Performance Impact

**Database**:
- ✅ Negligible: Simple MAX() query on indexed column
- ✅ Index on (tenant_id, wo_number) ensures <1ms lookup

**Display**:
- ✅ Minimal overhead: Single function call per WO displayed
- ✅ No additional queries needed (wo_number already in row)

---

## FAQ

**Q: Will this break existing code?**
A: Only if code directly depends on wo_id values being 1-8. All functions are additive, not breaking.

**Q: What about historical WO references?**
A: The wo_id stays the same internally. Only the display number changes.

**Q: Can we undo this?**
A: Yes - revert display code to show wo_id instead of wo_number. The wo_number data stays in database.

**Q: What about reports?**
A: Update report code to use get_wo_display_number() instead of wo_id.

---

## Support Files

- ✅ [migrate_per_company_wo_numbering.php](migrate_per_company_wo_numbering.php) - Run this first
- ✅ [wo_numbering_helpers.inc.php](wo_numbering_helpers.inc.php) - Helper functions (already included)
- 📝 This document - Implementation guide

---

## Summary

**This change makes each company independent:**
- Company 1: WO #1, #2, #3, #4, #5...
- Company 2: WO #1, #2, #3...
- Company 3: WO #1, #2, #3...

**No data inheritance, no confusion, each company has their own sequence.**

**Status**: Ready to implement
**Estimated Time**: 1-2 hours for full implementation
**Risk Level**: Low (backwards compatible)
