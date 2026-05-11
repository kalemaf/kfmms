<?php
/**
 * Lightweight automation test runner for CMMS.
 *
 * Usage:
 *   php tests/run_tests.php
 */

if (PHP_SAPI !== 'cli') {
    echo "This test runner must be executed from the command line.\n";
    exit(1);
}

// Use in-memory SQLite to avoid requiring a MySQL server for test execution.
putenv('APP_ENV=production');
putenv('ENABLE_DEBUG_PAGES=true');
putenv('DEBUG_MODE=true');
putenv('DEVELOPER_BYPASS_LICENSE=true');
putenv('APP_URL=https://127.0.0.1');
putenv('APP_SECRET=test-production-secret-please-change');
putenv('DB_TYPE=sqlite');
putenv('DB_FILE=:memory:');
putenv('SESSION_SAVE_PATH=' . __DIR__ . '/sessions');
putenv('LOG_DIR=' . __DIR__ . '/logs');
putenv('LOG_RETENTION_DAYS=7');

require_once __DIR__ . '/../config.inc.php';
require_once __DIR__ . '/../common.inc.php';
require_once __DIR__ . '/../libraries/inventory_manager.php';

$results = [
    'passed' => 0,
    'failed' => 0,
    'skipped' => 0,
];

function report($name, $pass, $message = '') {
    global $results;
    if ($pass) {
        echo "[PASS] $name\n";
        $results['passed']++;
    } else {
        echo "[FAIL] $name";
        if ($message !== '') {
            echo ": $message";
        }
        echo "\n";
        $results['failed']++;
    }
}

function assert_true($condition, $name, $message = '') {
    report($name, $condition === true, $message);
}

function assert_equals($expected, $actual, $name) {
    report($name, $expected === $actual, "Expected " . var_export($expected, true) . ", got " . var_export($actual, true));
}

function assert_contains_file($filePath, $needle, $name) {
    if (!file_exists($filePath)) {
        report($name, false, "File not found: $filePath");
        return;
    }
    $content = file_get_contents($filePath);
    report($name, strpos($content, $needle) !== false, "Missing '$needle' in $filePath");
}

// Ensure key helper functions load
assert_true(function_exists('parse_bool'), 'Helper parse_bool exists');
assert_true(function_exists('generate_license_key'), 'Helper generate_license_key exists');
assert_true(function_exists('csrf_input_tag'), 'Helper csrf_input_tag exists');
assert_true(function_exists('create_purchase_request'), 'Purchase request helper exists');
assert_true(function_exists('approve_purchase_request'), 'Purchase request approval helper exists');
assert_true(function_exists('create_purchase_order'), 'Purchase order helper exists');

// Validate helper logic
assert_true(parse_bool('true'), 'parse_bool(true) returns true');
assert_true(!parse_bool('false'), 'parse_bool(false) returns false');
assert_true(parse_bool('yes'), 'parse_bool(yes) returns true');
assert_true(!parse_bool('no'), 'parse_bool(no) returns false');

$licenseKey = generate_license_key();
assert_true(is_string($licenseKey), 'generate_license_key returns a string');
assert_true(strlen($licenseKey) === 16, 'generate_license_key returns 16 characters');
assert_true(preg_match('/^[A-Z0-9]{16}$/', $licenseKey) === 1, 'generate_license_key is uppercase alphanumeric');

$token = generate_csrf_token();
assert_true(is_string($token), 'generate_csrf_token returns a string');
assert_true(verify_csrf_token($token), 'verify_csrf_token accepts generated token');
assert_true(!verify_csrf_token('invalid-token'), 'verify_csrf_token rejects invalid token');
assert_true(defined('APP_ENV') && APP_ENV === 'production', 'APP_ENV is production for validation tests');
assert_true($debug_mode === false, 'Debug mode is disabled in production');
assert_true($debug_pages_enabled === false, 'Debug pages are disabled in production');
assert_true($developer_bypass_license === false, 'Developer bypass license mode is disabled in production');
assert_true(!is_debug_pages_enabled(), 'is_debug_pages_enabled() returns false in production');

$plans = get_subscription_plans();
assert_true(is_array($plans), 'get_subscription_plans returns array');
assert_true(isset($plans['trial'], $plans['basic'], $plans['professional'], $plans['enterprise']), 'Subscription plans include trial/basic/professional/enterprise');

// Static coverage for critical flow files
assert_contains_file(__DIR__ . '/../auth.php', 'csrf_input_tag()', 'Auth page includes CSRF protection');
assert_contains_file(__DIR__ . '/../license_gate.php', 'validate_license_key', 'License gate validates license keys');
assert_contains_file(__DIR__ . '/../purchase_request.php', 'approve_purchase_request(', 'Purchase request page uses approve_purchase_request helper');
assert_contains_file(__DIR__ . '/../work_order.php', 'send_permission_request_notification', 'Work order page sends permission request notifications for approvals');
assert_contains_file(__DIR__ . '/../work_order.php', 'reduce_spare_inventory', 'Work order page contains spare inventory reduction flow');

// File syntax checks for key production files
$filesToLint = [
    'config.inc.php',
    'common.inc.php',
    'auth.php',
    'license_gate.php',
    'purchase_request.php',
    'work_order.php',
    'inventory/purchase_orders.php',
];

foreach ($filesToLint as $file) {
    $path = __DIR__ . '/../' . $file;
    $output = null;
    $returnVar = null;
    exec("php -l " . escapeshellarg($path) . " 2>&1", $output, $returnVar);
    assert_true($returnVar === 0, "PHP syntax: $file", implode(' ', $output));
}

// Wrap-up
$summary = sprintf("\nTests completed: %d passed, %d failed.\n", $results['passed'], $results['failed']);
echo $summary;

exit($results['failed'] > 0 ? 1 : 0);
