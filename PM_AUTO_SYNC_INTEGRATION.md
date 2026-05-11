# INTEGRATION GUIDE: PM Auto-Sync System

## Overview
To prevent PM schedules from showing "Pending" when work is completed, you must integrate the auto-sync function whenever a work order is marked as complete.

## Step 1: Identify Your WO Completion Files
Search for files that update `work_orders.wo_status` to "Completed":

```bash
grep -r "wo_status.*=.*Completed" . --include="*.php"
grep -r "complete_date" . --include="*.php"  
grep -r "mark.*complete" . --include="*.php"
```

**Common file names to check:**
- `complete_work_order.php`
- `mark_wo_complete.php`
- `edit_work_order.php`
- `work_order.php`
- `update_wo_status.php`

## Step 2: Add the Auto-Sync Function Call

Once you find the file that marks WOs as complete, add this code right AFTER the work order status update:

### Example 1: If you have a dedicated completion function
```php
<?php
// In complete_work_order.php or similar

require_once 'config.inc.php';
require_once 'pm_auto_sync_on_wo_complete.php';  // ADD THIS

// ... your existing code ...

// Mark work order as complete
$update = "UPDATE work_orders SET wo_status='Completed', complete_date=NOW() WHERE id=" . (int)$wo_id;
mysqli_query($c, $update);

// ADD THIS: Auto-sync PM instances
$sync_result = pm_auto_sync_on_wo_complete($c, $wo_id);
if ($sync_result['synced_instances'] > 0) {
    error_log("PM Instance sync: " . $sync_result['synced_instances'] . " instance(s) updated for WO#" . $wo_id);
}

// ... rest of your code ...
?>
```

### Example 2: If WO completion is in a larger update statement
```php
<?php
// In work_order.php or similar (edit/update page)

require_once 'config.inc.php';
require_once 'pm_auto_sync_on_wo_complete.php';  // ADD THIS

// ... your form processing code ...

if ($_POST['action'] === 'complete') {
    $wo_id = $_POST['wo_id'];
    
    // Your existing completion logic
    $update = "UPDATE work_orders SET wo_status='Completed', complete_date=NOW() WHERE id=" . (int)$wo_id;
    
    if (mysqli_query($c, $update)) {
        // ADD THIS: Auto-sync immediately
        pm_auto_sync_on_wo_complete($c, $wo_id);
        
        $_SESSION['message'] = "Work order completed and PM schedule updated.";
    }
}
?>
```

### Example 3: If using AJAX/API endpoint
```php
<?php
// In api/complete_wo.php or similar

require_once '../config.inc.php';
require_once '../pm_auto_sync_on_wo_complete.php';  // ADD THIS

header('Content-Type: application/json');

$wo_id = $_POST['wo_id'] ?? $_GET['wo_id'];

if (!$wo_id) {
    echo json_encode(['success' => false, 'error' => 'No WO ID provided']);
    exit;
}

// Mark as complete
$update = "UPDATE work_orders SET wo_status='Completed', complete_date=NOW() 
           WHERE id=" . (int)$wo_id;

if (mysqli_query($c, $update)) {
    // ADD THIS: Auto-sync and return result
    $sync = pm_auto_sync_on_wo_complete($c, $wo_id);
    
    echo json_encode([
        'success' => true,
        'message' => 'Work order completed',
        'pm_instances_synced' => $sync['synced_instances']
    ]);
} else {
    echo json_encode(['success' => false, 'error' => mysqli_error($c)]);
}
?>
```

## Step 3: Test the Integration

### CLI Test
```bash
php pm_auto_sync_on_wo_complete.php 54
```
Output should show:
```
=== PM Instance Auto-Sync Result ===
WO ID: 54
Synced Instances: 1
✓ PM Schedule status updated.
```

### Test in Your Application
1. Create a test work order
2. Mark it as "Completed" through your normal UI
3. Check PM dashboard - should now show "Completed" immediately (not "Pending")

## Step 4: Verify Existing Installations

Check if any existing WO files have incomplete PM synchronization:

```bash
# Find all files that update wo_status
grep -l "wo_status.*Completed" *.php | head -10
```

Then use this to find which ones are missing the auto-sync:

```bash
grep -l "pm_auto_sync_on_wo_complete" $(grep -l "wo_status.*Completed" *.php)
```

Files in the first list but NOT in the second list need integration.

---

## Alternative: Database Trigger (Advanced)

Instead of PHP integration, you can use a MySQL trigger to auto-sync whenever a WO is updated:

```sql
CREATE TRIGGER pm_instance_sync_on_wo_complete
AFTER UPDATE ON work_orders
FOR EACH ROW
BEGIN
  IF NEW.wo_status = 'Completed' AND OLD.wo_status != 'Completed' THEN
    UPDATE pm_instances 
    SET status='Completed', completed_date=NEW.complete_date 
    WHERE wo_id=NEW.wo_id AND status='Pending';
  END IF;
END;
```

**Advantages:** Automatic, no code changes needed  
**Disadvantages:** MySQL-specific, requires database access, harder to debug

---

## Summary of Your System

You now have THREE layers of PM automation:

1. **Auto-Generation** (force_generate_wo.php) - Creates missing WOs
2. **Auto-Sync** (pm_auto_sync_on_wo_complete.php) - Updates PM status when WOs complete ← NEW
3. **Manual Cleanup** (fix_pm_instance.php) - Fix old stuck instances (if needed)

This ensures PM schedules always show the correct status.
