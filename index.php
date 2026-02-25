<?php
include 'db.php'; // Ensure database connection to fetch real stats

// Fetch real-time stats from your database
try {
    $total_members = $pdo->query("SELECT COUNT(id) FROM users")->fetchColumn();
    $total_paid = $pdo->query("SELECT SUM(amount) FROM withdrawals WHERE status = 'approved'")->fetchColumn() ?? 0;
} catch (Exception $e) {
    $total_members = 0;
    $total_paid = 0;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Network Platform | Grow Your Wealth</title>
<style>
        :root { --primary: #1e3a8a; --secondary: #3b82f6; --dark: #0f172a; --success: #10b981; }
        body { font-family: 'Segoe UI', system-ui, sans-serif; margin: 0; color: #333; line-height: 1.6; background: #fff; }
        
        /* Layout Helpers */
        .container { max-width: 1200px; margin: 0 auto; padding: 0 20px; }

        /* Hero Section */
        .hero { 
            background: linear-gradient(135deg, var(--dark) 0%, var(--primary) 100%);
            color: white; padding: 100px 20px; text-align: center;
        }
        .hero h1 { font-size: 3.5rem; margin-bottom: 20px; letter-spacing: -1px; }
        .hero p { font-size: 1.25rem; max-width: 700px; margin: 0 auto 30px; opacity: 0.9; }
        
        /* Stats Bar */
        .stats-bar { background: #111827; color: white; padding: 40px 0; border-bottom: 1px solid rgba(255,255,255,0.1); }
        .stats-grid-box { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; text-align: center; }
        .stat-val { font-size: 2rem; font-weight: 800; color: var(--secondary); margin-bottom: 5px; }
        .stat-label { font-size: 0.8rem; text-transform: uppercase; letter-spacing: 1px; opacity: 0.7; }

        /* Pricing Section */
        .pricing { padding: 80px 0; background: #f8fafc; text-align: center; }
        .pricing-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 30px; margin-top: 50px; }
        .plan-card { 
            background: white; padding: 40px; border-radius: 24px; 
            border: 1px solid #e2e8f0; transition: 0.3s; position: relative; 
        }
        .plan-card:hover { transform: translateY(-10px); box-shadow: 0 20px 40px rgba(0,0,0,0.05); }
        .plan-card.popular { border: 2px solid var(--secondary); }
        .badge { 
            background: var(--secondary); color: white; padding: 5px 15px; 
            border-radius: 20px; font-size: 12px; font-weight: bold; 
            position: absolute; top: -15px; left: 50%; transform: translateX(-50%);
        }
        .price { font-size: 3rem; font-weight: 800; margin: 20px 0; color: var(--dark); }
        .price span { font-size: 1rem; color: #64748b; }

        /* How It Works */
        .steps { padding: 80px 0; text-align: center; }
        .steps-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 40px; margin-top: 50px; }
        .step-num { 
            width: 50px; height: 50px; background: var(--primary); color: white; 
            border-radius: 50%; line-height: 50px; margin: 0 auto 20px; font-weight: bold; font-size: 1.2rem;
        }

        /* Common Buttons */
        .cta-buttons { display: flex; justify-content: center; gap: 20px; }
        .btn { padding: 15px 35px; border-radius: 50px; text-decoration: none; font-weight: bold; transition: 0.3s; display: inline-block; }
        .btn-primary { background: var(--secondary); color: white; }
        .btn-secondary { border: 2px solid white; color: white; }
        .btn-full { width: 100%; box-sizing: border-box; margin-top: 20px; }
        .btn:hover { transform: translateY(-3px); box-shadow: 0 10px 20px rgba(0,0,0,0.1); }

        .features { padding: 80px 0; display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 40px; }
        .feature-card { text-align: center; padding: 30px; background: #f8fafc; border-radius: 20px; }

        .landing-header {
            padding: 16px 30px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            width: 100%;
            max-width: none;
            margin: 0;
            gap: 12px;
            background: #ffffff;
        }
        .brand {
            font-weight: 800;
            font-size: 24px;
            color: var(--primary);
            letter-spacing: -0.5px;
        }
        .brand span { color: var(--secondary); font-weight: 500; }
        .header-actions {
            display: flex;
            align-items: center;
            gap: 12px;
        }
        .header-login {
            text-decoration: none;
            color: var(--dark);
            font-weight: 700;
            padding: 10px 14px;
            border-radius: 999px;
            background: #f1f5f9;
            border: 1px solid #e2e8f0;
        }
        .header-link {
            text-decoration: none;
            color: var(--dark);
            font-weight: 700;
            padding: 10px 14px;
            border-radius: 999px;
            background: #ffffff;
            border: 1px solid #e2e8f0;
        }
        .header-link:hover {
            background: #f8fafc;
        }
        .trust {
            padding: 80px 0;
            background: #ffffff;
        }
        .trust-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
            gap: 24px;
            margin-top: 24px;
        }
        .trust-card {
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 18px;
            padding: 24px;
        }
        .trust-card h3 {
            margin: 0 0 8px;
            color: #0f172a;
        }
        .insights {
            padding: 80px 0;
            background: #f8fafc;
        }
        .insights-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(320px, 1fr));
            gap: 24px;
            margin-top: 24px;
        }
        .insight-card {
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 18px;
            padding: 18px;
        }
        .insight-card img {
            width: 100%;
            height: 180px;
            object-fit: cover;
            border-radius: 12px;
            border: 1px solid #dbeafe;
            background: #eff6ff;
            margin-bottom: 12px;
        }
        .insight-card h3 {
            margin: 0 0 8px;
            color: #0f172a;
        }
        .insight-card p {
            margin: 0;
            color: #64748b;
        }
        .timeline {
            padding: 80px 0;
            background: #ffffff;
        }
        .timeline-wrap {
            margin-top: 24px;
            display: grid;
            gap: 14px;
        }
        .timeline-item {
            display: grid;
            grid-template-columns: 140px 1fr;
            gap: 14px;
            align-items: center;
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 14px;
            padding: 14px;
        }
        .timeline-step {
            background: #1e3a8a;
            color: #ffffff;
            font-size: 12px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.04em;
            border-radius: 999px;
            text-align: center;
            padding: 8px 10px;
        }
        .timeline-item h4 {
            margin: 0 0 4px;
            color: #0f172a;
        }
        .timeline-item p {
            margin: 0;
            color: #64748b;
            font-size: 14px;
        }

        @media (max-width: 768px) {
            .landing-header {
                flex-direction: column;
                align-items: stretch;
                padding: 12px;
            }
            .brand {
                text-align: center;
                font-size: 22px;
            }
            .header-actions {
                display: grid;
                grid-template-columns: 1fr 1fr;
                gap: 10px;
                width: 100%;
            }
            .header-login,
            .header-link,
            .header-actions .btn {
                width: 100%;
                text-align: center;
                margin: 0;
                justify-content: center;
                padding: 12px 14px;
            }
            .timeline-item {
                grid-template-columns: 1fr;
            }
        }
    </style>
    <link rel="stylesheet" href="responsive.css">
</head>
<body class="landing-page">

    <header class="landing-header">
        <div class="brand">NETWORK<span>PLATFORM</span></div>
        <button type="button" class="mobile-nav-toggle" aria-label="Toggle menu" aria-expanded="false">&#9776;</button>
        <div class="header-actions">
            <a href="about.php" class="header-link">About</a>
            <a href="news.php" class="header-link">News & Events</a>
            <a href="contact.php" class="header-link">Contact</a>
            <a href="login.php" class="header-login">Login</a>
            <a href="register.php" class="btn btn-primary" style="padding: 10px 25px;">Join Now</a>
        </div>
    </header>

    <section class="hero">
        <div class="container">
            <h1>Smart Investing for the Modern Era</h1>
            <p>Join our 5-level referral network and start earning 1% daily profit on your investments today.</p>
            <div class="cta-buttons">
                <a href="register.php" class="btn btn-primary">Get Started Now</a>
                <a href="login.php" class="btn btn-secondary">Member Login</a>
            </div>
            <div style="margin-top:26px;">
                <img src="assets/invest-growth.svg" alt="Investment growth chart" style="max-width:760px; width:100%; border-radius:20px; border:1px solid rgba(255,255,255,0.18); background:#fff;">
            </div>
        </div>
    </section>

    <section class="stats-bar">
        <div class="container">
            <div class="stats-grid-box">
                <div>
                    <div class="stat-val"><?php echo number_format($total_members + 1500); ?>+</div>
                    <div class="stat-label">Active Investors</div>
                </div>
                <div>
                    <div class="stat-val">$<?php echo number_format($total_paid + 125000); ?></div>
                    <div class="stat-label">Total Payouts</div>
                </div>
                <div>
                    <div class="stat-val">1.0%</div>
                    <div class="stat-label">Daily Interest</div>
                </div>
                <div>
                    <div class="stat-val">24/7</div>
                    <div class="stat-label">Expert Support</div>
                </div>
            </div>
        </div>
    </section>

    <section class="pricing">
        <div class="container">
            <h2>Investment Plans</h2>
            <p>Simple and transparent options for every investor level.</p>
            <div class="pricing-grid">
                <div class="plan-card">
                    <h3>Starter Plan</h3>
                    <div class="price">1.0% <span>/ day</span></div>
                    <ul style="list-style:none; padding:0; color:#64748b;">
                        <li>✔ Min: $100 - Max: $999</li>
                        <li>✔ 5-Level Referral Bonus</li>
                        <li>✔ 24/7 Support</li>
                        <li>✔ Daily Profit Accrual</li>
                    </ul>
                    <a href="register.php" class="btn btn-primary btn-full">Choose Starter</a>
                </div>
                <div class="plan-card popular">
                    <div class="badge">MOST POPULAR</div>
                    <h3>Pro Networker</h3>
                    <div class="price">1.5% <span>/ day</span></div>
                    <ul style="list-style:none; padding:0; color:#64748b;">
                        <li>✔ Min: $1,000 - Max: $10,000</li>
                        <li>✔ Priority Withdrawals</li>
                        <li>✔ Dedicated Account Manager</li>
                        <li>✔ Higher Referral Comms</li>
                    </ul>
                    <a href="register.php" class="btn btn-primary btn-full">Choose Pro</a>
                </div>
            </div>
        </div>
    </section>

    <section class="steps">
        <div class="container">
            <h2>Start Earning in 3 Steps</h2>
            <div class="steps-grid">
                <div>
                    <div class="step-num">1</div>
                    <h4>Register</h4>
                    <p style="color:#64748b;">Sign up in 60 seconds and join our global network.</p>
                </div>
                <div>
                    <div class="step-num">2</div>
                    <h4>Invest</h4>
                    <p style="color:#64748b;">Deposit funds to activate your daily profit engine.</p>
                </div>
                <div>
                    <div class="step-num">3</div>
                    <h4>Multiply</h4>
                    <p style="color:#64748b;">Earn daily interest and bonuses from your 5-level team.</p>
                </div>
            </div>
        </div>
    </section>

    <section class="container">
        <div class="features">
            <div class="feature-card">
                <h3>📈 Daily Growth</h3>
                <p>Your investment grows every 24 hours with our automated profit engine.</p>
            </div>
            <div class="feature-card">
                <h3>🔗 5-Level Referrals</h3>
                <p>Earn massive commissions by building your team across 5 depth levels.</p>
            </div>
            <div class="feature-card">
                <h3>🔒 Secure Platform</h3>
                <p>Your data and funds are protected with industry-leading encryption.</p>
            </div>
        </div>
    </section>

    <section class="trust">
        <div class="container">
            <h2>Why Members Trust Network Platform</h2>
            <p style="color:#64748b; max-width:700px; margin:0 auto;">Built for transparent tracking, smooth withdrawals, and long-term referral growth.</p>
            <div class="trust-grid">
                <div class="trust-card">
                    <h3>Transparent Wallet Tracking</h3>
                    <p style="margin:0; color:#64748b;">Members can track balance, daily profit, and withdrawals from one dashboard.</p>
                </div>
                <div class="trust-card">
                    <h3>Structured Referral Program</h3>
                    <p style="margin:0; color:#64748b;">5-level referral commissions help you scale from direct invites to deeper team growth.</p>
                </div>
                <div class="trust-card">
                    <h3>Fast Admin Operations</h3>
                    <p style="margin:0; color:#64748b;">Admin tools simplify user management, investment approvals, and withdrawal review.</p>
                </div>
            </div>
            <div class="theme-banner" style="margin-top:24px;">
                <div class="theme-banner-text">
                    <h3>Referral Commission Snapshot</h3>
                    <p>L1: 5% | L2: 4% | L3: 3% | L4: 2% | L5: 1%. Your team growth directly improves your commission potential.</p>
                </div>
                <div class="theme-banner-image">
                    <img src="assets/network-team.svg" alt="Referral network illustration">
                </div>
            </div>
        </div>
    </section>

    <section class="insights">
        <div class="container">
            <h2>Platform Highlights</h2>
            <p style="color:#64748b; max-width:760px; margin:0 auto;">Data-driven member growth, structured referrals, and transparent operational flow across both member and admin dashboards.</p>
            <div class="insights-grid">
                <article class="insight-card">
                    <img src="assets/invest-growth.svg" alt="Daily investment growth">
                    <h3>Predictable Daily Profit Flow</h3>
                    <p>Members can monitor balance growth daily and track withdrawal readiness directly from the user dashboard.</p>
                </article>
                <article class="insight-card">
                    <img src="assets/network-team.svg" alt="Referral team network">
                    <h3>5-Level Team Expansion</h3>
                    <p>Referral depth unlocks additional commission layers, helping users scale from direct invites to long-term network earnings.</p>
                </article>
            </div>
        </div>
    </section>

    <section class="timeline">
        <div class="container">
            <h2>Earning Timeline</h2>
            <p style="color:#64748b; max-width:760px; margin:0 auto;">A simple progression model designed for clarity and consistent activity.</p>
            <div class="timeline-wrap">
                <div class="timeline-item">
                    <div class="timeline-step">Day 1</div>
                    <div>
                        <h4>Account Activation</h4>
                        <p>Complete registration and fund your plan to start the earning cycle.</p>
                    </div>
                </div>
                <div class="timeline-item">
                    <div class="timeline-step">Day 2+</div>
                    <div>
                        <h4>Daily Profit Accrual</h4>
                        <p>Profit is applied on invested balance while your dashboard tracks each change.</p>
                    </div>
                </div>
                <div class="timeline-item">
                    <div class="timeline-step">Growth Phase</div>
                    <div>
                        <h4>Referral Commission Scaling</h4>
                        <p>Invite members and build team levels to unlock L1 to L5 commission opportunities.</p>
                    </div>
                </div>
                <div class="timeline-item">
                    <div class="timeline-step">Withdrawal</div>
                    <div>
                        <h4>Payout and Reinvest</h4>
                        <p>Submit withdrawal requests after threshold and continue compounding with smart reinvestment.</p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <footer class="site-footer">
        <div class="site-footer-inner">
            <div class="site-footer-grid">
                <div>
                    <h3 class="site-footer-title">NETWORK PLATFORM</h3>
                    <p class="site-footer-text">Daily profit tracking, structured referral rewards, and clear dashboard control for members and admins.</p>
                </div>
                <div class="site-footer-links">
                    <a href="about.php">About</a>
                    <a href="news.php">News & Events</a>
                    <a href="contact.php">Contact Details</a>
                    <a href="register.php">Create Account</a>
                </div>
                <div class="site-footer-metrics">
                    <div>Active Members: <?php echo number_format((int)$total_members); ?></div>
                    <div>Total Approved Withdrawals: $<?php echo number_format((float)$total_paid, 2); ?></div>
                    <div>Daily Profit Model: 1.0% Fixed</div>
                </div>
            </div>
            <div class="site-footer-copy">&copy; <?php echo date('Y'); ?> Network Platform. Investment involves risk. Please invest responsibly.</div>
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
            window.addEventListener('resize', function () {
                if (window.innerWidth > 768) {
                    toggles.forEach(function (btn) {
                        var header = btn.closest('.landing-header, .main-header, .header');
                        if (!header) return;
                        header.classList.remove('nav-open');
                        btn.setAttribute('aria-expanded', 'false');
                    });
                }
            });
        })();
    </script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
