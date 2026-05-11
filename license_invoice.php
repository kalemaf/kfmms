<?php
require_once 'config.inc.php';
session_save_path($session_save_path);
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once 'common.inc.php';

if (empty($_SESSION['user'])) {
    header('Location: auth.php');
    exit;
}

$user_id = $_SESSION['user_id'] ?? null;
$company_id = get_user_company_id($user_id);
$license = null;
$history = [];
$error = null;

if (empty($company_id)) {
    $error = 'Unable to identify your company profile. Please contact support.';
} else {
    $license = get_active_company_license($company_id);
    $history = get_license_audit_history($company_id, 50);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>License & Billing History - KFMMS</title>
    <style>
        body {
            margin: 0;
            font-family: Inter, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
            background: #0b1320;
            color: #f8fafc;
            min-height: 100vh;
        }
        .page-wrap {
            max-width: 960px;
            margin: 0 auto;
            padding: 32px;
        }
        .card {
            background: rgba(15, 23, 42, 0.92);
            border: 1px solid rgba(148, 163, 184, 0.12);
            border-radius: 24px;
            padding: 28px;
            margin-bottom: 24px;
            box-shadow: 0 24px 48px rgba(0,0,0,0.3);
        }
        h1, h2 {
            margin: 0 0 16px;
            font-weight: 700;
        }
        .button-bar {
            display: flex;
            gap: 12px;
            flex-wrap: wrap;
            margin-bottom: 20px;
        }
        .btn {
            padding: 12px 24px;
            border-radius: 999px;
            border: none;
            text-decoration: none;
            color: #0f172a;
            background: #38bdf8;
            display: inline-block;
            font-weight: 700;
        }
        .status-error {
            background: rgba(248, 113, 113, 0.12);
            border: 1px solid rgba(248, 113, 113, 0.28);
            color: #fecaca;
            padding: 16px;
            border-radius: 16px;
            margin-bottom: 24px;
        }
        .summary-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 16px;
            margin-bottom: 24px;
        }
        .summary-item {
            background: rgba(255, 255, 255, 0.04);
            border: 1px solid rgba(148, 163, 184, 0.12);
            border-radius: 16px;
            padding: 18px;
        }
        .summary-item strong {
            display: block;
            margin-bottom: 8px;
            color: #cbd5e1;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 12px;
        }
        thead th {
            text-align: left;
            font-size: 0.95rem;
            color: #cbd5e1;
            padding: 14px 12px;
            border-bottom: 1px solid rgba(148, 163, 184, 0.16);
        }
        tbody td {
            padding: 14px 12px;
            border-bottom: 1px solid rgba(255, 255, 255, 0.05);
            color: #e2e8f0;
            vertical-align: top;
        }
        .empty-state {
            color: #94a3b8;
        }
        .note {
            color: #cbd5e1;
            margin-top: 12px;
        }
    </style>
</head>
<body>
    <div class="page-wrap">
        <div class="button-bar">
            <a href="license_gate.php" class="btn">Back to License Gate</a>
            <a href="index.php" class="btn">Return to Dashboard</a>
        </div>

        <div class="card">
            <h1>License & Billing History</h1>
            <p class="note">Review the current license status and recent billing actions for your organization.</p>
        </div>

        <?php if ($error): ?>
            <div class="status-error"><?php echo htmlspecialchars($error); ?></div>
        <?php else: ?>
            <div class="card summary-grid">
                <div class="summary-item">
                    <strong>Current License</strong>
                    <div><?php echo htmlspecialchars($license['license_type'] ?? 'None'); ?></div>
                </div>
                <div class="summary-item">
                    <strong>Seats</strong>
                    <div><?php echo htmlspecialchars($license['purchased_seats'] ?? '0'); ?></div>
                </div>
                <div class="summary-item">
                    <strong>Expires</strong>
                    <div><?php echo !empty($license['expires_at']) ? htmlspecialchars(date('M j, Y', strtotime($license['expires_at']))) : 'Permanent'; ?></div>
                </div>
                <div class="summary-item">
                    <strong>Status</strong>
                    <div><?php echo !empty($license) ? 'Active' : 'No active license'; ?></div>
                </div>
            </div>

            <div class="card">
                <h2>Audit History</h2>
                <?php if (empty($history)): ?>
                    <p class="empty-state">No license audit records found yet.</p>
                <?php else: ?>
                    <table>
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Action</th>
                                <th>Details</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($history as $row): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars(date('M j, Y g:i A', strtotime($row['timestamp']))); ?></td>
                                    <td><?php echo htmlspecialchars($row['action']); ?></td>
                                    <td><?php echo nl2br(htmlspecialchars($row['details'] ?? '')); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>