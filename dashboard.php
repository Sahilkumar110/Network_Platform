<?php
session_start();
include 'db.php';
include 'functions.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id']; 
ensureUserCode($pdo, $user_id);
updateUserRank($pdo, $user_id);
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch();
$avatar_initial = strtoupper(substr((string)($user['username'] ?? 'U'), 0, 1));

$stmt_ref = $pdo->prepare("SELECT username, investment_amount FROM users WHERE referrer_id = ?");
$stmt_ref->execute([$user_id]);
$my_referrals = $stmt_ref->fetchAll();

$scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$host = $_SERVER['HTTP_HOST'] ?? 'localhost';
$base_path = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/')), '/');
$referral_link = $scheme . '://' . $host . $base_path . '/register.php?ref=' . rawurlencode((string)$user['user_code']);

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Dashboard | Network Platform</title>
<style>
        /* Modern Theme Variables */
        :root {
            --primary: #1e3a8a;
            --secondary: #3b82f6;
            --bg: #f8fafc;
            --text-dark: #1e293b;
            --text-light: #64748b;
            --success: #10b981;
            --danger: #ef4444;
        }

        body { 
            font-family: 'Inter', system-ui, -apple-system, sans-serif; 
            background: var(--bg); 
            margin: 0; 
            color: var(--text-dark);
        }
        * { box-sizing: border-box; }

        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 40px 20px;
        }

        /* Nav Header same as Admin */
        .main-header {
            background: #ffffff;
            padding: 0 20px;
            height: 70px;
            display: flex;
            align-items: center;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            position: sticky;
            top: 0;
            z-index: 1000;
        }

        .nav-container {
            width: 100%;
            max-width: 1200px;
            margin: 0 auto;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .logo { font-size: 22px; font-weight: 800; color: var(--primary); letter-spacing: -1px; text-decoration: none; }
        .logo span { color: var(--secondary); font-weight: 400; }

        .nav-right { display: flex; align-items: center; gap: 20px; }
        .user-info { text-align: right; }
        .role-badge { font-size: 10px; font-weight: 800; padding: 2px 8px; border-radius: 20px; margin-bottom: 2px; display: inline-block; }
        .user-bg { background: #dcfce7; color: #16a34a; border: 1px solid #bbf7d0; }
        .admin-bg { background: #fee2e2; color: #dc2626; border: 1px solid #fecaca; }
        .user-email { font-size: 13px; color: var(--text-light); display: block; }

        .logout-btn {
            display: flex; align-items: center; gap: 8px; background: var(--danger);
            color: white; text-decoration: none; padding: 10px 18px; border-radius: 8px;
            font-weight: 600; font-size: 14px; transition: 0.3s;
        }
        .profile-menu { position: relative; }
        .profile-menu summary { list-style: none; cursor: pointer; }
        .profile-menu summary::-webkit-details-marker { display: none; }
        .profile-trigger {
            width: 40px; height: 40px; border-radius: 999px; border: 2px solid #dbeafe;
            background: var(--primary); color: white; display: flex; align-items: center; justify-content: center;
            font-weight: 800; font-size: 14px;
        }
        .profile-card {
            position: absolute; right: 0; top: 46px; width: 240px; background: white;
            border: 1px solid #e2e8f0; border-radius: 12px; box-shadow: 0 10px 24px rgba(0,0,0,0.12);
            padding: 12px; z-index: 1200; color: var(--text-dark);
        }
        .profile-row { font-size: 12px; color: var(--text-light); margin-bottom: 6px; }
        .profile-row strong { color: var(--text-dark); font-size: 13px; }
        .profile-links { margin-top: 2px; display: grid; gap: 6px; }
        .profile-link {
            display:block; text-decoration:none; font-size:12px; font-weight:700;
            color:#1e293b; background:#f8fafc; border:1px solid #e2e8f0;
            border-radius:8px; padding:8px 10px;
        }
        .profile-link:hover { background:#eef2ff; }
        .profile-link-danger { color:#dc2626; }

        /* Stats Grid */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .card { 
            background: white; 
            padding: 25px; 
            border-radius: 12px; 
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05);
            border-top: 4px solid var(--primary);
        }

        .card h3 { 
            margin: 0; 
            color: var(--text-light); 
            font-size: 13px; 
            text-transform: uppercase; 
            letter-spacing: 0.05em;
        }

        .balance { 
            font-size: 32px; 
            color: var(--text-dark); 
            font-weight: 800; 
            margin: 10px 0;
        }

        /* Referral Box Styling */
        .ref-box { 
            background: #f1f5f9; 
            padding: 15px; 
            border: 1px dashed var(--secondary); 
            border-radius: 8px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-top: 15px;
        }

        code { font-family: monospace; color: var(--primary); font-weight: bold; font-size: 14px; }

        .copy-btn {
            background: var(--secondary);
            color: white;
            border: none;
            padding: 8px 15px;
            border-radius: 6px;
            cursor: pointer;
            font-weight: 600;
        }

        /* Table/List Styling */
        .ref-list {
            list-style: none;
            padding: 0;
            margin: 0;
        }

        .ref-item {
            display: flex;
            justify-content: space-between;
            padding: 12px 0;
            border-bottom: 1px solid #f1f5f9;
        }

        .ref-item:last-child { border: none; }

        /* Updated Withdrawal Card Styles */
.withdraw-status-container {
    margin-top: 15px;
    display: flex;
    flex-direction: column;
    align-items: center;
}

.btn-withdraw {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    width: 220px;
    max-width: 100%;
    padding: 14px;
    border-radius: 10px;
    font-weight: 700;
    text-decoration: none;
    transition: all 0.3s ease;
    border: none;
    margin: 0 auto;
    text-align: center;
}

.btn-active {
    background: var(--success);
    color: white;
    box-shadow: 0 4px 14px rgba(16, 185, 129, 0.3);
}

.btn-active:hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 20px rgba(16, 185, 129, 0.4);
}

.btn-locked {
    background: #f1f5f9;
    color: #94a3b8;
    cursor: not-allowed;
    border: 1px solid #e2e8f0;
}

/* Progress Bar for Motivation */
.progress-container {
    background: #e2e8f0;
    border-radius: 10px;
    height: 8px;
    width: 100%;
    margin: 15px 0 8px 0;
    overflow: hidden;
}

.progress-fill {
    background: var(--secondary);
    height: 100%;
    border-radius: 10px;
    transition: width 0.5s ease;
}

.needed-text {
    font-size: 12px;
    font-weight: 600;
    color: var(--danger);
    display: flex;
    justify-content: space-between;
    width: 220px;
    max-width: 100%;
    margin-top: 8px;
}
.withdraw-card-link {
    text-decoration: none;
    color: inherit;
    display: block;
}
.withdraw-card-link .card {
    transition: transform 0.2s ease, box-shadow 0.2s ease;
}
        .withdraw-card-link:hover .card {
    transform: translateY(-2px);
    box-shadow: 0 10px 20px rgba(0, 0, 0, 0.08);
}

        @media (max-width: 1024px) {
            .container {
                padding: 28px 16px;
            }
            .stats-grid {
                grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
                gap: 16px;
            }
            .balance {
                font-size: 28px;
            }
            .nav-right {
                gap: 12px;
            }
        }

        @media (max-width: 768px) {
            .main-header {
                height: auto;
                padding: 10px 12px;
                border-radius: 12px;
                margin: 10px 12px 0;
            }
            .nav-container {
                flex-direction: column;
                align-items: stretch;
                gap: 10px;
            }
            .logo {
                text-align: center;
                display: block;
                width: 100%;
            }
            .nav-right {
                width: 100%;
                display: grid;
                grid-template-columns: 1fr 1fr;
                gap: 10px;
                background: #f8fafc;
                border: 1px solid #e2e8f0;
                border-radius: 12px;
                padding: 10px;
            }
            .user-info {
                text-align: center;
                width: 100%;
                grid-column: 1 / -1;
            }
            .container {
                padding: 20px 12px;
            }
            .stats-grid {
                grid-template-columns: 1fr;
                gap: 14px;
                margin-bottom: 20px;
            }
            .card {
                padding: 18px;
            }
            .balance {
                font-size: 24px;
            }
            .ref-box {
                flex-direction: column;
                align-items: stretch;
                gap: 10px;
            }
            .ref-box code {
                overflow-wrap: anywhere;
                word-break: break-word;
            }
            .copy-btn {
                width: 100%;
            }
            .stats-grid .card[style*="grid-column: span 2"] {
                grid-column: span 1 !important;
            }
            .logout-btn {
                width: 100%;
                justify-content: center;
                margin: 0;
            }
            .profile-menu {
                grid-column: 1 / -1;
                width: 100%;
                display: flex;
                justify-content: center;
                margin: 0;
            }
        }

        @media (max-width: 480px) {
            .logo {
                font-size: 18px;
            }
            .logout-btn {
                width: 100%;
                justify-content: center;
                padding: 10px 12px;
                font-size: 13px;
            }
            .profile-card {
                width: min(92vw, 240px);
                right: 0;
            }
            .btn-withdraw,
            .needed-text {
                width: 100%;
            }
            .ref-item {
                gap: 8px;
                align-items: flex-start;
            }
        }
    </style>
    <link rel="stylesheet" href="responsive.css">
</head>
<body class="user-dashboard">

<header class="main-header">
    <div class="nav-container">
        <a href="index.php" class="logo">NETWORK<span>PLATFORM</span></a>
        <button type="button" class="mobile-nav-toggle" aria-label="Toggle menu" aria-expanded="false">&#9776;</button>
        <div class="nav-right">
            <div class="user-info">
                <span class="role-badge <?php echo (isset($_SESSION['role']) && $_SESSION['role'] === 'admin') ? 'admin-bg' : 'user-bg'; ?>">
                    <?php echo (isset($_SESSION['role']) && $_SESSION['role'] === 'admin') ? 'ADMIN USER VIEW' : 'USER MODE'; ?>
                </span>
                <span class="user-email">ID: <?php echo htmlspecialchars($user['user_code']); ?></span>
            </div>
            <details class="profile-menu">
                <summary class="profile-trigger"><?php echo htmlspecialchars($avatar_initial); ?></summary>
                <div class="profile-card">
                    <div class="profile-links">
                        <a class="profile-link" href="profile.php">Profile</a>
                        <?php if (isset($_SESSION['role']) && $_SESSION['role'] === 'admin'): ?>
                            <a class="profile-link" href="admin_dashboard.php">Admin Dashboard</a>
                        <?php endif; ?>
                        <a class="profile-link" href="crypto_profile.php">Crypto Profile</a>
                        <a class="profile-link" href="kyc.php">KYC</a>
                        <a class="profile-link" href="withdraw.php">Withdraw</a>
                        <a class="profile-link profile-link-danger" href="logout.php">Logout</a>
                    </div>
                </div>
            </details>
        </div>
    </div>
</header>

<div class="container">
    <h1 class="page-title">Welcome back, <?php echo htmlspecialchars($user['username']); ?>!</h1>
    <div class="theme-banner">
        <div class="theme-banner-text">
            <h3>Track Daily Profit and Team Growth</h3>
            <p>Monitor wallet movement, unlock withdrawals at threshold, and grow your referral network with clear live stats.</p>
        </div>
        <div class="theme-banner-image">
            <img src="assets/invest-growth.svg" alt="Dashboard growth visual">
        </div>
    </div>

    <div class="stats-grid">
        <div class="card">
            <h3>Wallet Balance</h3>
            <div class="balance">$<?php echo number_format($user['wallet_balance'], 2); ?></div>
            <p style="margin: 0; font-size: 14px; color: var(--text-light);">
                Rank: <strong style="color: var(--secondary);"><?php echo $user['user_rank']; ?></strong>
            </p>
            <a href="crypto_profile.php" style="display:inline-block; margin-top:8px; text-decoration:none; background:#0f766e; color:white; padding:10px 14px; border-radius:8px; font-weight:700; font-size:13px;">
                Crypto Address Profile
            </a>
            <a href="kyc.php" style="display:inline-block; margin-top:8px; margin-left:6px; text-decoration:none; background:#1d4ed8; color:white; padding:10px 14px; border-radius:8px; font-weight:700; font-size:13px;">
                KYC Verification
            </a>
        </div>

        <a href="withdraw.php" class="withdraw-card-link">
       <div class="card" style="border-top-color: var(--success);">
    <h3>Withdraw Funds</h3>
    
    <?php 
        $threshold = 200;
        $current_balance = $user['wallet_balance'];
        $percentage = ($current_balance / $threshold) * 100;
        if($percentage > 100) $percentage = 100; // Cap at 100%
    ?>

    <div class="balance" style="font-size: 24px; margin: 15px 0 5px 0;">
        $<?php echo number_format($current_balance, 2); ?> 
        <span style="font-size: 14px; color: var(--text-light); font-weight: normal;">/ $<?php echo $threshold; ?></span>
    </div>

    <div class="progress-container">
        <div class="progress-fill" style="width: <?php echo $percentage; ?>%;"></div>
    </div>

    <div class="withdraw-status-container">
        <?php if ($current_balance >= $threshold): ?>
            <span class="btn-withdraw btn-active">
                <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" fill="currentColor" viewBox="0 0 16 16">
                    <path d="M11 11.5a.5.5 0 0 1 .5-.5h2a.5.5 0 0 1 .5.5v2a.5.5 0 0 1-1 0v-1.071l-2.646 2.647a.5.5 0 0 1-.708-.708L12.293 12h-1.071a.5.5 0 0 1-.5-.5z"/>
                    <path d="M1 14s-1 0-1-1 1-4 6-4 6 3 6 4-1 1-1 1H1zm5-6a3 3 0 1 0 0-6 3 3 0 0 0 0 6z"/>
                </svg>
                Withdraw Funds
            </span>
        <?php else: ?>
            <button class="btn-withdraw btn-locked" disabled>
                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" viewBox="0 0 16 16">
                    <path d="M8 1a2 2 0 0 1 2 2v4H6V3a2 2 0 0 1 2-2zm3 6V3a3 3 0 0 0-6 0v4a2 2 0 0 0-2 2v5a2 2 0 0 0 2 2h6a2 2 0 0 0 2-2V9a2 2 0 0 0-2-2z"/>
                </svg>
                Balance Locked
            </button>
            <div class="needed-text">
                <span>Min: $<?php echo $threshold; ?></span>
                <span>Need $<?php echo number_format($threshold - $current_balance, 2); ?> more</span>
            </div>
        <?php endif; ?>
    </div>
</div>
        </a>
        <div class="card" style="border-top-color: var(--secondary);">
            <h3>Your Team (Level 1)</h3>
            <div class="balance"><?php echo count($my_referrals); ?> <span style="font-size: 14px; font-weight: normal; color: var(--text-light);">Members</span></div>
            <a href="referrals.php" style="color: var(--secondary); font-size: 14px; font-weight: 600; text-decoration: none;">View Full Tree →</a>
        </div>
    </div>

    <div class="stats-grid">
        <div class="card" style="grid-column: span 2;">
            <h3>Referral Program</h3>
            <p style="color: var(--text-light); font-size: 14px;">Invite your friends and earn commissions across 5 levels.</p>
            <div class="ref-box">
                <code><?php echo $referral_link; ?></code>
                <button class="copy-btn" onclick="copyLink('<?php echo $referral_link; ?>')">Copy Link</button>
            </div>
        </div>

        <div class="card">
            <h3>Recent Directs</h3>
            <div class="ref-list">
                <?php if (empty($my_referrals)): ?>
                    <p style="color: var(--text-light); font-size: 13px; margin-top: 15px;">No referrals yet.</p>
                <?php else: ?>
                    <?php foreach (array_slice($my_referrals, 0, 3) as $ref): ?>
                        <div class="ref-item">
                            <span style="font-weight: 600;"><?php echo htmlspecialchars($ref['username']); ?></span>
                            <span style="color: var(--success); font-weight: bold;">$<?php echo number_format($ref['investment_amount'], 2); ?></span>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<footer class="site-footer">
    <div class="site-footer-inner">
        <div class="site-footer-grid">
            <div>
                <h3 class="site-footer-title">Member Center</h3>
                <p class="site-footer-text">Manage your balance, referrals, and withdrawals from a single secure dashboard.</p>
            </div>
            <div class="site-footer-links">
                <a href="dashboard.php">Dashboard</a>
                <a href="referrals.php">Referral Tree</a>
                <a href="withdraw.php">Withdraw</a>
                <a href="contact.php">Support</a>
            </div>
            <div class="site-footer-metrics">
                <div>Wallet Balance: $<?php echo number_format((float)$user['wallet_balance'], 2); ?></div>
                <div>Direct Team Size: <?php echo count($my_referrals); ?></div>
                <div>User Rank: <?php echo htmlspecialchars((string)$user['user_rank']); ?></div>
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

    function copyLink(text) {
        navigator.clipboard.writeText(text);
        alert("Referral link copied to clipboard!");
    }
</script>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="scroll_top.js"></script>
</body>
</html>
