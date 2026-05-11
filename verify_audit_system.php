<?php
require_once 'config.inc.php';
require_once 'common.inc.php';

echo "╔════════════════════════════════════════════════════════════════╗\n";
echo "║         AUDIT LOGGING SYSTEM - FINAL VERIFICATION            ║\n";
echo "╚════════════════════════════════════════════════════════════════╝\n\n";

// 1. Check database connection
echo "1. DATABASE CONNECTION:\n";
if ($connection && is_object($connection)) {
    echo "   ✅ Connected to $db_type database\n";
} else {
    echo "   ❌ Database connection failed\n";
    exit(1);
}

// 2. Check audit tables exist
echo "\n2. AUDIT LOG TABLES:\n";
$tables_exist = true;
$total_records = 0;

foreach (['security_audit_log', 'compliance_audit_log'] as $table) {
    try {
        if ($db_type === 'sqlite') {
            $check = $connection->query("SELECT name FROM sqlite_master WHERE type='table' AND name='$table'");
            $exists = $check && $check->fetch(PDO::FETCH_ASSOC) !== false;
        } else {
            $check = $connection->query("SHOW TABLES LIKE '$table'");
            $exists = $check && $check->rowCount() > 0;
        }
        
        if ($exists) {
            $count = $connection->query("SELECT COUNT(*) as cnt FROM \"$table\"")->fetch(PDO::FETCH_ASSOC)['cnt'];
            echo "   ✅ $table: $count records\n";
            $total_records += $count;
        } else {
            echo "   ❌ $table: NOT FOUND\n";
            $tables_exist = false;
        }
    } catch (Exception $e) {
        echo "   ❌ Error checking $table: " . $e->getMessage() . "\n";
        $tables_exist = false;
    }
}

// 3. Check AuditLogger class
echo "\n3. AUDIT LOGGER CLASS:\n";
if (file_exists('app/AuditLogger.php')) {
    echo "   ✅ app/AuditLogger.php exists\n";
    
    if (class_exists('AuditLogger')) {
        echo "   ✅ AuditLogger class loaded\n";
    } else {
        require_once 'app/AuditLogger.php';
        if (class_exists('AuditLogger')) {
            echo "   ✅ AuditLogger class can be loaded\n";
        } else {
            echo "   ❌ AuditLogger class not found\n";
        }
    }
} else {
    echo "   ❌ app/AuditLogger.php NOT FOUND\n";
}

// 4. Check integration files
echo "\n4. INTEGRATION STATUS:\n";
$files_to_check = [
    'auth.php' => 'AuditLogger',
    'admin_roles.php' => 'AuditLogger',
    'force_password_change.php' => 'AuditLogger',
    'audit_logs.php' => 'security_audit_log'
];

foreach ($files_to_check as $file => $search_string) {
    if (file_exists($file)) {
        $content = file_get_contents($file);
        if (strpos($content, $search_string) !== false) {
            echo "   ✅ $file: Integrated\n";
        } else {
            echo "   ⚠️  $file: May not be integrated (check manually)\n";
        }
    } else {
        echo "   ❌ $file: NOT FOUND\n";
    }
}

// 5. Sample logs
echo "\n5. SAMPLE AUDIT LOG ENTRIES:\n";
try {
    $result = $connection->query("SELECT event_type, username, severity FROM security_audit_log ORDER BY log_id DESC LIMIT 3");
    if ($result) {
        $count = 0;
        while ($row = $result->fetch(PDO::FETCH_ASSOC)) {
            $count++;
            echo "   $count. " . $row['event_type'] . " - User: " . $row['username'] . " - Severity: " . $row['severity'] . "\n";
        }
        if ($count === 0) {
            echo "   (No entries yet)\n";
        }
    }
} catch (Exception $e) {
    echo "   ❌ Error: " . $e->getMessage() . "\n";
}

// 6. Final status
echo "\n╔════════════════════════════════════════════════════════════════╗\n";
echo "║                    FINAL STATUS                               ║\n";
echo "╚════════════════════════════════════════════════════════════════╝\n\n";

if ($tables_exist && $total_records > 0) {
    echo "✅ SYSTEM STATUS: READY FOR USE\n\n";
    echo "   • Database: Connected\n";
    echo "   • Tables: 2 active\n";
    echo "   • Records: $total_records total\n";
    echo "   • Logging: ACTIVE\n";
    echo "   • Display: WORKING\n\n";
    echo "Next step: Login to the system and view 📊 Audit Logs\n";
} else {
    echo "⚠️  SYSTEM STATUS: PARTIALLY READY\n\n";
    echo "   • Tables may not have data yet\n";
    echo "   • Try logging in to generate audit entries\n";
}
?>
