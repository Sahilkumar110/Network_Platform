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
ensureUserProfileImageColumn($pdo);
updateUserRank($pdo, $user_id);

$notice = '';
$notice_type = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        $notice = 'Invalid session token. Please refresh and try again.';
        $notice_type = 'error';
    } else {
        $file = $_FILES['avatar_image'] ?? null;
        if (!$file || !isset($file['error']) || (int)$file['error'] !== UPLOAD_ERR_OK) {
            $notice = 'Please choose a valid image file.';
            $notice_type = 'error';
        } else {
            $maxBytes = 2 * 1024 * 1024; // 2 MB
            if ((int)$file['size'] <= 0 || (int)$file['size'] > $maxBytes) {
                $notice = 'Image size must be between 1 byte and 2 MB.';
                $notice_type = 'error';
            } else {
                $tmpPath = (string)$file['tmp_name'];
                $finfo = finfo_open(FILEINFO_MIME_TYPE);
                $mime = $finfo ? (string)finfo_file($finfo, $tmpPath) : '';
                if ($finfo) {
                    finfo_close($finfo);
                }

                $allowed = [
                    'image/jpeg' => 'jpg',
                    'image/png' => 'png',
                    'image/webp' => 'webp',
                    'image/gif' => 'gif',
                ];

                if (!isset($allowed[$mime]) || @getimagesize($tmpPath) === false) {
                    $notice = 'Only JPG, PNG, WEBP, or GIF images are allowed.';
                    $notice_type = 'error';
                } else {
                    $uploadDirAbs = __DIR__ . '/storage/profile_images';
                    if (!is_dir($uploadDirAbs) && !@mkdir($uploadDirAbs, 0775, true)) {
                        $notice = 'Unable to create upload folder.';
                        $notice_type = 'error';
                    } else {
                        $newName = 'u' . $user_id . '_' . bin2hex(random_bytes(8)) . '.' . $allowed[$mime];
                        $relativePath = 'storage/profile_images/' . $newName;
                        $targetAbs = __DIR__ . '/' . $relativePath;

                        if (!@move_uploaded_file($tmpPath, $targetAbs)) {
                            $notice = 'Upload failed. Please try again.';
                            $notice_type = 'error';
                        } else {
                            $oldStmt = $pdo->prepare("SELECT profile_image FROM users WHERE id = ? LIMIT 1");
                            $oldStmt->execute([$user_id]);
                            $old = (string)$oldStmt->fetchColumn();

                            $up = $pdo->prepare("UPDATE users SET profile_image = ? WHERE id = ?");
                            $up->execute([$relativePath, $user_id]);

                            if ($old !== '' && strpos($old, 'storage/profile_images/') === 0) {
                                $oldAbs = __DIR__ . '/' . $old;
                                if (is_file($oldAbs)) {
                                    @unlink($oldAbs);
                                }
                            }

                            $notice = 'Profile image updated successfully.';
                            $notice_type = 'ok';
                        }
                    }
                }
            }
        }
    }
}

$stmt = $pdo->prepare("SELECT id, username, email, role, status, user_rank, wallet_balance, investment_amount, user_code, profile_image, created_at FROM users WHERE id = ? LIMIT 1");
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
.avatar-upload-form {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 8px;
        }
        .avatar-picker {
            position: relative;
            display: inline-flex;
            cursor: pointer;
        }
        .avatar {
            width:84px; height:84px; border-radius:999px; border:3px solid rgba(255,255,255,0.45);
            display:flex; align-items:center; justify-content:center; font-size:30px; font-weight:900;
            background: rgba(255,255,255,0.14);
            overflow: hidden;
            transition: transform 0.2s ease, filter 0.2s ease;
        }
        .avatar img { width:100%; height:100%; object-fit: cover; display:block; }
        .avatar-overlay {
            position: absolute;
            inset: 0;
            border-radius: 999px;
            background: rgba(15, 23, 42, 0.62);
            color: #fff;
            display: flex;
            align-items: center;
            justify-content: center;
            text-align: center;
            font-size: 10px;
            font-weight: 800;
            letter-spacing: .3px;
            opacity: 0;
            transition: opacity 0.2s ease;
            padding: 10px;
        }
        .avatar-picker:hover .avatar-overlay,
        .avatar-picker:focus-within .avatar-overlay {
            opacity: 1;
        }
        .avatar-picker:hover .avatar {
            transform: scale(1.03);
            filter: brightness(0.92);
        }
        .avatar-file-input { display: none; }
        .avatar-hint {
            font-size: 11px;
            opacity: .9;
            color: #dbeafe;
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
        .alert { margin-top:12px; border-radius:10px; padding:10px 12px; font-size:13px; font-weight:700; }
        .alert.ok { background:#dcfce7; color:#166534; border:1px solid #bbf7d0; }
        .alert.error { background:#fee2e2; color:#991b1b; border:1px solid #fecaca; }
    </style>
</head>
<body>
<div class="wrap">
    <div class="top">
        <a class="btn" href="<?php echo $is_admin ? 'admin_dashboard.php' : 'dashboard.php'; ?>">Back to Dashboard</a>
        <a class="btn alt" href="logout.php">Logout</a>
    </div>

    <?php
    $avatarPath = trim((string)($user['profile_image'] ?? ''));
    $avatarSrc = '';
    if ($avatarPath !== '' && strpos($avatarPath, 'storage/profile_images/') === 0 && is_file(__DIR__ . '/' . $avatarPath)) {
        $avatarSrc = $avatarPath . '?v=' . urlencode((string)@filemtime(__DIR__ . '/' . $avatarPath));
    }
    ?>

    <section class="hero">
        <div>
            <h1><?php echo htmlspecialchars((string)$user['username']); ?></h1>
            <p><?php echo htmlspecialchars((string)$user['email']); ?></p>
            <p style="margin-top:8px;">
                <span class="pill"><?php echo strtoupper(htmlspecialchars((string)$user['role'])); ?></span>
                <span class="pill"><?php echo htmlspecialchars((string)$user['user_rank']); ?></span>
                <span class="pill"><?php echo htmlspecialchars((string)$user['user_code']); ?></span>
                <span class="pill <?php echo (($user['status'] ?? 'active') === 'active') ? 'ok' : 'bad'; ?>">
                    <?php echo strtoupper(htmlspecialchars((string)($user['status'] ?? 'active'))); ?>
                </span>
            </p>
        </div>
        <form method="POST" enctype="multipart/form-data" class="avatar-upload-form" id="avatarUploadForm">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(csrfToken()); ?>">
            <label class="avatar-picker" for="avatarImageInput" title="Upload profile image">
                <div class="avatar">
                    <?php if ($avatarSrc !== ''): ?>
                        <img src="<?php echo htmlspecialchars($avatarSrc); ?>" alt="Profile avatar">
                    <?php else: ?>
                        <?php echo htmlspecialchars($initial); ?>
                    <?php endif; ?>
                </div>
                <span class="avatar-overlay">UPLOAD PHOTO</span>
            </label>
            <input id="avatarImageInput" class="avatar-file-input" type="file" name="avatar_image" accept=".jpg,.jpeg,.png,.webp,.gif,image/jpeg,image/png,image/webp,image/gif">
        </form>
    </section>

    <?php if ($notice !== ''): ?>
        <div class="alert <?php echo $notice_type === 'ok' ? 'ok' : 'error'; ?>"><?php echo htmlspecialchars($notice); ?></div>
    <?php endif; ?>

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
            <div class="row"><strong>Internal ID:</strong> #<?php echo (int)$user['id']; ?></div>
            <div class="row"><strong>Joined:</strong> <?php echo date('M d, Y', strtotime((string)$user['created_at'])); ?></div>
            <div class="row"><strong>Dashboard:</strong> <?php echo $is_admin ? 'Admin Dashboard' : 'User Dashboard'; ?></div>
            <div class="row"><strong>Session Role:</strong> <?php echo $is_admin ? 'Admin' : 'User'; ?></div>
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
<script>
    (function () {
        var input = document.getElementById('avatarImageInput');
        var form = document.getElementById('avatarUploadForm');
        if (!input || !form) return;
        input.addEventListener('change', function () {
            if (input.files && input.files.length > 0) {
                form.submit();
            }
        });
    })();
</script>
<script src="scroll_top.js"></script>
</body>
</html>
