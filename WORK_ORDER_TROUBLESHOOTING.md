# Work Order Status - Troubleshooting Guide

## Problem
You created a work order but it doesn't appear in "Active Work Orders" even though you submitted it successfully.

## Root Cause
When you create a new work order through the form, it starts in **'Pending Approval'** status. The original "Active Work Orders" query only shows items with status **'assigned'**, which requires manager approval AND assignment to a mechanic first.

## Solution

### Option 1: Quick Fix (Recommended)
1. Open this file in your browser: **http://localhost:8000/fix_work_order_queries.php**
2. Log in as a **manager** user
3. Click the file - it will automatically update the database queries
4. Reload your Work Orders page
5. Your work order should now appear!

### Option 2: Manual Database Update
If Option 1 doesn't work, run this SQL command directly in your database:

```sql
UPDATE queries 
SET sql = "SELECT wo.wo_id AS 'WO', wo.priority AS 'Priority', wo.equipment AS 'Equipment', wo.description AS 'Description', wo.wo_status AS 'Status', wo.submit_date AS 'Submit Date', wo.complete_date AS 'Completed Date', m.lname AS 'Mechanic', m.fname, m.id, p.description as 'Priority' FROM work_orders AS wo LEFT JOIN mechanics AS m ON wo.mechanic_id = m.id LEFT JOIN priority AS p ON wo.priority = p.priority WHERE (wo_status = 'assigned' OR wo_status = 'Approved') AND complete_date IS NULL"
WHERE name = 'WO_PENDING';
```

## Work Order Status Workflow

Here's how work orders move through statuses:

```
┌─────────────────────┐
│  Pending Approval   │  ← New work order starts here
│   (After Submit)    │
└──────────┬──────────┘
           │
           ├─ Manager Reviews
           │
           ▼
┌─────────────────────┐
│    Approved         │  ← Manager approved it
│  (Ready to assign)  │
└──────────┬──────────┘
           │
           ├─ Manager Assigns to Mechanic
           │
           ▼
┌─────────────────────┐
│    Assigned         │  ← Mechanic can now work on it
│   (In Progress)     │  ← Shows in "Active Work Orders"
└──────────┬──────────┘
           │
           ├─ Mechanic Completes
           │
           ▼
┌─────────────────────┐
│   Completed         │  ← Work is done
│  (Ready to close)   │  ← Shows in "Recently Closed"
└─────────────────────┘
```

## Key Points

✓ **Your work order exists** - You successfully created it
✓ **It's in the system** - Just needs manager approval
✓ **You can assign it** - If you're a manager, open it and change the status
✓ **It will appear** - Once assigned to a mechanic

## What Appears Where

| View | Shows | Status | Description |
|------|-------|--------|-------------|
| **Active Work Orders** | In-progress work | assigned, Approved | Work being done or ready to start |
| **Pending Assignment** | Awaiting mechanic | Approved, Pending Approval | Work approved but no mechanic assigned |
| **Recently Closed** | Completed work | Completed | Work finished in last 10 items |
| **All Work Orders** | Everything except pending approval | All except Pending Approval | Complete history |

## After Running the Fix

Your "Active Work Orders" view will now show:
- ✓ Work orders in "Approved" status (waiting for mechanic assignment)
- ✓ Work orders in "assigned" status (actively being worked)
- ✗ Work orders in "Pending Approval" status (not yet reviewed by manager)
- ✗ Completed work orders (no complete_date set)

This gives you better visibility into your workflow!

## Still Not Seeing Your Work Order?

If your work order still doesn't appear after running the fix, check:

1. **Are you the right user group?** 
   - "mechanic" can only see work assigned to them
   - "lead" can see their team's work
   - "manager" can see all work

2. **Is the work order actually created?**
   - Go to "All Work Orders" view
   - Search for it by WO number
   - If it's there, the issue is just the status filter

3. **Did you apply through the correct form?**
   - "Submit a Work Order" = Full work order (requires approval)
   - "Submit a Work Request" = Request form (different process)

## Need More Help?

Check your work order details:
1. Click "Work Orders" in navigation
2. Click "Edit #"
3. Enter your work order number
4. Look at the "Status" field
5. Check if you're assigned and what the complete_date shows

The work order system is working correctly - it's just controlling visibility based on workflow status!
