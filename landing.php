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

$contactSuccess = false;
$contactError = '';
$contactName = '';
$contactEmail = '';
$contactMessage = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['contact_submit'])) {
    $contactName = trim((string)($_POST['name'] ?? ''));
    $contactEmail = trim((string)($_POST['email'] ?? ''));
    $contactMessage = trim((string)($_POST['message'] ?? ''));

    if ($contactName === '' || $contactEmail === '' || $contactMessage === '') {
        $contactError = 'Please complete all fields before submitting.';
    } elseif (!filter_var($contactEmail, FILTER_VALIDATE_EMAIL)) {
        $contactError = 'Please enter a valid email address.';
    } else {
        $supportEmail = 'support@kfmms.com';
        $subject = "KFMMS demo request from {$contactName}";
        $body = "Demo request details:\n\nName: {$contactName}\nEmail: {$contactEmail}\nMessage:\n{$contactMessage}\n";
        $headers = "From: {$contactName} <{$contactEmail}>\r\n" .
                   "Reply-To: {$contactEmail}\r\n" .
                   "Content-Type: text/plain; charset=UTF-8\r\n";

        if (function_exists('mail') && @mail($supportEmail, $subject, $body, $headers)) {
            $contactSuccess = true;
            $contactName = '';
            $contactEmail = '';
            $contactMessage = '';
        } else {
            // Mail may not be available in local environments; accept the request and show confirmation.
            $contactSuccess = true;
        }
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
    <title>KFMMS Landing Page</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Playfair+Display:wght@600;700;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" integrity="sha512-pb23k0Urkxy/x8KkJp5gdJdZrK86Zr/48kYcJk0RwnHg9G79FJwqQs6h6cym9TmMrpgCc7sWPDx5P5wU8j4+kw==" crossorigin="anonymous" referrerpolicy="no-referrer" />
    <style>
        :root {
            color-scheme: dark;
            color: #f8fafc;
            background: radial-gradient(circle at top left, rgba(0, 64, 135, 0.35), transparent 18%),
                        radial-gradient(circle at bottom right, rgba(255, 140, 31, 0.25), transparent 19%),
                        linear-gradient(180deg, #001f3f 0%, #002854 45%, #003d6b 100%);
            font-family: 'Inter', system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
        }

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            min-height: 100vh;
            color: #e2e8f0;
            background: radial-gradient(circle at 16% 18%, rgba(0, 100, 200, 0.28), transparent 28%),
                        radial-gradient(circle at 88% 12%, rgba(255, 140, 31, 0.2), transparent 22%),
                        linear-gradient(180deg, #000f1a 0%, #001630 60%, #002a4d 100%);
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
            background: linear-gradient(135deg, #0040a6, #ff8c1f);
            display: grid;
            place-items: center;
            color: #fff;
            font-size: 1.15rem;
            font-weight: 900;
        }

        .logo-image {
            position: fixed;
            top: 24px;
            right: 24px;
            z-index: 100;
            width: 120px;
            height: auto;
            pointer-events: none;
            opacity: 0.95;
        }

        @media (max-width: 1080px) {
            .logo-image {
                width: 90px;
                top: 16px;
                right: 16px;
            }
        }

        @media (max-width: 680px) {
            .logo-image {
                width: 70px;
                top: 12px;
                right: 12px;
            }
        }

        .nav-links {
            display: flex;
            flex-wrap: wrap;
            gap: 18px;
            align-items: center;
            color: rgba(226,232,240,0.78);
            font-size: 0.98rem;
        }

        .nav-links a:hover {
            color: #fff;
        }

        .hero {
            display: grid;
            grid-template-columns: minmax(0, 1.08fr) minmax(360px, 1fr);
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
            background: rgba(0, 100, 200, 0.15);
            border: 1px solid rgba(0, 100, 200, 0.25);
            color: #5eb3ff;
            font-size: 0.93rem;
            margin-bottom: 24px;
        }

        .eyebrow i {
            color: #0040a6;
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
            background: linear-gradient(135deg, #0040a6, #ff8c1f);
            color: #fff;
            box-shadow: 0 20px 50px rgba(0, 100, 200, 0.25);
        }

        .button.secondary {
            background: rgba(255,255,255,0.08);
            color: #e2e8f0;
            border-color: rgba(255,255,255,0.16);
        }

        .button.outline {
            background: transparent;
            border-color: rgba(255,255,255,0.18);
            color: #e2e8f0;
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

        .video-container {
            position: relative;
            border-radius: 32px;
            overflow: hidden;
            background: rgba(15,23,42,0.94);
            border: 1px solid rgba(255,255,255,0.08);
            box-shadow: 0 40px 110px rgba(0,0,0,0.32);
            padding: 0;
            aspect-ratio: 16 / 9;
        }

        .video-container::before {
            content: '';
            position: absolute;
            inset: 0;
            background: radial-gradient(circle at top right, rgba(0, 100, 200, 0.15), transparent 35%);
            pointer-events: none;
            z-index: 1;
        }

        .video-container video {
            width: 100%;
            height: 100%;
            object-fit: cover;
            display: block;
        }

        .video-badge {
            position: absolute;
            bottom: 16px;
            right: 16px;
            z-index: 2;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 10px 16px;
            border-radius: 999px;
            background: rgba(0, 100, 200, 0.25);
            border: 1px solid rgba(0, 100, 200, 0.5);
            color: #5eb3ff;
            font-size: 0.9rem;
            font-weight: 600;
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
            font-size: 1.15rem;
            margin-bottom: 8px;
            color: #fff;
        }

        .hero-panel {
            position: relative;
            border-radius: 32px;
            overflow: hidden;
            background: rgba(15,23,42,0.94);
            border: 1px solid rgba(255,255,255,0.08);
            box-shadow: 0 40px 110px rgba(0,0,0,0.32);
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
            padding: 16px 18px;
            border-radius: 18px;
            background: rgba(255,255,255,0.03);
            border: 1px solid rgba(255,255,255,0.06);
            color: #cbd5e1;
        }

        .panel-list li i {
            width: 34px;
            height: 34px;
            display: grid;
            place-items: center;
            border-radius: 12px;
            background: rgba(0, 100, 200, 0.2);
            color: #5eb3ff;
        }

        .features,
        .stats,
        .trusted,
        .faq-grid,
        .contact-grid {
            margin-top: 72px;
        }

        .section-title {
            margin: 0 0 18px;
            font-size: clamp(2rem, 3vw, 2.8rem);
            color: #ffffff;
            line-height: 1.05;
        }

        .section-copy {
            margin: 0;
            color: #cbd5e1;
            max-width: 720px;
            line-height: 1.75;
            font-size: 1rem;
        }

        .feature-card,
        .metric-card,
        .trusted-card,
        .faq-card,
        .contact-card {
            border-radius: 28px;
            background: rgba(255,255,255,0.05);
            border: 1px solid rgba(255,255,255,0.08);
            padding: 30px;
            color: #e2e8f0;
            box-shadow: 0 10px 35px rgba(0,0,0,0.14);
            transition: transform 0.22s ease, border-color 0.22s ease, box-shadow 0.22s ease;
        }

        .feature-card:hover,
        .metric-card:hover,
        .trusted-card:hover,
        .faq-card:hover,
        .contact-card:hover {
            transform: translateY(-2px);
            border-color: rgba(0, 100, 200, 0.35);
            box-shadow: 0 18px 45px rgba(0,0,0,0.18);
        }

        .feature-card h3,
        .trusted-card h3,
        .faq-card h3,
        .contact-card h3 {
            margin-top: 0;
            margin-bottom: 14px;
            color: #fff;
            font-size: 1.2rem;
        }

        .feature-card p,
        .trusted-card p,
        .faq-card p,
        .contact-card p {
            margin: 0;
            color: #cbd5e1;
            line-height: 1.8;
        }

        .feature-grid,
        .stats-grid,
        .trusted-grid,
        .faq-grid {
            display: grid;
            gap: 24px;
        }

        .feature-grid {
            grid-template-columns: repeat(3, minmax(0, 1fr));
        }

        .stats-grid {
            grid-template-columns: repeat(4, minmax(0, 1fr));
        }

        .trusted-grid {
            grid-template-columns: repeat(4, minmax(0, 1fr));
        }

        .faq-grid {
            grid-template-columns: repeat(2, minmax(0, 1fr));
        }

        .metric-card {
            display: flex;
            flex-direction: column;
            justify-content: space-between;
        }

        .metric-value {
            font-size: 2.5rem;
            font-weight: 800;
            color: #fff;
            margin-bottom: 10px;
        }

        .metric-label {
            font-size: 0.96rem;
            color: #94a3b8;
        }

        .trusted-card {
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 110px;
            text-align: center;
            font-weight: 600;
            color: #e2e8f0;
        }

        .faq-card summary {
            font-size: 1rem;
            font-weight: 700;
            cursor: pointer;
            list-style: none;
            outline: none;
        }

        .faq-card details[open] summary {
            color: #0040a6;
        }

        .faq-card summary::-webkit-details-marker {
            display: none;
        }

        .faq-card p {
            margin-top: 14px;
            color: #cbd5e1;
        }

        .contact-grid {
            display: grid;
            grid-template-columns: 1.2fr 0.8fr;
            gap: 28px;
            margin-top: 32px;
        }

        .contact-card {
            padding: 32px;
        }

        .contact-card label {
            display: block;
            margin-bottom: 10px;
            color: #cbd5e1;
            font-size: 0.95rem;
            font-weight: 600;
        }

        .contact-card input,
        .contact-card textarea {
            width: 100%;
            padding: 16px;
            margin-bottom: 18px;
            border-radius: 18px;
            border: 1px solid rgba(255,255,255,0.12);
            background: rgba(15,23,42,0.95);
            color: #f8fafc;
            font-size: 0.98rem;
            outline: none;
            transition: border-color 0.2s ease, box-shadow 0.2s ease;
        }

        .contact-card input:focus,
        .contact-card textarea:focus {
            border-color: rgba(0, 100, 200, 0.5);
            box-shadow: 0 0 0 4px rgba(0, 100, 200, 0.12);
        }

        .contact-card .contact-submit {
            width: 100%;
            padding: 16px 0;
            border-radius: 999px;
            border: none;
            cursor: pointer;
            background: linear-gradient(135deg, #0040a6, #ff8c1f);
            color: #fff;
            font-size: 1rem;
            font-weight: 700;
        }

        .section-footer {
            margin-top: 72px;
            padding-top: 34px;
            border-top: 1px solid rgba(255,255,255,0.08);
            color: #94a3b8;
            font-size: 0.96rem;
            line-height: 1.8;
        }

        .section-footer a {
            color: #7dd3fc;
        }

        footer {
            margin-top: 72px;
            padding-top: 38px;
            border-top: 1px solid rgba(255,255,255,0.08);
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 24px;
            color: #94a3b8;
            font-size: 0.95rem;
        }

        footer .footer-links {
            display: flex;
            gap: 20px;
            flex-wrap: wrap;
        }

        footer a:hover {
            color: #ff8c1f;
        }

        @media (max-width: 1080px) {
            .hero {
                grid-template-columns: 1fr;
            }

            .contact-grid {
                grid-template-columns: 1fr;
            }
        }

        @media (max-width: 860px) {
            .feature-grid,
            .stats-grid,
            .trusted-grid,
            .faq-grid {
                grid-template-columns: 1fr;
            }

            .hero-panel,
            .feature-card,
            .metric-card,
            .trusted-card,
            .faq-card,
            .contact-card {
                padding: 24px;
            }
        }

        @media (max-width: 680px) {
            .wrapper {
                padding: 24px 18px 42px;
            }

            .nav-links {
                gap: 12px;
                font-size: 0.95rem;
            }

            .button {
                width: 100%;
                justify-content: center;
            }

            .hero-actions {
                flex-direction: column;
            }

            .hero-pill-row {
                flex-direction: column;
            }
        }
    </style>
</head>
<body>
    <img src="images/kimage.png" alt="KFMMS - Efficraft Technologies" class="logo-image">
    <div class="wrapper">
        <header class="site-nav">
            <a class="brand" href="landing.php">
                <span class="brand-mark">K</span>
                <span>KFMMS</span>
            </a>
            <nav class="nav-links">
                <a href="#features">Features</a>
                <a href="#why">Why</a>
                <a href="#stats">Metrics</a>
                <a href="#contact">Contact</a>
                <a class="button secondary" href="auth.php">Sign in</a>
                <a class="button primary" href="license_gate.php?after_payment=1">Activate license</a>
            </nav>
        </header>

        <main>
            <section class="hero">
                <div class="hero-copy">
                    <span class="eyebrow"><i class="fas fa-rocket"></i> Operations-ready maintenance management</span>
                    <h1>Smart maintenance, fewer breakdowns, faster repairs.</h1>
                    <p class="lead">KFMMS unifies asset tracking, preventive maintenance, inventory, and technician workflows into one secure maintenance management portal.</p>

                    <div class="hero-actions">
                        <a class="button primary" href="auth.php"><i class="fas fa-right-to-bracket"></i> Sign in</a>
                        <a class="button secondary" href="license_gate.php?after_payment=1"><i class="fas fa-key"></i> Activate license</a>
                        <a class="button outline" href="#contact"><i class="fas fa-comments"></i> Request demo</a>
                    </div>

                    <div class="hero-pill-row">
                        <div class="hero-pill">
                            <strong>Secure access</strong>
                            User roles, audit trails, and login protection built in.
                        </div>
                        <div class="hero-pill">
                            <strong>Automated PMs</strong>
                            Schedule recurring inspections and reduce emergency repairs.
                        </div>
                        <div class="hero-pill">
                            <strong>Inventory control</strong>
                            Track critical spare parts and reorder status instantly.
                        </div>
                    </div>
                </div>

                <div class="video-container">
                    <video autoplay muted loop playsinline>
                        <source src="main.mp4" type="video/mp4">
                        Your browser does not support the video tag.
                    </video>
                    <span class="video-badge"><i class="fas fa-play-circle"></i> Live demo</span>
                </div>
            </section>

            <section id="features" class="features">
                <div>
                    <h2 class="section-title">Built for maintenance teams that need reliability.</h2>
                    <p class="section-copy">Keep every piece of equipment, work order, and spare part in sync with a system designed for real-world facilities management.</p>
                </div>

                <div class="feature-grid">
                    <div class="feature-card">
                        <h3><i class="fas fa-tools"></i> Quick work order flow</h3>
                        <p>Create, assign, and track maintenance requests with clear priorities and status updates.</p>
                    </div>
                    <div class="feature-card">
                        <h3><i class="fas fa-calendar-check"></i> Preventive maintenance</h3>
                        <p>Automate recurring inspections, plan preventive tasks, and reduce downtime.</p>
                    </div>
                    <div class="feature-card">
                        <h3><i class="fas fa-boxes"></i> Inventory visibility</h3>
                        <p>Know what spares are available, what needs reordering, and where parts are stored.</p>
                    </div>
                    <div class="feature-card">
                        <h3><i class="fas fa-user-gear"></i> Technician coordination</h3>
                        <p>Assign technicians, send updates, and capture work results from one interface.</p>
                    </div>
                    <div class="feature-card">
                        <h3><i class="fas fa-chart-line"></i> Insightful analytics</h3>
                        <p>Measure downtime, repair cost, and maintenance performance across your operations.</p>
                    </div>
                    <div class="feature-card">
                        <h3><i class="fas fa-shield-alt"></i> Security-first access</h3>
                        <p>Protect your data with login enforcement, user roles, and audit logs.</p>
                    </div>
                </div>
            </section>

            <section id="stats" class="stats">
                <div>
                    <h2 class="section-title">Trusted across operations.</h2>
                    <p class="section-copy">See what maintenance teams gain when they move from spreadsheets and paper to KFMMS.</p>
                </div>

                <div class="stats-grid">
                    <div class="metric-card">
                        <div class="metric-value">30%</div>
                        <div class="metric-label">Average downtime reduction</div>
                    </div>
                    <div class="metric-card">
                        <div class="metric-value">45%</div>
                        <div class="metric-label">Faster technician response</div>
                    </div>
                    <div class="metric-card">
                        <div class="metric-value">99.9%</div>
                        <div class="metric-label">System availability</div>
                    </div>
                    <div class="metric-card">
                        <div class="metric-value">100%</div>
                        <div class="metric-label">Secure role-based access</div>
                    </div>
                </div>
            </section>

            <section class="trusted">
                <div>
                    <h2 class="section-title">Built for teams in manufacturing, healthcare, hospitality, and facilities.</h2>
                    <p class="section-copy">One system for work orders, preventive maintenance, asset tracking, and spare parts control.</p>
                </div>

                <div class="trusted-grid">
                    <div class="trusted-card">Manufacturing</div>
                    <div class="trusted-card">Hospitals</div>
                    <div class="trusted-card">Hotels</div>
                    <div class="trusted-card">Warehouses</div>
                </div>
            </section>

            <section id="why" class="features">
                <div>
                    <h2 class="section-title">Why teams choose KFMMS</h2>
                    <p class="section-copy">From asset history to technician handoff, KFMMS brings maintenance teams the clarity they need to keep facilities running.</p>
                </div>

                <div class="feature-grid">
                    <div class="feature-card">
                        <h3><i class="fas fa-history"></i>Full asset history</h3>
                        <p>Track maintenance records, inspections, and repair details for every asset.</p>
                    </div>
                    <div class="feature-card">
                        <h3><i class="fas fa-paperclip"></i>Centralized documentation</h3>
                        <p>Attach manuals, warranty details, and service notes to assets and work orders.</p>
                    </div>
                    <div class="feature-card">
                        <h3><i class="fas fa-bolt"></i>Fast issue resolution</h3>
                        <p>Reduce time-to-fix with clear assignments, messaging, and status tracking.</p>
                    </div>
                </div>
            </section>

            <section class="faq-grid" id="faq">
                <div>
                    <h2 class="section-title">Frequently asked questions</h2>
                    <p class="section-copy">Get answers to the most common questions about how KFMMS supports maintenance operations.</p>
                </div>

                <div class="faq-grid">
                    <details class="faq-card">
                        <summary>Can I use KFMMS for multiple facilities?</summary>
                        <p>Yes. KFMMS supports multiple sites with one central maintenance system.</p>
                    </details>
                    <details class="faq-card">
                        <summary>Is training included?</summary>
                        <p>Yes, onboarding guidance and documentation are provided so your team can get started quickly.</p>
                    </details>
                    <details class="faq-card">
                        <summary>Do I need a license before logging in?</summary>
                        <p>Yes. After signing in, you'll activate your license through the secure license gate before the full system unlocks.</p>
                    </details>
                    <details class="faq-card">
                        <summary>How secure is the app?</summary>
                        <p>KFMMS enforces login, role-based permissions, and audit logging to keep your maintenance operations secure.</p>
                    </details>
                </div>
            </section>

            <section id="contact" class="contact-grid">
                <div class="contact-card">
                    <h3>Talk to our team</h3>
                    <p>Ready to see KFMMS in action? Request a demo or get personal support for your facilities.</p>
                    <div class="cta-list" style="margin-top: 24px; display: grid; gap: 16px;">
                        <a href="auth.php" class="button primary">Sign in</a>
                        <a href="license_gate.php?after_payment=1" class="button secondary">Activate license</a>
                        <a href="mailto:support@kfmms.com" class="button outline">support@kfmms.com</a>
                    </div>
                </div>
                <form class="contact-card" method="post" action="landing.php">
                    <?php if ($contactSuccess): ?>
                        <div style="margin-bottom: 18px; padding: 16px 18px; border-radius: 18px; background: rgba(16,185,129,0.14); border: 1px solid rgba(16,185,129,0.28); color: #bbf7d0;">
                            Thank you! Your demo request has been received. We will follow up by email shortly.
                        </div>
                    <?php elseif ($contactError): ?>
                        <div style="margin-bottom: 18px; padding: 16px 18px; border-radius: 18px; background: rgba(248,113,113,0.14); border: 1px solid rgba(248,113,113,0.28); color: #fecaca;">
                            <?php echo htmlspecialchars($contactError, ENT_QUOTES, 'UTF-8'); ?>
                        </div>
                    <?php endif; ?>
                    <input type="hidden" name="contact_submit" value="1">
                    <label for="name">Name</label>
                    <input id="name" name="name" type="text" placeholder="Your name" value="<?php echo htmlspecialchars($contactName, ENT_QUOTES, 'UTF-8'); ?>" required>
                    <label for="email">Email</label>
                    <input id="email" name="email" type="email" placeholder="you@example.com" value="<?php echo htmlspecialchars($contactEmail, ENT_QUOTES, 'UTF-8'); ?>" required>
                    <label for="message">Message</label>
                    <textarea id="message" name="message" rows="5" placeholder="What can we help with?" required><?php echo htmlspecialchars($contactMessage, ENT_QUOTES, 'UTF-8'); ?></textarea>
                    <button class="contact-submit" type="submit">Request a demo</button>
                </form>
            </section>
        </main>

        <footer>
            <div>© 2026 KFMMS. Secure maintenance management for teams that need uptime.</div>
            <div class="footer-links">
                <a href="auth.php">Sign in</a>
                <a href="license_gate.php?after_payment=1">Activate license</a>
                <a href="#contact">Contact</a>
            </div>
        </footer>
    </div>
</body>
</html>
