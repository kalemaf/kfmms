<?php
session_start();

require_once 'config.inc.php';
require_once 'common.inc.php';

echo "SESSION['tenant_id']: " . ($_SESSION['tenant_id'] ?? 'NOT SET') . "\n";
echo "Attempting to add equipment...\n\n";

// Simulate form submission
$_POST['description'] = 'Test ' . time();
$_POST['parent_id'] = 0;
$_POST['location'] = '';
$_POST['status'] = '';
$_POST['manufacturer'] = '';
$_POST['model'] = '';
$_POST['serial_number'] = '';
$_FILES['csv_import']['name'] = '';
$_REQUEST['REQUEST_METHOD'] = 'POST';

// Get tenant_id
$tenant_id = (int)($_SESSION['tenant_id'] ?? 1);
echo "Using tenant_id: $tenant_id\n\n";

// Insert
try {
    $stmt = $connection->prepare("
        INSERT INTO equipment 
        (parent_id, description, location, status, manufacturer, model, serial_number, photo, tenant_id)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");
    
    $result = $stmt->execute([0, $_POST['description'], '', '', '', '', '', '', $tenant_id]);
    echo "Insert result: " . ($result ? "SUCCESS" : "FAILED") . "\n";
    
    if ($result) {
        $equipment_id = $connection->lastInsertId();
        echo "Equipment ID: $equipment_id\n\n";
        
        // Now query it back
        echo "Querying back from database...\n";
        $sql = "SELECT * FROM equipment WHERE tenant_id=$tenant_id ORDER BY id DESC LIMIT 1";
        $result = $connection->query($sql);
        $row = $result->fetch(PDO::FETCH_ASSOC);
        echo "Result: " . json_encode($row) . "\n\n";
        
        // Now try with safe_query_all
        echo "Using safe_query_all...\n";
        $equipment = safe_query_all("SELECT * FROM equipment WHERE tenant_id=$tenant_id ORDER BY id DESC LIMIT 1");
        echo "Count: " . count($equipment) . "\n";
        if (count($equipment) > 0) {
            echo "First result: " . json_encode($equipment[0]) . "\n";
        } else {
            echo "NO RESULTS FROM safe_query_all\n";
        }
    }
} catch (Exception $e) {
    echo "Exception: " . $e->getMessage() . "\n";
}
?>
