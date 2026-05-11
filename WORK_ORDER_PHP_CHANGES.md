# EXACT CODE CHANGES FOR work_order.php

## Location 1: CSV Import (Lines 91-96)

### BEFORE:
```php
$sql = "INSERT INTO work_orders (descriptive_text, requestor, equipment, description, priority, wo_status, submit_date, mechanic_id, est_hours, needed_date, updated, tenant_id) VALUES ('{$descriptive_text}', '{$requestor}', '{$equipment}', '{$description}', {$priority}, '{$wo_status}', '{$submit_date}', " . ($mechanic_id ? $mechanic_id : 'NULL') . ", " . ($est_hours ? $est_hours : 'NULL') . ", " . ($needed_date ? "'{$needed_date}'" : 'NULL') . ", NOW(), " . (int)($_SESSION['tenant_id'] ?? 1) . ")";
if ($connection->query($sql)) { $imported++; }
```

### AFTER:
```php
$tenant_id = (int)($_SESSION['tenant_id'] ?? 1);
$wo_number = get_next_wo_number($connection, $tenant_id);

$sql = "INSERT INTO work_orders (descriptive_text, requestor, equipment, description, priority, wo_status, submit_date, mechanic_id, est_hours, needed_date, updated, tenant_id, wo_number) VALUES ('{$descriptive_text}', '{$requestor}', '{$equipment}', '{$description}', {$priority}, '{$wo_status}', '{$submit_date}', " . ($mechanic_id ? $mechanic_id : 'NULL') . ", " . ($est_hours ? $est_hours : 'NULL') . ", " . ($needed_date ? "'{$needed_date}'" : 'NULL') . ", NOW(), {$tenant_id}, {$wo_number})";
if ($connection->query($sql)) { $imported++; }
```

**Changes**:
- Add `$tenant_id = (int)($_SESSION['tenant_id'] ?? 1);`
- Add `$wo_number = get_next_wo_number($connection, $tenant_id);`
- Add `wo_number` to column list in INSERT
- Add `{$wo_number}` to VALUES list
- Change last value from `(int)($_SESSION['tenant_id'] ?? 1)` to `{$tenant_id}`

---

## Location 2: Main Form Submission (Lines 408-411)

### BEFORE:
```php
$sql = "INSERT INTO work_orders 
        (pm_id, descriptive_text, requestor, equipment, description, priority, wo_status, submit_date, mechanic_id, est_hours, act_hours, account, complete_date, coordinating_instructions, needed_date, inspected_by, approval, action, maintenance_type, failure_mode, audit_item, sla_due_date, down_time_hours, response_time, resolution_time, updated, tenant_id) 
        VALUES 
        (" . implode(', ', $values) . ", " . (int)($_SESSION['tenant_id'] ?? 1) . ")";
if ($connection->query($sql)) {
    $newWoId = get_last_insert_id($connection);
```

### AFTER:
```php
$tenant_id = (int)($_SESSION['tenant_id'] ?? 1);
$wo_number = get_next_wo_number($connection, $tenant_id);

$sql = "INSERT INTO work_orders 
        (pm_id, descriptive_text, requestor, equipment, description, priority, wo_status, submit_date, mechanic_id, est_hours, act_hours, account, complete_date, coordinating_instructions, needed_date, inspected_by, approval, action, maintenance_type, failure_mode, audit_item, sla_due_date, down_time_hours, response_time, resolution_time, updated, tenant_id, wo_number) 
        VALUES 
        (" . implode(', ', $values) . ", {$tenant_id}, {$wo_number})";
if ($connection->query($sql)) {
    $newWoId = get_last_insert_id($connection);
```

**Changes**:
- Add `$tenant_id = (int)($_SESSION['tenant_id'] ?? 1);` before the INSERT
- Add `$wo_number = get_next_wo_number($connection, $tenant_id);` before the INSERT
- Add `wo_number` to the column list
- Change last VALUES parameter from `" . (int)($_SESSION['tenant_id'] ?? 1) . "` to `{$tenant_id}`
- Add `{$wo_number}` at the end of VALUES

---

## Location 3: Search/Display (Finding wo_id references)

Search for display of work order IDs and update as follows:

### Example 1 - Dashboard/List View:

**BEFORE**:
```php
echo "<tr><td>WO #" . $row['wo_id'] . "</td>...";
```

**AFTER**:
```php
echo "<tr><td>" . format_wo_reference($row, $connection) . "</td>...";
```

### Example 2 - Work Order Detail Page:

**BEFORE**:
```php
<h1>Work Order #<?php echo $work_order['wo_id']; ?></h1>
```

**AFTER**:
```php
<h1>Work Order <?php echo format_wo_reference($work_order, $connection); ?></h1>
```

### Example 3 - Inline wo_id display:

**BEFORE**:
```php
$message = "Created WO #" . $newWoId;
```

**AFTER**:
```php
$message = "Created " . get_wo_display_number($connection, $newWoId);
```

---

## Quick Implementation Script

Run this to automatically make the changes:

```bash
# Make backups first
cp work_order.php work_order.php.backup

# The changes are straightforward:
# 1. Find line 94-96 (CSV import)
# 2. Replace with the AFTER code above
# 3. Find line 408-411 (main INSERT)
# 4. Replace with the AFTER code above
# 5. Search for wo_id display and replace with format_wo_reference()
```

---

## Verification

After making changes, test:

```php
// Test 1: CSV import creates WO #1 for new company
// Upload CSV to company with no WOs

// Test 2: Form submission creates WO #1 for new company
// Create WO via form in company with no WOs

// Test 3: Sequential numbering
// Create multiple WOs in same company, verify numbers 1, 2, 3...

// Test 4: Independent numbering
// Create WO in Company 1 (WO #6)
// Create WO in Company 2 (WO #1)
// Create WO in Company 1 (WO #7)
// Verify each company maintains own sequence
```

---

## Files to Update After work_order.php

1. **dashboard.php** - Update recent WO display
2. **work_order_requests.php** - Update request references
3. **analytics_dashboard.php** - Update all WO references
4. **Email templates** - Update confirmation emails
5. **search.php** - Update search results display
6. **Any reports** - Update WO number references

---

## Testing Checklist

- [ ] Run migration script successfully
- [ ] Make code changes to work_order.php
- [ ] Test creating new WO → shows correct wo_number
- [ ] Test CSV import → shows correct wo_number
- [ ] Test multiple companies → each has own sequence
- [ ] Test display pages → show wo_number, not wo_id
- [ ] Test database → wo_number populated correctly

---

## Rollback

If you need to undo:

```bash
cp work_order.php.backup work_order.php
# Then revert display code to show wo_id
```

The database changes (wo_number column) are permanent but harmless if unused.
