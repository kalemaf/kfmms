# KPI Dashboard & MTTR/MTBF Design

This document outlines a professional layout for a KPI dashboard, a suggested database schema to support
calculation of MTTR (Mean Time To Repair) and MTBF (Mean Time Between Failures), and a reference table
of world-class benchmark values for these metrics.

*Supplemental files:*

- `schema_mttr_mtbf.sql` contains DDL for the necessary tables.
- `kpi.php` provides a sample KPI page with MTTR/MTBF calculations, date-range form and a Chart.js trend line.
- `api/metrics.php` exposes the core metrics as JSON for any front-end.
- A minimal React demo lives at `frontend/dashboard.html` to illustrate how the layout can migrate to a
  modern framework; it fetches from the API and renders simple cards.
---

## 📊 Professional KPI Dashboard Layout Structure

A well‑organized dashboard should allow users to glance at high‑level performance and drill into details.
Structure the page with the following areas:

1. **Header**
   - Title (e.g. "Monthly Performance Dashboard").
   - Date‑range selector ("Last 7 Days", "Last 30 Days", custom calendar).
   - Filter options (departments, regions, asset classes).
   - User profile / logout.

2. **Navigation sidebar**
   - Links to key sections (Dashboard, Work Orders, Assets, Reports, Settings).
   - Collapsible on narrow viewports.

3. **Summary Overview / KPI Tiles** (top row of cards)
   - Total Work Orders (current period).
   - Open / Pending / Overdue counts.
   - MTTR and MTBF (period).
   - Equipment Availability (%).
   - Utilization Rate.
   - Revenue, Expense, Profit Margin, Sales Growth, Satisfaction (if financial).

   Large cards show the value, period‑over‑period delta, and a miniature sparkline or icon. Use color coding
   (green/amber/red) to indicate status versus targets.

4. **Performance Graphs and Charts**
   - Time‑series line/area charts (e.g. failures, MTTR over last 12 weeks).
   - Bar charts comparing departments, locations or asset types.
   - Pie/donut charts for distribution (market share, failure reasons).

5. **Drill‑down Sections**
   - KPIs by category/department/team with small charts or tables.
   - Work orders by mechanic or area heatmap.
   - Upcoming preventive maintenance / due‑soon list.

6. **Alerts & Warnings**
   - Highlight KPIs that exceed thresholds.
   - Use badges or colored panels (red/yellow/green).

7. **Detailed Tables / Lists**
   - Scrollable table showing KPI name, current value, target, variance, trend.
   - Place at bottom or in a side panel for granular analysis.

8. **Comparisons & Benchmarking**
   - Display historical vs. actual values or industry benchmarks side‑by‑side.
   - Use gauges or progress bars for target‑vs‑actual.

9. **Footer**
   - Data refresh timestamp, version, copyright.

The layout should be responsive (cards stack on mobile) and use consistent spacing, typography and color.
CSS Grid or Flexbox are ideal for implementation.

---

## 📈 Database Schema for MTTR/MTBF

The tables must capture assets, incidents, downtime and uptime. Below is a more detailed
schema that aligns with the sample queries provided earlier. It can coexist with the
existing `work_orders` table in free‑cmms.

```sql
-- equipment master
CREATE TABLE equipment (
    equipment_id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    location VARCHAR(100),
    type VARCHAR(50),
    installation_date DATE,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

-- when an incident or failure occurs
CREATE TABLE Incident_Log (
    incident_id INT AUTO_INCREMENT PRIMARY KEY,
    equipment_id INT NOT NULL,
    start_time DATETIME NOT NULL,
    end_time DATETIME,
    downtime INT AS (TIMESTAMPDIFF(SECOND,start_time,end_time)) PERSISTENT,
    failure_reason VARCHAR(255),
    resolved_by INT,
    status ENUM('Resolved','Pending') DEFAULT 'Pending',
    FOREIGN KEY (equipment_id) REFERENCES equipment(equipment_id)
        ON DELETE CASCADE
);

-- maintenance activities
CREATE TABLE Maintenance_Log (
    maintenance_id INT AUTO_INCREMENT PRIMARY KEY,
    equipment_id INT NOT NULL,
    maintenance_date DATETIME NOT NULL,
    action_taken TEXT,
    parts_replaced TEXT,
    maintenance_type ENUM('Preventive','Corrective'),
    FOREIGN KEY (equipment_id) REFERENCES equipment(equipment_id)
        ON DELETE CASCADE
);

-- track uptime segments (useful for MTBF denominator)
CREATE TABLE Uptime_Record (
    record_id INT AUTO_INCREMENT PRIMARY KEY,
    equipment_id INT NOT NULL,
    start_time DATETIME NOT NULL,
    end_time DATETIME NOT NULL,
    uptime INT AS (TIMESTAMPDIFF(SECOND,start_time,end_time)) PERSISTENT,
    FOREIGN KEY (equipment_id) REFERENCES equipment(equipment_id)
        ON DELETE CASCADE
);

-- simplified failure history (could derive from Incident_Log)
CREATE TABLE Failure_History (
    failure_id INT AUTO_INCREMENT PRIMARY KEY,
    equipment_id INT NOT NULL,
    failure_date DATETIME NOT NULL,
    downtime INT,
    FOREIGN KEY (equipment_id) REFERENCES equipment(equipment_id)
        ON DELETE CASCADE
);
```

### MTTR Calculation

MTTR is the average downtime per failure. Sample query:

```sql
SELECT AVG(downtime) AS mttr
FROM Incident_Log
WHERE equipment_id = ?;
```

You can add date filters, group by equipment or department as needed.

### MTBF Calculation

MTBF is average time between failures, computed as total uptime divided by number of failures:

```sql
SELECT SUM(uptime) / COUNT(f.failure_id) AS mtbf
FROM Uptime_Record u
JOIN Failure_History f ON u.equipment_id = f.equipment_id
WHERE u.equipment_id = ?;
```

Alternatively, compute intervals between successive failure dates using window functions on
`Failure_History` and average the differences.

---

## 🎯 World-Class Benchmarks

Benchmarks depend on industry and asset criticality. The table below combines both generic
targets and an example industry‑focused view.

### Generic reliability thresholds

| Metric                    | Excellent / World-class | Good        | Needs Improvement |
|---------------------------|-------------------------|-------------|-------------------|
| MTTR (hours)              | < 2                     | 2–4         | > 4               |
| MTBF (hours)              | > 10 000              | 5 000–10 000 | < 5 000           |
| Availability (%)          | > 99.5%                | 98–99.5%    | < 98%            |
| First-time fix rate (%)   | > 90%                  | 80–90%      | < 80%            |
| PM compliance (%)         | > 95%                  | 85–95%      | < 85%            |

### Industry-specific example

| Industry        | KPI                 | Benchmark Value   | Notes                                      |
|-----------------|---------------------|-------------------|--------------------------------------------|
| Manufacturing   | MTTR (hrs)          | 4–8               | Rapid response expected                    |
| Manufacturing   | MTBF (hrs)          | 1 000–1 500       | High MTBF indicates reliable equipment     |
| IT / Data Center| MTTR (hrs)          | < 1               | Target low service restoration times       |
| Utilities       | MTBF (hrs)          | > 5 000           | Long intervals between outages            |


tailor these numbers to your domain and use them for colour-coded KPI cards (green/amber/red). Historical
benchmarks should be stored so you can chart progress over time.

---

Keep records of assumptions and calculation windows (daily, weekly, rolling 30 days) so metrics are comparable
over time. Accurate timestamps on `failures` and `repairs` are crucial.

This design provides a starting point to build a robust KPI dashboard and capture the data needed for MTTR/MTBF
analysis.