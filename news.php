<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>News & Events | Network Platform</title>
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
            min-height: 44vh;
            display: flex;
            align-items: center;
            background:
                linear-gradient(110deg, rgba(15, 23, 42, 0.9), rgba(30, 58, 138, 0.8)),
                url("https://images.unsplash.com/photo-1557426272-fc759fdf7a8d?auto=format&fit=crop&w=1800&q=80") center/cover no-repeat;
            color: #fff;
        }
        .hero h1 {
            font-size: clamp(2rem, 5vw, 3.3rem);
            line-height: 1.04;
            letter-spacing: -0.03em;
            margin-bottom: 10px;
        }
        .hero p {
            color: #dbeafe;
            max-width: 740px;
            margin-bottom: 0;
            font-size: 1.05rem;
        }
        .section { padding: 72px 0; }
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
        .news-card {
            background: #fff;
            border: 1px solid #e2e8f0;
            border-radius: 18px;
            box-shadow: 0 10px 24px rgba(15, 23, 42, 0.06);
            padding: 22px;
            height: 100%;
        }
        .news-meta {
            display: inline-block;
            font-size: 12px;
            font-weight: 700;
            letter-spacing: 0.06em;
            text-transform: uppercase;
            color: #1d4ed8;
            background: #eff6ff;
            border: 1px solid #bfdbfe;
            border-radius: 999px;
            padding: 4px 10px;
            margin-bottom: 10px;
        }
        .news-card p {
            color: #64748b;
            margin: 0;
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
            .hero { min-height: 40vh; }
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
                    <li class="nav-item"><a class="nav-link" href="about.php">About</a></li>
                    <li class="nav-item"><a class="nav-link active" href="news.php">News & Events</a></li>
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
        <h1>News & Events</h1>
        <p>Recent improvements, operational updates, and important announcements for members and administrators.</p>
    </div>
</section>

<section class="section">
    <div class="container text-center">
        <h2 class="section-title">Latest Announcements</h2>
        <p class="section-subtitle">Stay updated with platform enhancements, stability improvements, and feature rollouts.</p>
        <div class="row g-3 text-start">
            <div class="col-12 col-md-6">
                <article class="news-card">
                    <span class="news-meta">Platform</span>
                    <h5>Registration OTP Verification Enabled</h5>
                    <p>New member accounts now require OTP email confirmation before account creation to improve security and reduce fake signups.</p>
                </article>
            </div>
            <div class="col-12 col-md-6">
                <article class="news-card">
                    <span class="news-meta">Security</span>
                    <h5>Strong Password Policy Activated</h5>
                    <p>Password rules now enforce uppercase, lowercase, number, and special character requirements for improved account safety.</p>
                </article>
            </div>
            <div class="col-12 col-md-6">
                <article class="news-card">
                    <span class="news-meta">Mobile UI</span>
                    <h5>Responsive Dashboard Navigation Improved</h5>
                    <p>Navbar and action controls are now more usable on small screens with cleaner structure and better visual hierarchy.</p>
                </article>
            </div>
            <div class="col-12 col-md-6">
                <article class="news-card">
                    <span class="news-meta">System</span>
                    <h5>SMTP-Based OTP Delivery Added</h5>
                    <p>Email OTPs are delivered through SMTP-backed queue processing with immediate dispatch support for real-time user verification.</p>
                </article>
            </div>
        </div>
    </div>
</section>

<section class="section pt-0">
    <div class="container text-center">
        <h2 class="section-title">Media Updates</h2>
        <p class="section-subtitle">Explore visual highlights and communication updates relevant to platform members and stakeholders.</p>
        <div class="row g-3 text-start">
            <div class="col-12 col-md-6 col-lg-4">
                <div class="news-card media-card">
                    <img src="https://images.unsplash.com/photo-1552664730-d307ca884978?auto=format&fit=crop&w=1200&q=80" alt="Corporate update briefing">
                    <h5>Quarterly Performance Briefing</h5>
                    <p>Ongoing effort to improve clarity in dashboard metrics and user reporting transparency.</p>
                </div>
            </div>
            <div class="col-12 col-md-6 col-lg-4">
                <div class="news-card media-card">
                    <img src="https://images.unsplash.com/photo-1460925895917-afdab827c52f?auto=format&fit=crop&w=1200&q=80" alt="Data and analytics boards">
                    <h5>Data Visibility Improvements</h5>
                    <p>Enhanced card layout and structured admin views deliver faster decision support across operations.</p>
                </div>
            </div>
            <div class="col-12 col-md-6 col-lg-4">
                <div class="news-card media-card">
                    <img src="https://images.unsplash.com/photo-1520607162513-77705c0f0d4a?auto=format&fit=crop&w=1200&q=80" alt="Team coordination">
                    <h5>Member Experience Enhancements</h5>
                    <p>Usability updates on mobile and desktop to keep navigation and actions clean and predictable.</p>
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
                <p class="mb-0 text-secondary">Product updates and system improvements for all members and operators.</p>
            </div>
            <div class="col-6 col-md-4">
                <h6 class="text-light">Pages</h6>
                <div class="d-grid gap-1">
                    <a href="index.php">Home</a>
                    <a href="about.php">About</a>
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


