<?php
session_start();
include 'db.php';
include 'functions.php';
ensureWalletLedgerTable($pdo);
ensureNotificationQueueTable($pdo);
ensureComplianceEventsTable($pdo);

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
        $addresses = getPlatformDepositAddresses();
        $network = normalizeNetwork((string)$req['network']);
        $verifyRequired = strtolower((string)(getenv('DEPOSIT_AUTO_VERIFY') ?: 'false'));
        if (in_array($verifyRequired, ['1', 'true', 'yes', 'on'], true)) {
            $verify = verifyOnChainTransaction($network, (string)$req['tx_hash'], $addresses[$network] ?? null);
            if (empty($verify['ok'])) {
                logComplianceEvent(
                    $pdo,
                    (int)$req['user_id'],
                    'deposit_verification_failed',
                    'high',
                    'On-chain verification failed',
                    ['request_id' => (int)$req['id'], 'reason' => $verify['reason'] ?? 'unknown']
                );
                throw new Exception("On-chain verification failed: " . (string)($verify['reason'] ?? 'unknown'));
            }
        }

        processInvestment($pdo, (int)$req['user_id'], (float)$req['amount'], 'Crypto Deposit Approved');
        $upd = $pdo->prepare("UPDATE investment_requests SET status = 'approved', processed_at = NOW() WHERE id = ?");
        $upd->execute([$request_id]);
        queueUserNotification(
            $pdo,
            (int)$req['user_id'],
            'deposit_approved',
            'Deposit Approved',
            'Your investment deposit has been approved and credited to your account.',
            ['request_id' => (int)$req['id'], 'amount' => (float)$req['amount'], 'network' => $network]
        );
    } else {
        $upd = $pdo->prepare("UPDATE investment_requests SET status = 'rejected', processed_at = NOW() WHERE id = ?");
        $upd->execute([$request_id]);
        queueUserNotification(
            $pdo,
            (int)$req['user_id'],
            'deposit_rejected',
            'Deposit Rejected',
            'Your investment request was rejected by admin. Please contact support.',
            ['request_id' => (int)$req['id']]
        );
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
