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
ensureKycReviewHistoryTable($pdo);

$message = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        $message = ['type' => 'error', 'text' => 'Invalid session token'];
    } else {
        $full_name = trim((string)($_POST['full_name'] ?? ''));
        $dob = trim((string)($_POST['date_of_birth'] ?? ''));
        $country = strtoupper(trim((string)($_POST['country_code'] ?? '')));
        $doc_type = trim((string)($_POST['document_type'] ?? ''));
        $doc_no = trim((string)($_POST['document_number'] ?? ''));
        $doc_ref = trim((string)($_POST['document_ref'] ?? ''));

        try {
            if ($full_name === '' || $dob === '' || strlen($country) !== 2 || $doc_type === '' || $doc_no === '') {
                throw new Exception("All required fields must be filled.");
            }
            $check = $pdo->prepare("SELECT id FROM kyc_profiles WHERE user_id = ? LIMIT 1");
            $check->execute([$user_id]);
            $existing = $check->fetchColumn();
            if ($existing) {
                $upd = $pdo->prepare(
                    "UPDATE kyc_profiles
                     SET full_name=?, date_of_birth=?, country_code=?, document_type=?, document_number=?, document_ref=?, status='pending', review_note=NULL, reviewed_at=NULL, created_at=NOW()
                     WHERE user_id=?"
                );
                $upd->execute([$full_name, $dob, $country, $doc_type, $doc_no, $doc_ref ?: null, $user_id]);
            } else {
                $ins = $pdo->prepare(
                    "INSERT INTO kyc_profiles (user_id, full_name, date_of_birth, country_code, document_type, document_number, document_ref, status, created_at)
                     VALUES (?, ?, ?, ?, ?, ?, ?, 'pending', NOW())"
                );
                $ins->execute([$user_id, $full_name, $dob, $country, $doc_type, $doc_no, $doc_ref ?: null]);
            }
            $message = ['type' => 'success', 'text' => 'KYC submitted. Awaiting admin verification.'];
        } catch (Exception $e) {
            $message = ['type' => 'error', 'text' => $e->getMessage()];
        }
    }
}

$stmt = $pdo->prepare("SELECT * FROM kyc_profiles WHERE user_id = ? LIMIT 1");
$stmt->execute([$user_id]);
$kyc = $stmt->fetch(PDO::FETCH_ASSOC);

$historyStmt = $pdo->prepare(
    "SELECT status, note, reviewed_at, reviewed_by
     FROM kyc_review_history
     WHERE user_id = ?
     ORDER BY reviewed_at DESC
     LIMIT 10"
);
$historyStmt->execute([$user_id]);
$kyc_history = $historyStmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!doctype html>
<html lang="en">
<head>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>KYC Verification</title>
    <style>
        :root {
            --primary: #1e3a8a;
            --secondary: #3b82f6;
            --bg: #f8fafc;
            --text: #0f172a;
            --muted: #64748b;
        }
        body {
            font-family: 'Inter', 'Segoe UI', sans-serif;
            background: var(--bg);
            margin: 0;
            color: var(--text);
        }
        .main-header {
            background: #ffffff;
            padding: 0 20px;
            height: 70px;
            display: flex;
            align-items: center;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
            position: sticky;
            top: 0;
            z-index: 1000;
        }
        .nav-container {
            width: 100%;
            max-width: 1100px;
            margin: 0 auto;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }
        .logo {
            font-size: 22px;
            font-weight: 800;
            color: var(--primary);
            letter-spacing: -1px;
            text-decoration: none;
        }
        .logo span { color: var(--secondary); font-weight: 500; }
        .back-btn {
            text-decoration: none;
            background: #e2e8f0;
            color: #1e293b;
            padding: 10px 14px;
            border-radius: 10px;
            font-weight: 700;
            font-size: 13px;
        }
        .container {
            max-width: 850px;
            margin: 0 auto;
            padding: 26px 16px 40px;
        }
        .kyc-card {
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 18px;
            padding: 24px;
            box-shadow: 0 10px 24px rgba(15, 23, 42, 0.06);
        }
        .kyc-title {
            margin: 0;
            font-size: 30px;
            letter-spacing: -0.02em;
        }
        .kyc-sub {
            margin: 8px 0 18px;
            color: var(--muted);
            font-size: 14px;
        }
        .status-wrap {
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 12px;
            padding: 12px;
            margin-bottom: 14px;
            font-size: 13px;
        }
        .status-badge {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            border-radius: 999px;
            padding: 5px 10px;
            font-size: 11px;
            font-weight: 800;
            letter-spacing: 0.03em;
            text-transform: uppercase;
            border: 1px solid #cbd5e1;
            background: #f1f5f9;
            color: #334155;
        }
        .status-approved {
            background: #dcfce7;
            color: #166534;
            border-color: #86efac;
        }
        .status-verified {
            background: #dcfce7;
            color: #166534;
            border-color: #86efac;
        }
        .status-pending {
            background: #fef3c7;
            color: #92400e;
            border-color: #fde68a;
        }
        .status-rejected {
            background: #fee2e2;
            color: #991b1b;
            border-color: #fecaca;
        }
        .grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 12px;
        }
        .field {
            display: flex;
            flex-direction: column;
            gap: 6px;
            margin-bottom: 10px;
        }
        .field-full { grid-column: 1 / -1; }
        label {
            font-size: 12px;
            color: #475569;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.03em;
        }
        input, select {
            width: 100%;
            box-sizing: border-box;
            padding: 12px;
            border: 1px solid #cbd5e1;
            border-radius: 10px;
            font-size: 14px;
            background: #ffffff;
            color: #0f172a;
        }
        input:focus, select:focus {
            outline: none;
            border-color: var(--secondary);
            box-shadow: 0 0 0 4px rgba(59, 130, 246, 0.12);
        }
        .submit-btn {
            width: 100%;
            padding: 13px 16px;
            background: linear-gradient(135deg, #1d4ed8 0%, #1e3a8a 100%);
            color: #fff;
            border: none;
            border-radius: 10px;
            font-weight: 800;
            font-size: 14px;
            cursor: pointer;
        }
        .alert-success, .alert-error {
            padding: 10px 12px;
            border-radius: 10px;
            margin-bottom: 12px;
            font-size: 13px;
            border: 1px solid transparent;
        }
        .alert-success { background:#dcfce7; color:#166534; border-color:#86efac; }
        .alert-error { background:#fee2e2; color:#991b1b; border-color:#fecaca; }
        .status-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 10px;
            margin-bottom: 14px;
        }
        .status-item {
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 12px;
            padding: 10px 12px;
            font-size: 12px;
            color: #475569;
        }
        .status-item strong { color: #0f172a; font-size: 13px; display: block; margin-bottom: 4px; }
        .history-list { margin: 0; padding: 0; list-style: none; display: grid; gap: 8px; }
        .history-item {
            border: 1px solid #e2e8f0;
            border-radius: 12px;
            padding: 10px 12px;
            background: #ffffff;
            font-size: 12px;
            color: #475569;
        }
        .history-item strong { color: #0f172a; font-size: 13px; }
        .history-badge {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            border-radius: 999px;
            padding: 4px 8px;
            font-size: 10px;
            font-weight: 800;
            text-transform: uppercase;
            background: #f1f5f9;
            color: #334155;
            border: 1px solid #cbd5e1;
            margin-right: 6px;
        }
        @media (max-width: 768px) {
            .main-header { height: auto; padding: 10px 12px; }
            .nav-container { gap: 8px; flex-wrap: wrap; }
            .grid { grid-template-columns: 1fr; }
            .kyc-title { font-size: 26px; }
        }
    </style>
    <link rel="stylesheet" href="responsive.css">
</head>
<body>
    <header class="main-header">
        <div class="nav-container">
            <a href="index.php" class="logo">NETWORK<span>PLATFORM</span></a>
            <a href="dashboard.php" class="back-btn">Back to Dashboard</a>
        </div>
    </header>

    <div class="container">
    <div class="kyc-card">
        <h2 class="kyc-title">KYC Verification</h2>
        <p class="kyc-sub">Submit your verification details to unlock full platform features such as withdrawals and secure investment requests.</p>
        <?php if ($message): ?>
            <div class="alert-<?php echo htmlspecialchars($message['type']); ?>"><?php echo htmlspecialchars($message['text']); ?></div>
        <?php endif; ?>
        <?php $kyc_status = strtolower((string)($kyc['status'] ?? 'not_submitted')); ?>
        <div class="status-wrap">
            <div>Status:
                <span class="status-badge <?php echo ($kyc_status === 'verified' || $kyc_status === 'approved') ? 'status-verified' : ($kyc_status === 'pending' ? 'status-pending' : ($kyc_status === 'rejected' ? 'status-rejected' : '')); ?>">
                    <?php echo htmlspecialchars(strtoupper((string)($kyc['status'] ?? 'not_submitted'))); ?>
                </span>
            </div>
        </div>
        <div class="status-grid">
            <div class="status-item">
                <strong>Upload Status</strong>
                <?php if (!empty($kyc['created_at'])): ?>
                    Submitted on <?php echo htmlspecialchars(date('M d, Y H:i', strtotime((string)$kyc['created_at']))); ?>
                <?php else: ?>
                    Not submitted
                <?php endif; ?>
            </div>
            <div class="status-item">
                <strong>Review Status</strong>
                <?php if (!empty($kyc['reviewed_at'])): ?>
                    Reviewed on <?php echo htmlspecialchars(date('M d, Y H:i', strtotime((string)$kyc['reviewed_at']))); ?>
                <?php else: ?>
                    Waiting for review
                <?php endif; ?>
            </div>
            <div class="status-item">
                <strong>Document Reference</strong>
                <?php if (!empty($kyc['document_ref'])): ?>
                    Provided
                <?php else: ?>
                    Not provided
                <?php endif; ?>
            </div>
        </div>
        <?php if (!empty($kyc['review_note'])): ?>
            <div class="status-wrap">Review Note: <?php echo htmlspecialchars((string)$kyc['review_note']); ?></div>
        <?php endif; ?>
        <div class="status-wrap">
            <strong>Review History</strong>
            <?php if (!empty($kyc_history)): ?>
                <ul class="history-list">
                    <?php foreach ($kyc_history as $h): ?>
                        <li class="history-item">
                            <span class="history-badge"><?php echo htmlspecialchars(strtoupper((string)$h['status'])); ?></span>
                            <strong><?php echo htmlspecialchars(date('M d, Y H:i', strtotime((string)$h['reviewed_at']))); ?></strong>
                            <?php if (!empty($h['note'])): ?>
                                <div>Reason: <?php echo htmlspecialchars((string)$h['note']); ?></div>
                            <?php endif; ?>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php else: ?>
                <div style="margin-top:6px;">No review history yet.</div>
            <?php endif; ?>
        </div>
        <form method="post">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(csrfToken()); ?>">
            <div class="grid">
                <div class="field">
                    <label>Full Name</label>
                    <input type="text" name="full_name" required value="<?php echo htmlspecialchars((string)($kyc['full_name'] ?? '')); ?>">
                </div>
                <div class="field">
                    <label>Date of Birth</label>
                    <input type="date" name="date_of_birth" required value="<?php echo htmlspecialchars((string)($kyc['date_of_birth'] ?? '')); ?>">
                </div>
                <div class="field">
                    <label>Country Code (2-letter)</label>
                    <input type="text" name="country_code" maxlength="2" required value="<?php echo htmlspecialchars((string)($kyc['country_code'] ?? '')); ?>">
                </div>
                <div class="field">
                    <label>Document Type</label>
                    <select name="document_type" required>
                        <option value="">Select</option>
                        <option value="passport" <?php echo (($kyc['document_type'] ?? '') === 'passport') ? 'selected' : ''; ?>>Passport</option>
                        <option value="id_card" <?php echo (($kyc['document_type'] ?? '') === 'id_card') ? 'selected' : ''; ?>>ID Card</option>
                        <option value="driver_license" <?php echo (($kyc['document_type'] ?? '') === 'driver_license') ? 'selected' : ''; ?>>Driver License</option>
                    </select>
                </div>
                <div class="field field-full">
                    <label>Document Number</label>
                    <input type="text" name="document_number" required value="<?php echo htmlspecialchars((string)($kyc['document_number'] ?? '')); ?>">
                </div>
                <div class="field field-full">
                    <label>Document Reference URL (optional)</label>
                    <input type="text" name="document_ref" value="<?php echo htmlspecialchars((string)($kyc['document_ref'] ?? '')); ?>">
                </div>
            </div>
            <button type="submit" class="submit-btn">Submit KYC</button>
        </form>
    </div>
    </div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="scroll_top.js"></script>
</body>
</html>
