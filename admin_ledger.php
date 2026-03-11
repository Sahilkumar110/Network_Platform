<?php
session_start();
include 'db.php';
include 'functions.php';

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit();
}

ensureWalletLedgerTable($pdo);

$stmt_admin_user = $pdo->prepare("SELECT id, username, user_code FROM users WHERE id = ?");
$stmt_admin_user->execute([$_SESSION['user_id']]);
$admin_user = $stmt_admin_user->fetch(PDO::FETCH_ASSOC) ?: ['username' => 'Admin', 'user_code' => '#'.(string)$_SESSION['user_id']];
$admin_avatar_initial = strtoupper(substr((string)($admin_user['username'] ?? 'A'), 0, 1));

$search = trim((string)($_GET['search'] ?? ''));
$entry_type = trim((string)($_GET['entry_type'] ?? ''));
$page = max(1, (int)($_GET['page'] ?? 1));
$limit = 10;

if (!function_exists('ledgerLink')) {
    function ledgerLink(array $updates): string {
        $params = $_GET;
        foreach ($updates as $key => $value) {
            if ($value === null || $value === '') {
                unset($params[$key]);
            } else {
                $params[$key] = $value;
            }
        }
        $query = http_build_query($params);
        return 'admin_ledger.php' . ($query ? ('?' . $query) : '');
    }
}

$where = " WHERE 1=1 ";
$params = [];
if ($search !== '') {
    $where .= " AND (u.email LIKE ? OR u.username LIKE ? OR u.user_code LIKE ? OR l.user_id = ?) ";
    $params[] = "%{$search}%";
    $params[] = "%{$search}%";
    $params[] = "%{$search}%";
    $params[] = ctype_digit($search) ? (int)$search : -1;
}
if ($entry_type !== '') {
    $where .= " AND l.entry_type = ? ";
    $params[] = $entry_type;
}

$download = strtolower(trim((string)($_GET['download'] ?? '')));
if (in_array($download, ['csv', 'pdf'], true)) {
    $exportSql = "SELECT l.*, u.username, u.email, u.user_code
        FROM wallet_ledger l
        JOIN users u ON u.id=l.user_id
        {$where}
        ORDER BY l.id DESC";
    $exportStmt = $pdo->prepare($exportSql);
    $exportStmt->execute($params);
    $exportRows = $exportStmt->fetchAll(PDO::FETCH_ASSOC);

    if ($download === 'csv') {
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename=ledger_export.csv');
        $out = fopen('php://output', 'w');
        fputcsv($out, ['Date', 'User Code', 'Username', 'Email', 'Entry Type', 'Delta', 'Balance After', 'Description', 'Ref Type', 'Ref ID']);
        foreach ($exportRows as $row) {
            fputcsv($out, [
                $row['created_at'],
                $row['user_code'],
                $row['username'],
                $row['email'],
                $row['entry_type'],
                number_format((float)$row['delta_amount'], 2),
                number_format((float)$row['balance_after'], 2),
                $row['description'],
                $row['reference_type'],
                $row['reference_id'],
            ]);
        }
        fclose($out);
        exit();
    }

    $escape = function ($text) {
        $text = str_replace('\\', '\\\\', (string)$text);
        $text = str_replace('(', '\\(', $text);
        $text = str_replace(')', '\\)', $text);
        return $text;
    };

    $lines = [];
    $lines[] = 'Ledger Export';
    $lines[] = '';
    foreach ($exportRows as $row) {
        $lines[] = sprintf(
            '%s | %s | %s | %s | %s',
            $row['created_at'],
            $row['user_code'] ?: ('#' . $row['user_id']),
            $row['entry_type'],
            '$' . number_format((float)$row['delta_amount'], 2),
            '$' . number_format((float)$row['balance_after'], 2)
        );
    }

    $content = "BT\n/F1 11 Tf\n40 760 Td\n";
    $leading = 14;
    foreach ($lines as $i => $line) {
        if ($i > 0) {
            $content .= "0 -" . $leading . " Td\n";
        }
        $content .= "(" . $escape($line) . ") Tj\n";
    }
    $content .= "ET\n";

    $objects = [];
    $objects[] = "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n";
    $objects[] = "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n";
    $objects[] = "3 0 obj\n<< /Type /Page /Parent 2 0 R /MediaBox [0 0 612 792] /Resources << /Font << /F1 4 0 R >> >> /Contents 5 0 R >>\nendobj\n";
    $objects[] = "4 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n";
    $objects[] = "5 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n" . $content . "endstream\nendobj\n";

    $pdf = "%PDF-1.4\n";
    $offsets = [0];
    foreach ($objects as $obj) {
        $offsets[] = strlen($pdf);
        $pdf .= $obj;
    }
    $xrefPos = strlen($pdf);
    $pdf .= "xref\n0 " . count($offsets) . "\n";
    $pdf .= "0000000000 65535 f \n";
    for ($i = 1; $i < count($offsets); $i++) {
        $pdf .= str_pad((string)$offsets[$i], 10, '0', STR_PAD_LEFT) . " 00000 n \n";
    }
    $pdf .= "trailer\n<< /Size " . count($offsets) . " /Root 1 0 R >>\n";
    $pdf .= "startxref\n" . $xrefPos . "\n%%EOF";

    header('Content-Type: application/pdf');
    header('Content-Disposition: attachment; filename=ledger_export.pdf');
    echo $pdf;
    exit();
}

$countSql = "SELECT COUNT(*) FROM wallet_ledger l JOIN users u ON u.id=l.user_id {$where}";
$countStmt = $pdo->prepare($countSql);
$countStmt->execute($params);
$total = (int)$countStmt->fetchColumn();
$pages = max(1, (int)ceil($total / $limit));
$page = min($page, $pages);
$offset = ($page - 1) * $limit;

$sql = "SELECT l.*, u.username, u.email, u.user_code
        FROM wallet_ledger l
        JOIN users u ON u.id=l.user_id
        {$where}
        ORDER BY l.id DESC
        LIMIT {$limit} OFFSET {$offset}";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ledger Explorer</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            background: #0f172a;
            color: #e2e8f0;
            margin: 0;
            padding: 20px;
        }
        .main-header {
            background: #ffffff;
            padding: 0 20px;
            height: 70px;
            display: flex;
            align-items: center;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            border-radius: 14px;
        }
        .nav-container {
            width: 100%;
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 12px;
        }
        .logo {
            font-size: 22px;
            font-weight: 800;
            color: #1e3a8a;
            letter-spacing: -1px;
            text-decoration: none;
        }
        .logo span { color: #3b82f6; font-weight: 400; }
        .nav-right { display: flex; align-items: center; gap: 12px; }
        .user-info { display: flex; flex-direction: column; text-align: right; }
        .role-badge {
            font-size: 10px;
            font-weight: 800;
            padding: 2px 8px;
            border-radius: 20px;
            margin-bottom: 2px;
        }
        .admin-bg { background: #fee2e2; color: #dc2626; border: 1px solid #fecaca; }
        .user-email { font-size: 13px; color: #64748b; }
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
        .mobile-nav-toggle { display: none; border: 1px solid #dbeafe; background: #eff6ff; border-radius: 10px; padding: 8px 10px; cursor: pointer; }
        .page-title {
            margin: 18px 0 8px;
            font-size: clamp(26px, 3vw, 36px);
            font-weight: 800;
            letter-spacing: -0.02em;
            color: #f8fafc;
        }
        .page-subtitle { margin: 0 0 18px; color: #94a3b8; }
        .card {
            background: #1e293b;
            border: 1px solid #334155;
            border-radius: 14px;
            padding: 14px;
            margin-bottom: 14px;
        }
        .filters { display: flex; gap: 8px; flex-wrap: wrap; }
        input, select, button {
            padding: 10px;
            border-radius: 8px;
            border: 1px solid #475569;
            background: #0f172a;
            color: #e2e8f0;
        }
        button {
            background: #2563eb;
            border-color: #2563eb;
            color: #fff;
            font-weight: 700;
            cursor: pointer;
        }
        .table-wrap {
            background: #1e293b;
            border: 1px solid #334155;
            border-radius: 14px;
            overflow: auto;
        }
        table { width: 100%; border-collapse: collapse; min-width: 920px; }
        th, td {
            border-bottom: 1px solid #334155;
            padding: 10px;
            font-size: 12px;
            text-align: left;
            color: #e2e8f0;
        }
        th { background: #172033; color: #cbd5e1; font-weight: 700; }
        .pager { padding: 12px 14px; }
        .pager-nav {
            display: flex;
            gap: 8px;
            justify-content: flex-end;
            flex-wrap: wrap;
        }
        .pager-btn {
            text-decoration: none;
            color: #cbd5e1;
            padding: 6px 11px;
            border: 1px solid #475569;
            border-radius: 8px;
            display: inline-block;
            font-size: 13px;
            font-weight: 700;
            background: #1e293b;
        }
        .pager-btn:hover {
            background: #172033;
            color: #fff;
        }
        .pager-btn-active {
            background: #2563eb;
            border-color: #2563eb;
            color: #fff;
        }
        @media (max-width: 900px) {
            body { padding: 14px; }
            .main-header { height: auto; padding: 10px 12px; }
            .nav-container { flex-wrap: wrap; }
            .mobile-nav-toggle { display: inline-block; margin-left: auto; }
            .nav-right { width: 100%; display: none; }
            .main-header.nav-open .nav-right { display: grid; grid-template-columns: 1fr; gap: 10px; }
            .user-info { text-align: center; }
            .profile-menu { width: 100%; display: flex; justify-content: center; }
            .page-title { margin-top: 12px; }
        }
    </style>
    <link rel="stylesheet" href="responsive.css?v=20260301">
</head>
<body class="admin-dashboard admin-ledger">
    <header class="main-header">
        <div class="nav-container">
            <a href="index.php" class="logo">NETWORK<span>PLATFORM</span></a>
            <button type="button" class="mobile-nav-toggle" aria-label="Toggle menu" aria-expanded="false">&#9776;</button>
            <div class="nav-right">
                <div class="user-info">
                    <span class="role-badge admin-bg">ADMIN MODE</span>
                    <span class="user-email">ID: <?php echo htmlspecialchars((string)$admin_user['user_code']); ?></span>
                </div>
                <details class="profile-menu">
                    <summary class="profile-trigger"><?php echo htmlspecialchars($admin_avatar_initial); ?></summary>
                    <div class="profile-card">
                        <div class="profile-links">
                            <a class="profile-link" href="profile.php">Profile</a>
                            <a class="profile-link" href="admin_dashboard.php">Admin Dashboard</a>
                            <a class="profile-link" href="dashboard.php">User Dashboard</a>
                            <a class="profile-link" href="admin_withdrawals.php">Withdrawals</a>
                            <a class="profile-link" href="admin_compliance.php">Compliance</a>
                            <a class="profile-link profile-link-danger" href="logout.php">Logout</a>
                        </div>
                    </div>
                </details>
            </div>
        </div>
    </header>
    <div class="card">
        <h1 class="page-title">Ledger Explorer</h1>
        <p class="page-subtitle">Track wallet movements, balances, and references for all users.</p>
        <form class="filters" method="get">
            <input type="text" name="search" placeholder="User/email/code/id" value="<?php echo htmlspecialchars($search); ?>">
            <input type="text" name="entry_type" placeholder="entry_type" value="<?php echo htmlspecialchars($entry_type); ?>">
            <button type="submit">Filter</button>
        </form>
        <div style="margin-top:10px; display:flex; gap:8px; flex-wrap:wrap;">
            <a class="pager-btn" href="<?php echo htmlspecialchars(ledgerLink(['download' => 'csv', 'page' => null])); ?>">Download CSV</a>
            <a class="pager-btn" href="<?php echo htmlspecialchars(ledgerLink(['download' => 'pdf', 'page' => null])); ?>">Download PDF</a>
        </div>
    </div>
    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>ID</th><th>Date</th><th>User</th><th>Type</th><th>Delta</th><th>Before</th><th>After</th><th>Ref</th><th>Description</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($rows)): ?>
                    <tr><td colspan="9" style="text-align:center;">No ledger entries found.</td></tr>
                <?php else: foreach ($rows as $r): ?>
                    <tr>
                        <td><?php echo (int)$r['id']; ?></td>
                        <td><?php echo htmlspecialchars((string)$r['created_at']); ?></td>
                        <td><?php echo htmlspecialchars((string)$r['username']) . ' (' . htmlspecialchars((string)$r['user_code']) . ')'; ?></td>
                        <td><?php echo htmlspecialchars((string)$r['entry_type']); ?></td>
                        <td><?php echo number_format((float)$r['delta_amount'], 2); ?></td>
                        <td><?php echo number_format((float)$r['balance_before'], 2); ?></td>
                        <td><?php echo number_format((float)$r['balance_after'], 2); ?></td>
                        <td><?php echo htmlspecialchars((string)($r['reference_type'] ?? '')) . ':' . htmlspecialchars((string)($r['reference_id'] ?? '')); ?></td>
                        <td><?php echo htmlspecialchars((string)($r['description'] ?? '')); ?></td>
                    </tr>
                <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
    <?php if ($pages > 1): ?>
        <div class="card pager">
            <div class="pager-nav">
                <?php if ($page > 1): ?>
                    <a class="pager-btn" href="<?php echo htmlspecialchars(ledgerLink(['page' => $page - 1])); ?>">Prev</a>
                <?php endif; ?>
                <?php
                $start = max(1, $page - 2);
                $end = min($pages, $page + 2);
                for ($p = $start; $p <= $end; $p++):
                    $active = ($p === $page) ? ' pager-btn-active' : '';
                ?>
                    <a class="pager-btn<?php echo $active; ?>" href="<?php echo htmlspecialchars(ledgerLink(['page' => $p])); ?>"><?php echo $p; ?></a>
                <?php endfor; ?>
                <?php if ($page < $pages): ?>
                    <a class="pager-btn" href="<?php echo htmlspecialchars(ledgerLink(['page' => $page + 1])); ?>">Next</a>
                <?php endif; ?>
            </div>
        </div>
    <?php endif; ?>
    <script>
        (function () {
            var btn = document.querySelector('.mobile-nav-toggle');
            var header = document.querySelector('.main-header');
            if (!btn || !header) return;
            btn.addEventListener('click', function () {
                var isOpen = header.classList.toggle('nav-open');
                btn.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
            });

            header.querySelectorAll('a').forEach(function (link) {
                link.addEventListener('click', function () {
                    header.classList.remove('nav-open');
                    btn.setAttribute('aria-expanded', 'false');
                });
            });

            document.addEventListener('click', function (e) {
                if (!header.contains(e.target)) {
                    header.classList.remove('nav-open');
                    btn.setAttribute('aria-expanded', 'false');
                }
                document.querySelectorAll('.profile-menu').forEach(function (menu) {
                    if (!menu.contains(e.target)) {
                        menu.removeAttribute('open');
                    }
                });
            });
        })();
    </script>
<script src="scroll_top.js"></script>
</body>
</html>
