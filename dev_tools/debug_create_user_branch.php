<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require 'config.inc.php';
require 'common.inc.php';

if (!$db_available) {
    die("DB unavailable\n");
}

$company_id = 0;
$username = 'debuguser_' . time();
$email = 'debuguser_' . time() . '@example.com';
$phone = '(555) 123-4567';
$role = 'technician';
$password = 'DebugPass123!';

$valid_roles = ['admin', 'maintenance manager', 'supervisor', 'technician', 'operator', 'developer'];

if (empty($username) || empty($email) || empty($password)) {
    die('ERROR: required fields missing\n');
}
if (!in_array($role, $valid_roles, true)) {
    die('ERROR: invalid role\n');
}

$check_query = "SELECT user_id FROM users WHERE username = ? OR email = ? LIMIT 1";
if ($db_type === 'sqlite') {
    $check = $connection->prepare($check_query);
    if (!$check) {
        die('ERROR: prepare failed for check user\n');
    }
    $check->bindParam(1, $username, PDO::PARAM_STR);
    $check->bindParam(2, $email, PDO::PARAM_STR);
    $res = $check->execute();
    var_dump('check execute', $res, $check->errorInfo());
    $user_exists = $check->fetch(PDO::FETCH_ASSOC) !== false;
    var_dump('user_exists', $user_exists);
    $check->closeCursor();
} else {
    die('Not sqlite');
}

if ($user_exists) {
    die('ERROR: user already exists\n');
}

$password_hash = password_hash($password, PASSWORD_BCRYPT);
$insert_query = "INSERT INTO users (username, email, password_hash, role, phone, company_id, is_active) VALUES (?, ?, ?, ?, ?, ?, 1)";
$stmt = $connection->prepare($insert_query);
if (!$stmt) {
    die('ERROR: prepare failed for insert\n');
}
$stmt->bindParam(1, $username, PDO::PARAM_STR);
$stmt->bindParam(2, $email, PDO::PARAM_STR);
$stmt->bindParam(3, $password_hash, PDO::PARAM_STR);
$stmt->bindParam(4, $role, PDO::PARAM_STR);
$stmt->bindParam(5, $phone, PDO::PARAM_STR);
$stmt->bindParam(6, $company_id, PDO::PARAM_INT);
$success = $stmt->execute();
var_dump('insert execute', $success, $stmt->errorInfo());
if ($success) {
    echo "Inserted user $username\n";
} else {
    echo "Insert failed\n";
}
