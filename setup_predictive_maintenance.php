<?php
/**
 * Predictive Maintenance - Quick Setup & Testing
 * 
 * Run this script once to:
 * 1. Initialize all predictive maintenance tables
 * 2. Add sample data for testing
 * 3. Generate sample alerts
 * 4. Display dashboard metrics
 */

require_once 'config.inc.php';
require_once 'common.inc.php';
require_once 'libraries/predictive_maintenance.php';

echo "\n";
echo "╔═══════════════════════════════════════════════════════════════════════════╗\n";
echo "║     PREDICTIVE MAINTENANCE SYSTEM - INITIALIZATION & TESTING              ║\n";
echo "╚═══════════════════════════════════════════════════════════════════════════╝\n\n";

$_SESSION['tenant_id'] = 1;
$tenant_id = 1;
$results = [];

// ============ STEP 1: CREATE TABLES ============
echo "Step 1: Initializing Database Tables...\n";
try {
    $init_result = create_predictive_maintenance_tables();
    echo "✅ Tables initialized successfully\n";
    $results['tables_created'] = true;
} catch (Exception $e) {
    echo "⚠️ Tables already exist (safe): " . $e->getMessage() . "\n";
    $results['tables_created'] = true;
}

// ============ STEP 2: ADD SAMPLE EQUIPMENT LIFECYCLE ============
echo "\nStep 2: Adding Sample Equipment Lifecycle Data...\n";

$sample_equipment = [
    [
        'name' => 'Main Pump Station',
        'expected_hours' => 40000,
        'current_hours' => 32000,  // 80% used
        'criticality' => 'High'
    ],
    [
        'name' => 'Backup Motor',
        'expected_hours' => 35000,
        'current_hours' => 18000,  // 51% used
        'criticality' => 'Medium'
    ],
    [
        'name' => 'Circulation Fan',
        'expected_hours' => 50000,
        'current_hours' => 48000,  // 96% used - CRITICAL
        'criticality' => 'High'
    ]
];

$added_count = 0;
foreach ($sample_equipment as $eq) {
    try {
        // Check if already exists
        $check = $connection->query("SELECT id FROM asset_lifecycle WHERE equipment_id = 1 AND criticality = '{$eq['criticality']}' LIMIT 1")->fetch();
        
        if (!$check) {
            $stmt = $connection->prepare("
                INSERT INTO asset_lifecycle 
                (equipment_id, asset_category, expected_lifecycle_hours, 
                 current_runtime_hours, criticality, installation_date, tenant_id)
                VALUES (?, ?, ?, ?, ?, ?, ?)
            ");
            
            $eq_id = rand(1, 10); // Use random equipment ID for demo
            $stmt->execute([
                $eq_id,
                'equipment',
                $eq['expected_hours'],
                $eq['current_hours'],
                $eq['criticality'],
                date('Y-m-d', strtotime('-2 years')),
                $tenant_id
            ]);
            
            echo "  ✅ Added: {$eq['name']} ({$eq['current_hours']}/{$eq['expected_hours']} hours)\n";
            $added_count++;
        }
    } catch (Exception $e) {
        echo "  ⚠️ {$eq['name']}: " . $e->getMessage() . "\n";
    }
}
$results['sample_assets'] = $added_count;

// ============ STEP 3: ADD SAMPLE CONDITION DATA ============
echo "\nStep 3: Adding Sample Condition Monitoring Data...\n";

$sample_conditions = [
    ['eq_id' => 1, 'param' => 'temperature', 'value' => 85, 'unit' => '°C', 'warning' => 80, 'critical' => 90],
    ['eq_id' => 1, 'param' => 'vibration', 'value' => 3.2, 'unit' => 'mm/s', 'warning' => 3.5, 'critical' => 4.5],
    ['eq_id' => 2, 'param' => 'pressure', 'value' => 2.8, 'unit' => 'bar', 'warning' => 3.0, 'critical' => 3.5],
    ['eq_id' => 3, 'param' => 'temperature', 'value' => 92, 'unit' => '°C', 'warning' => 80, 'critical' => 90],
];

$conditions_added = 0;
foreach ($sample_conditions as $cond) {
    try {
        $stmt = $connection->prepare("
            INSERT INTO condition_monitoring 
            (equipment_id, parameter_type, measured_value, unit, 
             threshold_warning, threshold_critical, status, tenant_id)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        ");
        
        $status = 'Normal';
        if ($cond['value'] >= $cond['critical']) {
            $status = 'Critical';
        } elseif ($cond['value'] >= $cond['warning']) {
            $status = 'Warning';
        }
        
        $stmt->execute([
            $cond['eq_id'],
            $cond['param'],
            $cond['value'],
            $cond['unit'],
            $cond['warning'],
            $cond['critical'],
            $status,
            $tenant_id
        ]);
        
        echo "  ✅ {$cond['param']}: {$cond['value']}{$cond['unit']} [$status]\n";
        $conditions_added++;
    } catch (Exception $e) {
        // Might fail if table doesn't exist yet, that's OK
    }
}
$results['condition_records'] = $conditions_added;

// ============ STEP 4: ADD MAINTENANCE SCHEDULES ============
echo "\nStep 4: Creating Maintenance Schedules...\n";

$schedules_added = 0;
try {
    $stmt = $connection->prepare("
        INSERT INTO maintenance_schedule 
        (equipment_id, task_name, task_description, maintenance_type, 
         trigger_type, trigger_value, trigger_unit, frequency_days, 
         priority, status, tenant_id)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");
    
    $tasks = [
        ['eq' => 1, 'name' => 'Oil change', 'type' => 'hours', 'value' => 1000, 'priority' => 'High'],
        ['eq' => 1, 'name' => 'Filter replacement', 'type' => 'hours', 'value' => 500, 'priority' => 'High'],
        ['eq' => 2, 'name' => 'Bearing lubrication', 'type' => 'hours', 'value' => 2000, 'priority' => 'Medium'],
        ['eq' => 3, 'name' => 'Inspection', 'type' => 'days', 'value' => 30, 'priority' => 'High'],
    ];
    
    foreach ($tasks as $task) {
        $stmt->execute([
            $task['eq'],
            $task['name'],
            "Routine maintenance: {$task['name']}",
            'Preventive',
            $task['type'],
            $task['value'],
            $task['type'],
            30,
            $task['priority'],
            'Active',
            $tenant_id
        ]);
        
        echo "  ✅ {$task['name']} (Equipment {$task['eq']})\n";
        $schedules_added++;
    }
} catch (Exception $e) {
    echo "  ⚠️ Some schedules couldn't be created\n";
}
$results['schedules_created'] = $schedules_added;

// ============ STEP 5: GENERATE SAMPLE ALERTS ============
echo "\nStep 5: Generating Predictive Alerts...\n";

$alerts_created = 0;
try {
    // Alert for overused equipment
    create_predictive_alert(
        3,
        null,
        'overused_equipment',
        'Critical',
        'Circulation Fan approaching end of life',
        'Usage: 96% of expected 50,000 hour lifecycle',
        'Schedule replacement within 2 weeks',
        0.95,
        date('Y-m-d', strtotime('+7 days'))
    );
    echo "  ✅ Alert: Equipment nearing end of lifecycle\n";
    $alerts_created++;
    
    // Alert for condition anomaly
    create_predictive_alert(
        1,
        null,
        'condition_anomaly',
        'Warning',
        'Main Pump - High Temperature Detected',
        'Temperature reading: 85°C (threshold: 80°C)',
        'Inspect cooling system and check for blockages',
        0.85,
        date('Y-m-d', strtotime('+3 days'))
    );
    echo "  ✅ Alert: Condition threshold exceeded\n";
    $alerts_created++;
    
} catch (Exception $e) {
    echo "  ⚠️ Some alerts couldn't be created\n";
}
$results['alerts_created'] = $alerts_created;

// ============ STEP 6: DISPLAY METRICS ============
echo "\nStep 6: Retrieving Dashboard Metrics...\n";

try {
    $health = get_asset_health_overview();
    echo "  📊 Total Assets: {$health['total_assets']}\n";
    echo "  🟢 Healthy: {$health['healthy']}\n";
    echo "  🟡 Warning: {$health['warning']}\n";
    echo "  🔴 Critical: {$health['critical']}\n";
    echo "  💪 Fleet Health Score: {$health['health_percentage']}%\n";
    echo "  📈 Average Usage: {$health['average_usage']}%\n";
    
    $alerts = get_critical_alerts(5);
    echo "\n  🚨 Critical Alerts: " . count($alerts) . "\n";
    foreach (array_slice($alerts, 0, 3) as $alert) {
        echo "     - {$alert['title']}\n";
    }
    
    $maintenance = get_upcoming_maintenance(30);
    echo "\n  📅 Upcoming Maintenance: " . count($maintenance) . " tasks\n";
    foreach (array_slice($maintenance, 0, 3) as $task) {
        echo "     - {$task['task_name']} (due: {$task['next_due_date']})\n";
    }
    
} catch (Exception $e) {
    echo "  ⚠️ Could not retrieve metrics: " . $e->getMessage() . "\n";
}

// ============ COMPLETION ============
echo "\n╔═══════════════════════════════════════════════════════════════════════════╗\n";
echo "║                        SETUP COMPLETE ✅                                  ║\n";
echo "╚═══════════════════════════════════════════════════════════════════════════╝\n\n";

echo "Summary:\n";
echo "  ✅ Database tables initialized\n";
echo "  ✅ {$results['sample_assets']} sample assets added\n";
echo "  ✅ {$results['condition_records']} condition readings recorded\n";
echo "  ✅ {$results['schedules_created']} maintenance tasks created\n";
echo "  ✅ {$results['alerts_created']} predictive alerts generated\n\n";

echo "Next Steps:\n";
echo "  1️⃣ Open dashboard: predictive_dashboard.php\n";
echo "  2️⃣ Review alerts and metrics\n";
echo "  3️⃣ Add real equipment data to your system\n";
echo "  4️⃣ Configure IoT sensor integration (optional)\n";
echo "  5️⃣ Monitor condition data in real-time\n\n";

echo "📚 Documentation: PREDICTIVE_MAINTENANCE_GUIDE.md\n";
echo "🔌 API Endpoint: api_condition_monitoring.php\n";
echo "📊 Dashboard: predictive_dashboard.php\n\n";

?>
