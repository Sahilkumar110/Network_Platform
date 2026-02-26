<!DOCTYPE html>
<html lang="en">
<head>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>News & Events | Network Platform</title>
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
        .news-list {
            display: grid;
            gap: 16px;
        }
        .news-card {
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 16px;
            padding: 20px;
        }
        .news-meta {
            color: #64748b;
            font-size: 13px;
            margin-bottom: 8px;
            font-weight: 700;
        }
        .news-card h3 {
            margin: 0 0 8px;
            color: #0f172a;
        }
        .news-card p {
            margin: 0;
            color: #64748b;
        }
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
            <a href="about.php" class="header-link">About</a>
            <a href="news.php" class="header-link active">News & Events</a>
            <a href="contact.php" class="header-link">Contact</a>
            <a href="login.php" class="header-link">Login</a>
            <a href="register.php" class="header-link">Join Now</a>
        </div>
    </header>

    <section class="hero">
        <div class="container">
            <h1>News & Events</h1>
            <p>Latest platform updates, roadmap milestones, and important notices for members and admins.</p>
        </div>
    </section>

    <section class="section">
        <div class="container">
            <h2 class="page-title">Latest Updates</h2>
            <div class="news-list">
                <article class="news-card">
                    <div class="news-meta">February 2026 | Platform Update</div>
                    <h3>Improved Mobile Dashboard Experience</h3>
                    <p>We have upgraded dashboard responsiveness for smoother card, table, and navigation behavior on phones and tablets.</p>
                </article>
                <article class="news-card">
                    <div class="news-meta">February 2026 | Admin Enhancement</div>
                    <h3>User List Now Supports Mobile Expand View</h3>
                    <p>Admin user list now starts compact and expands on tap, helping manage member records faster on smaller screens.</p>
                </article>
                <article class="news-card">
                    <div class="news-meta">January 2026 | System Notice</div>
                    <h3>Referral Tracking and Profit Job Stabilization</h3>
                    <p>Referral and daily profit processing has been reinforced for clearer accounting and reliable distribution logs.</p>
                </article>
            </div>
        </div>
    </section>

    <footer class="site-footer">
        <div class="site-footer-inner">
            <div class="site-footer-grid">
                <div>
                    <h3 class="site-footer-title">NETWORK PLATFORM</h3>
                    <p class="site-footer-text">Follow platform updates, product enhancements, and admin operation notices.</p>
                </div>
                <div class="site-footer-links">
                    <a href="about.php">About</a>
                    <a href="news.php">News & Events</a>
                    <a href="contact.php">Contact Details</a>
                    <a href="dashboard.php">Member Dashboard</a>
                </div>
                <div class="site-footer-metrics">
                    <div>News Feed: Product + Ops</div>
                    <div>Focus: Reliability and Transparency</div>
                    <div>Cycle: Ongoing Improvements</div>
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
