<?php
require_once __DIR__ . '/config.inc.php';
require_once __DIR__ . '/common.inc.php';
require_once __DIR__ . '/libraries/inventory_manager.php';

// Set up session
$_SESSION['tenant_id'] = 1;
$_SESSION['user_id'] = 1;
$_SESSION['user'] = 'testuser';
$_SESSION['role'] = 'admin';

echo "Testing Purchase Request Form Submission\n";
echo str_repeat("=", 70) . "\n\n";

// Get warehouse and site data
$tenant_id = 1;
$sites = query_to_array("SELECT id, full_location FROM sites_locations WHERE is_active = 1 AND tenant_id = $tenant_id LIMIT 1");
$warehouses = query_to_array("SELECT id, warehouse_name FROM warehouses WHERE is_active = 1 AND tenant_id = $tenant_id LIMIT 1");

if (empty($sites)) {
    echo "[✗] No sites/locations found\n";
    exit(1);
}
if (empty($warehouses)) {
    echo "[✗] No warehouses found\n";
    exit(1);
}

$site_id = $sites[0]['id'];
$warehouse_id = $warehouses[0]['id'];

echo "Test Data:\n";
echo "  Site/Location: {$sites[0]['full_location']} (id=$site_id)\n";
echo "  Warehouse: {$warehouses[0]['warehouse_name']} (id=$warehouse_id)\n\n";

// Simulate form POST
$_SERVER['REQUEST_METHOD'] = 'POST';
$_GET['action'] = 'create';

$_POST = [
    'form_action' => 'draft',
    'request_type' => 'Stock',
    'priority' => 'normal',
    'required_by_date' => date('Y-m-d', strtotime('+7 days')),
    'department' => 'Test Department',
    'site_location_id' => (string)$site_id,
    'warehouse_id' => (string)$warehouse_id,
    'linked_work_order' => '',
    'project_code' => 'TEST-001',
    'budget_code' => 'BUD-001',
    'expense_type' => 'OpEx',
    'justification' => 'Test PR for warehouse integration',
    'notes' => 'Additional test notes',
    'items' => json_encode([
        [
            'part_id' => 1,
            'description' => 'Test Part',
            'quantity' => 5,
            'unit_of_measure' => 'EA',
            'unit_cost' => 100.00,
            'vendor_id' => 1
        ]
    ])
];

echo "Form POST Data:\n";
foreach ($_POST as $key => $value) {
    if ($key === 'items') {
        echo "  $key: " . substr($value, 0, 50) . "...\n";
    } else {
        echo "  $key: $value\n";
    }
}

echo "\n" . str_repeat("-", 70) . "\n";
echo "Processing form submission...\n\n";

// Now include and run the purchase_request.php form processing
ob_start();

// Mock get_current_user_info if needed
function get_current_user_info() {
    return [
        'id' => $_SESSION['user_id'] ?? 1,
        'username' => $_SESSION['user'] ?? 'testuser',
        'email' => 'test@test.com'
    ];
}

// Extract just the form processing logic from purchase_request.php
$current_user = get_current_user_info();
$user_id = intval($current_user['id'] ?? 0);
$user_name = $current_user['username'] ?? 'Unknown';
$user_role = $_SESSION['role'] ?? '';
$connection = $GLOBALS['c'];

if ($user_id > 0 && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $form_action = trim($_POST['form_action']);
    
    if (in_array($form_action, ['draft', 'submit'], true)) {
        $required_by_date = trim($_POST['required_by_date'] ?? '');
        $priority = trim($_POST['priority'] ?? 'normal');
        $status = $form_action === 'submit' ? 'submitted' : 'draft';
        $request_type = trim($_POST['request_type'] ?? 'Stock');
        $department = trim($_POST['department'] ?? '');
        $site_location_id = intval($_POST['site_location_id'] ?? 0);
        $warehouse_id = intval($_POST['warehouse_id'] ?? 0);
        $project_code = trim($_POST['project_code'] ?? '');
        $budget_code = trim($_POST['budget_code'] ?? '');
        $expense_type = trim($_POST['expense_type'] ?? 'OpEx');
        $justification = trim($_POST['justification'] ?? '');
        $notes_text = trim($_POST['notes'] ?? '');
        
        $items = json_decode($_POST['items'] ?? '[]', true);
        $clean_items = [];
        
        foreach ($items as $item) {
            $clean_items[] = [
                'part_id' => intval($item['part_id'] ?? 0),
                'quantity' => floatval($item['quantity'] ?? 0),
                'unit_cost' => floatval($item['unit_cost'] ?? 0),
                'description' => trim($item['description'] ?? ''),
                'unit_of_measure' => trim($item['unit_of_measure'] ?? 'EA'),
                'vendor_id' => intval($item['vendor_id'] ?? 0)
            ];
        }
        
        echo "[→] Creating purchase request...\n";
        echo "    - site_location_id: $site_location_id\n";
        echo "    - warehouse_id: $warehouse_id\n";
        echo "    - Items count: " . count($clean_items) . "\n\n";
        
        // Get site location name
        $site_location_name = '';
        if ($site_location_id > 0) {
            $site_result = $connection->query("SELECT full_location FROM sites_locations WHERE id = $site_location_id AND tenant_id = $tenant_id");
            if ($site_result && $row = $site_result->fetch_assoc()) {
                $site_location_name = $row['full_location'];
            }
        }
        
        // Get warehouse name
        $warehouse_name = '';
        if ($warehouse_id > 0) {
            $wh_result = $connection->query("SELECT warehouse_name FROM warehouses WHERE id = $warehouse_id AND tenant_id = $tenant_id");
            if ($wh_result && $row = $wh_result->fetch_assoc()) {
                $warehouse_name = $row['warehouse_name'];
            }
        }
        
        $notes = "Request Type: {$request_type}\n" .
                 "Department: {$department}\n" .
                 "Site / Location: {$site_location_name}\n" .
                 "Warehouse: {$warehouse_name}\n" .
                 "Project Code: {$project_code}\n" .
                 "Budget Code: {$budget_code}\n" .
                 "Expense Type: {$expense_type}\n\n" .
                 "Justification:\n{$justification}\n\n" .
                 "Additional Notes:\n{$notes_text}";
        
        try {
            $created_pr_id = create_purchase_request(
                $user_id,
                $clean_items,
                $required_by_date,
                $priority,
                $status,
                $notes,
                $department,
                '',  // cost_center
                $site_location_id,
                $warehouse_id,
                '',  // linked_work_order
                $project_code,
                $budget_code,
                '',  // gl_account
                $expense_type,
                $justification,
                $connection
            );
            
            if ($created_pr_id) {
                echo "[✓] Purchase Request created successfully!\n";
                echo "    PR ID: $created_pr_id\n\n";
                
                // Verify data was saved
                $verify_stmt = $connection->query("SELECT id, pr_number, warehouse_id, site_location_id, tenant_id FROM purchase_requests WHERE id = $created_pr_id");
                if ($verify_stmt && $pr_data = $verify_stmt->fetch(PDO::FETCH_ASSOC)) {
                    echo "[✓] Data verification:\n";
                    echo "    - PR Number: " . $pr_data['pr_number'] . "\n";
                    echo "    - warehouse_id: " . $pr_data['warehouse_id'] . "\n";
                    echo "    - site_location_id: " . $pr_data['site_location_id'] . "\n";
                    echo "    - tenant_id: " . $pr_data['tenant_id'] . "\n";
                    
                    if ($pr_data['warehouse_id'] == $warehouse_id && 
                        $pr_data['site_location_id'] == $site_location_id && 
                        $pr_data['tenant_id'] == $tenant_id) {
                        echo "\n[✓] ALL DATA SAVED CORRECTLY!\n";
                    } else {
                        echo "\n[✗] Data mismatch!\n";
                    }
                }
            } else {
                echo "[✗] Failed to create purchase request\n";
            }
        } catch (Exception $e) {
            echo "[✗] Error: " . $e->getMessage() . "\n";
        }
    }
}

ob_end_clean();

echo "\n" . str_repeat("=", 70) . "\n";
echo "TEST COMPLETE\n";

?>
