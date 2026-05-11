<?php
/**
 * User Access Management for CMMS
 * Content only - to be included in index.php
 */

require_once 'config.inc.php';
require_once 'common.inc.php';

function ensure_user_creation_authorizations_table($connection) {
    global $db_type;
    if ($db_type === 'sqlite') {
        if (function_exists('ensure_sqlite_user_creation_authorizations_table')) {
            ensure_sqlite_user_creation_authorizations_table($connection);
        }
        return;
    }
    $connection->query(
        "CREATE TABLE IF NOT EXISTS `user_creation_authorizations` (
            `auth_id` INT(11) NOT NULL AUTO_INCREMENT,
            `pending_username` VARCHAR(50) NOT NULL,
            `pending_email` VARCHAR(255) NULL,
            `password_hash` VARCHAR(255) NOT NULL,
            `temp_password` VARCHAR(255) NULL,
            `role` ENUM('admin','maintenance manager','supervisor','technician','operator') NOT NULL DEFAULT 'operator',
            `phone` VARCHAR(20) NULL,
            `country_code` VARCHAR(5) DEFAULT '+256',
            `requestor_id` INT(11) NULL,
            `requestor_name` VARCHAR(255) NULL,
            `auth_code` CHAR(6) NOT NULL,
            `is_used` BOOLEAN NOT NULL DEFAULT FALSE,
            `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            `expires_at` DATETIME NOT NULL,
            `used_at` DATETIME NULL,
            PRIMARY KEY (`auth_id`),
            UNIQUE KEY `uk_auth_code` (`auth_code`),
            INDEX `idx_is_used` (`is_used`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );
}

function generate_user_authorization_code($connection) {
    $code = null;
    for ($attempt = 0; $attempt < 8; $attempt++) {
        $candidate = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        $stmt = $connection->prepare("SELECT 1 FROM user_creation_authorizations WHERE auth_code = ? LIMIT 1");
        if (!$stmt) {
            break;
        }
        $stmt->bind_param('s', $candidate);
        $stmt->execute();
        if (method_exists($stmt, 'store_result')) {
            $stmt->store_result();
            if (method_exists($stmt, 'num_rows')) {
                $exists = $stmt->num_rows() > 0;
            } else {
                $exists = $stmt->num_rows > 0;
            }
        } else {
            $result = method_exists($stmt, 'get_result') ? $stmt->get_result() : null;
            if ($result) {
                $exists = (isset($result->num_rows) ? $result->num_rows : $result->num_rows()) > 0;
            } else {
                $exists = false;
            }
        }
        $stmt->close();
        if (!$exists) {
            $code = $candidate;
            break;
        }
    }
    return $code ?: str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
}

function generate_temporary_password($length = 12) {
    $chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789!@#$%';
    $password = '';
    for ($i = 0; $i < $length; $i++) {
        $password .= $chars[random_int(0, strlen($chars) - 1)];
    }
    return $password;
}

function send_temporary_password_email($to_email, $username, $temp_password) {
    global $SMTP_ENABLED, $SMTP_HOST, $SMTP_PORT, $SMTP_USER, $SMTP_PASS, $SMTP_SECURE, $SMTP_FROM_EMAIL, $SMTP_FROM_NAME;
    
    $from_email = $SMTP_FROM_EMAIL ?? 'noreply@cmms.local';
    $from_name = $SMTP_FROM_NAME ?? 'CMMS System';
    $subject = 'Your CMMS Account - Temporary Password';
    $body = <<<HTML
<html>
<body style="font-family: Arial, sans-serif; line-height: 1.6;">
    <h2>Welcome to CMMS</h2>
    <p>Hello <strong>$username</strong>,</p>
    <p>Your account has been created successfully. Use the temporary password below to login for the first time:</p>
    <div style="background-color: #f0f0f0; padding: 15px; margin: 20px 0; border-left: 4px solid #007bff;">
        <strong>Username:</strong> $username<br>
        <strong>Temporary Password:</strong> <code style="font-size: 14px; background-color: #fff; padding: 5px;">$temp_password</code>
    </div>
    <p><strong style="color: red;">⚠️ IMPORTANT:</strong> You must change this password immediately after your first login. Do not share this temporary password with anyone.</p>
    <p>Login here: <a href="http://localhost/auth.php">CMMS Login Portal</a></p>
    <hr>
    <p style="color: #666; font-size: 12px;">This is an automated message. Please do not reply to this email.</p>
</body>
</html>
HTML;

    if (!empty($SMTP_ENABLED)) {
        $autoload = __DIR__ . DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR . 'autoload.php';
        if (file_exists($autoload)) {
            require_once $autoload;
            try {
                $mail = new PHPMailer\PHPMailer\PHPMailer(true);
                $mail->isSMTP();
                $mail->Host = $SMTP_HOST;
                $mail->Port = $SMTP_PORT;
                $mail->SMTPSecure = $SMTP_SECURE;
                if (!empty($SMTP_USER)) {
                    $mail->SMTPAuth = true;
                    $mail->Username = $SMTP_USER;
                    $mail->Password = $SMTP_PASS;
                }
                $mail->setFrom($from_email, $from_name);
                $mail->addAddress($to_email, $username);
                $mail->isHTML(true);
                $mail->Subject = $subject;
                $mail->Body = $body;
                $mail->AltBody = strip_tags($body);
                return $mail->send();
            } catch (Exception $e) {
                error_log("Email error: " . $e->getMessage());
                return false;
            }
        }
    }
    return true; // Assume success if SMTP not enabled
}

function ensure_role_enum_values($connection) {
    global $db_type;
    if (!is_object($connection) || ($db_type ?? '') === 'sqlite') {
        return;
    }

    $allowedRoles = [
        'admin',
        'maintenance manager',
        'supervisor',
        'technician',
        'operator'
    ];
    $enumList = "'" . implode("','", $allowedRoles) . "'";

    $tables = [
        'users',
        'user_creation_authorizations'
    ];

    foreach ($tables as $table) {
        $checkStmt = $connection->prepare("SHOW TABLES LIKE ?");
        if ($checkStmt) {
            $checkStmt->bind_param('s', $table);
            $checkStmt->execute();
            $checkStmt->store_result();
            $exists = method_exists($checkStmt, 'num_rows') ? $checkStmt->num_rows() > 0 : $checkStmt->num_rows > 0;
            $checkStmt->close();
            if ($exists) {
                $connection->query("ALTER TABLE `{$table}` MODIFY `role` ENUM({$enumList}) NOT NULL DEFAULT 'operator'");
            }
        }
    }
}

function send_authorization_email($to, $subject, $body) {
    global $SMTP_ENABLED, $SMTP_HOST, $SMTP_PORT, $SMTP_USER, $SMTP_PASS, $SMTP_SECURE, $SMTP_FROM_EMAIL, $SMTP_FROM_NAME;
    $from_email = $SMTP_FROM_EMAIL ?? 'no-reply@example.com';
    $from_name = $SMTP_FROM_NAME ?? 'Free CMMS';

    if (!empty($SMTP_ENABLED)) {
        $autoload = __DIR__ . DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR . 'autoload.php';
        if (file_exists($autoload)) {
            require_once $autoload;
            try {
                $mail = new PHPMailer\PHPMailer\PHPMailer(true);
                $mail->isSMTP();
                $mail->Host = $SMTP_HOST;
                $mail->Port = !empty($SMTP_PORT) ? (int) $SMTP_PORT : 25;
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
                $mail->Timeout = 10;
                $mail->SMTPDebug = 0;
                $mail->setFrom($from_email, $from_name);
                $mail->addAddress($to);
                $mail->Subject = $subject;
                $mail->Body = $body;
                $mail->AltBody = $body;
                $mail->send();
                return true;
            } catch (Exception $e) {
                error_log('[Authorization Email] PHPMailer failed: ' . $e->getMessage());
                // Fallback to PHP mail() below
            }
        }
    }

    $headers = 'From: ' . $from_email . "\r\n" . 'X-Mailer: PHP/' . phpversion();
    $result = @mail($to, $subject, $body, $headers);
    if (!$result) {
        error_log('[Authorization Email] PHP mail() failed for recipient: ' . $to);
    }
    return $result;
}

// Check if user has admin/manager/developer access
$user_role = $_SESSION['role'] ?? '';
if (!in_array($user_role, ['admin', 'manager', 'maintenance manager', 'supervisor', 'developer'], true)) {
    echo '<h2>Access Denied</h2><p>You do not have permission to manage users.</p><p><a href="index.php">Return to Main Application</a></p>';
    return;
}

// Get users data
$users = [];
if ($connection) {
    $user_role = $_SESSION['role'] ?? '';
    $is_admin_or_dev = in_array($user_role, ['admin', 'developer'], true);
    
    if ($is_admin_or_dev) {
        // Admins and developers can see all users across tenants
        $result = $connection->query("SELECT user_id, username, email, role, is_active, last_login_at, created_at, company_id FROM users ORDER BY username");
    } else {
        // Regular users only see users from their tenant
        $tenant_id = intval($_SESSION['tenant_id'] ?? 0);
        $result = $connection->query("SELECT user_id, username, email, role, is_active, last_login_at, created_at, company_id FROM users WHERE company_id = $tenant_id OR company_id = 0 ORDER BY username");
    }
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $users[] = $row;
        }
    }
}

$message = '';
$message_style = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_user'])) {
    ensure_user_creation_authorizations_table($connection);
    ensure_role_enum_values($connection);

    $new_username = trim($_POST['new_username'] ?? '');
    $new_email = trim($_POST['new_email'] ?? '');
    $new_password = trim($_POST['new_password'] ?? '');
    $new_role = trim($_POST['new_role'] ?? '');
    $country_code = trim($_POST['country_code'] ?? '+256');
    $new_phone = trim($_POST['new_phone'] ?? '');
    $company_id = intval($_POST['company_id'] ?? 0);
    $authorization_code = trim($_POST['authorization_code'] ?? '');
    $requestor_name = $_SESSION['user'] ?? $_SESSION['username'] ?? 'Unknown';
    $requestor_id = intval($_SESSION['user_id'] ?? 0);

    if ($authorization_code !== '') {
        $stmt = $connection->prepare("SELECT auth_id, pending_username, pending_email, password_hash, temp_password, role, phone, country_code, company_id FROM user_creation_authorizations WHERE auth_code = ? AND is_used = 0 AND expires_at >= NOW() LIMIT 1");
        if ($stmt) {
            $stmt->bindParam(1, $authorization_code, PDO::PARAM_STR);
            $select_success = false;
            $select_error = '';
            try {
                $stmt->execute();
                $select_success = true;
            } catch (Exception $e) {
                $select_error = $e->getMessage();
            }
            if ($select_success) {
                $row = $stmt->fetch(PDO::FETCH_ASSOC);
                if ($row) {
                    $auth_id = $row['auth_id'];
                    $pending_username = $row['pending_username'];
                    $pending_email = $row['pending_email'];
                    $pending_password_hash = $row['password_hash'];
                    $pending_temp_password = $row['temp_password'];
                    $pending_role = $row['role'];
                    $auth_phone = $row['phone'];
                    $auth_country_code = $row['country_code'];
                    $auth_company_id = $row['company_id'];
                    if (empty($new_phone)) {
                        $new_phone = $auth_phone;
                    }
                    if (empty($country_code) || $country_code === '+256') {
                        $country_code = $auth_country_code;
                    }
                    $insertStmt = $connection->prepare("INSERT INTO users (username, email, password_hash, role, phone, country_code, company_id, whatsapp_enabled, password_change_required) VALUES (?, ?, ?, ?, ?, ?, ?, 1, 1)");
                    if ($insertStmt) {
                        $insertStmt->bindParam(1, $pending_username, PDO::PARAM_STR);
                        $insertStmt->bindParam(2, $pending_email, PDO::PARAM_STR);
                        $insertStmt->bindParam(3, $pending_password_hash, PDO::PARAM_STR);
                        $insertStmt->bindParam(4, $pending_role, PDO::PARAM_STR);
                        $insertStmt->bindParam(5, $new_phone, PDO::PARAM_STR);
                        $insertStmt->bindParam(6, $country_code, PDO::PARAM_STR);
                        $insertStmt->bindParam(7, $auth_company_id, PDO::PARAM_INT);
                        $insert_success = false;
                        $insert_error = '';
                        try {
                            $insertStmt->execute();
                            $insert_success = true;
                        } catch (Exception $e) {
                            $insert_error = $e->getMessage();
                        }
                        if ($insert_success) {
                            $insertStmt->closeCursor();
                            send_temporary_password_email($pending_email, $pending_username, $pending_temp_password);
                            $updateStmt = $connection->prepare("UPDATE user_creation_authorizations SET is_used = 1, used_at = NOW() WHERE auth_id = ?");
                            if ($updateStmt) {
                                $updateStmt->bindParam(1, $auth_id, PDO::PARAM_INT);
                                $updateStmt->execute();
                                $updateStmt->closeCursor();
                            }
                            $message_style = 'background:#d4edda;color:#155724;border:1px solid #c3e6cb;';
                            $message = '<strong>✓ User created successfully!</strong> Temporary password sent to ' . htmlspecialchars($pending_email) . '. They must change it on first login.';
                        } else {
                            $insertStmt->close();
                            $message_style = 'background:#f8d7da;color:#721c24;border:1px solid #f5c6cb;';
                            $message = 'Error adding authorized user: ' . htmlspecialchars($insert_error);
                        }
                    } else {
                        $message_style = 'background:#f8d7da;color:#721c24;border:1px solid #f5c6cb;';
                        $error_info = $connection->errorInfo();
                        $message = 'Database error creating user: ' . htmlspecialchars($error_info[2] ?? 'Unknown error');
                    }
                } else {
                    $statusStmt = $connection->prepare("SELECT is_used, expires_at FROM user_creation_authorizations WHERE auth_code = ? LIMIT 1");
                    if ($statusStmt) {
                        $statusStmt->bindParam(1, $authorization_code, PDO::PARAM_STR);
                        try {
                            $statusStmt->execute();
                            $statusRow = $statusStmt->fetch(PDO::FETCH_ASSOC);
                            if ($statusRow) {
                                $status_used = $statusRow['is_used'];
                                $status_expires_at = $statusRow['expires_at'];
                                if ($status_used) {
                                    $message = 'That authorization code has already been used. Request a new code from the developer.';
                                } elseif ($status_expires_at < date('Y-m-d H:i:s')) {
                                    $message = 'That authorization code has expired. Request a new code from the developer.';
                                } else {
                                    $message = 'Authorization code is invalid. Ask the developer for a valid 6-digit code.';
                                }
                            } else {
                                $message = 'Authorization code is invalid. Ask the developer for a valid 6-digit code.';
                            }
                        } catch (Exception $e) {
                            $message = 'Authorization lookup failed: ' . htmlspecialchars($e->getMessage());
                        }
                        $statusStmt->closeCursor();
                    } else {
                        $message = 'Authorization code is invalid or expired. Ask the developer for a valid 6-digit code.';
                    }
                    $message_style = 'background:#f8d7da;color:#721c24;border:1px solid #f5c6cb;';
                }
            } else {
                $message_style = 'background:#f8d7da;color:#721c24;border:1px solid #f5c6cb;';
                $message = 'Authorization lookup failed: ' . htmlspecialchars($select_error);
            }
            $stmt->close();
        } else {
            $message_style = 'background:#f8d7da;color:#721c24;border:1px solid #f5c6cb;';
            $message = 'Database error validating authorization code: ' . htmlspecialchars($connection->error);
        }
    } else {
        if ($new_username && $new_password && $new_role && $company_id > 0) {
            $password_hash = password_hash($new_password, PASSWORD_DEFAULT);
            $auth_code = generate_user_authorization_code($connection);
            $expires_at = date('Y-m-d H:i:s', strtotime('+24 hours'));

            $stmt = $connection->prepare("INSERT INTO user_creation_authorizations (pending_username, pending_email, password_hash, temp_password, role, phone, country_code, company_id, requestor_id, requestor_name, auth_code, expires_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            if ($stmt) {
                $stmt->bind_param('sssssssissss', $new_username, $new_email, $password_hash, $new_password, $new_role, $new_phone, $country_code, $company_id, $requestor_id, $requestor_name, $auth_code, $expires_at);
                $insert_success = false;
                $insert_error = '';
                if ($db_type === 'sqlite') {
                    try {
                        $stmt->execute();
                        $insert_success = true;
                    } catch (Exception $e) {
                        $insert_error = $e->getMessage();
                    }
                } else {
                    $insert_success = $stmt->execute();
                    if (!$insert_success) {
                        $insert_error = $stmt->error;
                    }
                }
                if ($insert_success) {
                    $stmt->close();
                    
                    // Get company name for email
                    $company_name = 'Unknown Company';
                    $company_stmt = $connection->prepare("SELECT company_name FROM companies WHERE company_id = ?");
                    if ($company_stmt) {
                        $company_stmt->bind_param('i', $company_id);
                        if ($company_stmt->execute()) {
                            $company_stmt->bind_result($company_name_result);
                            if ($company_stmt->fetch()) {
                                $company_name = $company_name_result;
                            }
                        }
                        $company_stmt->close();
                    }
                    
                    $developerEmail = 'kalemaf876@gmail.com';
                    $subject = "Developer authorization required for new user '{$new_username}'";
                    $body = "A new user creation request has been submitted by {$requestor_name}.\n\n" .
                            "Username: {$new_username}\n" .
                            "Email: " . ($new_email !== '' ? $new_email : 'Not provided') . "\n" .
                            "Role: {$new_role}\n" .
                            "Company: {$company_name}\n\n" .
                            "Authorize this request by giving the requester the following 6-digit code:\n\n" .
                            "{$auth_code}\n\n" .
                            "This code expires in 24 hours. Once the requester has the code, they must enter it in the Add User form and submit again to complete the user creation.";
                    send_authorization_email($developerEmail, $subject, $body);
                    $message_style = 'background:#d4edda;color:#155724;border:1px solid #c3e6cb;';
                    $message = '<strong>✓ Request Submitted</strong><br>An authorization code was sent to the developer. Ask them for the 6-digit code and enter it here to complete the new user creation.';
                } else {
                    $stmt->close();
                    $message_style = 'background:#f8d7da;color:#721c24;border:1px solid #f5c6cb;';
                    $message = 'Error saving authorization request: ' . htmlspecialchars($stmt->error);
                }
            } else {
                $message_style = 'background:#f8d7da;color:#721c24;border:1px solid #f5c6cb;';
                $message = 'Database error storing authorization request: ' . htmlspecialchars($connection->error);
            }
        } else {
            $message_style = 'background:#f8d7da;color:#721c24;border:1px solid #f5c6cb;';
            $message = 'Please fill in all required fields.';
        }
    }
}
?>

<h2>User Management</h2>

<?php if (!empty($message)): ?>
    <div style="<?php echo $message_style; ?> padding: 15px; border-radius: 5px; margin-bottom: 20px;">
        <?php echo $message; ?>
    </div>
<?php endif; ?>

<?php if (empty($users)): ?>
    <p>No users found in the database.</p>
    <p>You may need to add users manually or check the user creation process.</p>
<?php else: ?>
    <p>Total Users: <?php echo count($users); ?></p>

    <table border="1" cellpadding="5" cellspacing="0" style="border-collapse: collapse; width: 100%; margin-top: 20px;">
        <tr style="background-color: #f0f0f0;">
            <th>User ID</th>
            <th>Username</th>
            <th>Email</th>
            <th>Role</th>
            <th>Status</th>
            <th>Last Login</th>
            <th>Created</th>
        </tr>
        <?php foreach ($users as $user): ?>
        <tr>
            <td><?php echo htmlspecialchars($user['user_id']); ?></td>
            <td><?php echo htmlspecialchars($user['username']); ?></td>
            <td><?php echo htmlspecialchars($user['email'] ?? ''); ?></td>
            <td><?php echo htmlspecialchars($user['role']); ?></td>
            <td><?php echo $user['is_active'] ? 'Active' : 'Inactive'; ?></td>
            <td><?php echo htmlspecialchars($user['last_login_at'] ?? 'Never'); ?></td>
            <td><?php echo htmlspecialchars($user['created_at'] ?? ''); ?></td>
        </tr>
        <?php endforeach; ?>
    </table>
<?php endif; ?>

<h3>Add New User</h3>
<form method="post" action="" style="margin-top: 20px;">
    <table border="1" cellpadding="5" cellspacing="0" style="border-collapse: collapse;">
        <tr>
            <td><label for="new_username">Username:</label></td>
            <td><input type="text" id="new_username" name="new_username" required></td>
        </tr>
        <tr>
            <td><label for="new_email">Email:</label></td>
            <td><input type="email" id="new_email" name="new_email"></td>
        </tr>
        <tr>
            <td><label for="country_code">Country Code:</label></td>
            <td>
                <select id="country_code" name="country_code" required style="width: 100%;">
                    <option value="">-- Select Country --</option>
                    <option value="+1">🇺🇸 +1 (USA/Canada)</option>
                    <option value="+256">🇺🇬 +256 (Uganda)</option>
                    <option value="+254">🇰🇪 +254 (Kenya)</option>
                    <option value="+255">🇹🇿 +255 (Tanzania)</option>
                    <option value="+234">🇳🇬 +234 (Nigeria)</option>
                    <option value="+27">🇿🇦 +27 (South Africa)</option>
                    <option value="+233">🇬🇭 +233 (Ghana)</option>
                    <option value="+44">🇬🇧 +44 (UK)</option>
                    <option value="+33">🇫🇷 +33 (France)</option>
                    <option value="+49">🇩🇪 +49 (Germany)</option>
                    <option value="+39">🇮🇹 +39 (Italy)</option>
                    <option value="+34">🇪🇸 +34 (Spain)</option>
                    <option value="+31">🇳🇱 +31 (Netherlands)</option>
                    <option value="+41">🇨🇭 +41 (Switzerland)</option>
                    <option value="+46">🇸🇪 +46 (Sweden)</option>
                    <option value="+47">🇳🇴 +47 (Norway)</option>
                    <option value="+45">🇩🇰 +45 (Denmark)</option>
                    <option value="+358">🇫🇮 +358 (Finland)</option>
                    <option value="+86">🇨🇳 +86 (China)</option>
                    <option value="+81">🇯🇵 +81 (Japan)</option>
                    <option value="+91">🇮🇳 +91 (India)</option>
                    <option value="+92">🇵🇰 +92 (Pakistan)</option>
                    <option value="+60">🇲🇾 +60 (Malaysia)</option>
                    <option value="+66">🇹🇭 +66 (Thailand)</option>
                    <option value="+65">🇸🇬 +65 (Singapore)</option>
                    <option value="+62">🇮🇩 +62 (Indonesia)</option>
                    <option value="+61">🇦🇺 +61 (Australia)</option>
                    <option value="+64">🇳🇿 +64 (New Zealand)</option>
                    <option value="+55">🇧🇷 +55 (Brazil)</option>
                    <option value="+52">🇲🇽 +52 (Mexico)</option>
                    <option value="+1">🇨🇦 +1 (Canada)</option>
                </select>
            </td>
        </tr>
        <tr>
            <td><label for="new_phone">Phone Number:</label></td>
            <td><input type="tel" id="new_phone" name="new_phone" placeholder="Enter your phone number (without country code)" pattern="[0-9\s\-\(\)]*"></td>
        </tr>
        <tr>
            <td><label for="new_password">Password:</label></td>
            <td><input type="password" id="new_password" name="new_password" required></td>
        </tr>
        <tr>
            <td><label for="new_role">Role:</label></td>
            <td>
                <select id="new_role" name="new_role" required>
                    <option value="operator">Operator</option>
                    <option value="technician">Technician</option>
                    <option value="supervisor">Supervisor</option>
                    <option value="maintenance manager">Maintenance Manager</option>
                    <option value="admin">Admin</option>
                </select>
            </td>
        </tr>
        <tr>
            <td><label for="company_id">Company:</label></td>
            <td>
                <select id="company_id" name="company_id" required>
                    <option value="">-- Select Company --</option>
                    <?php
                    // Get companies for dropdown
                    $companies = [];
                    if ($connection) {
                        $user_role = $_SESSION['role'] ?? '';
                        $is_admin_or_dev = in_array($user_role, ['admin', 'developer'], true);
                        
                        if ($is_admin_or_dev) {
                            // Admins and developers can see all companies
                            $company_result = $connection->query("SELECT company_id, company_name FROM companies ORDER BY company_name");
                        } else {
                            // Regular users only see their own company
                            $tenant_id = intval($_SESSION['tenant_id'] ?? 0);
                            $company_result = $connection->query("SELECT company_id, company_name FROM companies WHERE company_id = $tenant_id ORDER BY company_name");
                        }
                        if ($company_result) {
                            while ($company_row = $company_result->fetch_assoc()) {
                                $companies[] = $company_row;
                            }
                        }
                    }
                    foreach ($companies as $company): ?>
                    <option value="<?php echo $company['company_id']; ?>"><?php echo htmlspecialchars($company['company_name']); ?></option>
                    <?php endforeach; ?>
                </select>
            </td>
        </tr>
        <tr>
            <td><label for="authorization_code">Developer Authorization Code:</label></td>
            <td><input type="text" id="authorization_code" name="authorization_code" pattern="\d{6}" maxlength="6" placeholder="6-digit code from developer"></td>
        </tr>
        <tr>
            <td colspan="2" style="text-align: center;">
                <input type="submit" name="add_user" value="Add User" style="padding: 5px 15px;">
            </td>
        </tr>
    </table>
</form>

<p style="margin-top: 20px;">
    <a href="index.php?nav=admin">Back to Administration</a> |
    <a href="index.php">Back to Dashboard</a>
</p>