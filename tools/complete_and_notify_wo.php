<?php
// tools/complete_and_notify_wo.php
// Usage: php complete_and_notify_wo.php [wo_id]
require_once __DIR__ . '/../config.inc.php';

error_reporting(E_ALL);
ini_set('display_errors', '1');

$argv_id = isset($argv[1]) ? (int)$argv[1] : 0;

if ($argv_id > 0) {
    $wo_id = $argv_id;
} else {
    // find the most recent PM instance with a wo_id
    $r = mysqli_query($connection, "SELECT * FROM pm_instances WHERE wo_id IS NOT NULL ORDER BY id DESC LIMIT 1");
    if ($r && ($row = mysqli_fetch_assoc($r))) {
        $wo_id = (int)$row['wo_id'];
    } else {
        echo "NO_PM_INSTANCE_FOUND\n";
        exit(2);
    }
}

echo "TARGET_WO_ID:" . $wo_id . "\n";

// Mark work order completed
$u1 = "UPDATE work_orders SET wo_status='Completed', complete_date=CURDATE() WHERE wo_id=" . intval($wo_id);
if (!$connection->query($u1)) {
    echo "ERROR_UPDATING_WO:" . $connection->error . "\n";
    exit(3);
}
echo "WORK_ORDER_MARKED_COMPLETED\n";

// Update pm_instances
$u2 = "UPDATE pm_instances SET status='Completed', completed_date=CURDATE() WHERE wo_id=" . intval($wo_id);
$connection->query($u2);
echo "PM_INSTANCES_UPDATED_AFFECTED:" . $connection->affected_rows . "\n";

// Insert audit log
$actor = mysqli_real_escape_string($connection, 'automated-test');
$details = mysqli_real_escape_string($connection, 'Automated completion test');
$ins = "INSERT INTO audit_logs (actor, action, target_type, target_id, details, created_at) VALUES ('" . $actor . "', 'complete', 'work_order', " . intval($wo_id) . ", '" . $details . "', NOW())";
$connection->query($ins);
echo "AUDIT_LOG_INSERTED_ID:" . $connection->insert_id . "\n";

// Generate or fetch PDF
$pdfContent = null;
$printUrl = 'http://127.0.0.1:3000/print.php?wo_id=' . intval($wo_id);
$opts = array('http' => array('timeout' => 5));
$context = stream_context_create($opts);
@ $pdfContent = @file_get_contents($printUrl, false, $context);

if (!$pdfContent || strlen($pdfContent) < 100) {
    // fallback minimal PDF
    $pdfContent = "%PDF-1.4\n1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n3 0 obj\n<< /Type /Page /Parent 2 0 R /MediaBox [0 0 612 792] /Contents 4 0 R /Resources << /Font << /F1 5 0 R >> >> >>\nendobj\n4 0 obj\n<< /Length 44 >>\nstream\nBT\n/F1 12 Tf\n50 750 Td\n(Maintenix Test PDF for WO " . intval($wo_id) . ") Tj\nET\nendstream\nendobj\n5 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\nxref\n0 6\n0000000000 65535 f\n0000000009 00000 n\n0000000074 00000 n\n0000000133 00000 n\n0000000281 00000 n\n0000000377 00000 n\ntrailer\n<< /Size 6 /Root 1 0 R >>\nstartxref\n456\n%%EOF\n";
    echo "PDF_FALLBACK_USED\n";
} else {
    echo "PDF_FETCHED_BYTES:" . strlen($pdfContent) . "\n";
}

$attachDir = __DIR__ . '/../logs/attachments';
if (!is_dir($attachDir)) mkdir($attachDir, 0755, true);
$pdfPath = $attachDir . '/work_order_' . intval($wo_id) . '.pdf';
file_put_contents($pdfPath, $pdfContent);
echo "PDF_SAVED:" . $pdfPath . "\n";

// Send email using PHPMailer (vendor present)
$autoload = __DIR__ . '/../vendor/autoload.php';
if (file_exists($autoload)) require_once $autoload;
else {
    // try direct require as fallback
    if (file_exists(__DIR__ . '/../vendor/phpmailer/phpmailer/src/PHPMailer.php')) {
        require_once __DIR__ . '/../vendor/phpmailer/phpmailer/src/PHPMailer.php';
        require_once __DIR__ . '/../vendor/phpmailer/phpmailer/src/SMTP.php';
        require_once __DIR__ . '/../vendor/phpmailer/phpmailer/src/Exception.php';
    }
}

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

try {
    $mail = new PHPMailer(true);
    $mail->isSMTP();
    $mail->Host = $SMTP_HOST;
    $mail->Port = $SMTP_PORT;
    if (!empty($SMTP_SECURE)) $mail->SMTPSecure = $SMTP_SECURE;
    $mail->SMTPAuth = !empty($SMTP_USER);
    if (!empty($SMTP_USER)) { $mail->Username = $SMTP_USER; $mail->Password = $SMTP_PASS; }
    $mail->setFrom($SMTP_FROM_EMAIL, $SMTP_FROM_NAME);
    $mail->addAddress($admincontact);
    $mail->Subject = "[TEST AUTO] Work Order Completed - WO#" . intval($wo_id);
    $mail->Body = "Work Order " . intval($wo_id) . " has been marked completed by automated test.";
    $mail->addAttachment($pdfPath, basename($pdfPath));
    $sent = $mail->send();
    echo "EMAIL_SENT:" . ($sent ? '1' : '0') . "\n";
} catch (Exception $e) {
    echo "EMAIL_ERROR:" . $e->getMessage() . "\n";
}

// Print DB rows for verification
$r1 = mysqli_query($connection, "SELECT * FROM work_orders WHERE wo_id=" . intval($wo_id) . " LIMIT 1");
if ($r1 && ($w = mysqli_fetch_assoc($r1))) {
    echo "---WORK_ORDER---\n";
    foreach ($w as $k => $v) echo "$k: $v\n";
}

$r2 = mysqli_query($connection, "SELECT * FROM pm_instances WHERE wo_id=" . intval($wo_id));
if ($r2 && mysqli_num_rows($r2) > 0) {
    echo "---PM_INSTANCES---\n";
    while ($row = mysqli_fetch_assoc($r2)) {
        foreach ($row as $k => $v) echo "$k: $v\n";
        echo "----\n";
    }
} else {
    echo "NO_PM_INSTANCE_ROWS\n";
}

// Show recent completion_workflow.log tail (last 2000 bytes)
$log = __DIR__ . '/../logs/completion_workflow.log';
if (file_exists($log)) {
    $s = file_get_contents($log);
    $tail = substr($s, -2000);
    echo "---completion_workflow.log (tail)---\n" . $tail . "\n";
}

$elog = __DIR__ . '/../logs/email_send.log';
if (file_exists($elog)) {
    $s2 = file_get_contents($elog);
    $tail2 = substr($s2, -2000);
    echo "---email_send.log (tail)---\n" . $tail2 . "\n";
}

echo "COMPLETE_SCRIPT_DONE\n";

?>
