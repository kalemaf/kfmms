<?php
/**
 * Improved Email Notification Handler
 * Add this to your work_order_requests.php to handle SMTP failures gracefully
 * 
 * INSTALLATION: Replace the send_notification_email function in work_order_requests.php
 * with this improved version that includes better error handling and fallback options.
 */

if (!function_exists('send_notification_email_improved')) {
    /**
     * Enhanced notification email function with better error handling
     * Supports: PHPMailer (SMTP) → PHP mail() → Database log fallback
     */
    function send_notification_email_improved($to, $subject, $body, $from = null, $from_name = null, $attachment = null, $attachment_name = null) {
        global $SMTP_ENABLED, $SMTP_HOST, $SMTP_PORT, $SMTP_USER, $SMTP_PASS, $SMTP_SECURE, $SMTP_FROM_EMAIL, $SMTP_FROM_NAME, $connection;

        $from_email = $from ?: ($SMTP_FROM_EMAIL ?? 'no-reply@example.com');
        $from_name = $from_name ?: ($SMTP_FROM_NAME ?? 'Maintenix');
        
        // Ensure logs directory exists
        $log_dir = __DIR__ . '/logs';
        if (!is_dir($log_dir)) {
            mkdir($log_dir, 0755, true);
        }

        global $TEMP_DISABLE_SMTP_FOR_TESTS;
        $GLOBALS['EMAIL_SEND_ERROR'] = '';
        $email_sent = false;

        // ===== ATTEMPT 1: PHPMailer SMTP =====
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
                    $mail->isHTML(false);
                    
                    if (!empty($attachment) && !empty($attachment_name)) {
                        $mail->addStringAttachment($attachment, $attachment_name);
                    }
                    
                    $mail->send();
                    $email_sent = true;
                    
                    @file_put_contents($log_dir . '/email_send.log', 
                        date('Y-m-d H:i:s') . " [SMTP_SUCCESS] To: {$to} | Subject: {$subject}\n", 
                        FILE_APPEND);
                    
                    return true;
                    
                } catch (\Exception $e) {
                    $GLOBALS['EMAIL_SEND_ERROR'] = $e->getMessage();
                    @file_put_contents($log_dir . '/email_send.log', 
                        date('Y-m-d H:i:s') . " [SMTP_FAILED] To: {$to} | Error: " . $e->getMessage() . "\n", 
                        FILE_APPEND);
                    
                    // Continue to fallback methods
                }
            }
        }

        // ===== ATTEMPT 2: PHP mail() function =====
        if (!$email_sent) {
            $headers = 'From: ' . $from_email . ' <' . $from_email . '>' . "\r\n";
            $headers .= 'Reply-To: ' . $from_email . "\r\n";
            $headers .= 'X-Mailer: PHP/' . phpversion() . "\r\n";
            $headers .= 'Content-Type: text/plain; charset=UTF-8' . "\r\n";
            
            if (empty($attachment) || empty($attachment_name)) {
                if (@mail($to, $subject, $body, $headers)) {
                    $email_sent = true;
                    @file_put_contents($log_dir . '/email_send.log', 
                        date('Y-m-d H:i:s') . " [PHP_MAIL_SUCCESS] To: {$to} | Subject: {$subject}\n", 
                        FILE_APPEND);
                    return true;
                }
            } else {
                // With attachment using PHP mail()
                $separator = md5(time());
                $eol = "\r\n";
                $headers .= "MIME-Version: 1.0" . $eol;
                $headers .= "Content-Type: multipart/mixed; boundary=\"" . $separator . "\"" . $eol;

                $message = "--" . $separator . $eol;
                $message .= "Content-Type: text/plain; charset=UTF-8" . $eol;
                $message .= "Content-Transfer-Encoding: 7bit" . $eol . $eol;
                $message .= $body . $eol . $eol;
                $message .= "--" . $separator . $eol;
                $message .= "Content-Type: application/octet-stream; name=\"" . $attachment_name . "\"" . $eol;
                $message .= "Content-Transfer-Encoding: base64" . $eol;
                $message .= "Content-Disposition: attachment; filename=\"" . $attachment_name . "\"" . $eol . $eol;
                $message .= chunk_split(base64_encode($attachment)) . $eol . $eol;
                $message .= "--" . $separator . "--";

                if (@mail($to, $subject, $message, $headers)) {
                    $email_sent = true;
                    @file_put_contents($log_dir . '/email_send.log', 
                        date('Y-m-d H:i:s') . " [PHP_MAIL_ATTACHMENT_SUCCESS] To: {$to} | Subject: {$subject}\n", 
                        FILE_APPEND);
                    return true;
                }
            }
            
            if (!$email_sent) {
                @file_put_contents($log_dir . '/email_send.log', 
                    date('Y-m-d H:i:s') . " [PHP_MAIL_FAILED] To: {$to} | Subject: {$subject}\n", 
                    FILE_APPEND);
                $GLOBALS['EMAIL_SEND_ERROR'] = 'PHP mail() function failed - check server mail configuration';
            }
        }

        // ===== ATTEMPT 3: Database logging (fallback) =====
        if (!$email_sent && isset($connection)) {
            try {
                $stmt = $connection->prepare("INSERT INTO notification_queue (recipient_email, subject, body, status, created_at) VALUES (?, ?, ?, ?, NOW())");
                if ($stmt) {
                    $status = 'pending';
                    $stmt->bind_param('sss', $to, $subject, $body);
                    if ($stmt->execute()) {
                        @file_put_contents($log_dir . '/email_send.log', 
                            date('Y-m-d H:i:s') . " [DB_QUEUE] To: {$to} | Subject: {$subject} | Queued for later sending\n", 
                            FILE_APPEND);
                        
                        // Set error message indicating email was queued
                        $GLOBALS['EMAIL_SEND_ERROR'] = 'Email queued in database - will be sent when SMTP is available';
                        return true; // Technically succeeded (queued)
                    }
                }
            } catch (Exception $e) {
                // Silent failure - at least we tried
            }
        }

        return $email_sent;
    }
}

// ===== DATABASE TABLE FOR NOTIFICATION QUEUE =====
// Run this SQL if the notification_queue table doesn't exist:
/*
CREATE TABLE IF NOT EXISTS `notification_queue` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `recipient_email` VARCHAR(255) NOT NULL,
  `subject` VARCHAR(255) NOT NULL,
  `body` LONGTEXT NOT NULL,
  `status` ENUM('pending', 'sent', 'failed') DEFAULT 'pending',
  `attempts` INT DEFAULT 0,
  `last_error` TEXT,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `sent_at` TIMESTAMP NULL,
  INDEX idx_status (status),
  INDEX idx_created (created_at)
);
*/

// ===== CRON JOB HELPER: Process queued emails =====
if (!function_exists('process_email_queue')) {
    /**
     * Call this from a cron job to retry pending emails
     * Example: php process_email_queue.php
     */
    function process_email_queue($connection) {
        global $SMTP_ENABLED, $SMTP_HOST, $SMTP_PORT, $SMTP_USER, $SMTP_PASS, $SMTP_SECURE, $SMTP_FROM_EMAIL, $SMTP_FROM_NAME;
        
        if (!$connection) return;
        
        $result = $connection->query("SELECT id, recipient_email, subject, body FROM notification_queue WHERE status='pending' LIMIT 10");
        if (!$result) return;
        
        while ($row = $result->fetch_assoc()) {
            try {
                $mail = new PHPMailer\PHPMailer\PHPMailer(true);
                $mail->isSMTP();
                $mail->Host = $SMTP_HOST;
                $mail->Port = (int)$SMTP_PORT;
                $mail->SMTPAuth = !empty($SMTP_USER);
                if ($mail->SMTPAuth) {
                    $mail->Username = $SMTP_USER;
                    $mail->Password = $SMTP_PASS;
                }
                if (!empty($SMTP_SECURE)) {
                    $mail->SMTPSecure = $SMTP_SECURE;
                }
                $mail->Timeout = 10;
                $mail->setFrom($SMTP_FROM_EMAIL, $SMTP_FROM_NAME);
                $mail->addAddress($row['recipient_email']);
                $mail->Subject = $row['subject'];
                $mail->Body = $row['body'];
                $mail->send();
                
                $update = $connection->prepare("UPDATE notification_queue SET status='sent', sent_at=NOW() WHERE id=?");
                $update->bind_param('i', $row['id']);
                $update->execute();
                
            } catch (Exception $e) {
                $error = $e->getMessage();
                $update = $connection->prepare("UPDATE notification_queue SET attempts=attempts+1, last_error=? WHERE id=?");
                $update->bind_param('si', $error, $row['id']);
                $update->execute();
            }
        }
    }
}
?>
