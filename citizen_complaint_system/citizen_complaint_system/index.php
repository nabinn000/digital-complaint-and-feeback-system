<?php
session_start();
// Only destroy session if user explicitly navigated here via logout
// Removed session_destroy() — visiting the homepage no longer logs you out
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Citizen Complaint Portal — Government of Nepal</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@300;400;500&display=swap" rel="stylesheet">
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

        :root {
            --navy: #0b1f3a;
            --navy-hover: #122848;
            --gold: #c9a84c;
            --bg: #eef2f7;
            --white: #ffffff;
            --text: #111827;
            --muted: #6b7280;
            --border: #e5e7eb;
        }

        body {
            font-family: 'DM Sans', sans-serif;
            background: var(--bg);
            color: var(--text);
            min-height: 100vh;
            display: flex;
            flex-direction: column;
        }

        /* ── Navbar ─────────────────────────────── */
        nav {
            background: var(--navy);
            padding: 0 32px;
            height: 60px;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .nav-brand {
            display: flex;
            align-items: center;
            gap: 10px;
            text-decoration: none;
        }

        .nav-brand .icon {
            font-size: 20px;
        }

        .nav-brand .name {
            font-size: 14px;
            font-weight: 500;
            color: #fff;
        }

        .nav-links { display: flex; align-items: center; gap: 8px; }

        .nav-links a {
            font-size: 13px;
            font-weight: 400;
            color: rgba(255,255,255,0.75);
            text-decoration: none;
            padding: 7px 14px;
            border-radius: 7px;
            transition: background 0.18s, color 0.18s;
        }

        .nav-links a:hover { background: rgba(255,255,255,0.1); color: #fff; }

        .nav-links a.btn-nav {
            background: var(--gold);
            color: var(--navy);
            font-weight: 500;
        }

        .nav-links a.btn-nav:hover { background: #d4b560; }

        /* ── Hero ───────────────────────────────── */
        .hero {
            background: var(--navy);
            padding: 72px 32px 80px;
            text-align: center;
            position: relative;
            overflow: hidden;
        }

        .hero::before {
            content: '';
            position: absolute;
            inset: 0;
            background: radial-gradient(ellipse 60% 60% at 50% 110%, rgba(201,168,76,0.12), transparent);
            pointer-events: none;
        }

        .hero-badge {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            background: rgba(201,168,76,0.15);
            border: 1px solid rgba(201,168,76,0.35);
            border-radius: 20px;
            padding: 5px 14px;
            font-size: 11.5px;
            font-weight: 500;
            letter-spacing: 0.08em;
            color: var(--gold);
            text-transform: uppercase;
            margin-bottom: 24px;
            animation: fadeUp 0.5s ease both;
        }

        .hero h1 {
            font-size: clamp(28px, 4vw, 44px);
            font-weight: 500;
            color: #fff;
            line-height: 1.2;
            max-width: 620px;
            margin: 0 auto 16px;
            animation: fadeUp 0.5s 0.08s ease both;
        }

        .hero p {
            font-size: 15px;
            font-weight: 300;
            color: rgba(255,255,255,0.6);
            max-width: 480px;
            margin: 0 auto 36px;
            line-height: 1.7;
            animation: fadeUp 0.5s 0.16s ease both;
        }

        .hero-actions {
            display: flex;
            gap: 12px;
            justify-content: center;
            flex-wrap: wrap;
            animation: fadeUp 0.5s 0.22s ease both;
        }

        .hero-actions a {
            height: 46px;
            padding: 0 28px;
            border-radius: 8px;
            font-size: 14px;
            font-weight: 500;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            transition: opacity 0.18s, transform 0.1s;
        }

        .hero-actions a:active { transform: scale(0.98); }

        .btn-primary { background: var(--gold); color: var(--navy); }
        .btn-primary:hover { opacity: 0.88; }

        .btn-outline {
            background: transparent;
            color: rgba(255,255,255,0.85);
            border: 1.5px solid rgba(255,255,255,0.25);
        }

        .btn-outline:hover { border-color: rgba(255,255,255,0.5); color: #fff; }

        /* ── Features ───────────────────────────── */
        .section {
            padding: 64px 32px;
            max-width: 1100px;
            margin: 0 auto;
            width: 100%;
        }

        .section-label {
            font-size: 11px;
            font-weight: 500;
            letter-spacing: 0.14em;
            text-transform: uppercase;
            color: var(--gold);
            text-align: center;
            margin-bottom: 10px;
        }

        .section-title {
            font-size: 24px;
            font-weight: 500;
            color: var(--navy);
            text-align: center;
            margin-bottom: 8px;
        }

        .section-sub {
            font-size: 14px;
            color: var(--muted);
            text-align: center;
            font-weight: 300;
            margin-bottom: 40px;
        }

        .features-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 20px;
        }

        .feature-card {
            background: var(--white);
            border-radius: 12px;
            padding: 28px 24px;
            border: 1px solid var(--border);
            transition: box-shadow 0.2s, transform 0.2s;
        }

        .feature-card:hover {
            box-shadow: 0 4px 20px rgba(0,0,0,0.07);
            transform: translateY(-2px);
        }

        .feature-icon {
            width: 44px;
            height: 44px;
            background: #f0f4f9;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 20px;
            margin-bottom: 16px;
        }

        .feature-card h3 {
            font-size: 15px;
            font-weight: 500;
            color: var(--navy);
            margin-bottom: 8px;
        }

        .feature-card p {
            font-size: 13px;
            color: var(--muted);
            font-weight: 300;
            line-height: 1.6;
        }

        /* ── How it works ───────────────────────── */
        .steps-section {
            background: var(--white);
            padding: 64px 32px;
        }

        .steps-inner {
            max-width: 900px;
            margin: 0 auto;
        }

        .steps-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: 0;
            position: relative;
            margin-top: 40px;
        }

        .step {
            text-align: center;
            padding: 0 20px;
            position: relative;
        }

        .step:not(:last-child)::after {
            content: '';
            position: absolute;
            top: 20px;
            right: -1px;
            width: 2px;
            height: 40px;
            background: var(--border);
        }

        .step-num {
            width: 40px;
            height: 40px;
            background: var(--navy);
            color: #fff;
            border-radius: 50%;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 14px;
            font-weight: 500;
            margin-bottom: 14px;
        }

        .step h4 {
            font-size: 14px;
            font-weight: 500;
            color: var(--navy);
            margin-bottom: 6px;
        }

        .step p {
            font-size: 12.5px;
            color: var(--muted);
            font-weight: 300;
            line-height: 1.6;
        }

        /* ── CTA banner ─────────────────────────── */
        .cta-section {
            padding: 64px 32px;
            max-width: 700px;
            margin: 0 auto;
            text-align: center;
        }

        .cta-section h2 {
            font-size: 22px;
            font-weight: 500;
            color: var(--navy);
            margin-bottom: 10px;
        }

        .cta-section p {
            font-size: 14px;
            color: var(--muted);
            font-weight: 300;
            margin-bottom: 28px;
            line-height: 1.7;
        }

        .cta-btn {
            display: inline-flex;
            align-items: center;
            height: 46px;
            padding: 0 32px;
            background: var(--navy);
            color: #fff;
            font-size: 14px;
            font-weight: 500;
            border-radius: 8px;
            text-decoration: none;
            transition: background 0.18s;
        }

        .cta-btn:hover { background: var(--navy-hover); }

        /* ── Footer ─────────────────────────────── */
        footer {
            background: var(--navy);
            padding: 24px 32px;
            text-align: center;
            margin-top: auto;
        }

        footer p {
            font-size: 12px;
            color: rgba(255,255,255,0.4);
            font-weight: 300;
            line-height: 1.8;
        }

        /* ── Animations ─────────────────────────── */
        @keyframes fadeUp {
            from { opacity: 0; transform: translateY(14px); }
            to   { opacity: 1; transform: translateY(0); }
        }

        @media (max-width: 600px) {
            nav { padding: 0 20px; }
            .hero { padding: 52px 20px 60px; }
            .section { padding: 48px 20px; }
            .steps-section { padding: 48px 20px; }
            .step:not(:last-child)::after { display: none; }
        }
    </style>
</head>
<body>

    <!-- Navbar -->
    <nav>
        <a class="nav-brand" href="index.php">
            <span class="icon">🏛</span>
            <span class="name">Citizen Complaint Portal</span>
        </a>
        <div class="nav-links">
            <?php if (isset($_SESSION["user_id"])): ?>
                <a href="<?php echo $_SESSION['role'] == 'admin' ? 'admin/admin_dashboard.php' : 'dashboard.php'; ?>">Dashboard</a>
                <a href="logout.php">Logout</a>
            <?php else: ?>
                <a href="track.php">Track complaint</a>
                <a href="login.php">Sign in</a>
                <a href="register.php" class="btn-nav">Register</a>
            <?php endif; ?>
        </div>
    </nav>

    <!-- Hero -->
    <section class="hero">
        <div class="hero-badge">🇳🇵 &nbsp;Official Government Portal</div>
        <h1>Report issues. Track progress.<br>Get results.</h1>
        <p>A transparent digital platform connecting citizens of Nepal with local government authorities for faster, accountable complaint resolution.</p>
        <div class="hero-actions">
            <a href="register.php" class="btn-primary">Submit a complaint</a>
            <a href="login.php" class="btn-outline">Sign in to dashboard</a>
        </div>
    </section>

    <!-- Features -->
    <div class="section">
        <div class="section-label">Why use this platform</div>
        <h2 class="section-title">Everything you need, in one place</h2>
        <p class="section-sub">Designed to make complaint management simple for citizens and efficient for authorities.</p>
        <div class="features-grid">
            <div class="feature-card">
                <div class="feature-icon">🔍</div>
                <h3>Transparent Tracking</h3>
                <p>Every complaint gets a unique tracking ID so you can monitor progress at any time.</p>
            </div>
            <div class="feature-card">
                <div class="feature-icon">⚡</div>
                <h3>Fast Resolution</h3>
                <p>Authorities are notified immediately and can update complaint status in real time.</p>
            </div>
            <div class="feature-card">
                <div class="feature-icon">💬</div>
                <h3>Direct Communication</h3>
                <p>Citizens and officials interact through a single structured platform — no lost calls.</p>
            </div>
            <div class="feature-card">
                <div class="feature-icon">🔒</div>
                <h3>Secure & Private</h3>
                <p>Your personal data is encrypted and only accessible to authorised administrators.</p>
            </div>
        </div>
    </div>

    <!-- How it works -->
    <div class="steps-section">
        <div class="steps-inner">
            <div class="section-label">How it works</div>
            <h2 class="section-title" style="text-align:center;">Three simple steps</h2>
            <div class="steps-grid">
                <div class="step">
                    <div class="step-num">1</div>
                    <h4>Register & sign in</h4>
                    <p>Create a free citizen account in under a minute.</p>
                </div>
                <div class="step">
                    <div class="step-num">2</div>
                    <h4>Submit your complaint</h4>
                    <p>Describe the issue, choose a category, and attach evidence if needed.</p>
                </div>
                <div class="step">
                    <div class="step-num">3</div>
                    <h4>Track the outcome</h4>
                    <p>Follow your complaint from Pending to Resolved using your tracking ID.</p>
                </div>
            </div>
        </div>
    </div>

    <!-- CTA -->
    <div class="cta-section">
        <h2>Ready to report an issue?</h2>
        <p>Join thousands of citizens already using this platform to hold local government accountable and improve public services.</p>
        <a href="register.php" class="cta-btn">Create a free account</a>
    </div>

    <!-- Footer -->
    <footer>
        <p>© 2026 Ministry of Public Services, Government of Nepal</p>
        <p>Official Government Portal &nbsp;·&nbsp; All Rights Reserved</p>
    </footer>

</body>
</html>