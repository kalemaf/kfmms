<?php
/**
 * Simple Company Check
 */
require_once 'config.inc.php';

echo "<h1>Companies</h1>";

try {
    $result = $connection->query("SELECT company_id, name, email FROM companies");
    if ($result) {
        $companies = $result->fetchAll(PDO::FETCH_ASSOC);
        echo "<pre>";
        print_r($companies);
        echo "</pre>";
    } else {
        echo "No companies found or query failed";
    }
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}

echo "<h1>Users</h1>";
try {
    $result = $connection->query("SELECT user_id, username, email, company_id, tenant_id, role FROM users");
    if ($result) {
        $users = $result->fetchAll(PDO::FETCH_ASSOC);
        echo "<pre>";
        print_r($users);
        echo "</pre>";
    }
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}