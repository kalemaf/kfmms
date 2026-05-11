<?php
/**
 * Pre-login welcome splash page for KFMMS
 */

// Prevent framing to block clickjacking and cross-origin load attempts
header('X-Frame-Options: DENY', false);

// Suppress display of errors to prevent breaking page layout
// All errors are still logged for debugging
error_reporting(E_ALL);
ini_set('display_errors', '0');  // Don't display errors on page
ini_set('log_errors', '1');      // Log errors to error_log

require_once 'config.inc.php';
session_save_path($session_save_path);
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once 'common.inc.php';

// Debug headers for development
if (!empty($debug_mode)) {
    header('X-Debug-Session: ' . json_encode([
        'user' => $_SESSION['user'] ?? 'none',
        'user_id' => $_SESSION['user_id'] ?? 'none',
        'role' => $_SESSION['role'] ?? 'none'
    ]));
    header('X-Frame-Check: ' . (isset($_SERVER['HTTP_REFERER']) ? 'framed' : 'direct'));
}

// Check if being loaded in a frame with different origin (CORS issue)
if ($debug_mode && isset($_SERVER['HTTP_REFERER'])) {
    $referer_host = parse_url($_SERVER['HTTP_REFERER'], PHP_URL_HOST);
    $current_host = $_SERVER['HTTP_HOST'];
    if ($referer_host && $referer_host !== $current_host && strpos($referer_host, 'chrome-error') === false) {
        // Different origin - this might cause CORS issues
        header('X-CORS-Warning: Different origin detected - ' . $referer_host . ' vs ' . $current_host);
    }
}

if (!empty($_SESSION['user'])) {
    header('Location: index.php');
    exit;
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Welcome to KFMMS</title>
    <link href="https://fonts.googleapis.com/css2?family=Dancing+Script:wght@400;700&family=Playfair+Display:wght@400;700;900&family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            color-scheme: dark;
            color: #f4f4f4;
            background: #0b1320;
            --primary-gradient: linear-gradient(135deg, #f59e0b, #0ea5e9);
            --secondary-gradient: linear-gradient(135deg, #8b5cf6, #ec4899);
            --accent-gradient: linear-gradient(135deg, #10b981, #f59e0b);
        }

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            min-height: 100vh;
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
            background: radial-gradient(circle at top left, rgba(7, 99, 255, 0.18), transparent 24%),
                        radial-gradient(circle at bottom right, rgba(255, 208, 74, 0.16), transparent 20%),
                        linear-gradient(180deg, #061827 0%, #08131f 54%, #0b1320 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            position: relative;
        }

        body::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: 
                radial-gradient(circle at 20% 80%, rgba(120, 119, 198, 0.1) 0%, transparent 50%),
                radial-gradient(circle at 80% 20%, rgba(255, 119, 198, 0.1) 0%, transparent 50%),
                radial-gradient(circle at 40% 40%, rgba(120, 219, 255, 0.1) 0%, transparent 50%);
            animation: float 20s ease-in-out infinite;
        }

        .splash-card {
            width: min(680px, calc(100vw - 44px));
            padding: 48px 44px 42px;
            border-radius: 32px;
            background: rgba(4, 18, 44, 0.95);
            border: 1px solid rgba(250, 204, 63, 0.25);
            box-shadow: 
                0 40px 110px rgba(0, 0, 0, 0.35),
                0 0 60px rgba(250, 204, 63, 0.1),
                inset 0 1px 0 rgba(255, 255, 255, 0.1);
            text-align: center;
            position: relative;
            backdrop-filter: blur(10px);
            z-index: 1;
        }

        .brand-mark {
            display: inline-flex;
            align-items: center;
            gap: 16px;
            margin-bottom: 32px;
            position: relative;
        }

        .brand-mark img {
            width: 130px;
            max-width: 100%;
            height: auto;
            border-radius: 20px;
            border: 2px solid rgba(255,255,255,0.2);
            background: rgba(15, 23, 42, 0.8);
            padding: 10px;
            box-shadow: 0 8px 32px rgba(0,0,0,0.3);
            transition: transform 0.3s ease;
        }

        .brand-mark img:hover {
            transform: scale(1.05);
        }

        .brand-mark h1 {
            margin: 0;
            font-size: 2.2rem;
            letter-spacing: 0.15em;
            text-transform: uppercase;
            background: var(--primary-gradient);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            text-shadow: 0 2px 20px rgba(245, 158, 11, 0.3);
            font-weight: 700;
            position: relative;
        }

        .brand-mark h1::after {
            content: '';
            position: absolute;
            bottom: -8px;
            left: 50%;
            transform: translateX(-50%);
            width: 60px;
            height: 3px;
            background: var(--primary-gradient);
            border-radius: 2px;
            box-shadow: 0 0 20px rgba(245, 158, 11, 0.5);
        }

        .hero-icon {
            position: relative;
            width: 180px;
            height: 180px;
            margin: 0 auto 28px;
        }

        .gear {
            width: 125px;
            height: 125px;
            border: 16px solid #3b82f6;
            border-radius: 50%;
            position: absolute;
            inset: 20px auto auto 20px;
            animation: spin 8s linear infinite;
            box-shadow: 
                0 0 40px rgba(59, 130, 246, 0.4),
                inset 0 0 40px rgba(59, 130, 246, 0.1);
        }

        .gear::before,
        .gear::after {
            content: "";
            position: absolute;
            width: 20px;
            height: 48px;
            background: #38bdf8;
            top: -18px;
            left: calc(50% - 10px);
            border-radius: 12px;
            box-shadow: 0 0 20px rgba(56,189,248,0.4);
        }

        .gear::after {
            transform: rotate(90deg);
            top: calc(50% - 24px);
            left: -18px;
        }

        .gear-dot {
            position: absolute;
            width: 12px;
            height: 12px;
            background: #c7d2fe;
            border-radius: 50%;
            box-shadow: 0 0 15px rgba(199, 210, 254, 0.6);
        }

        .gear-dot:nth-child(1) { top: 12px; left: calc(50% - 6px); }
        .gear-dot:nth-child(2) { top: calc(50% - 6px); right: 12px; }
        .gear-dot:nth-child(3) { bottom: 12px; left: calc(50% - 6px); }
        .gear-dot:nth-child(4) { top: calc(50% - 6px); left: 12px; }

        .spanner {
            position: absolute;
            width: 20px;
            height: 112px;
            background: linear-gradient(180deg, #facc15, #f97316);
            top: 16px;
            left: 82px;
            border-radius: 16px;
            transform: rotate(45deg);
            box-shadow: 
                0 20px 40px rgba(250, 204, 21, 0.3),
                0 0 30px rgba(249, 115, 22, 0.2);
        }

        .spanner::before {
            content: "";
            position: absolute;
            top: -16px;
            left: -16px;
            width: 48px;
            height: 48px;
            border: 12px solid #0f172a;
            border-radius: 50%;
            box-shadow: 
                inset 0 0 0 3px rgba(255,255,255,0.2),
                0 0 20px rgba(15, 23, 42, 0.5);
        }

        .splash-card h2 {
            margin: 0 0 16px;
            font-size: clamp(2.2rem, 2.6vw, 3rem);
            line-height: 1.1;
            font-family: 'Playfair Display', serif;
            font-weight: 700;
            background: linear-gradient(135deg, #f8fafc 0%, #e2e8f0 50%, #f1f5f9 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            text-shadow: 0 2px 25px rgba(17,24,39,0.4);
            position: relative;
        }

        .splash-card h2::before {
            content: '';
            position: absolute;
            top: -10px;
            left: -20px;
            right: -20px;
            height: 2px;
            background: linear-gradient(90deg, transparent, rgba(250, 204, 63, 0.5), transparent);
            border-radius: 1px;
        }

        .highlight-word {
            background: var(--secondary-gradient);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            font-style: italic;
            font-family: 'Dancing Script', cursive;
            font-weight: 700;
            text-shadow: 0 0 30px rgba(139, 92, 246, 0.3);
        }

        .splash-card p {
            margin: 0 0 28px;
            font-size: 1.1rem;
            color: #dbeafe;
            line-height: 1.7;
            max-width: 560px;
            margin-left: auto;
            margin-right: auto;
            font-weight: 400;
            position: relative;
        }

        .splash-card p::after {
            content: '';
            position: absolute;
            bottom: -12px;
            left: 50%;
            transform: translateX(-50%);
            width: 40px;
            height: 1px;
            background: rgba(250, 204, 63, 0.4);
            border-radius: 1px;
        }

        .button-container {
            display: flex;
            flex-wrap: wrap;
            justify-content: center;
            gap: 16px;
            margin-top: 24px;
            margin-bottom: 8px;
        }

        .enter-button {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 12px;
            padding: 16px 32px;
            border-radius: 50px;
            border: 2px solid rgba(255, 208, 74, 0.4);
            background: var(--primary-gradient);
            color: #fff;
            font-weight: 600;
            text-decoration: none;
            letter-spacing: 0.02em;
            font-size: 1rem;
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
            box-shadow: 0 8px 25px rgba(245, 158, 11, 0.3);
        }

        .enter-button::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255,255,255,0.2), transparent);
            transition: left 0.5s ease;
        }

        .enter-button:hover::before {
            left: 100%;
        }

        .enter-button:hover,
        .enter-button:focus-visible {
            transform: translateY(-3px) scale(1.05);
            box-shadow: 
                0 15px 35px rgba(14, 165, 233, 0.4),
                0 0 40px rgba(245, 158, 11, 0.2);
            background: linear-gradient(135deg, #0ea5e9, #f59e0b);
            border-color: rgba(255, 208, 74, 0.6);
        }

        .enter-button.primary {
            background: var(--primary-gradient);
        }

        .enter-button.secondary {
            background: var(--accent-gradient);
            box-shadow: 0 8px 25px rgba(16, 185, 129, 0.3);
        }

        .enter-button.secondary:hover {
            box-shadow: 0 15px 35px rgba(16, 185, 129, 0.4);
        }

        .meta-note {
            margin-top: 24px;
            font-size: 0.95rem;
            color: #94a3b8;
            font-style: italic;
            opacity: 0.8;
            position: relative;
        }

        .meta-note::before {
            content: '💡';
            margin-right: 8px;
            font-size: 1.1em;
        }

        @keyframes spin {
            from { transform: rotate(0deg); }
            to { transform: rotate(360deg); }
        }

        @keyframes float {
            0%, 100% { transform: translateY(0px) rotate(0deg); }
            33% { transform: translateY(-10px) rotate(1deg); }
            66% { transform: translateY(10px) rotate(-1deg); }
        }

        @media (max-width: 640px) {
            .splash-card {
                padding: 36px 28px 32px;
            }
            
            .brand-mark h1 {
                font-size: 1.8rem;
            }
            
            .splash-card h2 {
                font-size: clamp(1.8rem, 4vw, 2.4rem);
            }
            
            .button-container {
                flex-direction: column;
                align-items: center;
            }
            
            .enter-button {
                width: 100%;
                max-width: 280px;
            }
        }
    </style>
</head>
<body>
    <main class="splash-card" role="main">
        <div class="brand-mark">
            <img src="images/kimage.png" alt="KFMMS logo">
            <h1>KFMMS</h1>
        </div>

        <div class="hero-icon" aria-hidden="true">
            <div class="gear">
                <span class="gear-dot"></span>
                <span class="gear-dot"></span>
                <span class="gear-dot"></span>
                <span class="gear-dot"></span>
            </div>
            <div class="spanner"></div>
        </div>

        <h2>Welcome to the <span class="highlight-word">KFMMS</span> Asset Management Suite</h2>
        <p>Streamline <strong>planned maintenance</strong>, manage <strong>equipment and spares</strong>, and launch <strong>work orders</strong> from one secure portal.</p>
        <div class="button-container">
            <a class="enter-button primary" href="auth.php">
                <i class="fas fa-sign-in-alt"></i> Enter KFMMS
            </a>
            <a class="enter-button secondary" href="license_gate.php?after_payment=1">
                <i class="fas fa-key"></i> Activate Subscription
            </a>
        </div>
        <div class="meta-note">Secure login required to continue. If you have just purchased a plan, activate your license key first.</div>
    </main>

    <script>
        // Frame busting for development - prevent loading in restricted frames
        if (window.top !== window.self) {
            try {
                // Check if we're in a development environment
                if (window.location.hostname === '127.0.0.1' || window.location.hostname === 'localhost') {
                    // Allow framing in development but log it
                    console.log('Page loaded in frame during development - allowing for VS Code preview');
                } else {
                    // In production, break out of frames
                    window.top.location = window.self.location;
                }
            } catch (err) {
                // Ignore cross-origin frame access errors from browser preview tools
                console.log('Frame busting skipped due to cross-origin parent frame:', err.message);
            }
        }

        // Test FontAwesome icons
        console.log('Testing FontAwesome icons...');
        const testIcon = document.createElement('i');
        testIcon.className = 'fas fa-chart-line';
        testIcon.style.display = 'none';
        document.body.appendChild(testIcon);
    </script>
</body>
</html>
