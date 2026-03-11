<?php
include 'db.php';
include 'functions.php';
ensureWalletLedgerTable($pdo);
requireCronAccess();
ensureCronRunsTable($pdo);

$today = date('Y-m-d');

$stmt_check = $pdo->prepare("SELECT setting_value FROM settings WHERE setting_key = 'last_cron_run'");
$stmt_check->execute();
$last_run = $stmt_check->fetchColumn();

if ($last_run === $today) {
    die("Daily profit has already been processed for today.");
}

$rate_percent = 1.0;
$rate_decimal = 0.01;

try {
    $pdo->beginTransaction();

    $users_stmt = $pdo->query(
        "SELECT id, wallet_balance
         FROM users
         WHERE status = 'active'
           AND wallet_balance > 0"
    );
    $users = $users_stmt->fetchAll(PDO::FETCH_ASSOC);

    $processed = 0;
    $skipped = 0;
    $failed = 0;
    $total_profit = 0.00;
    $daily_description = "Daily 1% fixed on wallet balance [{$today}]";

    $profit_log = $pdo->prepare(
        "INSERT INTO transactions (user_id, amount, type, level, description, created_at)
         VALUES (?, ?, 'daily_profit', NULL, ?, NOW())"
    );
    $profit_exists = $pdo->prepare(
        "SELECT id
         FROM transactions
         WHERE user_id = ?
           AND type = 'daily_profit'
           AND DATE(created_at) = ?
         LIMIT 1"
    );

    foreach ($users as $user) {
        $wallet_balance = (float)$user['wallet_balance'];

        if ($wallet_balance < 0) {
            $skipped++;
            continue;
        }

        $profit = round($wallet_balance * $rate_decimal, 2);
        if ($profit <= 0) {
            $skipped++;
            continue;
        }

        // Idempotent guard: ensure this user is credited only once per day.
        $profit_exists->execute([(int)$user['id'], $today]);
        if ($profit_exists->fetchColumn()) {
            $skipped++;
            continue;
        }

        try {
            applyWalletDelta(
                $pdo,
                (int)$user['id'],
                $profit,
                'daily_profit',
                $daily_description,
                'cron_profit',
                null
            );
            $profit_log->execute([$user['id'], $profit, $daily_description]);
            queueUserNotification(
                $pdo,
                (int)$user['id'],
                'daily_profit',
                'Daily Profit Credited',
                'Daily profit of $' . number_format($profit, 2) . ' has been credited to your wallet on ' . $today . '.',
                ['amount' => $profit, 'date' => $today]
            );
            createUserNotification(
                $pdo,
                (int)$user['id'],
                'Daily Profit Credited',
                'Daily profit of $' . number_format($profit, 2) . ' has been credited to your wallet.',
                'daily_profit'
            );

            $processed++;
            $total_profit += $profit;
        } catch (Exception $userError) {
            $failed++;
            appLog('warning', 'Daily profit skipped for user', [
                'user_id' => (int)$user['id'],
                'reason' => $userError->getMessage()
            ]);
            continue;
        }
    }

    // Keep all ranks consistent in one pass after profit distribution.
    recalculateAllUserRanks($pdo);

    $mark_run = $pdo->prepare("UPDATE settings SET setting_value = ? WHERE setting_key = 'last_cron_run'");
    $mark_run->execute([$today]);

    $pdo->commit();
    $msg = "Daily profit processed for $processed users at $rate_percent%. Skipped: $skipped. Failed: $failed. Total credited: $" . number_format($total_profit, 2);
    logCronRun($pdo, 'cron_profit', 'success', $msg);
    echo $msg;
} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    logCronRun($pdo, 'cron_profit', 'failed', $e->getMessage());
    die("Error processing daily profit: " . $e->getMessage());
}
?>
