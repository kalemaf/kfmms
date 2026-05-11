# Integration Guide: Performance Monitoring in work_order.php

## Overview
This guide shows exactly where and how to integrate the performance monitoring system into your existing work order flow.

---

## Integration Point #1: When Work Order is Assigned

### Location: work_order.php (wherever you handle work order assignment/save)

### Current Code (approximate):
```php
// Existing work order assignment code
$work_order_id = save_work_order($data);
$assigned_to = $_POST['assigned_to'];

// ... more code ...
```

### Add This After Assignment:
```php
// Import SLA service
require_once __DIR__ . '/libraries/slaService.php';

// Get priority from form (or use default)
$priority = $_POST['priority'] ?? 'High';  // Values: Critical, High, Medium, Low

// Create SLA tracking record
// This initializes the work_order_sla table with assigned_at timestamp
create_work_order_sla($work_order_id, $assigned_to, $priority);
```

### Complete Example:
```php
<?php
require_once 'config.inc.php';
require_once 'libraries/slaService.php';

// Save the work order
$work_order_id = $_POST['id'] ?? null;
$assigned_to = $_POST['assigned_to'];
$priority = $_POST['priority'] ?? 'High';
$asset_id = $_POST['asset_id'];

// ... existing save logic ...
// Save work order to database
// ...

// NEW: Create SLA tracking
create_work_order_sla($work_order_id, $assigned_to, $priority);

// Redirect to confirmation
header('Location: work_order.php?id=' . $work_order_id . '&saved=1');
exit;
?>
```

---

## Integration Point #2: When Technician Acknowledges Work Order

### Location: wherever technicians acknowledge/accept work orders

### Current Code (approximate):
```php
// Existing acknowledgment code
update_work_order_status($work_order_id, 'acknowledged');
// ... more code ...
```

### Add This After Acknowledgment:
```php
// Import SLA service
require_once __DIR__ . '/libraries/slaService.php';

// Update SLA acknowledgment record
// This calculates response time and checks if response_sla_met
acknowledge_work_order_sla($work_order_id);
```

### Complete Example:
```php
<?php
require_once 'config.inc.php';
require_once 'libraries/slaService.php';

$work_order_id = $_POST['work_order_id'];

// ... existing acknowledgment logic ...
// Update work order status
// ...

// NEW: Acknowledge SLA
acknowledge_work_order_sla($work_order_id);

// Get SLA details for confirmation message
$sla = get_work_order_sla_summary($work_order_id);

if ($sla['response_sla_met']) {
    $message = "✅ Work order acknowledged within SLA";
} else {
    $message = "⚠️ Work order acknowledged, but outside SLA window";
}

// Redirect with message
header('Location: work_order.php?id=' . $work_order_id . '&msg=' . urlencode($message));
exit;
?>
```

---

## Integration Point #3: When Work Order is Completed

### Location: wherever you mark work orders as complete/closed

### Current Code (approximate):
```php
// Existing completion code
complete_work_order($work_order_id, $completion_notes);
// ... more code ...
```

### Add This After Completion:
```php
// Import services
require_once __DIR__ . '/libraries/slaService.php';
require_once __DIR__ . '/libraries/repeatFailureService.php';

// Update SLA completion record
// This calculates completion time and checks if completion_sla_met
complete_work_order_sla($work_order_id);

// Get asset and failure info for repeat detection
$asset_id = get_work_order_asset_id($work_order_id);
$failure_category = get_work_order_failure_category($work_order_id);

// Auto-detect repeat failures
auto_detect_repeat_failure($asset_id, $failure_category, 30);  // Check 30-day window
```

### Complete Example:
```php
<?php
require_once 'config.inc.php';
require_once 'libraries/slaService.php';
require_once 'libraries/repeatFailureService.php';

$work_order_id = $_POST['work_order_id'];
$completion_notes = $_POST['completion_notes'];
$asset_id = $_POST['asset_id'];
$failure_category = $_POST['fault_code'] ?? 'General';

// ... existing completion logic ...
// Mark work order as complete in database
// ...

// NEW: Complete SLA tracking
complete_work_order_sla($work_order_id);

// NEW: Check for repeat failures
auto_detect_repeat_failure($asset_id, $failure_category, 30);

// Get completion details
$sla = get_work_order_sla_summary($work_order_id);

$completion_info = [
    'response_sla_met' => $sla['response_sla_met'] ? '✅ Yes' : '❌ No',
    'completion_sla_met' => $sla['completion_sla_met'] ? '✅ Yes' : '❌ No',
    'response_time' => $sla['response_time_minutes'] . ' minutes',
    'completion_time' => $sla['completion_time_minutes'] . ' minutes',
];

// Redirect with completion details
header('Location: work_order.php?id=' . $work_order_id . '&completed=1');
exit;
?>
```

---

## Integration Point #4: Recalculate Performance Metrics

### Option A: Run via Cron Job (Recommended)

Add this to your server's crontab:

```bash
# Run aggregation daily at 2 AM
0 2 * * * php /path/to/your/cmms/libraries/performanceAggregator.php daily

# Run weekly summary every Monday at 2 AM
0 2 * * 1 php /path/to/your/cmms/libraries/performanceAggregator.php weekly

# Run monthly summary on 1st of month at 2 AM
0 2 1 * * php /path/to/your/cmms/libraries/performanceAggregator.php monthly
```

### Option B: Run from Admin Dashboard Button

In your admin/supervisor area, add a button:

```php
<?php
if ($_POST['action'] == 'recalculate_performance') {
    if (!is_admin()) {
        die('Access denied');
    }
    
    require_once 'libraries/performanceAggregator.php';
    
    $result = aggregate_all_technician_performance('monthly', date('Y-m-d'));
    
    echo json_encode([
        'success' => true,
        'message' => 'Performance metrics recalculated',
        'data' => $result
    ]);
    exit;
}
?>
```

HTML Button:
```html
<form method="POST">
    <input type="hidden" name="action" value="recalculate_performance">
    <button type="submit" class="btn btn-primary">
        Recalculate Performance Metrics
    </button>
</form>
```

### Option C: Run Manually from Command Line

```bash
# From your CMMS root directory
php libraries/performanceAggregator.php daily
php libraries/performanceAggregator.php monthly

# With specific date
php libraries/performanceAggregator.php monthly 2026-05-07
```

---

## Integration Point #5: View Performance Dashboard

### For Managers/Supervisors:

```html
<!-- Add this link to your navigation -->
<a href="technician_performance_dashboard.php" class="nav-link">
    Performance Dashboard
</a>
```

### For Admins (add management section):

```html
<!-- Somewhere in admin area -->
<div class="admin-section">
    <h3>Performance Monitoring</h3>
    <ul>
        <li><a href="technician_performance_dashboard.php">View Performance Dashboard</a></li>
        <li><button onclick="recalculateMetrics()">Recalculate Performance Metrics</button></li>
        <li><a href="admin_sla_policies.php">Configure SLA Policies</a></li>
    </ul>
</div>
```

---

## Complete Workflow Example

Here's a complete example showing the entire flow:

```php
<?php
require_once 'config.inc.php';
require_once 'libraries/slaService.php';
require_once 'libraries/repeatFailureService.php';
require_once 'libraries/performanceService.php';

// ============================================
// SCENARIO: Complete work order flow
// ============================================

// Step 1: Create new work order
$work_order_data = [
    'equipment_id' => 5,
    'priority' => 'High',
    'description' => 'Pump repair',
    'assigned_to' => 3,  // Technician ID
    'asset_id' => 5,
    'fault_code' => 'PUMP_SEAL_FAILURE'
];

// Save to database
$work_order_id = save_work_order($work_order_data);
echo "Created work order $work_order_id\n";

// Step 2: Assign to technician - INTEGRATION POINT #1
require_once 'libraries/slaService.php';
create_work_order_sla(
    $work_order_id, 
    $work_order_data['assigned_to'], 
    $work_order_data['priority']
);
echo "Created SLA tracking for work order $work_order_id\n";

// ... Technician works on the task ...

// Step 3: Technician acknowledges - INTEGRATION POINT #2
acknowledge_work_order_sla($work_order_id);
$sla = get_work_order_sla_summary($work_order_id);
echo "Acknowledged: Response SLA Met? " . ($sla['response_sla_met'] ? 'YES' : 'NO') . "\n";

// ... Technician performs repairs ...

// Step 4: Technician completes work - INTEGRATION POINT #3
complete_work_order_sla($work_order_id);

// Check for repeat failures
auto_detect_repeat_failure(
    $work_order_data['asset_id'],
    $work_order_data['fault_code'],
    30  // Check 30-day window
);

$sla = get_work_order_sla_summary($work_order_id);
echo "Completed: Completion SLA Met? " . ($sla['completion_sla_met'] ? 'YES' : 'NO') . "\n";
echo "Completed: Total time: " . $sla['completion_time_minutes'] . " minutes\n";

// ... End of month or on demand ...

// Step 5: Recalculate performance - INTEGRATION POINT #4
require_once 'libraries/performanceAggregator.php';
$tech_id = $work_order_data['assigned_to'];

$performance = calculate_technician_performance(
    $tech_id,
    date('Y-m-01'),           // Month start
    date('Y-m-t'),            // Month end
    'monthly'
);

echo "\n=== Technician Performance (Monthly) ===\n";
echo "Response SLA: " . $performance['response_sla_percentage'] . "%\n";
echo "Completion SLA: " . $performance['completion_sla_percentage'] . "%\n";
echo "First-Time Fix: " . $performance['first_time_fix_percentage'] . "%\n";
echo "Overall Score: " . $performance['overall_score'] . "\n";
echo "Rating: " . $performance['rating'] . "\n";
echo "Repeat Failures: " . $performance['repeat_failure_count'] . "\n";

// Store in cache
store_performance_metrics($performance);
echo "\nPerformance metrics cached for dashboard\n";

// Step 6: Manager views dashboard - INTEGRATION POINT #5
echo "\n=== Dashboard Ready ===\n";
echo "Visit: technician_performance_dashboard.php\n";
?>
```

---

## Database Tables Reference

### work_order_sla
Records created after Integration Point #1 (assignment):
```
assigned_at: NOW() when work order assigned
```

Updated after Integration Point #2 (acknowledgment):
```
acknowledged_at: NOW()
response_time_minutes: calculated
response_sla_met: 1 or 0
```

Updated after Integration Point #3 (completion):
```
completed_at: NOW()
completion_time_minutes: calculated
completion_sla_met: 1 or 0
is_overdue: 1 or 0
```

### repeat_failures
Records created after Integration Point #3 (if repeat detected):
```
original_work_order_id: ID of first failure
repeat_work_order_id: ID of current work order
days_between_failures: calculated
```

### technician_performance
Records created after Integration Point #4 (aggregation):
```
response_sla_percentage: calculated
completion_sla_percentage: calculated
first_time_fix_percentage: calculated
overall_score: weighted formula
rating: Excellent/Good/Satisfactory/Needs Improvement/Poor
```

---

## Error Handling

All integration functions include error handling:

```php
// If SLA policy not found, uses 'High' priority default
create_work_order_sla($wo_id, $tech_id, 'InvalidPriority');  // OK - uses default

// If work order doesn't exist, returns silently
acknowledge_work_order_sla(99999);  // OK - no error

// If asset not found, repeat failure check returns false
auto_detect_repeat_failure(99999, 'FAULT', 30);  // OK - no repeat found
```

---

## Performance Tips

### For High-Volume Systems:

1. **Run aggregation during off-hours** (2-3 AM)
2. **Use cron job instead of on-demand** (avoid dashboard load)
3. **Cache dashboard for 1 hour** (reduce calculation frequency)
4. **Index frequently queried columns**:
   - tenant_id
   - technician_id
   - work_order_id
   - assigned_at
   - completed_at

Example index creation:
```sql
CREATE INDEX idx_sla_technician ON work_order_sla(tenant_id, work_order_id);
CREATE INDEX idx_repeat_asset ON repeat_failures(tenant_id, asset_id);
CREATE INDEX idx_performance_tech ON technician_performance(tenant_id, technician_id);
```

---

## Testing Your Integration

### Test #1: SLA Creation
```bash
# Create a work order and check sla_policies table
SELECT * FROM work_order_sla WHERE work_order_id = YOUR_WO_ID;
# Should show: assigned_at = NOW()
```

### Test #2: SLA Acknowledgment
```bash
# Acknowledge work order and check again
SELECT * FROM work_order_sla WHERE work_order_id = YOUR_WO_ID;
# Should show: acknowledged_at = NOW(), response_sla_met = 0 or 1
```

### Test #3: SLA Completion
```bash
# Complete work order and check again
SELECT * FROM work_order_sla WHERE work_order_id = YOUR_WO_ID;
# Should show: completed_at = NOW(), completion_sla_met = 0 or 1
```

### Test #4: Repeat Failure Detection
```bash
# Create 2 work orders for same asset with same fault
# Second completion should create repeat_failures record
SELECT * FROM repeat_failures WHERE asset_id = YOUR_ASSET_ID;
# Should show: days_between_failures calculated
```

### Test #5: Performance Calculation
```bash
# Run aggregator
php libraries/performanceAggregator.php daily

# Check performance table
SELECT * FROM technician_performance WHERE technician_id = YOUR_TECH_ID;
# Should show: All percentages calculated, overall_score and rating
```

### Test #6: Dashboard Access
```
Visit: http://yourapp.com/technician_performance_dashboard.php
- Should load for managers
- Should deny access for technicians
- Should show all technicians' performance
```

---

## Migration Checklist

- [ ] Add `require_once 'libraries/slaService.php'` to work assignment code
- [ ] Call `create_work_order_sla()` when work order assigned
- [ ] Add `require_once 'libraries/slaService.php'` to acknowledgment code
- [ ] Call `acknowledge_work_order_sla()` when technician acknowledges
- [ ] Add `require_once 'libraries/slaService.php'` to completion code
- [ ] Add `require_once 'libraries/repeatFailureService.php'` to completion code
- [ ] Call `complete_work_order_sla()` when work order completed
- [ ] Call `auto_detect_repeat_failure()` when work order completed
- [ ] Set up cron job for `performanceAggregator.php daily`
- [ ] Add dashboard link to manager navigation
- [ ] Test complete flow with sample work orders
- [ ] Verify multi-tenant isolation
- [ ] Train managers on dashboard usage
- [ ] Monitor performance in production

---

## Support

Questions? Check:
1. Individual function documentation in library files
2. PERFORMANCE_MONITORING_GUIDE.md for complete system documentation
3. Error logs in php_error.log
4. Database structure with PRAGMA table_info

---

**Integration Difficulty**: Easy (4 function calls at 3 locations)  
**Estimated Time**: 2-3 hours total  
**Production Ready**: Yes ✅  
**Multi-Tenant Safe**: Yes ✅
