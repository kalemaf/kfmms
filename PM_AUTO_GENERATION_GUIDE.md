# automated-pm-wo-generation

## Problem You Had

Your PM schedules were showing **"Pending Generation"** status even though they had past-due dates. The system wasn't automatically creating work orders for preventive maintenance tasks that had reached their due dates.

---

## Solution Implemented

I've enhanced your free-CMMS with **THREE automatic and manual work order generation systems** to ensure PMs never get missed:

### 1. **Force Generate Button** (Most Reliable - Use This!)
   - **Where:** Go to **Preventive Maintenance** dashboard (`pm.php`)
   - **What it does:** Shows a red "⚡ Force Generate All Missing WOs Now" button
   - **Result:** Immediately generates ALL missing work orders for past-due PM schedules
   - **Output:** Shows you exactly what was generated with detailed logs

### 2. **Automatic Backup (Pageload Trigger)**
   - **When it runs:** Every time `pm.php` is loaded
   - **What it does:** Silently checks for any overdue PM schedules without work orders
   - **Result:** Auto-generates missing WOs if found
   - **Logs:** All actions are logged to PHP error logs

### 3. **Windows Task Scheduler** (Background Process)
   - **When it runs:** Daily at 2 AM (or whenever you set it)
   - **What it does:** Runs `generate_pm.php` to scan and generate work orders
   - **Result:** Catches any missed PMs even if the dashboard isn't visited

---

## How to Use

### For Your Specific Problem (Overdue PMs with "Pending Generation"):

1. **Go to PM Dashboard:**
   - Open **Preventive Maintenance** from your main menu

2. **Click "⚡ Force Generate All Missing WOs Now" button**
   - It's the BIG RED BUTTON with lightning bolt
   - Don't worry - no harm if you click it multiple times

3. **Watch the results:**
   - A log appears showing exactly what was generated
   - See which schedules got WOs created
   - See if there were any errors

4. **Refresh the page:**
   - Your "Pending Generation" PMs should now show proper WO numbers
   - Status will change from "Not Started" to "Pending"

### For Ongoing Auto-Generation:

The system now has **TWO automatic triggers**, so you should never have this problem again:
- **Auto-run on PM pageload** - triggered whenever you visit the PM dashboard
- **Windows Scheduler** - runs the generator in the background daily

---

## New Files Created

1. **`force_generate_wo.php`**
   - Standalone work order generator
   - Can be called manually via web or from command line
   - Provides detailed logging of what it creates
   - Safe to run multiple times (won't create duplicates)

2. **`pm_generation_diagnostics.php`**
   - **BEST FOR DIAGNOSTICS** - Shows system health in real-time
   - See how many PMs are overdue
   - View all recent generated work orders
   - Get troubleshooting guidance
   - Shows metrics and historical data
   - **Visit this page to understand the health of your PM system**

3. **Enhanced `pm.php`**
   - New force-generate button with real-time results display
   - Improved error handling and logging
   - Better visibility into automatic processes

---

## Troubleshooting

### "Still seeing 'Pending Generation' after clicking Force Generate?"

Check `pm_generation_diagnostics.php` for:
1. Are the PM schedules marked as `active=1`?
2. Do they have a `frequency` set (daily, weekly, monthly, etc.)?
3. Does the `assigned_to` mechanic exist in the database?
4. Check the "Overdue (No WO Yet)" metric

### "Why do some PMs still say 'Not Started'?"

This is expected if:
- The WO was just created today
- The PM hasn't been scheduled yet (unscheduled PM masters)
- The frequency hasn't been set

### "Want to run from Command Line?"

```bash
# Force generate with detailed output
cd "c:\free-cmms 0.04"
php force_generate_wo.php

# Get JSON results
php force_generate_wo.php?json=1

# Test mode (no changes, just show what would happen)
php force_generate_wo.php?test=1
```

### "Want to automate it more?"

Add to Windows Task Scheduler:
```
Program: C:\php\php.exe
Arguments: C:\free-cmms 0.04\force_generate_wo.php
Run every: 15 minutes during business hours
```

Or add to your existing generate_pm.php task to run more frequently.

---

## Key Improvements

✅ **Multiple triggersredundancy** - System will never miss a PM  
✅ **Detailed logging** - Know exactly what's happening  
✅ **Safe to run repeatedly** - Won't create duplicate WOs  
✅ **Manual override** - Force generation when you need it  
✅ **Full diagnostics** - See system health at a glance  
✅ **Error handling** - Gracefully handles database issues  
✅ **No code breaking** - Fully backwards compatible  

---

## Testing Your System

1. **Visit the diagnostics page:**
   - Go to `pm_generation_diagnostics.php`
   - Check "Overdue (No WO Yet)" metric
   - If >0, click "Force Generate All Missing WOs"

2. **Monitor the logs:**
   - Check PHP error logs (`logs/scheduler.log`)
   - Should see entries like: `[PM] auto_update_overdue_pms: Generated X work orders`

3. **Verify the PM dashboard:**
   - Go back to PM dashboard (`pm.php`)
   - "Pending Generation" statuses should now have WO numbers
   - Check "Due Now" metric - should decrease

---

## Questions?

All the new PHP files have detailed comments explaining the code.  
The `pm_generation_diagnostics.php` shows you everything happening in real-time.  
Check `BACKGROUND_SCHEDULING.md` for info on the Windows Scheduler setup.

**The system is now configured to automatically generate work orders. No more manual intervention needed!**
