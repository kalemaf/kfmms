<?php
require_once('config.inc.php');
session_save_path($session_save_path);
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (!isset($_SESSION['user'])) {
    header("Location: auth.php");
    exit;
}

require_once('common.inc.php');

echo "<h1>Inventory Tenant Isolation Debug</h1>";
echo "<p>Current tenant_id: " . tenant_id() . "</p>";

// Check parts_master
$query = "SELECT COUNT(*) as cnt FROM parts_master";
$query = apply_tenant_filter($query);
$result = $connection->query($query);
$row = $result->fetch_assoc();
echo "<p>Parts master records for current tenant: " . $row['cnt'] . "</p>";

// Check consumables
$query = "SELECT COUNT(*) as cnt FROM consumables";
$query = apply_tenant_filter($query);
$result = $connection->query($query);
$row = $result->fetch_assoc();
echo "<p>Consumables records for current tenant: " . $row['cnt'] . "</p>";

// Check inventory_transactions
$query = "SELECT COUNT(*) as cnt FROM inventory_transactions";
$query = apply_tenant_filter($query);
$result = $connection->query($query);
$row = $result->fetch_assoc();
echo "<p>Inventory transactions for current tenant: " . $row['cnt'] . "</p>";

// Check total records without tenant filter
$query = "SELECT COUNT(*) as cnt FROM parts_master";
$result = $connection->query($query);
$row = $result->fetch_assoc();
echo "<p>Total parts master records (all tenants): " . $row['cnt'] . "</p>";

$query = "SELECT COUNT(*) as cnt FROM consumables";
$result = $connection->query($query);
$row = $result->fetch_assoc();
echo "<p>Total consumables records (all tenants): " . $row['cnt'] . "</p>";

$query = "SELECT COUNT(*) as cnt FROM inventory_transactions";
$result = $connection->query($query);
$row = $result->fetch_assoc();
echo "<p>Total inventory transactions (all tenants): " . $row['cnt'] . "</p>";
?>