<?php
session_start();
include 'db.php';
include 'functions.php';

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit();
}

$status = $_GET['status'] ?? 'all';
$search = trim($_GET['search'] ?? '');
$date_from = $_GET['date_from'] ?? '';
$date_to = $_GET['date_to'] ?? '';

$allowed_statuses = ['all', 'pending', 'approved', 'rejected'];
if (!in_array($status, $allowed_statuses, true)) {
    $status = 'all';
}

$sql = "
    SELECT w.id, w.user_id, w.amount, w.method, w.details, w.status, w.created_at, u.username, u.email
    FROM withdrawals w
    JOIN users u ON w.user_id = u.id
    WHERE 1=1
";
$params = [];

if ($status !== 'all') {
    $sql .= " AND w.status = ? ";
    $params[] = $status;
}
if ($search !== '') {
    $sql .= " AND (u.username LIKE ? OR u.email LIKE ? OR w.user_id = ?) ";
    $params[] = "%{$search}%";
    $params[] = "%{$search}%";
    $params[] = ctype_digit($search) ? (int)$search : -1;
}
if ($date_from !== '') {
    $sql .= " AND DATE(w.created_at) >= ? ";
    $params[] = $date_from;
}
if ($date_to !== '') {
    $sql .= " AND DATE(w.created_at) <= ? ";
    $params[] = $date_to;
}

$sql .= " ORDER BY w.created_at DESC ";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Withdrawals</title>
    <style>
        :root { --primary: #1e3a8a; --danger: #ef4444; --ok: #10b981; }
        body { margin: 0; background: #f1f5f9; font-family: Arial, sans-serif; color: #1e293b; }
        .header { background: #fff; border-bottom: 1px solid #e2e8f0; }
        .header-inner { max-width: 1200px; margin: 0 auto; padding: 14px 20px; display: flex; justify-content: space-between; align-items: center; gap: 12px; }
        .logo { text-decoration: none; color: var(--primary); font-weight: 800; font-size: 22px; letter-spacing: -1px; }
        .logo span { color: #3b82f6; font-weight: 400; }
        .actions { display: flex; gap: 10px; }
        .btn { text-decoration: none; border-radius: 8px; padding: 9px 12px; font-weight: 700; font-size: 13px; color: #fff; background: var(--primary); }
        .btn-danger { background: var(--danger); }
        .container { max-width: 1200px; margin: 20px auto; padding: 0 20px; }
        .panel { background: #fff; border: 1px solid #e2e8f0; border-radius: 12px; padding: 14px; margin-bottom: 16px; }
        .filters { display: grid; grid-template-columns: 150px 1fr 170px 170px 120px; gap: 10px; }
        input, select, button { padding: 10px; border: 1px solid #cbd5e1; border-radius: 8px; font-size: 14px; }
        button { cursor: pointer; font-weight: 700; background: var(--primary); color: #fff; border: none; }
        table { width: 100%; border-collapse: collapse; background: #fff; border-radius: 12px; overflow: hidden; }
        th, td { padding: 12px; border-bottom: 1px solid #e2e8f0; text-align: left; font-size: 13px; }
        th { background: #f8fafc; color: #475569; }
        .status { padding: 4px 8px; border-radius: 999px; font-size: 11px; font-weight: 800; text-transform: uppercase; display: inline-block; }
        .pending { background: #fef3c7; color: #92400e; }
        .approved { background: #dcfce7; color: #166534; }
        .rejected { background: #fee2e2; color: #991b1b; }
        .row-actions { display: flex; gap: 6px; }
        .approve { background: var(--ok); }
        .reject { background: var(--danger); }
        .alert { padding: 10px 12px; border-radius: 8px; margin-bottom: 12px; font-size: 13px; }
        .alert-ok { background: #dcfce7; color: #166534; }
        .alert-err { background: #fee2e2; color: #991b1b; }
        @media (max-width: 980px) { .filters { grid-template-columns: 1fr; } }
    </style>
</head>
<body>
    <div class="header">
        <div class="header-inner">
            <a href="index.php" class="logo">NETWORK<span>PLATFORM</span></a>
            <div class="actions">
                <a href="admin_dashboard.php" class="btn">Admin Dashboard</a>
                <a href="logout.php" class="btn btn-danger">Logout</a>
            </div>
        </div>
    </div>

    <div class="container">
        <?php if (isset($_GET['success'])): ?>
            <div class="alert alert-ok"><?php echo htmlspecialchars($_GET['success']); ?></div>
        <?php endif; ?>
        <?php if (isset($_GET['error'])): ?>
            <div class="alert alert-err"><?php echo htmlspecialchars($_GET['error']); ?></div>
        <?php endif; ?>

        <div class="panel">
            <h2 style="margin-top:0;">Withdrawal History</h2>
            <form method="GET" class="filters">
                <select name="status">
                    <option value="all" <?php echo $status === 'all' ? 'selected' : ''; ?>>All Status</option>
                    <option value="pending" <?php echo $status === 'pending' ? 'selected' : ''; ?>>Pending</option>
                    <option value="approved" <?php echo $status === 'approved' ? 'selected' : ''; ?>>Approved</option>
                    <option value="rejected" <?php echo $status === 'rejected' ? 'selected' : ''; ?>>Rejected</option>
                </select>
                <input type="text" name="search" placeholder="Search username, email, or user ID" value="<?php echo htmlspecialchars($search); ?>">
                <input type="date" name="date_from" value="<?php echo htmlspecialchars($date_from); ?>">
                <input type="date" name="date_to" value="<?php echo htmlspecialchars($date_to); ?>">
                <button type="submit">Apply</button>
            </form>
        </div>

        <table>
            <thead>
                <tr>
                    <th>Date</th>
                    <th>User</th>
                    <th>Amount</th>
                    <th>Method</th>
                    <th>Details</th>
                    <th>Status</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($rows)): ?>
                    <tr><td colspan="7" style="text-align:center;">No withdrawal records found.</td></tr>
                <?php else: ?>
                    <?php foreach ($rows as $r): ?>
                        <tr>
                            <td><?php echo date('M d, Y H:i', strtotime($r['created_at'])); ?></td>
                            <td><?php echo htmlspecialchars($r['username']) . " (#" . (int)$r['user_id'] . ")<br><small>" . htmlspecialchars($r['email']) . "</small>"; ?></td>
                            <td>$<?php echo number_format((float)$r['amount'], 2); ?></td>
                            <td><?php echo htmlspecialchars($r['method']); ?></td>
                            <td><?php echo htmlspecialchars($r['details']); ?></td>
                            <td><span class="status <?php echo htmlspecialchars($r['status']); ?>"><?php echo htmlspecialchars($r['status']); ?></span></td>
                            <td>
                                <?php if ($r['status'] === 'pending'): ?>
                                    <form action="handle_withdrawal.php" method="POST" class="row-actions">
                                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(csrfToken()); ?>">
                                        <input type="hidden" name="withdrawal_id" value="<?php echo (int)$r['id']; ?>">
                                        <input type="hidden" name="redirect_page" value="admin_withdrawals.php">
                                        <button type="submit" name="decision" value="approve" class="approve">Approve</button>
                                        <button type="submit" name="decision" value="reject" class="reject">Reject</button>
                                    </form>
                                <?php else: ?>
                                    <span style="color:#94a3b8;">Processed</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</body>
</html>
