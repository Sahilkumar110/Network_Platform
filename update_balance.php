<?php
session_start();
include 'db.php';
include 'functions.php';
ensureWalletLedgerTable($pdo);

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        header("Location: admin_dashboard.php?error=Invalid session token");
        exit();
    }
    $user_id = (int)$_POST['user_id'];
    $amount = (float)$_POST['amount'];
    $action = $_POST['action'];
    $admin_id = $_SESSION['user_id'];

    if ($amount <= 0 || !in_array($action, ['add', 'subtract'], true)) {
        header("Location: admin_dashboard.php?error=Invalid request");
        exit();
    }

    try {
        $pdo->beginTransaction();

        if ($action === 'add') {
            // Admin credit is treated as both wallet credit and investment credit.
            // This ensures referral commissions run on the invested amount.
            $stmt = $pdo->prepare(
                "UPDATE users
                 SET investment_amount = investment_amount + ?
                 WHERE id = ?"
            );
            $stmt->execute([$amount, $user_id]);
            if ($stmt->rowCount() === 0) {
                throw new Exception("User not found.");
            }

            applyWalletDelta(
                $pdo,
                $user_id,
                $amount,
                'admin_credit',
                'Admin balance/investment credit',
                'admin_user',
                $admin_id
            );

            $tx_stmt = $pdo->prepare(
                "INSERT INTO transactions (user_id, amount, type, level, description, created_at)
                 VALUES (?, ?, 'deposit', NULL, 'Admin balance/investment credit', NOW())"
            );
            $tx_stmt->execute([$user_id, $amount]);

            distributeCommissions($pdo, $user_id, $amount);
            updateUserRank($pdo, $user_id);
        } else {
            applyWalletDelta(
                $pdo,
                $user_id,
                -$amount,
                'admin_debit',
                'Admin manual wallet deduction',
                'admin_user',
                $admin_id
            );
        }

        $log_stmt = $pdo->prepare("INSERT INTO transaction_logs (admin_id, user_id, action_type, amount) VALUES (?, ?, ?, ?)");
        $log_stmt->execute([$admin_id, $user_id, $action, $amount]);

        $pdo->commit();
        header("Location: admin_dashboard.php?success=Balance Updated and Logged");
    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        header("Location: admin_dashboard.php?error=" . urlencode($e->getMessage()));
    }
    exit();
}
