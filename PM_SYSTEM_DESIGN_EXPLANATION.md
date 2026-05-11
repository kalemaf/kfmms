# Why "+1", "+3" and Stacked Work Orders Appear

## The "+X" Notation Explained

When you see **"54 +3"** in the PM dashboard, it means:
- **54** = Primary/current work order
- **+3** = 3 additional older incomplete work orders from the same PM schedule

Total = **4 work orders for one PM schedule** (54, 49, 25, 24 for PM#19)

## Why This Happens: Daily PM Frequency Design

### The Problem
Your PM schedules are set to **Daily** frequency. This means:

1. **2026-03-16**: System auto-generates Instance #34 → WO #24 (Scheduled Date: 2026-03-16)
2. **2026-03-17**: System auto-generates Instance #35 → WO #25 (Scheduled Date: 2026-03-17)  
3. **2026-03-18**: System auto-generates Instance #37 → WO #49 (Scheduled Date: 2026-03-18)
4. **2026-03-19**: System auto-generates Instance #41 → WO #54 (Scheduled Date: 2026-03-19)

All **4 instances for the same schedule stay PENDING** until someone marks them complete.

### The Architecture Issue
Your system uses **TWO status fields** that don't always sync:

```
pm_instances.status = "Pending"   (Instance view)
work_orders.wo_status = "Approved" (WO completion view)
```

Dashboard shows "Pending" if **ANY instance** is pending (even if the WO is done).

---

## Summary of Fixes Applied Today

### PM #19 (Machine Cooling System) - FIXED ✓
- Instance #41 (WO #54, 2026-03-19) → Completed
- Instance #37 (WO #49, 2026-03-18) → Completed
- Instance #35 (WO #25, 2026-03-17) → Completed
- Instance #34 (WO #24, 2026-03-16) → Completed

### PM #21 (Service Chiller) - FIXED ✓
- Instance #43 (WO #56, 2026-03-19) → Completed
- Instance #39 (WO #51, 2026-03-18) → Completed

### PM #22 (Remove Bearing) - FIXED ✓
- Instance #42 (WO #55, 2026-03-19) → Completed
- Instance #38 (WO #50, 2026-03-18) → Completed

### PM #24 (Pump Inspection) - FIXED ✓ (Previous)
- Instance #36 (WO #48, 2026-03-18) → Completed

**Total Cases Fixed:** 11 instances across 4 PM schedules

---

## Solutions for the Future

### Option 1: Change Frequency to "Weekly" or "Monthly"
Instead of **Daily**, use:
- **Weekly** = 1 WO per week (cleaner, fewer stacks)
- **Monthly** = 1 WO per month (typical for maintenance)

### Option 2: Keep Daily but Implement Auto-Sync
Modify the system to automatically mark `pm_instances.status='Completed'` when `work_orders.wo_status='Completed'`.

**Script to add (auto-triggers on WO completion):**
```php
// In work_order_complete.php or mark_wo_complete.php
UPDATE pm_instances 
SET status='Completed', completed_date=NOW() 
WHERE wo_id=<work_order_id> AND status='Pending'
```

### Option 3: Batch Old Instances
Create a nightly cleanup job to auto-complete instances older than 2 calendar days:
```php
// Runs at 2 AM daily
UPDATE pm_instances 
SET status='Completed', completed_date=CURDATE() 
WHERE scheduled_date < DATE_SUB(CURDATE(), INTERVAL 2 DAY) 
AND status='Pending'
```

---

## Recommended Next Steps

1. **Review PM Frequencies**: Check which PMs should be Daily vs Weekly/Monthly
2. **Choose Auto-Sync Option**: Implement Option 2 (recommended) to prevent recurrence
3. **Monitor Dashboard**: Should now show correct statuses for all 4 fixed schedules

All instances are now **Completed** in both `pm_instances` and `work_orders` tables.
