# Independent PM Instance System

## What Changed

Previously, the PM dashboard showed work orders grouped by schedule:
```
PM#19 machine cooling syr    54 +3 Pending
```
This meant "WO #54 and 3 others" but didn't show them separately, making it impossible to:
- Generate WOs for individual instances
- Mark individual instances complete
- Track each PM instance independently

## New System: Complete Independence

Now each PM instance appears on its own row:
```
PM#19  machine cooling syr  Instance #41  2026-03-19  WO #54  PENDING  [View WO] [✓ Mark Done]
PM#19  machine cooling syr  Instance #37  2026-03-18  WO #49  PENDING  [View WO] [✓ Mark Done]
PM#19  machine cooling syr  Instance #35  2026-03-17  WO #25  PENDING  [View WO] [✓ Mark Done]
PM#19  machine cooling syr  Instance #34  2026-03-16  WO #24  PENDING  [View WO] [✓ Mark Done]
```

Each instance can be managed **completely independently**.

---

## Why This Matters

### Example: Daily PM Schedule
Your "Machine Cooling System" PM runs **every day**:

| Date | Instance ID | Scheduled Date | WO ID | Status |
|------|-------------|-----------------|--------|--------|
| Day 1 | #34 | 2026-03-16 | #24 | Completed ✓ |
| Day 2 | #35 | 2026-03-17 | #25 | Completed ✓ |
| Day 3 | #37 | 2026-03-18 | #49 | Completed ✓ |
| Day 4 | #41 | 2026-03-19 | #54 | Completed ✓ |

**Each instance must be completed independently.**

In the old system, if Instance #34 wasn't marked complete, the entire PM schedule showed "Pending" even though Days 2-4 were done. Now you see exactly which one is pending.

---

## Files & Their Purpose

### 1. **pm_independent_view.php** (MAIN DASHBOARD)
- Shows each PM instance on its own row
- No "+X" grouping
- Buttons to view WO, generate WO, or mark complete
- Statistics showing pending/completed count

**Access:** http://your-server/pm_independent_view.php

**Features:**
- Independent status tracking
- Per-instance generation
- Per-instance completion
- Overdue highlighting (red background)
- Separate metrics for each instance

### 2. **pm_instance_api.php** (API Handler)
- Backend API for independent instance operations
- Three actions: `get`, `generate`, `complete`

**Endpoints:**
```bash
# Get instance details
GET pm_instance_api.php?action=get&instance_id=41

# Generate WO for this instance only
POST pm_instance_api.php?action=generate&instance_id=41

# Mark this instance complete
POST pm_instance_api.php?action=complete&instance_id=41
```

### 3. **force_generate_wo_independent.php** (Batch Generation)
- Generates WOs for ALL pending instances
- Shows each instance separately in logs
- Processes instances independently (one per instance, not per schedule)

**Usage:**
```bash
php force_generate_wo_independent.php
```

**Output:** JSON with detailed per-instance generation results

### 4. **pm_auto_sync_on_wo_complete.php** (Auto-Sync)
- Syncs pm_instances status when WO is marked complete
- Prevents "+1" grouping issues at the root

---

## How to Use

### Method 1: Web Dashboard (Recommended)

1. **Open Dashboard:**
   - Go to http://your-server/pm_independent_view.php
   
2. **View All Instances:**
   - See each PM instance on its own row
   - No grouping or "+X" notation

3. **Generate Missing WOs:**
   - Click "⚡ Force Generate All Missing WOs Now" button
   - OR click "Generate WO" button next to each instance

4. **Complete Individual Instances:**
   - Click "✓ Mark Done" next to any pending instance
   - Instance is marked complete independently
   - Status updates immediately

### Method 2: CLI Batch Generation

```bash
cd c:\free-cmms\ 0.04
php force_generate_wo_independent.php
```

Output shows each instance:
```
Instance #41: Schedule 'machine cooling syr' → Generated WO #54 ✓
Instance #37: Schedule 'machine cooling syr' → Generated WO #49 ✓
Instance #35: Schedule 'machine cooling syr' → Already has WO #25 - SKIPPED
Instance #34: Schedule 'machine cooling syr' → Already has WO #24 - SKIPPED
...
Generation complete. Generated: 2 WOs
```

### Method 3: API (For Custom Integration)

```javascript
// Complete a single instance
fetch('pm_instance_api.php?action=complete&instance_id=41', {
    method: 'POST'
})
.then(r => r.json())
.then(data => console.log(data));

// Generate WO for instance
fetch('pm_instance_api.php?action=generate&instance_id=41', {
    method: 'POST'
})
.then(r => r.json())
.then(data => console.log('Generated:', data.wo_id));
```

---

## No More "+X" Grouping

### Before (Old System)
```
WO  54 +3  Pending
```
What does "+3" mean? Which WOs? When due? Who did what?

### After (New System)
```
Instance #41  WO 54  2026-03-19  PENDING  [View] [Done]
Instance #37  WO 49  2026-03-18  PENDING  [View] [Done]
Instance #35  WO 25  2026-03-17  PENDING  [View] [Done]
Instance #34  WO 24  2026-03-16  PENDING  [View] [Done]
```
Crystal clear. Each one shows its own due date, WO, and status.

---

## Database Structure (Unchanged)

The database still uses:
- **pm_schedules** - Defines the PM (e.g., "machine cooling system")
- **pm_instances** - Individual instances (one per scheduled occurrence)
- **work_orders** - Work orders linked to instances

**Key relationship:**
```
PM Schedule #19  
  ├─ Instance #34 → WO #24 (2026-03-16)
  ├─ Instance #35 → WO #25 (2026-03-17)
  ├─ Instance #37 → WO #49 (2026-03-18)
  └─ Instance #41 → WO #54 (2026-03-19)
```

Each instance manages its own WO - complete independence.

---

## Integration Checklist

### If You Use the Dashboard

✓ **pm_independent_view.php** - Already integrated, just use it
✓ **pm_instance_api.php** - Already integrated with dashboard

### If You Generate WOs Automatically

✓ Use **force_generate_wo_independent.php** instead of force_generate_wo.php
- Better per-instance tracking
- Clearer logs
- Each instance processed independently

### If You Want Auto-Sync on WO Completion

✓ Integrate **pm_auto_sync_on_wo_complete.php** in your WO completion code:

```php
// In work_order.php or complete_wo.php
require_once 'pm_auto_sync_on_wo_complete.php';

// After marking WO as complete:
pm_auto_sync_on_wo_complete($c, $wo_id);
```

This prevents "+X" issues at the source.

---

## FAQ

**Q: How is this different from the old PM dashboard?**
A: Old = instances grouped by schedule. New = each instance is independent with its own row, WO, status, and controls.

**Q: Can I still view the old dashboard?**
A: Yes. pm.php still exists and works. But pm_independent_view.php is recommended.

**Q: Do I have to mark each instance manually?**
A: No. Click "Force Generate All Missing WOs Now" to generate for all pending instances at once, then mark complete individually.

**Q: What if I want to batch complete multiple instances?**
A: Use the CLI:
```bash
php fix_pm_instance.php 41 && php fix_pm_instance.php 37 && php fix_pm_instance.php 35 && php fix_pm_instance.php 34
```

**Q: Why do daily PMs create multiple instances?**
A: By design - one instance per day ensures you track each occurrence. You can change frequency to Weekly or Monthly in PM settings if desired.

**Q: Can I automate the completion?**
A: Yes - integrate pm_auto_sync_on_wo_complete.php on WO completion. Then instances auto-sync when WOs are marked done.

---

## Summary

| Aspect | Old System | New System |
|--------|-----------|-----------|
| Display | "WO #54 +3 Pending" | Separate rows for each |
| Clarity | Grouped, unclear | Individual, transparent |
| Generation | Per-schedule | Per-instance |
| Completion | Group status | Independent status |
| Management | Limited | Full control |
| Integration | Complex | Simple API |

**Result:** Each PM instance is now a **completely independent unit** that can be generated, viewed, and completed on its own schedule.
