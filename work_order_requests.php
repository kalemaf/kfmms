<?php
require_once 'config.inc.php';
require_once 'common.inc.php';
if (file_exists(__DIR__ . '/libraries/predictive_maintenance.php')) {
    require_once __DIR__ . '/libraries/predictive_maintenance.php';
}
if (file_exists(__DIR__ . '/libraries/predictive_integration.php')) {
    require_once __DIR__ . '/libraries/predictive_integration.php';
}

if (basename($_SERVER['PHP_SELF']) === basename(__FILE__)) {
    require_once 'title.php';
}

$currentUserRole = $_SESSION['role'] ?? '';
$currentUserId = $_SESSION['user_id'] ?? null;
$canApprove = in_array($currentUserRole, ['maintenance manager', 'supervisor', 'manager', 'admin', 'developer'], true);
$message = '';

if (!function_exists('send_notification_email')) {
    function send_notification_email($to, $subject, $body, $from = null, $from_name = null, $attachment = null, $attachment_name = null) {
        global $SMTP_ENABLED, $SMTP_HOST, $SMTP_PORT, $SMTP_USER, $SMTP_PASS, $SMTP_SECURE, $SMTP_FROM_EMAIL, $SMTP_FROM_NAME;

        $from_email = $from ?: ($SMTP_FROM_EMAIL ?? 'no-reply@example.com');
        $from_name = $from_name ?: ($SMTP_FROM_NAME ?? 'Maintenix');

        global $TEMP_DISABLE_SMTP_FOR_TESTS;
        $GLOBALS['EMAIL_SEND_ERROR'] = '';
        if (!empty($SMTP_ENABLED) && empty($TEMP_DISABLE_SMTP_FOR_TESTS)) {
            $autoload = __DIR__ . DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR . 'autoload.php';
            if (file_exists($autoload)) {
                require_once $autoload;
                try {
                    $mail = new PHPMailer\PHPMailer\PHPMailer(true);
                    $mail->isSMTP();
                    $mail->Host = $SMTP_HOST;
                    $mail->Port = !empty($SMTP_PORT) ? (int)$SMTP_PORT : 25;
                    if (!empty($SMTP_USER)) {
                        $mail->SMTPAuth = true;
                        $mail->Username = $SMTP_USER;
                        $mail->Password = $SMTP_PASS;
                    } else {
                        $mail->SMTPAuth = false;
                    }
                    if (!empty($SMTP_SECURE)) {
                        $mail->SMTPSecure = $SMTP_SECURE;
                    }
                    $mail->Timeout = 5;
                    $mail->SMTPDebug = 0;
                    $mail->setFrom($from_email, $from_name);
                    $mail->addAddress($to);
                    $mail->Subject = $subject;
                    $mail->Body = $body;
                    $mail->AltBody = $body;
                    if (!empty($attachment) && !empty($attachment_name)) {
                        $mail->addStringAttachment($attachment, $attachment_name);
                    }
                    $mail->send();
                    @file_put_contents(__DIR__ . '/logs/email_send.log', date('c') . " - SMTP send OK to {$to}\n", FILE_APPEND);
                    return true;
                } catch (\Exception $e) {
                    $GLOBALS['EMAIL_SEND_ERROR'] = $e->getMessage();
                    @file_put_contents(__DIR__ . '/logs/email_send.log', date('c') . " - SMTP send FAILED to {$to}: " . $e->getMessage() . "\n", FILE_APPEND);
                }
            }
        }

        $headers = 'From: ' . $from_email . "\r\n" . 'X-Mailer: PHP/' . phpversion();
        if (empty($attachment) || empty($attachment_name)) {
            return @mail($to, $subject, $body, $headers);
        }

        $separator = md5(time());
        $eol = "\r\n";
        $headers .= "MIME-Version: 1.0" . $eol;
        $headers .= "Content-Type: multipart/mixed; boundary=\"" . $separator . "\"" . $eol;

        $message = "--" . $separator . $eol;
        $message .= "Content-Type: text/plain; charset=iso-8859-1" . $eol;
        $message .= "Content-Transfer-Encoding: 7bit" . $eol . $eol;
        $message .= $body . $eol . $eol;
        $message .= "--" . $separator . $eol;
        $message .= "Content-Type: application/pdf; name=\"" . $attachment_name . "\"" . $eol;
        $message .= "Content-Transfer-Encoding: base64" . $eol;
        $message .= "Content-Disposition: attachment; filename=\"" . $attachment_name . "\"" . $eol . $eol;
        $message .= chunk_split(base64_encode($attachment)) . $eol . $eol;
        $message .= "--" . $separator . "--";

        return @mail($to, $subject, $body, $headers);
    }
}

function sanitize_sql_value($value) {
    return str_replace("'", "''", $value);
}

// Handle approval or rejection actions for managers/supervisors
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && ($canApprove || $_POST['action'] === 'submit_request')) {
    $action = trim($_POST['action']);

    if ($action === 'approve' || $action === 'reject') {
        if (!$canApprove) {
            $message = 'You are not authorized to approve requests.';
        } else {
            $requestId = intval($_POST['request_id'] ?? 0);
            $notes = sanitize_input(trim($_POST['approval_notes'] ?? ''));
            $status = $action === 'approve' ? 'Approved' : 'Rejected';
            $approvalDate = date('Y-m-d H:i:s');

            $requestRow = null;
            if ($requestId > 0) {
                $stmt = safe_query_row("SELECT * FROM work_order_requests WHERE request_id = " . intval($requestId) . " LIMIT 1");
                if ($stmt) {
                    $requestRow = $stmt;
                }
            }

            if (!$requestRow) {
                $message = 'Work order request could not be found.';
            } elseif ($requestRow['status'] !== 'Pending Approval') {
                $message = 'Only pending requests can be approved or rejected.';
            } else {
                if ($status === 'Approved') {
                    $submitDate = $requestRow['submit_date'] ?: date('Y-m-d');
                    $slaDueDate = $requestRow['sla_due_date'] ?: date('Y-m-d', strtotime($submitDate . ' +1 day'));
                    $equipmentValue = sanitize_sql_value((string)($requestRow['equipment'] ?? ''));
                    $tenantId = (int)($_SESSION['tenant_id'] ?? 0);
                    $sql = "INSERT INTO work_orders (tenant_id, request_id, descriptive_text, requestor, equipment, description, priority, wo_status, submit_date, needed_date, sla_due_date, response_time, resolution_time, updated) VALUES (" . $tenantId . ", " . intval($requestRow['request_id']) . ", '" . sanitize_sql_value($requestRow['descriptive_text']) . "', '" . sanitize_sql_value($requestRow['requestor']) . "', '" . $equipmentValue . "', '" . sanitize_sql_value($requestRow['description']) . "', " . intval($requestRow['priority']) . ", 'Approved', '" . sanitize_sql_value($submitDate) . "', " . ($requestRow['needed_date'] ? "'" . sanitize_sql_value($requestRow['needed_date']) . "'" : 'NULL') . ", '" . sanitize_sql_value($slaDueDate) . "', NULL, NULL, " . get_current_timestamp_sql() . ")";
                    if ($connection->query($sql)) {
                        $workOrderId = $connection->lastInsertId();
                        $updateSql = "UPDATE work_order_requests SET status = 'Approved', approval_by_id = " . intval($currentUserId) . ", approval_date = '" . sanitize_sql_value($approvalDate) . "', approval_notes = '" . sanitize_sql_value($notes) . "', work_order_id = " . intval($workOrderId) . " WHERE request_id = " . intval($requestId);
                        $connection->query($updateSql);
                        $message = 'Request approved and work order #' . intval($workOrderId) . ' created.';
                    } else {
                        $message = 'Failed to create work order during approval: ' . $connection->error;
                    }
                } else {
                    $updateSql = "UPDATE work_order_requests SET status = 'Rejected', approval_by_id = " . intval($currentUserId) . ", approval_date = '" . sanitize_sql_value($approvalDate) . "', approval_notes = '" . sanitize_sql_value($notes) . "' WHERE request_id = " . intval($requestId);
                    if ($connection->query($updateSql)) {
                        $message = 'Request rejected successfully.';
                    } else {
                        $message = 'Failed to reject request: ' . $connection->error;
                    }
                }
            }
        }
    } elseif ($action === 'submit_request') {
        $descriptive_text = sanitize_input(trim($_POST['descriptive_text'] ?? ''));
        $requestor = sanitize_input(trim($_POST['requestor'] ?? ''));
        $equipment = sanitize_input(trim($_POST['equipment'] ?? ''));
        $description = sanitize_input(trim($_POST['description'] ?? ''));
        $needed_date = trim($_POST['needed_date'] ?? '');
        $priority = max(1, intval($_POST['priority'] ?? 1));
        $submit_date = date('Y-m-d');
        $sla_due_date = date('Y-m-d', strtotime($submit_date . ' +1 day'));

        $needed_date_sql = ($needed_date === '' || $needed_date === '0000-00-00') ? 'NULL' : "'" . sanitize_sql_value($needed_date) . "'";
        $tenantId = (int)($_SESSION['tenant_id'] ?? 0);

        $insertSql = "INSERT INTO work_order_requests (tenant_id, requestor_user_id, descriptive_text, requestor, equipment, description, priority, submit_date, needed_date, sla_due_date, status, updated) VALUES (" . $tenantId . ", " . intval($currentUserId) . ", '" . sanitize_sql_value($descriptive_text) . "', '" . sanitize_sql_value($requestor) . "', '" . sanitize_sql_value($equipment) . "', '" . sanitize_sql_value($description) . "', " . intval($priority) . ", '" . sanitize_sql_value($submit_date) . "', " . $needed_date_sql . ", '" . sanitize_sql_value($sla_due_date) . "', 'Pending Approval', " . get_current_timestamp_sql() . ")";

        if ($connection->query($insertSql)) {
            $message = 'Work order request submitted successfully and is pending approval.';
        } else {
            $message = 'Failed to submit request: ' . $connection->error;
        }
    }
}

$equipmentOptions = query_to_array("SELECT id, description FROM equipment ORDER BY description");

$allowedStatuses = ['Pending Approval', 'Approved', 'Rejected', 'Canceled'];
$statusFilter = trim($_GET['status'] ?? '');
$statusSql = '';
$filterClauses = [];
if ($statusFilter !== '' && in_array($statusFilter, $allowedStatuses, true)) {
    $filterClauses[] = "r.status = '" . sanitize_sql_value($statusFilter) . "'";
}
if (!$canApprove && $currentUserId) {
    $filterClauses[] = "r.requestor_user_id = " . intval($currentUserId);
}
if (!empty($filterClauses)) {
    $statusSql = 'WHERE ' . implode(' AND ', $filterClauses);
}

$requests = [];
$requestQuery = "SELECT r.request_id, r.descriptive_text, r.requestor, r.equipment, r.description, r.submit_date, r.needed_date, r.priority, r.status, r.approval_date, r.approval_notes, r.work_order_id, " .
                "COALESCE(r.sla_due_date, date(r.submit_date, '+1 day')) AS sla_due_date, r.approval_by_id, r.updated, " .
                "COALESCE(e.description, CAST(r.equipment AS TEXT)) AS equipment_name, u.username AS approver_name " .
                "FROM work_order_requests r " .
                "LEFT JOIN equipment e ON r.equipment = e.id " .
                "LEFT JOIN users u ON r.approval_by_id = u.user_id " .
                $statusSql .
                " ORDER BY r.submit_date DESC LIMIT 200";
$requestRows = safe_query_all($requestQuery);
foreach ($requestRows as $row) {
    $requests[] = $row;
}
?>
<style>
    body { font-family: Arial, sans-serif; margin: 20px; }
    .page-header { margin-bottom: 20px; }
    .alert { padding: 12px 15px; border-radius: 4px; margin-bottom: 20px; }
    .alert-success { background: #e9f7ef; border: 1px solid #c3eed7; color: #155724; }
    .alert-danger { background: #f8d7da; border: 1px solid #f5c6cb; color: #721c24; }
    .card { border: 1px solid #ddd; border-radius: 6px; padding: 18px; margin-bottom: 24px; background: #fff; }
    .card h2, .card h3 { margin-top: 0; }
    .form-row { display: grid; gap: 12px; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); margin-bottom: 14px; }
    label { display: block; margin-bottom: 6px; font-weight: 600; }
    input[type="text"], input[type="date"], input[type="number"], select, textarea { width: 100%; padding: 8px 10px; border: 1px solid #ccc; border-radius: 4px; }
    textarea { resize: vertical; min-height: 100px; }
    button { background: #007bff; color: white; border: none; border-radius: 4px; padding: 10px 16px; cursor: pointer; }
    button:hover { background: #0069d9; }
    table { width: 100%; border-collapse: collapse; }
    th, td { padding: 10px 12px; border: 1px solid #ddd; }
    th { background: #f8f9fa; text-align: left; }
    .badge { display: inline-block; padding: 4px 10px; border-radius: 12px; font-size: 12px; }
    .badge-pending { background: #ffeeba; color: #856404; }
    .badge-completed { background: #d4edda; color: #155724; }
    .badge-approved { background: #cce5ff; color: #004085; }
    .badge-rejected { background: #f8d7da; color: #721c24; }
    .badge-warning { background: #fff3cd; color: #856404; }
    .button-link { text-decoration: none; color: white; background: #28a745; padding: 6px 10px; border-radius: 4px; }
</style>
</head>
<body>
    <div class="page-header">
        <h1>Work Order Requests</h1>
        <p>Submit a new work request and approve requests before generating the work order number.</p>
    </div>

    <?php if ($message): ?>
        <div class="alert <?php echo strpos($message, 'Failed') === false ? 'alert-success' : 'alert-danger'; ?>">
            <?php echo htmlspecialchars($message); ?>
        </div>
    <?php endif; ?>

    <div class="card">
        <h2>Create Work Order Request</h2>
        <form method="post">
            <input type="hidden" name="action" value="submit_request">
            <div class="form-row">
                <div>
                    <label for="descriptive_text">Work Needed</label>
                    <input type="text" id="descriptive_text" name="descriptive_text" required placeholder="Summary of the required work">
                </div>
                <div>
                    <label for="requestor">Requestor</label>
                    <input type="text" id="requestor" name="requestor" required placeholder="Your name or department" value="<?php echo htmlspecialchars($_SESSION['username'] ?? ''); ?>">
                </div>
            </div>
            <div class="form-row">
                <div>
                    <label for="equipment">Equipment</label>
                    <select id="equipment" name="equipment">
                        <option value="">-- Select equipment --</option>
                        <?php foreach ($equipmentOptions as $equip): ?>
                            <option value="<?php echo (int)$equip['id']; ?>"><?php echo htmlspecialchars($equip['description']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label for="priority">Priority</label>
                    <select id="priority" name="priority">
                        <option value="1">1 - Normal</option>
                        <option value="2">2 - High</option>
                        <option value="3">3 - Urgent</option>
                    </select>
                </div>
                <div>
                    <label for="needed_date">Needed By</label>
                    <input type="date" id="needed_date" name="needed_date">
                </div>
            </div>
            <div class="form-row">
                <div style="grid-column: span 2;">
                    <label for="description">Detailed Work Description</label>
                    <textarea id="description" name="description" placeholder="Describe the work to be done"></textarea>
                </div>
            </div>
            <button type="submit">Submit Work Request</button>
        </form>
    </div>

    <div class="card">
        <h2>Work Order Requests</h2>
        <form method="get" style="margin-bottom: 16px; display: inline-block;">
            <label for="status_filter">Filter by status</label>
            <select id="status_filter" name="status" onchange="this.form.submit()" style="margin-left: 10px;">
                <option value="">All statuses</option>
                <?php foreach ($allowedStatuses as $status): ?>
                    <option value="<?php echo htmlspecialchars($status); ?>"<?php echo $statusFilter === $status ? ' selected' : ''; ?>><?php echo htmlspecialchars($status); ?></option>
                <?php endforeach; ?>
            </select>
        </form>

        <table>
            <thead>
                <tr>
                    <th>Request ID</th>
                    <th>Summary</th>
                    <th>Requestor</th>
                    <th>Equipment</th>
                    <th>Status</th>
                    <th>Submitted</th>
                    <th>SLA Due</th>
                    <th>Work Order</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($requests)): ?>
                    <tr>
                        <td colspan="9" style="text-align:center; color:#666;">No work order requests found.</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($requests as $req): ?>
                        <tr>
                            <td>REQ #<?php echo (int)$req['request_id']; ?></td>
                            <td><?php echo htmlspecialchars($req['descriptive_text']); ?></td>
                            <td><?php echo htmlspecialchars($req['requestor']); ?></td>
                            <td><?php echo htmlspecialchars($req['equipment_name'] ?: 'Not set'); ?></td>
                            <td>
                                <?php
                                    $statusClass = 'badge-pending';
                                    if ($req['status'] === 'Completed') { $statusClass = 'badge-completed'; }
                                    elseif ($req['status'] === 'Approved') { $statusClass = 'badge-approved'; }
                                    elseif ($req['status'] === 'Rejected') { $statusClass = 'badge-rejected'; }
                                ?>
                                <span class="badge <?php echo $statusClass; ?>"><?php echo htmlspecialchars($req['status']); ?></span>
                            </td>
                            <td><?php echo htmlspecialchars($req['submit_date'] ?? '-'); ?></td>
                            <td><?php echo htmlspecialchars($req['sla_due_date'] ?? '-'); ?></td>
                            <td>
                                <?php if (!empty($req['work_order_id'])): ?>
                                    <a class="button-link" href="work_order.php?edit=<?php echo (int)$req['work_order_id']; ?>">WO #<?php echo (int)$req['work_order_id']; ?></a>
                                <?php else: ?>
                                    -
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($canApprove && $req['status'] === 'Pending Approval'): ?>
                                    <form method="post" style="display:inline-block; margin-right: 4px;">
                                        <input type="hidden" name="action" value="approve">
                                        <input type="hidden" name="request_id" value="<?php echo (int)$req['request_id']; ?>">
                                        <button type="submit">Approve</button>
                                    </form>
                                    <form method="post" style="display:inline-block;">
                                        <input type="hidden" name="action" value="reject">
                                        <input type="hidden" name="request_id" value="<?php echo (int)$req['request_id']; ?>">
                                        <button type="submit" style="background:#bd2130;">Reject</button>
                                    </form>
                                <?php elseif ($req['status'] === 'Rejected'): ?>
                                    <span style="color:#721c24;">Rejected</span>
                                <?php else: ?>
                                    <span style="color:#666;">No action</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</body>
</html>
