<?php
/**
 * Professional Work Order View Page
 * Displays work order details with print functionality and company logo
 */

require_once 'config.inc.php';
require_once 'common.inc.php';
$user_role = $_SESSION['role'] ?? '';

$wo_id = (int)($_GET['wo_id'] ?? 0);
$work_order = null;
$message = '';

if ($wo_id > 0 && $connection) {
    $tenant_id = (int)($_SESSION['tenant_id'] ?? 1);
    $work_order = db_query_row_params(
        "SELECT wo.*, 
               COALESCE(e.description, wo.equipment) AS equipment_name,
               COALESCE(u.username, '') AS technician_name
        FROM work_orders wo
        LEFT JOIN equipment e ON wo.equipment = CAST(e.id AS CHAR)
        LEFT JOIN users u ON wo.mechanic_id = u.user_id
        WHERE wo.wo_id = ? AND wo.tenant_id = ?
        LIMIT 1",
        [$wo_id, $tenant_id]
    );
    if (!$work_order) {
        $message = 'Work order not found.';
    }
} else {
    $message = 'Invalid work order ID.';
}

if (!$work_order) {
    echo "<h2>Error</h2><p>$message</p><p><a href='index.php?nav=work_orders'>Back to Work Orders</a></p>";
    exit;
}

// Get spares used for this work order
$spares_used = [];
if ($connection) {
    $tenant_id = (int)($_SESSION['tenant_id'] ?? 1);
    $spares_used = db_query_all_params(
        "SELECT wos.spare_id, wos.quantity_used, COALESCE(es.part_name, '') AS spare_name
        FROM work_order_spares wos
        LEFT JOIN equipment_spares es ON wos.spare_id = es.id AND es.tenant_id = ?
        WHERE wos.wo_id = ? AND wos.tenant_id = ?
        ORDER BY spare_name",
        [$tenant_id, $wo_id, $tenant_id]
    );
}

// Handle print action
if (isset($_GET['print'])) {
    // Set headers for print
    header('Content-Type: text/html; charset=utf-8');
    echo '<!DOCTYPE html><html><head><title>Work Order WO #' . $wo_id . '</title>';
    echo '<style>
        body { 
            font-family: Arial, sans-serif; 
            margin: 10px; 
            font-size: 12px;
            line-height: 1.3;
        }
        .header { 
            text-align: center; 
            margin-bottom: 15px; 
        }
        .logo { 
            max-width: 100px; 
            margin-bottom: 5px;
        }
        .details { 
            margin: 10px 0; 
        }
        .section { 
            margin: 5px 0; 
            padding: 5px; 
            border: 1px solid #ccc;
            page-break-inside: avoid;
        }
        .section strong {
            display: inline-block;
            min-width: 120px;
        }
        @media print { 
            .no-print { display: none; }
            body { margin: 0.5cm; }
            .section { margin: 3px 0; padding: 3px; }
            * { -webkit-print-color-adjust: exact; color-adjust: exact; }
        }
    </style>';
    echo '</head><body>';
    // Print content
    echo '<div class="header">';
    echo '<img src="images/image.jpeg" alt="Company Logo" class="logo"><br>';
    echo '<h1 style="font-size: 18px; margin: 5px 0;">Work Order</h1>';
    echo '<h2 style="font-size: 16px; margin: 5px 0;">WO #' . $wo_id . '</h2>';
    echo '</div>';
    // Details in a more compact layout
    echo '<div class="details">';
    echo '<div class="section"><strong>Description:</strong> ' . htmlspecialchars($work_order['descriptive_text']) . '</div>';
    echo '<div class="section"><strong>Equipment:</strong> ' . htmlspecialchars($work_order['equipment_name']) . '</div>';
    echo '<div class="section"><strong>Requestor:</strong> ' . htmlspecialchars($work_order['requestor']) . '</div>';
    echo '<div class="section"><strong>Technician:</strong> ' . htmlspecialchars($work_order['technician_name'] ?: 'Unassigned') . '</div>';
    echo '<div class="section"><strong>Status:</strong> ' . htmlspecialchars($work_order['wo_status']) . '</div>';
    if (!empty($work_order['failure_mode'])) {
        echo '<div class="section"><strong>Failure Mode:</strong> ' . htmlspecialchars($work_order['failure_mode']) . '</div>';
    }
    if (!empty($work_order['maintenance_type'])) {
        echo '<div class="section"><strong>Maintenance Type:</strong> ' . htmlspecialchars($work_order['maintenance_type']) . '</div>';
    }
    echo '<div class="section"><strong>Priority:</strong> ' . (int)$work_order['priority'] . '</div>';
    echo '<div class="section"><strong>Submit Date:</strong> ' . htmlspecialchars($work_order['submit_date']) . '</div>';
    if ($work_order['needed_date']) echo '<div class="section"><strong>Needed Date:</strong> ' . htmlspecialchars($work_order['needed_date']) . '</div>';
    if ($work_order['est_hours']) echo '<div class="section"><strong>Estimated Hours:</strong> ' . (int)$work_order['est_hours'] . '</div>';
    if ($work_order['act_hours']) echo '<div class="section"><strong>Actual Hours:</strong> ' . (int)$work_order['act_hours'] . '</div>';
    if ($work_order['complete_date']) echo '<div class="section"><strong>Complete Date:</strong> ' . htmlspecialchars($work_order['complete_date']) . '</div>';
    if ($work_order['description']) echo '<div class="section"><strong>Description:</strong><br><div style="margin-left: 120px; white-space: pre-wrap; word-wrap: break-word;">' . htmlspecialchars($work_order['description']) . '</div></div>';
    if ($work_order['action']) echo '<div class="section"><strong>Action Taken:</strong><br><div style="margin-left: 120px; white-space: pre-wrap; word-wrap: break-word;">' . htmlspecialchars($work_order['action']) . '</div></div>';
    if ($work_order['coordinating_instructions']) echo '<div class="section"><strong>Coordinating Instructions:</strong><br><div style="margin-left: 120px; white-space: pre-wrap; word-wrap: break-word;">' . htmlspecialchars($work_order['coordinating_instructions']) . '</div></div>';
    echo '<div style="margin-top: 15px; border-top: 2px solid #ccc; padding-top: 10px;"><strong>SLA & Metrics</strong></div>';
    if ($work_order['sla_due_date']) echo '<div class="section"><strong>SLA Due Date:</strong> ' . htmlspecialchars($work_order['sla_due_date']) . '</div>';
    if ($work_order['down_time_hours']) echo '<div class="section"><strong>Down Time Hours:</strong> ' . (float)$work_order['down_time_hours'] . '</div>';
    if ($work_order['response_time']) echo '<div class="section"><strong>Response Time:</strong> ' . (float)$work_order['response_time'] . ' hours</div>';
    if ($work_order['resolution_time']) echo '<div class="section"><strong>Resolution Time:</strong> ' . (float)$work_order['resolution_time'] . ' hours</div>';
    echo '<div class="section"><strong>Audit Item:</strong> ' . ($work_order['audit_item'] ? 'Yes' : 'No') . '</div>';
    echo '<div style="margin-top: 15px; border-top: 2px solid #ccc; padding-top: 10px;"><strong>Approvals & Inspection</strong></div>';
    if ($work_order['inspected_by']) echo '<div class="section"><strong>Inspected By:</strong> ' . htmlspecialchars($work_order['inspected_by']) . '</div>';
    if ($work_order['approval']) echo '<div class="section"><strong>Approved By:</strong> ' . htmlspecialchars($work_order['approval']) . '</div>';
    if (!empty($spares_used)) {
        echo '<div style="margin-top: 15px; border-top: 2px solid #ccc; padding-top: 10px;"><strong>Spares Used</strong></div>';
        foreach ($spares_used as $spare) {
            $spare_name = !empty($spare['spare_name']) ? htmlspecialchars($spare['spare_name']) : 'Spare ID: ' . (int)$spare['spare_id'];
            echo '<div class="section"><strong>' . $spare_name . ':</strong> Qty ' . (int)$spare['quantity_used'] . '</div>';
        }
    }
    echo '</div>';
    echo '</body></html>';
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Work Order WO #<?php echo $wo_id; ?> - CMMS</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        body { background-color: #f8f9fa; }
        .wo-container { max-width: 800px; margin: 20px auto; background: white; padding: 30px; border-radius: 10px; box-shadow: 0 0 20px rgba(0,0,0,0.1); }
        .wo-header { text-align: center; margin-bottom: 30px; border-bottom: 2px solid #007bff; padding-bottom: 20px; }
        .wo-logo { max-width: 150px; margin-bottom: 15px; }
        .wo-title { color: #007bff; font-size: 2.5rem; margin: 0; }
        .wo-number { font-size: 1.5rem; color: #6c757d; }
        .wo-section { margin: 20px 0; padding: 15px; background: #f8f9fa; border-radius: 5px; }
        .wo-section h5 { color: #007bff; margin-bottom: 10px; border-bottom: 1px solid #dee2e6; padding-bottom: 5px; }
        .print-btn { position: fixed; top: 20px; right: 20px; background: #28a745; color: white; border: none; padding: 10px 15px; border-radius: 5px; cursor: pointer; }
        .print-btn:hover { background: #218838; }
        @media print {
            .print-btn, .no-print { display: none !important; }
            body { background: white; }
            .wo-container { box-shadow: none; margin: 0; }
        }
    </style>
</head>
<body>

<button class="print-btn" onclick="window.open('view_work_order.php?wo_id=<?php echo $wo_id; ?>&print=1', '_blank')">
    <i class="fas fa-print"></i> Print
</button>

<div class="wo-container">
    <div class="wo-header">
        <img src="images/image.jpeg" alt="Company Logo" class="wo-logo">
        <h1 class="wo-title">Work Order</h1>
        <div class="wo-number">WO #<?php echo $wo_id; ?></div>
    </div>

    <div class="wo-section">
        <h5>Basic Information</h5>
        <div class="row">
            <div class="col-md-6">
                <p><strong>Description:</strong> <?php echo htmlspecialchars($work_order['descriptive_text']); ?></p>
                <p><strong>Equipment:</strong> <?php echo htmlspecialchars($work_order['equipment_name']); ?></p>
                <p><strong>Requestor:</strong> <?php echo htmlspecialchars($work_order['requestor']); ?></p>
                <?php if (!empty($work_order['failure_mode'])): ?>
                    <p><strong>Failure Mode:</strong> <?php echo htmlspecialchars($work_order['failure_mode']); ?></p>
                <?php endif; ?>
                <?php if (!empty($work_order['maintenance_type'])): ?>
                    <p><strong>Maintenance Type:</strong> <?php echo htmlspecialchars($work_order['maintenance_type']); ?></p>
                <?php endif; ?>
            </div>
            <div class="col-md-6">
                <p><strong>Technician:</strong> <?php echo htmlspecialchars($work_order['technician_name'] ?: 'Unassigned'); ?></p>
                <p><strong>Status:</strong> <span class="badge bg-<?php
                    $status_colors = ['Pending Approval' => 'warning', 'Assigned' => 'info', 'Approved' => 'success', 'Suspended' => 'secondary', 'Completed' => 'success', 'Rejected' => 'danger', 'Hot Job' => 'danger'];
                    echo $status_colors[$work_order['wo_status']] ?? 'secondary';
                ?>"><?php echo htmlspecialchars($work_order['wo_status']); ?></span></p>
                <p><strong>Priority:</strong> <?php echo (int)$work_order['priority']; ?></p>
            </div>
        </div>
    </div>

    <div class="wo-section">
        <h5>Dates & Hours</h5>
        <div class="row">
            <div class="col-md-6">
                <p><strong>Submit Date:</strong> <?php echo htmlspecialchars($work_order['submit_date']); ?></p>
                <?php if ($work_order['needed_date']): ?>
                <p><strong>Needed Date:</strong> <?php echo htmlspecialchars($work_order['needed_date']); ?></p>
                <?php endif; ?>
            </div>
            <div class="col-md-6">
                <?php if ($work_order['est_hours']): ?>
                <p><strong>Estimated Hours:</strong> <?php echo (int)$work_order['est_hours']; ?></p>
                <?php endif; ?>
                <?php if ($work_order['act_hours']): ?>
                <p><strong>Actual Hours:</strong> <?php echo (int)$work_order['act_hours']; ?></p>
                <?php endif; ?>
                <?php if ($work_order['complete_date']): ?>
                <p><strong>Complete Date:</strong> <?php echo htmlspecialchars($work_order['complete_date']); ?></p>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <?php if ($work_order['description']): ?>
    <div class="wo-section">
        <h5>Description</h5>
        <p><?php echo nl2br(htmlspecialchars($work_order['description'])); ?></p>
    </div>
    <?php endif; ?>

    <?php if ($work_order['action']): ?>
    <div class="wo-section">
        <h5>Action Taken</h5>
        <p><?php echo nl2br(htmlspecialchars($work_order['action'])); ?></p>
    </div>
    <?php endif; ?>

    <?php if ($work_order['coordinating_instructions']): ?>
    <div class="wo-section">
        <h5>Coordinating Instructions</h5>
        <p><?php echo nl2br(htmlspecialchars($work_order['coordinating_instructions'])); ?></p>
    </div>
    <?php endif; ?>

    <div class="wo-section">
        <h5>SLA & Metrics</h5>
        <div class="row">
            <div class="col-md-6">
                <?php if ($work_order['sla_due_date']): ?>
                <p><strong>SLA Due Date:</strong> <?php echo htmlspecialchars($work_order['sla_due_date']); ?></p>
                <?php endif; ?>
                <?php if ($work_order['down_time_hours']): ?>
                <p><strong>Down Time Hours:</strong> <?php echo (float)$work_order['down_time_hours']; ?></p>
                <?php endif; ?>
            </div>
            <div class="col-md-6">
                <?php if ($work_order['response_time']): ?>
                <p><strong>Response Time:</strong> <?php echo (float)$work_order['response_time']; ?> hours</p>
                <?php endif; ?>
                <?php if ($work_order['resolution_time']): ?>
                <p><strong>Resolution Time:</strong> <?php echo (float)$work_order['resolution_time']; ?> hours</p>
                <?php endif; ?>
                <p><strong>Audit Item:</strong> <?php echo $work_order['audit_item'] ? 'Yes' : 'No'; ?></p>
            </div>
        </div>
    </div>

    <?php if (!empty($spares_used)): ?>
    <div class="wo-section">
        <h5>Spares Used</h5>
        <div class="row">
            <?php foreach ($spares_used as $spare): ?>
                <?php $spare_name = !empty($spare['spare_name']) ? htmlspecialchars($spare['spare_name']) : 'Spare ID: ' . (int)$spare['spare_id']; ?>
                <div class="col-md-6">
                    <p><strong><?php echo $spare_name; ?>:</strong> Qty <?php echo (int)$spare['quantity_used']; ?></p>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>

    <div class="text-center mt-4 no-print">
        <a href="index.php?nav=work_orders" class="btn btn-secondary">Back to Work Orders</a>
        <?php if ($user_role !== 'technician'): ?>
            <a href="index.php?nav=work_orders&edit=<?php echo $wo_id; ?>" class="btn btn-primary">Edit Work Order</a>
        <?php endif; ?>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>