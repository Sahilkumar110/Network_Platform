<?php
session_start();
include 'db.php';
include 'functions.php';

// 1. SECURITY CHECK: Make sure ONLY admins can stay here
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit("Access Denied: You must be an Admin.");
}
backfillMissingUserCodes($pdo);
ensureLoginAttemptsTable($pdo);
ensureKycProfilesTable($pdo);
ensureCronRunsTable($pdo);
ensureLedgerReconciliationTable($pdo);
ensureComplianceEventsTable($pdo);
ensureInvestmentRequestsTable($pdo);
ensureUserCryptoAddressesTable($pdo);
$failed_logins_24h = 0;
$pending_max_age_hours = 0;
$last_cron_status = 'n/a';
$last_cron_at = '-';
$ledger_mismatch_users = 0;
$ledger_mismatch_amount = 0.0;
$last_reconcile_at = '-';

$stmt_admin_user = $pdo->prepare("SELECT id, username, email, role, user_rank, wallet_balance, user_code FROM users WHERE id = ?");
$stmt_admin_user->execute([$_SESSION['user_id']]);
$admin_user = $stmt_admin_user->fetch();
$admin_avatar_initial = strtoupper(substr((string)($admin_user['username'] ?? 'A'), 0, 1));
// Calculate Global Stats
try {
    // Total Users
    $stmt_total_users = $pdo->query("SELECT COUNT(id) as total FROM users");
    $total_users = $stmt_total_users->fetch()['total'];

    // Total Wallet Balances (Money you owe users)
    $stmt_total_balance = $pdo->query("SELECT SUM(wallet_balance) as total FROM users");
    $total_platform_balance = $stmt_total_balance->fetch()['total'] ?? 0;

    // Total Investments (Money flowing in)
    $stmt_total_invested = $pdo->query("SELECT SUM(investment_amount) as total FROM users");
    $total_invested = $stmt_total_invested->fetch()['total'] ?? 0;

    // Total Manual Adjustments (From your new Transaction Log)
    $stmt_total_adj = $pdo->query("SELECT SUM(amount) as total FROM transaction_logs WHERE action_type = 'add'");
    $total_bonuses = $stmt_total_adj->fetch()['total'] ?? 0;

    $failed24Stmt = $pdo->query("SELECT COUNT(*) AS c FROM login_attempts WHERE is_success = 0 AND attempted_at >= (NOW() - INTERVAL 24 HOUR)");
    $failed_logins_24h = (int)($failed24Stmt->fetch()['c'] ?? 0);

    $pendingAgeStmt = $pdo->query("
        SELECT MAX(TIMESTAMPDIFF(HOUR, created_at, NOW())) AS max_age
        FROM (
            SELECT created_at FROM withdrawals WHERE status='pending'
            UNION ALL
            SELECT created_at FROM investment_requests WHERE status='pending'
            UNION ALL
            SELECT created_at FROM user_crypto_addresses WHERE status='pending'
            UNION ALL
            SELECT created_at FROM kyc_profiles WHERE status='pending'
        ) p
    ");
    $pending_max_age_hours = (int)($pendingAgeStmt->fetch()['max_age'] ?? 0);

    $cronStmt = $pdo->query("SELECT job_name, status, created_at FROM cron_runs ORDER BY created_at DESC LIMIT 1");
    $last_cron = $cronStmt->fetch();
    $last_cron_status = $last_cron['status'] ?? 'n/a';
    $last_cron_at = $last_cron['created_at'] ?? '-';

    $recStmt = $pdo->query("SELECT mismatched_users, total_mismatch_amount, created_at FROM ledger_reconciliation_reports ORDER BY id DESC LIMIT 1");
    $last_rec = $recStmt->fetch();
    $ledger_mismatch_users = (int)($last_rec['mismatched_users'] ?? 0);
    $ledger_mismatch_amount = (float)($last_rec['total_mismatch_amount'] ?? 0);
    $last_reconcile_at = $last_rec['created_at'] ?? '-';

} catch (PDOException $e) {
    echo "Stats Error: " . $e->getMessage();
}
try {
    // 2. FETCH PLATFORM STATS
    $stmt_stats = $pdo->query("
        SELECT 
            COUNT(id) as total_users,
            SUM(investment_amount) as total_invested,
            SUM(wallet_balance) as total_liabilities
        FROM users
    ");
    $stats = $stmt_stats->fetch();

    // 3. FETCH ALL USERS FOR THE TABLE
    $stmt_users = $pdo->query("SELECT id, username, email, role, status, wallet_balance, investment_amount, created_at, referrer_id, user_code FROM users ORDER BY id DESC");
    $users = $stmt_users->fetchAll();
    // Check if a search was performed
$search = isset($_GET['search']) ? $_GET['search'] : '';

if (!empty($search)) {
    // Search by ID or Email
    $stmt_users = $pdo->prepare("SELECT id, username, email, role, status, wallet_balance, investment_amount, created_at, referrer_id, user_code FROM users WHERE email LIKE ? OR id = ? OR user_code LIKE ? ORDER BY id DESC");
    $stmt_users->execute(["%$search%", $search, "%$search%"]);
} else {
    // Normal fetch
    $stmt_users = $pdo->query("SELECT id, username, email, role, status, wallet_balance, investment_amount, created_at, referrer_id, user_code FROM users ORDER BY id DESC");
}
$users = $stmt_users->fetchAll();

} catch (PDOException $e) {
    die("Database Error: " . $e->getMessage());
}

$dashboard_limit = 5;
if (!function_exists('adminDashboardLink')) {
    function adminDashboardLink(array $updates): string {
        $params = $_GET;
        foreach ($updates as $key => $value) {
            if ($value === null) {
                unset($params[$key]);
            } else {
                $params[$key] = $value;
            }
        }
        $query = http_build_query($params);
        return 'admin_dashboard.php' . ($query ? ('?' . $query) : '');
    }
}

$users_page = max(1, (int)($_GET['users_page'] ?? 1));
$users_total = count($users);
$users_pages = max(1, (int)ceil($users_total / $dashboard_limit));
$users_page = min($users_page, $users_pages);
$users_display = array_slice($users, ($users_page - 1) * $dashboard_limit, $dashboard_limit);

if (!function_exists('renderAdminPager')) {
    function adminPagerAnchor(string $param): string {
        $anchors = [
            'users_page' => 'users-list',
            'crypto_page' => 'pending-crypto',
            'kyc_page' => 'pending-kyc',
            'investments_page' => 'pending-investments',
            'withdrawals_page' => 'pending-withdrawals',
            'logs_page' => 'recent-logs',
        ];
        return $anchors[$param] ?? '';
    }

    function renderAdminPager(string $param, int $currentPage, int $totalPages): string {
        if ($totalPages <= 1) {
            return '';
        }

        $start = max(1, $currentPage - 2);
        $end = min($totalPages, $currentPage + 2);
        $html = '<div class="section-footer"><div class="pager-nav">';
        $anchor = adminPagerAnchor($param);
        $hash = $anchor !== '' ? ('#' . $anchor) : '';

        if ($currentPage > 1) {
            $prevLink = htmlspecialchars(adminDashboardLink([$param => $currentPage - 1]));
            $html .= '<a class="pager-btn" href="' . $prevLink . $hash . '">Prev</a>';
        }

        for ($i = $start; $i <= $end; $i++) {
            $pageLink = htmlspecialchars(adminDashboardLink([$param => $i]));
            $activeClass = ($i === $currentPage) ? ' pager-btn-active' : '';
            $html .= '<a class="pager-btn' . $activeClass . '" href="' . $pageLink . $hash . '">' . $i . '</a>';
        }

        if ($currentPage < $totalPages) {
            $nextLink = htmlspecialchars(adminDashboardLink([$param => $currentPage + 1]));
            $html .= '<a class="pager-btn" href="' . $nextLink . $hash . '">Next</a>';
        }

        $html .= '</div></div>';
        return $html;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard</title>
<style>
        body { font-family: Arial, sans-serif; background: #1a202c; color: white; padding: 20px; }
        .card-container { display: flex; gap: 20px; margin-bottom: 20px; }
        .card { background: #2d3748; padding: 20px; border-radius: 10px; flex: 1; border-left: 5px solid #4299e1; }
        table { width: 100%; border-collapse: collapse; background: #2d3748; }
        th, td { padding: 12px; border-bottom: 1px solid #4a5568; text-align: left; }
        th { background: #4a5568; }
        .status-admin { color: #48bb78; font-weight: bold; }
        /* Navigation Styling */
.main-header {
    background: #ffffff;
    padding: 0 20px;
    height: 70px;
    display: flex;
    align-items: center;
    box-shadow: 0 2px 10px rgba(0,0,0,0.1);
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

.logo {
    font-size: 22px;
    font-weight: 800;
    color: #1e3a8a;
    letter-spacing: -1px;
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    gap: 8px;
}

.logo span {
    color: #3b82f6;
    font-weight: 400;
}
.logo-menu { position: relative; }

.nav-right {
    display: flex;
    align-items: center;
    gap: 12px;
}

.user-info {
    display: flex;
    flex-direction: column;
    text-align: right;
}

.role-badge {
    font-size: 10px;
    font-weight: 800;
    padding: 2px 8px;
    border-radius: 20px;
    margin-bottom: 2px;
}

.admin-bg { background: #fee2e2; color: #dc2626; border: 1px solid #fecaca; }
.user-bg { background: #dcfce7; color: #16a34a; border: 1px solid #bbf7d0; }

.user-email {
    font-size: 13px;
    color: #64748b;
}

.logout-btn {
    display: flex;
    align-items: center;
    gap: 8px;
    background: #ef4444;
    color: white;
    text-decoration: none;
    padding: 10px 18px;
    border-radius: 8px;
    font-weight: 600;
    font-size: 14px;
    transition: all 0.3s ease;
}
.profile-btn {
    background: #1e3a8a;
}
.profile-btn:hover {
    background: #1d4ed8;
    box-shadow: 0 4px 12px rgba(30, 58, 138, 0.3);
}

.logout-btn:hover {
    background: #dc2626;
    transform: translateY(-1px);
    box-shadow: 0 4px 12px rgba(239, 68, 68, 0.3);
}
.profile-menu { position: relative; }
.profile-menu summary { list-style: none; cursor: pointer; }
.profile-menu summary::-webkit-details-marker { display: none; }
.profile-trigger {
    width: 40px; height: 40px; border-radius: 999px; border: 2px solid #dbeafe;
    background: #1e3a8a; color: white; display: flex; align-items: center; justify-content: center;
    font-weight: 800; font-size: 14px;
}
.profile-card {
    position: absolute; right: 0; top: 46px; width: 240px; background: white;
    border: 1px solid #e2e8f0; border-radius: 12px; box-shadow: 0 10px 24px rgba(0,0,0,0.12);
    padding: 12px; z-index: 1200; color: #1e293b;
}
.profile-row { font-size: 12px; color: #64748b; margin-bottom: 6px; }
.profile-row strong { color: #0f172a; font-size: 13px; }
.profile-links { margin-top: 8px; border-top: 1px solid #e2e8f0; padding-top: 8px; display: grid; gap: 6px; }
.profile-link {
    display: block;
    text-decoration: none;
    font-size: 12px;
    font-weight: 700;
    color: #1e293b;
    background: #f8fafc;
    border: 1px solid #e2e8f0;
    border-radius: 8px;
    padding: 8px 10px;
}
.profile-link:hover { background: #eef2ff; }
.profile-link-danger { color: #dc2626; }
.search-form {
    display: flex;
    background: #f1f5f9;
    border-radius: 8px;
    padding: 2px;
    width: 300px;
    border: 1px solid #cbd5e0;
}

.search-form input {
    border: none;
    background: transparent;
    padding: 8px 12px;
    flex: 1;
    outline: none;
    font-size: 14px;
}

.search-form button {
    background: #1e3a8a;
    border: none;
    padding: 8px 15px;
    border-radius: 6px;
    cursor: pointer;
    display: flex;
    align-items: center;
    transition: background 0.3s;
}

.search-form button:hover {
    background: #3b82f6;
}
.investment-queue-search {
    display: flex;
    align-items: center;
    gap: 10px;
    flex-wrap: wrap;
    margin: 10px 0 14px;
    padding: 12px;
    border-radius: 12px;
    border: 1px solid #334155;
    background: linear-gradient(180deg, #172033 0%, #111827 100%);
}
.investment-queue-search input[type="text"] {
    flex: 1;
    min-width: 280px;
    border: 1px solid #475569;
    background: #0f172a;
    color: #e2e8f0;
    padding: 10px 12px;
    border-radius: 10px;
    font-size: 14px;
}
.investment-queue-search input[type="text"]::placeholder {
    color: #94a3b8;
}
.investment-queue-search button {
    border: none;
    background: #2563eb;
    color: #fff;
    padding: 10px 14px;
    border-radius: 10px;
    font-weight: 700;
    cursor: pointer;
}
.investment-queue-search button:hover {
    background: #1d4ed8;
}
.queue-clear-link {
    color: #93c5fd;
    text-decoration: none;
    font-size: 13px;
    font-weight: 700;
}
.queue-clear-link:hover {
    text-decoration: underline;
}
.btn-add {
    background: #10b981; /* Green */
    color: white;
    border: none;
    padding: 5px 10px;
    border-radius: 4px;
    cursor: pointer;
    font-weight: bold;
}

.btn-sub {
    background: #ef4444; /* Red */
    color: white;
    border: none;
    padding: 5px 12px;
    border-radius: 4px;
    cursor: pointer;
    font-weight: bold;
}

.btn-add:hover { background: #059669; }
.btn-sub:hover { background: #dc2626; }

/* Notification popups */
.alert {
    padding: 10px;
    margin-bottom: 20px;
    border-radius: 8px;
    text-align: center;
}
.alert-success { background: #d1fae5; color: #065f46; border: 1px solid #34d399; }
.stats-grid {
    display: grid;
    grid-template-columns: repeat(4, minmax(0, 1fr));
    gap: 16px;
    margin: 20px 0 28px;
}

.stat-card {
    position: relative;
    background: linear-gradient(180deg, #ffffff 0%, #f8fafc 100%);
    padding: 18px;
    border-radius: 14px;
    box-shadow: 0 10px 24px rgba(15, 23, 42, 0.08);
    border: 1px solid #e2e8f0;
    overflow: hidden;
    transition: transform 0.2s ease, box-shadow 0.2s ease;
}

.stat-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 14px 28px rgba(15, 23, 42, 0.12);
}

.stat-card::before {
    content: "";
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 4px;
    background: #1e3a8a;
}

.stat-card-members::before { background: #1e3a8a; }
.stat-card-liability::before { background: #ef4444; }
.stat-card-invested::before { background: #10b981; }
.stat-card-bonus::before { background: #3b82f6; }

.stat-value-positive { color: #10b981; }
.stat-value-accent { color: #3b82f6; }

@media (max-width: 1200px) {
    .stats-grid {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }
}

.stat-label {
    display: block;
    color: #64748b;
    font-size: 12px;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.04em;
    margin-bottom: 8px;
}

.stat-value {
    font-size: clamp(22px, 2.2vw, 28px);
    font-weight: 800;
    color: #1e293b;
    margin-bottom: 4px;
}

.stat-sub {
    font-size: 11px;
    color: #94a3b8;
}
/* Status Button Styling */
.btn-status {
    display: block; /* Makes it a full-width button inside the cell */
    text-align: center;
    padding: 6px 0;
    border-radius: 6px;
    text-decoration: none;
    font-size: 11px;
    font-weight: 700;
    text-transform: uppercase;
    transition: all 0.2s ease;
    border: none;
    width: 100%;
    cursor: pointer;
}

/* Red style for Banning */
.btn-ban {
    background: #fee2e2;
    color: #b91c1c;
    border: 1px solid #fca5a5;
}

.btn-ban:hover {
    background: #ef4444;
    color: white;
}

/* Green style for Unbanning */
.btn-unban {
    background: #dcfce7;
    color: #15803d;
    border: 1px solid #86efac;
}

.btn-unban:hover {
    background: #22c55e;
    color: white;
}
.section-head {
    margin-top: 14px;
}
.section-head h3 {
    margin: 0 0 10px 0;
}
.section-footer {
    display: flex;
    justify-content: center;
    margin-top: 10px;
}
.pager-nav {
    display: flex;
    align-items: center;
    gap: 6px;
    flex-wrap: wrap;
    justify-content: center;
}
.pager-btn {
    text-decoration: none;
    color: #bfdbfe;
    border: 1px solid #334155;
    background: #1e293b;
    border-radius: 8px;
    padding: 6px 10px;
    font-size: 12px;
    font-weight: 700;
    min-width: 34px;
    text-align: center;
}
.pager-btn:hover {
    background: #334155;
    color: #fff;
}
.pager-btn-active {
    background: #3b82f6;
    border-color: #3b82f6;
    color: #fff;
}
.user-table-mobile {
    display: none;
}
.user-accordion {
    border: 1px solid #334155;
    border-radius: 10px;
    background: #2d3748;
    margin-bottom: 10px;
    overflow: hidden;
}
.user-accordion > summary {
    list-style: none;
    cursor: pointer;
    padding: 12px;
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 12px;
    font-weight: 700;
    color: #e2e8f0;
    background: #253244;
}
.user-accordion > summary::-webkit-details-marker {
    display: none;
}
.ua-id {
    color: #93c5fd;
    font-size: 12px;
}
.ua-name {
    color: #fff;
    font-size: 14px;
}
.ua-caret {
    color: #94a3b8;
    font-size: 12px;
}
.user-accordion[open] .ua-caret {
    transform: rotate(180deg);
}
.user-accordion-body {
    padding: 10px 12px 12px;
}
.ua-row {
    display: flex;
    justify-content: space-between;
    gap: 10px;
    padding: 6px 0;
    border-bottom: 1px solid #3b475a;
    color: #e2e8f0;
    font-size: 13px;
}
.ua-row:last-child {
    border-bottom: none;
}
.ua-row-label {
    color: #94a3b8;
    font-size: 11px;
    text-transform: uppercase;
    font-weight: 700;
}
.ua-actions {
    margin-top: 8px;
    display: flex;
    flex-direction: column;
    gap: 6px;
}
.ua-actions form {
    margin: 0;
}
.ua-adjust-form {
    display: grid !important;
    grid-template-columns: 1fr auto auto;
    gap: 6px !important;
}
.ua-adjust-form input[type="number"] {
    width: 100% !important;
    min-width: 0;
    padding: 8px;
    border-radius: 6px;
    border: 1px solid #475569;
    background: #1e293b;
    color: #fff;
}

@media (max-width: 768px) {
    body { padding: 12px; }
    .main-header {
        height: auto;
        padding: 10px 12px;
        border-radius: 12px;
        margin-bottom: 12px;
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
    .search-form {
        width: 100%;
    }
    .investment-queue-search {
        flex-direction: column;
        align-items: stretch;
    }
    .investment-queue-search input[type="text"] {
        min-width: 0;
        width: 100%;
    }
    .investment-queue-search button {
        width: 100%;
        justify-content: center;
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
        width: 100%;
        text-align: center;
        grid-column: 1 / -1;
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
    }
    .stats-grid {
        grid-template-columns: repeat(2, minmax(0, 1fr)) !important;
        gap: 12px;
        margin: 16px 0;
    }
    .stat-card {
        padding: 16px;
    }
    .stat-value {
        font-size: 22px;
    }
    .user-table {
        display: none;
    }
    .user-table-mobile {
        display: block;
    }
}

@media (max-width: 480px) {
    .stats-grid {
        grid-template-columns: 1fr !important;
    }
    .logout-btn {
        flex: 1 1 100%;
        width: 100%;
    }
    .ua-adjust-form {
        grid-template-columns: 1fr;
    }
}
    </style>
    <link rel="stylesheet" href="responsive.css">
</head>
<body class="admin-dashboard">
<header class="main-header">
    <div class="nav-container">
        <a href="index.php" class="logo">NETWORK<span>PLATFORM</span></a>
        <button type="button" class="mobile-nav-toggle" aria-label="Toggle menu" aria-expanded="false">&#9776;</button>
        <button type="button" class="mobile-search-toggle" aria-label="Toggle search" aria-expanded="false">&#128269;</button>

        <form action="admin_dashboard.php" method="GET" class="search-form">
            <input type="text" name="search" placeholder="Search user by email or ID..." 
                   value="<?php echo isset($_GET['search']) ? htmlspecialchars($_GET['search']) : ''; ?>">
            <button type="submit">
                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="white" viewBox="0 0 16 16">
                    <path d="M11.742 10.344a6.5 6.5 0 1 0-1.397 1.398h-.001c.03.04.062.078.098.115l3.85 3.85a1 1 0 0 0 1.415-1.414l-3.85-3.85a1.007 1.007 0 0 0-.115-.1zM12 6.5a5.5 5.5 0 1 1-11 0 5.5 5.5 0 0 1 11 0z"/>
                </svg>
            </button>
        </form>
        
        <div class="nav-right">
            <div class="user-info">
                <span class="role-badge admin-bg">ADMIN MODE</span>
                <span class="user-email">ID: <?php echo htmlspecialchars($admin_user['user_code'] ?? ('#' . $_SESSION['user_id'])); ?></span>
            </div>
            <details class="profile-menu">
                <summary class="profile-trigger"><?php echo htmlspecialchars($admin_avatar_initial); ?></summary>
                <div class="profile-card">
                    <div class="profile-links">
                        <a class="profile-link" href="profile.php">Profile</a>
                        <a class="profile-link" href="dashboard.php">User Dashboard</a>
                        <a class="profile-link" href="admin_withdrawals.php">Withdrawals</a>
                        <a class="profile-link" href="admin_ledger.php">Ledger</a>
                        <a class="profile-link" href="admin_compliance.php">Compliance</a>
                        <a class="profile-link profile-link-danger" href="logout.php">Logout</a>
                    </div>
                </div>
            </details>
        </div>
    </div>
</header>

    <h1 class="page-title">Admin Control Panel</h1>
    <?php if (isset($_GET['success'])): ?>
    <div class="alert alert-success"><?php echo htmlspecialchars($_GET['success']); ?></div>
<?php endif; ?>
    <?php if (isset($_GET['error'])): ?>
    <div class="alert" style="background:#fee2e2;color:#991b1b;border:1px solid #fecaca;"><?php echo htmlspecialchars($_GET['error']); ?></div>
<?php endif; ?>

<div class="theme-banner theme-banner-dark">
    <div class="theme-banner-text">
        <h3>Admin Operations Overview</h3>
        <p>Control investments, withdrawals, user balances, and referral-side growth from one centralized panel.</p>
    </div>
    <div class="theme-banner-image">
        <img src="assets/network-team.svg" alt="Admin network operations visual">
    </div>
</div>

<div class="stats-grid">
    <div class="stat-card stat-card-members">
        <span class="stat-label">Total Members</span>
        <div class="stat-value"><?php echo number_format($total_users); ?></div>
        <span class="stat-sub">Registered Accounts</span>
    </div>

    <div class="stat-card stat-card-liability">
        <span class="stat-label">Platform Liabilities</span>
        <div class="stat-value">$<?php echo number_format($total_platform_balance, 2); ?></div>
        <span class="stat-sub">Total User Balances</span>
    </div>

    <div class="stat-card stat-card-invested">
        <span class="stat-label">Total Investments</span>
        <div class="stat-value stat-value-positive">$<?php echo number_format($total_invested, 2); ?></div>
        <span class="stat-sub">Total Inflow</span>
    </div>

    <div class="stat-card stat-card-bonus">
        <span class="stat-label">Bonuses Issued</span>
        <div class="stat-value stat-value-accent">$<?php echo number_format($total_bonuses, 2); ?></div>
        <span class="stat-sub">Manual Adjustments</span>
    </div>
</div>

<div class="stats-grid" style="margin-top:12px;">
    <div class="stat-card">
        <span class="stat-label">Failed Logins (24h)</span>
        <div class="stat-value"><?php echo number_format($failed_logins_24h); ?></div>
        <span class="stat-sub">Security pressure indicator</span>
    </div>
    <div class="stat-card">
        <span class="stat-label">Oldest Pending Age</span>
        <div class="stat-value"><?php echo number_format($pending_max_age_hours); ?>h</div>
        <span class="stat-sub">Pending approvals aging</span>
    </div>
    <div class="stat-card">
        <span class="stat-label">Last Cron</span>
        <div class="stat-value"><?php echo htmlspecialchars(strtoupper((string)$last_cron_status)); ?></div>
        <span class="stat-sub"><?php echo htmlspecialchars((string)$last_cron_at); ?></span>
    </div>
    <div class="stat-card">
        <span class="stat-label">Wallet Mismatch Alerts</span>
        <div class="stat-value"><?php echo number_format($ledger_mismatch_users); ?></div>
        <span class="stat-sub">$<?php echo number_format($ledger_mismatch_amount, 2); ?> (<?php echo htmlspecialchars((string)$last_reconcile_at); ?>)</span>
    </div>
</div>

    <div class="section-head" id="users-list"><h3>User List</h3></div>
    <table class="user-table">
        <tr>
            <th>ID</th>
            <th>Username</th>
            <th>Email</th>
            <th>Referral Source</th>
            <th>Role</th>
            <th>Status</th>
            <th>Balance</th>
            <th>Invested</th>
            <th>Actions</th>
        </tr>
        <?php foreach ($users_display as $u): ?>
       
        <tr>
            <td data-label="ID"><?php echo htmlspecialchars($u['user_code'] ?: ('#' . $u['id'])); ?></td>
            <td data-label="Username"><?php echo htmlspecialchars($u['username']); ?></td>
            <td data-label="Email"><?php echo htmlspecialchars($u['email']); ?></td>
            <td data-label="Referral Source">
                <?php if (!empty($u['referrer_id'])): ?>
                    <?php echo htmlspecialchars(getUserCodeById($pdo, (int)$u['referrer_id'])); ?>
                <?php else: ?>
                    Normal Signup
                <?php endif; ?>
            </td>
            <td data-label="Role" class="<?php echo $u['role'] == 'admin' ? 'status-admin' : ''; ?>">
                <?php echo strtoupper($u['role']); ?>
            </td>
            <td data-label="Status"><?php echo strtoupper($u['status']); ?></td>
            <td data-label="Balance">$<?php echo number_format($u['wallet_balance'], 2); ?></td>
            <td data-label="Invested">$<?php echo number_format($u['investment_amount'], 2); ?></td>
            <td data-label="Actions">
                <div style="display: flex; flex-direction: column; gap: 6px;">
                    <form action="update_balance.php" method="POST" style="display: flex; gap: 5px;">
                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(csrfToken()); ?>">
                        <input type="hidden" name="user_id" value="<?php echo $u['id']; ?>">
                        <input type="number" name="amount" step="0.01" placeholder="0.00" style="width: 70px; padding: 5px; border-radius: 4px; border: 1px solid #ccc;" required>
                        <button type="submit" name="action" value="add" class="btn-add">+</button>
                        <button type="submit" name="action" value="subtract" class="btn-sub">-</button>
                    </form>
                    <?php if ($u['role'] !== 'admin'): ?>
                        <form action="toggle_status.php" method="POST">
                            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(csrfToken()); ?>">
                            <input type="hidden" name="id" value="<?php echo (int)$u['id']; ?>">
                            <button type="submit" class="btn-status <?php echo $u['status'] === 'active' ? 'btn-ban' : 'btn-unban'; ?>">
                                <?php echo $u['status'] === 'active' ? 'Ban User' : 'Unban User'; ?>
                            </button>
                        </form>
                    <?php endif; ?>
                </div>
            </td>
        </tr>
        <?php endforeach; ?>
    </table>
    <div class="user-table-mobile">
        <?php foreach ($users_display as $u): ?>
            <details class="user-accordion">
                <summary>
                    <div>
                        <div class="ua-id"><?php echo htmlspecialchars($u['user_code'] ?: ('#' . $u['id'])); ?></div>
                        <div class="ua-name"><?php echo htmlspecialchars($u['username']); ?></div>
                    </div>
                    <span class="ua-caret">▼</span>
                </summary>
                <div class="user-accordion-body">
                    <div class="ua-row">
                        <span class="ua-row-label">Email</span>
                        <span><?php echo htmlspecialchars($u['email']); ?></span>
                    </div>
                    <div class="ua-row">
                        <span class="ua-row-label">Referral</span>
                        <span>
                            <?php if (!empty($u['referrer_id'])): ?>
                                <?php echo htmlspecialchars(getUserCodeById($pdo, (int)$u['referrer_id'])); ?>
                            <?php else: ?>
                                Normal Signup
                            <?php endif; ?>
                        </span>
                    </div>
                    <div class="ua-row">
                        <span class="ua-row-label">Role</span>
                        <span><?php echo strtoupper($u['role']); ?></span>
                    </div>
                    <div class="ua-row">
                        <span class="ua-row-label">Status</span>
                        <span><?php echo strtoupper($u['status']); ?></span>
                    </div>
                    <div class="ua-row">
                        <span class="ua-row-label">Balance</span>
                        <span>$<?php echo number_format($u['wallet_balance'], 2); ?></span>
                    </div>
                    <div class="ua-row">
                        <span class="ua-row-label">Invested</span>
                        <span>$<?php echo number_format($u['investment_amount'], 2); ?></span>
                    </div>
                    <div class="ua-actions">
                        <form action="update_balance.php" method="POST" class="ua-adjust-form">
                            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(csrfToken()); ?>">
                            <input type="hidden" name="user_id" value="<?php echo $u['id']; ?>">
                            <input type="number" name="amount" step="0.01" placeholder="0.00" required>
                            <button type="submit" name="action" value="add" class="btn-add">+</button>
                            <button type="submit" name="action" value="subtract" class="btn-sub">-</button>
                        </form>
                        <?php if ($u['role'] !== 'admin'): ?>
                            <form action="toggle_status.php" method="POST">
                                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(csrfToken()); ?>">
                                <input type="hidden" name="id" value="<?php echo (int)$u['id']; ?>">
                                <button type="submit" class="btn-status <?php echo $u['status'] === 'active' ? 'btn-ban' : 'btn-unban'; ?>">
                                    <?php echo $u['status'] === 'active' ? 'Ban User' : 'Unban User'; ?>
                                </button>
                            </form>
                        <?php endif; ?>
                    </div>
                </div>
            </details>
        <?php endforeach; ?>
    </div>
    <?php echo renderAdminPager('users_page', $users_page, $users_pages); ?>
<?php
// Fetch last 10 transactions
$stmt_logs = $pdo->query("
    SELECT tl.*, u.email as user_email 
    FROM transaction_logs tl 
    JOIN users u ON tl.user_id = u.id 
    ORDER BY tl.created_at DESC LIMIT 10
");
$logs = $stmt_logs->fetchAll();

ensureInvestmentRequestsTable($pdo);
ensureUserCryptoAddressesTable($pdo);
$investment_search = trim((string)($_GET['investment_search'] ?? ''));
// Fetch pending investment requests (searchable queue)
$invest_sql = "
    SELECT ir.id, ir.user_id, ir.amount, ir.network, ir.tx_hash, ir.status, ir.created_at, u.username, u.email, u.user_code
    FROM investment_requests ir
    JOIN users u ON ir.user_id = u.id
    WHERE ir.status = 'pending'
";
$invest_params = [];
if ($investment_search !== '') {
    $invest_sql .= " AND (u.username LIKE ? OR u.email LIKE ? OR u.user_code LIKE ? OR ir.network LIKE ? OR ir.tx_hash LIKE ? OR ir.user_id = ?) ";
    $invest_params[] = "%{$investment_search}%";
    $invest_params[] = "%{$investment_search}%";
    $invest_params[] = "%{$investment_search}%";
    $invest_params[] = "%{$investment_search}%";
    $invest_params[] = "%{$investment_search}%";
    $invest_params[] = ctype_digit($investment_search) ? (int)$investment_search : -1;
}
$invest_sql .= " ORDER BY ir.created_at DESC ";
$stmt_investments = $pdo->prepare($invest_sql);
$stmt_investments->execute($invest_params);
$pending_investments = $stmt_investments->fetchAll();

// Fetch pending crypto address verification requests
$stmt_crypto = $pdo->query("
    SELECT ca.id, ca.user_id, ca.network, ca.address, ca.status, ca.created_at, u.username, u.email
    FROM user_crypto_addresses ca
    JOIN users u ON ca.user_id = u.id
    WHERE ca.status = 'pending'
    ORDER BY ca.created_at DESC
");
$pending_crypto = $stmt_crypto->fetchAll();

// Fetch pending KYC requests
$stmt_kyc = $pdo->query("
    SELECT k.id, k.user_id, k.full_name, k.country_code, k.document_type, k.document_number, k.status, k.created_at, u.username, u.email, u.user_code
    FROM kyc_profiles k
    JOIN users u ON k.user_id = u.id
    WHERE k.status = 'pending'
    ORDER BY k.created_at DESC
");
$pending_kyc = $stmt_kyc->fetchAll();

// Fetch pending withdrawal requests
$stmt_withdrawals = $pdo->query("
    SELECT w.id, w.user_id, w.amount, w.method, w.details, w.status, w.created_at, u.username, u.email
    FROM withdrawals w
    JOIN users u ON w.user_id = u.id
    WHERE w.status = 'pending'
    ORDER BY w.created_at DESC
");
$pending_withdrawals = $stmt_withdrawals->fetchAll();

$crypto_page = max(1, (int)($_GET['crypto_page'] ?? 1));
$kyc_page = max(1, (int)($_GET['kyc_page'] ?? 1));
$investments_page = max(1, (int)($_GET['investments_page'] ?? 1));
$withdrawals_page = max(1, (int)($_GET['withdrawals_page'] ?? 1));
$logs_page = max(1, (int)($_GET['logs_page'] ?? 1));

$pending_crypto_total = count($pending_crypto);
$pending_kyc_total = count($pending_kyc);
$pending_investments_total = count($pending_investments);
$pending_withdrawals_total = count($pending_withdrawals);
$logs_total = count($logs);

$pending_crypto_pages = max(1, (int)ceil($pending_crypto_total / $dashboard_limit));
$pending_kyc_pages = max(1, (int)ceil($pending_kyc_total / $dashboard_limit));
$pending_investments_pages = max(1, (int)ceil($pending_investments_total / $dashboard_limit));
$pending_withdrawals_pages = max(1, (int)ceil($pending_withdrawals_total / $dashboard_limit));
$logs_pages = max(1, (int)ceil($logs_total / $dashboard_limit));

$crypto_page = min($crypto_page, $pending_crypto_pages);
$kyc_page = min($kyc_page, $pending_kyc_pages);
$investments_page = min($investments_page, $pending_investments_pages);
$withdrawals_page = min($withdrawals_page, $pending_withdrawals_pages);
$logs_page = min($logs_page, $logs_pages);

$pending_crypto_display = array_slice($pending_crypto, ($crypto_page - 1) * $dashboard_limit, $dashboard_limit);
$pending_kyc_display = array_slice($pending_kyc, ($kyc_page - 1) * $dashboard_limit, $dashboard_limit);
$pending_investments_display = array_slice($pending_investments, ($investments_page - 1) * $dashboard_limit, $dashboard_limit);
$pending_withdrawals_display = array_slice($pending_withdrawals, ($withdrawals_page - 1) * $dashboard_limit, $dashboard_limit);
$logs_display = array_slice($logs, ($logs_page - 1) * $dashboard_limit, $dashboard_limit);
?>

<div style="margin-top: 50px;">
    <div class="section-head" id="pending-crypto"><h3>Pending Crypto Address Verifications</h3></div>
    <table class="log-table">
        <thead>
            <tr>
                <th>Date</th>
                <th>User</th>
                <th>Network</th>
                <th>Address</th>
                <th>Action</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($pending_crypto_display)): ?>
                <tr><td colspan="5" style="text-align:center;">No pending crypto verification requests</td></tr>
            <?php else: ?>
                <?php foreach ($pending_crypto_display as $ca): ?>
                    <tr>
                        <td><?php echo date('M d, Y H:i', strtotime($ca['created_at'])); ?></td>
                        <td><?php echo htmlspecialchars($ca['username']) . " (" . htmlspecialchars($ca['email']) . ")"; ?></td>
                        <td><?php echo htmlspecialchars($ca['network']); ?></td>
                        <td><?php echo htmlspecialchars($ca['address']); ?></td>
                        <td>
                            <form action="handle_crypto_address.php" method="POST" style="display:flex; gap:6px;">
                                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(csrfToken()); ?>">
                                <input type="hidden" name="row_id" value="<?php echo (int)$ca['id']; ?>">
                                <button type="submit" name="decision" value="approve" class="btn-add">Approve</button>
                                <button type="submit" name="decision" value="reject" class="btn-sub">Reject</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
    <?php echo renderAdminPager('crypto_page', $crypto_page, $pending_crypto_pages); ?>

    <div class="section-head" id="pending-kyc"><h3>Pending KYC Verifications</h3></div>
    <table class="log-table">
        <thead>
            <tr>
                <th>Date</th>
                <th>User</th>
                <th>Full Name</th>
                <th>Country</th>
                <th>Document</th>
                <th>Action</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($pending_kyc_display)): ?>
                <tr><td colspan="6" style="text-align:center;">No pending KYC requests</td></tr>
            <?php else: foreach ($pending_kyc_display as $k): ?>
                <tr>
                    <td><?php echo date('M d, Y H:i', strtotime($k['created_at'])); ?></td>
                    <td><?php echo htmlspecialchars($k['username']) . " (" . htmlspecialchars($k['user_code']) . ")<br><small>" . htmlspecialchars($k['email']) . "</small>"; ?></td>
                    <td><?php echo htmlspecialchars($k['full_name']); ?></td>
                    <td><?php echo htmlspecialchars($k['country_code']); ?></td>
                    <td><?php echo htmlspecialchars($k['document_type']) . " / " . htmlspecialchars($k['document_number']); ?></td>
                    <td>
                        <form action="handle_kyc.php" method="POST" style="display:flex; gap:6px; align-items:center;">
                            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(csrfToken()); ?>">
                            <input type="hidden" name="kyc_id" value="<?php echo (int)$k['id']; ?>">
                            <input type="text" name="review_note" placeholder="Note" style="max-width:120px;">
                            <button type="submit" name="decision" value="approve" class="btn-add">Approve</button>
                            <button type="submit" name="decision" value="reject" class="btn-sub">Reject</button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; endif; ?>
        </tbody>
    </table>
    <?php echo renderAdminPager('kyc_page', $kyc_page, $pending_kyc_pages); ?>

    <div class="section-head" id="pending-investments"><h3>Pending Investment Requests</h3></div>
    <form action="admin_dashboard.php" method="GET" class="investment-queue-search">
        <input type="text" name="investment_search" value="<?php echo htmlspecialchars($investment_search); ?>" placeholder="Search user, email, user code, tx hash, network, or user ID">
        <input type="hidden" name="investments_page" value="1">
        <?php if (!empty($search)): ?>
            <input type="hidden" name="search" value="<?php echo htmlspecialchars($search); ?>">
        <?php endif; ?>
        <button type="submit">Search Queue</button>
        <?php if ($investment_search !== ''): ?>
            <a class="queue-clear-link" href="<?php echo htmlspecialchars(adminDashboardLink(['investment_search' => null, 'investments_page' => 1])); ?>">Clear Filter</a>
        <?php endif; ?>
    </form>
    <table class="log-table">
        <thead>
            <tr>
                <th>Date</th>
                <th>User</th>
                <th>Amount</th>
                <th>Network</th>
                <th>TX Hash</th>
                <th>Action</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($pending_investments_display)): ?>
                <tr><td colspan="6" style="text-align:center;">No pending investment requests</td></tr>
            <?php else: ?>
                <?php foreach ($pending_investments_display as $ir): ?>
                    <tr>
                        <td><?php echo date('M d, Y H:i', strtotime($ir['created_at'])); ?></td>
                        <td><?php echo htmlspecialchars($ir['username']) . " (" . htmlspecialchars($ir['user_code']) . ")<br><small>" . htmlspecialchars($ir['email']) . "</small>"; ?></td>
                        <td>$<?php echo number_format((float)$ir['amount'], 2); ?></td>
                        <td><?php echo htmlspecialchars($ir['network']); ?></td>
                        <td><?php echo htmlspecialchars($ir['tx_hash']); ?></td>
                        <td>
                            <form action="handle_investment.php" method="POST" style="display:flex; gap:6px;">
                                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(csrfToken()); ?>">
                                <input type="hidden" name="request_id" value="<?php echo (int)$ir['id']; ?>">
                                <button type="submit" name="decision" value="approve" class="btn-add">Approve</button>
                                <button type="submit" name="decision" value="reject" class="btn-sub">Reject</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
    <?php echo renderAdminPager('investments_page', $investments_page, $pending_investments_pages); ?>

    <div class="section-head" id="pending-withdrawals"><h3>Pending Withdrawal Requests</h3></div>
    <table class="log-table">
        <thead>
            <tr>
                <th>Date</th>
                <th>User</th>
                <th>Amount</th>
                <th>Method</th>
                <th>Details</th>
                <th>Action</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($pending_withdrawals_display)): ?>
                <tr><td colspan="6" style="text-align:center;">No pending withdrawals</td></tr>
            <?php else: ?>
                <?php foreach ($pending_withdrawals_display as $w): ?>
                    <tr>
                        <td><?php echo date('M d, Y H:i', strtotime($w['created_at'])); ?></td>
                        <td><?php echo htmlspecialchars($w['username']) . " (" . htmlspecialchars($w['email']) . ")"; ?></td>
                        <td>$<?php echo number_format($w['amount'], 2); ?></td>
                        <td><?php echo htmlspecialchars($w['method']); ?></td>
                        <td><?php echo htmlspecialchars($w['details']); ?></td>
                        <td>
                            <form action="handle_withdrawal.php" method="POST" style="display:flex; gap:6px;">
                                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(csrfToken()); ?>">
                                <input type="hidden" name="withdrawal_id" value="<?php echo (int)$w['id']; ?>">
                                <button type="submit" name="decision" value="approve" class="btn-add">Approve</button>
                                <button type="submit" name="decision" value="reject" class="btn-sub">Reject</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
    <?php echo renderAdminPager('withdrawals_page', $withdrawals_page, $pending_withdrawals_pages); ?>

    <div class="section-head" id="recent-logs"><h3>Recent Activity Log</h3></div>
    <table class="log-table">
        <thead>
            <tr>
                <th>Date</th>
                <th>User Email</th>
                <th>Action</th>
                <th>Amount</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($logs_display as $log): ?>
            <tr>
                <td><?php echo $log['created_at']; ?></td>
                <td><?php echo $log['user_email']; ?></td>
                <td>
                    <span class="badge <?php echo $log['action_type']; ?>">
                        <?php echo strtoupper($log['action_type']); ?>
                    </span>
                </td>
                <td>$<?php echo number_format($log['amount'], 2); ?></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    <?php echo renderAdminPager('logs_page', $logs_page, $logs_pages); ?>
</div>

<footer class="site-footer">
    <div class="site-footer-inner">
        <div class="site-footer-grid">
            <div>
                <h3 class="site-footer-title">Admin Console</h3>
                <p class="site-footer-text">Centralized governance for member records, requests, platform liabilities, and manual adjustments.</p>
            </div>
            <div class="site-footer-links">
                <a href="admin_dashboard.php">Dashboard</a>
                <a href="admin_withdrawals.php">Withdrawals</a>
                <a href="admin_ledger.php">Ledger</a>
                <a href="admin_compliance.php">Compliance</a>
                <a href="admin_settings.php">Settings</a>
                <a href="contact.php">Support Contacts</a>
            </div>
            <div class="site-footer-metrics">
                <div>Total Members: <?php echo number_format((int)$total_users); ?></div>
                <div>Total Investments: $<?php echo number_format((float)$total_invested, 2); ?></div>
                <div>Platform Liabilities: $<?php echo number_format((float)$total_platform_balance, 2); ?></div>
            </div>
        </div>
        <div class="site-footer-copy">&copy; <?php echo date('Y'); ?> Network Platform Admin</div>
    </div>
</footer>

<style>
    .log-table { width: 100%; border-collapse: collapse; margin-top: 10px; font-size: 14px; background: #fff; color: #333; }
    .log-table th, .log-table td { padding: 10px; border: 1px solid #eee; text-align: left; background-color: white; }
    .badge { padding: 3px 8px; border-radius: 4px; color: white; font-weight: bold; font-size: 11px; }
    .add { background: #10b981; }
    .subtract { background: #ef4444; }
</style>
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
                if (isOpen) {
                    header.classList.remove('search-open');
                    var searchBtn = header.querySelector('.mobile-search-toggle');
                    if (searchBtn) searchBtn.setAttribute('aria-expanded', 'false');
                }
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

        var searchToggles = document.querySelectorAll('.mobile-search-toggle');
        searchToggles.forEach(function (btn) {
            var header = btn.closest('.main-header');
            if (!header) return;
            btn.addEventListener('click', function (e) {
                e.stopPropagation();
                var isOpen = header.classList.toggle('search-open');
                btn.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
                if (isOpen) {
                    header.classList.remove('nav-open');
                    var navBtn = header.querySelector('.mobile-nav-toggle');
                    if (navBtn) navBtn.setAttribute('aria-expanded', 'false');
                    var searchInput = header.querySelector('.search-form input[name=\"search\"]');
                    if (searchInput) searchInput.focus();
                }
            });
        });

        document.addEventListener('click', function (e) {
            searchToggles.forEach(function (btn) {
                var header = btn.closest('.main-header');
                if (!header || header.contains(e.target)) return;
                header.classList.remove('search-open');
                btn.setAttribute('aria-expanded', 'false');
            });
        });

        document.addEventListener('click', function (e) {
            document.querySelectorAll('.profile-menu').forEach(function (menu) {
                if (!menu.contains(e.target)) {
                    menu.removeAttribute('open');
                }
            });
        });
    })();
</script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="scroll_top.js"></script>
</body>
</html>
