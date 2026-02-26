<!DOCTYPE html>
<html lang="en">
<head>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>About | Network Platform</title>
    <style>
        :root { --primary: #1e3a8a; --secondary: #3b82f6; --dark: #0f172a; }
        body { font-family: 'Segoe UI', system-ui, sans-serif; margin: 0; color: #1e293b; background: #f8fafc; }
        .container { max-width: 1100px; margin: 0 auto; padding: 0 20px; }
        .landing-header {
            padding: 16px 30px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            width: 100%;
            background: #ffffff;
            border-bottom: 1px solid #e2e8f0;
        }
        .brand { font-weight: 800; font-size: 24px; color: var(--primary); letter-spacing: -0.5px; text-decoration: none; }
        .brand span { color: var(--secondary); font-weight: 500; }
        .header-actions { display: flex; align-items: center; gap: 12px; }
        .header-link {
            text-decoration: none;
            color: var(--dark);
            font-weight: 700;
            padding: 10px 14px;
            border-radius: 999px;
            background: #ffffff;
            border: 1px solid #e2e8f0;
        }
        .header-link.active { background: #dbeafe; border-color: #93c5fd; color: #1e3a8a; }
        .hero {
            background: linear-gradient(135deg, var(--dark) 0%, var(--primary) 100%);
            color: #ffffff;
            padding: 72px 20px;
            text-align: center;
        }
        .hero h1 { margin: 0 0 12px; font-size: clamp(34px, 5vw, 52px); }
        .hero p { margin: 0 auto; max-width: 760px; color: #cbd5e1; }
        .section { padding: 56px 0; }
        .grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(260px, 1fr));
            gap: 20px;
            margin-top: 20px;
        }
        .card {
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 16px;
            padding: 22px;
        }
        .card h3 { margin: 0 0 8px; color: #0f172a; }
        .card p { margin: 0; color: #64748b; }
        .footer {
            background: var(--dark);
            color: #ffffff;
            text-align: center;
            padding: 48px 20px;
        }
        .footer a { color: #cbd5e1; text-decoration: none; margin: 0 8px; }
        @media (max-width: 768px) {
            .landing-header { flex-direction: column; align-items: stretch; gap: 10px; padding: 12px; }
            .header-actions { display: grid; grid-template-columns: 1fr 1fr; width: 100%; }
            .header-link { text-align: center; }
        }
    </style>
    <link rel="stylesheet" href="responsive.css">
</head>
<body class="landing-page">
    <header class="landing-header">
        <a href="index.php" class="brand">NETWORK<span>PLATFORM</span></a>
        <button type="button" class="mobile-nav-toggle" aria-label="Toggle menu" aria-expanded="false">&#9776;</button>
        <div class="header-actions">
            <a href="about.php" class="header-link active">About</a>
            <a href="news.php" class="header-link">News & Events</a>
            <a href="contact.php" class="header-link">Contact</a>
            <a href="login.php" class="header-link">Login</a>
            <a href="register.php" class="header-link">Join Now</a>
        </div>
    </header>

    <section class="hero">
        <div class="container">
            <h1>About Network Platform</h1>
            <p>Network Platform is focused on daily growth plans and team-based referral expansion with a transparent user and admin dashboard experience.</p>
        </div>
    </section>

    <section class="section">
        <div class="container">
            <h2 class="page-title">What We Provide</h2>
            <div class="grid">
                <div class="card">
                    <h3>Daily Profit Model</h3>
                    <p>Members can track daily earning movement directly from their dashboards with clear wallet visibility.</p>
                </div>
                <div class="card">
                    <h3>Referral Depth System</h3>
                    <p>A structured 5-level referral system supports long-term network building and commission growth.</p>
                </div>
                <div class="card">
                    <h3>Admin Oversight</h3>
                    <p>Operational controls for approvals, balances, requests, and activity logs are centralized in admin mode.</p>
                </div>
            </div>
        </div>
    </section>

    <footer class="site-footer">
        <div class="site-footer-inner">
            <div class="site-footer-grid">
                <div>
                    <h3 class="site-footer-title">NETWORK PLATFORM</h3>
                    <p class="site-footer-text">Trusted investment workflows, daily growth model, and structured referral expansion.</p>
                </div>
                <div class="site-footer-links">
                    <a href="about.php">About</a>
                    <a href="news.php">News & Events</a>
                    <a href="contact.php">Contact Details</a>
                    <a href="register.php">Join Now</a>
                </div>
                <div class="site-footer-metrics">
                    <div>Plans: Starter and Pro</div>
                    <div>Referral Depth: 5 Levels</div>
                    <div>Support Window: 6 Days / Week</div>
                </div>
            </div>
            <div class="site-footer-copy">&copy; <?php echo date('Y'); ?> Network Platform</div>
        </div>
    </footer>
    <script>
        (function () {
            var toggles = document.querySelectorAll('.mobile-nav-toggle');
            toggles.forEach(function (btn) {
                var header = btn.closest('.landing-header, .main-header, .header');
                if (!header) return;
                btn.addEventListener('click', function (e) {
                    e.stopPropagation();
                    var isOpen = header.classList.toggle('nav-open');
                    btn.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
                });
                header.querySelectorAll('a').forEach(function (link) {
                    link.addEventListener('click', function () {
                        header.classList.remove('nav-open');
                        btn.setAttribute('aria-expanded', 'false');
                    });
                });
            });
            document.addEventListener('click', function (e) {
                toggles.forEach(function (btn) {
                    var header = btn.closest('.landing-header, .main-header, .header');
                    if (!header || header.contains(e.target)) return;
                    header.classList.remove('nav-open');
                    btn.setAttribute('aria-expanded', 'false');
                });
            });
        })();
    </script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="scroll_top.js"></script>
</body>
</html>
