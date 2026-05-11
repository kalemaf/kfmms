# Unified Pending Items Dashboard - User Guide

## Overview

Your Maintenix CMMS now has a **Unified Pending Items Dashboard** - a professional, Excel-like interface that shows all pending Work Orders, Preventive Maintenance tasks, and Purchase Orders in one place.

---

## Quick Start

### 1. **Accessing the Dashboard**

   **Step 1:** Log in to Maintenix as a Manager or Admin
   
   **Step 2:** Click the **"Pending Items"** tab in the main navigation bar
   
   **Step 3:** The dashboard loads automatically

### 2. **What You See**

The dashboard displays:
- **4 Metric Cards** at the top showing counts:
  - Pending Work Orders (blue)
  - Pending PMs (green)
  - Pending Purchase Orders (red)
  - Total Pending Items (purple)

---

## Features Guide

### **Tab Navigation**

The dashboard has 4 tabs:

| Tab | Shows | Use Case |
|-----|-------|----------|
| **All Items** | WO + PM + PO combined, sorted by due date | Get complete overview of all pending work |
| **Work Orders** | Pending WOs only (New, Assigned, In Progress) | Focus on maintenance tasks |
| **PM** | Pending preventive maintenance | Monitor preventive maintenance schedules |
| **Purchase Orders** | Pending POs (Draft, Submitted, Receipt Pending) | Track purchasing activities |

### **Column Sorting**

Click any column header to sort:
- ✓ Sort ascending/descending
- ✓ Sort by ID, description, status, due date, etc.
- ✓ Click again to reverse sort direction

**Example:**
- Click "Due Date" to show most urgent items first
- Click "Status" to group items by status

### **Search & Filter**

Use the **Search box** (top right of table):
1. Type any keyword
2. Results filter in real-time
3. Searches across all columns

**Examples:**
- Type "Motor" to find all items mentioning motor
- Type "Overdue" to find overdue items
- Type "Pump" to find pump-related work

### **Status Indicators**

Each item has a color-coded status badge:

**Work Orders:**
- 📄 **New** (yellow) - Just created, not yet assigned
- 👤 **Assigned** (blue) - Assigned to technician
- ⚙️ **In Progress** (purple) - Currently being worked on

**PM:**
- ⏰ **Pending** (yellow) - Scheduled, waiting to be done
- ⏰ **Due** (red) - Due soon (within 3 days)
- ⏰ **Overdue** (dark) - Past due date

**Purchase Orders:**
- 📦 **Draft** (grey) - Not yet submitted
- 📦 **Submitted** (blue) - Sent to vendor
- 📦 **Pending Receipt** (yellow) - Waiting for delivery

### **Due Date Information**

The "Due Date" column shows:
- **Date** in MM/DD/YYYY format
- **Days remaining** in parentheses (days until due)
- **Red text with "Overdue"** if past due date
- **Highlight colors:**
  - Red = Overdue
  - Orange = Due today or within 24 hours
  - Blue = Due soon (3+ days away)

**Examples:**
- `03/10/2026 (2 days)` = Due in 2 days
- `03/05/2026 (Overdue)` = Past due date (RED alert)

### **Quick Action Buttons**

Each row has action buttons:

| Button | Action |
|--------|--------|
| **👁️ View** | Opens the item in view-only mode |
| **✏️ Edit** | Opens the item for editing |

These buttons route to the correct application:
- Work Orders → work_order.php
- PMs → pm_professional.php
- Purchase Orders → purchase_order.php

### **Export Options**

Export the current view:

1. **📥 Export CSV**
   - Download as .csv file (opens in Excel)
   - All columns exported
   - Use for: External analysis, email reports

2. **📊 Export Excel**
   - Download as .xls file (Excel format)
   - Professional formatting
   - Ready to print or share

3. **🖨️ Print**
   - Opens browser print dialog
   - Optimized for A4 paper
   - Hides UI elements, shows only data

4. **🔄 Refresh**
   - Reloads latest data from database
   - Use after making changes elsewhere

**File Naming:**
All exports include:
- View type (all, work_orders, pm, po)
- Current date (YYYY-MM-DD)
- Example: `pending_items_all_2026-03-08.csv`

---

## Common Tasks

### **Find All Overdue Items**

1. Click any tab (or use "All Items")
2. Click the "Due Date" column header
3. Scroll to top to see overdue items (they're highlighted in RED)

OR:
1. Use the Search box
2. Type "Overdue"

### **Export to Manager for Review**

1. Click the tab you want to export
2. Click "Export CSV" or "Export Excel"
3. File downloads automatically
4. Open in Excel or send via email

### **Print Daily Standup Report**

1. Select "All Items" tab
2. Click "Print"
3. Browser print dialog opens
4. Select printer and print
5. Get clean, professional report

### **Focus On Urgent Work**

1. Click "Work Orders" tab
2. Click "Due Date" column header to sort
3. Top items are most urgent
4. Click "View" on any item to open details

### **Track a Specific Vendor's POs**

1. Click "Purchase Orders" tab
2. Use Search box
3. Type vendor name
4. See all that vendor's pending orders

---

## Keyboard Shortcuts

| Shortcut | Action |
|----------|--------|
| **Ctrl+P** | Print |
| **Ctrl+F** | Open browser search (searches table) |
| **Click Column Header** | Sort |

---

## Tips & Best Practices

✅ **Daily Check:**
   - Check "Pending Items" tab every morning
   - Focus on RED (overdue) items first
   - Assign tasks based on due dates

✅ **Weekly Reports:**
   - Export "All Items" as CSV every Friday
   - Send to management for oversight
   - Track completion rate

✅ **Data Accuracy:**
   - Keep item statuses updated
   - Update due dates before they pass
   - Assign items immediately when created

✅ **Performance:**
   - Dashboard loads in <1 second
   - Search filters in real-time
   - Export takes <2 seconds

---

## Troubleshooting

### **Dashboard won't load**
- Check you're logged in as Manager or Admin
- Clear browser cache (Ctrl+Shift+Del)
- Try different browser
- Check database connection

### **Export button does nothing**
- Check pop-up blocker is disabled
- Try different browser
- File may be downloading (check downloads)

### **Data looks old**
- Click "Refresh" to reload latest data
- Data updates automatically every 60 seconds

### **Can't see my changes**
- Click "Refresh" button
- Or press F5 to reload page

### **Pagination shows "No records"**
- Try different page or tab
- Adjust "Show X entries" dropdown
- Use search to find specific items

---

## Data Included

### **Work Orders**
- Status: New, Assigned, In Progress (excludes Completed, Cancelled)
- Fields: WO #, Description, Status, Due Date, Asset, Technician, Created Date

### **Preventive Maintenance**
- Status: Pending, Due, Overdue (from pm_schedule_log)
- Fields: PM ID, Description, Status, Due Date, Asset, Scheduled Date

### **Purchase Orders**
- Status: Draft, Submitted, Pending Receipt (excludes Received, Closed)
- Fields: PO #, Description, Status, Expected Delivery, Vendor, Created By

---

## Security

- **Access Control:** Manager/Admin only
- **Data Protection:** All entries require login
- **Audit Trail:** All views are logged
- **Session:** Secure session handling

---

## Browser Recommendations

✅ **Supported:**
- Chrome 90+
- Firefox 88+
- Edge 90+
- Safari 14+
- Mobile browsers (iOS Safari, Chrome Mobile)

❌ **Not Recommended:**
- Internet Explorer (use modern browser)
- Text-only browsers

---

## Need Help?

- **Dashboard won't load:** Check browser console (F12)
- **Questions about data:** See "Data Included" section above
- **Feature requests:** Contact system administrator

---

**Version:** 1.0  
**Last Updated:** March 8, 2026  
**System:** Maintenix CMMS v0.04
