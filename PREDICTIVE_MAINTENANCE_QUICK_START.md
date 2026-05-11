# ⚡ Quick Reference: Predictive Maintenance Verification

## 📊 Files Installed ✅

```
✅ libraries/predictive_maintenance.php (20.9 KB)
✅ predictive_dashboard.php (16.3 KB)
✅ api_condition_monitoring.php (8.5 KB)
✅ setup_predictive_maintenance.php (10.3 KB)
```

---

## 🚀 3-Step Setup

### Step 1: Initialize Database
```bash
php setup_predictive_maintenance.php
```
**Result**: 6 tables created, sample data loaded

### Step 2: View Dashboard
```
Browser: http://localhost:8000/predictive_dashboard.php
```
**Result**: See alerts, metrics, upcoming maintenance

### Step 3: Test API
```bash
curl -X POST http://localhost:8000/api_condition_monitoring.php \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer TEST_TOKEN" \
  -d '{"equipment_id":1,"parameter_type":"temperature","measured_value":85,"unit":"°C"}'
```
**Result**: Data submitted, alerts generated

---

## 🗄️ 6 Database Tables Created

| Table | Purpose | Rows After Setup |
|-------|---------|------------------|
| `asset_lifecycle` | Equipment lifecycle | ~3 |
| `condition_monitoring` | Sensor readings | ~3 |
| `maintenance_schedule` | Preventive tasks | ~3 |
| `part_lifecycle` | Part tracking | ~3 |
| `asset_health_metrics` | KPIs (MTBF/MTTR/OEE) | ~3 |
| `predictive_alerts` | Intelligent alerts | ~2-3 |

---

## 🎯 Key Features to Check

### Feature 1: Dashboard
- [ ] Open: `predictive_dashboard.php`
- [ ] See: Key metrics cards
- [ ] See: Critical alerts section
- [ ] See: Upcoming maintenance list
- [ ] Check: Color-coded health status

### Feature 2: Alerts
- [ ] Critical alerts display
- [ ] Severity levels (Critical/Warning/Normal)
- [ ] Confidence scores
- [ ] Actionable recommendations

### Feature 3: API
- [ ] Single data submission works
- [ ] Batch submissions work
- [ ] Alerts auto-generate on threshold
- [ ] Status determined automatically

### Feature 4: Metrics
- [ ] MTBF (Mean Time Between Failures)
- [ ] MTTR (Mean Time To Repair)
- [ ] OEE (Overall Equipment Effectiveness)
- [ ] Health scores (0-100%)

### Feature 5: Multi-Tenant
- [ ] Company A sees only its data
- [ ] Company B sees only its data
- [ ] No cross-tenant data leakage

---

## 🔧 Verify via SQL

```sql
-- Check tables exist
SELECT count(*) as table_count 
FROM sqlite_master 
WHERE type='table' AND name IN (
  'asset_lifecycle', 'condition_monitoring',
  'maintenance_schedule', 'part_lifecycle',
  'asset_health_metrics', 'predictive_alerts'
);
-- Expected: 6

-- Check sample data
SELECT count(*) FROM asset_lifecycle;
-- Expected: 3 (or more)

-- Check alerts generated
SELECT count(*) FROM predictive_alerts;
-- Expected: 2+ alerts

-- Check condition data
SELECT count(*) FROM condition_monitoring;
-- Expected: 3+
```

---

## 📍 Navigation Links

| Feature | URL |
|---------|-----|
| **Dashboard** | `/predictive_dashboard.php` |
| **Setup** | `/setup_predictive_maintenance.php` |
| **API** | `/api_condition_monitoring.php` |
| **Core Library** | `/libraries/predictive_maintenance.php` |

---

## 💻 Implementation in Code

### Access Critical Alerts
```php
require 'libraries/predictive_maintenance.php';
$alerts = get_critical_alerts(10);
foreach ($alerts as $alert) {
    echo $alert['title'] . ': ' . $alert['recommendation'];
}
```

### Get Asset Health
```php
$health = get_asset_health_overview();
echo "Fleet Health: " . $health['health_percentage'] . "%";
echo "Critical Assets: " . $health['critical'];
```

### Calculate KPIs
```php
$mtbf = calculate_mtbf($equipment_id);
$mttr = calculate_mttr($equipment_id);
$oee = calculate_oee($equipment_id);
```

### Submit Condition Data
```php
$result = submit_condition_data([
    'equipment_id' => 1,
    'parameter_type' => 'temperature',
    'measured_value' => 85.2,
    'unit' => '°C',
    'threshold_warning' => 80,
    'threshold_critical' => 90
], $tenant_id, $user_id);
```

---

## 🟢 Status Indicators

| Status | Color | Meaning |
|--------|-------|---------|
| **Healthy** | 🟢 Green | 0-50% lifecycle used |
| **Caution** | 🟡 Yellow | 50-70% lifecycle used |
| **Warning** | 🟠 Orange | 70-90% lifecycle used |
| **Critical** | 🔴 Red | 90%+ lifecycle used |

---

## 🚨 Quick Troubleshooting

| Issue | Check | Fix |
|-------|-------|-----|
| Tables don't exist | Run: `php setup_predictive_maintenance.php` | Initialize system |
| Dashboard blank | Check: sample data loaded | Run setup script |
| API returns 401 | Check: Bearer token | Add auth header |
| No alerts | Check: thresholds exceeded | Lower thresholds or submit data |
| Cross-tenant data | Check: tenant_id filter | Should be isolated |

---

## ✅ Verification Checklist

```
□ Files exist (4 PHP + 4 docs)
□ Setup script runs without errors
□ Database tables created (6 tables)
□ Dashboard loads and displays data
□ API accepts POST requests
□ Alerts generate on threshold
□ Multi-tenant isolation works
□ Documentation accessible
```

---

## 🎓 Learn More

- **Full Setup**: Read `PREDICTIVE_MAINTENANCE_GUIDE.md`
- **Features**: Read `PREDICTIVE_SYSTEM_COMPLETE.md`
- **Implementation**: Read `PREDICTIVE_MAINTENANCE_VERIFICATION.md`
- **Security**: Read `PREDICTIVE_TENANT_ISOLATION_AUDIT.md`

---

**Status**: 🟢 **System Installed & Ready**  
**Next Step**: Run `php setup_predictive_maintenance.php`
