<?php
// inventory/stock_management.php
require_once("../config.inc.php");
session_save_path($session_save_path);
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (!isset($_SESSION['user'])) {
    header("Location: ../auth.php");
    exit;
}

$part_id = isset($_GET['part_id']) ? intval($_GET['part_id']) : 0;
if ($part_id <= 0) {
    die('<div style="color:red;">Invalid part ID.</div>');
}

// Fetch part details
$query = "SELECT * FROM parts_master WHERE id = $part_id";
$query = apply_tenant_filter($query);
$result = $connection->query($query);
$part = $result ? $result->fetch_assoc() : null;
if (!$part) {
    die('<div style="color:red;">Part not found.</div>');
}

// Fetch stock transactions (example table: stock_transactions)
$transactions = [];
$sql = "SELECT 
    transaction_date as date,
    transaction_type as type,
    quantity_change as quantity,
    CONCAT(reference_type, ' ', COALESCE(reference_id, '')) as reference,
    notes
    FROM inventory_transactions 
    WHERE part_id = $part_id 
    ORDER BY transaction_date DESC";
$sql = apply_tenant_filter($sql);
$res = $connection->query($sql);
if ($res) {
    while ($row = $res->fetch_assoc()) {
        $transactions[] = $row;
    }
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Stock Management - <?php echo htmlspecialchars($part['part_name']); ?></title>
    <link rel="stylesheet" href="../styles/nicetable.css">
    <style>
        body { font-family: 'Segoe UI', Arial, sans-serif; background: #f5f5f5; }
        .container { max-width: 800px; margin: 30px auto; background: #fff; padding: 24px; border-radius: 8px; box-shadow: 0 2px 8px #0001; }
        h1 { font-size: 1.5em; margin-bottom: 10px; }
        .part-info { margin-bottom: 20px; }
        table { width: 100%; border-collapse: collapse; }
        th, td { padding: 8px 12px; border: 1px solid #ddd; }
        th { background: #f0f0f0; }
    </style>
</head>
<body>
<div class="container">
    <h1>Stock Management: <?php echo htmlspecialchars($part['part_name']); ?></h1>
    <div class="part-info">
        <strong>Part Code:</strong> <?php echo htmlspecialchars($part['part_code']); ?><br>
        <strong>Description:</strong> <?php echo htmlspecialchars($part['description']); ?><br>
        <strong>Current Stock:</strong> <?php echo htmlspecialchars($part['current_stock'] ?? 'N/A'); ?>
    </div>
    <h2>Stock Transactions</h2>
    <?php if (empty($transactions)): ?>
        <div>No stock transactions found for this part.</div>
    <?php else: ?>
    <table>
        <tr>
            <th>Date</th>
            <th>Type</th>
            <th>Quantity</th>
            <th>Reference</th>
            <th>Notes</th>
        </tr>
        <?php foreach ($transactions as $txn): ?>
        <tr>
            <td><?php echo htmlspecialchars($txn['date'] ?? ''); ?></td>
            <td><?php echo htmlspecialchars($txn['type'] ?? ''); ?></td>
            <td><?php echo htmlspecialchars($txn['quantity'] ?? ''); ?></td>
            <td><?php echo htmlspecialchars($txn['reference'] ?? ''); ?></td>
            <td><?php echo htmlspecialchars($txn['notes'] ?? ''); ?></td>
        </tr>
        <?php endforeach; ?>
    </table>
    <?php endif; ?>
</div>
</body>
</html>
