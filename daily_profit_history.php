<?php
session_start();
include 'db.php';
include 'functions.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = (int)$_SESSION['user_id'];

$stmt = $pdo->prepare("SELECT id, username, user_rank, user_code FROM users WHERE id = ? LIMIT 1");
$stmt->execute([$user_id]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$user) {
    session_destroy();
    header("Location: login.php");
    exit();
}

$allowed_days = [7, 30, 90];
$days = (int)($_GET['days'] ?? 30);
if (!in_array($days, $allowed_days, true)) {
    $days = 30;
}
$today = new DateTimeImmutable('today');
$start = $today->sub(new DateInterval('P' . $days . 'D'));

$custom_start = trim((string)($_GET['start'] ?? ''));
$custom_end = trim((string)($_GET['end'] ?? ''));
$use_custom = false;
if ($custom_start !== '' && $custom_end !== '') {
    $start_dt = DateTimeImmutable::createFromFormat('Y-m-d', $custom_start);
    $end_dt = DateTimeImmutable::createFromFormat('Y-m-d', $custom_end);
    if ($start_dt && $end_dt && $start_dt <= $end_dt) {
        $start = $start_dt;
        $today = $end_dt;
        $days = (int)$start->diff($today)->days + 1;
        $use_custom = true;
    }
}

$tx = $pdo->prepare(
    "SELECT DATE(created_at) AS d, SUM(amount) AS total
     FROM transactions
     WHERE user_id = ?
       AND type = 'daily_profit'
       AND created_at >= ?
     GROUP BY DATE(created_at)"
);
$tx->execute([$user_id, $start->format('Y-m-d')]);
$rows = $tx->fetchAll(PDO::FETCH_ASSOC);

$byDate = [];
foreach ($rows as $row) {
    $byDate[(string)$row['d']] = (float)$row['total'];
}

$reportRows = [];
for ($i = 0; $i < $days; $i++) {
    $d = $today->sub(new DateInterval('P' . $i . 'D'));
    $key = $d->format('Y-m-d');
    $amount = $byDate[$key] ?? 0.0;
    $reportRows[] = [
        'date' => $d->format('M d, Y'),
        'status' => $amount > 0 ? 'Credited' : 'Not Credited',
        'amount' => $amount > 0 ? number_format($amount, 2) : ''
    ];
}

if (isset($_GET['download']) && $_GET['download'] === 'csv') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=daily_profit_history.csv');
    $out = fopen('php://output', 'w');
    fputcsv($out, ['Date', 'Status', 'Amount']);
    foreach ($reportRows as $row) {
        fputcsv($out, [$row['date'], $row['status'], $row['amount'] !== '' ? ('$' . $row['amount']) : '']);
    }
    fclose($out);
    exit();
}

if (isset($_GET['download']) && $_GET['download'] === 'pdf') {
    $escape = function ($text) {
        $text = str_replace('\\', '\\\\', $text);
        $text = str_replace('(', '\\(', $text);
        $text = str_replace(')', '\\)', $text);
        return $text;
    };

    $lines = [];
    $lines[] = 'Daily Profit History (' . $days . ' days)';
    $lines[] = '';
    foreach ($reportRows as $row) {
        $amt = $row['amount'] !== '' ? ('$' . $row['amount']) : '-';
        $lines[] = $row['date'] . '  |  ' . $row['status'] . '  |  ' . $amt;
    }

    $content = "BT\n/F1 12 Tf\n50 750 Td\n";
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
    header('Content-Disposition: attachment; filename=daily_profit_history.pdf');
    echo $pdf;
    exit();
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Daily Profit History</title>
    <link rel="stylesheet" href="responsive.css?v=20260301">
    <style>
        :root { --primary:#1e3a8a; --accent:#0f766e; --bg:#f8fafc; --ink:#0f172a; --muted:#64748b; }
        body { margin:0; font-family:"Segoe UI", Arial, sans-serif; background:var(--bg); color:var(--ink); }
        .main-header {
            background:#ffffff; padding:0 20px; height:70px; display:flex; align-items:center;
            box-shadow:0 2px 10px rgba(0,0,0,0.05); position:sticky; top:0; z-index:1000;
        }
        .nav-container { width:100%; max-width:1200px; margin:0 auto; display:flex; justify-content:space-between; align-items:center; gap:12px; }
        .logo { font-size:22px; font-weight:800; color:var(--primary); letter-spacing:-1px; text-decoration:none; }
        .logo span { color:#3b82f6; font-weight:400; }
        .nav-right { display:flex; align-items:center; gap:16px; }
        .user-info { text-align:right; }
        .user-email { font-size:13px; color:var(--muted); display:block; }
        .profile-menu { position:relative; }
        .profile-menu summary { list-style:none; cursor:pointer; }
        .profile-menu summary::-webkit-details-marker { display:none; }
        .profile-trigger {
            width:40px; height:40px; border-radius:999px; border:2px solid #dbeafe;
            background:var(--primary); color:#fff; display:flex; align-items:center; justify-content:center;
            font-weight:800; font-size:14px;
        }
        .profile-card {
            position:absolute; right:0; top:46px; width:240px; background:#fff; border:1px solid #e2e8f0;
            border-radius:12px; box-shadow:0 10px 24px rgba(0,0,0,0.12); padding:12px; z-index:1200; color:var(--ink);
        }
        .profile-links { margin-top:8px; border-top:1px solid #e2e8f0; padding-top:8px; display:grid; gap:6px; }
        .profile-link {
            display:block; text-decoration:none; font-size:12px; font-weight:700; color:#1e293b;
            background:#f8fafc; border:1px solid #e2e8f0; border-radius:8px; padding:8px 10px;
        }
        .profile-link:hover { background:#eef2ff; }
        .profile-link-danger { color:#dc2626; }
        .container { max-width:1200px; margin:24px auto; padding:0 20px; }
        .page-hero {
            background:#ffffff; border:1px solid #e2e8f0; border-radius:16px; padding:18px 20px;
            box-shadow:0 8px 20px rgba(15, 23, 42, 0.05); margin-bottom:16px;
        }
        .page-title { margin:0; font-size:clamp(22px, 3vw, 32px); font-weight:800; letter-spacing:-0.02em; }
        .page-subtitle { margin:8px 0 0; color:var(--muted); font-size:14px; }
        .table-wrap { background:#fff; border:1px solid #e2e8f0; border-radius:14px; overflow:hidden; }
        table { width:100%; border-collapse:collapse; }
        th, td { padding:12px; border-bottom:1px solid #e2e8f0; text-align:left; font-size:13px; }
        th { background:#f8fafc; color:#475569; }
        .pill {
            display:inline-block; padding:4px 8px; border-radius:999px; font-size:11px; font-weight:800;
        }
        .pill.ok { background:#dcfce7; color:#166534; }
        .pill.bad { background:#fee2e2; color:#991b1b; }
        @media (max-width: 768px) {
            .main-header { height:auto; padding:10px 12px; }
            .nav-container { flex-wrap:wrap; gap:8px; }
            .nav-right { width:100%; justify-content:space-between; }
        }
    </style>
</head>
<body class="user-dashboard">
<header class="main-header">
    <div class="nav-container">
        <a href="index.php" class="logo">NETWORK<span>PLATFORM</span></a>
        <div class="nav-right">
            <div class="user-info">
                <span class="user-email">ID: <?php echo htmlspecialchars((string)$user['user_code']); ?></span>
            </div>
            <details class="profile-menu">
                <summary class="profile-trigger"><?php echo htmlspecialchars(strtoupper(substr((string)$user['username'], 0, 1))); ?></summary>
                <div class="profile-card">
                    <div class="profile-links">
                        <a class="profile-link" href="profile.php">Profile</a>
                        <a class="profile-link" href="dashboard.php">User Dashboard</a>
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
    <section class="page-hero">
        <h1 class="page-title">Daily Profit History</h1>
        <p class="page-subtitle">Last <?php echo (int)$days; ?> days of daily profit credit status.</p>
        <form method="get" style="margin-top:10px; display:flex; gap:10px; flex-wrap:wrap;">
            <select name="days" style="padding:8px 10px; border-radius:10px; border:1px solid #e2e8f0;">
                <option value="7" <?php echo $days === 7 ? 'selected' : ''; ?>>Last 7 days</option>
                <option value="30" <?php echo $days === 30 ? 'selected' : ''; ?>>Last 30 days</option>
                <option value="90" <?php echo $days === 90 ? 'selected' : ''; ?>>Last 90 days</option>
            </select>
            <input type="date" name="start" value="<?php echo htmlspecialchars($use_custom ? $start->format('Y-m-d') : ''); ?>" style="padding:8px 10px; border-radius:10px; border:1px solid #e2e8f0;">
            <input type="date" name="end" value="<?php echo htmlspecialchars($use_custom ? $today->format('Y-m-d') : ''); ?>" style="padding:8px 10px; border-radius:10px; border:1px solid #e2e8f0;">
            <button type="submit" style="padding:8px 12px; border-radius:10px; border:1px solid #1e3a8a; background:#1e3a8a; color:#fff; font-weight:700;">Apply</button>
            <a href="daily_profit_history.php?<?php echo htmlspecialchars(http_build_query(['days' => (int)$days, 'start' => $use_custom ? $start->format('Y-m-d') : '', 'end' => $use_custom ? $today->format('Y-m-d') : '', 'download' => 'csv'])); ?>" style="padding:8px 12px; border-radius:10px; border:1px solid #0f766e; background:#0f766e; color:#fff; text-decoration:none; font-weight:700;">Download CSV</a>
            <a href="daily_profit_history.php?<?php echo htmlspecialchars(http_build_query(['days' => (int)$days, 'start' => $use_custom ? $start->format('Y-m-d') : '', 'end' => $use_custom ? $today->format('Y-m-d') : '', 'download' => 'pdf'])); ?>" style="padding:8px 12px; border-radius:10px; border:1px solid #0f172a; background:#0f172a; color:#fff; text-decoration:none; font-weight:700;">Download PDF</a>
        </form>
    </section>

    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>Date</th>
                    <th>Status</th>
                    <th>Amount</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($reportRows as $row): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($row['date']); ?></td>
                        <td><span class="pill <?php echo $row['status'] === 'Credited' ? 'ok' : 'bad'; ?>"><?php echo htmlspecialchars($row['status']); ?></span></td>
                        <td><?php echo $row['amount'] !== '' ? ('$' . htmlspecialchars($row['amount'])) : '-'; ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
</body>
</html>
