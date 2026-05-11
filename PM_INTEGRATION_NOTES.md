# PM System Integration - Unified Dashboard

## Problem
PM schedules created in "PM Schedules" list weren't appearing in the "P.M. Dashboard" (pm.php).

## Root Cause  
Two separate PM systems in the database:
1. **Legacy System**: `pm_schedules` → `generate_pm.php` → `pm_instances` (user's schedules)
2. **Professional System**: `pm_masters` → manual creation → `pm_schedule_log` (system data)

Dashboard only queried `pm_schedule_log`, missing legacy `pm_instances`.

## Solution
Updated [pm.php](pm.php#L145-L185) to query **both** systems:

### New Dashboard Query Logic
```php
// Professional PM system - SELECT from pm_schedule_log
// Legacy PM system - SELECT from pm_schedules with GROUP_CONCAT(pm_instances.wo_id)
// Merge results by due_date
// Display in unified table with "Type" column (Prof vs Legacy)
```

### Key Features
- **Unified View**: Both professional and legacy PM work in one table
- **Color Coding**: Professional (light blue) vs Legacy (light green) for easy distinction
- **Duplicate Prevention**: Uses `GROUP_CONCAT(wo_id)` to consolidate multiple instances per schedule
- **Work Order Links**: First WO shows as link with "+N" indicator if multiple exist

### User Workflow
1. User creates PM schedule in "PM Schedules" list
2. `generate_pm.php` runs (manual or scheduled) when due
3. Work orders created and linked in `pm_instances`
4. Dashboard shows schedule with clickable WO link
5. Mechanic completes WO, which updates both `pm_instances` and `pm_schedule_log` (see: save.php completion logic)

### Database Tables Involved
| Table | Purpose | Role |
|-------|---------|------|
| `pm_schedules` | User's PM configurations | Source of truth for legacy system |
| `pm_instances` | Links schedules to work orders | Created by generate_pm.php |
| `pm_schedule_log` | Professional PM records | Dashboard primary query |
| `pm_masters` | Professional PM definitions | Linked to pm_schedule_log |
| `work_orders` | Actual maintenance tasks | Created by both systems |

### Status After Integration
✅ Legacy PM schedules visible in dashboard  
✅ Work order generation working (IDs 98-99 created)  
✅ WO links functional in dashboard  
✅ WO completion syncs both pm_instances and pm_schedule_log  

### Testing
Run `php tools/test_dashboard_query.php` to verify both systems return data.

### Next Steps (Optional)
1. Set up scheduled task to run `generate_pm.php` automatically (Windows Task Scheduler)
2. Add user documentation about the two PM systems
3. Consider consolidating into single PM system in future version
