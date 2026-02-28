<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Contact | Network Platform</title>
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
                url("https://images.unsplash.com/photo-1521791136064-7986c2920216?auto=format&fit=crop&w=1800&q=80") center/cover no-repeat;
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
        .contact-card {
            background: #fff;
            border: 1px solid #e2e8f0;
            border-radius: 18px;
            box-shadow: 0 10px 24px rgba(15, 23, 42, 0.06);
            padding: 22px;
            height: 100%;
        }
        .contact-label {
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
        .contact-value {
            font-weight: 700;
            color: #0f172a;
            margin-bottom: 0;
        }
        .tip-box {
            margin-top: 18px;
            background: #eaf2ff;
            border: 1px solid #bfdbfe;
            border-radius: 14px;
            padding: 14px;
            color: #1e3a8a;
            font-weight: 600;
        }
        .media-card img {
            width: 100%;
            height: 220px;
            object-fit: cover;
            border-radius: 14px;
            border: 1px solid #dbeafe;
            margin-bottom: 12px;
        }
.map-frame {
            width: 100%;
            min-height: 360px;
            border: 1px solid #dbeafe;
            border-radius: 16px;
            overflow: hidden;
            box-shadow: 0 10px 24px rgba(15, 23, 42, 0.08);
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
                    <li class="nav-item"><a class="nav-link" href="news.php">News & Events</a></li>
                    <li class="nav-item"><a class="nav-link active" href="contact.php">Contact</a></li>
                    <li class="nav-item"><a class="nav-link" href="login.php">Login</a></li>
                    <li class="nav-item ms-lg-2"><a class="btn-join mt-2 mt-lg-0" href="register.php">Join Now</a></li>
                </ul>
            </div>
        </div>
    </nav>
</header>

<section class="hero">
    <div class="container">
        <h1>Contact Network Platform</h1>
        <p>For account support, withdrawals, KYC, and technical help, reach us through the official channels below.</p>
    </div>
</section>

<section class="section">
    <div class="container text-center">
        <h2 class="section-title">Support Channels</h2>
        <p class="section-subtitle">Use registered email and user ID for faster response from our operations team.</p>
        <div class="row g-3 text-start">
            <div class="col-12 col-md-6">
                <div class="contact-card">
                    <span class="contact-label">Support Email</span>
                    <p class="contact-value">support@networkplatform.com</p>
                </div>
            </div>
            <div class="col-12 col-md-6">
                <div class="contact-card">
                    <span class="contact-label">Phone</span>
                    <p class="contact-value">+92 331 3615520</p>
                    <p class="contact-value">+92 334 8596114</p>
                </div>
            </div>
            <div class="col-12 col-md-6">
                <div class="contact-card">
                    <span class="contact-label">Office Address</span>
                    <p class="contact-value">Kiran Hospital Rd, Gulzar-e-Hijri Rizwan CHS, Scheme 33, Karachi, Pakistan.</p>
                </div>
            </div>
            <div class="col-12 col-md-6">
                <div class="contact-card">
                    <span class="contact-label">Working Hours</span>
                    <p class="contact-value">Monday - Saturday, 9:00 AM - 8:00 PM (UTC)</p>
                </div>
            </div>
        </div>
        <div class="tip-box">
            Tip: For password reset or OTP issues, also mention approximate time and your login email so we can trace quickly.
        </div>
    </div>
</section>

<section class="section pt-0">
    <div class="container text-center">
        <h2 class="section-title">Contact Team & Location</h2>
        <p class="section-subtitle">Our operations and support channels are mapped for better accessibility and faster communication.</p>
        <div class="row g-3 text-start">
            <div class="col-12 col-md-6 col-lg-4">
                <div class="contact-card media-card">
                    <img src="https://images.unsplash.com/photo-1521791136064-7986c2920216?auto=format&fit=crop&w=1200&q=80" alt="Customer support call center">
                    <h5>Responsive Support Desk</h5>
                    <p class="mb-0 text-secondary">Support team handles login, verification, withdrawal, and profile issues with priority routing.</p>
                </div>
            </div>
            <div class="col-12 col-md-6 col-lg-4">
                <div class="contact-card media-card">
                    <img src="https://images.unsplash.com/photo-1486406146926-c627a92ad1ab?auto=format&fit=crop&w=1200&q=80" alt="Office building">
                    <h5>Office Presence</h5>
                    <p class="mb-0 text-secondary">Professional operations from a centralized location to maintain service continuity.</p>
                </div>
            </div>
            <div class="col-12 col-md-6 col-lg-4">
                <div class="contact-card">
                    <h5 class="mb-3">Support Response Standards</h5>
                    <p class="mb-2 text-secondary">For faster support, share your user ID, registered email, and issue time in one message.</p>
                    <p class="mb-0 text-secondary">Priority is given to account access, withdrawal processing, and OTP verification issues.</p>
                </div>
            </div>
        </div>
    </div>
</section>

<section class="section pt-0">
    <div class="container">
        <h2 class="section-title text-center">Find Us on Map</h2>
        <p class="section-subtitle text-center">Kiran Hospital Road, Gulzar-e-Hijri, Scheme 33, Karachi.</p>
        <div class="map-frame">
            <iframe
                src="https://www.google.com/maps?q=Kiran+Hospital+Road,+Gulzar-e-Hijri,+Scheme+33,+Karachi&output=embed"
                width="100%"
                height="380"
                style="border:0;"
                allowfullscreen=""
                loading="lazy"
                referrerpolicy="no-referrer-when-downgrade"
                title="Network Platform office map">
            </iframe>
        </div>
    </div>
</section>

<footer class="site-footer py-5">
    <div class="container">
        <div class="row g-4">
            <div class="col-12 col-md-4">
                <h5 class="fw-bold mb-2">Network Platform</h5>
                <p class="mb-0 text-secondary">Reach our support team for account, security, and operations assistance.</p>
            </div>
            <div class="col-6 col-md-4">
                <h6 class="text-light">Pages</h6>
                <div class="d-grid gap-1">
                    <a href="index.php">Home</a>
                    <a href="about.php">About</a>
                    <a href="news.php">News & Events</a>
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


