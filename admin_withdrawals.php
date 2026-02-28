<?php
session_start();
include 'db.php';
include 'functions.php';

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit();
}

$stmt_admin_user = $pdo->prepare("SELECT id, username, user_code FROM users WHERE id = ? LIMIT 1");
$stmt_admin_user->execute([$_SESSION['user_id']]);
$admin_user = $stmt_admin_user->fetch(PDO::FETCH_ASSOC) ?: ['user_code' => '#' . (string)$_SESSION['user_id']];

$status = $_GET['status'] ?? 'all';
$search = trim($_GET['search'] ?? '');
$date_from = $_GET['date_from'] ?? '';
$date_to = $_GET['date_to'] ?? '';
$page = max(1, (int)($_GET['page'] ?? 1));
$per_page = 20;
$export = strtolower(trim((string)($_GET['export'] ?? '')));

$allowed_statuses = ['all', 'pending', 'approved', 'rejected'];
if (!in_array($status, $allowed_statuses, true)) {
    $status = 'all';
}

$where_sql = " WHERE 1=1 ";
$params = [];

if ($status !== 'all') {
    $where_sql .= " AND w.status = ? ";
    $params[] = $status;
}
if ($search !== '') {
    $where_sql .= " AND (u.username LIKE ? OR u.email LIKE ? OR u.user_code LIKE ? OR w.user_id = ?) ";
    $params[] = "%{$search}%";
    $params[] = "%{$search}%";
    $params[] = "%{$search}%";
    $params[] = ctype_digit($search) ? (int)$search : -1;
}
if ($date_from !== '') {
    $where_sql .= " AND DATE(w.created_at) >= ? ";
    $params[] = $date_from;
}
if ($date_to !== '') {
    $where_sql .= " AND DATE(w.created_at) <= ? ";
    $params[] = $date_to;
}

$count_sql = "
    SELECT COUNT(*)
    FROM withdrawals w
    JOIN users u ON w.user_id = u.id
    {$where_sql}
";
$count_stmt = $pdo->prepare($count_sql);
$count_stmt->execute($params);
$total_rows = (int)$count_stmt->fetchColumn();
$total_pages = max(1, (int)ceil($total_rows / $per_page));
$page = min($page, $total_pages);
$offset = ($page - 1) * $per_page;

$sql = "
    SELECT w.id, w.user_id, w.amount, w.method, w.details, w.status, w.created_at, u.username, u.email, u.user_code
    FROM withdrawals w
    JOIN users u ON w.user_id = u.id
    {$where_sql}
    ORDER BY w.created_at DESC
";

if ($export === 'csv') {
    $export_stmt = $pdo->prepare($sql);
    $export_stmt->execute($params);
    $export_rows = $export_stmt->fetchAll(PDO::FETCH_ASSOC);

    $filename = "withdrawals_" . date('Ymd_His') . ".csv";
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=' . $filename);
    $out = fopen('php://output', 'w');
    fputcsv($out, ['ID', 'Date', 'User ID', 'User Code', 'Username', 'Email', 'Amount', 'Method', 'Details', 'Status']);
    foreach ($export_rows as $r) {
        fputcsv($out, [
            $r['id'],
            $r['created_at'],
            $r['user_id'],
            $r['user_code'],
            $r['username'],
            $r['email'],
            $r['amount'],
            $r['method'],
            $r['details'],
            $r['status']
        ]);
    }
    fclose($out);
    exit();
}

$sql .= " LIMIT {$per_page} OFFSET {$offset}";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
backfillMissingUserCodes($pdo);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Withdrawals</title>
<style>
        :root { --primary: #1e3a8a; --danger: #ef4444; --ok: #10b981; }
        body {
            margin: 0;
            background: radial-gradient(900px 360px at 15% -5%, rgba(59, 130, 246, 0.14), transparent 60%),
                        radial-gradient(900px 360px at 85% -5%, rgba(30, 64, 175, 0.12), transparent 60%),
                        #0f172a;
            font-family: Arial, sans-serif;
            color: #e2e8f0;
        }
        .header { background: #fff; border-bottom: 1px solid #e2e8f0; }
        .header-inner { max-width: 1200px; margin: 0 auto; padding: 14px 20px; display: flex; justify-content: space-between; align-items: center; gap: 12px; }
        .logo { text-decoration: none; color: var(--primary); font-weight: 800; font-size: 22px; letter-spacing: -1px; }
        .logo span { color: #3b82f6; font-weight: 400; }
        .actions { display: flex; gap: 10px; align-items: center; }
        .user-info { display: flex; flex-direction: column; text-align: right; margin-right: 4px; }
        .role-badge {
            font-size: 10px;
            font-weight: 800;
            padding: 2px 8px;
            border-radius: 20px;
            margin-bottom: 2px;
            background: #fee2e2;
            color: #dc2626;
            border: 1px solid #fecaca;
            display: inline-block;
        }
        .user-email { font-size: 12px; color: #64748b; }
        .profile-menu { position: relative; }
        .profile-menu summary { list-style: none; cursor: pointer; }
        .profile-menu summary::-webkit-details-marker { display: none; }
        .profile-trigger {
            width: 40px;
            height: 40px;
            border-radius: 999px;
            border: 2px solid #dbeafe;
            background: #1e3a8a;
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 800;
            font-size: 14px;
        }
        .profile-card {
            position: absolute;
            right: 0;
            top: 46px;
            width: 240px;
            background: white;
            border: 1px solid #e2e8f0;
            border-radius: 12px;
            box-shadow: 0 10px 24px rgba(0,0,0,0.12);
            padding: 12px;
            z-index: 1200;
            color: #1e293b;
        }
        .profile-links {
            margin-top: 8px;
            border-top: 1px solid #e2e8f0;
            padding-top: 8px;
            display: grid;
            gap: 6px;
        }
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
        .btn { text-decoration: none; border-radius: 8px; padding: 9px 12px; font-weight: 700; font-size: 13px; color: #fff; background: var(--primary); }
        .btn-danger { background: var(--danger); }
        .container { max-width: 1200px; margin: 24px auto; padding: 0 20px; }
        .page-hero {
            background: linear-gradient(140deg, #111827 0%, #1f2937 100%);
            border: 1px solid #334155;
            border-radius: 14px;
            padding: 16px 18px;
            margin-bottom: 14px;
        }
        .page-title {
            margin: 0;
            font-size: clamp(26px, 3vw, 36px);
            font-weight: 800;
            letter-spacing: -0.02em;
            color: #f8fafc;
        }
        .page-subtitle {
            margin: 8px 0 0;
            color: #94a3b8;
            font-size: 14px;
        }
        .panel { background: #1e293b; border: 1px solid #334155; border-radius: 12px; padding: 14px; margin-bottom: 16px; }
        .filters { display: grid; grid-template-columns: 150px 1fr 170px 170px 120px 140px; gap: 10px; }
        input, select, button { padding: 10px; border: 1px solid #475569; border-radius: 8px; font-size: 14px; background: #0f172a; color: #e2e8f0; }
        button { cursor: pointer; font-weight: 700; background: var(--primary); color: #fff; border: none; }
        .btn-export { text-decoration: none; display: inline-block; text-align: center; background: #0f766e; color: #fff; border-radius: 8px; padding: 10px; font-weight: 700; }
        table { width: 100%; border-collapse: collapse; background: #1e293b; border: 1px solid #334155; border-radius: 12px; overflow: hidden; }
        th, td { padding: 12px; border-bottom: 1px solid #334155; text-align: left; font-size: 13px; color: #e2e8f0; }
        th { background: #172033; color: #cbd5e1; }
        .status { padding: 4px 8px; border-radius: 999px; font-size: 11px; font-weight: 800; text-transform: uppercase; display: inline-block; }
        .pending { background: #fef3c7; color: #92400e; }
        .approved { background: #dcfce7; color: #166534; }
        .rejected { background: #fee2e2; color: #991b1b; }
        .row-actions { display: flex; gap: 6px; }
        .approve { background: var(--ok); }
        .reject { background: var(--danger); }
        .alert { padding: 10px 12px; border-radius: 8px; margin-bottom: 12px; font-size: 13px; border: 1px solid transparent; }
        .alert-ok { background: #dcfce7; color: #166534; }
        .alert-err { background: #fee2e2; color: #991b1b; }
        .pager { display: flex; gap: 8px; justify-content: flex-end; margin-top: 12px; }
        .pager a { text-decoration: none; border-radius: 8px; padding: 7px 11px; border: 1px solid #475569; color: #cbd5e1; font-weight: 700; background: #1e293b; }
        .pager .active { background: #1e3a8a; color: #fff; border-color: #1e3a8a; }
        @media (max-width: 980px) { .filters { grid-template-columns: 1fr; } }
    </style>
    <link rel="stylesheet" href="responsive.css?v=20260301">
</head>
<body class="admin-withdrawals">
    <div class="header main-header">
        <div class="header-inner nav-container">
            <a href="index.php" class="logo">NETWORK<span>PLATFORM</span></a>
            <button type="button" class="mobile-nav-toggle" aria-label="Toggle menu" aria-expanded="false">&#9776;</button>
            <div class="actions nav-right">
                <div class="user-info">
                    <span class="role-badge">ADMIN MODE</span>
                    <span class="user-email">ID: <?php echo htmlspecialchars((string)$admin_user['user_code']); ?></span>
                </div>
                <details class="profile-menu">
                    <summary class="profile-trigger"><?php echo htmlspecialchars(strtoupper(substr((string)($admin_user['username'] ?? 'A'), 0, 1))); ?></summary>
                    <div class="profile-card">
                        <div class="profile-links">
                            <a class="profile-link" href="profile.php">Profile</a>
                            <a class="profile-link" href="admin_dashboard.php">Admin Dashboard</a>
                            <a class="profile-link" href="dashboard.php">User Dashboard</a>
                            <a class="profile-link" href="admin_ledger.php">Ledger</a>
                            <a class="profile-link" href="admin_compliance.php">Compliance</a>
                            <a class="profile-link profile-link-danger" href="logout.php">Logout</a>
                        </div>
                    </div>
                </details>
            </div>
        </div>
    </div>

    <div class="container">
        <section class="page-hero">
            <h1 class="page-title">Withdrawal History</h1>
            <p class="page-subtitle">Review pending withdrawals, approve or reject requests, and export filtered records.</p>
        </section>
        <?php if (isset($_GET['success'])): ?>
            <div class="alert alert-ok"><?php echo htmlspecialchars($_GET['success']); ?></div>
        <?php endif; ?>
        <?php if (isset($_GET['error'])): ?>
            <div class="alert alert-err"><?php echo htmlspecialchars($_GET['error']); ?></div>
        <?php endif; ?>

        <div class="panel">
            <form method="GET" class="filters">
                <select name="status">
                    <option value="all" <?php echo $status === 'all' ? 'selected' : ''; ?>>All Status</option>
                    <option value="pending" <?php echo $status === 'pending' ? 'selected' : ''; ?>>Pending</option>
                    <option value="approved" <?php echo $status === 'approved' ? 'selected' : ''; ?>>Approved</option>
                    <option value="rejected" <?php echo $status === 'rejected' ? 'selected' : ''; ?>>Rejected</option>
                </select>
                <input type="text" name="search" placeholder="Search username, email, user ID or code" value="<?php echo htmlspecialchars($search); ?>">
                <input type="date" name="date_from" value="<?php echo htmlspecialchars($date_from); ?>">
                <input type="date" name="date_to" value="<?php echo htmlspecialchars($date_to); ?>">
                <button type="submit">Apply</button>
                <a class="btn-export" href="admin_withdrawals.php?<?php echo htmlspecialchars(http_build_query(['status' => $status, 'search' => $search, 'date_from' => $date_from, 'date_to' => $date_to, 'export' => 'csv'])); ?>">Export CSV</a>
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
                            <td><?php echo htmlspecialchars($r['username']) . " (" . htmlspecialchars((string)$r['user_code']) . ")<br><small>" . htmlspecialchars($r['email']) . "</small>"; ?></td>
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
        <?php if ($total_pages > 1): ?>
            <div class="pager">
                <?php
                $base = ['status' => $status, 'search' => $search, 'date_from' => $date_from, 'date_to' => $date_to];
                if ($page > 1):
                    $prev = http_build_query(array_merge($base, ['page' => $page - 1]));
                ?>
                    <a href="admin_withdrawals.php?<?php echo htmlspecialchars($prev); ?>">Prev</a>
                <?php endif; ?>
                <?php
                $start = max(1, $page - 2);
                $end = min($total_pages, $page + 2);
                for ($p = $start; $p <= $end; $p++):
                    $link = http_build_query(array_merge($base, ['page' => $p]));
                ?>
                    <a class="<?php echo $p === $page ? 'active' : ''; ?>" href="admin_withdrawals.php?<?php echo htmlspecialchars($link); ?>"><?php echo $p; ?></a>
                <?php endfor; ?>
                <?php if ($page < $total_pages):
                    $next = http_build_query(array_merge($base, ['page' => $page + 1]));
                ?>
                    <a href="admin_withdrawals.php?<?php echo htmlspecialchars($next); ?>">Next</a>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>
    <script>
        (function () {
            function syncHeaderOffset() {
                var header = document.querySelector('.header');
                if (!header) return;
                var extra = window.innerWidth <= 768 ? 18 : 16;
                var offset = header.offsetHeight + extra;
                document.body.style.setProperty('padding-top', offset + 'px', 'important');
            }

            syncHeaderOffset();
            window.addEventListener('resize', syncHeaderOffset);

            var toggles = document.querySelectorAll('.mobile-nav-toggle');
            toggles.forEach(function (btn) {
                var header = btn.closest('.landing-header, .main-header, .header');
                if (!header) return;
                btn.addEventListener('click', function (e) {
                    e.stopPropagation();
                    var isOpen = header.classList.toggle('nav-open');
                    btn.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
                    setTimeout(syncHeaderOffset, 0);
                });
                header.querySelectorAll('a').forEach(function (link) {
                    link.addEventListener('click', function () {
                        header.classList.remove('nav-open');
                        btn.setAttribute('aria-expanded', 'false');
                        setTimeout(syncHeaderOffset, 0);
                    });
                });
            });
            document.addEventListener('click', function (e) {
                toggles.forEach(function (btn) {
                    var header = btn.closest('.landing-header, .main-header, .header');
                    if (!header || header.contains(e.target)) return;
                    header.classList.remove('nav-open');
                    btn.setAttribute('aria-expanded', 'false');
                    setTimeout(syncHeaderOffset, 0);
                });
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
