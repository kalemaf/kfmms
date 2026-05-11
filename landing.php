<?php
/**
 * Landing page shown to visitors before login
 */

header('X-Frame-Options: DENY', false);

error_reporting(E_ALL);
ini_set('display_errors', '0');
ini_set('log_errors', '1');

require_once 'config.inc.php';
session_save_path($session_save_path);
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once 'common.inc.php';

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
    <title>KFMMS Landing Page</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Playfair+Display:wght@600;700;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" integrity="sha512-pb23k0Urkxy/x8KkJp5gdJdZrK86Zr/48kYcJk0RwnHg9G79FJwqQs6h6cym9TmMrpgCc7sWPDx5P5wU8j4+kw==" crossorigin="anonymous" referrerpolicy="no-referrer" />
    <style>
        :root {
            color-scheme: dark;
            color: #f8fafc;
            background: radial-gradient(circle at top left, rgba(59, 130, 246, 0.26), transparent 18%),
                        radial-gradient(circle at bottom right, rgba(245, 158, 11, 0.18), transparent 19%),
                        linear-gradient(180deg, #050b16 0%, #08131f 45%, #0d1728 100%);
            font-family: 'Inter', system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
        }

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            min-height: 100vh;
            color: #e2e8f0;
            background: radial-gradient(circle at 16% 18%, rgba(56,189,248,0.22), transparent 28%),
                        radial-gradient(circle at 88% 12%, rgba(244, 63, 94, 0.14), transparent 22%),
                        linear-gradient(180deg, #020617 0%, #09131f 60%, #0f1d2f 100%);
        }

        body, html {
            width: 100%;
        }

        html {
            scroll-behavior: smooth;
        }

        a {
            color: inherit;
            text-decoration: none;
        }

        .wrapper {
            width: min(1180px, 100%);
            margin: 0 auto;
            padding: 32px 24px 60px;
        }

        .site-nav {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 18px;
            padding-bottom: 12px;
        }

        .brand {
            display: inline-flex;
            align-items: center;
            gap: 14px;
            font-weight: 700;
            letter-spacing: 0.05em;
            font-size: 1rem;
        }

        .brand-mark {
            width: 46px;
            height: 46px;
            border-radius: 18px;
            background: linear-gradient(135deg, #0ea5e9, #f59e0b);
            display: grid;
            place-items: center;
            color: #0f172a;
            font-size: 1.15rem;
            font-weight: 900;
        }

        .nav-links {
            display: flex;
            flex-wrap: wrap;
            gap: 20px;
            align-items: center;
            color: rgba(226,232,240,0.78);
        }

        .nav-links a:hover {
            color: #fff;
        }

        .hero {
            display: grid;
            grid-template-columns: minmax(0, 1.05fr) minmax(360px, 1fr);
            gap: 42px;
            align-items: center;
            margin-top: 48px;
        }

        .hero-copy {
            max-width: 640px;
        }

        .eyebrow {
            display: inline-flex;
            align-items: center;
            gap: 10px;
            padding: 10px 16px;
            border-radius: 999px;
            background: rgba(59,130,246,0.12);
            border: 1px solid rgba(59,130,246,0.18);
            color: #bfdbfe;
            font-size: 0.93rem;
            margin-bottom: 24px;
        }

        .eyebrow i {
            color: #60a5fa;
        }

        h1 {
            margin: 0;
            font-size: clamp(3rem, 5vw, 4.8rem);
            line-height: 0.96;
            letter-spacing: -0.05em;
            color: #ffffff;
        }

        p.lead {
            margin: 26px 0 32px;
            font-size: 1.1rem;
            line-height: 1.85;
            color: #cbd5e1;
            max-width: 680px;
        }

        .hero-actions {
            display: flex;
            flex-wrap: wrap;
            gap: 16px;
        }

        .button {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            padding: 16px 28px;
            border-radius: 999px;
            border: 1px solid transparent;
            font-weight: 700;
            transition: transform 0.22s ease, box-shadow 0.22s ease, background 0.22s ease;
        }

        .button.primary {
            background: linear-gradient(135deg, #0ea5e9, #f59e0b);
            color: #0f172a;
            box-shadow: 0 20px 50px rgba(14,165,233,0.22);
        }

        .button.secondary {
            background: rgba(255,255,255,0.08);
            color: #e2e8f0;
            border-color: rgba(255,255,255,0.16);
        }

        .button:hover {
            transform: translateY(-2px);
            box-shadow: 0 24px 60px rgba(0,0,0,0.22);
        }

        .hero-pill-row {
            display: flex;
            flex-wrap: wrap;
            gap: 14px;
            margin-top: 40px;
        }

        .hero-pill {
            flex: 1 1 190px;
            min-width: 190px;
            padding: 18px 22px;
            border-radius: 20px;
            background: rgba(255,255,255,0.04);
            border: 1px solid rgba(255,255,255,0.08);
            color: #e2e8f0;
        }

        .hero-pill strong {
            display: block;
            font-size: 1.3rem;
            margin-bottom: 8px;
            color: #fff;
        }

        .hero-panel {
            position: relative;
            border-radius: 32px;
            overflow: hidden;
            background: rgba(15,23,42,0.92);
            border: 1px solid rgba(255,255,255,0.08);
            box-shadow: 0 40px 110px rgba(0,0,0,0.33);
            padding: 32px;
        }

        .hero-panel::before {
            content: '';
            position: absolute;
            inset: 0;
            background: radial-gradient(circle at top right, rgba(14,165,233,0.12), transparent 35%);
            pointer-events: none;
        }

        .panel-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            gap: 18px;
            margin-bottom: 24px;
            flex-wrap: wrap;
        }

        .panel-title {
            margin: 0;
            font-size: 1.1rem;
            color: #f8fafc;
        }

        .panel-subtitle {
            color: #94a3b8;
            margin: 8px 0 0;
            max-width: 440px;
        }

        .panel-list {
            display: grid;
            gap: 14px;
            list-style: none;
            padding: 0;
            margin: 0;
        }

        .panel-list li {
            display: flex;
            align-items: center;
            gap: 14px;
            padding: 14px 16px;
            border-radius: 18px;
            background: rgba(255,255,255,0.03);
            border: 1px solid rgba(255,255,255,0.06);
        }

        .panel-list li i {
            width: 34px;
            height: 34px;
            display: grid;
            place-items: center;
            border-radius: 12px;
            background: rgba(59,130,246,0.16);
            color: #7dd3fc;
        }

        .features {
            margin-top: 64px;
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 24px;
        }

        .feature-card {
            padding: 28px;
            border-radius: 28px;
            background: rgba(255,255,255,0.05);
            border: 1px solid rgba(255,255,255,0.08);
        }

        .feature-card h3 {
            margin-top: 0;
            margin-bottom: 14px;
            font-size: 1.15rem;
            color: #fff;
        }

        .feature-card p {
            margin: 0;
            color: #cbd5e1;
            line-height: 1.75;
        }

        .feature-badges {
            display: flex;
            flex-wrap: wrap;
            gap: 12px;
            margin-top: 22px;
        }

        .badge {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 10px 14px;
            border-radius: 999px;
            background: rgba(14,165,233,0.12);
            color: #bfdbfe;
            font-size: 0.92rem;
        }

        .section-footer {
            margin-top: 64px;
            padding-top: 34px;
            border-top: 1px solid rgba(255,255,255,0.08);
            color: #94a3b8;
            font-size: 0.96rem;
            line-height: 1.8;
        }

        .section-footer a {
            color: #7dd3fc;
        }

        @media (max-width: 960px) {
            .hero {
                grid-template-columns: 1fr;
            }

            .features {
                grid-template-columns: 1fr;
            }
        }

        @media (max-width: 700px) {
            .wrapper {
                padding: 24px 18px 42px;
            }

            .hero-copy {
                max-width: 100%;
            }
        }
    </style>
</head>
<body>
    <div class="wrapper">
        <header class="site-nav">
            <a class="brand" href="landing.php">
                <span class="brand-mark">K</span>
                <span>KFMMS</span>
            </a>
            <nav class="nav-links">
                <a href="#features">Features</a>
                <a href="#security">Security</a>
                <a href="#why">Why KFMMS</a>
                <a class="button secondary" href="auth.php">Sign in</a>
            </nav>
        </header>

        <main>
            <section class="hero">
                <div class="hero-copy">
                    <span class="eyebrow"><i class="fas fa-rocket"></i> Maintenance software for operations teams</span>
                    <h1>One place to manage work orders, inventory, and preventive maintenance.</h1>
                    <p class="lead">KFMMS gives maintenance teams a clean, secure portal to track assets, assign technicians, and ensure equipment uptime without the complexity.</p>

                    <div class="hero-actions">
                        <a class="button primary" href="auth.php"><i class="fas fa-right-to-bracket"></i> Sign in</a>
                        <a class="button secondary" href="license_gate.php?after_payment=1"><i class="fas fa-key"></i> Activate license</a>
                    </div>

                    <div class="hero-pill-row">
                        <div class="hero-pill">
                            <strong>24/7 ready</strong>
                            Secure team access and asset visibility.
                        </div>
                        <div class="hero-pill">
                            <strong>Automated PMs</strong>
                            Reduce emergency repairs with scheduled maintenance.
                        </div>
                        <div class="hero-pill">
                            <strong>Inventory visibility</strong>
                            See spare levels and reorder status instantly.
                        </div>
                    </div>
                </div>

                <section class="hero-panel" aria-label="KFMMS dashboard preview">
                    <div class="panel-header">
                        <div>
                            <p class="panel-title">Live operations dashboard</p>
                            <p class="panel-subtitle">Track team activity, work order progress, and inventory health from one secure workspace.</p>
                        </div>
                        <span class="badge"><i class="fas fa-lock"></i> Login required</span>
                    </div>
                    <ul class="panel-list">
                        <li><i class="fas fa-list-check"></i> Quick view of open and overdue work orders</li>
                        <li><i class="fas fa-boxes"></i> Inventory alerts for critical spares</li>
                        <li><i class="fas fa-users"></i> Technician assignments and status updates</li>
                        <li><i class="fas fa-calendar-days"></i> Planned maintenance for the week ahead</li>
                    </ul>
                </section>
            </section>

            <section id="features" class="features">
                <div class="feature-card">
                    <h3><i class="fas fa-tools"></i> Work orders that move fast</h3>
                    <p>Open and assign work orders in seconds, then monitor completion with clear status updates and priority routing.</p>
                    <div class="feature-badges">
                        <span class="badge">Assign quickly</span>
                        <span class="badge">Track completion</span>
                    </div>
                </div>
                <div class="feature-card">
                    <h3><i class="fas fa-calendar-check"></i> Preventive maintenance made easy</h3>
                    <p>Schedule regular checks, automate repeat tasks, and reduce downtime with a reliable PM engine.</p>
                    <div class="feature-badges">
                        <span class="badge">Recurring PMs</span>
                        <span class="badge">Calendar view</span>
                    </div>
                </div>
                <div class="feature-card" id="security">
                    <h3><i class="fas fa-shield-alt"></i> Built-in security controls</h3>
                    <p>Secure your site with enforced login, user roles, and audit logging for every maintenance action.</p>
                    <div class="feature-badges">
                        <span class="badge">Role based</span>
                        <span class="badge">Audit logs</span>
                    </div>
                </div>
            </section>

            <section id="why" class="section-footer">
                <p><strong>KFMMS</strong> helps maintenance teams reduce emergency repairs, improve uptime, and simplify the life cycle of asset work. Start from this landing page and choose whether to sign in or activate your license before entering the app.</p>
                <p>Need support? <a href="auth.php">Sign in</a> or <a href="license_gate.php?after_payment=1">activate your license</a> to continue.</p>
            </section>
        </main>
    </div>
</body>
</html>
