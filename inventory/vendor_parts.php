<?php
require_once("../config.inc.php");
if (session_status() === PHP_SESSION_NONE) {
    session_save_path($session_save_path);
    session_start();
}
if (!isset($_SESSION['user'])) {
    header("Location: ../auth.php");
    exit;
}

require_once("../common.inc.php");
require_once("../libraries/inventory_manager.php");

$vendor_id = isset($_GET['vendor_id']) ? intval($_GET['vendor_id']) : 0;
if ($vendor_id <= 0) {
    header("Location: vendor_management.php");
    exit;
}

$vendor = get_vendor_details($vendor_id, $connection);
if (!$vendor) {
    $_SESSION['error'] = "Supplier not found.";
    header("Location: vendor_management.php");
    exit;
}

// Attempt to map parts to this supplier using vendor_reference or supplier part metadata.
$vendor_code = $connection->real_escape_string($vendor['vendor_code'] ?? '');
$vendor_name = $connection->real_escape_string($vendor['vendor_name'] ?? '');

$parts_query = "SELECT * FROM parts_master";
$conditions = [];
if ($vendor_code !== '') {
    $conditions[] = "vendor_reference = '$vendor_code'";
    $conditions[] = "vendor_reference LIKE '%$vendor_code%'";
}
if ($vendor_name !== '') {
    $conditions[] = "vendor_reference = '$vendor_name'";
    $conditions[] = "vendor_reference LIKE '%$vendor_name%'";
}
if (!empty($conditions)) {
    $parts_query .= " WHERE (" . implode(' OR ', $conditions) . ") ORDER BY part_name ASC";
} else {
    $parts_query .= " WHERE 0 ORDER BY part_name ASC";
}

$parts_result = $connection->query($parts_query);
$vendor_parts = [];
if ($parts_result) {
    while ($row = $parts_result->fetch_assoc()) {
        $vendor_parts[] = $row;
    }
}

$title = "Supplier Parts";
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($title); ?> - CMMS</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { background: #f5f5f5; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; }
        .container { max-width: 1200px; margin: 20px auto; padding: 0 15px; }
        .header { background: white; padding: 25px; border-radius: 8px; margin-bottom: 25px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        .header h1 { color: #333; margin-bottom: 5px; font-weight: 700; }
        .header p { color: #666; margin-bottom: 0; }
        .card { border-radius: 10px; }
        .table th, .table td { vertical-align: middle; }
        .badge-status { text-transform: uppercase; letter-spacing: 0.04em; }
    </style>
</head>
<body>
<div class="container">
    <div class="header">
        <h1><i class="fas fa-boxes"></i> Supplier Parts</h1>
        <p>Parts linked to <?php echo htmlspecialchars($vendor['vendor_name']); ?> (<?php echo htmlspecialchars($vendor['vendor_code']); ?>).</p>
    </div>

    <?php if (!empty($vendor_parts)): ?>
        <div class="card mb-4">
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-striped table-hover">
                        <thead>
                            <tr>
                                <th>Part Code</th>
                                <th>Part Name</th>
                                <th>Manufacturer</th>
                                <th>Supplier Part #</th>
                                <th>Vendor Reference</th>
                                <th>Unit Cost</th>
                                <th>Stock</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($vendor_parts as $part): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($part['part_code']); ?></td>
                                    <td><?php echo htmlspecialchars($part['part_name']); ?></td>
                                    <td><?php echo htmlspecialchars($part['manufacturer'] ?? 'N/A'); ?></td>
                                    <td><?php echo htmlspecialchars($part['supplier_part_number'] ?? 'N/A'); ?></td>
                                    <td><?php echo htmlspecialchars($part['vendor_reference'] ?? 'N/A'); ?></td>
                                    <td><?php echo number_format(floatval($part['unit_cost'] ?? 0), 2); ?></td>
                                    <td><?php echo intval($part['total_on_hand'] ?? 0); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    <?php else: ?>
        <div class="alert alert-info">
            <i class="fas fa-info-circle"></i> No parts are currently linked to this supplier. Use the inventory parts master to add supplier part numbers or vendor references.
        </div>
    <?php endif; ?>

    <div class="d-flex justify-content-between gap-2">
        <a href="vendor_management.php" class="btn btn-secondary"><i class="fas fa-chevron-left"></i> Back to Suppliers</a>
        <a href="../vendors.php" class="btn btn-primary"><i class="fas fa-users"></i> Supplier Directory</a>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
