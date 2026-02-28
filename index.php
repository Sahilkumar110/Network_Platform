<?php
include 'db.php';

try {
    $total_members = (int)$pdo->query("SELECT COUNT(id) FROM users")->fetchColumn();
    $total_paid = (float)($pdo->query("SELECT SUM(amount) FROM withdrawals WHERE status = 'approved'")->fetchColumn() ?? 0);
} catch (Exception $e) {
    $total_members = 0;
    $total_paid = 0;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Network Platform | Smart Growth Network</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="responsive.css">
    <style>
        :root {
            --brand-dark: #0f172a;
            --brand-mid: #1e3a8a;
            --brand-accent: #3b82f6;
            --brand-soft: #eaf2ff;
            --text-soft: #cbd5e1;
        }
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
        body.landing-page { margin-top: 0 !important; }
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
            color: var(--brand-mid);
            text-decoration: none;
        }
        .brand span {
            color: var(--brand-accent);
            font-weight: 500;
        }
        .nav-link {
            font-weight: 700;
            color: #1f2937 !important;
            border-radius: 10px;
            padding: 8px 12px !important;
        }
        .nav-link:hover {
            background: #eef4ff;
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
            min-height: 88vh;
            display: flex;
            align-items: center;
            position: relative;
            overflow: hidden;
            background:
                linear-gradient(110deg, rgba(15, 23, 42, 0.92), rgba(30, 58, 138, 0.82)),
                url("https://images.unsplash.com/photo-1486406146926-c627a92ad1ab?auto=format&fit=crop&w=1800&q=80") center/cover no-repeat;
        }
        .public-main {
            padding-top: 76px;
        }
        .media-card img {
            width: 100%;
            height: 220px;
            object-fit: cover;
            border-radius: 14px;
            border: 1px solid #dbeafe;
            margin-bottom: 12px;
        }
.hero::after {
            content: "";
            position: absolute;
            inset: auto -20% -190px -20%;
            height: 280px;
            background: radial-gradient(closest-side, rgba(59, 130, 246, 0.35), transparent);
        }
        .hero-inner {
            position: relative;
            z-index: 2;
            color: #fff;
        }
        .hero-kicker {
            font-size: 0.8rem;
            letter-spacing: 0.16em;
            text-transform: uppercase;
            color: #93c5fd;
            font-weight: 700;
            margin-bottom: 12px;
        }
        .hero h1 {
            font-size: clamp(2rem, 5vw, 4.2rem);
            line-height: 1.02;
            letter-spacing: -0.04em;
            margin-bottom: 18px;
            max-width: 860px;
        }
        .hero p {
            font-size: clamp(1rem, 2vw, 1.2rem);
            color: var(--text-soft);
            max-width: 760px;
            margin-bottom: 28px;
        }
        .hero-actions {
            display: flex;
            gap: 12px;
            flex-wrap: wrap;
        }
        .hero-btn {
            border-radius: 12px;
            padding: 12px 20px;
            font-weight: 700;
            text-decoration: none;
        }
        .hero-btn-primary {
            background: #3b82f6;
            color: #fff;
            border: 1px solid #3b82f6;
        }
        .hero-btn-ghost {
            background: rgba(255, 255, 255, 0.08);
            border: 1px solid rgba(255, 255, 255, 0.35);
            color: #fff;
        }
        .stat-strip {
            margin-top: -42px;
            position: relative;
            z-index: 10;
        }
        .stat-card {
            background: #fff;
            border: 1px solid #e2e8f0;
            border-radius: 16px;
            box-shadow: 0 14px 28px rgba(15, 23, 42, 0.1);
            padding: 18px;
            height: 100%;
        }
        .stat-label {
            margin: 0 0 6px;
            font-size: 0.75rem;
            text-transform: uppercase;
            letter-spacing: 0.08em;
            color: #64748b;
            font-weight: 700;
        }
        .stat-value {
            margin: 0;
            font-size: clamp(1.4rem, 2.4vw, 2rem);
            font-weight: 800;
            color: #0f172a;
        }
        .section {
            padding: 72px 0;
        }
        .section-title {
            font-size: clamp(1.8rem, 3.4vw, 2.6rem);
            letter-spacing: -0.03em;
            margin-bottom: 10px;
            font-weight: 800;
        }
        .section-subtitle {
            color: #64748b;
            max-width: 760px;
            margin: 0 auto 28px;
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
            background: var(--brand-soft);
            color: var(--brand-mid);
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 1.2rem;
            margin-bottom: 12px;
            font-weight: 800;
        }
        .plan {
            background: linear-gradient(180deg, #ffffff, #f8fbff);
        }
        .plan .rate {
            font-size: 2rem;
            font-weight: 800;
            color: #0f172a;
            margin: 10px 0 14px;
        }
        .plan ul {
            padding-left: 18px;
            color: #475569;
            margin-bottom: 18px;
        }
        .plan .btn {
            border-radius: 10px;
            font-weight: 700;
        }
        .cta-band {
            background: linear-gradient(120deg, #0f172a, #1e3a8a);
            border-radius: 24px;
            color: #fff;
            padding: 34px 24px;
            border: 1px solid #1e3a8a;
            box-shadow: 0 22px 38px rgba(2, 6, 23, 0.24);
        }
        .cta-band p {
            color: #dbeafe;
            margin: 0;
        }
        .site-footer {
            margin-top: 56px;
            background: #0b1220;
            color: #dbe4f3;
            border-top: 1px solid #23324d;
        }
        .site-footer a {
            color: #c7d9ff;
            text-decoration: none;
        }
        .site-footer a:hover {
            color: #ffffff;
        }
        @media (max-width: 991.98px) {
            .public-main { padding-top: 70px; }
            .hero {
                min-height: 76vh;
                background-position: 58% center;
            }
            .stat-strip {
                margin-top: 16px;
            }
            .section {
                padding: 56px 0;
            }
        }
    </style>
</head>
<body class="landing-page">

<header class="topbar" style="position:fixed;top:0;left:0;right:0;z-index:5000;">
    <nav class="navbar navbar-expand-lg py-2">
        <div class="container">
            <a class="brand" href="index.php">NETWORK<span>PLATFORM</span></a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#mainNav" aria-controls="mainNav" aria-expanded="false" aria-label="Toggle navigation">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="mainNav">
                <ul class="navbar-nav ms-auto align-items-lg-center gap-lg-1">
                    <li class="nav-item"><a class="nav-link" href="about.php">About</a></li>
                    <li class="nav-item"><a class="nav-link" href="news.php">News & Events</a></li>
                    <li class="nav-item"><a class="nav-link" href="contact.php">Contact</a></li>
                    <li class="nav-item"><a class="nav-link" href="login.php">Login</a></li>
                    <li class="nav-item ms-lg-2"><a class="btn-join mt-2 mt-lg-0" href="register.php">Join Now</a></li>
                </ul>
            </div>
        </div>
    </nav>
</header>

<main class="public-main">
<section class="hero">
    <div class="container hero-inner">
        <div class="hero-kicker">Smart Referral Finance</div>
        <h1>Build a Strong Network. Track Daily Growth. Withdraw with Clarity.</h1>
        <p>Network Platform combines fixed daily profit logic with a 5-level referral structure, giving users transparent growth and admins complete operational control.</p>
        <div class="hero-actions">
            <a href="register.php" class="hero-btn hero-btn-primary">Get Started</a>
            <a href="login.php" class="hero-btn hero-btn-ghost">Member Login</a>
        </div>
    </div>
</section>

<section class="stat-strip pb-2">
    <div class="container">
        <div class="row g-3">
            <div class="col-12 col-md-6 col-xl-3">
                <div class="stat-card">
                    <p class="stat-label">Registered Members</p>
                    <p class="stat-value"><?php echo number_format($total_members); ?>+</p>
                </div>
            </div>
            <div class="col-12 col-md-6 col-xl-3">
                <div class="stat-card">
                    <p class="stat-label">Approved Withdrawals</p>
                    <p class="stat-value">$<?php echo number_format($total_paid, 2); ?></p>
                </div>
            </div>
            <div class="col-12 col-md-6 col-xl-3">
                <div class="stat-card">
                    <p class="stat-label">Daily Profit</p>
                    <p class="stat-value">1.0% Fixed</p>
                </div>
            </div>
            <div class="col-12 col-md-6 col-xl-3">
                <div class="stat-card">
                    <p class="stat-label">Referral Depth</p>
                    <p class="stat-value">5 Levels</p>
                </div>
            </div>
        </div>
    </div>
</section>

<section class="section">
    <div class="container text-center">
        <h2 class="section-title">How The Platform Works</h2>
        <p class="section-subtitle">Simple onboarding, transparent earning model, and clear admin moderation. Designed for real users on both desktop and mobile.</p>
        <div class="row g-3 text-start">
            <div class="col-12 col-md-6 col-lg-3">
                <div class="glass-panel">
                    <div class="icon-badge">1</div>
                    <h5>Create Account</h5>
                    <p class="mb-0 text-secondary">Register using email OTP verification and secure password rules.</p>
                </div>
            </div>
            <div class="col-12 col-md-6 col-lg-3">
                <div class="glass-panel">
                    <div class="icon-badge">2</div>
                    <h5>Activate Balance</h5>
                    <p class="mb-0 text-secondary">Admin can fund user balance, and users can begin activity.</p>
                </div>
            </div>
            <div class="col-12 col-md-6 col-lg-3">
                <div class="glass-panel">
                    <div class="icon-badge">3</div>
                    <h5>Earn Daily Profit</h5>
                    <p class="mb-0 text-secondary">Daily cron adds fixed 1% according to platform profit rules.</p>
                </div>
            </div>
            <div class="col-12 col-md-6 col-lg-3">
                <div class="glass-panel">
                    <div class="icon-badge">4</div>
                    <h5>Grow Team</h5>
                    <p class="mb-0 text-secondary">Referral tree pays commissions from L1 to L5 levels.</p>
                </div>
            </div>
        </div>
    </div>
</section>

<section class="section pt-0">
    <div class="container">
        <div class="row g-3">
            <div class="col-12 col-lg-6">
                <div class="glass-panel plan">
                    <h4>Starter Plan</h4>
                    <div class="rate">1.0% / Day</div>
                    <ul>
                        <li>Daily fixed growth model</li>
                        <li>Referral commissions enabled</li>
                        <li>User dashboard + support tools</li>
                    </ul>
                    <a href="register.php" class="btn btn-primary">Choose Starter</a>
                </div>
            </div>
            <div class="col-12 col-lg-6">
                <div class="glass-panel plan">
                    <h4>Network Builder</h4>
                    <div class="rate">L1 5% to L5 1%</div>
                    <ul>
                        <li>L1 5%, L2 4%, L3 3%</li>
                        <li>L4 2%, L5 1% commissions</li>
                        <li>Live referral and wallet tracking</li>
                    </ul>
                    <a href="register.php" class="btn btn-outline-primary">Build Team</a>
                </div>
            </div>
        </div>
    </div>
</section>

<section class="section pt-0">
    <div class="container">
        <div class="cta-band">
            <div class="row align-items-center g-3">
                <div class="col-12 col-lg-8">
                    <h3 class="mb-2">Ready to launch your referral growth network?</h3>
                    <p>Join the platform, verify account quickly, and start using a complete member + admin ecosystem.</p>
                </div>
                <div class="col-12 col-lg-4 text-lg-end">
                    <a href="register.php" class="btn btn-light fw-bold me-2">Create Account</a>
                    <a href="login.php" class="btn btn-outline-light fw-bold">Login</a>
                </div>
            </div>
        </div>
    </div>
</section>

<section class="section pt-0">
    <div class="container text-center">
        <h2 class="section-title">Professional Platform Highlights</h2>
        <p class="section-subtitle">A modern investment and referral ecosystem with clear dashboards, transparent records, and fast operations.</p>
        <div class="row g-3 text-start">
            <div class="col-12 col-md-6 col-lg-4">
                <div class="glass-panel media-card">
                    <img src="https://images.unsplash.com/photo-1553729459-efe14ef6055d?auto=format&fit=crop&w=1200&q=80" alt="Financial planning workspace">
                    <h5>Transparent Financial Tracking</h5>
                    <p class="mb-0 text-secondary">Users and admins can review balances, payouts, and referral growth through structured data views.</p>
                </div>
            </div>
            <div class="col-12 col-md-6 col-lg-4">
                <div class="glass-panel media-card">
                    <img src="https://images.unsplash.com/photo-1551288049-bebda4e38f71?auto=format&fit=crop&w=1200&q=80" alt="Analytics dashboard display">
                    <h5>Analytics-Friendly Dashboard</h5>
                    <p class="mb-0 text-secondary">Designed for quick decision-making with concise metrics, card-based summaries, and responsive layouts.</p>
                </div>
            </div>
            <div class="col-12 col-md-6 col-lg-4">
                <div class="glass-panel media-card">
                    <img src="https://images.unsplash.com/photo-1520607162513-77705c0f0d4a?auto=format&fit=crop&w=1200&q=80" alt="Business collaboration meeting">
                    <h5>Referral Network Expansion</h5>
                    <p class="mb-0 text-secondary">Structured level commissions support long-term team growth and measurable network performance.</p>
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
                <p class="mb-0 text-secondary">A responsive referral and profit platform with clear dashboards and operational controls.</p>
            </div>
            <div class="col-6 col-md-4">
                <h6 class="text-light">Pages</h6>
                <div class="d-grid gap-1">
                    <a href="about.php">About</a>
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
        <div class="d-flex flex-column flex-md-row justify-content-between gap-2">
            <small class="text-secondary">&copy; <?php echo date('Y'); ?> Network Platform. All rights reserved.</small>
            <small class="text-secondary">Designed for desktop, tablet, and mobile responsive usage.</small>
        </div>
    </div>
</footer>
</main>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="scroll_top.js"></script>
</body>
</html>


