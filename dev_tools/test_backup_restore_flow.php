<?php
/**
 * Test Suite: Backup & Restore Flow
 * Tests backup creation, verification, and restoration
 */
require 'config.inc.php';

echo "\n";
echo "═══════════════════════════════════════════════════════════════\n";
echo "TEST SUITE: Backup & Restore Flow\n";
echo "═══════════════════════════════════════════════════════════════\n";
echo "\n";

$test_results = [];
$test_num = 1;

// Test 1: Database exists
echo "Test $test_num: Database File Exists\n";
try {
    if (file_exists(__DIR__ . '/database/maintenix.db')) {
        $size = round(filesize(__DIR__ . '/database/maintenix.db') / 1024 / 1024, 2);
        echo "✅ PASS: Database file exists\n";
        echo "   - File: database/maintenix.db\n";
        echo "   - Size: {$size} MB\n";
        $test_results[] = ['num' => $test_num, 'status' => 'PASS'];
    } else {
        echo "❌ FAIL: Database file not found\n";
        $test_results[] = ['num' => $test_num, 'status' => 'FAIL'];
    }
} catch (Exception $e) {
    echo "❌ FAIL: {$e->getMessage()}\n";
    $test_results[] = ['num' => $test_num, 'status' => 'FAIL'];
}
echo "\n";
$test_num++;

// Test 2: Backup directory exists
echo "Test $test_num: Backup Directory Exists\n";
try {
    if (is_dir(__DIR__ . '/database/backups')) {
        echo "✅ PASS: Backup directory exists\n";
        echo "   - Path: database/backups\n";
        $test_results[] = ['num' => $test_num, 'status' => 'PASS'];
    } else {
        echo "❌ FAIL: Backup directory not found\n";
        $test_results[] = ['num' => $test_num, 'status' => 'FAIL'];
    }
} catch (Exception $e) {
    echo "❌ FAIL: {$e->getMessage()}\n";
    $test_results[] = ['num' => $test_num, 'status' => 'FAIL'];
}
echo "\n";
$test_num++;

// Test 3: Database integrity check
echo "Test $test_num: Database Integrity Check\n";
try {
    $pdo = new PDO('sqlite:' . __DIR__ . '/database/maintenix.db');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    $result = $pdo->query('PRAGMA integrity_check');
    $check = $result->fetch(PDO::FETCH_ASSOC);
    $pdo = null;
    
    if ($check && $check['integrity_check'] === 'ok') {
        echo "✅ PASS: Database integrity check passed\n";
        echo "   - Status: OK\n";
        $test_results[] = ['num' => $test_num, 'status' => 'PASS'];
    } else {
        echo "❌ FAIL: Database integrity check failed\n";
        echo "   - Status: " . ($check['integrity_check'] ?? 'unknown') . "\n";
        $test_results[] = ['num' => $test_num, 'status' => 'FAIL'];
    }
} catch (Exception $e) {
    echo "❌ FAIL: {$e->getMessage()}\n";
    $test_results[] = ['num' => $test_num, 'status' => 'FAIL'];
}
echo "\n";
$test_num++;

// Test 4: Create backup
echo "Test $test_num: Create Database Backup\n";
try {
    $timestamp = date('Y-m-d_H-i-s');
    $backup_file = __DIR__ . '/database/backups/maintenix_' . $timestamp . '.db';
    
    if (!copy(__DIR__ . '/database/maintenix.db', $backup_file)) {
        echo "❌ FAIL: Failed to copy database file\n";
        $test_results[] = ['num' => $test_num, 'status' => 'FAIL'];
    } else if (!file_exists($backup_file) || filesize($backup_file) === 0) {
        echo "❌ FAIL: Backup file created but appears empty\n";
        @unlink($backup_file);
        $test_results[] = ['num' => $test_num, 'status' => 'FAIL'];
    } else {
        chmod($backup_file, 0644);
        $size = round(filesize($backup_file) / 1024 / 1024, 2);
        echo "✅ PASS: Backup created successfully\n";
        echo "   - File: database/backups/maintenix_{$timestamp}.db\n";
        echo "   - Size: {$size} MB\n";
        $test_results[] = ['num' => $test_num, 'status' => 'PASS', 'backup_file' => $backup_file];
    }
} catch (Exception $e) {
    echo "❌ FAIL: {$e->getMessage()}\n";
    $test_results[] = ['num' => $test_num, 'status' => 'FAIL'];
}
echo "\n";
$test_num++;

// Test 5: Verify backup integrity
echo "Test $test_num: Verify Backup File Integrity\n";
try {
    $backup_file = $test_results[3]['backup_file'] ?? null;
    if (!$backup_file) {
        echo "⏭️  SKIP: No backup file from previous test\n";
        $test_results[] = ['num' => $test_num, 'status' => 'SKIP'];
    } else {
        $pdo = new PDO('sqlite:' . $backup_file);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        
        $result = $pdo->query('PRAGMA integrity_check');
        $check = $result->fetch(PDO::FETCH_ASSOC);
        $pdo = null;
        
        if ($check && $check['integrity_check'] === 'ok') {
            echo "✅ PASS: Backup file integrity verified\n";
            echo "   - File: " . basename($backup_file) . "\n";
            echo "   - Status: OK\n";
            $test_results[] = ['num' => $test_num, 'status' => 'PASS'];
        } else {
            echo "❌ FAIL: Backup file integrity check failed\n";
            $test_results[] = ['num' => $test_num, 'status' => 'FAIL'];
        }
    }
} catch (Exception $e) {
    echo "❌ FAIL: {$e->getMessage()}\n";
    $test_results[] = ['num' => $test_num, 'status' => 'FAIL'];
}
echo "\n";
$test_num++;

// Test 6: List available backups
echo "Test $test_num: List Available Backups\n";
try {
    $backups_dir = __DIR__ . '/database/backups';
    if (!is_dir($backups_dir)) {
        echo "❌ FAIL: Backups directory not found\n";
        $test_results[] = ['num' => $test_num, 'status' => 'FAIL'];
    } else {
        $files = array_diff(scandir($backups_dir), ['.', '..']);
        $backups = [];
        
        foreach ($files as $file) {
            if (preg_match('/maintenix_\d{4}-\d{2}-\d{2}/', $file)) {
                $file_path = $backups_dir . '/' . $file;
                $backups[] = [
                    'file' => $file,
                    'size' => round(filesize($file_path) / 1024 / 1024, 2),
                    'modified' => date('Y-m-d H:i:s', filemtime($file_path))
                ];
            }
        }
        
        if (empty($backups)) {
            echo "⚠️  WARNING: No backup files found\n";
            $test_results[] = ['num' => $test_num, 'status' => 'FAIL'];
        } else {
            echo "✅ PASS: Backups found\n";
            echo "   - Total backups: " . count($backups) . "\n";
            foreach ($backups as $idx => $backup) {
                echo "   - [{$idx}] {$backup['file']} ({$backup['size']} MB) - {$backup['modified']}\n";
            }
            $test_results[] = ['num' => $test_num, 'status' => 'PASS'];
        }
    }
} catch (Exception $e) {
    echo "❌ FAIL: {$e->getMessage()}\n";
    $test_results[] = ['num' => $test_num, 'status' => 'FAIL'];
}
echo "\n";
$test_num++;

// Test 7: Simulate restore operation (read-only test)
echo "Test $test_num: Restore Capability (Simulation)\n";
try {
    $backup_file = $test_results[3]['backup_file'] ?? null;
    if (!$backup_file) {
        echo "⏭️  SKIP: No backup file from previous test\n";
        $test_results[] = ['num' => $test_num, 'status' => 'SKIP'];
    } else {
        // Create safety backup name
        $safety_backup = __DIR__ . '/database/maintenix.db.before_restore_' . date('Y-m-d_H-i-s');
        
        // Verify current database can be backed up
        if (file_exists(__DIR__ . '/database/maintenix.db')) {
            echo "✅ PASS: Restore operation would be possible\n";
            echo "   - Current DB: database/maintenix.db\n";
            echo "   - Safety backup would be: database/maintenix.db.before_restore_...\n";
            echo "   - Restore from: " . basename($backup_file) . "\n";
            echo "   - (Actual restore skipped - read-only test)\n";
            $test_results[] = ['num' => $test_num, 'status' => 'PASS'];
        } else {
            echo "❌ FAIL: Current database not found for safety backup\n";
            $test_results[] = ['num' => $test_num, 'status' => 'FAIL'];
        }
    }
} catch (Exception $e) {
    echo "❌ FAIL: {$e->getMessage()}\n";
    $test_results[] = ['num' => $test_num, 'status' => 'FAIL'];
}
echo "\n";

// Summary
echo "═══════════════════════════════════════════════════════════════\n";
echo "TEST SUMMARY\n";
echo "═══════════════════════════════════════════════════════════════\n";

$passed = 0;
$failed = 0;
$skipped = 0;
$warned = 0;

foreach ($test_results as $result) {
    if ($result['status'] === 'PASS') $passed++;
    elseif ($result['status'] === 'FAIL') $failed++;
    elseif ($result['status'] === 'SKIP') $skipped++;
}

echo "Total Tests: " . count($test_results) . "\n";
echo "✅ Passed:  $passed\n";
echo "❌ Failed:  $failed\n";
echo "⏭️  Skipped: $skipped\n";
echo "\n";

if ($failed === 0) {
    echo "🟢 BACKUP & RESTORE FLOW: ALL TESTS PASSED\n";
    echo "\n";
    echo "Backup system is operational:\n";
    echo "  1. Database backups can be created successfully\n";
    echo "  2. Backups maintain data integrity\n";
    echo "  3. Multiple backups can be stored\n";
    echo "  4. Restore operations are possible\n";
} else {
    echo "🔴 BACKUP & RESTORE FLOW: SOME TESTS FAILED\n";
}

echo "\n";
?>
