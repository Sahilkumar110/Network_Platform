<?php
session_start();
include 'db.php';
include 'functions.php';

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit();
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: admin_dashboard.php?error=Invalid request");
    exit();
}
if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
    header("Location: admin_dashboard.php?error=Invalid session token");
    exit();
}

ensureKycProfilesTable($pdo);
ensureKycReviewHistoryTable($pdo);
ensureComplianceEventsTable($pdo);
ensureNotificationQueueTable($pdo);

$kyc_id = (int)($_POST['kyc_id'] ?? 0);
$decision = trim((string)($_POST['decision'] ?? ''));
$note = trim((string)($_POST['review_note'] ?? ''));
if ($kyc_id <= 0 || !in_array($decision, ['approve', 'reject'], true)) {
    header("Location: admin_dashboard.php?error=Invalid KYC request");
    exit();
}

try {
    $pdo->beginTransaction();
    $stmt = $pdo->prepare("SELECT id, user_id, status FROM kyc_profiles WHERE id = ? FOR UPDATE");
    $stmt->execute([$kyc_id]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$row) {
        throw new Exception("KYC profile not found.");
    }
    if ($row['status'] !== 'pending') {
        throw new Exception("KYC profile already processed.");
    }

    $status = $decision === 'approve' ? 'verified' : 'rejected';
    $upd = $pdo->prepare("UPDATE kyc_profiles SET status = ?, review_note = ?, reviewed_at = NOW() WHERE id = ?");
    $upd->execute([$status, $note ?: null, $kyc_id]);

    $history = $pdo->prepare(
        "INSERT INTO kyc_review_history (kyc_id, user_id, reviewed_by, status, note, reviewed_at)
         VALUES (?, ?, ?, ?, ?, NOW())"
    );
    $history->execute([
        $kyc_id,
        (int)$row['user_id'],
        (int)($_SESSION['user_id'] ?? 0),
        $status,
        $note ?: null
    ]);

    logComplianceEvent(
        $pdo,
        (int)$row['user_id'],
        'kyc_review',
        $decision === 'approve' ? 'info' : 'medium',
        "KYC {$status}",
        ['kyc_id' => $kyc_id, 'note' => $note]
    );

    queueUserNotification(
        $pdo,
        (int)$row['user_id'],
        'kyc_' . $status,
        'KYC ' . ucfirst($status),
        $decision === 'approve'
            ? 'Your KYC is verified. You can now use full platform features.'
            : 'Your KYC was rejected. Please review and re-submit your documents.',
        ['kyc_id' => $kyc_id, 'note' => $note]
    );

    $pdo->commit();
    header("Location: admin_dashboard.php?success=KYC {$status}");
    exit();
} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    header("Location: admin_dashboard.php?error=" . urlencode($e->getMessage()));
    exit();
}

