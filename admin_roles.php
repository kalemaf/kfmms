<?php
/**
 * 🔐 SaaS Control Center - KFMMS
 * Professional SaaS management interface for Efficraft Technologies
 * Developer Super Admin controls with comprehensive system management
 */

// Enable error reporting but don't display errors to users
error_reporting(E_ALL);
ini_set('display_errors', '0');  // Don't display errors on page
ini_set('log_errors', '1');      // Log errors to error_log

require_once 'config.inc.php';
require_once 'common.inc.php';
require_once 'app/PasswordManager.php';
require_once 'app/AuditLogger.php';

global $db_type;

// Check if user is developer or admin (Super Admin)
$user_role = strtolower(trim($_SESSION['role'] ?? ''));
$user_group = strtolower(trim($_SESSION['group'] ?? ''));
$user_name = strtolower(trim($_SESSION['user'] ?? ''));
$user_email = strtolower(trim($_SESSION['email'] ?? ''));

function hasSuperAdminAccess($connection, $user_role, $user_group, $user_name, $user_email) {
    $allowed_emails = ['kalemaf876@gmail.com'];
    global $db_type;
    if (in_array($user_role, ['developer', 'admin'], true) || in_array($user_group, ['developer', 'admin'], true)) {
        return true;
    }
    if ($user_name === 'developer' || in_array($user_email, $allowed_emails, true)) {
        return true;
    }
    if ($connection && !empty($user_name)) {
        if ($db_type === 'sqlite') {
            $stmt = $connection->prepare('SELECT role FROM users WHERE LOWER(username) = ? LIMIT 1');
            if ($stmt) {
                $stmt->bindParam(1, $user_name, PDO::PARAM_STR);
                $stmt->execute();
                $row = $stmt->fetch(PDO::FETCH_ASSOC);
                if ($row) {
                    $db_role = strtolower(trim($row['role'] ?? ''));
                    $stmt->closeCursor();
                    return in_array($db_role, ['developer', 'admin'], true);
                }
                $stmt->closeCursor();
            }
        } else {
            $stmt = $connection->prepare('SELECT role FROM users WHERE LOWER(username) = ? LIMIT 1');
            if ($stmt) {
                $stmt->bindParam(1, $user_name, PDO::PARAM_STR);
                $stmt->execute();
                $row = $stmt->fetch(PDO::FETCH_ASSOC);
                if ($row) {
                    $db_role = strtolower(trim($row['role'] ?? ''));
                    $stmt->closeCursor();
                    return in_array($db_role, ['developer', 'admin'], true);
                }
                $stmt->closeCursor();
            }
        }
    }
    return false;
}

if (!hasSuperAdminAccess($connection, $user_role, $user_group, $user_name, $user_email)) {
    echo '<div class="alert alert-danger"><h4>🚫 Access Denied</h4><p>Super Admin access required. Only Efficraft Technologies developers and administrators can access the SaaS Control Center.</p><p><a href="index.php" class="btn btn-primary">← Return to Application</a></p></div>';
    exit;
}

// Handle form submissions
$message = '';
$message_type = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    switch ($action) {
        case 'add_company':
            $company_name = trim($_POST['company_name'] ?? '');
            $contact_name = trim($_POST['contact_name'] ?? '');
            $contact_email = trim($_POST['company_email'] ?? trim($_POST['contact_email'] ?? ''));
            $contact_phone = trim($_POST['phone'] ?? '');
            $industry = trim($_POST['industry'] ?? '');
            $company_size = trim($_POST['company_size'] ?? '');
            $initial_tier = $_POST['initial_tier'] ?? 'trial';
            $auto_activate = isset($_POST['auto_activate']) ? 1 : 0;

            if (empty($company_name) || empty($contact_email)) {
                $message = '❌ Company name and email are required.';
                $message_type = 'danger';
            } else {
                try {
                    // Check if company already exists
                    if ($db_type === 'sqlite') {
                        $check = $connection->prepare("SELECT company_id FROM companies WHERE company_email = ? OR company_name = ?");
                        $check->bindParam(1, $contact_email, PDO::PARAM_STR);
                        $check->bindParam(2, $company_name, PDO::PARAM_STR);
                        $check->execute();
                        $already_exists = $check->fetch(PDO::FETCH_ASSOC) !== false;
                        $check->closeCursor();
                    } else {
                        $check = $connection->prepare("SELECT company_id FROM companies WHERE company_email = ? OR company_name = ?");
                        $check->bindParam(1, $contact_email, PDO::PARAM_STR);
                        $check->bindParam(2, $company_name, PDO::PARAM_STR);
                        $check->execute();
                        $already_exists = $check->fetch(PDO::FETCH_ASSOC) !== false;
                        $check->closeCursor();
                    }

                    if ($already_exists) {
                        $message = '❌ A company with this email or name already exists.';
                        $message_type = 'danger';
                    } else {
                        // Insert company
                        if ($db_type === 'sqlite') {
                            $stmt = $connection->prepare("INSERT INTO companies (company_name, company_email, contact_name, contact_phone) VALUES (?, ?, ?, ?)");
                            $stmt->bindParam(1, $company_name, PDO::PARAM_STR);
                            $stmt->bindParam(2, $contact_email, PDO::PARAM_STR);
                            $stmt->bindParam(3, $contact_name, PDO::PARAM_STR);
                            $stmt->bindParam(4, $contact_phone, PDO::PARAM_STR);
                            $stmt->execute();
                            $new_company_id = $connection->lastInsertId();
                            $stmt->closeCursor();
                        } else {
                            $stmt = $connection->prepare("INSERT INTO companies (company_name, company_email, contact_name, contact_phone) VALUES (?, ?, ?, ?)");
                            $stmt->bind_param('ssss', $company_name, $contact_email, $contact_name, $contact_phone);
                            $stmt->execute();
                            $new_company_id = $stmt->insert_id;
                            $stmt->close();
                        }

                        // Create system control record
                        $max_users_value = ($initial_tier === 'enterprise') ? 999 : (($initial_tier === 'professional') ? 100 : (($initial_tier === 'basic') ? 25 : 5));
                        $activated = $auto_activate ? 1 : 0;
                        
                        if ($db_type === 'sqlite') {
                            $cc_stmt = $connection->prepare("INSERT INTO system_control (company_id, system_activated, feature_tier, max_users) VALUES (?, ?, ?, ?)");
                            $cc_stmt->bindParam(1, $new_company_id, PDO::PARAM_INT);
                            $cc_stmt->bindParam(2, $activated, PDO::PARAM_INT);
                            $cc_stmt->bindParam(3, $initial_tier, PDO::PARAM_STR);
                            $cc_stmt->bindParam(4, $max_users_value, PDO::PARAM_INT);
                            $cc_stmt->execute();
                            $cc_stmt->closeCursor();
                        } else {
                            $cc_stmt = $connection->prepare("INSERT INTO system_control (company_id, system_activated, feature_tier, max_users) VALUES (?, ?, ?, ?)");
                            $cc_stmt->bind_param('iisi', $new_company_id, $activated, $initial_tier, $max_users_value);
                            $cc_stmt->execute();
                            $cc_stmt->close();
                        }

                        // Generate license key
                        $license_key = generate_license_key();
                        $purchased_seats = $max_users_value;
                        $used_seats = 0;
                        $is_active = 1;

                        // Create company license
                        if ($db_type === 'sqlite') {
                            $lc_stmt = $connection->prepare("INSERT INTO company_licenses (company_id, license_key, license_type, purchased_seats, used_seats, is_active) VALUES (?, ?, ?, ?, ?, ?)");
                            if (!$lc_stmt) {
                                throw new Exception("Failed to prepare license insert statement");
                            }
                            $lc_stmt->bindParam(1, $new_company_id, PDO::PARAM_INT);
                            $lc_stmt->bindParam(2, $license_key, PDO::PARAM_STR);
                            $lc_stmt->bindParam(3, $initial_tier, PDO::PARAM_STR);
                            $lc_stmt->bindParam(4, $purchased_seats, PDO::PARAM_INT);
                            $lc_stmt->bindParam(5, $used_seats, PDO::PARAM_INT);
                            $lc_stmt->bindParam(6, $is_active, PDO::PARAM_INT);
                            if (!$lc_stmt->execute()) {
                                throw new Exception("Failed to insert license: " . json_encode($lc_stmt->errorInfo()));
                            }
                            $lc_stmt->closeCursor();
                            error_log("License created for company $new_company_id with key $license_key");
                        } else {
                            $lc_stmt = $connection->prepare("INSERT INTO company_licenses (company_id, license_key, license_type, purchased_seats, used_seats, is_active) VALUES (?, ?, ?, ?, ?, ?)");
                            if (!$lc_stmt) {
                                throw new Exception("Failed to prepare license insert statement");
                            }
                            $lc_stmt->bind_param('issiii', $new_company_id, $license_key, $initial_tier, $purchased_seats, $used_seats, $is_active);
                            if (!$lc_stmt->execute()) {
                                throw new Exception("Failed to insert license: " . $lc_stmt->error);
                            }
                            $lc_stmt->close();
                            error_log("License created for company $new_company_id with key $license_key");
                        }

                        $message = "✅ Company <strong>" . htmlspecialchars($company_name) . "</strong> registered successfully! License Key: <span class=\"license-key\">" . htmlspecialchars($license_key) . "</span>";
                        $message_type = 'success';
                    }
                } catch (Exception $e) {
                    $message = '❌ Error: ' . htmlspecialchars($e->getMessage());
                    $message_type = 'danger';
                    error_log("Add company error: " . $e->getMessage());
                }
            }
            break;

        case 'activate_system':
            $company_id = (int)($_POST['company_id'] ?? 0);
            if ($company_id > 0) {
                try {
                    // Check if system_control record exists
                    $check_stmt = $connection->prepare("SELECT control_id FROM system_control WHERE company_id = ?");
                    if ($check_stmt) {
                        $check_stmt->bindParam(1, $company_id, PDO::PARAM_INT);
                        $check_stmt->execute();
                        $result = $check_stmt->fetch(PDO::FETCH_ASSOC);

                        if ($result !== false) {
                            // Update existing
                            $stmt = $connection->prepare("UPDATE system_control SET system_activated = TRUE, activation_date = datetime('now'), system_locked = FALSE, lock_reason = NULL WHERE company_id = ?");
                        } else {
                            // Create new
                            $stmt = $connection->prepare("INSERT INTO system_control (company_id, system_activated, activation_date) VALUES (?, TRUE, datetime('now'))");
                        }

                        if ($stmt) {
                            $stmt->bindParam(1, $company_id, PDO::PARAM_INT);
                            if ($stmt->execute()) {
                                // Also update company_licenses to active if exists
                                $lic_stmt = $connection->prepare("UPDATE company_licenses SET is_active = 1 WHERE company_id = ? LIMIT 1");
                                if ($lic_stmt) {
                                    $lic_stmt->bind_param('i', $company_id);
                                    $lic_stmt->execute();
                                    $lic_stmt->close();
                                }
                                
                                $message = '✅ System activated successfully for company.';
                                $message_type = 'success';
                            } else {
                                $message = '❌ Failed to activate system: ' . $stmt->error;
                                $message_type = 'danger';
                            }
                            $stmt->close();
                        }
                        $check_stmt->close();
                    } else {
                        $message = '❌ Database error preparing statement.';
                        $message_type = 'danger';
                    }
                } catch (Exception $e) {
                    $message = '❌ Error activating system: ' . $e->getMessage();
                    $message_type = 'danger';
                }
            } else {
                $message = '❌ Invalid company ID.';
                $message_type = 'danger';
            }
            break;

        case 'deactivate_system':
            $company_id = (int)($_POST['company_id'] ?? 0);
            $lock_reason = trim($_POST['lock_reason'] ?? 'Administrative deactivation');
            if ($company_id > 0) {
                $stmt = $connection->prepare("UPDATE system_control SET system_activated = FALSE, system_locked = TRUE, lock_reason = ? WHERE company_id = ?");
                if ($stmt) {
                    $stmt->bind_param('si', $lock_reason, $company_id);
                    if ($stmt->execute()) {
                        $message = '🔒 System deactivated successfully.';
                        $message_type = 'warning';
                    } else {
                        $message = '❌ Failed to deactivate system.';
                        $message_type = 'danger';
                    }
                    $stmt->close();
                }
            }
            break;

        case 'update_subscription':
            $company_id = (int)($_POST['company_id'] ?? 0);
            $subscription_status = $_POST['subscription_status'] ?? 'active';
            $max_users = (int)($_POST['max_users'] ?? 5);
            $feature_tier = $_POST['feature_tier'] ?? 'basic';
            $expires_days = (int)($_POST['expires_days'] ?? 30);

            if ($company_id > 0) {
                $expires_at = date('Y-m-d H:i:s', strtotime("+$expires_days days"));
                $stmt = $connection->prepare("UPDATE system_control SET subscription_status = ?, max_users = ?, feature_tier = ?, subscription_expires_at = ? WHERE company_id = ?");
                if ($stmt) {
                    $stmt->bind_param('sisss', $subscription_status, $max_users, $feature_tier, $expires_at, $company_id);
                    if ($stmt->execute()) {
                        $message = '📅 Subscription updated successfully.';
                        $message_type = 'success';
                    } else {
                        $message = '❌ Failed to update subscription.';
                        $message_type = 'danger';
                    }
                    $stmt->close();
                }
            }
            break;

        case 'record_payment':
            $company_id = (int)($_POST['company_id'] ?? 0);
            $amount = (float)($_POST['amount'] ?? 0);
            $period_months = (int)($_POST['period_months'] ?? 1);
            $payment_method = $_POST['payment_method'] ?? 'manual';

            if ($company_id > 0 && $amount > 0) {
                $period_start = date('Y-m-d H:i:s');
                $period_end = date('Y-m-d H:i:s', strtotime("+$period_months months"));

                $stmt = $connection->prepare("INSERT INTO subscription_payments (company_id, amount, payment_date, payment_method, subscription_period_start, subscription_period_end, payment_status, processed_by) VALUES (?, ?, datetime('now'), ?, ?, ?, 'completed', ?)");
                if ($stmt) {
                    $stmt->bind_param('idsssi', $company_id, $amount, $payment_method, $period_start, $period_end, $_SESSION['user_id']);
                    if ($stmt->execute()) {
                        // Update subscription status
                        $update_stmt = $connection->prepare("UPDATE system_control SET subscription_status = 'active', subscription_expires_at = ? WHERE company_id = ?");
                        if ($update_stmt) {
                            $update_stmt->bind_param('si', $period_end, $company_id);
                            $update_stmt->execute();
                            $update_stmt->close();
                        }
                        $message = '💰 Payment recorded and subscription activated.';
                        $message_type = 'success';
                    } else {
                        $message = '❌ Failed to record payment.';
                        $message_type = 'danger';
                    }
                    $stmt->close();
                }
            }
            break;

        case 'create_system_update':
            $version = trim($_POST['version'] ?? '');
            $update_type = $_POST['update_type'] ?? 'feature';
            $description = trim($_POST['description'] ?? '');
            $changelog = trim($_POST['changelog'] ?? '');
            $is_mandatory = isset($_POST['is_mandatory']) ? 1 : 0;

            if (!empty($version) && !empty($description)) {
                $stmt = $connection->prepare("INSERT INTO system_updates (version, update_type, description, changelog, is_mandatory, release_date, created_by) VALUES (?, ?, ?, ?, ?, datetime('now'), ?)");
                if ($stmt) {
                    $stmt->bind_param('ssssii', $version, $update_type, $description, $changelog, $is_mandatory, $_SESSION['user_id']);
                    if ($stmt->execute()) {
                        $message = '🚀 System update created successfully.';
                        $message_type = 'success';
                    } else {
                        $message = '❌ Failed to create system update.';
                        $message_type = 'danger';
                    }
                    $stmt->close();
                }
            }
            break;

        case 'create_user':
            $company_id = (int)($_POST['company_id'] ?? 0);
            $username = trim($_POST['username'] ?? '');
            $email = trim($_POST['email'] ?? '');
            $phone = trim($_POST['phone'] ?? '');
            $role = $_POST['role'] ?? 'technician';

            // Validate role
            $valid_roles = ['admin', 'maintenance manager', 'supervisor', 'technician', 'operator', 'developer'];
            $role_display = $role;
            if ($role === 'user') {
                $role = 'technician'; // Map 'user' to 'technician'
            } elseif ($role === 'manager') {
                $role = 'maintenance manager'; // Map 'manager' to 'maintenance manager'
            }

            if (empty($username) || empty($email)) {
                $message = '❌ Username and email are required.';
                $message_type = 'danger';
            } elseif (!in_array($role, $valid_roles)) {
                $message = '❌ Invalid role selected.';
                $message_type = 'danger';
            } else {
                // Check if user exists
                $check_query = "SELECT user_id FROM users WHERE username = ? OR email = ? LIMIT 1";
                if ($db_type === 'sqlite') {
                    try {
                        $check = $connection->prepare($check_query);
                        if (!$check) {
                            $message = '❌ Database error: Unable to prepare statement';
                            $message_type = 'danger';
                        } else {
                            $check->bindParam(1, $username, PDO::PARAM_STR);
                            $check->bindParam(2, $email, PDO::PARAM_STR);
                            $check->execute();
                            $user_exists = $check->fetch(PDO::FETCH_ASSOC) !== false;
                            $check->closeCursor();
                        }
                    } catch (Exception $e) {
                        $message = '❌ Database error: ' . $e->getMessage();
                        $message_type = 'danger';
                        $user_exists = false;
                    }
                } else {
                    $check = $connection->prepare($check_query);
                    if (!$check) {
                        $message = '❌ Database error: ' . $connection->error;
                        $message_type = 'danger';
                    } else {
                        $check->bind_param('ss', $username, $email);
                        $check->execute();
                        $result = $check->fetch(PDO::FETCH_ASSOC);
                        $user_exists = $result !== false;
                        $check->close();
                    }
                }

                if (!empty($message)) {
                    // Error already set
                } elseif ($user_exists) {
                    $message = '❌ User with this username or email already exists.';
                    $message_type = 'danger';
                } else {
                    // Generate temporary password using PasswordManager
                    $temporary_password = PasswordManager::generateTemporaryPassword();
                    $password_hash = PasswordManager::hashPassword($temporary_password);

                    // Insert user with temporary password and must_change_password flag
                    $insert_query = "INSERT INTO users (username, email, password_hash, role, phone, company_id, tenant_id, is_active, must_change_password, temporary_password, password_generated_at) VALUES (?, ?, ?, ?, ?, ?, ?, 1, 1, ?, " . get_current_timestamp_sql() . ")";
                    if ($db_type === 'sqlite') {
                        try {
                            $stmt = $connection->prepare($insert_query);
                            if (!$stmt) {
                                $message = '❌ Database error: Unable to prepare statement';
                                $message_type = 'danger';
                            } else {
                                $stmt->bindParam(1, $username, PDO::PARAM_STR);
                                $stmt->bindParam(2, $email, PDO::PARAM_STR);
                                $stmt->bindParam(3, $password_hash, PDO::PARAM_STR);
                                $stmt->bindParam(4, $role, PDO::PARAM_STR);
                                $stmt->bindParam(5, $phone, PDO::PARAM_STR);
                                $stmt->bindParam(6, $company_id, PDO::PARAM_INT);
                                $stmt->bindParam(7, $company_id, PDO::PARAM_INT); // tenant_id = company_id
                                $stmt->bindParam(8, $temporary_password, PDO::PARAM_STR);
                                $success = $stmt->execute();
                                if ($success) {
                                    // Log user creation
                                    $new_user_id = $connection->lastInsertId();
                                    $audit = new AuditLogger($connection, $db_type);
                                    $user_data = [
                                        'username' => $username,
                                        'email' => $email,
                                        'role' => $role,
                                        'phone' => $phone,
                                        'company_id' => $company_id
                                    ];
                                    $audit->logUserCreated($_SESSION['user_id'] ?? 0, $new_user_id, $user_data);
                                    
                                    $message = '✅ User <strong>' . htmlspecialchars($username) . '</strong> created successfully!<br>Temporary password: <code style="background: #f0f0f0; padding: 2px 6px; border-radius: 3px;">' . htmlspecialchars($temporary_password) . '</code><br><a href="pending_users_dashboard.php" style="color: #667eea; text-decoration: underline; font-weight: 600;">→ View in Pending Users Dashboard</a>';
                                    $message_type = 'success';
                                } else {
                                    $message = '❌ Failed to create user.';
                                    $message_type = 'danger';
                                }
                                $stmt->closeCursor();
                            }
                        } catch (Exception $e) {
                            $message = '❌ Database error: ' . $e->getMessage();
                            $message_type = 'danger';
                        }
                    } else {
                        $stmt = $connection->prepare($insert_query);
                        if (!$stmt) {
                            $message = '❌ Database error: ' . $connection->error;
                            $message_type = 'danger';
                        } else {
                            $stmt->bind_param('sssssiis', $username, $email, $password_hash, $role, $phone, $company_id, $company_id, $temporary_password);
                            if ($stmt->execute()) {
                                // Log user creation
                                $new_user_id = $connection->insert_id;
                                $audit = new AuditLogger($connection, $db_type);
                                $user_data = [
                                    'username' => $username,
                                    'email' => $email,
                                    'role' => $role,
                                    'phone' => $phone,
                                    'company_id' => $company_id
                                ];
                                $audit->logUserCreated($_SESSION['user_id'] ?? 0, $new_user_id, $user_data);
                                
                                $message = '✅ User <strong>' . htmlspecialchars($username) . '</strong> created successfully!<br>Temporary password: <code style="background: #f0f0f0; padding: 2px 6px; border-radius: 3px;">' . htmlspecialchars($temporary_password) . '</code><br><a href="pending_users_dashboard.php" style="color: #667eea; text-decoration: underline; font-weight: 600;">→ View in Pending Users Dashboard</a>';
                                $message_type = 'success';
                            } else {
                                $message = '❌ Failed to create user: ' . $stmt->error;
                                $message_type = 'danger';
                            }
                            $stmt->close();
                        }
                    }
                }
            }
            break;

        case 'create_role':
            $role_name = trim($_POST['role_name'] ?? '');
            $role_description = trim($_POST['role_description'] ?? '');
            $permissions = $_POST['permissions'] ?? [];

            if (empty($role_name)) {
                $message = '❌ Role name is required.';
                $message_type = 'danger';
            } else {
                // Check if role exists
                $check = $connection->prepare("SELECT role_id FROM roles WHERE role_name = ?");
                $check->bindParam(1, $role_name, PDO::PARAM_STR);
                $check->execute();
                if ($check->fetch(PDO::FETCH_ASSOC) !== false) {
                    $message = '❌ A role with this name already exists.';
                    $message_type = 'danger';
                } else {
                    // Create role
                    $stmt = $connection->prepare("INSERT INTO roles (role_name, role_description) VALUES (?, ?)");
                    if ($stmt) {
                        $stmt->bindParam(1, $role_name, PDO::PARAM_STR);
                        $stmt->bindParam(2, $role_description, PDO::PARAM_STR);
                        if ($stmt->execute()) {
                            $message = '✅ Role <strong>' . htmlspecialchars($role_name) . '</strong> created successfully.';
                            $message_type = 'success';
                        } else {
                            $error_info = $stmt->errorInfo();
                            $message = '❌ Failed to create role: ' . ($error_info[2] ?? 'Unknown error');
                            $message_type = 'danger';
                        }
                        $stmt->closeCursor();
                    } else {
                        $message = '❌ Database error.';
                        $message_type = 'danger';
                    }
                }
                $check->closeCursor();
            }
            break;

        case 'activate_user':
            $user_id = (int)($_POST['user_id'] ?? 0);
            if ($user_id > 0) {
                $stmt = $connection->prepare("UPDATE users SET is_active = 1, is_locked = 0 WHERE user_id = ?");
                if ($stmt) {
                    $stmt->bind_param('i', $user_id);
                    if ($stmt->execute()) {
                        $message = '✅ User activated successfully.';
                        $message_type = 'success';
                    } else {
                        $message = '❌ Failed to activate user.';
                        $message_type = 'danger';
                    }
                    $stmt->close();
                }
            }
            break;

        case 'deactivate_user':
            $user_id = (int)($_POST['user_id'] ?? 0);
            if ($user_id > 0) {
                $stmt = $connection->prepare("UPDATE users SET is_active = 0, is_locked = 1 WHERE user_id = ?");
                if ($stmt) {
                    $stmt->bind_param('i', $user_id);
                    if ($stmt->execute()) {
                        $message = '🔒 User deactivated successfully.';
                        $message_type = 'warning';
                    } else {
                        $message = '❌ Failed to deactivate user.';
                        $message_type = 'danger';
                    }
                    $stmt->close();
                }
            }
            break;

        case 'deploy_update':
            $update_id = (int)($_POST['update_id'] ?? 0);
            $company_id = (int)($_POST['deploy_company_id'] ?? 0);

            if ($update_id > 0 && $company_id > 0) {
                $stmt = $connection->prepare("INSERT INTO update_deployments (update_id, company_id, deployment_status, started_at, deployed_by) VALUES (?, ?, 'in_progress', datetime('now'), ?)");
                if ($stmt) {
                    $stmt->bind_param('iii', $update_id, $company_id, $_SESSION['user_id']);
                    if ($stmt->execute()) {
                        $message = '📦 Update deployment initiated.';
                        $message_type = 'info';
                    } else {
                        $message = '❌ Failed to initiate deployment.';
                        $message_type = 'danger';
                    }
                    $stmt->close();
                }
            }
            break;

        case 'create_company':
            $company_name = trim($_POST['company_name'] ?? '');
            $company_email = trim($_POST['company_email'] ?? '');
            $contact_name = trim($_POST['contact_name'] ?? '');
            $contact_phone = trim($_POST['contact_phone'] ?? '');
            $license_type = $_POST['license_type'] ?? 'basic';
            $purchased_seats = (int)($_POST['purchased_seats'] ?? 1);

            if (empty($company_name)) {
                $message = 'Company name is required.';
                $message_type = 'danger';
            } else {
                // Create company
                $stmt = $connection->prepare("INSERT INTO companies (company_name, company_email, contact_name, contact_phone) VALUES (?, ?, ?, ?)");
                if ($stmt) {
                    $stmt->bind_param('ssss', $company_name, $company_email, $contact_name, $contact_phone);
                    if ($stmt->execute()) {
                        $company_id = $stmt->insert_id;

                        // Generate license key
                        $license_key = generate_license_key();

                        // Create license
                        $license_stmt = $connection->prepare("INSERT INTO company_licenses (company_id, license_key, purchased_seats, license_type) VALUES (?, ?, ?, ?)");
                        if ($license_stmt) {
                            $license_stmt->bind_param('isis', $company_id, $license_key, $purchased_seats, $license_type);
                            $license_stmt->execute();
                            $license_stmt->close();
                        }

                        $message = "Company created successfully. License Key: $license_key";
                        $message_type = 'success';
                    } else {
                        $message = 'Failed to create company.';
                        $message_type = 'danger';
                    }
                    $stmt->close();
                }
            }
            break;

        case 'delete_user':
            $user_id = (int)($_POST['user_id'] ?? 0);
            if ($user_id > 0) {
                $stmt = $connection->prepare("DELETE FROM users WHERE user_id = ?");
                if ($stmt) {
                    $stmt->bind_param('i', $user_id);
                    if ($stmt->execute()) {
                        $message = 'User deleted successfully.';
                        $message_type = 'success';
                    } else {
                        $message = 'Failed to delete user.';
                        $message_type = 'danger';
                    }
                    $stmt->close();
                }
            }
            break;

        case 'reset_company_data':
            $company_id = (int)($_POST['reset_company_id'] ?? 0);
            if ($company_id > 0) {
                // Delete work orders, equipment, inventory, etc. for this company
                $tables_to_clear = [
                    'work_orders',
                    'equipment',
                    'inventory_items',
                    'purchase_orders',
                    'goods_receipt_notes',
                    'maintenance_schedules',
                    'audit_logs'
                ];

                $cleared_count = 0;
                foreach ($tables_to_clear as $table) {
                    if (table_exists($table)) {
                        $stmt = $connection->prepare("DELETE FROM $table WHERE company_id = ?");
                        if ($stmt) {
                            $stmt->bind_param('i', $company_id);
                            $stmt->execute();
                            $cleared_count += $stmt->affected_rows;
                            $stmt->close();
                        }
                    }
                }

                $message = "Company data reset successfully. $cleared_count records cleared.";
                $message_type = 'success';
            }
            break;

        case 'delete_company':
            $company_id = (int)($_POST['delete_company_id'] ?? 0);
            if ($company_id > 0) {
                // First, delete all associated data
                $tables_to_clear = [
                    'work_orders',
                    'equipment',
                    'inventory_items',
                    'purchase_orders',
                    'goods_receipt_notes',
                    'maintenance_schedules',
                    'audit_logs',
                    'users',
                    'system_control'
                ];

                $deleted_count = 0;
                foreach ($tables_to_clear as $table) {
                    if (table_exists($table)) {
                        if ($db_type === 'sqlite') {
                            try {
                                $stmt = $connection->prepare("DELETE FROM $table WHERE company_id = ?");
                                if ($stmt) {
                                    $stmt->bindParam(1, $company_id, PDO::PARAM_INT);
                                    $stmt->execute();
                                    $deleted_count += $stmt->rowCount();
                                    $stmt->closeCursor();
                                }
                            } catch (Exception $e) {
                                // Continue with other tables
                            }
                        } else {
                            $stmt = $connection->prepare("DELETE FROM $table WHERE company_id = ?");
                            if ($stmt) {
                                $stmt->bind_param('i', $company_id);
                                $stmt->execute();
                                $deleted_count += $stmt->affected_rows;
                                $stmt->close();
                            }
                        }
                    }
                }

                // Finally, delete the company itself
                if ($db_type === 'sqlite') {
                    try {
                        $stmt = $connection->prepare("DELETE FROM companies WHERE company_id = ?");
                        if ($stmt) {
                            $stmt->bindParam(1, $company_id, PDO::PARAM_INT);
                            $success = $stmt->execute();
                            $stmt->closeCursor();
                            if ($success) {
                                $message = 'Company and all associated data deleted successfully. ' . $deleted_count . ' records removed.';
                                $message_type = 'success';
                            } else {
                                $message = 'Failed to delete company.';
                                $message_type = 'danger';
                            }
                        }
                    } catch (Exception $e) {
                        $message = 'Failed to delete company: ' . $e->getMessage();
                        $message_type = 'danger';
                    }
                } else {
                    $stmt = $connection->prepare("DELETE FROM companies WHERE company_id = ?");
                    if ($stmt) {
                        $stmt->bind_param('i', $company_id);
                        if ($stmt->execute()) {
                            $message = 'Company and all associated data deleted successfully. ' . $deleted_count . ' records removed.';
                            $message_type = 'success';
                        } else {
                            $message = 'Failed to delete company.';
                            $message_type = 'danger';
                        }
                        $stmt->close();
                    }
                }
            }
            break;
    }
}

// Helper functions

/**
 * Execute a database query safely for both SQLite and MySQL
 * @param mixed $connection Database connection
 * @param string $query SQL query with ? placeholders
 * @param array $params Parameter values to bind
 * @param string $types PDO types or MySQLi type string (e.g., 'ssi')
 * @return mixed Result for SELECT, true/false for INSERT/UPDATE
 */
function execute_db_query($connection, $query, $params = [], $types = '') {
    global $db_type;
    
    try {
        if ($db_type === 'sqlite') {
            $stmt = $connection->prepare($query);
            if (!$stmt) {
                throw new Exception("Failed to prepare statement: " . print_r($connection->errorInfo(), true));
            }
            for ($i = 0; $i < count($params); $i++) {
                $pdo_type = PDO::PARAM_STR;
                if (!empty($types) && isset($types[$i])) {
                    $type_char = $types[$i];
                    if ($type_char === 'i') $pdo_type = PDO::PARAM_INT;
                    elseif ($type_char === 'b') $pdo_type = PDO::PARAM_LOB;
                }
                $stmt->bindParam($i + 1, $params[$i], $pdo_type);
            }
            $stmt->execute();
            return $stmt;
        } else {
            $stmt = $connection->prepare($query);
            if (!$stmt) {
                throw new Exception("Failed to prepare statement: " . $connection->error);
            }
            if (!empty($params) && !empty($types)) {
                $stmt->bind_param($types, ...$params);
            }
            $stmt->execute();
            return $stmt;
        }
    } catch (Exception $e) {
        error_log("Database query error: " . $e->getMessage());
        throw $e;
    }
}
if (!function_exists('generate_license_key')) {
    function generate_license_key() {
        return strtoupper(substr(md5(uniqid(mt_rand(), true)), 0, 16));
    }
}

function fetch_result_rows($result) {
    global $db_type;

    if (!$result) {
        return [];
    }

    if ($db_type === 'sqlite') {
        // PDO handling
        if (method_exists($result, 'fetchAll')) {
            return $result->fetchAll(PDO::FETCH_ASSOC);
        }
        // Fallback
        $rows = [];
        while ($row = $result->fetch(PDO::FETCH_ASSOC)) {
            $rows[] = $row;
        }
        return $rows;
    } else {
        // MySQLi handling
        if (method_exists($result, 'fetch_all')) {
            return $result->fetch_all(MYSQLI_ASSOC);
        }
        // Fallback
        $rows = [];
        while ($row = fetch_assoc_compatible($result)) {
            $rows[] = $row;
        }
        return $rows;
    }
}

// Get data for display
$roles = [];
$permissions = [];
$users = [];
$companies = [];
$system_controls = [];
$recent_payments = [];
$system_updates = [];
$analytics_summary = [];

// Safely get data with error handling
try {
    // Get roles
    $result = $connection->query("SELECT * FROM roles ORDER BY role_name");
    if ($result) {
        $roles = fetch_result_rows($result);
    }
} catch (Exception $e) {
    error_log("Error fetching roles: " . $e->getMessage());
}

try {
    // Get permissions
    $result = $connection->query("SELECT * FROM permissions ORDER BY resource, action");
    if ($result) {
        $permissions = fetch_result_rows($result);
    }
} catch (Exception $e) {
    error_log("Error fetching permissions: " . $e->getMessage());
}

try {
    // Get users with company info
    $result = $connection->query("
        SELECT u.*, c.company_name
        FROM users u
        LEFT JOIN companies c ON u.company_id = c.company_id
        ORDER BY u.username
    ");
    if ($result) {
        $users = fetch_result_rows($result);
    }
} catch (Exception $e) {
    error_log("Error fetching users: " . $e->getMessage());
}

try {
    // Get companies with license and system control info
    $result = $connection->query("
        SELECT c.*, cl.license_id, cl.license_key, cl.purchased_seats, cl.used_seats, cl.license_type,
               sc.system_activated, sc.system_locked, sc.subscription_status, sc.max_users,
               sc.current_users, sc.feature_tier, sc.subscription_expires_at, sc.system_version
        FROM companies c
        LEFT JOIN company_licenses cl ON c.company_id = cl.company_id AND cl.is_active = 1
        LEFT JOIN system_control sc ON c.company_id = sc.company_id
        ORDER BY c.company_name
    ");
    if ($result) {
        $companies = fetch_result_rows($result);
        // Debug: Log if any company has no license
        foreach ($companies as $co) {
            if (empty($co['license_key']) && empty($co['license_id'])) {
                error_log("WARNING: Company ID " . $co['company_id'] . " ({$co['company_name']}) has no license attached");
                // Try to fetch from table directly as fallback
                $fallback_result = $connection->query("SELECT * FROM company_licenses WHERE company_id = " . intval($co['company_id']) . " ORDER BY license_id DESC LIMIT 1");
                if ($fallback_result) {
                    $fallback_rows = fetch_result_rows($fallback_result);
                    if (!empty($fallback_rows)) {
                        error_log("  → But found license in DB: " . $fallback_rows[0]['license_key']);
                    } else {
                        error_log("  → No licenses found in company_licenses table");
                    }
                }
            }
        }
    }
} catch (Exception $e) {
    error_log("Error fetching companies: " . $e->getMessage());
}

try {
    // Get recent payments
    $result = $connection->query("
        SELECT sp.*, c.company_name
        FROM subscription_payments sp
        JOIN companies c ON sp.company_id = c.company_id
        ORDER BY sp.payment_date DESC LIMIT 10
    ");
    if ($result) {
        $recent_payments = fetch_result_rows($result);
    }
} catch (Exception $e) {
    error_log("Error fetching payments: " . $e->getMessage());
}

try {
    // Get system updates
    $result = $connection->query("SELECT * FROM system_updates ORDER BY release_date DESC LIMIT 10");
    if ($result) {
        $system_updates = fetch_result_rows($result);
    }
} catch (Exception $e) {
    error_log("Error fetching system updates: " . $e->getMessage());
}

try {
    // Get analytics summary
    if ($db_type === 'sqlite') {
        $result = $connection->query("
            SELECT
                COUNT(DISTINCT sa.company_id) as active_companies,
                AVG(sa.metric_value) as avg_system_load,
                COUNT(*) as total_metrics
            FROM system_analytics sa
            WHERE sa.recorded_at >= datetime('now', '-1 day')
        ");
    } else {
        $result = $connection->query("
            SELECT
                COUNT(DISTINCT sa.company_id) as active_companies,
                AVG(sa.metric_value) as avg_system_load,
                COUNT(*) as total_metrics
            FROM system_analytics sa
            WHERE sa.recorded_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
        ");
    }
    if ($result) {
        $analytics_summary = fetch_assoc_compatible($result);
    }
} catch (Exception $e) {
    error_log("Error fetching analytics: " . $e->getMessage());
}
?>

<style>
.saas-control-container {
    max-width: 1600px;
    margin: 0 auto;
    padding: 20px;
    font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
}

.control-header {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    padding: 20px;
    border-radius: 12px;
    margin-bottom: 25px;
    box-shadow: 0 4px 6px rgba(0,0,0,0.1);
}

.control-header h3 {
    margin: 0;
    font-size: 1.8em;
    font-weight: 600;
}

.control-header p {
    margin: 8px 0 0 0;
    opacity: 0.9;
    font-size: 1.1em;
}

.saas-control-container {
    max-width: 1320px;
    margin: 30px auto;
    padding: 30px 28px 40px;
    background: linear-gradient(180deg, #f5f8ff 0%, #ffffff 100%);
    border-radius: 24px;
    box-shadow: 0 30px 60px rgba(15, 36, 97, 0.12);
}

.control-header {
    text-align: left;
    padding-bottom: 20px;
    border-bottom: 1px solid rgba(136, 152, 210, 0.18);
    margin-bottom: 28px;
}

.control-header h3 {
    margin: 0;
    font-size: 2rem;
    line-height: 1.1;
    color: #1f2d55;
}

.control-header p {
    margin: 10px 0 0;
    color: #5f6f94;
    opacity: 0.95;
    font-size: 1rem;
    max-width: 760px;
}

.control-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(340px, 1fr));
    gap: 22px;
    margin-bottom: 28px;
}

.control-card {
    background: linear-gradient(180deg, #ffffff 0%, #f8fbff 100%);
    border-radius: 18px;
    padding: 24px;
    box-shadow: 0 18px 45px rgba(79, 114, 220, 0.08);
    border: 1px solid rgba(102, 126, 234, 0.18);
    transition: transform 0.25s ease, box-shadow 0.25s ease;
}

.control-card:hover {
    transform: translateY(-3px);
    box-shadow: 0 26px 60px rgba(79, 114, 220, 0.14);
}

.control-card h4 {
    margin: 0 0 18px 0;
    color: #16254d;
    font-size: 1.25em;
    font-weight: 700;
}

.control-card .card-icon {
    font-size: 1.6em;
    margin-right: 10px;
    color: #4f72dc;
}

.status-active { color: #28a745; font-weight: 600; }
.status-inactive { color: #6c757d; }
.status-locked { color: #dc3545; font-weight: 600; }
.status-expired { color: #fd7e14; font-weight: 600; }
.status-trial { color: #17a2b8; font-weight: 600; }

.metric-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 15px;
    margin: 15px 0;
}

.metric-card {
    background: linear-gradient(180deg, #eef4ff 0%, #ffffff 100%);
    padding: 20px 18px;
    border-radius: 16px;
    text-align: center;
    border-left: 5px solid #5b7cec;
    box-shadow: 0 12px 24px rgba(43, 82, 165, 0.08);
}

.metric-value {
    font-size: 2rem;
    font-weight: 800;
    color: #183568;
    display: block;
}

.metric-label {
    font-size: 0.9em;
    color: #6c757d;
    margin-top: 5px;
}

.form-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 15px;
}

.form-group {
    margin-bottom: 15px;
}

.form-group label {
    display: block;
    margin-bottom: 5px;
    font-weight: 600;
    color: #2c3e50;
}

.form-group input, .form-group select, .form-group textarea {
    width: 100%;
    padding: 14px 14px;
    border: 1px solid #ced4da;
    border-radius: 14px;
    font-size: 15px;
    transition: border-color 0.3s, box-shadow 0.3s;
    background: #f8fbff;
}

.form-group input:focus, .form-group select:focus, .form-group textarea:focus {
    outline: none;
    border-color: #5b7cec;
    box-shadow: 0 0 0 4px rgba(91, 124, 236, 0.12);
}

.btn-saas {
    padding: 12px 22px;
    border: none;
    border-radius: 999px;
    cursor: pointer;
    font-size: 14px;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.6px;
    transition: transform 0.25s ease, box-shadow 0.25s ease, background 0.25s ease;
    box-shadow: 0 10px 24px rgba(39, 108, 247, 0.14);
}

.btn-saas:hover {
    transform: translateY(-1px);
}

.btn-activate {
    background: linear-gradient(135deg, #2596be, #3ea7f8);
    color: white;
}
.btn-activate:hover {
    background: linear-gradient(135deg, #1f81b1, #2b95e1);
}

.btn-deactivate {
    background: linear-gradient(135deg, #f05d66, #d93b4f);
    color: white;
}
.btn-deactivate:hover {
    background: linear-gradient(135deg, #d94b55, #c42c3f);
}

.btn-update {
    background: linear-gradient(135deg, #5062ff, #1a48d1);
    color: white;
}
.btn-update:hover {
    background: linear-gradient(135deg, #3f4ee5, #163bb5);
}

.btn-payment {
    background: linear-gradient(135deg, #f7c948, #f59f1b);
    color: #212529;
}
.btn-payment:hover {
    background: linear-gradient(135deg, #e5b32d, #d48610);
}

.data-table {
    width: 100%;
    border-collapse: collapse;
    margin-top: 15px;
    background: white;
    border-radius: 8px;
    overflow: hidden;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
}

.data-table th, .data-table td {
    padding: 12px 15px;
    text-align: left;
    border-bottom: 1px solid #e1e5e9;
}

.data-table th {
    background-color: #f8f9fa;
    font-weight: 600;
    color: #2c3e50;
    font-size: 0.9em;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.data-table tr:hover {
    background-color: #f8f9fa;
}

.alert {
    padding: 15px 20px;
    border-radius: 8px;
    margin-bottom: 20px;
    border-left: 4px solid;
}

.alert-success { background-color: #d4edda; color: #155724; border-left-color: #28a745; }
.alert-danger { background-color: #f8d7da; color: #721c24; border-left-color: #dc3545; }
.alert-warning { background-color: #fff3cd; color: #856404; border-left-color: #ffc107; }
.alert-info { background-color: #d1ecf1; color: #0c5460; border-left-color: #17a2b8; }

.license-key {
    font-family: 'Courier New', monospace;
    background-color: #f8f9fa;
    padding: 2px 6px;
    border-radius: 4px;
    font-size: 0.85em;
    font-weight: 600;
}

.system-status {
    display: inline-block;
    padding: 4px 8px;
    border-radius: 12px;
    font-size: 0.8em;
    font-weight: 600;
    text-transform: uppercase;
}

.badge-locked { background-color: #dc3545; color: white; }
.badge-active { background-color: #28a745; color: white; }
.badge-inactive { background-color: #6c757d; color: white; }
.badge-expired { background-color: #fd7e14; color: white; }
.badge-trial { background-color: #17a2b8; color: white; }

.tabs {
    display: flex;
    margin-bottom: 20px;
    border-bottom: 1px solid #e1e5e9;
}

.tab-button {
    padding: 12px 24px;
    background: none;
    border: none;
    border-bottom: 3px solid transparent;
    cursor: pointer;
    font-size: 1em;
    font-weight: 600;
    color: #6c757d;
    transition: all 0.3s;
}

.tab-button.active {
    color: #667eea;
    border-bottom-color: #667eea;
}

.tab-content {
    display: none;
}

.tab-content.active {
    display: block;
}

.btn-warning {
    background-color: #ffc107;
    color: #212529;
}

.btn-warning:hover {
    background-color: #e0a800;
}
</style>

<div class="saas-control-container">
    <div class="control-header">
        <h3><i class="fas fa-crown"></i> 🏢 SaaS Control Center - Efficraft Technologies</h3>
        <p>Professional cloud-based CMMS management platform with enterprise-grade controls</p>
    </div>

    <?php if ($message): ?>
    <div class="alert alert-<?php echo $message_type; ?>">
        <i class="fas fa-info-circle"></i> <?php echo $message; ?>
    </div>
    <?php endif; ?>

    <!-- System Overview Metrics -->
    <div class="control-grid">
        <div class="control-card">
            <h4><i class="fas fa-chart-line card-icon"></i>System Overview</h4>
            <div class="metric-grid">
                <div class="metric-card">
                    <span class="metric-value"><?php echo count($companies); ?></span>
                    <span class="metric-label">Total Companies</span>
                </div>
                <div class="metric-card">
                    <span class="metric-value"><?php echo count($users); ?></span>
                    <span class="metric-label">Total Users</span>
                </div>
                <div class="metric-card">
                    <span class="metric-value"><?php echo $analytics_summary['active_companies'] ?? 0; ?></span>
                    <span class="metric-label">Active Systems</span>
                </div>
                <div class="metric-card">
                    <span class="metric-value">$<?php echo number_format(array_sum(array_column($recent_payments, 'amount')), 0); ?></span>
                    <span class="metric-label">Revenue (30 days)</span>
                </div>
            </div>
        </div>

        <div class="control-card">
            <h4><i class="fas fa-shield-alt card-icon"></i>System Health</h4>
            <div class="metric-grid">
                <div class="metric-card">
                    <span class="metric-value status-active"><?php echo count(array_filter($companies, fn($c) => ($c['system_activated'] ?? false))); ?></span>
                    <span class="metric-label">Activated Systems</span>
                </div>
                <div class="metric-card">
                    <span class="metric-value status-locked"><?php echo count(array_filter($companies, fn($c) => ($c['system_locked'] ?? false))); ?></span>
                    <span class="metric-label">Locked Systems</span>
                </div>
                <div class="metric-card">
                    <span class="metric-value status-trial"><?php echo count(array_filter($companies, fn($c) => ($c['subscription_status'] ?? '') === 'trial')); ?></span>
                    <span class="metric-label">Trial Accounts</span>
                </div>
                <div class="metric-card">
                    <span class="metric-value"><?php echo count($system_updates); ?></span>
                    <span class="metric-label">Available Updates</span>
                </div>
            </div>
        </div>
    </div>

    <!-- Tabbed Interface -->
    <div class="tabs">
        <button class="tab-button active" onclick="showTab('add-company')">➕ Add Company</button>
        <button class="tab-button" onclick="showTab('companies')">🏢 Companies</button>
        <button class="tab-button" onclick="showTab('users-roles')">👥 Users & Roles</button>
        <button class="tab-button" onclick="showTab('subscriptions')">💰 Subscriptions</button>
        <button class="tab-button" onclick="showTab('system-control')">⚙️ System Control</button>
        <button class="tab-button" onclick="showTab('updates')">🚀 Updates</button>
        <button class="tab-button" onclick="showTab('analytics')">📊 Analytics</button>
        <a href="pending_users_dashboard.php" class="tab-button" style="text-decoration:none;color:inherit;">📋 Pending Users</a>
    </div>

    <!-- Add Company Tab -->
    <div id="add-company" class="tab-content active">
        <div class="control-card">
            <h4><i class="fas fa-plus card-icon"></i>Register New Company</h4>
            <form method="post">
                <input type="hidden" name="action" value="add_company">
                <div class="form-grid">
                    <div class="form-group">
                        <label>Company Name: <span style="color: red;">*</span></label>
                        <input type="text" name="company_name" placeholder="e.g., Acme Corporation" required>
                    </div>
                    <div class="form-group">
                        <label>Company Email: <span style="color: red;">*</span></label>
                        <input type="email" name="company_email" placeholder="contact@company.com" required>
                    </div>
                    <div class="form-group">
                        <label>Contact Name:</label>
                        <input type="text" name="contact_name" placeholder="e.g., John Doe">
                    </div>
                    <div class="form-group">
                        <label>Contact Phone:</label>
                        <input type="tel" name="phone" placeholder="(555) 123-4567">
                    </div>
                    <div class="form-group">
                        <label>Industry:</label>
                        <input type="text" name="industry" placeholder="e.g., Manufacturing, Healthcare">
                    </div>
                    <div class="form-group">
                        <label>Company Size:</label>
                        <select name="company_size">
                            <option value="">Select Size</option>
                            <option value="1-50">1-50 employees</option>
                            <option value="51-200">51-200 employees</option>
                            <option value="201-500">201-500 employees</option>
                            <option value="501-1000">501-1000 employees</option>
                            <option value="1000+">1000+ employees</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Initial Feature Tier:</label>
                        <select name="initial_tier">
                            <option value="trial">Trial (30 days, 5 users)</option>
                            <option value="basic">Basic ($49/mo, 25 users)</option>
                            <option value="professional">Professional ($99/mo, 100 users)</option>
                            <option value="enterprise">Enterprise ($199/mo, Unlimited)</option>
                        </select>
                    </div>
                    <div class="form-group" style="grid-column: span 2;">
                        <label>Auto-activate System:</label>
                        <input type="checkbox" name="auto_activate" value="1"> Check to activate immediately
                    </div>
                </div>
                <button type="submit" class="btn-saas btn-activate" style="margin-top: 15px;">Register Company</button>
            </form>
        </div>
    </div>
    <div id="companies" class="tab-content">
        <div class="control-card">
            <h4><i class="fas fa-building card-icon"></i>Company & License Management</h4>
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Company</th>
                        <th>License Key</th>
                        <th>System Status</th>
                        <th>Subscription</th>
                        <th>Users</th>
                        <th>Tier</th>
                        <th>Expires</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($companies as $company): ?>
                    <tr>
                        <td>
                            <strong><?php echo htmlspecialchars($company['company_name']); ?></strong><br>
                            <small><?php echo htmlspecialchars($company['contact_name'] ?? ''); ?></small>
                        </td>
                        <td><span class="license-key"><?php echo htmlspecialchars($company['license_key'] ?? 'No License'); ?></span></td>
                        <td>
                            <?php
                            $activated = $company['system_activated'] ?? false;
                            $locked = $company['system_locked'] ?? false;
                            if ($locked) {
                                echo '<span class="system-status badge-locked">🔒 Locked</span>';
                            } elseif ($activated) {
                                echo '<span class="system-status badge-active">✅ Active</span>';
                            } else {
                                echo '<span class="system-status badge-inactive">⏸️ Inactive</span>';
                            }
                            ?>
                        </td>
                        <td>
                            <?php
                            $status = $company['subscription_status'] ?? 'trial';
                            $class = match($status) {
                                'active' => 'badge-active',
                                'expired' => 'badge-expired',
                                'trial' => 'badge-trial',
                                'suspended' => 'badge-locked',
                                default => 'badge-inactive'
                            };
                            echo '<span class="system-status ' . $class . '">' . ucfirst($status) . '</span>';
                            ?>
                        </td>
                        <td><?php echo ($company['current_users'] ?? 0) . '/' . ($company['max_users'] ?? 5); ?></td>
                        <td><?php echo ucfirst($company['feature_tier'] ?? 'trial'); ?></td>
                        <td><?php echo $company['subscription_expires_at'] ? date('M j, Y', strtotime($company['subscription_expires_at'])) : 'Never'; ?></td>
                        <td>
                            <form method="post" style="display: inline;" onsubmit="activateSystem(event, <?php echo $company['company_id']; ?>)">
                                <input type="hidden" name="action" value="activate_system">
                                <input type="hidden" name="company_id" value="<?php echo $company['company_id']; ?>">
                                <button type="submit" class="btn-saas btn-activate" style="font-size: 11px; padding: 6px 10px;">Activate</button>
                            </form>
                            <form method="post" style="display: inline;">
                                <input type="hidden" name="action" value="deactivate_system">
                                <input type="hidden" name="company_id" value="<?php echo $company['company_id']; ?>">
                                <input type="hidden" name="lock_reason" value="Administrative deactivation">
                                <button type="submit" class="btn-saas btn-deactivate" style="font-size: 11px; padding: 6px 10px;" onclick="return confirm('Lock this system?')">Lock</button>
                            </form>
                            <form method="post" style="display: inline;" onsubmit="return confirm('Are you sure you want to permanently delete this company and ALL associated data? This action cannot be undone!')">
                                <input type="hidden" name="action" value="delete_company">
                                <input type="hidden" name="delete_company_id" value="<?php echo $company['company_id']; ?>">
                                <button type="submit" class="btn-saas btn-deactivate" style="font-size: 11px; padding: 6px 10px; background: linear-gradient(135deg, #dc3545, #c82333);">
                                    🗑️ Delete
                                </button>
                            </form>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Users & Roles Tab -->
    <div id="users-roles" class="tab-content">
        <div class="control-grid">
            <div class="control-card">
                <h4><i class="fas fa-user-plus card-icon"></i>Create New User</h4>
                <form method="post">
                    <input type="hidden" name="action" value="create_user">
                    <div class="form-grid">
                        <div class="form-group">
                            <label>Company:</label>
                            <select name="company_id" required style="grid-column: span 2;">
                                <option value="">Select Company</option>
                                <?php foreach ($companies as $company): ?>
                                <option value="<?php echo $company['company_id']; ?>"><?php echo htmlspecialchars($company['company_name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Username: <span style="color: red;">*</span></label>
                            <input type="text" name="username" placeholder="username" required>
                        </div>
                        <div class="form-group">
                            <label>Email: <span style="color: red;">*</span></label>
                            <input type="email" name="email" placeholder="user@company.com" required>
                        </div>
                        <div class="form-group">
                            <label>Phone:</label>
                            <input type="tel" name="phone" placeholder="(555) 123-4567">
                        </div>
                        <div class="form-group">
                            <label>Role:</label>
                            <select name="role" required>
                                <option value="operator">Operator</option>
                                <option value="technician">Technician</option>
                                <option value="supervisor">Supervisor</option>
                                <option value="maintenance manager">Maintenance Manager</option>
                                <option value="admin">Admin</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Password: <span style="color: red;">*</span></label>
                            <input type="password" name="password" placeholder="Temporary password" required style="grid-column: span 2;">
                        </div>
                    </div>
                    <button type="submit" class="btn-saas btn-activate">Create User</button>
                </form>
            </div>

            <div class="control-card">
                <h4><i class="fas fa-shield-alt card-icon"></i>Create New Role</h4>
                <form method="post">
                    <input type="hidden" name="action" value="create_role">
                    <div class="form-grid">
                        <div class="form-group">
                            <label>Role Name: <span style="color: red;">*</span></label>
                            <input type="text" name="role_name" placeholder="e.g., Equipment Manager" required style="grid-column: span 2;">
                        </div>
                        <div class="form-group">
                            <label>Description:</label>
                            <textarea name="role_description" placeholder="Role description..." rows="3" style="grid-column: span 2;"></textarea>
                        </div>
                        <div style="grid-column: span 2;">
                            <label style="font-weight: 600; display: block; margin-bottom: 10px;">Permissions:</label>
                            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px;">
                                <?php 
                                $permission_groups = ['equipment' => 'Equipment Management', 'inventory' => 'Inventory Management', 'work_orders' => 'Work Orders', 'reports' => 'Reports', 'admin' => 'Admin Functions'];
                                foreach ($permission_groups as $key => $label): ?>
                                <div><label><input type="checkbox" name="permissions" value="<?php echo $key; ?>"> <?php echo $label; ?></label></div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                    <button type="submit" class="btn-saas btn-update" style="margin-top: 15px;">Create Role</button>
                </form>
            </div>
        </div>

        <!-- Users List -->
        <div class="control-card">
            <h4><i class="fas fa-list card-icon"></i>All Users</h4>
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Username</th>
                        <th>Email</th>
                        <th>Company</th>
                        <th>Role</th>
                        <th>Phone</th>
                        <th>Status</th>
                        <th>Last Login</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($users as $user): ?>
                    <tr>
                        <td><strong><?php echo htmlspecialchars($user['username']); ?></strong></td>
                        <td><?php echo htmlspecialchars($user['email'] ?? ''); ?></td>
                        <td><?php echo htmlspecialchars($user['company_name'] ?? 'N/A'); ?></td>
                        <td><?php echo ucfirst(str_replace('_', ' ', $user['role'])); ?></td>
                        <td><?php echo htmlspecialchars($user['phone'] ?? ''); ?></td>
                        <td>
                            <?php 
                            $is_locked = $user['is_locked'] ?? false;
                            $is_active = $user['is_active'] ?? true;
                            $class = ($is_active && !$is_locked) ? 'badge-active' : 'badge-inactive';
                            $status = ($is_locked) ? '🔒 Locked' : (($is_active) ? '✅ Active' : '⏸️ Inactive');
                            echo '<span class="system-status ' . $class . '">' . $status . '</span>';
                            ?>
                        </td>
                        <td><?php echo ($user['last_login_at'] ?? null) ? date('M j, Y H:i', strtotime($user['last_login_at'])) : 'Never'; ?></td>
                        <td>
                            <form method="post" style="display: inline;">
                                <input type="hidden" name="action" value="<?php echo ($is_active && !$is_locked) ? 'deactivate_user' : 'activate_user'; ?>">
                                <input type="hidden" name="user_id" value="<?php echo $user['user_id']; ?>">
                                <button type="submit" class="btn-saas <?php echo ($is_active && !$is_locked) ? 'btn-deactivate' : 'btn-activate'; ?>" style="font-size: 11px; padding: 6px 10px;">
                                    <?php echo ($is_active && !$is_locked) ? 'Deactivate' : 'Activate'; ?>
                                </button>
                            </form>
                            <form method="post" style="display: inline;" onsubmit="return confirm('Are you sure you want to permanently delete this user? This action cannot be undone.')">
                                <input type="hidden" name="action" value="delete_user">
                                <input type="hidden" name="user_id" value="<?php echo $user['user_id']; ?>">
                                <button type="submit" class="btn-saas btn-deactivate" style="font-size: 11px; padding: 6px 10px; background: linear-gradient(135deg, #dc3545, #c82333);">
                                    🗑️ Delete
                                </button>
                            </form>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Subscriptions Tab -->
    <div id="subscriptions" class="tab-content">
        <div class="control-grid">
            <div class="control-card">
                <h4><i class="fas fa-credit-card card-icon"></i>Record Payment</h4>
                <form method="post">
                    <input type="hidden" name="action" value="record_payment">
                    <div class="form-grid">
                        <div class="form-group">
                            <label>Company:</label>
                            <select name="company_id" required>
                                <option value="">Select Company</option>
                                <?php foreach ($companies as $company): ?>
                                <option value="<?php echo $company['company_id']; ?>"><?php echo htmlspecialchars($company['company_name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Amount ($):</label>
                            <input type="number" name="amount" step="0.01" min="0" required>
                        </div>
                        <div class="form-group">
                            <label>Period (Months):</label>
                            <input type="number" name="period_months" value="1" min="1" max="24" required>
                        </div>
                        <div class="form-group">
                            <label>Payment Method:</label>
                            <select name="payment_method">
                                <option value="stripe">Stripe</option>
                                <option value="paypal">PayPal</option>
                                <option value="manual">Manual</option>
                                <option value="bank">Bank Transfer</option>
                            </select>
                        </div>
                    </div>
                    <button type="submit" class="btn-saas btn-payment">Record Payment</button>
                </form>
            </div>

            <div class="control-card">
                <h4><i class="fas fa-calendar-alt card-icon"></i>Update Subscription</h4>
                <form method="post">
                    <input type="hidden" name="action" value="update_subscription">
                    <div class="form-grid">
                        <div class="form-group">
                            <label>Company:</label>
                            <select name="company_id" required>
                                <option value="">Select Company</option>
                                <?php foreach ($companies as $company): ?>
                                <option value="<?php echo $company['company_id']; ?>"><?php echo htmlspecialchars($company['company_name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Status:</label>
                            <select name="subscription_status">
                                <option value="trial">Trial</option>
                                <option value="active">Active</option>
                                <option value="expired">Expired</option>
                                <option value="suspended">Suspended</option>
                                <option value="grace_period">Grace Period</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Max Users:</label>
                            <input type="number" name="max_users" value="5" min="1" max="1000">
                        </div>
                        <div class="form-group">
                            <label>Feature Tier:</label>
                            <select name="feature_tier">
                                <option value="trial">Trial</option>
                                <option value="basic">Basic</option>
                                <option value="professional">Professional</option>
                                <option value="enterprise">Enterprise</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Extend Days:</label>
                            <input type="number" name="expires_days" value="30" min="1" max="3650">
                        </div>
                    </div>
                    <button type="submit" class="btn-saas btn-update">Update Subscription</button>
                </form>
            </div>
        </div>

        <div class="control-card">
            <h4><i class="fas fa-history card-icon"></i>Recent Payments</h4>
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Company</th>
                        <th>Amount</th>
                        <th>Date</th>
                        <th>Method</th>
                        <th>Period</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($recent_payments as $payment): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($payment['company_name']); ?></td>
                        <td>$<?php echo number_format($payment['amount'], 2); ?></td>
                        <td><?php echo date('M j, Y', strtotime($payment['payment_date'])); ?></td>
                        <td><?php echo ucfirst($payment['payment_method'] ?? 'manual'); ?></td>
                        <td><?php echo date('M j', strtotime($payment['subscription_period_start'])) . ' - ' . date('M j, Y', strtotime($payment['subscription_period_end'])); ?></td>
                        <td><span class="system-status badge-active"><?php echo ucfirst($payment['payment_status']); ?></span></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- System Control Tab -->
    <div id="system-control" class="tab-content">
        <div class="control-card">
            <h4><i class="fas fa-cogs card-icon"></i>System Control Actions</h4>
            <div class="control-grid">
                <div class="control-card">
                    <h5>🔄 System Reset</h5>
                    <p>Reset company data to clean state</p>
                    <form method="post">
                        <input type="hidden" name="action" value="reset_company_data">
                        <select name="reset_company_id" required style="margin-bottom: 10px;">
                            <option value="">Select Company</option>
                            <?php foreach ($companies as $company): ?>
                            <option value="<?php echo $company['company_id']; ?>"><?php echo htmlspecialchars($company['company_name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                        <button type="submit" class="btn-saas btn-deactivate" onclick="return confirm('This will delete all work orders, equipment, and inventory data. Continue?')">Reset Data</button>
                    </form>
                </div>

                <div class="control-card">
                    <h5>📊 Health Check</h5>
                    <p>Run system diagnostics</p>
                    <a href="index.php?nav=health_check" class="btn-saas btn-update" style="display: inline-block; text-decoration: none; cursor: pointer;">Run Check</a>
                </div>

                <div class="control-card">
                    <h5>🔧 Maintenance Mode</h5>
                    <p>Enable/disable maintenance mode</p>
                    <form method="post" action="maintenance_mode.php" style="display: inline;">
                        <input type="hidden" name="action" value="toggle">
                        <input type="hidden" name="message" value="<?php $is_maint = file_exists(__DIR__ . '/maintenance.flag'); echo $is_maint ? 'System online' : 'System is under maintenance. Please check back soon.'; ?>">
                        <button type="submit" class="btn-saas btn-warning"><?php echo $is_maint ? 'Disable Maintenance Mode' : 'Enable Maintenance Mode'; ?></button>
                    </form>
                    <div style="font-size: 12px; margin-top: 10px; color: #666;">
                        <?php 
                        echo $is_maint ? '🔴 Maintenance <strong>ACTIVE</strong> — click above to return system online' : '🟢 System <strong>ONLINE</strong> — click above to enable maintenance';
                        ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Updates Tab -->
    <div id="updates" class="tab-content">
        <div class="control-card">
            <h4><i class="fas fa-rocket card-icon"></i>Create System Update</h4>
            <form method="post">
                <input type="hidden" name="action" value="create_system_update">
                <div class="form-grid">
                    <div class="form-group">
                        <label>Version:</label>
                        <input type="text" name="version" placeholder="1.0.1" required>
                    </div>
                    <div class="form-group">
                        <label>Type:</label>
                        <select name="update_type">
                            <option value="bug_fix">Bug Fix</option>
                            <option value="security">Security</option>
                            <option value="feature">Feature</option>
                            <option value="major">Major Release</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Description:</label>
                        <input type="text" name="description" placeholder="Brief description" required>
                    </div>
                    <div class="form-group">
                        <label>Mandatory:</label>
                        <input type="checkbox" name="is_mandatory" value="1">
                    </div>
                </div>
                <div class="form-group">
                    <label>Changelog:</label>
                    <textarea name="changelog" rows="4" placeholder="Detailed changelog..."></textarea>
                </div>
                <button type="submit" class="btn-saas btn-update">Create Update</button>
            </form>
        </div>

        <div class="control-card">
            <h4><i class="fas fa-list card-icon"></i>System Updates</h4>
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Version</th>
                        <th>Type</th>
                        <th>Description</th>
                        <th>Mandatory</th>
                        <th>Release Date</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($system_updates as $update): ?>
                    <tr>
                        <td><strong><?php echo htmlspecialchars($update['version']); ?></strong></td>
                        <td><?php echo ucfirst(str_replace('_', ' ', $update['update_type'])); ?></td>
                        <td><?php echo htmlspecialchars($update['description']); ?></td>
                        <td><?php echo $update['is_mandatory'] ? 'Yes' : 'No'; ?></td>
                        <td><?php echo date('M j, Y', strtotime($update['release_date'])); ?></td>
                        <td><span class="system-status badge-active"><?php echo ucfirst($update['deployment_status']); ?></span></td>
                        <td>
                            <form method="post" style="display: inline;">
                                <input type="hidden" name="action" value="deploy_update">
                                <input type="hidden" name="update_id" value="<?php echo $update['update_id']; ?>">
                                <select name="deploy_company_id" style="margin-right: 5px;">
                                    <option value="">Select Company</option>
                                    <?php foreach ($companies as $company): ?>
                                    <option value="<?php echo $company['company_id']; ?>"><?php echo htmlspecialchars($company['company_name']); ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <button type="submit" class="btn-saas btn-update" style="font-size: 11px; padding: 6px 10px;">Deploy</button>
                            </form>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Analytics Tab -->
    <div id="analytics" class="tab-content">
        <div class="control-card">
            <h4><i class="fas fa-chart-bar card-icon"></i>System Analytics</h4>
            <div class="metric-grid">
                <div class="metric-card">
                    <span class="metric-value"><?php echo $analytics_summary['total_metrics'] ?? 0; ?></span>
                    <span class="metric-label">Total Metrics (24h)</span>
                </div>
                <div class="metric-card">
                    <span class="metric-value"><?php echo number_format($analytics_summary['avg_system_load'] ?? 0, 1); ?>%</span>
                    <span class="metric-label">Avg System Load</span>
                </div>
                <div class="metric-card">
                    <span class="metric-value"><?php echo count(array_filter($companies, fn($c) => strtotime($c['subscription_expires_at'] ?? 'now') < time())); ?></span>
                    <span class="metric-label">Expired Subscriptions</span>
                </div>
                <div class="metric-card">
                    <span class="metric-value"><?php echo count(array_filter($companies, fn($c) => ($c['subscription_status'] ?? '') === 'trial')); ?></span>
                    <span class="metric-label">Active Trials</span>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function showTab(tabName) {
    // Hide all tab contents
    const contents = document.querySelectorAll('.tab-content');
    contents.forEach(content => content.classList.remove('active'));

    // Remove active class from all buttons
    const buttons = document.querySelectorAll('.tab-button');
    buttons.forEach(button => button.classList.remove('active'));

    // Show selected tab
    document.getElementById(tabName).classList.add('active');
    event.target.classList.add('active');
}

/**
 * Activate system via API endpoint
 */
function activateSystem(event, companyId) {
    event.preventDefault();
    
    if (!confirm('Activate this system? It will enable all services.')) {
        return false;
    }
    
    const formData = new FormData();
    formData.append('action', 'activate');
    formData.append('company_id', companyId);
    
    fetch('license_api.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert('✅ System activated successfully!');
            location.reload();
        } else {
            alert('❌ Error: ' + (data.error || 'Unknown error occurred'));
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('❌ Failed to activate system: ' + error);
    });
    
    return false;
}
</script></content>
<parameter name="filePath">c:\free-cmms 0.04\admin_roles.php