<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

$_SERVER['REQUEST_METHOD'] = 'POST';
$_POST = [
    'action' => 'create_user',
    'company_id' => '0',
    'username' => 'debuguser_' . time(),
    'email' => 'debuguser_' . time() . '@example.com',
    'phone' => '(555) 123-4567',
    'role' => 'technician',
    'password' => 'DebugPass123!'
];

session_start();
$_SESSION['user'] = 'developer';
$_SESSION['role'] = 'developer';
$_SESSION['group'] = 'developer';
$_SESSION['email'] = 'developer@example.com';
$_SESSION['user_id'] = 1;

file_put_contents('debug_create_user2_input.txt', "REQUEST_METHOD=" . ($_SERVER['REQUEST_METHOD'] ?? 'N/A') . "\naction=" . ($_POST['action'] ?? 'N/A') . "\n");

ob_start();
require 'admin_roles.php';
$output = ob_get_clean();
file_put_contents('debug_create_user2_output.html', $output);

file_put_contents('debug_create_user2_summary.txt', "output_length=" . strlen($output) . "\n");

if (preg_match('/<div class="alert alert-([^"]+)">(.*?)<\/div>/s', $output, $matches)) {
    file_put_contents('debug_create_user2_summary.txt', "alert_type=" . $matches[1] . "\nmessage=" . strip_tags($matches[2]) . "\n", FILE_APPEND);
} else {
    file_put_contents('debug_create_user2_summary.txt', "alert=none\n", FILE_APPEND);
}
