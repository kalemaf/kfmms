<?php
/**
 * Migrate existing work orders into artisan_work_order_assignments.
 *
 * This script finds work orders where mechanic_id references an artisan_id or artisan.user_id
 * and inserts missing artisan_work_order_assignments rows for them. It is intended to
 * integrate older work orders into the artisan performance dashboard.
 */

require_once 'config.inc.php';
session_start();

$tenant_id = isset($_GET['tenant_id']) ? (int)$_GET['tenant_id'] : ($_SESSION['tenant_id'] ?? 1);
if ($tenant_id <= 0) {
    $tenant_id = 1;
}

$output = [];
try {
    $stmt = $pdo->prepare(
        "SELECT wo_id, tenant_id, mechanic_id, submit_date, created_at
         FROM work_orders
         WHERE tenant_id = ?
           AND mechanic_id IS NOT NULL"
    );
    $stmt->execute([$tenant_id]);
    $workOrders = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $processed = 0;
    $inserted = 0;
    $skipped = 0;
    $unmatched = 0;

    $artisanLookup = $pdo->prepare(
        "SELECT artisan_id
         FROM artisans
         WHERE tenant_id = ?
           AND (artisan_id = ? OR user_id = ?)
         LIMIT 1"
    );

    $assignmentExists = $pdo->prepare(
        "SELECT 1
         FROM artisan_work_order_assignments
         WHERE tenant_id = ?
           AND artisan_id = ?
           AND work_order_id = ?"
    );

    $insertAssignment = $pdo->prepare(
        "INSERT INTO artisan_work_order_assignments
         (tenant_id, artisan_id, work_order_id, assignment_date, created_at, updated_at)
         VALUES (?, ?, ?, ?, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)"
    );

    $updateLastAssigned = $pdo->prepare(
        "UPDATE artisans
         SET last_assigned_date = ?
         WHERE tenant_id = ?
           AND artisan_id = ?
           AND (last_assigned_date IS NULL OR last_assigned_date < ?)"
    );

    foreach ($workOrders as $workOrder) {
        $processed++;
        $mechanicId = (int)$workOrder['mechanic_id'];

        $artisanLookup->execute([$tenant_id, $mechanicId, $mechanicId]);
        $artisan = $artisanLookup->fetch(PDO::FETCH_ASSOC);

        if (!$artisan) {
            $unmatched++;
            continue;
        }

        $artisanId = (int)$artisan['artisan_id'];
        $assignmentExists->execute([$tenant_id, $artisanId, $workOrder['wo_id']]);
        if ($assignmentExists->fetchColumn()) {
            $skipped++;
            continue;
        }

        $assignmentDate = $workOrder['submit_date'] ?: $workOrder['created_at'];
        if (empty($assignmentDate)) {
            $assignmentDate = date('Y-m-d H:i:s');
        }

        $insertAssignment->execute([$tenant_id, $artisanId, $workOrder['wo_id'], $assignmentDate]);
        $updateLastAssigned->execute([$assignmentDate, $tenant_id, $artisanId, $assignmentDate]);
        $inserted++;
    }

    $output[] = "Tenant ID: $tenant_id";
    $output[] = "Processed work orders: $processed";
    $output[] = "Inserted assignments: $inserted";
    $output[] = "Skipped existing assignments: $skipped";
    $output[] = "Unmatched mechanic_id values: $unmatched";
} catch (Exception $e) {
    $output[] = 'Error: ' . $e->getMessage();
}

header('Content-Type: text/plain');
echo implode("\n", $output);
