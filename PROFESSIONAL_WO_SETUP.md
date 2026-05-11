# Professional Work Order System - Setup Instructions

## Overview
This upgrade adds **6 professional sections** to the CMMS work order system to capture comprehensive maintenance data needed for reliability analysis (MTBF, MTTR, availability, Pareto analysis).

## Sections Added

1. **Identification & Request** – Asset ID, Name, Location, Department
2. **Description & Technical Details** – Problem, Failure code, Root cause, Work type, Safety, Lockout/Tagout
3. **Assignment & Planning** – Technician, Planned hours, Skills, Estimated cost, Required parts
4. **Execution** – Start/finish times, Downtime, Labor hours, Spare parts, Technician comments
5. **Financial** – Labor cost, Material cost, Total cost, Downtime cost
6. **Reliability Data** – Failure type, Cause code, Component replaced, MTTR impact, Repeat failure

## Database Setup

### Apply Migration (Required)

Run the migration to add 30+ new columns to the `work_orders` table:

**Option 1: From CLI**
```bash
php migrations/add_wo_fields.php
```

**Option 2: Browser (as admin)**
```
http://127.0.0.1:8000/migrations/add_wo_fields.php
```

The script will:
- Check if columns already exist (safe to run multiple times)
- Display progress (+ = added, - = skipped, ! = error)
- Print "Migration complete" when done

### Database Columns Added

| Column | Type | Purpose |
|--------|------|---------|
| asset_id | VARCHAR(128) | Equipment identifier |
| asset_name | VARCHAR(255) | Equipment name |
| location | VARCHAR(255) | Physical location |
| department | VARCHAR(255) | Department responsible |
| problem_description | TEXT | Description of failure |
| failure_code | VARCHAR(128) | Failure classification |
| root_cause | TEXT | Diagnosed root cause |
| work_type | VARCHAR(50) | Corrective/Preventive/Predictive/Inspection/Emergency |
| safety_requirements | TEXT | Safety notes |
| lockout_required | VARCHAR(10) | Yes/No |
| planned_hours | DECIMAL(8,2) | Estimated labor |
| required_skills | VARCHAR(255) | Skills needed |
| estimated_cost | DECIMAL(10,2) | Budget |
| required_parts | TEXT | Parts list |
| actual_start_time | DATETIME | When work began |
| actual_finish_time | DATETIME | When work ended |
| downtime_duration | DECIMAL(8,2) | Equipment downtime in hours |
| spare_parts_consumed | TEXT | Parts actually used |
| labor_cost | DECIMAL(10,2) | Actual labor cost |
| material_cost | DECIMAL(10,2) | Material cost |
| total_cost | DECIMAL(12,2) | Labor + Material |
| downtime_cost | DECIMAL(12,2) | Cost of downtime |
| failure_type | VARCHAR(128) | Failure classification |
| cause_code | VARCHAR(128) | Root cause code |
| component_replaced | VARCHAR(255) | Component changed |
| mttr_impact | VARCHAR(128) | MTTR effect |
| repeat_failure | VARCHAR(10) | Yes/No |
| materials_summary | TEXT | Material summary |
| notes | TEXT | General notes |

## User Interface Changes

### Work Order Form (`work_order.php`)

✅ **Added Full-Width Responsive Container**
- Form spreads across page (not confined to left side)
- Mobile-friendly responsive layout
- 6 professional sections with visual grouping

✅ **New Input Fields** across all 6 sections
- Auto-complete from database when editing existing WO
- Sensible defaults for new WOs
- Proper field types (text, textarea, select, datetime, number)

### Print Work Order (`print_wo.php`)

✅ **Enhanced Print Layout**
- Displays all 6 professional sections
- Clean, professional formatting
- Styled for PDF export / printing

## Implementation Steps

### 1. Apply Database Migration
```bash
cd c:\free-cmms 0.04
php migrations/add_wo_fields.php
```
Expected output:
```
Applying migration: add professional work order fields to work_orders table
+ Added column asset_id
+ Added column asset_name
+ Added column location
...
Migration complete.
```

### 2. Refresh Browser
```
Ctrl+F5 (Windows/Linux)
Cmd+Shift+R (macOS)
```

### 3. Test the New Form
- Open existing work order: `http://127.0.0.1:8000/work_order.php?wo_id=23`
- Create new work order: Menu → Work Orders → Create New
- Fill in fields across all 6 sections
- Click **Save & Close**

### 4. Print a Work Order
- Open work order detail
- Click 🖨️ icon → Opens print preview
- View all 6 professional sections
- Print to paper or save as PDF

## Data Entry Flow

**When Creating a Work Order:**
1. **Section 1** – Asset & Request info (supervisor fills)
2. **Section 2** – Problem description & initial analysis
3. **Section 3** – Assign tech, estimate time & cost (manager fills)
4. **Section 4** – Filled during work execution (technician fills)
5. **Section 5** – Costs calculated (finance/manager fills)
6. **Section 6** – Reliability data (quality/manager fills)

## Backward Compatibility

✅ **All existing work orders remain unchanged**
- Old fields still work
- New fields default to NULL
- No data loss
- Existing reports/queries unaffected
- `save.php` automatically handles new fields

## Troubleshooting

**Issue: Columns not showing in form**
- Run migration: `php migrations/add_wo_fields.php`
- Verify with: `SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_NAME='work_orders'`

**Issue: "Undefined property" errors in print_wo.php**
- Check migration ran successfully
- Field names use null coalescing (`$wo->field ?? ''`) so safe defaults apply

**Issue: Form doesn't save new fields**
- Verify `save.php` processes all `$_REQUEST` variables
- Check MySQL error log for column errors
- Ensure user has INSERT/UPDATE permissions

## Before & After

**BEFORE:**
- Basic work order (description, status, tech, hours)
- No failure analysis
- No cost tracking
- No reliability data

**AFTER:**
- Comprehensive maintenance record:
  - Asset details
  - Failure classification
  - Work type categorization
  - Financial tracking
  - Reliability metrics (MTTR, MTBF, Pareto ready)
- Professional print-outs
- KPI data for analysis dashboards

## Notes for Administrators

- All fields are **optional** (allow NULL) to avoid breaking existing workflows
- New fields designed for **gradual adoption**—teams can start using sections incrementally
- Financial fields can integrate with accounting systems
- Reliability data feeds predictive maintenance algorithms
- Consider training users on which sections they will fill

## Support

For issues or questions:
1. Check that migration completed successfully
2. Review browser console for JavaScript errors
3. Check MySQL error log for SQL issues
4. Verify user permissions for INSERT/UPDATE

---

**Version:** 1.0 (February 2026)
**Status:** Production Ready
