<?php
session_start();
include 'db.php';
include 'functions.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = (int)$_SESSION['user_id'];
ensureKycProfilesTable($pdo);
ensureUserCode($pdo, $user_id);
updateUserRank($pdo, $user_id);

$stmt = $pdo->prepare("SELECT id, username, email, role, user_rank, wallet_balance, investment_amount, user_code, created_at FROM users WHERE id = ? LIMIT 1");
$stmt->execute([$user_id]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user) {
    session_destroy();
    header("Location: login.php");
    exit();
}

$kyc = getUserKycStatus($pdo, $user_id);

$refStmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE referrer_id = ?");
$refStmt->execute([$user_id]);
$direct_referrals = (int)$refStmt->fetchColumn();

$wdStmt = $pdo->prepare("SELECT COUNT(*) FROM withdrawals WHERE user_id = ? AND status = 'pending'");
$wdStmt->execute([$user_id]);
$pending_withdrawals = (int)$wdStmt->fetchColumn();

$invStmt = $pdo->prepare("SELECT COUNT(*) FROM investment_requests WHERE user_id = ? AND status = 'pending'");
$invStmt->execute([$user_id]);
$pending_investments = (int)$invStmt->fetchColumn();

$initial = strtoupper(substr((string)$user['username'], 0, 1));
$is_admin = (($user['role'] ?? 'user') === 'admin');
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Profile | Network Platform</title>
    <style>
        :root {
            --blue:#1e3a8a;
            --teal:#0f766e;
            --bg:#f1f5f9;
            --ink:#0f172a;
            --muted:#64748b;
        }
        * { box-sizing:border-box; }
        body {
            margin:0;
            font-family: "Segoe UI", Arial, sans-serif;
            background:
                radial-gradient(1200px 700px at 8% -5%, #dbeafe 0%, transparent 56%),
                radial-gradient(900px 600px at 95% 0%, #ccfbf1 0%, transparent 52%),
                var(--bg);
            color: var(--ink);
            padding: 24px 14px 40px;
        }
        .wrap { max-width: 980px; margin: 0 auto; }
        .top {
            display:flex; align-items:center; justify-content:space-between; gap:12px; margin-bottom:16px;
        }
        .btn {
            text-decoration:none; padding:10px 14px; border-radius:10px; color:#fff; background:var(--blue); font-weight:700; font-size:13px;
        }
        .btn.alt { background: var(--teal); }
        .hero {
            background: linear-gradient(135deg, #1e3a8a, #0f766e);
            border-radius: 18px;
            padding: 24px;
            color:#fff;
            box-shadow: 0 16px 30px rgba(15, 23, 42, 0.18);
            display:flex; justify-content:space-between; gap:18px; align-items:center;
        }
        .avatar {
            width:84px; height:84px; border-radius:999px; border:3px solid rgba(255,255,255,0.45);
            display:flex; align-items:center; justify-content:center; font-size:30px; font-weight:900;
            background: rgba(255,255,255,0.14);
        }
        .hero h1 { margin: 0 0 6px; font-size: 28px; }
        .hero p { margin: 0; opacity: .9; }
        .grid {
            display:grid; grid-template-columns: repeat(auto-fit, minmax(210px, 1fr)); gap:12px; margin-top:16px;
        }
        .card {
            background:#fff; border:1px solid #e2e8f0; border-radius:14px; padding:14px;
        }
        .label { font-size:11px; letter-spacing:.4px; color:var(--muted); text-transform:uppercase; font-weight:800; }
        .value { margin-top:6px; font-size:20px; font-weight:800; color:var(--ink); }
        .sub { margin-top:4px; font-size:12px; color:var(--muted); }
        .list .row { padding:7px 0; border-bottom:1px dashed #e2e8f0; font-size:13px; color:#334155; }
        .list .row:last-child { border-bottom:0; }
        .pill {
            display:inline-block; padding:4px 8px; border-radius:999px; font-size:11px; font-weight:800;
            background:#e2e8f0; color:#334155;
        }
        .pill.ok { background:#dcfce7; color:#166534; }
        .pill.warn { background:#fef3c7; color:#92400e; }
        .pill.bad { background:#fee2e2; color:#991b1b; }
    </style>
</head>
<body>
<div class="wrap">
    <div class="top">
        <a class="btn" href="<?php echo $is_admin ? 'admin_dashboard.php' : 'dashboard.php'; ?>">Back to Dashboard</a>
        <a class="btn alt" href="logout.php">Logout</a>
    </div>

    <section class="hero">
        <div>
            <h1><?php echo htmlspecialchars((string)$user['username']); ?></h1>
            <p><?php echo htmlspecialchars((string)$user['email']); ?></p>
            <p style="margin-top:8px;">
                <span class="pill"><?php echo strtoupper(htmlspecialchars((string)$user['role'])); ?></span>
                <span class="pill"><?php echo htmlspecialchars((string)$user['user_rank']); ?></span>
                <span class="pill"><?php echo htmlspecialchars((string)$user['user_code']); ?></span>
            </p>
        </div>
        <div class="avatar"><?php echo htmlspecialchars($initial); ?></div>
    </section>

    <section class="grid">
        <div class="card">
            <div class="label">Wallet Balance</div>
            <div class="value">$<?php echo number_format((float)$user['wallet_balance'], 2); ?></div>
            <div class="sub">Available balance</div>
        </div>
        <div class="card">
            <div class="label">Investment Amount</div>
            <div class="value">$<?php echo number_format((float)$user['investment_amount'], 2); ?></div>
            <div class="sub">Total activated capital</div>
        </div>
        <div class="card">
            <div class="label">Direct Referrals</div>
            <div class="value"><?php echo number_format($direct_referrals); ?></div>
            <div class="sub">Level-1 team members</div>
        </div>
        <div class="card">
            <div class="label">KYC Status</div>
            <div class="value" style="font-size:16px;">
                <?php
                $ks = strtoupper((string)($kyc['status'] ?? 'NOT SUBMITTED'));
                $class = 'pill';
                if ($ks === 'VERIFIED') { $class = 'pill ok'; }
                elseif ($ks === 'PENDING') { $class = 'pill warn'; }
                elseif ($ks === 'REJECTED') { $class = 'pill bad'; }
                ?>
                <span class="<?php echo $class; ?>"><?php echo htmlspecialchars($ks); ?></span>
            </div>
            <div class="sub"><?php echo !empty($kyc['country_code']) ? ('Country: ' . htmlspecialchars((string)$kyc['country_code'])) : 'No country submitted'; ?></div>
        </div>
    </section>

    <section class="grid">
        <div class="card list">
            <div class="label">Account Details</div>
            <div class="row"><strong>User ID:</strong> <?php echo htmlspecialchars((string)$user['user_code']); ?></div>
            <div class="row"><strong>Joined:</strong> <?php echo date('M d, Y', strtotime((string)$user['created_at'])); ?></div>
            <div class="row"><strong>Role:</strong> <?php echo htmlspecialchars((string)$user['role']); ?></div>
            <div class="row"><strong>Rank:</strong> <?php echo htmlspecialchars((string)$user['user_rank']); ?></div>
        </div>
        <div class="card list">
            <div class="label">Request Overview</div>
            <div class="row"><strong>Pending Investments:</strong> <?php echo number_format($pending_investments); ?></div>
            <div class="row"><strong>Pending Withdrawals:</strong> <?php echo number_format($pending_withdrawals); ?></div>
            <div class="row"><strong>Crypto Profile:</strong> <a href="crypto_profile.php">Manage</a></div>
            <div class="row"><strong>KYC:</strong> <a href="kyc.php">Open KYC Page</a></div>
        </div>
    </section>
</div>
<script src="scroll_top.js"></script>
</body>
</html>
