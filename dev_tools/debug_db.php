<?php
/**
 * Debug: Check database structure
 */
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once 'config.inc.php';

echo "<h1>Database Debug</h1>";

// Check DB type
echo "<p>DB Type: " . DB_TYPE . "</p>";

// List all tables
echo "<h2>Tables</h2>";
$tables = $connection->query("SELECT name FROM sqlite_master WHERE type='table' ORDER BY name")->fetchAll(PDO::FETCH_COLUMN);
echo "<pre>";
print_r($tables);
echo "</pre>";

// Check companies
echo "<h2>Companies</h2>";
$companies = $connection->query("SELECT id, company_name FROM companies ORDER BY id")->fetchAll(PDO::FETCH_ASSOC);
echo "<pre>";
print_r($companies);
echo "</pre>";

// Check users
echo "<h2>Users</h2>";
$users = $connection->query("SELECT user_id, username, company_id, tenant_id FROM users ORDER BY user_id")->fetchAll(PDO::FETCH_ASSOC);
echo "<pre>";
print_r($users);
echo "</pre>";

// Check work_orders table structure
echo "<h2>work_orders table info</h2>";
$wo_info = $connection->query("PRAGMA table_info(work_orders)")->fetchAll(PDO::FETCH_ASSOC);
echo "<pre>";
print_r($wo_info);
echo "</pre>";

// Check work_orders data
echo "<h2>Work Orders</h2>";
$wo = $connection->query("SELECT wo_id, tenant_id, descriptive_text FROM work_orders ORDER BY wo_id")->fetchAll(PDO::FETCH_ASSOC);
echo "<pre>";
print_r($wo);
echo "</pre>";