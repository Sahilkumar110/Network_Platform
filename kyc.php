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
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>KYC Verification</title>
    <style>
        body { font-family: Arial, sans-serif; background:#f1f5f9; margin:0; padding:20px; }
        .card { max-width:700px; margin:20px auto; background:#fff; border:1px solid #e2e8f0; border-radius:12px; padding:18px; }
        input, select, button { width:100%; box-sizing:border-box; padding:10px; margin:8px 0; border:1px solid #cbd5e1; border-radius:8px; }
        button { background:#1e3a8a; color:#fff; border:none; font-weight:700; cursor:pointer; }
        .alert-success { background:#dcfce7; color:#166534; padding:10px; border-radius:8px; }
        .alert-error { background:#fee2e2; color:#991b1b; padding:10px; border-radius:8px; }
    </style>
</head>
<body>
    <div class="card">
        <h2>KYC Verification</h2>
        <p><a href="dashboard.php">Back to Dashboard</a></p>
        <?php if ($message): ?>
            <div class="alert-<?php echo htmlspecialchars($message['type']); ?>"><?php echo htmlspecialchars($message['text']); ?></div>
        <?php endif; ?>
        <p>Status: <strong><?php echo htmlspecialchars(strtoupper((string)($kyc['status'] ?? 'not_submitted'))); ?></strong></p>
        <?php if (!empty($kyc['review_note'])): ?>
            <p>Review Note: <?php echo htmlspecialchars((string)$kyc['review_note']); ?></p>
        <?php endif; ?>
        <form method="post">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(csrfToken()); ?>">
            <label>Full Name</label>
            <input type="text" name="full_name" required value="<?php echo htmlspecialchars((string)($kyc['full_name'] ?? '')); ?>">
            <label>Date of Birth</label>
            <input type="date" name="date_of_birth" required value="<?php echo htmlspecialchars((string)($kyc['date_of_birth'] ?? '')); ?>">
            <label>Country Code (2-letter)</label>
            <input type="text" name="country_code" maxlength="2" required value="<?php echo htmlspecialchars((string)($kyc['country_code'] ?? '')); ?>">
            <label>Document Type</label>
            <select name="document_type" required>
                <option value="">Select</option>
                <option value="passport" <?php echo (($kyc['document_type'] ?? '') === 'passport') ? 'selected' : ''; ?>>Passport</option>
                <option value="id_card" <?php echo (($kyc['document_type'] ?? '') === 'id_card') ? 'selected' : ''; ?>>ID Card</option>
                <option value="driver_license" <?php echo (($kyc['document_type'] ?? '') === 'driver_license') ? 'selected' : ''; ?>>Driver License</option>
            </select>
            <label>Document Number</label>
            <input type="text" name="document_number" required value="<?php echo htmlspecialchars((string)($kyc['document_number'] ?? '')); ?>">
            <label>Document Reference URL (optional)</label>
            <input type="text" name="document_ref" value="<?php echo htmlspecialchars((string)($kyc['document_ref'] ?? '')); ?>">
            <button type="submit">Submit KYC</button>
        </form>
    </div>
<script src="scroll_top.js"></script>
</body>
</html>
