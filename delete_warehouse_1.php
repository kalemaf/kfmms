<?php
require_once __DIR__ . '/config.inc.php';
require_once __DIR__ . '/common.inc.php';

if (PHP_SAPI !== 'cli') {
    echo "This script must be run from the command line.\n";
    exit(1);
}

if (!$db_available) {
    fwrite(STDERR, "Database unavailable: {$db_error}\n");
    exit(1);
}

$warehouseId = 1;
$connection->beginTransaction();
try {
    // Delete inventory references to the warehouse locations first
    $connection->exec("DELETE FROM stock_locales WHERE warehouse_location_id IN (SELECT id FROM warehouse_locations WHERE warehouse_id = $warehouseId)");
    $connection->exec("DELETE FROM goods_receipt_items WHERE gr_id IN (SELECT id FROM goods_receipts WHERE warehouse_location_id IN (SELECT id FROM warehouse_locations WHERE warehouse_id = $warehouseId))");
    $connection->exec("DELETE FROM goods_receipts WHERE warehouse_location_id IN (SELECT id FROM warehouse_locations WHERE warehouse_id = $warehouseId)");
    $connection->exec("DELETE FROM warehouse_locations WHERE warehouse_id = $warehouseId");
    $connection->exec("DELETE FROM warehouses WHERE id = $warehouseId");
    $connection->commit();
    echo "Deleted warehouse ID $warehouseId and its related rows.\n";
} catch (Exception $ex) {
    $connection->rollBack();
    fwrite(STDERR, "Failed to delete warehouse ID $warehouseId: " . $ex->getMessage() . "\n");
    exit(1);
}
