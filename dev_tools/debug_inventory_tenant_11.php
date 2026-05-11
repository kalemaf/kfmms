<?php
// Simulate user logged in with tenant_id = 11
$_SESSION['tenant_id'] = 11;
$_SESSION['user_id'] = 1; // dummy user_id

include 'config.inc.php';
include 'common.inc.php';
include 'libraries/inventory_manager.php';

echo "<h1>Inventory Data for Tenant 11</h1>";
echo "<p>Current tenant_id: " . tenant_id() . "</p>";

// Check parts
$parts = get_parts($connection);
echo "<p>Parts visible: " . count($parts) . "</p>";

// Check consumables
$consumables = get_consumables($connection);
echo "<p>Consumables visible: " . count($consumables) . "</p>";

// Check transactions
$query = "SELECT COUNT(*) as cnt FROM inventory_transactions";
$query = apply_tenant_filter($query);
$result = $connection->query($query);
$row = $result->fetch(PDO::FETCH_ASSOC);
echo "<p>Transactions visible: " . $row['cnt'] . "</p>";
?>