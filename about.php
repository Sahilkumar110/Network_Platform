<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>About | Network Platform</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="responsive.css">
    <style>
        html, body {
            margin: 0 !important;
            padding: 0 !important;
        }
        body {
            margin: 0;
            font-family: "Segoe UI", Tahoma, Geneva, Verdana, sans-serif;
            color: #0f172a;
            background: #f3f6fb;
        }
        body.landing-page {
            padding-top: 76px !important;
            margin-top: 0 !important;
        }
        .topbar {
            position: fixed !important;
            top: 0 !important;
            left: 0 !important;
            right: 0 !important;
            width: 100% !important;
            margin: 0 !important;
            z-index: 5000 !important;
            background: rgba(255, 255, 255, 0.92);
            backdrop-filter: blur(10px);
            border-bottom: 1px solid #dbe2ee;
        }
        .brand {
            font-size: 1.7rem;
            font-weight: 900;
            letter-spacing: -0.04em;
            color: #1e3a8a;
            text-decoration: none;
        }
        .brand span { color: #3b82f6; font-weight: 500; }
        .nav-link {
            font-weight: 700;
            color: #1f2937 !important;
            border-radius: 10px;
            padding: 8px 12px !important;
        }
        .nav-link:hover, .nav-link.active {
            background: #eef4ff;
            color: #1e3a8a !important;
        }
        .btn-join {
            background: linear-gradient(135deg, #2563eb, #1d4ed8);
            border: 1px solid #1d4ed8;
            color: #fff;
            border-radius: 10px;
            font-weight: 700;
            padding: 8px 14px;
            text-decoration: none;
            display: inline-block;
        }
        .hero {
            min-height: 48vh;
            display: flex;
            align-items: center;
            background:
                linear-gradient(110deg, rgba(15, 23, 42, 0.9), rgba(30, 58, 138, 0.8)),
                url("https://images.unsplash.com/photo-1488998527040-85054a85150e?auto=format&fit=crop&w=1800&q=80") center/cover no-repeat;
            color: #fff;
        }
        .hero h1 {
            font-size: clamp(2rem, 5vw, 3.4rem);
            line-height: 1.04;
            letter-spacing: -0.03em;
            margin-bottom: 12px;
            max-width: 760px;
        }
        .hero p {
            color: #dbeafe;
            max-width: 740px;
            margin-bottom: 0;
            font-size: 1.05rem;
        }
        .section {
            padding: 72px 0;
        }
        .section-title {
            font-size: clamp(1.8rem, 3.4vw, 2.5rem);
            letter-spacing: -0.03em;
            margin-bottom: 12px;
            font-weight: 800;
        }
        .section-subtitle {
            color: #64748b;
            max-width: 760px;
            margin: 0 auto 26px;
        }
        .glass-panel {
            background: #fff;
            border: 1px solid #e2e8f0;
            border-radius: 18px;
            box-shadow: 0 10px 24px rgba(15, 23, 42, 0.06);
            padding: 22px;
            height: 100%;
        }
        .icon-badge {
            width: 44px;
            height: 44px;
            border-radius: 12px;
            background: #eaf2ff;
            color: #1e3a8a;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 1.1rem;
            margin-bottom: 10px;
            font-weight: 800;
        }
        .media-card img {
            width: 100%;
            height: 220px;
            object-fit: cover;
            border-radius: 14px;
            border: 1px solid #dbeafe;
            margin-bottom: 12px;
        }
.site-footer {
            margin-top: 52px;
            background: #0b1220;
            color: #dbe4f3;
            border-top: 1px solid #23324d;
        }
        .site-footer a { color: #c7d9ff; text-decoration: none; }
        .site-footer a:hover { color: #fff; }
        @media (max-width: 991.98px) {
            body.landing-page {
                padding-top: 70px !important;
            }
            .section { padding: 56px 0; }
            .hero { min-height: 42vh; }
        }
    </style>
</head>
<body class="landing-page">

<header class="topbar">
    <nav class="navbar navbar-expand-lg py-2">
        <div class="container">
            <a class="brand" href="index.php">NETWORK<span>PLATFORM</span></a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#mainNav" aria-controls="mainNav" aria-expanded="false" aria-label="Toggle navigation">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="mainNav">
                <ul class="navbar-nav ms-auto align-items-lg-center gap-lg-1">
                    <li class="nav-item"><a class="nav-link active" href="about.php">About</a></li>
                    <li class="nav-item"><a class="nav-link" href="news.php">News & Events</a></li>
                    <li class="nav-item"><a class="nav-link" href="contact.php">Contact</a></li>
                    <li class="nav-item"><a class="nav-link" href="login.php">Login</a></li>
                    <li class="nav-item ms-lg-2"><a class="btn-join mt-2 mt-lg-0" href="register.php">Join Now</a></li>
                </ul>
            </div>
        </div>
    </nav>
</header>

<section class="hero">
    <div class="container">
        <h1>About Network Platform</h1>
        <p>Network Platform is built for structured member growth, transparent finance tracking, and a robust referral ecosystem from user to admin level.</p>
    </div>
</section>

<section class="section">
    <div class="container text-center">
        <h2 class="section-title">What Makes The Platform Strong</h2>
        <p class="section-subtitle">The architecture is focused on clarity, secure access, and measurable progression across investment, referral, and withdrawal operations.</p>
        <div class="row g-3 text-start">
            <div class="col-12 col-md-6 col-lg-4">
                <div class="glass-panel">
                    <div class="icon-badge">01</div>
                    <h5>Transparent User Dashboard</h5>
                    <p class="mb-0 text-secondary">Users see wallet status, referral activity, and withdrawal readiness in one clean interface.</p>
                </div>
            </div>
            <div class="col-12 col-md-6 col-lg-4">
                <div class="glass-panel">
                    <div class="icon-badge">02</div>
                    <h5>Admin Control and Monitoring</h5>
                    <p class="mb-0 text-secondary">Admin panel handles balances, requests, KYC, and operations with direct visibility and control.</p>
                </div>
            </div>
            <div class="col-12 col-md-6 col-lg-4">
                <div class="glass-panel">
                    <div class="icon-badge">03</div>
                    <h5>Referral Engine</h5>
                    <p class="mb-0 text-secondary">Multi-level referral structure supports network expansion with clear commission mapping.</p>
                </div>
            </div>
        </div>
    </div>
</section>

<section class="section pt-0">
    <div class="container">
        <div class="glass-panel">
            <h4 class="mb-3">Core Principles</h4>
            <div class="row g-3">
                <div class="col-12 col-md-4"><strong>Security:</strong> OTP flows, CSRF checks, and strong-password policy.</div>
                <div class="col-12 col-md-4"><strong>Reliability:</strong> Daily profit and notification workflows with queue processing.</div>
                <div class="col-12 col-md-4"><strong>Scalability:</strong> Modular pages for members, admin, and compliance features.</div>
            </div>
        </div>
    </div>
</section>

<section class="section pt-0">
    <div class="container text-center">
        <h2 class="section-title">Behind The Platform</h2>
        <p class="section-subtitle">A professional operation model combining technology workflows, member support structure, and transparent administration.</p>
        <div class="row g-3 text-start">
            <div class="col-12 col-md-6 col-lg-4">
                <div class="glass-panel media-card">
                    <img src="https://images.unsplash.com/photo-1520607162513-77705c0f0d4a?auto=format&fit=crop&w=1200&q=80" alt="Team collaboration room">
                    <h5>Support Operations</h5>
                    <p class="mb-0 text-secondary">Dedicated process flow for onboarding, KYC, ticket handling, and user issue resolution.</p>
                </div>
            </div>
            <div class="col-12 col-md-6 col-lg-4">
                <div class="glass-panel media-card">
                    <img src="https://images.unsplash.com/photo-1517248135467-4c7edcad34c4?auto=format&fit=crop&w=1200&q=80" alt="Professional office operations">
                    <h5>Admin Workflow</h5>
                    <p class="mb-0 text-secondary">Structured approval layers for withdrawals, investments, and compliance monitoring.</p>
                </div>
            </div>
            <div class="col-12 col-md-6 col-lg-4">
                <div class="glass-panel media-card">
                    <img src="https://images.unsplash.com/photo-1454165804606-c3d57bc86b40?auto=format&fit=crop&w=1200&q=80" alt="Business strategy session">
                    <h5>Strategic Growth</h5>
                    <p class="mb-0 text-secondary">Long-term architecture designed around referral depth, data transparency, and controlled scaling.</p>
                </div>
            </div>
        </div>
        </div>
</section>

<footer class="site-footer py-5">
    <div class="container">
        <div class="row g-4">
            <div class="col-12 col-md-4">
                <h5 class="fw-bold mb-2">Network Platform</h5>
                <p class="mb-0 text-secondary">Professional referral and growth platform experience for mobile and desktop users.</p>
            </div>
            <div class="col-6 col-md-4">
                <h6 class="text-light">Pages</h6>
                <div class="d-grid gap-1">
                    <a href="index.php">Home</a>
                    <a href="news.php">News & Events</a>
                    <a href="contact.php">Contact</a>
                </div>
            </div>
            <div class="col-6 col-md-4">
                <h6 class="text-light">Access</h6>
                <div class="d-grid gap-1">
                    <a href="register.php">Join Now</a>
                    <a href="login.php">Member Login</a>
                    <a href="admin_dashboard.php">Admin Dashboard</a>
                </div>
            </div>
        </div>
        <hr class="border-secondary my-4">
        <small class="text-secondary">&copy; <?php echo date('Y'); ?> Network Platform. All rights reserved.</small>
    </div>
</footer>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="scroll_top.js"></script>
</body>
</html>


