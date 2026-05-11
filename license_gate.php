<?php
/**
 * License Gate Page
 * Handles license activation and subscription plan selection
 */

// Allow framing for development (localhost/127.0.0.1)
$is_localhost = isset($_SERVER['HTTP_HOST']) && preg_match('/^(localhost|127\.0\.0\.1)(:\d+)?$/', $_SERVER['HTTP_HOST']);
if ($is_localhost) {
    // In development, allow framing from localhost
    header('Access-Control-Allow-Origin: http://127.0.0.1:8000', false);
    header('Access-Control-Allow-Credentials: true', false);
} else {
    // In production, use DENY to prevent clickjacking
    header('X-Frame-Options: DENY', false);
}

// Suppress display of errors to prevent breaking page layout
error_reporting(E_ALL);
ini_set('display_errors', '0');  // Don't display errors on page
ini_set('log_errors', '1');      // Log errors to error_log

require_once 'config.inc.php';
session_save_path($session_save_path);
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once 'common.inc.php';

$is_guest_activation = empty($_SESSION['user']);

// If logged in, allow developers/admins to bypass the license gate and redirect directly
$license_check = ['valid' => false, 'message' => 'License validation not performed yet'];
if (!$is_guest_activation) {
    $license_check = check_user_license();
    if ($license_check['valid']) {
        header('Location: index.php');
        exit;
    }
    $user_role = strtolower($_SESSION['role'] ?? '');
    $current_user_name = strtolower(trim($_SESSION['user'] ?? ''));
    if ($user_role === 'developer' || $user_role === 'admin' || $current_user_name === 'developer') {
        header('Location: index.php');
        exit;
    }
}

$message = '';
$error = '';
if (!empty($_GET['message'])) {
    $message = trim($_GET['message']);
}
if (!empty($_GET['error'])) {
    $error = trim($_GET['error']);
}
$is_new_subscriber = isset($_GET['after_payment']) && $_GET['after_payment'] === '1';
$activation_instructions = 'If you have completed payment, enter the 16-character license key from your confirmation email below to activate your subscription.';
$payment_config = get_payment_provider_config();
$plans = get_subscription_plans();
$renewal_message = null;
$license = $license_check['license'] ?? null;
if (!$license_check['valid'] && !empty($license)) {
    $renewal_message = get_license_renewal_message($license);
}
$onboarding_steps = get_customer_onboarding_steps();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['purchase_plan'])) {
        $plan_key = trim($_POST['plan_key'] ?? '');
        if (empty($plan_key) || !isset($plans[$plan_key])) {
            $error = 'Please select a valid subscription plan.';
        } else {
            if ($plans[$plan_key]['price'] === 0) {
                if ($is_guest_activation) {
                    $error = 'Please log in to activate the free trial plan.';
                } else {
                    $company_id = get_user_company_id($_SESSION['user_id']);
                    if (empty($company_id)) {
                        $error = 'Unable to determine your company profile. Please contact support.';
                    } else {
                        $result = create_company_license_from_payment(
                            $company_id,
                            $plan_key,
                            'free-trial',
                            null,
                            $_SESSION['email'] ?? ''
                        );
                        if ($result['success']) {
                            $message = 'Your trial has been activated. A license key has been sent to your registered email address.';
                        } else {
                            $error = $result['message'];
                        }
                    }
                }
            } elseif ($payment_config['provider'] === 'stripe' || $payment_config['provider'] === 'paypal') {
                if ($is_guest_activation) {
                    $error = 'Please log in to purchase a plan and receive your license key by email.';
                } else {
                    $company_id = get_user_company_id($_SESSION['user_id']);
                    if (empty($company_id)) {
                        $error = 'Unable to determine your company profile. Please contact support.';
                    } else {
                        $metadata = [
                            'plan_key' => $plan_key,
                            'user_id' => $_SESSION['user_id'],
                            'company_id' => $company_id,
                            'user_email' => $_SESSION['email'] ?? '',
                            'customer_email' => $_SESSION['email'] ?? '',
                        ];

                        if ($payment_config['provider'] === 'stripe') {
                            $checkout = create_checkout_session($plan_key, $metadata);
                        } else {
                            $checkout = create_paypal_order($plan_key, $metadata);
                        }

                        if ($checkout['success'] && !empty($checkout['redirect_url'])) {
                            header('Location: ' . $checkout['redirect_url']);
                            exit;
                        }
                        $error = $checkout['message'];
                    }
                }
            } else {
                $message = 'Please contact sales@efficraft.com to complete payment for the ' . htmlspecialchars($plans[$plan_key]['name']) . ' plan.';
            }
        }
    } elseif (isset($_POST['activate_license'])) {
        $license_key = trim($_POST['license_key'] ?? '');

        if (empty($license_key)) {
            $error = 'Please enter a license key.';
        } else {
            if ($is_guest_activation) {
                $validation = validate_license_key($license_key);
                if ($validation['valid']) {
                    $license = $validation['license'];
                    $message = 'License key is valid for ' . htmlspecialchars($license['company_name']) . '.\nPlease log in with your account to complete activation.';
                } else {
                    $error = $validation['message'];
                }
            } else {
                $result = activate_license($license_key);
                if ($result['success']) {
                    $message = $result['message'];
                    // Redirect after successful activation
                    header('Location: index.php?activated=1');
                    exit;
                } else {
                    $error = $result['message'];
                }
            }
        }
    }
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>License Activation - KFMMS</title>
    <style>
        :root {
            color-scheme: dark;
            color: #f4f4f4;
            background: #0b1320;
        }

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            min-height: 100vh;
            font-family: Inter, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
            background: radial-gradient(circle at top, rgba(10, 122, 255, 0.2), transparent 28%),
                        linear-gradient(180deg, #0f172a 0%, #090b13 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }

        .gate-container {
            width: min(800px, calc(100vw - 40px));
            background: rgba(15, 23, 42, 0.94);
            border-radius: 24px;
            padding: 40px;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.5);
            backdrop-filter: blur(20px);
            border: 1px solid rgba(255, 255, 255, 0.1);
        }

        .logo-section {
            text-align: center;
            margin-bottom: 40px;
        }

        .logo {
            display: inline-flex;
            justify-content: center;
            margin-bottom: 16px;
        }

        .brand-logo {
            max-width: 160px;
            width: 100%;
            height: auto;
            border-radius: 20px;
            border: 1px solid rgba(255, 209, 102, 0.22);
            background: rgba(255, 255, 255, 0.08);
            box-shadow: 0 16px 50px rgba(0, 0, 0, 0.24);
        }

        .subtitle {
            color: #f8fafc;
            font-size: 1.2rem;
            margin-bottom: 16px;
            font-weight: 600;
        }

        .note {
            color: #d1d5db;
            max-width: 760px;
            margin: 0 auto 32px auto;
            font-size: 0.96rem;
            line-height: 1.75;
        }

        .cta-bar {
            display: flex;
            justify-content: center;
            margin-top: 20px;
            gap: 12px;
            flex-wrap: wrap;
        }

        .cta-bar .btn-cta {
            padding: 12px 28px;
            border-radius: 999px;
            background: linear-gradient(135deg, #facc15 0%, #0ea5e9 100%);
            color: #08131f;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.03em;
            border: none;
            transition: transform 0.25s ease, box-shadow 0.25s ease;
        }

        .cta-bar .btn-cta:hover {
            transform: translateY(-2px);
            box-shadow: 0 16px 30px rgba(14, 165, 233, 0.20);
        }

        .status-message {
            padding: 16px;
            border-radius: 12px;
            margin-bottom: 24px;
            font-weight: 500;
        }

        .status-success {
            background: rgba(34, 197, 94, 0.1);
            border: 1px solid rgba(34, 197, 94, 0.3);
            color: #22c55e;
        }

        .status-error {
            background: rgba(239, 68, 68, 0.1);
            border: 1px solid rgba(239, 68, 68, 0.3);
            color: #ef4444;
        }

        .plans-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 40px;
        }

        .plan-card {
            background: linear-gradient(180deg, rgba(15, 23, 42, 0.86), rgba(15, 23, 42, 0.72));
            border: 1px solid rgba(14, 165, 233, 0.18);
            border-radius: 20px;
            padding: 26px;
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }

        .plan-card::before {
            content: '';
            position: absolute;
            top: -18px;
            right: -18px;
            width: 120px;
            height: 120px;
            background: rgba(14, 165, 233, 0.12);
            border-radius: 50%;
        }

        .plan-card:hover {
            border-color: rgba(14, 165, 233, 0.3);
            transform: translateY(-2px);
        }

        .plan-name {
            font-size: 1.6rem;
            font-weight: 700;
            margin-bottom: 8px;
            color: #facc15;
            text-transform: uppercase;
            letter-spacing: 0.08em;
        }

        .plan-price {
            font-size: 2rem;
            font-weight: 700;
            margin-bottom: 16px;
        }

        .plan-price .currency {
            font-size: 1rem;
            font-weight: 500;
        }

        .plan-price .period {
            font-size: 0.9rem;
            color: #94a3b8;
        }

        .plan-features {
            list-style: none;
            padding: 0;
            margin: 0;
        }

        .plan-features li {
            padding: 4px 0;
            color: #cbd5e1;
            font-size: 0.9rem;
        }

        .plan-features li:before {
            content: "✓";
            color: #22c55e;
            font-weight: bold;
            margin-right: 8px;
        }

        .plan-label {
            display: inline-block;
            margin-top: 14px;
            padding: 6px 12px;
            border-radius: 999px;
            background: rgba(56, 189, 248, 0.12);
            color: #bfdbfe;
            font-size: 0.85rem;
            margin-bottom: 18px;
        }

        .plan-actions {
            display: flex;
            justify-content: center;
            margin-top: 20px;
        }

        .renewal-banner {
            background: rgba(249, 115, 22, 0.1);
            border: 1px solid rgba(249, 115, 22, 0.25);
            color: #f97316;
            border-radius: 16px;
            padding: 16px;
            margin-bottom: 24px;
            text-align: center;
        }

        .onboarding-section {
            background: rgba(15, 23, 42, 0.88);
            border-radius: 20px;
            padding: 28px;
            margin-bottom: 40px;
            border: 1px solid rgba(148, 163, 184, 0.18);
        }

        .onboarding-steps {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 16px;
            margin-top: 24px;
        }

        .step-card {
            background: rgba(30, 41, 59, 0.7);
            border: 1px solid rgba(148, 163, 184, 0.12);
            border-radius: 18px;
            padding: 18px;
            min-height: 160px;
        }

        .step-number {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 30px;
            height: 30px;
            border-radius: 50%;
            background: rgba(14, 165, 233, 0.18);
            color: #38bdf8;
            margin-bottom: 14px;
            font-weight: 700;
        }

        .step-title {
            margin: 0 0 8px;
            font-size: 1rem;
            font-weight: 700;
            color: #f8fafc;
        }

        .step-description {
            margin: 0;
            color: #cbd5e1;
            line-height: 1.6;
        }

        .plan-card form {
            margin-top: 18px;
        }

        .plan-card button {
            width: 100%;
        }

        .plan-card .manual-cta {
            color: #e2e8f0;
            border: 1px solid rgba(148, 163, 184, 0.2);
            background: rgba(100, 116, 139, 0.14);
        }

        .activation-section {
            background: rgba(30, 41, 59, 0.3);
            border-radius: 16px;
            padding: 32px;
            border: 1px solid rgba(255, 255, 255, 0.1);
        }

        .section-title {
            font-size: 1.5rem;
            font-weight: 600;
            margin-bottom: 24px;
            text-align: center;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-label {
            display: block;
            margin-bottom: 8px;
            font-weight: 500;
            color: #e2e8f0;
        }

        .form-input {
            width: 100%;
            padding: 12px 16px;
            background: rgba(15, 23, 42, 0.8);
            border: 1px solid rgba(255, 255, 255, 0.2);
            border-radius: 8px;
            color: #f4f4f4;
            font-size: 1rem;
            transition: border-color 0.3s ease;
        }

        .form-input:focus {
            outline: none;
            border-color: #0ea5e9;
            box-shadow: 0 0 0 3px rgba(14, 165, 233, 0.1);
        }

        .form-input::placeholder {
            color: #94a3b8;
        }

        .btn {
            padding: 12px 24px;
            border: none;
            border-radius: 8px;
            font-size: 1rem;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-block;
            text-align: center;
        }

        .btn-primary {
            background: linear-gradient(135deg, #0ea5e9, #3b82f6);
            color: white;
        }

        .btn-primary:hover {
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(14, 165, 233, 0.3);
        }

        .btn-secondary {
            background: rgba(71, 85, 105, 0.8);
            color: #e2e8f0;
        }

        .btn-secondary:hover {
            background: rgba(71, 85, 105, 1);
        }

        .form-actions {
            display: flex;
            gap: 12px;
            justify-content: center;
            margin-top: 24px;
        }

        .contact-info {
            text-align: center;
            margin-top: 32px;
            padding-top: 24px;
            border-top: 1px solid rgba(255, 255, 255, 0.1);
            color: #94a3b8;
        }

        .contact-info a {
            color: #0ea5e9;
            text-decoration: none;
        }

        .contact-info a:hover {
            text-decoration: underline;
        }

        @media (max-width: 640px) {
            .gate-container {
                padding: 24px;
            }

            .plans-grid {
                grid-template-columns: 1fr;
            }

            .form-actions {
                flex-direction: column;
            }

            .btn {
                width: 100%;
            }
        }
    </style>
</head>
<body>
    <div class="gate-container">
        <div class="logo-section">
            <div class="logo">
                <img src="images/kimage.png" alt="KFMMS logo" class="brand-logo">
            </div>
            <div class="subtitle">Efficraft Technologies CMMS</div>
            <p class="note">
                <?php echo htmlspecialchars($is_new_subscriber ? 'Thanks for subscribing! Enter the license key you received after payment to activate your account.' : 'Access requires a valid license. Choose a plan or enter your license key below.'); ?>
            </p>
            <?php if ($is_new_subscriber): ?>
                <p class="note">If you do not have your license key yet, please check your confirmation email or contact support.</p>
            <?php endif; ?>
            <div class="cta-bar">
                <a href="#activate-license" class="btn-cta">Activate Subscription</a>
                <?php if (!$is_guest_activation): ?>
                    <a href="license_invoice.php" class="btn-cta">View Billing History</a>
                <?php endif; ?>
            </div>
        </div>

        <?php if ($message): ?>
            <div class="status-message status-success">
                <?php echo nl2br(htmlspecialchars($message)); ?>
            </div>
        <?php endif; ?>

        <?php if (!empty($renewal_message)): ?>
            <div class="renewal-banner">
                <?php echo htmlspecialchars($renewal_message); ?>
            </div>
        <?php endif; ?>

        <?php if ($error): ?>
            <div class="status-message status-error">
                <?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>

        <?php if (!$license_check['valid'] && !$is_guest_activation && $license_check['message'] !== 'User not logged in'): ?>
            <div class="status-message status-error">
                <?php echo htmlspecialchars($license_check['message']); ?>
            </div>
        <?php endif; ?>

        <div class="plans-grid">
            <?php foreach ($plans as $plan_key => $plan): ?>
                <div class="plan-card">
                    <div class="plan-name"><?php echo htmlspecialchars($plan['name']); ?></div>
                    <div class="plan-label"><?php echo htmlspecialchars($plan['label']); ?></div>
                    <div class="plan-price">
                        <?php if ($plan['price'] == 0): ?>
                            Free
                        <?php else: ?>
                            <span class="currency">$</span><?php echo htmlspecialchars($plan['price']); ?>
                            <span class="period">/<?php echo htmlspecialchars($plan['duration']); ?></span>
                        <?php endif; ?>
                    </div>
                    <ul class="plan-features">
                        <?php foreach ($plan['features'] as $feature): ?>
                            <li><?php echo htmlspecialchars($feature); ?></li>
                        <?php endforeach; ?>
                    </ul>
                    <div class="plan-actions">
                        <?php if ($payment_config['provider'] === 'stripe' || $payment_config['provider'] === 'paypal'): ?>
                            <form method="post" action="">
                                <input type="hidden" name="plan_key" value="<?php echo htmlspecialchars($plan_key); ?>">
                                <button type="submit" name="purchase_plan" class="btn btn-primary">
                                    <?php if ($plan['price'] == 0): ?>
                                        Activate Trial
                                    <?php elseif ($payment_config['provider'] === 'paypal'): ?>
                                        Pay with PayPal
                                    <?php else: ?>
                                        Purchase Plan
                                    <?php endif; ?>
                                </button>
                            </form>
                        <?php else: ?>
                            <a href="mailto:sales@efficraft.com?subject=<?php echo urlencode('Request for ' . $plan['name'] . ' subscription'); ?>" class="btn manual-cta">
                                Contact Sales
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <div class="onboarding-section">
            <div class="section-title">Customer Onboarding</div>
            <p class="note">Follow these steps to complete subscription setup and activate your license.</p>
            <div class="onboarding-steps">
                <?php foreach ($onboarding_steps as $step_index => $step): ?>
                    <div class="step-card">
                        <div class="step-number"><?php echo $step_index + 1; ?></div>
                        <h3 class="step-title"><?php echo htmlspecialchars($step['title']); ?></h3>
                        <p class="step-description"><?php echo htmlspecialchars($step['description']); ?></p>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>

        <div class="activation-section" id="activate-license">
            <div class="section-title">Activate License</div>
            <p class="note"><?php echo htmlspecialchars($activation_instructions); ?></p>
            <?php if ($is_guest_activation): ?>
                <p class="note">You are not logged in yet. After validating your license key, please <a href="auth.php" style="color:#0ea5e9; text-decoration:underline;">log in to complete activation</a>.</p>
            <?php endif; ?>

            <form method="post" action="">
                <div class="form-group">
                    <label for="license_key" class="form-label">License Key</label>
                    <input type="text"
                           id="license_key"
                           name="license_key"
                           class="form-input"
                           placeholder="Enter your 16-character license key"
                           maxlength="16"
                           pattern="[A-Z0-9]{16}"
                           style="text-transform: uppercase; font-family: monospace;"
                           required>
                </div>

                <div class="form-actions">
                    <button type="submit" name="activate_license" class="btn btn-primary">
                        Activate License
                    </button>
                    <a href="auth.php?logout=1" class="btn btn-secondary">
                        Logout
                    </a>
                </div>
            </form>
        </div>

        <div class="contact-info">
            <p>Need a license? Contact us at <a href="mailto:sales@kfmms.com">sales@kfmms.com</a></p>
            <p>Support: <a href="mailto:support@kfmms.com">support@kfmms.com</a></p>
        </div>
    </div>

    <script>
        // Auto-format license key input
        document.getElementById('license_key').addEventListener('input', function(e) {
            let value = e.target.value.toUpperCase().replace(/[^A-Z0-9]/g, '');
            e.target.value = value;
        });

        // Frame busting for development
        if (window.top !== window.self) {
            try {
                if (window.location.hostname === '127.0.0.1' || window.location.hostname === 'localhost') {
                    console.log('License gate loaded in frame during development');
                } else {
                    window.top.location = window.self.location;
                }
            } catch (err) {
                console.log('Frame busting skipped due to cross-origin parent frame:', err.message);
            }
        }
    </script>
</body>
</html>