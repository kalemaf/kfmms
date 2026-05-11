<?php
include 'auth.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Not authenticated']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

$alert_id = isset($_POST['alert_id']) ? (int)$_POST['alert_id'] : 0;

if (!$alert_id) {
    echo json_encode(['success' => false, 'message' => 'Invalid alert ID']);
    exit;
}

try {
    $sql = "UPDATE lifecycle_alerts SET
            is_acknowledged = 1,
            acknowledged_by = ?,
            acknowledged_at = NOW()
            WHERE id = ? AND is_acknowledged = 0";

    $stmt = mysqli_prepare($connection, $sql);
    mysqli_stmt_bind_param($stmt, 'si', $_SESSION['username'], $alert_id);

    if (mysqli_stmt_execute($stmt)) {
        $affected_rows = mysqli_stmt_affected_rows($stmt);
        if ($affected_rows > 0) {
            echo json_encode(['success' => true, 'message' => 'Alert acknowledged successfully']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Alert not found or already acknowledged']);
        }
    } else {
        echo json_encode(['success' => false, 'message' => 'Database error: ' . mysqli_error($connection)]);
    }

    mysqli_stmt_close($stmt);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}
?>