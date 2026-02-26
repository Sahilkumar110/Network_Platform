<?php
session_start();
include 'db.php';
include 'functions.php';
ensureWalletLedgerTable($pdo);
ensureNotificationQueueTable($pdo);

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: admin_dashboard.php?error=Invalid request");
    exit();
}

$redirect_page = $_POST['redirect_page'] ?? 'admin_dashboard.php';
if (!in_array($redirect_page, ['admin_dashboard.php', 'admin_withdrawals.php'], true)) {
    $redirect_page = 'admin_dashboard.php';
}

if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
    header("Location: {$redirect_page}?error=Invalid session token");
    exit();
}

$withdrawal_id = (int)($_POST['withdrawal_id'] ?? 0);
$decision = $_POST['decision'] ?? '';

if ($withdrawal_id <= 0 || !in_array($decision, ['approve', 'reject'], true)) {
    header("Location: {$redirect_page}?error=Invalid request");
    exit();
}

try {
    $pdo->beginTransaction();

    $stmt = $pdo->prepare("SELECT id, user_id, amount, status FROM withdrawals WHERE id = ? FOR UPDATE");
    $stmt->execute([$withdrawal_id]);
    $withdrawal = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$withdrawal) {
        throw new Exception("Withdrawal request not found.");
    }
    if ($withdrawal['status'] !== 'pending') {
        throw new Exception("Withdrawal request already processed.");
    }

    if ($decision === 'approve') {
        $update = $pdo->prepare("UPDATE withdrawals SET status = 'approved' WHERE id = ?");
        $update->execute([$withdrawal_id]);
        queueUserNotification(
            $pdo,
            (int)$withdrawal['user_id'],
            'withdrawal_approved',
            'Withdrawal Approved',
            'Your withdrawal request has been approved.',
            ['withdrawal_id' => (int)$withdrawal['id'], 'amount' => (float)$withdrawal['amount']]
        );
    } else {
        $update = $pdo->prepare("UPDATE withdrawals SET status = 'rejected' WHERE id = ?");
        $update->execute([$withdrawal_id]);

        applyWalletDelta(
            $pdo,
            (int)$withdrawal['user_id'],
            (float)$withdrawal['amount'],
            'withdrawal_refund',
            'Withdrawal rejected by admin and refunded',
            'withdrawals',
            (int)$withdrawal['id']
        );
        queueUserNotification(
            $pdo,
            (int)$withdrawal['user_id'],
            'withdrawal_rejected',
            'Withdrawal Rejected',
            'Your withdrawal request was rejected and funds were returned to your wallet.',
            ['withdrawal_id' => (int)$withdrawal['id'], 'amount' => (float)$withdrawal['amount']]
        );
    }

    $pdo->commit();
    header("Location: {$redirect_page}?success=Withdrawal request updated");
    exit();
} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    header("Location: {$redirect_page}?error=" . urlencode($e->getMessage()));
    exit();
}
?>
