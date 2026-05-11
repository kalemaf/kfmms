<?php
/**
 * Test inventory reduction after work order completion
 * Verifies consumables and spares are properly reduced
 */

require_once 'config.inc.php';
require_once 'libraries/inventory_manager.php';
require_once 'spare_integration_functions.php';

echo "<h3>Inventory Reduction Test</h3>";

if (!$connection) {
    die("Database connection failed");
}

// Get a pending work order
$wo_query = $connection->query("SELECT wo_id, equipment, description FROM work_orders WHERE wo_status IN ('Pending', 'Open', 'Assigned') LIMIT 1");
if (!$wo_query) {
    die("Failed to query work orders");
}

$wo_row = $wo_query->fetch_assoc();
if (!$wo_row) {
    echo "<p style='color:red'>No pending work orders found. Create one first.</p>";
    exit;
}

$wo_id = $wo_row['wo_id'];
echo "<p><strong>Testing Work Order #$wo_id:</strong> " . htmlspecialchars($wo_row['description']) . "</p>";

// Get consumables for this WO
$cons_query = $connection->query("SELECT * FROM work_order_consumables WHERE work_order_id = $wo_id");
$consumables_linked = [];
if ($cons_query) {
    while ($row = $cons_query->fetch_assoc()) {
        $consumables_linked[] = $row;
    }
}

echo "<h4>Consumables Linked to WO #$wo_id:</h4>";
if (empty($consumables_linked)) {
    echo "<p style='color:orange'>⚠ No consumables linked to this work order</p>";
} else {
    echo "<table border='1' cellpadding='10'>";
    echo "<tr><th>Consumable ID</th><th>Qty Required</th><th>Unit Cost</th><th>Notes</th></tr>";
    foreach ($consumables_linked as $c) {
        echo "<tr>";
        echo "<td>" . $c['consumable_id'] . "</td>";
        echo "<td>" . $c['quantity_required'] . "</td>";
        echo "<td>" . $c['unit_cost'] . "</td>";
        echo "<td>" . htmlspecialchars($c['notes']) . "</td>";
        echo "</tr>";
    }
    echo "</table>";
}

// Get spares for this WO
$spares_query = $connection->query("SELECT * FROM work_order_spares WHERE wo_id = $wo_id");
$spares_linked = [];
if ($spares_query) {
    while ($row = $spares_query->fetch_assoc()) {
        $spares_linked[] = $row;
    }
}

echo "<h4>Spares Linked to WO #$wo_id:</h4>";
if (empty($spares_linked)) {
    echo "<p style='color:orange'>⚠ No spares linked to this work order</p>";
} else {
    echo "<table border='1' cellpadding='10'>";
    echo "<tr><th>Spare ID</th><th>Qty Used</th></tr>";
    foreach ($spares_linked as $s) {
        echo "<tr>";
        echo "<td>" . $s['spare_id'] . "</td>";
        echo "<td>" . $s['quantity_used'] . "</td>";
        echo "</tr>";
    }
    echo "</table>";
}

// Get consumable and spare inventory BEFORE completion
echo "<h4>Current Stock BEFORE Completion:</h4>";

$cons_list = [];
$cons_stock_query = $connection->query("SELECT id, name, current_stock FROM consumables");
if ($cons_stock_query) {
    while ($row = $cons_stock_query->fetch_assoc()) {
        $cons_list[] = $row;
    }
}

echo "<p><strong>Consumables:</strong></p>";
if (empty($cons_list)) {
    echo "<p style='color:orange'>No consumables in system</p>";
} else {
    echo "<table border='1' cellpadding='5'>";
    echo "<tr><th>ID</th><th>Name</th><th>Stock</th></tr>";
    foreach ($cons_list as $c) {
        echo "<tr><td>" . $c['id'] . "</td><td>" . htmlspecialchars($c['name']) . "</td><td>" . $c['current_stock'] . "</td></tr>";
    }
    echo "</table>";
}

$spare_list = [];
$spare_stock_query = $connection->query("SELECT id, part_name, quantity FROM equipment_spares LIMIT 10");
if ($spare_stock_query) {
    while ($row = $spare_stock_query->fetch_assoc()) {
        $spare_list[] = $row;
    }
}

echo "<p><strong>Equipment Spares (first 10):</strong></p>";
if (empty($spare_list)) {
    echo "<p style='color:orange'>No equipment spares in system</p>";
} else {
    echo "<table border='1' cellpadding='5'>";
    echo "<tr><th>ID</th><th>Part Name</th><th>Qty</th></tr>";
    foreach ($spare_list as $s) {
        echo "<tr><td>" . $s['id'] . "</td><td>" . htmlspecialchars($s['part_name']) . "</td><td>" . $s['quantity'] . "</td></tr>";
    }
    echo "</table>";
}

echo "<h4>⚠️ Action Required</h4>";
echo "<p>To test inventory reduction:</p>";
echo "<ol>";
echo "<li>Add consumables and spares to WO #$wo_id using the work order form</li>";
echo "<li>Set work order status to 'Completed'</li>";
echo "<li>Click 'Complete Work Order' button</li>";
echo "<li>Verify consumable and spare quantities are reduced</li>";
echo "</ol>";

echo "<p><a href='work_order.php?id=" . $wo_id . "' class='btn btn-primary'>Edit WO #" . $wo_id . "</a></p>";
?>
