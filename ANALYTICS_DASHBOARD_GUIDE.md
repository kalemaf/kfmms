# Advanced Analytics & Reporting Dashboard
## CMMS v0.04+ | Production-Ready

**Version:** 1.0  
**Last Updated:** March 8, 2026  
**Status:** Production Ready  

---

## Executive Summary

The Advanced Analytics & Reporting Dashboard provides comprehensive operational intelligence for maintenance operations, enabling data-driven decision-making across seven critical business domains.

---

## Features Overview

### 1. MTBF/MTTR Trending Analysis
**Mean Time Between Failures / Mean Time To Repair**

- **Purpose:** Track equipment reliability and repair speed indicators
- **Key Metrics:**
  - Total cumulative failures per equipment
  - Average MTTR (how long repairs typically take)
  - Total cumulative downtime in days
  - System health status indicators

- **Business Use:**
  - Identify chronically problematic equipment
  - Benchmark repair speed against industry standards
  - Predict maintenance resource needs
  - Allocate technician training/tools to priority equipment

- **Data Sources:** work_orders table (submit_date to complete_date)
- **Calculation:** Avg MTTR = SUM(complete_date - submit_date) / COUNT(failures)

---

### 2. Equipment Downtime Analysis
**Detailed Downtime Tracking by Asset**

- **Purpose:** Quantify the operational impact of maintenance
- **Key Metrics:**
  - Number of downtime events
  - Total days/hours unavailable
  - Average duration per event
  - Last repair date and priorities involved

- **Business Use:**
  - Calculate equipment availability percentages
  - Justify capital equipment replacement decisions
  - Prioritize preventive maintenance targets
  - Understand maintenance cost drivers
  - Support production planning

- **Data Sources:** work_orders table with join to equipment table
- **Calculation:** Total Downtime = SUM(complete_date - submit_date) / 24 hours

---

### 3. Cost Analysis by Maintenance Type
**Spending Breakdown Across Maintenance Categories**

Three categories tracked:

**A. Work Order Costs**
   - Labor costs (technician time)
   - Material costs (parts, supplies used)
   - Subtotal per work order
   
**B. Purchase Order Costs**
   - Parts and materials procurement
   - Vendor-supplied services
   
**C. Preventive Maintenance Costs**
   - Planned maintenance investments
   - Scheduled labor

- **Business Use:**
  - Budget planning and forecasting
  - ROI justification for PM programs
  - Cost control initiatives
  - Identify cost reduction opportunities
  - Vendor cost benchmarking

- **Metrics Displayed:**
  - Total cost by category
  - Count of activities
  - Average cost per activity
  - Labor vs. material breakdown
  - Period-over-period comparison

---

### 4. Technician Productivity Report
**Individual and Team Performance Analytics**

- **Purpose:** Measure technician performance and capacity
- **Key Metrics per Technician:**
  - Total jobs completed
  - Jobs completed per week
  - Average days required per job
  - Total labor cost generated
  - Average cost per job
  - Percentage of high-priority assignments
  - Productivity trends

- **Business Use:**
  - Identify top performers for mentoring/promotion
  - Spot technician training gaps
  - Plan technician staffing needs
  - Optimize work assignment algorithms
  - Set performance benchmarks
  - Labor cost management

- **Bonus Features:**
  - Filters by date range and technician
  - High-priority job tracking
  - Productivity trending

---

### 5. Vendor Performance Scorecard
**Supplier Quality and Reliability Metrics**

- **Purpose:** Evaluate vendor reliability and value
- **Scoring Methodology:** 
  - Completion Rate (% of orders completed)
  - On-Time Delivery Rate (% of orders delivered by expected date)
  - Overall Score = (Completion% + OnTime%) / 2

- **Score Interpretation:**
  - **90-100:** Excellent (star vendor)
  - **75-89:** Good (acceptable vendor)
  - **Below 75:** Poor (review relationship)

- **Key Metrics:**
  - Order volume and spending
  - Average order value
  - Delivery performance
  - Late delivery count and percentage
  - Average delivery days

- **Business Use:**
  - Vendor selection and contract negotiations
  - Performance improvement discussions
  - Alternative vendor evaluation
  - Supply chain risk assessment
  - Procurement process optimization

---

### 6. Budget vs. Actual Spending
**Financial Performance Against Plan**

**Categories Tracked:**
- Work Order Labor (all technician time)
- Materials & Parts (WO line items)
- Purchase Orders (vendor supplies)

**Metrics:**
- Budgeted amount (if configured)
- Actual spending
- Variance ($)
- Percentage of budget
- Status (Under/Over Budget)

- **Business Use:**
  - Monitor spending against budget
  - Identify overspend categories
  - Adjust future budget allocations
  - Financial variance analysis
  - Cost control reporting to executives
  - Quarterly/annual budget reviews

**Note:** Budget amounts must be configured in the `maintenance_budget` table

---

### 7. Preventive Maintenance ROI Analysis
**Return on Investment for PM Programs**

- **Purpose:** Justify PM spending and measure program effectiveness
- **Methodology:**
  - Track all scheduled PM activities
  - Count compliance (% of scheduled PMs actually completed)
  - Measure prevention value (estimated cost avoidance)
  - Calculate ROI percentage

- **Key Metrics per PM Program:**
  - Scheduled activities
  - Completed activities
  - Compliance percentage
  - Total PM cost invested
  - Estimated savings (default: 75% of PM cost)
  - ROI percentage
  - Status indicator (Active/Monitor/Review)

- **ROI Calculation:**
  ```
  ROI% = ((Estimated Savings - PM Cost) / PM Cost) × 100
  
  Example:
  - PM Cost: $1,000
  - Est. Savings: $4,000
  - ROI = ($4,000 - $1,000) / $1,000 × 100 = 300%
  ```

- **Business Use:**
  - Justify continued PM program funding
  - Identify underperforming PM tasks
  - Demonstrate maintenance value to leadership
  - Optimize PM strategies
  - Budget allocation decisions
  - Regulatory compliance documentation

- **Status Indicators:**
  - **Active:** Excellent compliance (≥90%) AND positive ROI
  - **Monitor:** Acceptable compliance (≥75%) OR marginal ROI
  - **Review:** Low compliance (<75%) OR negative ROI - requires investigation

---

## How to Use the Analytics Dashboard

### Accessing the Dashboard
1. Log in as **Manager** or **Admin** user
2. Click the **"Analytics"** tab in the navigation bar
3. Dashboards loads with default date range (past 1 year) and all data

### Filtering Data

**Available Filters:**
- **From Date:** Start of analysis period (default: 1 year ago)
- **To Date:** End of analysis period (default: today)
- **Equipment ID:** Filter specific equipment (optional)
- **Technician ID:** Filter specific technician (optional)
- **Vendor ID:** Filter specific vendor (optional)

**Steps to Filter:**
1. Enter desired date range
2. Select optional filters (equipment, technician, vendor)
3. Click **"Filter"** button
4. Dashboard updates with filtered data

### Exporting & Printing

- **Print Report:** Click **"Print"** button to open browser print dialog
  - Optimized for printing with clean formatting
  - Easy pagination across multiple pages

- **Export to CSV:**
  - Currently displayed in HTML tables
  - Can be copied and pasted to Excel
  - Future: Add CSV export buttons to individual sections

---

## Data Dictionary

### MTBF/MTTR Report
| Column | Definition |
|--------|-----------|
| Equipment | Asset name or code |
| Total Failures | Count of work orders for this asset |
| Avg MTTR | Average hours to complete repair |
| Total Downtime | Sum of all downtime periods in days |
| Status | Qualitative assessment (Excellent/Good/Poor) |

### Equipment Downtime Report
| Column | Definition |
|--------|-----------|
| Equipment | Asset name |
| Type | Equipment category |
| Downtime Events | Number of maintenance activities |
| Total Days Down | Cumulative downtime in 24-hour days |
| Avg Days/Event | Average duration per maintenance event |
| Last Repair | Most recent completion date |

### Cost Analysis Report
| Column | Definition |
|--------|-----------|
| Maintenance Type | WO, PM, or PO |
| Count | Number of activities |
| Labor Cost | Technician time cost |
| Material Cost | Parts and supplies cost |
| Total Cost | Labor + Materials |
| Avg Cost/Unit | Total Cost / Count |

### Technician Productivity Report
| Column | Definition |
|--------|-----------|
| Technician | Employee name |
| Jobs Completed | Total work orders finished |
| Jobs/Week | Average weekly output |
| Avg Days/Job | Average duration per assignment |
| Total Labor Cost | Sum of labor charges |
| Avg Cost/Job | Labor cost per work order |
| High Priority % | Percentage of critical/high priority work |

### Vendor Performance Report
| Column | Definition |
|--------|-----------|
| Vendor | Supplier name |
| Type | Parts/Service/Equipment supplier |
| Orders | Purchase orders placed |
| Total Spent | Total procurement spend |
| Completion % | Orders fully received |
| On-Time % | Orders received by expected date |
| Avg Delivery Days | Average lead time |
| Score | Overall vendor rating (0-100) |

### Budget vs. Actual Report
| Column | Definition |
|--------|-----------|
| Category | Labor/Materials/POs |
| Budgeted | Planned spending from budget |
| Actual Spent | Real spending to date |
| Variance | Budget minus actual |
| % of Budget | Actual / Budget × 100 |
| Status | Under/Over budget indicator |

### PM ROI Report
| Column | Definition |
|--------|-----------|
| PM Program | Preventive maintenance task name |
| Scheduled | Total activities planned in period |
| Completed | Activities actually performed |
| Compliance % | Completed / Scheduled × 100 |
| PM Cost | Total investment in this PM |
| Est. Savings | Estimated prevention value (75% of cost) |
| ROI % | ((Savings - Cost) / Cost) × 100 |
| Status | Active/Monitor/Review indicator |

---

## Configuration

### Budget Table Setup

To enable Budget vs. Actual reporting, create a maintenance_budget table:

```sql
CREATE TABLE IF NOT EXISTS maintenance_budget (
    id INT PRIMARY KEY AUTO_INCREMENT,
    budget_category VARCHAR(50) NOT NULL,
    budget_amount DECIMAL(12,2) NOT NULL,
    fiscal_year INT,
    notes TEXT,
    created_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY (budget_category, fiscal_year)
);

-- Populate with your organization's budget
INSERT INTO maintenance_budget (budget_category, budget_amount, fiscal_year) VALUES
    ('labor', 50000, YEAR(CURDATE())),
    ('materials', 30000, YEAR(CURDATE())),
    ('purchases', 25000, YEAR(CURDATE()));
```

### Customization Options

The dashboard can be customized by editing `analytics_dashboard.php`:

**To add more metrics:**
1. Add new query function (following get_* naming convention)
2. Call function in main data loading section
3. Add new report section in HTML with table or chart
4. Update documentation

**To change color schemes:**
- Modify CSS in `<style>` section
- Current colors: Purple (#667eea) primary, Bootstrap colors for status

**To add charts:**
- Include Chart.js (already loaded via CDN)
- Add `<canvas>` element in report section
- Create JavaScript to populate chart with data

---

## Best Practices

### For Managers
1. **Weekly:** Review Pending Items + Technician Productivity
2. **Monthly:** Full Analytics Dashboard review
3. **Quarterly:** Budget vs. Actual + Vendor Performance analysis
4. **Annually:** PM ROI review for program adjustments

### For Executives
1. **Monthly:** Review MTBF/MTTR and Equipment Downtime trends
2. **Quarterly:** Budget vs. Actual and PM ROI reporting
3. **Annually:** Vendor Performance scorecard and strategic decisions

### For Maintenance Planners
1. **Ongoing:** Use Equipment Downtime to prioritize PM targets
2. **Monthly:** Technician Productivity to balance workload
3. **Quarterly:** Cost Analysis to adjust maintenance strategies
4. **Ad-hoc:** Vendor Performance for supply chain decisions

---

## Troubleshooting

### "No data available for selected date range"
- **Solution:** Adjust date range to include activities
- **Check:** Ensure work orders/POs have completion dates

### Technician shows 0 jobs
- **Solution:** Technician not assigned to completed work orders
- **Check:** Verify mechanic_id field populated in work_orders

### Vendor score is low
- **Solution:** Check order completion and delivery dates
- **Check:** Ensure expected_delivery_date set on POs

### Budget shows $0
- **Solution:** Budget amounts not configured
- **Solution:** Create entries in maintenance_budget table

---

## Support & Maintenance

**Version:** 1.0 (March 8, 2026)  
**Compatibility:** PHP 7.4+, MySQL 5.7+  
**Browser Support:** Modern browsers (Chrome, Edge, Firefox)  
**Responsive Design:** Works on desktop and tablets  

**For issues or feature requests:**
- Contact system administrator
- Check database connectivity
- Verify table structure matches schema
- Review log files for errors

---

## Future Enhancements

Potential features for v2.0:
- Interactive charts and graphs (Bar, Line, Pie)
- Export to PDF, Excel formats
- Email scheduled reports
- Dashboard widgets for quick statistics
- Trend analysis and forecasting
- Equipment purchase recommendations
- Technician skill matrix
- Advanced filtering and custom date ranges
- KPI dashboard with traffic light indicators
- Benchmarking against industry standards

---

**Happy analyzing! 📊**
