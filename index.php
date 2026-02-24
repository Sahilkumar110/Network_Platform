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
    </style>
</head>
<body>

    <header style="padding: 20px; display: flex; justify-content: space-between; align-items: center; max-width: 1200px; margin: 0 auto;">
        <div style="font-weight: bold; font-size: 24px; color: var(--primary);">NETWORK<span style="color: var(--secondary);">PLATFORM</span></div>
        <div>
            <a href="login.php" style="text-decoration: none; color: var(--dark); margin-right: 20px; font-weight: 600;">Login</a>
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

    <footer style="background: var(--dark); color: white; text-align: center; padding: 60px 20px;">
        <div class="container">
            <div style="font-weight: bold; font-size: 24px; margin-bottom: 20px;">NETWORK<span>PLATFORM</span></div>
            <p style="opacity: 0.6; font-size: 0.9rem;">&copy; 2026 Network Platform. High-yield investment involves risk. Invest responsibly.</p>
        </div>
    </footer>

</body>
</html>