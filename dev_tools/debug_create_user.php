<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Simulate a POST request for admin_roles.php create user
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

ob_start();
require 'admin_roles.php';
$output = ob_get_clean();

file_put_contents('debug_create_user_output.html', $output);
echo "Wrote debug_create_user_output.html\n";
echo "--- MESSAGE SEARCH ---\n";
if (preg_match('/<div class="alert alert-([^"]+)">(.*?)<\/div>/s', $output, $matches)) {
    echo "alert type: " . $matches[1] . "\n";
    echo "message: " . strip_tags($matches[2]) . "\n";
} else {
    echo "no alert found\n";
}

// Check whether the user was inserted
require 'config.inc.php';
$stmt = $connection->prepare('SELECT username, email, role, company_id FROM users WHERE username = ? LIMIT 1');
if ($stmt) {
    if ($db_type === 'sqlite') {
        $stmt->bindParam(1, $_POST['username'], PDO::PARAM_STR);
        $stmt->execute();
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
    } else {
        $stmt->bind_param('s', $_POST['username']);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
    }
    if ($row) {
        echo "USER FOUND: " . json_encode($row) . "\n";
    } else {
        echo "USER NOT FOUND\n";
    }
} else {
    echo "Failed to prepare select statement\n";
}
