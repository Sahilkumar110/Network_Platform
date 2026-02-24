<?php
session_start();
include 'db.php';

// 1. SECURITY CHECK: Make sure ONLY admins can stay here
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit("Access Denied: You must be an Admin.");
}
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
    $stmt_users = $pdo->query("SELECT id, username, email, role, wallet_balance, investment_amount, created_at FROM users ORDER BY id DESC");
    $users = $stmt_users->fetchAll();
    // Check if a search was performed
$search = isset($_GET['search']) ? $_GET['search'] : '';

if (!empty($search)) {
    // Search by ID or Email
    $stmt_users = $pdo->prepare("SELECT * FROM users WHERE email LIKE ? OR id = ? ORDER BY id DESC");
    $stmt_users->execute(["%$search%", $search]);
} else {
    // Normal fetch
    $stmt_users = $pdo->query("SELECT * FROM users ORDER BY id DESC");
}
$users = $stmt_users->fetchAll();

} catch (PDOException $e) {
    die("Database Error: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html>
<head>
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
}

.logo span {
    color: #3b82f6;
    font-weight: 400;
}

.nav-right {
    display: flex;
    align-items: center;
    gap: 20px;
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

.logout-btn:hover {
    background: #dc2626;
    transform: translateY(-1px);
    box-shadow: 0 4px 12px rgba(239, 68, 68, 0.3);
}
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
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 20px;
    margin: 25px 0;
}

.stat-card {
    background: white;
    padding: 20px;
    border-radius: 12px;
    box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
    border-top: 4px solid #1e3a8a;
}

.stat-label {
    display: block;
    color: #64748b;
    font-size: 13px;
    font-weight: 600;
    text-transform: uppercase;
    margin-bottom: 8px;
}

.stat-value {
    font-size: 24px;
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
    </style>
</head>
<body>
<header class="main-header">
    <div class="nav-container">
        <div class="logo">
            NETWORK<span>PLATFORM</span>
        </div>

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
                <span class="user-email">ID: #<?php echo $_SESSION['user_id']; ?></span>
            </div>
            <a href="logout.php" class="logout-btn">Logout</a>
        </div>
    </div>
</header>

<div class="stats-grid">
    <div class="stat-card">
        <span class="stat-label">Total Members</span>
        <div class="stat-value"><?php echo number_format($total_users); ?></div>
        <span class="stat-sub">Registered Accounts</span>
    </div>

    <div class="stat-card">
        <span class="stat-label">Platform Liabilities</span>
        <div class="stat-value">$<?php echo number_format($total_platform_balance, 2); ?></div>
        <span class="stat-sub">Total User Balances</span>
    </div>

    <div class="stat-card">
        <span class="stat-label">Total Investments</span>
        <div class="stat-value" style="color: #10b981;">$<?php echo number_format($total_invested, 2); ?></div>
        <span class="stat-sub">Total Inflow</span>
    </div>

    <div class="stat-card">
        <span class="stat-label">Bonuses Issued</span>
        <div class="stat-value" style="color: #3b82f6;">$<?php echo number_format($total_bonuses, 2); ?></div>
        <span class="stat-sub">Manual Adjustments</span>
    </div>
</div>


    <h1>Admin Control Panel</h1>
    <?php if (isset($_GET['success'])): ?>
    <div class="alert alert-success"><?php echo htmlspecialchars($_GET['success']); ?></div>
<?php endif; ?>

    <h3>User List</h3>
    <table>
        <tr>
            <th>ID</th>
            <th>Username</th>
            <th>Email</th>
            <th>Role</th>
            <th>Balance</th>
            <th>Invested</th>
        </tr>
        <?php foreach ($users as $u): ?>
       
        <tr>
            <td><?php echo $u['id']; ?></td>
            <td><?php echo htmlspecialchars($u['username']); ?></td>
            <td><?php echo htmlspecialchars($u['email']); ?></td>
            <td class="<?php echo $u['role'] == 'admin' ? 'status-admin' : ''; ?>">
                <?php echo strtoupper($u['role']); ?>
            </td>
            <td>$<?php echo number_format($u['wallet_balance'], 2); ?></td>
            <td>$<?php echo number_format($u['investment_amount'], 2); ?></td>
        </tr>

        
        <th>Actions</th>

<td>
    <form action="update_balance.php" method="POST" style="display: flex; gap: 5px;">
        <input type="hidden" name="user_id" value="<?php echo $u['id']; ?>">
        <input type="number" name="amount" step="0.01" placeholder="0.00" style="width: 70px; padding: 5px; border-radius: 4px; border: 1px solid #ccc;" required>
        
        <button type="submit" name="action" value="add" class="btn-add">+</button>
        <button type="submit" name="action" value="subtract" class="btn-sub">-</button>
    </form>
</td>
        <?php endforeach; ?>
    </table>
<?php
// Fetch last 10 transactions
$stmt_logs = $pdo->query("
    SELECT tl.*, u.email as user_email 
    FROM transaction_logs tl 
    JOIN users u ON tl.user_id = u.id 
    ORDER BY tl.created_at DESC LIMIT 10
");
$logs = $stmt_logs->fetchAll();
?>

<div style="margin-top: 50px;">
    <h3>Recent Activity Log</h3>
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
            <?php foreach ($logs as $log): ?>
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
</div>

<style>
    .log-table { width: 100%; border-collapse: collapse; margin-top: 10px; font-size: 14px; background: #fff; color: #333; }
    .log-table th, .log-table td { padding: 10px; border: 1px solid #eee; text-align: left; background-color: white; }
    .badge { padding: 3px 8px; border-radius: 4px; color: white; font-weight: bold; font-size: 11px; }
    .add { background: #10b981; }
    .subtract { background: #ef4444; }
</style>
</body>
</html>