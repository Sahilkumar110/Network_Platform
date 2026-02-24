<?php
session_start();
include 'db.php';

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: admin_dashboard.php?error=Invalid request");
    exit();
}

$withdrawal_id = (int)($_POST['withdrawal_id'] ?? 0);
$decision = $_POST['decision'] ?? '';

if ($withdrawal_id <= 0 || !in_array($decision, ['approve', 'reject'], true)) {
    header("Location: admin_dashboard.php?error=Invalid request");
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
    } else {
        $update = $pdo->prepare("UPDATE withdrawals SET status = 'rejected' WHERE id = ?");
        $update->execute([$withdrawal_id]);

        $refund = $pdo->prepare("UPDATE users SET wallet_balance = wallet_balance + ? WHERE id = ?");
        $refund->execute([(float)$withdrawal['amount'], (int)$withdrawal['user_id']]);
    }

    $pdo->commit();
    header("Location: admin_dashboard.php?success=Withdrawal request updated");
    exit();
} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    header("Location: admin_dashboard.php?error=" . urlencode($e->getMessage()));
    exit();
}
?>
