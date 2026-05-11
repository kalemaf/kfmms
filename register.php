<?php
/**
 * Registration/Setup Page for New Companies
 * 
 * This page allows new companies to register
 * Usage: http://yoursite.com/register.php
 */

require_once 'config.inc.php';

// Check if registration is allowed
$allow_registration = getenv('ALLOW_PUBLIC_REGISTRATION') === 'true';

if (!$allow_registration && !isset($_GET['setup_token'])) {
    die('Registration is currently closed');
}

$message = '';
$error = '';
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validate CSRF token
    $csrf_token = $_SESSION['csrf_token'] ?? null;
    $post_token = $_POST['csrf_token'] ?? null;
    
    if ($csrf_token !== $post_token) {
        $error = 'Invalid security token';
    } else {
        // Register company
        $company_service = new CompanyService($connection, $db_type);
        
        $company_data = [
            'name' => $_POST['company_name'] ?? '',
            'email' => $_POST['company_email'] ?? '',
            'phone' => $_POST['phone'] ?? '',
            'address' => $_POST['address'] ?? '',
            'city' => $_POST['city'] ?? '',
            'state' => $_POST['state'] ?? '',
            'country' => $_POST['country'] ?? '',
            'postal_code' => $_POST['postal_code'] ?? ''
        ];
        
        $company_result = $company_service->register($company_data);
        
        if ($company_result['success']) {
            $company_id = $company_result['company_id'];
            
            // Create admin user
            $auth_manager = new AuthenticationManager($connection, $db_type);
            
            $admin_result = $auth_manager->registerUser([
                'email' => $_POST['admin_email'] ?? '',
                'password' => $_POST['password'] ?? '',
                'full_name' => $_POST['admin_name'] ?? '',
                'role' => 'admin',
                'tenant_id' => $company_id
            ]);
            
            if ($admin_result['success']) {
                $success = true;
                $message = 'Company registered successfully! Redirecting to login...';
                
                // Redirect to login after 3 seconds
                header('Refresh: 3; url=login.php');
            } else {
                $error = $admin_result['message'];
            }
        } else {
            $error = $company_result['message'];
        }
    }
}

// Generate CSRF token
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register Company - KFMMS</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        
        .container {
            background: white;
            border-radius: 10px;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.2);
            max-width: 500px;
            width: 100%;
            padding: 40px;
        }
        
        h1 {
            color: #333;
            margin-bottom: 10px;
            font-size: 28px;
        }
        
        .subtitle {
            color: #666;
            margin-bottom: 30px;
            font-size: 14px;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        label {
            display: block;
            color: #333;
            font-weight: 500;
            margin-bottom: 8px;
            font-size: 14px;
        }
        
        input[type="text"],
        input[type="email"],
        input[type="password"],
        input[type="tel"] {
            width: 100%;
            padding: 12px;
            border: 1px solid #ddd;
            border-radius: 5px;
            font-size: 14px;
            transition: border-color 0.3s;
        }
        
        input[type="text"]:focus,
        input[type="email"]:focus,
        input[type="password"]:focus,
        input[type="tel"]:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }
        
        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
        }
        
        .alert {
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 20px;
            font-size: 14px;
        }
        
        .alert-error {
            background-color: #fee;
            border: 1px solid #fcc;
            color: #c33;
        }
        
        .alert-success {
            background-color: #efe;
            border: 1px solid #cfc;
            color: #3c3;
        }
        
        .section-title {
            color: #667eea;
            font-size: 12px;
            font-weight: 600;
            text-transform: uppercase;
            margin-top: 30px;
            margin-bottom: 15px;
            padding-top: 20px;
            border-top: 1px solid #eee;
        }
        
        button {
            width: 100%;
            padding: 12px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            border-radius: 5px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: transform 0.2s;
            margin-top: 20px;
        }
        
        button:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 20px rgba(102, 126, 234, 0.4);
        }
        
        .login-link {
            text-align: center;
            margin-top: 20px;
            font-size: 14px;
            color: #666;
        }
        
        .login-link a {
            color: #667eea;
            text-decoration: none;
            font-weight: 600;
        }
        
        .required {
            color: #e74c3c;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>🏢 Register Your Company</h1>
        <p class="subtitle">Join KFMMS and start managing your maintenance operations</p>
        
        <?php if ($error): ?>
            <div class="alert alert-error">
                ⚠️ <?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>
        
        <?php if ($success): ?>
            <div class="alert alert-success">
                ✅ <?= htmlspecialchars($message) ?>
            </div>
        <?php else: ?>
            <form method="POST">
                <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                
                <!-- Company Information -->
                <div class="section-title">Company Information</div>
                
                <div class="form-group">
                    <label for="company_name">Company Name <span class="required">*</span></label>
                    <input type="text" id="company_name" name="company_name" required placeholder="e.g., ACME Corporation">
                </div>
                
                <div class="form-group">
                    <label for="company_email">Company Email <span class="required">*</span></label>
                    <input type="email" id="company_email" name="company_email" required placeholder="contact@company.com">
                </div>
                
                <div class="form-group">
                    <label for="phone">Phone Number</label>
                    <input type="tel" id="phone" name="phone" placeholder="+1-555-0000">
                </div>
                
                <div class="form-group">
                    <label for="address">Address</label>
                    <input type="text" id="address" name="address" placeholder="123 Main Street">
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="city">City</label>
                        <input type="text" id="city" name="city" placeholder="New York">
                    </div>
                    <div class="form-group">
                        <label for="state">State</label>
                        <input type="text" id="state" name="state" placeholder="NY">
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="country">Country</label>
                        <input type="text" id="country" name="country" placeholder="USA">
                    </div>
                    <div class="form-group">
                        <label for="postal_code">Postal Code</label>
                        <input type="text" id="postal_code" name="postal_code" placeholder="10001">
                    </div>
                </div>
                
                <!-- Admin User Setup -->
                <div class="section-title">Administrator Account</div>
                
                <div class="form-group">
                    <label for="admin_name">Full Name <span class="required">*</span></label>
                    <input type="text" id="admin_name" name="admin_name" required placeholder="John Admin">
                </div>
                
                <div class="form-group">
                    <label for="admin_email">Email Address <span class="required">*</span></label>
                    <input type="email" id="admin_email" name="admin_email" required placeholder="admin@company.com">
                </div>
                
                <div class="form-group">
                    <label for="password">Password <span class="required">*</span></label>
                    <input type="password" id="password" name="password" required placeholder="Min 8 characters">
                    <small style="color: #999; margin-top: 5px; display: block;">
                        Use at least 8 characters with uppercase, lowercase, and numbers
                    </small>
                </div>
                
                <button type="submit">🚀 Create Company Account</button>
                
                <div class="login-link">
                    Already have an account? <a href="login.php">Login here</a>
                </div>
            </form>
        <?php endif; ?>
    </div>
</body>
</html>
