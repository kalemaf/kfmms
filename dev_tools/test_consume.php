<?php
error_reporting(E_ALL);
ini_set('display_errors', '1');

include 'config/database.php';
include 'libraries/inventory_manager.php';

$connection = get_db_connection();

// Get the most recently created work order
$result = $connection->query('SELECT wo_id FROM work_orders ORDER BY wo_id DESC LIMIT 1');
$row = $result->fetch(PDO::FETCH_ASSOC);
$wo_id = $row['wo_id'];

echo "Testing consume_work_order_consumables with WO ID: $wo_id\n";

try {
    $result = consume_work_order_consumables($wo_id, $connection);
    echo "Result: " . var_export($result, true) . "\n";
} catch (Exception $e) {
    echo "Exception: " . $e->getMessage() . "\n";
    echo "Trace: " . $e->getTraceAsString() . "\n";
}
?>
