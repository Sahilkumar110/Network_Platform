<?php
session_start();
include 'db.php';
include 'functions.php';
ensureWalletLedgerTable($pdo);

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    die("Unauthorized");
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    die("Invalid request");
}
if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
    die("Invalid session token");
}

// Configuration: Daily Profit Percentage (e.g., 1% = 0.01)
$daily_rate = 0.01; 
$today = date('Y-m-d');
$admin_id = (int)$_SESSION['user_id'];

try {
    $pdo->beginTransaction();

    // 1. Get all active users with wallet balance
    $stmt = $pdo->query(
        "SELECT id, wallet_balance
         FROM users
         WHERE status = 'active'
           AND wallet_balance > 0"
    );
    $users = $stmt->fetchAll();

    $count = 0;
    $skipped = 0;
    $failed = 0;
    foreach ($users as $user) {
        $wallet_balance = (float)$user['wallet_balance'];
        if ($wallet_balance < 0) {
            $skipped++;
            continue;
        }

        $profit = round($wallet_balance * $daily_rate, 2);

        if ($profit <= 0) {
            $skipped++;
            continue;
        }

        try {
            // 2. Add profit to wallet with auditable ledger entry
            applyWalletDelta(
                $pdo,
                (int)$user['id'],
                (float)$profit,
                'daily_profit',
                'Daily 1% fixed on wallet balance',
                'auto_profit',
                null
            );

            // 3. Log the transaction so user sees "Daily Profit" in admin transaction log
            $log = $pdo->prepare(
                "INSERT INTO transaction_logs (admin_id, user_id, action_type, amount, reason, created_at)
                 VALUES (?, ?, 'add', ?, 'Daily Profit', NOW())"
            );
            $log->execute([$admin_id, $user['id'], $profit]);

            $count++;
        } catch (Exception $userError) {
            $failed++;
            appLog('warning', 'Auto profit skipped for user', [
                'user_id' => (int)$user['id'],
                'reason' => $userError->getMessage()
            ]);
            continue;
        }
    }

    recalculateAllUserRanks($pdo);

    $pdo->commit();
    header("Location: admin_dashboard.php?success=Distributed profit to $count users for $today (skipped: $skipped, failed: $failed)");

} catch (Exception $e) {
    $pdo->rollBack();
    die("Automation Error: " . $e->getMessage());
}
?>
