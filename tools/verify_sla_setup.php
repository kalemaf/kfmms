<?php
/**
 * SLA Fields Verification Tool
 * Checks if SLA fields have been properly added to work_orders table
 */

include 'config.inc.php';

echo "=== Maintenix SLA Fields Verification ===\n\n";

// Check if work_orders table exists
$result = $connection->query("SHOW TABLES LIKE 'work_orders'");
if ($result->num_rows === 0) {
    echo "❌ ERROR: work_orders table not found!\n";
    exit(1);
}
echo "✅ work_orders table found\n";

// Check for SLA fields
$required_fields = [
    'created_at' => 'TIMESTAMP',
    'acknowledged_at' => 'DATETIME',
    'completed_at' => 'DATETIME',
    'sla_response_limit' => 'INT',
    'sla_completion_limit' => 'INT',
    'sla_status' => 'VARCHAR'
];

$missing_fields = [];
$existing_fields = [];

foreach ($required_fields as $field => $expected_type) {
    $result = $connection->query("SHOW COLUMNS FROM work_orders LIKE '$field'");
    if ($result && $result->num_rows > 0) {
        $row = $result->fetch_assoc();
        echo "✅ Field '$field' exists (Type: {$row['Type']})\n";
        $existing_fields[] = $field;
    } else {
        echo "❌ Field '$field' MISSING (Expected type: $expected_type)\n";
        $missing_fields[] = $field;
    }
}

echo "\n";

if (empty($missing_fields)) {
    echo "✅ ALL SLA FIELDS ARE INSTALLED!\n\n";
    
    // Check sample data
    $result = $connection->query("SELECT wo_id, created_at, acknowledged_at, completed_at, sla_status FROM work_orders LIMIT 1");
    if ($result && $result->num_rows > 0) {
        $row = $result->fetch_assoc();
        echo "Sample work order data (WO#{$row['wo_id']}):\n";
        echo "  created_at: " . ($row['created_at'] ?? 'NULL') . "\n";
        echo "  acknowledged_at: " . ($row['acknowledged_at'] ?? 'NULL') . "\n";
        echo "  completed_at: " . ($row['completed_at'] ?? 'NULL') . "\n";
        echo "  sla_status: " . ($row['sla_status'] ?? 'NULL') . "\n";
        echo "\n";
    }
    
    // Check helper file
    $helper_path = __DIR__ . '/libraries/sla_helper.php';
    if (file_exists($helper_path)) {
        echo "✅ SLA helper file found at libraries/sla_helper.php\n";
    } else {
        echo "❌ SLA helper file NOT FOUND at libraries/sla_helper.php\n";
    }
    
    // Check save.php integration
    $save_php = file_get_contents(__DIR__ . '/save.php');
    if (strpos($save_php, 'sla_helper.php') !== false) {
        echo "✅ save.php includes SLA helper\n";
    } else {
        echo "⚠️  save.php may not include SLA helper\n";
    }
    
    if (strpos($save_php, 'set_acknowledged_timestamp') !== false) {
        echo "✅ save.php has acknowledgment tracking\n";
    } else {
        echo "⚠️  save.php may not have acknowledgment tracking\n";
    }
    
    if (strpos($save_php, 'set_completed_timestamp') !== false) {
        echo "✅ save.php has completion tracking\n";
    } else {
        echo "⚠️  save.php may not have completion tracking\n";
    }
    
    echo "\n✅ SLA TRACKING IS READY TO USE!\n";
    
} else {
    echo "❌ MISSING FIELDS: " . implode(', ', $missing_fields) . "\n";
    echo "\nTo install missing fields, run the migration:\n";
    echo "1. Open phpMyAdmin\n";
    echo "2. Select the 'free_cmms' database\n";
    echo "3. Click 'SQL' tab\n";
    echo "4. Copy and paste the contents of: migrations/001_add_sla_fields.sql\n";
    echo "5. Click 'Go'\n";
    echo "\nOR run from command line:\n";
    echo "mysql -u username -p free_cmms < migrations/001_add_sla_fields.sql\n";
    exit(1);
}
?>
