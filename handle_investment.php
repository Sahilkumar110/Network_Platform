<?php
session_start();
include 'db.php';
include 'functions.php';
ensureWalletLedgerTable($pdo);

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

ensureInvestmentRequestsTable($pdo);

$request_id = (int)($_POST['request_id'] ?? 0);
$decision = $_POST['decision'] ?? '';
if ($request_id <= 0 || !in_array($decision, ['approve', 'reject'], true)) {
    header("Location: admin_dashboard.php?error=Invalid request");
    exit();
}

try {
    $pdo->beginTransaction();
    $stmt = $pdo->prepare("SELECT * FROM investment_requests WHERE id = ? FOR UPDATE");
    $stmt->execute([$request_id]);
    $req = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$req) {
        throw new Exception("Investment request not found.");
    }
    if ($req['status'] !== 'pending') {
        throw new Exception("Investment request already processed.");
    }

    if ($decision === 'approve') {
        if (investmentTxHashExists($pdo, (string)$req['network'], (string)$req['tx_hash'], (int)$req['id'])) {
            throw new Exception("Duplicate TX hash detected. This investment request cannot be approved.");
        }
        processInvestment($pdo, (int)$req['user_id'], (float)$req['amount'], 'Crypto Deposit Approved');
        $upd = $pdo->prepare("UPDATE investment_requests SET status = 'approved', processed_at = NOW() WHERE id = ?");
        $upd->execute([$request_id]);
    } else {
        $upd = $pdo->prepare("UPDATE investment_requests SET status = 'rejected', processed_at = NOW() WHERE id = ?");
        $upd->execute([$request_id]);
    }

    $pdo->commit();
    header("Location: admin_dashboard.php?success=Investment request updated");
    exit();
} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    header("Location: admin_dashboard.php?error=" . urlencode($e->getMessage()));
    exit();
}
?>
